<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 30pt 36pt 38pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #17243a; }
        header { border-bottom: 2pt solid #245c73; padding-bottom: 11pt; margin-bottom: 16pt; }
        header p, header h1 { margin: 0; }
        .federation { font-size: 17pt; font-weight: bold; color: #12334e; }
        .brand { font-size: 9pt; font-weight: bold; letter-spacing: 2pt; margin-top: 8pt; color: #245c73; }
        h1 { font-size: 17pt; line-height: 1.2; }
        .metadata { margin-top: 7pt; color: #40526a; }
        h2 { margin: 14pt 0 5pt; font-size: 11pt; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 5pt; vertical-align: top; text-align: left; overflow-wrap: break-word; word-wrap: break-word; }
        th { background: #193c56; color: #fff; font-size: 7.5pt; }
        td { border-bottom: 0.6pt solid #d2dce5; }
        tr:nth-child(even) td { background: #eef4f8; }
    </style>
</head>
<body>
    <header>
        <p class="federation">SCFTODA</p>
        <p>Surigao City Federation of Tricycle Operators and Drivers Association</p>
        <p class="brand">SCANTRACK</p>
        <h1>DRIVER ENTRY, EXIT AND QUEUE MONITORING REPORT</h1>
        <div class="metadata">
            <strong>{{ $title }}</strong><br>
            Reporting period {{ $period }} · Philippine Standard Time (Asia/Manila)<br>
            Generated {{ $generatedAt }}
        </div>
    </header>
    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($section['headers'] as $heading => $width)
                        <th style="width: {{ $width }}%">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $cells)
                    <tr>
                        @foreach ($cells as $cell)
                            <td>{!! nl2br(e($cell)) !!}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['headers']) }}">{{ $section['empty'] ?? 'No matching records.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
