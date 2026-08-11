<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\AcademicRisk\RiskBoard\Presentation\Livewire\RiskBoardComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('risk-board', RiskBoardComponent::class)
    ->name('academicrisk.riskboard.index');
