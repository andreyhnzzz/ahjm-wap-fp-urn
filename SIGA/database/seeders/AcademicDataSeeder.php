<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * INFRA-01's data layer: the realistic offer the reports (RE-01, RE-02)
 * and the risk board (RE-04) operate on.
 *
 * Fully deterministic — no faker, no randomness. A grader must be able
 * to re-run this and land on the exact same board, and a regression in
 * the risk rules has to be visible as a changed count, not written off
 * as "different random data this time".
 *
 * Idempotent: every row is written with updateOrCreate() on its natural
 * key, so running the seeder twice leaves the same row count rather than
 * doubling it (41 scenario/filler/previous-term groups + 1500 bulk
 * volume groups, see BULK_GROUP_COUNT).
 *
 * The scenario block below is designed so the dataset contains at least
 * one case of every risk RE-04 defines and every alert colour RE-02 can
 * paint. See SCENARIO_GROUPS for the case-by-case breakdown.
 */
class AcademicDataSeeder extends Seeder
{
    private const CURRENT_TERM = '2026-II';

    private const PREVIOUS_TERM = '2026-I';

    /**
     * Volume filler for pagination/report load testing, on top of the
     * hand-written scenario above. Uses its own teacher pool (BULK-####
     * identity cards) so it never touches the workload totals the
     * scenario's RE-04/RE-02 comments depend on, but reuses the existing
     * classrooms. Written with updateOrCreate() like everything else, so
     * re-seeding stays at 1500 rows instead of growing every run.
     */
    private const BULK_GROUP_COUNT = 1500;

    private const BULK_TEACHER_COUNT = 50;

    /**
     * [identity card, name, reference workload]
     *
     * @var array<int, array{0: string, 1: string, 2: float}>
     */
    private const TEACHERS = [
        ['1-1045-0782', 'Ana Lucía Rodríguez Mora', 1.00],
        ['2-0678-0341', 'Carlos Eduardo Jiménez Salas', 1.00],
        ['1-0912-0455', 'María Fernanda Solís Vargas', 1.00],
        ['3-0455-0128', 'Jorge Andrés Castro Núñez', 1.00],
        ['2-0733-0619', 'Silvia Patricia Alfaro Quesada', 0.75],
        ['1-1188-0234', 'Roberto Antonio Vega Chaves', 1.00],
        ['4-0201-0876', 'Laura Melissa Araya Brenes', 0.75],
        ['2-0844-0512', 'Diego Alberto Montero Ruiz', 1.00],
        ['1-0759-0983', 'Karla Vanessa Herrera Umaña', 0.50],
        ['5-0322-0147', 'Luis Gerardo Sánchez Picado', 1.00],
        ['1-1276-0390', 'Andrea Sofía Campos Elizondo', 0.75],
        ['2-0591-0728', 'Manuel Enrique Rojas Barrantes', 1.00],
        ['3-0688-0264', 'Gabriela María Fonseca Zúñiga', 0.50],
        ['1-0987-0651', 'Óscar Emilio Delgado Cordero', 1.00],
        ['2-0410-0839', 'Natalia Isabel Cordero Ramírez', 0.75],
    ];

    /**
     * [name, capacity]
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private const CLASSROOMS = [
        ['Aula 101', 35],
        ['Aula 102', 30],
        ['Aula 103', 25],
        ['Aula 201', 40],
        ['Aula 202', 32],
        ['Laboratorio de Cómputo A', 24],
        ['Laboratorio de Cómputo B', 24],
        ['Aula Magna', 60],
        ['Aula 301', 28],
        ['Taller de Redes', 20],
    ];

    /**
     * Hand-written cases, each one there to make a specific rule
     * observable in the UI:
     *
     *  - Ana Lucía accumulates 1.25 in the current term → RE-04 Medium
     *    ("jornada en conflicto") and RE-02 over-contracting (assigned
     *    above her 1.00 reference).
     *  - Carlos Eduardo carries 0.50 against a 1.00 reference → RE-02
     *    significant under-contracting (below 80%).
     *  - María Fernanda lands exactly on 1.00 → RE-02 with no alert.
     *  - ISW-211-G02 has no teacher → RE-04 High.
     *  - ISW-111-G01 has no classroom → RE-04 High.
     *  - ISW-111-G02 has 3 students → RE-04 Low (below the threshold of 5).
     *  - ADM-101-G01 has none of the three → three simultaneous items.
     *  - ADM-101-G02 is cancelled and deliberately unstaffed → proves
     *    cancelled groups are excluded from the board instead of
     *    generating noise.
     *
     * [code, course, teacher index|null, classroom index|null, enrollment, workload, modality, status]
     *
     * @var array<int, array{0: string, 1: string, 2: int|null, 3: int|null, 4: int, 5: float, 6: Modality, 7: GroupStatus}>
     */
    private const SCENARIO_GROUPS = [
        ['ISW-521-G01', 'ISW-521', 0, 0, 28, 0.25, Modality::InPerson, GroupStatus::Open],
        ['ISW-521-G02', 'ISW-521', 0, 1, 24, 0.25, Modality::InPerson, GroupStatus::Open],
        ['ISW-521-G03', 'ISW-521', 0, 2, 22, 0.25, Modality::Blended, GroupStatus::Open],
        ['ISW-411-G01', 'ISW-411', 0, 3, 30, 0.25, Modality::InPerson, GroupStatus::Open],
        ['ISW-411-G02', 'ISW-411', 0, 4, 26, 0.25, Modality::Virtual, GroupStatus::Open],
        ['ISW-311-G01', 'ISW-311', 1, 5, 18, 0.50, Modality::InPerson, GroupStatus::Open],
        ['ISW-311-G02', 'ISW-311', 2, 6, 20, 0.50, Modality::Blended, GroupStatus::Open],
        ['ISW-211-G01', 'ISW-211', 2, 7, 25, 0.50, Modality::InPerson, GroupStatus::Open],
        ['ISW-211-G02', 'ISW-211', null, 8, 15, 0.00, Modality::InPerson, GroupStatus::Open],
        ['ISW-111-G01', 'ISW-111', 3, null, 19, 0.50, Modality::InPerson, GroupStatus::Open],
        ['ISW-111-G02', 'ISW-111', 4, 9, 3, 0.50, Modality::Virtual, GroupStatus::Open],
        ['ADM-101-G01', 'ADM-101', null, null, 4, 0.00, Modality::InPerson, GroupStatus::Open],
        ['ADM-101-G02', 'ADM-101', null, 0, 32, 0.00, Modality::InPerson, GroupStatus::Cancelled],
    ];

    /**
     * Courses used to top the current term up to a realistic size. Each
     * filler group carries 0.25 and no teacher receives more than two of
     * them, so the filler can never invent a workload conflict the
     * scenario block did not intend.
     *
     * @var array<int, string>
     */
    private const FILLER_COURSES = [
        'ISW-621', 'ISW-631', 'ADM-201', 'ADM-211', 'CON-101',
        'MAT-101', 'MAT-201', 'ING-101', 'ING-201', 'EST-101',
    ];

    public function run(): void
    {
        $teachers = $this->seedTeachers();
        $classrooms = $this->seedClassrooms();

        $this->seedScenarioGroups($teachers, $classrooms);
        $this->seedFillerGroups($teachers, $classrooms);
        $this->seedPreviousTermGroups($teachers, $classrooms);

        $bulkTeachers = $this->seedBulkTeachers();
        $this->seedBulkGroups($bulkTeachers, $classrooms);
    }

    /**
     * @return array<int, Teacher>
     */
    private function seedTeachers(): array
    {
        $teachers = [];

        foreach (self::TEACHERS as [$identityCard, $name, $referenceWorkload]) {
            $teachers[] = Teacher::query()->updateOrCreate(
                ['identity_card' => $identityCard],
                ['name' => $name, 'reference_workload' => $referenceWorkload],
            );
        }

        return $teachers;
    }

    /**
     * @return array<int, Classroom>
     */
    private function seedClassrooms(): array
    {
        $classrooms = [];

        foreach (self::CLASSROOMS as [$name, $capacity]) {
            $classrooms[] = Classroom::query()->updateOrCreate(
                ['name' => $name],
                ['capacity' => $capacity],
            );
        }

        return $classrooms;
    }

    /**
     * @param  array<int, Teacher>  $teachers
     * @param  array<int, Classroom>  $classrooms
     */
    private function seedScenarioGroups(array $teachers, array $classrooms): void
    {
        foreach (self::SCENARIO_GROUPS as $spec) {
            [$code, $courseCode, $teacherIndex, $classroomIndex, $enrollment, $workload, $modality, $status] = $spec;

            $this->writeGroup(
                code: $code,
                courseCode: $courseCode,
                term: self::CURRENT_TERM,
                teacher: $teacherIndex === null ? null : $teachers[$teacherIndex],
                classroom: $classroomIndex === null ? null : $classrooms[$classroomIndex],
                enrollment: $enrollment,
                workload: $workload,
                modality: $modality,
                status: $status,
            );
        }
    }

    /**
     * Rounds the current term out to 33 groups, spreading them over the
     * teachers the scenario block left alone (index 5 onwards).
     *
     * @param  array<int, Teacher>  $teachers
     * @param  array<int, Classroom>  $classrooms
     */
    private function seedFillerGroups(array $teachers, array $classrooms): void
    {
        $modalities = Modality::cases();

        foreach (self::FILLER_COURSES as $courseIndex => $courseCode) {
            for ($groupNumber = 1; $groupNumber <= 2; $groupNumber++) {
                $sequence = $courseIndex * 2 + $groupNumber;
                $teacherIndex = 5 + ($sequence % 10);

                $this->writeGroup(
                    code: sprintf('%s-G%02d', $courseCode, $groupNumber),
                    courseCode: $courseCode,
                    term: self::CURRENT_TERM,
                    teacher: $teachers[$teacherIndex],
                    classroom: $classrooms[$sequence % 10],
                    // 12..36 students: comfortably above the risk threshold,
                    // so filler never competes with the intended Low case.
                    enrollment: 12 + ($sequence * 3) % 25,
                    workload: 0.25,
                    modality: $modalities[$sequence % count($modalities)],
                    status: GroupStatus::Open,
                );
            }
        }
    }

    /**
     * A closed previous term, so the report screens have a second option
     * in their term selector and RE-01 can be shown filtering by term
     * rather than dumping everything.
     *
     * @param  array<int, Teacher>  $teachers
     * @param  array<int, Classroom>  $classrooms
     */
    private function seedPreviousTermGroups(array $teachers, array $classrooms): void
    {
        $courses = ['ISW-521', 'ISW-411', 'ISW-311', 'ADM-101'];

        foreach ($courses as $courseIndex => $courseCode) {
            for ($groupNumber = 1; $groupNumber <= 2; $groupNumber++) {
                $sequence = $courseIndex * 2 + $groupNumber;

                $this->writeGroup(
                    code: sprintf('%s-%s-G%02d', $courseCode, self::PREVIOUS_TERM, $groupNumber),
                    courseCode: $courseCode,
                    term: self::PREVIOUS_TERM,
                    teacher: $teachers[$sequence % count($teachers)],
                    classroom: $classrooms[$sequence % count($classrooms)],
                    enrollment: 20 + $sequence,
                    workload: 0.25,
                    modality: Modality::InPerson,
                    status: GroupStatus::Closed,
                );
            }
        }
    }

    /**
     * @return array<int, Teacher>
     */
    private function seedBulkTeachers(): array
    {
        $teachers = [];

        for ($i = 1; $i <= self::BULK_TEACHER_COUNT; $i++) {
            $teachers[] = Teacher::query()->updateOrCreate(
                ['identity_card' => sprintf('BULK-%04d', $i)],
                ['name' => sprintf('Docente Volumen %04d', $i), 'reference_workload' => 1.00],
            );
        }

        return $teachers;
    }

    /**
     * @param  array<int, Teacher>  $teachers
     * @param  array<int, Classroom>  $classrooms
     */
    private function seedBulkGroups(array $teachers, array $classrooms): void
    {
        $courses = [...self::FILLER_COURSES, 'ISW-521', 'ISW-411', 'ISW-311', 'ISW-211', 'ISW-111', 'ADM-101'];
        $modalities = Modality::cases();

        for ($i = 1; $i <= self::BULK_GROUP_COUNT; $i++) {
            $this->writeGroup(
                code: sprintf('BULK-%04d', $i),
                courseCode: $courses[$i % count($courses)],
                term: self::CURRENT_TERM,
                teacher: $teachers[$i % count($teachers)],
                classroom: $classrooms[$i % count($classrooms)],
                enrollment: 8 + ($i % 33),
                workload: 0.25,
                modality: $modalities[$i % count($modalities)],
                status: GroupStatus::Open,
            );
        }
    }

    private function writeGroup(
        string $code,
        string $courseCode,
        string $term,
        ?Teacher $teacher,
        ?Classroom $classroom,
        int $enrollment,
        float $workload,
        Modality $modality,
        GroupStatus $status,
    ): void {
        Group::query()->updateOrCreate(
            ['code' => $code],
            [
                'course_code' => $courseCode,
                'term' => $term,
                'teacher_id' => $teacher?->id,
                'classroom_id' => $classroom?->id,
                'estimated_enrollment' => $enrollment,
                'assigned_workload' => $teacher === null ? 0 : $workload,
                'modality' => $modality->value,
                'status' => $status->value,
            ],
        );
    }
}
