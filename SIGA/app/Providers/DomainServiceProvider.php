<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Classroom\Infrastructure\Persistence\Repositories\EloquentClassroomRepository;
use Src\Academic\Classroom\Presentation\Policies\ClassroomPolicy;
use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Infrastructure\Persistence\Repositories\EloquentGroupRepository;
use Src\Academic\Group\Presentation\Policies\GroupPolicy;
use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Academic\Teacher\Infrastructure\Persistence\Repositories\EloquentTeacherRepository;
use Src\Academic\Teacher\Presentation\Policies\TeacherPolicy;
use Src\AcademicRisk\RiskBoard\Domain\Contracts\RiskSourceInterface;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskBoard;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskThresholds;
use Src\AcademicRisk\RiskBoard\Infrastructure\Persistence\Repositories\EloquentRiskSourceRepository;
use Src\AcademicRisk\RiskBoard\Presentation\Policies\RiskBoardPolicy;
use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Infrastructure\Persistence\Repositories\EloquentPermissionRepository;
use Src\IdentityAccess\Permission\Presentation\Policies\PermissionPolicy;
use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Infrastructure\Persistence\Repositories\EloquentRoleRepository;
use Src\IdentityAccess\Role\Presentation\Policies\RolePolicy;
use Src\Reporting\OfferReport\Domain\Contracts\OfferReportArchiveInterface;
use Src\Reporting\OfferReport\Domain\Contracts\OfferSourceInterface;
use Src\Reporting\OfferReport\Domain\Entities\OfferReport;
use Src\Reporting\OfferReport\Infrastructure\Persistence\Archives\FilesystemOfferReportArchive;
use Src\Reporting\OfferReport\Infrastructure\Persistence\Repositories\EloquentOfferSourceRepository;
use Src\Reporting\OfferReport\Presentation\Policies\OfferReportPolicy;
use Src\Reporting\TeacherLoadReport\Application\UseCases\GenerateTeacherLoadReportUseCase;
use Src\Reporting\TeacherLoadReport\Domain\Contracts\TeacherLoadSourceInterface;
use Src\Reporting\TeacherLoadReport\Domain\Entities\TeacherLoadReport;
use Src\Reporting\TeacherLoadReport\Infrastructure\Persistence\Repositories\EloquentTeacherLoadSourceRepository;
use Src\Reporting\TeacherLoadReport\Presentation\Policies\TeacherLoadReportPolicy;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\ExcelFileWriterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;
use Src\Shared\Export\Infrastructure\SpatieExcelExporter;
use Src\Shared\Export\Infrastructure\SpatieExcelFileWriter;
use Src\Shared\Export\Infrastructure\SpatiePdfExporter;
use Src\Shared\Export\Infrastructure\SpatiePdfFileWriter;

final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $domainBindings = [
        RoleRepositoryInterface::class => EloquentRoleRepository::class,
        PermissionRepositoryInterface::class => EloquentPermissionRepository::class,
        TeacherRepositoryInterface::class => EloquentTeacherRepository::class,
        ClassroomRepositoryInterface::class => EloquentClassroomRepository::class,
        GroupRepositoryInterface::class => EloquentGroupRepository::class,
        RiskSourceInterface::class => EloquentRiskSourceRepository::class,
        OfferSourceInterface::class => EloquentOfferSourceRepository::class,
        OfferReportArchiveInterface::class => FilesystemOfferReportArchive::class,
        TeacherLoadSourceInterface::class => EloquentTeacherLoadSourceRepository::class,
        ExcelExporterInterface::class => SpatieExcelExporter::class,
        PdfExporterInterface::class => SpatiePdfExporter::class,
        ExcelFileWriterInterface::class => SpatieExcelFileWriter::class,
        PdfFileWriterInterface::class => SpatiePdfFileWriter::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private array $domainPolicies = [
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        Teacher::class => TeacherPolicy::class,
        Classroom::class => ClassroomPolicy::class,
        Group::class => GroupPolicy::class,
        RiskBoard::class => RiskBoardPolicy::class,
        OfferReport::class => OfferReportPolicy::class,
        TeacherLoadReport::class => TeacherLoadReportPolicy::class,
    ];

    public function register(): void
    {
        foreach ($this->domainBindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        $this->registerConfiguredDomainServices();
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerSuperAdminBypass();
        $this->loadContextRoutes();
    }

    /**
     * The composition root, and the only place where configuration is
     * turned into a domain parameter.
     *
     * This is what lets `src/` stay free of config() and env() while the
     * business rules it holds remain tunable: the thresholds RE-04 and
     * RE-02 require to be configurable are read here, once, and injected
     * as plain values. A unit test constructs the same objects with its
     * own numbers and never touches Laravel's config at all.
     */
    private function registerConfiguredDomainServices(): void
    {
        $this->app->bind(RiskThresholds::class, static fn (): RiskThresholds => new RiskThresholds(
            minimumEnrollment: (int) config('academic.risk.minimum_enrollment', 5),
            maximumWorkload: (float) config('academic.risk.maximum_workload', 1.0),
        ));

        $this->app->bind(
            GenerateTeacherLoadReportUseCase::class,
            fn (Application $app): GenerateTeacherLoadReportUseCase => new GenerateTeacherLoadReportUseCase(
                $app->make(TeacherLoadSourceInterface::class),
                $this->underLoadRatio(),
            ),
        );
    }

    /**
     * Clamped to (0, 1]. A ratio above 1 would make "under-contracted"
     * overlap "over-contracted" and every teacher would light up in two
     * colours at once; zero or less would make the rule unreachable.
     * Configuration should not be able to express either.
     */
    private function underLoadRatio(): float
    {
        $configured = (float) config('academic.teacher_load.under_load_ratio', 0.8);

        return max(0.01, min(1.0, $configured));
    }

    private function registerPolicies(): void
    {
        foreach ($this->domainPolicies as $entity => $policy) {
            Gate::policy($entity, $policy);
        }
    }

    /**
     * Superadmin passes every authorization check unconditionally. The
     * RoleSeeder already syncs it every existing permission — this is the
     * safety net that also covers permissions introduced after the last
     * seed run, without needing to re-sync anything.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (Authenticatable $user): ?bool {
            return method_exists($user, 'hasRole') && $user->hasRole('Superadmin')
                ? true
                : null;
        });
    }

    private function loadContextRoutes(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        foreach (File::glob(base_path('src/*/*/Presentation/Routes/web.php')) as $routeFile) {
            $this->loadRoutesFrom($routeFile);
        }
    }
}
