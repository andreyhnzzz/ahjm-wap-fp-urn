<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>{{ __('Teacher load report') }} &middot; {{ $summary['teacherName'] }}</title>
    <style>
        /*
          Self-contained on purpose: no @import, no webfont, no image URL.
          Chromium renders this with zero network requests, which is what
          keeps generation time predictable instead of hostage to a font
          CDN — the same property the shared table-pdf template has.
        */
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #24282e;
            font-family: Arial, Helvetica, sans-serif;
        }

        /*
          THE RE-02 REQUIREMENT.

          "la leyenda ... en todas sus páginas" is satisfied by
          position: fixed, which Chromium's print engine paints once per
          printed page rather than once per document. The @page bottom
          margin below reserves the strip it occupies, so it can never
          overlap the last row of a table that runs to the page break.
        */
        .page-legend {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 8px 0.6in;
            border-top: 2px solid #0f2547;
            background: #eef2fa;
            color: #0f2547;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.03em;
            text-align: center;
            text-transform: uppercase;
        }

        .report-header {
            background: #0f2547;
            border-bottom: 4px solid #9fb7e0;
            padding: 14px 0.6in;
            color: #fff;
            text-align: center;
        }

        .header-title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .header-subtitle {
            font-size: 10px;
            letter-spacing: 0.06em;
            color: #9fb7e0;
            margin-top: 3px;
            text-transform: uppercase;
        }

        .content {
            padding: 0 0.6in;
        }

        h1 {
            font-size: 24px;
            line-height: 1.15;
            color: #0f2547;
            margin: 26px 0 4px;
        }

        .identity {
            font-size: 11.5px;
            color: #3a4252;
            margin-bottom: 18px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .summary td {
            width: 25%;
            border: 1px solid #d7dce6;
            padding: 10px 12px;
            vertical-align: top;
        }

        .summary-value {
            font-size: 17px;
            font-weight: bold;
            color: #0f2547;
        }

        .summary-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }

        /*
          The alert colour RE-02 requires. It is resolved server-side by
          WorkloadStatusFormatter::pdfPalette() so the printed badge and
          the on-screen badge are always the same verdict — this template
          only paints what it is told.
        */
        .verdict {
            display: inline-block;
            margin: 14px 0 18px;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid {{ $palette['border'] }};
            background: {{ $palette['background'] }};
            color: {{ $palette['text'] }};
            font-size: 12px;
            font-weight: bold;
        }

        table.groups {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.groups th {
            background: #0f2547;
            color: #fff;
            padding: 9px 7px;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        table.groups td {
            padding: 8px 7px;
            font-size: 10px;
            border-bottom: 1px solid #e4e8ef;
        }

        table.groups tbody tr:nth-child(even) {
            background: #f6f8fc;
        }

        table.groups tfoot td {
            border-top: 2px solid #0f2547;
            font-weight: bold;
            font-size: 10.5px;
            background: #fff;
        }

        .num {
            text-align: right;
        }

        .empty {
            text-align: center;
            font-style: italic;
            color: #8a92a0;
            padding: 20px 8px;
        }

        .issued {
            margin: 16px 0 40px;
            font-size: 9.5px;
            color: #6b7280;
        }

        @page {
            size: letter;
            /* The extra bottom margin is the strip .page-legend occupies. */
            margin: 0 0 0.9in;
        }
    </style>
</head>

<body>

    <div class="page-legend">{{ $legend }}</div>

    <header class="report-header">
        <div class="header-title">Universidad T&eacute;cnica Nacional</div>
        <div class="header-subtitle">Sede Regional San Carlos &middot; SIGA</div>
    </header>

    <main class="content">
        <h1>{{ __('Teacher load report') }}</h1>
        <div class="identity">
            <strong>{{ $summary['teacherName'] }}</strong> &middot; {{ $summary['identityCard'] }} &middot; {{ $summary['term'] }}
        </div>

        <div class="verdict">{{ $summary['statusLabel'] }}</div>

        <table class="summary">
            <tr>
                <td>
                    <div class="summary-value">{{ $summary['assigned'] }}</div>
                    <div class="summary-label">{{ __('Assigned workload') }}</div>
                </td>
                <td>
                    <div class="summary-value">{{ $summary['reference'] }}</div>
                    <div class="summary-label">{{ __('Estimated workload') }}</div>
                </td>
                <td>
                    <div class="summary-value">{{ $summary['utilization'] }}%</div>
                    <div class="summary-label">{{ __('Usage of the reference workload') }}</div>
                </td>
                <td>
                    <div class="summary-value">{{ $summary['groups'] }}</div>
                    <div class="summary-label">{{ __('Assigned groups') }}</div>
                </td>
            </tr>
        </table>

        <div class="issued">{{ __('Issue date') }}: {{ $generatedAt }}</div>

        <table class="groups">
            <thead>
                <tr>
                    @foreach ($headers as $header)
                    <th>{{ $header['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['groupCode'] }}</td>
                    <td>{{ $row['courseCode'] }}</td>
                    <td>{{ $row['classroom'] }}</td>
                    <td>{{ $row['modality'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="num">{{ $row['estimatedEnrollment'] }}</td>
                    <td class="num">{{ $row['assignedWorkload'] }}</td>
                </tr>
                @empty
                <tr>
                    <td class="empty" colspan="{{ count($headers) }}">{{ __('This teacher has no groups assigned in the selected term.') }}</td>
                </tr>
                @endforelse
            </tbody>
            @if (count($rows) > 0)
            <tfoot>
                <tr>
                    <td colspan="{{ count($headers) - 2 }}">{{ __('Total') }}</td>
                    <td class="num">{{ $summary['students'] }}</td>
                    <td class="num">{{ $summary['assigned'] }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </main>

</body>

</html>
