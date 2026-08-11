/**
 * Alpine.js is injected as a page global by Livewire, not imported as an
 * npm module here — so it's declared ambiently, typed only for the
 * surface this codebase actually calls (Alpine.data(...)).
 */
interface AlpineGlobal {
  data(name: string, factory: (config?: Record<string, unknown>) => object): void;
}

declare const Alpine: AlpineGlobal;

interface DocumentEventMap {
  "alpine:init": Event;
}

interface WindowEventMap {
  "data-table-refresh": CustomEvent<{
    rows: Record<string, string | number | boolean | null>[];
  }>;
}
