<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class DomainServiceProvider extends ServiceProvider
{
    /**
     * O(1) deterministic binding map for Domain Interfaces to Infrastructure Implementations.
     *
     * Architectural Note:
     * We explicitly map bindings here rather than using dynamic file scanning (I/O operations)
     * during the framework's boot cycle. This guarantees blazing fast startup times and
     * prevents silent failures caused by namespace typos or complex subdirectory structures.
     *
     * This array is maintained automatically by `php artisan make:ddd`.
     *
     * @var array<class-string, class-string>
     */
    private array $domainBindings = [
        // Map your bounded contexts here.
        // e.g., \Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface::class
        //    => \Src\IdentityAccess\Role\Infrastructure\Persistence\Repositories\EloquentRoleRepository::class,
    ];

    /**
     * O(1) deterministic map of Domain Entities to their Authorization Policies.
     *
     * Same rationale as $domainBindings: explicit registration over runtime
     * discovery. Maintained automatically by `php artisan make:ddd --policy`.
     *
     * @var array<class-string, class-string>
     */
    private array $domainPolicies = [
        // e.g., \Src\IdentityAccess\Role\Domain\Entities\Role::class
        //    => \Src\IdentityAccess\Role\Presentation\Policies\RolePolicy::class,
    ];

    /**
     * Register bindings in the container.
     *
     * Runs during the cheap "register" phase — no I/O, deferred-provider safe.
     */
    public function register(): void
    {
        foreach ($this->domainBindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    /**
     * Boot framework-facing concerns for every bounded context.
     *
     * Route loading is skipped entirely when `php artisan route:cache` has
     * been run, so this scales to N contexts in production with zero added
     * latency — the filesystem glob only ever runs in local/dev.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->loadContextRoutes();
    }

    private function registerPolicies(): void
    {
        foreach ($this->domainPolicies as $entity => $policy) {
            Gate::policy($entity, $policy);
        }
    }

    private function loadContextRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        foreach (File::glob(base_path('src/*/*/Presentation/Routes/web.php')) as $routeFile) {
            $this->loadRoutesFrom($routeFile);
        }
    }
}
