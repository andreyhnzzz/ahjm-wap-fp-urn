<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks a queued PDF/Excel export from creation to download, so a CRUD
 * screen can poll "is my file ready yet?" instead of blocking the HTTP
 * request on Chrome for as long as GenerateReportExportJob takes.
 *
 * @property int $id
 * @property int $user_id
 * @property string $format
 * @property string $title
 * @property string $filename
 * @property ReportExportStatus $status
 * @property string $disk
 * @property string|null $file_path
 * @property string|null $error_message
 */
#[Fillable(['user_id', 'format', 'title', 'filename', 'status', 'disk', 'file_path', 'error_message'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReportExportStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
