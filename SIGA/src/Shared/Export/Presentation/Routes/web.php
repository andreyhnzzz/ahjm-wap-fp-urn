<?php

declare(strict_types=1);

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a queued export once GenerateReportExportJob marks it ready.
 * Ownership check first: a ReportExport row belongs to whoever
 * requested it, never anyone who can guess or enumerate an id.
 */
Route::middleware(['web', 'auth', 'verified'])
    ->get('report-exports/{reportExport}/download', function (ReportExport $reportExport) {
        abort_unless($reportExport->user_id === Auth::id(), 403);
        abort_unless($reportExport->status === ReportExportStatus::Ready, 404);

        $disk = Storage::disk($reportExport->disk);
        abort_unless($reportExport->file_path !== null && $disk->exists($reportExport->file_path), 404);

        return $disk->download($reportExport->file_path, $reportExport->filename);
    })
    ->name('report-exports.download');
