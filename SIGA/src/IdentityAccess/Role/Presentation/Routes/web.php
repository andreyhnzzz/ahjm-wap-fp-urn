<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;


Route::middleware(['web', 'auth', 'verified'])
    ->get('roles', RoleComponent::class)
    ->name('identityaccess.role.index');
