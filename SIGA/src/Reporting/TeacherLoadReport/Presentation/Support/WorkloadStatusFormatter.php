<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Presentation\Support;

use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\WorkloadStatus;

/**
 * Wording and colour for the workload verdict.
 *
 * RE-02 requires over-contracting and significant under-contracting to
 * be marked "in one colour" and "in another". Both the on-screen report
 * and the PDF read their colour from here, so the two can never diverge
 * — a coordinator comparing the screen against the printed copy has to
 * see the same red on the same teacher.
 *
 * Kept out of the domain for the usual reason: WorkloadStatus decides
 * *what* the verdict is, this decides how a human sees it.
 */
final class WorkloadStatusFormatter
{
    public static function label(WorkloadStatus $status): string
    {
        return match ($status) {
            WorkloadStatus::OverContracted => __('Over-contracting'),
            WorkloadStatus::UnderContracted => __('Significant under-contracting'),
            WorkloadStatus::Balanced => __('No alert'),
        };
    }

    /**
     * CSS modifier appended to `.workload-badge` in the dashboard theme.
     */
    public static function variant(WorkloadStatus $status): string
    {
        return match ($status) {
            WorkloadStatus::OverContracted => 'over',
            WorkloadStatus::UnderContracted => 'under',
            WorkloadStatus::Balanced => 'balanced',
        };
    }

    /**
     * Literal colours for the PDF, which is rendered by Chromium from a
     * self-contained template and therefore cannot reach the dashboard
     * stylesheet or its CSS custom properties.
     *
     * @return array{background: string, text: string, border: string}
     */
    public static function pdfPalette(WorkloadStatus $status): array
    {
        return match ($status) {
            WorkloadStatus::OverContracted => ['background' => '#fde7e7', 'text' => '#b42318', 'border' => '#f0b4b0'],
            WorkloadStatus::UnderContracted => ['background' => '#fff3d6', 'text' => '#93650b', 'border' => '#e8cd8f'],
            WorkloadStatus::Balanced => ['background' => '#e6f0e2', 'text' => '#3d7a30', 'border' => '#bcd9b2'],
        };
    }
}
