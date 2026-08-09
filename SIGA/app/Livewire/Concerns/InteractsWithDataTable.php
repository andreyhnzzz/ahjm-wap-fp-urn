<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Generic pagination/sorting/search state shared by every CRUD data-table
 * component, regardless of bounded context. Extracted so no future module
 * has to re-implement it (DRY, matches the existing App\Concerns pattern
 * used by ProfileValidationRules / PasswordValidationRules).
 *
 * Two table modes are supported, selected per-component via the
 * `$tableMode` property:
 *
 *  - 'client' (default): the full collection is fetched from the
 *    Application layer ONCE per Livewire render and handed to Alpine.js
 *    as JSON (see resources/js/data-table.js — `crudTable`). Search, sort
 *    and pagination are then resolved entirely in the browser: zero
 *    Livewire round-trips until an actual mutation (create/update/delete)
 *    happens. Intended for small, reference-style datasets — roles,
 *    permissions, catalogs, statuses, etc.
 *
 *  - 'server': classic Livewire-driven pagination. Search/sort/page
 *    changes trigger a request to the server, as is required once a
 *    dataset is too large to ship to the browser in one response.
 *
 * A concrete component only needs to:
 *   1. Set `protected string $tableMode = 'client' | 'server';`
 *   2. In `render()`, branch on `$this->isServerMode()` and call the
 *      matching Application UseCase method (`all()` vs `paginate()`).
 *
 * Everything else — the four pagination actions, the sort toggle, and
 * resetting the page on search/perPage changes — is inherited.
 */
trait InteractsWithDataTable
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    public int $perPage = 10;

    public int $page = 1;

    public string $sortKey = '';

    public string $sortDir = 'asc';

    /**
     * Exposed to the Blade view so it can pick which set of directives to
     * render (wire:* for 'server', x-* or @* for 'client')
     * without changing a single visual class.
     */
    public function tableMode(): string
    {
        return $this->tableMode;
    }

    public function isServerMode(): bool
    {
        return $this->tableMode() === 'server';
    }

    public function isClientMode(): bool
    {
        return ! $this->isServerMode();
    }

    /**
     * Server mode only — client mode resets its own page inside Alpine
     * and never touches this property over the wire.
     */
    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    public function updatingPerPage(): void
    {
        $this->page = 1;
    }

    /**
     * Server mode only. In client mode, sorting is handled by Alpine's
     * `sort()` method in resources/js/data-table.js and this method is
     * simply never wired up in the Blade view.
     */
    public function sort(string $key): void
    {
        $this->sortDir = $this->sortKey === $key && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortKey = $key;
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * Client mode only — call this after any mutation (create/update/
     * delete) that changes what the table should display, passing the
     * freshly re-fetched rows. Dispatches a browser event that
     * resources/js/data-table.js's `crudTable` Alpine component listens
     * for and applies directly to its own `rows` state.
     *
     * Why this exists: Alpine's `x-data="crudTable({ rows: @js($rows) })"`
     * is evaluated once, the first time the element enters the DOM.
     * Livewire's DOM morph deliberately preserves existing Alpine
     * component state across re-renders (so open dropdowns, in-progress
     * typing, etc. survive unrelated updates) — meaning a fresh x-data
     * attribute in newly-rendered HTML is never re-read after that first
     * init. Without this, `rows` goes stale the moment any mutation
     * changes the underlying data.
     *
     * No-op in server mode, where Livewire's normal re-render already
     * updates the DOM correctly on its own — every concrete component's
     * save()/delete() can call this unconditionally regardless of mode.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function refreshTable(array $rows): void
    {
        if ($this->isClientMode()) {
            $this->dispatch('data-table-refresh', rows: $rows);
        }
    }
}
