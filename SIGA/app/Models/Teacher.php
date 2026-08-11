<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persistence model for the Academic\Teacher module.
 *
 * Lives in App\Models, never inside src/: Eloquent is an infrastructure
 * detail of this application, and the bounded context must stay
 * loadable with the ORM removed. Only
 * Src\Academic\Teacher\Infrastructure\...\EloquentTeacherRepository is
 * allowed to touch this class; nothing in Domain or Application knows
 * it exists.
 *
 * @property int $id
 * @property string $identity_card
 * @property string $name
 * @property float $reference_workload
 * @property-read Collection<int, Group> $groups
 */
#[Fillable(['identity_card', 'name', 'reference_workload'])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // The column is decimal(4,2); the driver hands it back as a string.
        // Casting here means the repository maps a real float into the
        // Domain Entity instead of leaking a numeric string into it.
        return [
            'reference_workload' => 'float',
        ];
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
