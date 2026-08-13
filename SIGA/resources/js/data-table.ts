/**
 * Reusable Alpine.js data source for client-side CRUD tables.
 *
 * Pairs with App\Livewire\Concerns\InteractsWithDataTable running in
 * 'client' mode: the Livewire component fetches the FULL collection once
 * per render and serializes it as plain JSON (never Domain Entities) into
 * this component's `rows`. From that point on, search, sort and
 * pagination are resolved entirely in the browser — no further Livewire
 * request is made until the user actually creates, edits or deletes a
 * record, at which point Livewire re-renders and hands over a fresh
 * `rows` array.
 *
 * Usage (Blade):
 *   <div x-data="crudTable({
 *          rows: @js($rows),
 *          searchable: ['name'],
 *          sortKey: 'name',
 *          perPage: 10,
 *     })">
 *     <input x-model.debounce.150ms="search">
 *     <template x-for="row in pageRows" :key="row.id"> ... </template>
 *   </div>
 *
 * Register once in resources/js/app.js:
 *   import './data-table.js';
 */

type Row = Record<string, string | number | boolean | null>;

interface CrudTableConfig {
  rows?: Row[];
  searchable?: string[];
  search?: string;
  perPage?: number;
  sortKey?: string | null;
  sortDir?: "asc" | "desc";
}

interface CrudTableComponent {
  rows: Row[];
  searchable: string[];
  search: string;
  perPage: number;
  page: number;
  sortKey: string | null;
  sortDir: "asc" | "desc";

  init(): void;

  readonly filtered: Row[];
  readonly sorted: Row[];
  readonly total: number;
  readonly lastPage: number;
  readonly from: number;
  readonly to: number;
  readonly pageRows: Row[];
  readonly pageSet: number[];

  sort(key: string): void;
  previousPage(): void;
  nextPage(): void;
  gotoPage(p: number): void;
  paginationSummary(template: string): string;

  // Alpine-injected magic method.
  $watch(property: string, callback: (value: unknown) => void): void;
}

// Queued exports (InteractsWithExports::pollExportStatus()) dispatch this
// once GenerateReportExportJob finishes, carrying the authenticated
// download URL. Global and Alpine-independent — the export button lives
// outside crudTable's x-data, and there's only ever one active export per
// page — so this registers once at module load rather than per component
// instance.
window.addEventListener("export-ready", (event) => {
  const { url, filename } = (event as CustomEvent<{ url: string; filename: string }>).detail;

  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
});

document.addEventListener("alpine:init", () => {
  Alpine.data("crudTable", (config: CrudTableConfig = {}): CrudTableComponent => ({
    // ---- state -----------------------------------------------------
    rows: config.rows ?? [],
    searchable: config.searchable ?? [],
    // Pre-filled when the page was opened with a `?q=` query string —
    // that's how the risk board (RE-04) can deep-link straight to the
    // one group or teacher an alert is about instead of dropping the
    // user on an unfiltered list. Empty for a normal visit.
    search: config.search ?? "",
    perPage: config.perPage ?? 10,
    page: 1,
    sortKey: config.sortKey ?? null,
    sortDir: config.sortDir ?? "asc",

    init() {
      // Any search or page-size change always snaps back to page 1,
      // mirroring the server-mode behaviour (updatingSearch()).
      this.$watch("search", () => {
        this.page = 1;
      });
      this.$watch("perPage", () => {
        this.page = 1;
      });

      // Unidirectional Presentation -> UI sync (Livewire -> Alpine).
      // Livewire/Alpine's DOM morph deliberately preserves this
      // component's existing reactive state across re-renders (so
      // open dropdowns, in-progress typing, etc. survive unrelated
      // updates) — which means a fresh x-data="crudTable(...)"
      // attribute in newly-rendered HTML is never re-read after the
      // first init. Without this, `rows` would go stale the moment
      // any create/update/delete changes the underlying data.
      //
      // The fix: after any mutation, the Livewire component calls
      // $this->dispatch('data-table-refresh', rows: [...]) with the
      // freshly re-fetched rows (see RoleComponent/PermissionComponent
      // save()/delete()). That's it — only the row payload crosses
      // the wire, no full-page re-render, no lost UI state.
      //
      // Assumes one <x-ui.data-table> per page. If a future screen
      // ever needs two independent client-mode tables at once, this
      // needs a per-table event name instead of a shared one.
      window.addEventListener("data-table-refresh", (event) => {
        this.rows = event.detail.rows;
        this.page = 1;
      });
    },

    // ---- derived data ------------------------------------------------
    get filtered(): Row[] {
      const term = this.search.trim().toLowerCase();
      if (term === "" || this.searchable.length === 0) return this.rows;

      return this.rows.filter((row) =>
        this.searchable.some((key) =>
          String(row[key] ?? "")
            .toLowerCase()
            .includes(term),
        ),
      );
    },

    get sorted(): Row[] {
      if (!this.sortKey) return this.filtered;

      const key = this.sortKey;
      const dir = this.sortDir === "desc" ? -1 : 1;

      return [...this.filtered].sort((a, b) => {
        const av = a[key];
        const bv = b[key];

        if (av === bv) return 0;

        return typeof av === "string" && typeof bv === "string"
          ? av.localeCompare(bv) * dir
          : (av as string | number) > (bv as string | number)
            ? dir
            : -dir;
      });
    },

    get total(): number {
      return this.filtered.length;
    },

    get lastPage(): number {
      return Math.max(1, Math.ceil(this.total / this.perPage));
    },

    get from(): number {
      return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1;
    },

    get to(): number {
      return Math.min(this.page * this.perPage, this.total);
    },

    get pageRows(): Row[] {
      const start = (this.page - 1) * this.perPage;

      return this.sorted.slice(start, start + this.perPage);
    },

    // Same "compact with ellipsis" page-number set the server-mode
    // Blade view builds in PHP — kept identical so pagination looks
    // pixel-for-pixel the same in either mode.
    get pageSet(): number[] {
      const last = this.lastPage;

      if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
      }

      const set = new Set([
        1,
        2,
        last - 1,
        last,
        this.page - 1,
        this.page,
        this.page + 1,
      ]);

      return [...set].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
    },

    // ---- actions -----------------------------------------------------
    sort(key: string) {
      this.sortDir =
        this.sortKey === key && this.sortDir === "asc" ? "desc" : "asc";
      this.sortKey = key;
    },

    previousPage() {
      this.page = Math.max(1, this.page - 1);
    },

    nextPage() {
      this.page = Math.min(this.lastPage, this.page + 1);
    },

    gotoPage(p: number) {
      this.page = Math.max(1, Math.min(this.lastPage, p));
    },

    // ---- i18n helper -------------------------------------------------
    // Laravel's __() with no replacement array returns the string with
    // its :placeholders intact, e.g. "Mostrando :from a :to de :total
    // registros" (see lang/es.json). We only fill in the numbers here,
    // so the actual wording/word-order stays 100% owned by Laravel's
    // translation files — no English fallback ever leaks into the UI.
    paginationSummary(template: string): string {
      return template
        .replace(":from", String(this.from))
        .replace(":to", String(this.to))
        .replace(":total", String(this.total));
    },
  }) as CrudTableComponent);
});
