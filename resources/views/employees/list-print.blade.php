@php
    $counts = $data['counts'];
    $employees = $data['employees'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('al-mohafiz-logo.png') }}">
    <title>Employee List - {{ $data['typeLabel'] }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
        }

        .toolbar {
            width: 210mm;
            margin: 16px auto 0;
            display: flex;
            justify-content: flex-end;
        }

        .toolbar button {
            border: 0;
            border-radius: 6px;
            background: #111827;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 10px 16px;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: #fff;
            padding: 12mm;
            box-shadow: 0 12px 34px rgba(15, 23, 42, 0.16);
        }

        .header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: center;
            border-bottom: 3px solid #111827;
            padding-bottom: 10px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }

        .brand h1 {
            margin: 0;
            font-size: 19px;
            letter-spacing: -0.01em;
        }

        .brand p {
            margin: 2px 0 0;
            font-size: 11px;
            color: #4b5563;
        }

        .meta {
            text-align: right;
            font-size: 11px;
            color: #4b5563;
        }

        .meta strong {
            display: block;
            font-size: 15px;
            color: #111827;
        }

        .stats {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border: 1px solid #d1d5db;
        }

        .stat {
            padding: 9px 10px;
            border-right: 1px solid #e5e7eb;
        }

        .stat:last-child { border-right: 0; }

        .stat span {
            display: block;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .stat strong {
            display: block;
            margin-top: 3px;
            font-size: 16px;
        }

        table {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
            font-size: 10.5px;
        }

        thead th {
            background: #111827;
            color: #fff;
            font-size: 9.5px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 7px 6px;
            text-align: left;
            border: 1px solid #111827;
        }

        tbody td {
            padding: 6px;
            border: 1px solid #d1d5db;
        }

        tbody tr:nth-child(even) td { background: #f9fafb; }

        .code {
            font-family: "Courier New", monospace;
            color: #374151;
        }

        .pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
        }

        .pill.active { background: #dcfce7; color: #166534; }
        .pill.on_leave { background: #fef3c7; color: #92400e; }
        .pill.left { background: #e5e7eb; color: #4b5563; }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 9.5px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="page">
        <div class="header">
            <div class="brand">
                <img src="{{ asset('al-mohafiz-logo.png') }}" alt="Al Mohafiz">
                <div>
                    <h1>Al Mohafiz Building Contracting LLC</h1>
                    <p>Employee List</p>
                </div>
            </div>
            <div class="meta">
                <strong>{{ $data['typeLabel'] }}</strong>
                Generated {{ $generatedAt }}
            </div>
        </div>

        <div class="stats">
            <div class="stat"><span>Total</span><strong>{{ $counts['total'] }}</strong></div>
            <div class="stat"><span>Active</span><strong>{{ $counts['active'] }}</strong></div>
            <div class="stat"><span>On Leave</span><strong>{{ $counts['on_leave'] }}</strong></div>
            <div class="stat"><span>Left</span><strong>{{ $counts['left'] }}</strong></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 12%">Code</th>
                    <th style="width: 33%">Name</th>
                    <th style="width: 27%">Profession</th>
                    <th style="width: 14%">Status</th>
                    <th style="width: 14%">Added On</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td class="code">{{ $employee['code'] }}</td>
                        <td>{{ $employee['name'] }}</td>
                        <td>{{ $employee['profession'] }}</td>
                        <td>
                            <span class="pill {{ $employee['status'] }}">{{ $employee['statusLabel'] }}</span>
                        </td>
                        <td>{{ $employee['addedOn'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 22px; color: #6b7280;">
                            No employees in this category.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            <span>{{ $counts['total'] }} employee{{ $counts['total'] === 1 ? '' : 's' }} listed.</span>
            <span>Al Mohafiz ERP</span>
        </div>
    </div>
</body>
</html>
