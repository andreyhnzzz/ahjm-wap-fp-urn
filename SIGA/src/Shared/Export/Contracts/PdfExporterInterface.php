<?php

declare(strict_types=1);

namespace Src\Shared\Export\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Port for rendering HTML to a downloadable PDF. Takes a raw HTML
 * string, not a Blade view name + data — that keeps this contract from
 * assuming Blade even exists. Rendering the view to a string is the
 * Presentation layer's job (RoleComponent/PermissionComponent, when we
 * wire this in); this port's only concern is "HTML in, PDF response out".
 *
 * Same reasoning as ExcelExporterInterface: one shared port, not one
 * per entity — turning a rendered page into a PDF needs no domain
 * knowledge. Only Infrastructure/SpatiePdfExporter.php is allowed to
 * import Spatie/Browsershot types.
 */
interface PdfExporterInterface
{
    /**
     * @param string $paperSize Any size the underlying renderer accepts
     *        ('a4', 'letter', 'legal', ...). Defaults to 'a4' since
     *        that's the more common case; a specific report's own
     *        design dictates otherwise (e.g. this app's report template
     *        is built for US Letter to match its @page CSS rule) and
     *        passes it explicitly — the port stays generic, only the
     *        call site knows which size its own HTML was designed for.
     */
    public function fromHtml(string $html, string $filename, string $paperSize = 'a4'): StreamedResponse;
}
