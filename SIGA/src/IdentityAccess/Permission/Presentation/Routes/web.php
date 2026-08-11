<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Permission\Presentation\Livewire\PermissionComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('permissions', PermissionComponent::class)
    ->name('identityaccess.permission.index');
