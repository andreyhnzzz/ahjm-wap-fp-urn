<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $module
 * @property string $action
 * @property string $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 */
#[Fillable(['module', 'action', 'name'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user');
    }
}
