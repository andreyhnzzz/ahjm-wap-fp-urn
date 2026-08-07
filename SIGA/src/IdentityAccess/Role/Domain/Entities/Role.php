<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Domain\Entities;

use Src\IdentityAccess\Role\Domain\Exceptions\RoleIsProtectedException;

/**
 * Role — Aggregate Root of the IdentityAccess bounded context.
 *
 * Pure PHP. Zero framework coupling — no Eloquent, no Illuminate imports.
 * This class is the single source of truth for Role business
 * invariants. Persistence concerns belong to Infrastructure, not here.
 */
final class Role
{
    /**
     * Structural roles the system depends on (seeders, Gate::before).
     * Cannot be renamed or deleted — permissions may still be edited.
     */
    private const PROTECTED_NAMES = ['Superadmin', 'Admin'];

    private function __construct(
        private readonly ?int $id,
        private string $name,
        /** @var array<int, string> */
        private array $permissions,
    ) {}

    /**
     * @param array<int, string> $permissions
     */
    public static function create(string $name, array $permissions = []): self
    {
        return new self(id: null, name: $name, permissions: $permissions);
    }

    /**
     * @param array<int, string> $permissions
     */
    public static function reconstitute(int $id, string $name, array $permissions = []): self
    {
        return new self(id: $id, name: $name, permissions: $permissions);
    }

    public function rename(string $name): void
    {
        if ($this->isProtected() && $name !== $this->name) {
            throw RoleIsProtectedException::forName($this->name);
        }

        $this->name = $name;
    }

    /**
     * @param array<int, string> $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function isProtected(): bool
    {
        return in_array($this->name, self::PROTECTED_NAMES, true);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }
}
