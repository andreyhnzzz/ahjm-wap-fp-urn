<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Academic\Classroom\Presentation\Livewire\ClassroomComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('classrooms', ClassroomComponent::class)
    ->name('academic.classroom.index');
