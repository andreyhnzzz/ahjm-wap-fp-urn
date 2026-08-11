<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistence model for the Academic\Group module (an academic group:
 * one course, one term, optionally one teacher and one classroom).
 *
 * `teacher` and `classroom` are declared as @property-read relations on
 * purpose — accessing `$model->teacher` as a magic property without this
 * docblock is exactly the `property.notFound` PHPStan failure the CRUD
 * manual warns about.
 *
 * @property int $id
 * @property string $code
 * @property string $course_code
 * @property string $term
 * @property int|null $teacher_id
 * @property int|null $classroom_id
 * @property int $estimated_enrollment
 * @property float $assigned_workload
 * @property string $modality
 * @property string $status
 * @property-read Teacher|null $teacher
 * @property-read Classroom|null $classroom
 */
#[Fillable([
    'code',
    'course_code',
    'term',
    'teacher_id',
    'classroom_id',
    'estimated_enrollment',
    'assigned_workload',
    'modality',
    'status',
])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_enrollment' => 'integer',
            'assigned_workload' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
