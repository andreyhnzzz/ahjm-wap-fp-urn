<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Permission\Presentation\Controllers\PermissionController;

/**
 * Routes for the IdentityAccess\Permission bounded context.
 *
 * Auto-loaded by App\Providers\DomainServiceProvider::boot() via a glob
 * over src/*\/*\/Presentation/Routes/web.php — do not require this file
 * manually elsewhere, and note that it is skipped entirely at runtime
 * when `php artisan route:cache` has been run.
 */
Route::middleware('web')
    ->prefix('identityaccess/permissions')
    ->group(function () {
        // Naive plural above — adjust the prefix by hand for irregular plurals.
        Route::post('/', [PermissionController::class, 'store'])
            ->name('identityaccess.permission.store');
    });
