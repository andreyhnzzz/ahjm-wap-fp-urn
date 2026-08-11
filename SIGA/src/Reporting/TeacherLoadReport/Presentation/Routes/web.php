<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Reporting\TeacherLoadReport\Presentation\Livewire\TeacherLoadReportComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('reports/teacher-load', TeacherLoadReportComponent::class)
    ->name('reporting.teacherloadreport.index');
