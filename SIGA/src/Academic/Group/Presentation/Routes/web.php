<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Academic\Group\Presentation\Livewire\GroupComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('groups', GroupComponent::class)
    ->name('academic.group.index');
