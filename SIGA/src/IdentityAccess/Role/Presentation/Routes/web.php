<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('identityaccess/roles')
    ->group(function () {
        Route::get('/', RoleComponent::class)->name('identityaccess.role.index');
    });
