<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Role\Presentation\Controllers\RoleController;

/**
 * Routes for the IdentityAccess\Role bounded context.
 *
 * Auto-loaded by App\Providers\DomainServiceProvider::boot() via a glob
 * over src/*\/*\/Presentation/Routes/web.php — do not require this file
 * manually elsewhere, and note that it is skipped entirely at runtime
 * when `php artisan route:cache` has been run.
 */
Route::middleware('web')
    ->prefix('identityaccess/roles')
    ->group(function () {
        // Naive plural above — adjust the prefix by hand for irregular plurals.
        Route::post('/', [RoleController::class, 'store'])
            ->name('identityaccess.role.store');
    });
