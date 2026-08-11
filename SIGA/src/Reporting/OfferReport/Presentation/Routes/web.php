<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Reporting\OfferReport\Presentation\Livewire\OfferReportComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('reports/academic-offer', OfferReportComponent::class)
    ->name('reporting.offerreport.index');
