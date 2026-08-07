<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Domain\Entities;

/**
 * Permission — Aggregate Root of the IdentityAccess bounded context.
 * Pure PHP, zero framework coupling. Uniqueness of (module, action) is
 * enforced at Infrastructure (DB unique index) and validated at
 * Presentation — the Domain layer stays free of repository access.
 */
final class Permission
{
    private function __construct(
        private readonly ?int $id,
        private string $module,
        private string $action,
    ) {}

    public static function create(string $module, string $action): self
    {
        return new self(id: null, module: $module, action: $action);
    }

    public static function reconstitute(int $id, string $module, string $action): self
    {
        return new self(id: $id, module: $module, action: $action);
    }

    public function redefine(string $module, string $action): void
    {
        $this->module = $module;
        $this->action = $action;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function module(): string
    {
        return $this->module;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function name(): string
    {
        return "{$this->module}.{$this->action}";
    }
}
