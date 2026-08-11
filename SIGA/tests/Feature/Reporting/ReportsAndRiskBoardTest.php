<?php

namespace Tests\Feature\Reporting;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\AcademicRisk\RiskBoard\Application\UseCases\EvaluateRiskBoardUseCase;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;
use Src\AcademicRisk\RiskBoard\Presentation\Livewire\RiskBoardComponent;
use Src\Reporting\OfferReport\Presentation\Livewire\OfferReportComponent;
use Src\Reporting\TeacherLoadReport\Presentation\Livewire\TeacherLoadReportComponent;
use Tests\TestCase;

/**
 * RE-01, RE-02 and RE-04 through the real stack.
 *
 * The PDF assertions genuinely drive headless Chromium through
 * Browsershot — they are the only way to know the report pipeline works
 * rather than merely type-checks.
 */
class ReportsAndRiskBoardTest extends TestCase
{
    use RefreshDatabase;

    private const TERM = '2026-II';

    /**
     * RE-01's core acceptance criterion: one request, two files, same
     * information, inside the time budget.
     */
    public function test_it_generates_both_the_spreadsheet_and_the_pdf_of_a_term(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $component = Livewire::test(OfferReportComponent::class)
            ->assertSet('term', self::TERM)
            ->call('generate');

        $disk = Storage::disk('local');

        $this->assertTrue($disk->exists('reports/offer/2026-ii.xlsx'), 'The .xlsx artifact was not produced.');
        $this->assertTrue($disk->exists('reports/offer/2026-ii.pdf'), 'The .pdf artifact was not produced.');
        $this->assertGreaterThan(0, $disk->size('reports/offer/2026-ii.pdf'));

        $elapsed = $component->get('lastGenerationSeconds');
        $this->assertNotNull($elapsed, 'The generation time was not measured.');
        $this->assertLessThan(30, $elapsed, 'RE-01 requires the report in under 30 seconds.');

        $disk->deleteDirectory('reports');
    }

    public function test_the_offer_report_screen_lists_the_groups_of_the_selected_term(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $this->get(route('reporting.offerreport.index'))
            ->assertOk()
            ->assertSee('ISW-521-G01')
            ->assertSee('Sin asignar');
    }

    public function test_downloading_before_generating_does_not_serve_a_file(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        Storage::disk('local')->deleteDirectory('reports');

        Livewire::test(OfferReportComponent::class)
            ->call('downloadExcel')
            ->assertNoFileDownloaded();
    }

    /**
     * RE-02: the legend must appear on the report, and the alert colour
     * must match the teacher's actual situation.
     */
    public function test_the_teacher_load_report_shows_the_alert_and_the_mandatory_legend(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $overloaded = Teacher::query()->where('identity_card', '1-1111-1111')->firstOrFail();

        $this->get(route('reporting.teacherloadreport.index', ['teacher' => $overloaded->id, 'term' => self::TERM]))
            ->assertOk()
            ->assertSee('Sobrecontratación')
            ->assertSee('Jornada indicativa — sujeta a confirmación de RRHH');
    }

    public function test_the_teacher_load_report_exports_a_pdf(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $teacher = Teacher::query()->where('identity_card', '1-1111-1111')->firstOrFail();

        Livewire::test(TeacherLoadReportComponent::class)
            ->set('teacherId', (string) $teacher->id)
            ->set('term', self::TERM)
            ->call('exportPdf')
            ->assertFileDownloaded();
    }

    public function test_the_teacher_load_report_exports_a_spreadsheet(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $teacher = Teacher::query()->where('identity_card', '1-1111-1111')->firstOrFail();

        Livewire::test(TeacherLoadReportComponent::class)
            ->set('teacherId', (string) $teacher->id)
            ->set('term', self::TERM)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    /**
     * RE-04's two acceptance criteria, in one pass: creating a group
     * without a teacher makes it appear on the board, and assigning one
     * makes it disappear — with no page reload involved, because the
     * board is recomputed on every render.
     */
    public function test_a_risk_appears_when_the_data_breaks_and_disappears_when_it_is_fixed(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $this->get(route('academicrisk.riskboard.index'))
            ->assertOk()
            ->assertDontSee('ISW-999-G01');

        $orphan = Group::factory()->create([
            'code' => 'ISW-999-G01',
            'term' => self::TERM,
            'teacher_id' => null,
            'assigned_workload' => 0,
        ]);

        Livewire::test(RiskBoardComponent::class)
            ->assertSee('ISW-999-G01')
            ->assertSee('Grupo sin docente asignado');

        $orphan->update(['teacher_id' => Teacher::query()->value('id')]);

        Livewire::test(RiskBoardComponent::class)->assertDontSee('ISW-999-G01');
    }

    public function test_the_board_groups_the_seeded_risks_by_level(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedOffer();

        $board = app(EvaluateRiskBoardUseCase::class)->handle();

        $this->assertSame(2, $board->countAt(RiskLevel::High), 'One group with no teacher and one with no classroom.');
        $this->assertSame(1, $board->countAt(RiskLevel::Medium), 'The overloaded teacher.');
        $this->assertSame(1, $board->countAt(RiskLevel::Low), 'The group below the enrollment threshold.');
    }

    public function test_the_report_screens_are_closed_to_users_without_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('reporting.offerreport.index'))->assertForbidden();
        $this->get(route('reporting.teacherloadreport.index'))->assertForbidden();
        $this->get(route('academicrisk.riskboard.index'))->assertForbidden();
    }

    /**
     * A minimal offer carrying exactly one case of each situation the
     * three requirements care about.
     */
    private function seedOffer(): void
    {
        $overloaded = Teacher::factory()->create([
            'identity_card' => '1-1111-1111',
            'name' => 'Ana Rodríguez',
            'reference_workload' => 1.00,
        ]);

        $classroom = Classroom::factory()->create(['name' => 'Aula 101', 'capacity' => 30]);

        // 1.25 in one term: over-contracted for RE-02, in conflict for RE-04.
        foreach (['ISW-521-G01', 'ISW-521-G02', 'ISW-521-G03', 'ISW-521-G04', 'ISW-521-G05'] as $code) {
            $this->group($code, $overloaded->id, $classroom->id, 25, 0.25);
        }

        // No teacher -> High.
        $this->group('ISW-211-G02', null, $classroom->id, 20, 0.0);
        // No classroom -> High.
        $this->group('ISW-111-G01', $overloaded->id, null, 20, 0.0);
        // Below the enrollment threshold -> Low.
        $this->group('ISW-111-G02', $overloaded->id, $classroom->id, 3, 0.0);
    }

    private function group(string $code, ?int $teacherId, ?int $classroomId, int $enrollment, float $workload): void
    {
        Group::query()->create([
            'code' => $code,
            'course_code' => substr($code, 0, 7),
            'term' => self::TERM,
            'teacher_id' => $teacherId,
            'classroom_id' => $classroomId,
            'estimated_enrollment' => $enrollment,
            'assigned_workload' => $workload,
            'modality' => Modality::InPerson->value,
            'status' => GroupStatus::Open->value,
        ]);
    }

    private function superadmin(): User
    {
        $this->seed(PermissionSeeder::class);

        $role = Role::query()->create(['name' => 'Superadmin']);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
