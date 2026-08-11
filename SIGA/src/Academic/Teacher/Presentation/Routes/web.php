<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Academic\Teacher\Presentation\Livewire\TeacherComponent;

// Auto-loaded by DomainServiceProvider, which globs every
// Presentation/Routes/web.php under src/ — nothing else to register.
Route::middleware(['web', 'auth', 'verified'])
    ->get('teachers', TeacherComponent::class)
    ->name('academic.teacher.index');
