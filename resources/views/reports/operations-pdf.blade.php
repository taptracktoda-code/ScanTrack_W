<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28pt 34pt 40pt; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.3; color: #202833; }
        header { padding-bottom: 12pt; border-bottom: 1pt solid #cbd5df; }
        .brand { margin: 0 0 2pt; font-size: 9pt; font-weight: bold; color: #234c60; }
        .federation { margin: 0; font-size: 8.5pt; color: #4b5663; }
        h1 { margin: 9pt 0 6pt; font-size: 19pt; line-height: 1.2; font-weight: bold; }
        .metadata { margin: 2pt 0 0; font-size: 8pt; color: #4b5663; }
        h2 { margin: 15pt 0 5pt; font-size: 11pt; line-height: 1.25; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { vertical-align: top; text-align: left; overflow-wrap: break-word; word-wrap: break-word; }
        th { padding: 6pt; font-size: 8.25pt; background: #edf2f5; color: #233b4c; border-bottom: 1pt solid #bdc9d4; }
        td { padding: 5pt 6pt; border-bottom: 0.5pt solid #dbe1e7; }
        td:first-child .cell-line:first-child { font-weight: bold; }
        .cell-line + .cell-line { margin-top: 1pt; }
        .cell-label { font-size: 8pt; color: #4b5663; }
        .numeric { text-align: right; }
        .empty { padding: 9pt 6pt; }
    </style>
</head>
<body>
    <header>
        <p class="brand">SCANTRACK / SCFTODA</p>
        <p class="federation">Surigao City Federation of Tricycle Operators and Drivers Association</p>
        <h1>{{ $title }}</h1>
        @if ($organization)
            <p class="metadata"><strong>TODA</strong> {{ $organization }}</p>
        @endif
        <p class="metadata"><strong>Reporting period</strong> {{ $period }} · Philippine Standard Time (Asia/Manila)</p>
        <p class="metadata"><strong>Generated</strong> {{ $generatedAt }}</p>
    </header>
    @foreach ($sections as $section)
        @php
            $numericColumn = array_search('Trips completed (all time)', array_keys($section['headers']), true);
        @endphp
        <h2>{{ $section['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($section['headers'] as $heading => $width)
                        <th scope="col" @class(['numeric' => $loop->index === $numericColumn]) style="width: {{ $width }}%">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $cells)
                    <tr>
                        @foreach ($cells as $cell)
                            <td @class(['numeric' => $loop->index === $numericColumn])>
                                @foreach ((array) $cell as $label => $value)
                                    <div class="cell-line">
                                        @if (is_string($label))
                                            <span class="cell-label">{{ $label }}</span>
                                        @endif
                                        {{ $value }}
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($section['headers']) }}">{{ $section['empty'] ?? 'No matching records.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
