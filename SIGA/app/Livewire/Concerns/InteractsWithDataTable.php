<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Closure;
use Livewire\Attributes\Url;

/**
 * Pagination/sorting/search state shared by every CRUD data-table
 * component, regardless of bounded context.
 *
 * Two modes:
 *
 *  - 'client': the full collection ships to Alpine once per render (see
 *    resources/js/data-table.ts) and search/sort/paging are resolved in
 *    the browser — zero round-trips until a mutation. For small
 *    reference catalogs: roles, permissions, statuses.
 *  - 'server': Livewire-driven paging, for datasets too large to ship
 *    in one response.
 *
 * `$tableMode` is the component's PREFERENCE, not the verdict. It used
 * to be the verdict, and that was the bug: a constant per screen decides
 * for a dataset it has never seen. Groups only became 'server' after the
 * screen died at 45,000 rows in production-sized data — every other
 * screen is still one growth spurt away from the same afternoon.
 *
 * So a component that prefers 'client' gets it only while the data is
 * small enough (see CLIENT_MODE_MAX_ROWS); past that it falls back to
 * server paging on its own. A component that declares 'server' stays
 * there regardless — that direction is never wrong.
 *
 * Components call tableModeFor() in render() and branch on the answer to
 * pick `all()` vs `paginate()`. Everything else is inherited.
 */
trait InteractsWithDataTable
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    public int $perPage = 10;

    /**
     * The sizes the "Show N records" selector offers. Kept in sync by
     * hand with the <option> list in components/ui/data-table.blade.php.
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50];

    public int $page = 1;

    /**
     * Rows above which client mode stops paying for itself.
     *
     * What client mode costs is the whole collection as JSON inside the
     * initial response's x-data attribute. Measured on this app's own
     * rows: 285 bytes per row on the widest table (groups, nine columns)
     * and 96 on the narrowest (teachers, three). 2,000 rows is therefore
     * ~0.55 MB of JSON in the worst case here and ~0.18 MB in the best —
     * a payload budget, which is the real constraint, expressed in the
     * unit a component can actually check before paying it.
     *
     * Not a hard ceiling like ChunkedChromePdfWriter's chunk size:
     * crossing it costs a round-trip per search, not a failure. Which is
     * exactly why it must not be crossed silently.
     */
    private const CLIENT_MODE_MAX_ROWS = 2000;

    /**
     * What the data decided, once. Public for the same reason
     * $bootstrapped is: Livewire's snapshot has to carry it, or every
     * request re-runs the count that produced it.
     */
    public string $resolvedTableMode = '';

    public string $sortKey = '';

    public string $sortDir = 'asc';

    /**
     * Public (not protected) so Livewire's snapshot carries it across
     * requests — flips true after this component's first render.
     */
    public bool $bootstrapped = false;

    /**
     * True on this component's very first render only. Call once at the
     * top of render(), before building any client-mode `rows` or modal
     * lookup catalogs (permission list, teacher/classroom options, ...):
     * those are read by Alpine exactly once, the moment their element
     * first enters the DOM, and never again — Livewire's morph preserves
     * an already-mounted Alpine component's state instead of re-reading
     * a fresh x-data attribute (see refreshTable() below). Every render
     * after the first was fetching that data from the DB for nothing.
     */
    protected function isFirstRender(): bool
    {
        if ($this->bootstrapped) {
            return false;
        }

        $this->bootstrapped = true;

        return true;
    }

    /**
     * Exposed to the Blade view so it can pick which set of directives to
     * render (wire:* for 'server', x-* or @* for 'client')
     * without changing a single visual class.
     */
    public function tableMode(): string
    {
        return $this->resolvedTableMode !== '' ? $this->resolvedTableMode : $this->tableMode;
    }

    /**
     * Resolves the mode for the data actually present, once per component
     * lifetime, and remembers it.
     *
     * $countRows is a closure and not a number because the whole point is
     * not to pay for what we are deciding about: it runs only on the
     * first render of a client-preferring component, and what it should
     * do is a COUNT — the same one the paginator already issues — never a
     * fetch of the rows being counted.
     *
     * @param  Closure(): int  $countRows
     */
    protected function tableModeFor(Closure $countRows): string
    {
        // A component that asked for server paging is never talked out
        // of it: erring towards a round-trip is always survivable, and
        // erring the other way is what this method exists to prevent.
        if ($this->tableMode === 'server') {
            return $this->resolvedTableMode = 'server';
        }

        if ($this->resolvedTableMode !== '') {
            return $this->resolvedTableMode;
        }

        return $this->resolvedTableMode = $countRows() > self::CLIENT_MODE_MAX_ROWS
            ? 'server'
            : 'client';
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
     * The page size to actually query with — never `$perPage` raw.
     *
     * In server mode this value becomes the query's LIMIT, and a Livewire
     * public property is whatever the client's payload says it is: the
     * <select> offering 10/25/50 is a UI affordance, not a constraint.
     * Unclamped, a crafted request asking for every row hydrates the whole
     * table in a single request — on the 45,000-row table that is a fatal
     * memory error any authenticated user could trigger at will, and
     * repeatedly.
     *
     * Clamped on read rather than in an `updatedPerPage()` hook on
     * purpose: read-time cannot be bypassed by however the property came
     * to hold its value. Same shape as the risk board bounding its own
     * poll interval in code instead of trusting the field.
     */
    protected function pageSize(): int
    {
        return in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : 10;
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
     * Client mode only — call after any mutation, passing the freshly
     * re-fetched rows.
     *
     * Alpine evaluates `x-data="crudTable({ rows: ... })"` once, and
     * Livewire's morph deliberately preserves mounted Alpine state, so a
     * fresh x-data attribute is never re-read. Without this dispatch,
     * `rows` goes stale the moment a mutation changes the data.
     *
     * No-op in server mode, where Livewire's re-render already handles
     * it — components can call this unconditionally.
     *
     * Takes a Closure, not the rows: "no-op" used to mean the dispatch was
     * skipped while the argument had already been built, and building it
     * means re-fetching the whole table. On the 20,000-row offer that was
     * 1.3 s and 102 MB burned on every save, delete and export for a value
     * server mode immediately discarded. An array is still accepted for
     * the client-mode components, where the rows are needed either way.
     *
     * @param  Closure(): array<int, array<string, mixed>>|array<int, array<string, mixed>>  $rows
     */
    public function refreshTable(Closure|array $rows): void
    {
        if ($this->isServerMode()) {
            return;
        }

        $this->dispatch('data-table-refresh', rows: $rows instanceof Closure ? $rows() : $rows);
    }
}
