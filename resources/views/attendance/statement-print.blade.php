@php
    $subject = $statement['subject'];
    $rows = $statement['rows'];
    $totals = $statement['totals'];
    $withSalary = $statement['withSalary'];
    $isProject = $statement['mode'] === 'project';
    $money = fn ($value) => number_format((float) $value, 2);
    $title = $isProject ? 'Project Attendance Statement' : 'Employee Attendance Statement';
    $columnCount = 6 + ($isProject ? 3 : 0) + ($withSalary ? 4 : 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('al-mohafiz-logo.png') }}">
    <title>{{ $title }} - {{ $subject['name'] }}</title>
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
            gap: 8px;
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

        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 52px; height: 52px; object-fit: contain; }
        .brand h1 { margin: 0; font-size: 19px; letter-spacing: -0.01em; }
        .brand p { margin: 2px 0 0; font-size: 11px; color: #4b5563; }

        .meta { text-align: right; font-size: 11px; color: #4b5563; }
        .meta strong { display: block; font-size: 15px; color: #111827; }

        .subject-line {
            margin: 12px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 22px;
            font-size: 11.5px;
        }

        .subject-line span b { color: #4b5563; font-weight: 700; }

        .stats {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border: 1px solid #d1d5db;
        }

        .stat { padding: 9px 10px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
        .stat:nth-child(4n) { border-right: 0; }
        .stat:nth-last-child(-n+4) { border-bottom: 0; }

        .stat span {
            display: block;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .stat strong { display: block; margin-top: 3px; font-size: 15px; }
        .stat.total strong { color: #b45309; }

        .warning {
            margin-top: 12px;
            padding: 8px 10px;
            border-left: 3px solid #b45309;
            background: #fef3c7;
            color: #92400e;
            font-size: 10.5px;
            font-weight: 700;
        }

        table { width: 100%; margin-top: 14px; border-collapse: collapse; font-size: 10.5px; }

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

        tbody td { padding: 6px; border: 1px solid #d1d5db; }
        tbody tr:nth-child(even) td { background: #f9fafb; }

        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .code { color: #6b7280; font-size: 9.5px; }

        .pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9px;
            font-size: 9.5px;
            font-weight: 700;
        }

        .pill.present { background: #dcfce7; color: #166534; }
        .pill.absent { background: #fee2e2; color: #991b1b; }
        .pill.leave { background: #fef3c7; color: #92400e; }

        tfoot td { padding: 7px 6px; border: 1px solid #9ca3af; background: #e5e7eb; font-weight: 800; }

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
            .page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
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
                    <p>{{ $title }}</p>
                </div>
            </div>
            <div class="meta">
                <strong>{{ $subject['name'] }}</strong>
                {{ $subject['typeLabel'] }}
            </div>
        </div>

        <div class="subject-line">
            @if ($subject['code'])
                <span><b>{{ $isProject ? 'Project Code:' : 'Employee Code:' }}</b> {{ $subject['code'] }}</span>
            @endif
            @if ($subject['profession'])
                <span><b>{{ $isProject ? 'Client:' : 'Profession:' }}</b> {{ $subject['profession'] }}</span>
            @endif
            <span><b>Status:</b> {{ ucfirst($subject['status']) }}</span>
            <span><b>Date Range:</b> {{ $statement['rangeLabel'] }}</span>
            <span><b>Generated:</b> {{ $generatedAt }}</span>
        </div>

        <div class="stats">
            <div class="stat"><span>Present Days</span><strong>{{ $totals['presentDays'] }}</strong></div>
            <div class="stat"><span>Absent</span><strong>{{ $totals['absent'] }}</strong></div>
            <div class="stat"><span>Leave</span><strong>{{ $totals['leave'] }}</strong></div>
            <div class="stat"><span>Overtime Hours</span><strong>{{ $totals['overtimeHours'] }}</strong></div>
            @if ($isProject)
                <div class="stat"><span>Employees</span><strong>{{ $totals['uniqueEmployees'] }}</strong></div>
            @else
                <div class="stat"><span>Projects</span><strong>{{ $totals['projects'] }}</strong></div>
            @endif
            @if ($withSalary)
                <div class="stat"><span>Basic Cost</span><strong>{{ $money($totals['basicCost']) }}</strong></div>
                <div class="stat"><span>Overtime Cost</span><strong>{{ $money($totals['overtimeCost']) }}</strong></div>
                <div class="stat total"><span>Total Cost</span><strong>{{ $money($totals['totalCost']) }}</strong></div>
            @else
                <div class="stat"><span>Entries</span><strong>{{ $totals['entries'] }}</strong></div>
            @endif
        </div>

        @if ($withSalary && $subject['missingSalary'])
            <p class="warning">Cost is incomplete. This employee has no salary setting, so every day is costed at zero.</p>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width: 11%">Date</th>
                    <th style="width: 6%">Day</th>
                    @if ($isProject)
                        <th style="width: 7%">Code</th>
                        <th style="width: 18%">Employee</th>
                        <th style="width: 13%">Profession</th>
                    @endif
                    <th>Project</th>
                    <th style="width: 9%">Status</th>
                    <th class="num" style="width: 6%">Day</th>
                    <th class="num" style="width: 6%">OT</th>
                    @unless ($isProject)
                        <th style="width: 16%">Note</th>
                    @endunless
                    @if ($withSalary)
                        <th class="num" style="width: 9%">Basic</th>
                        <th class="num" style="width: 8%">OT Cost</th>
                        <th class="num" style="width: 9%">Total</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td class="code">{{ $row['weekday'] }}</td>
                        @if ($isProject)
                            <td class="code">{{ $row['employeeCode'] ?? '-' }}</td>
                            <td>{{ $row['employeeName'] }}</td>
                            <td class="code">{{ $row['profession'] ?? '-' }}</td>
                        @endif
                        <td>{{ $row['projectName'] ?: '-' }}</td>
                        <td><span class="pill {{ $row['status'] }}">{{ ucfirst($row['status']) }}</span></td>
                        <td class="num">{{ $row['dayValue'] }}</td>
                        <td class="num">{{ $row['overtimeHours'] ?: '-' }}</td>
                        @unless ($isProject)
                            <td class="code">{{ $row['note'] ?: '-' }}</td>
                        @endunless
                        @if ($withSalary)
                            <td class="num">{{ $money($row['basicCost']) }}</td>
                            <td class="num">{{ $money($row['overtimeCost']) }}</td>
                            <td class="num">{{ $money($row['totalCost']) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" style="text-align: center; padding: 22px; color: #6b7280;">
                            No attendance recorded in this date range.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($rows))
                <tfoot>
                    <tr>
                        <td colspan="{{ $isProject ? 6 : 3 }}">TOTAL</td>
                        <td></td>
                        <td class="num">{{ $totals['presentDays'] }}</td>
                        <td class="num">{{ $totals['overtimeHours'] }}</td>
                        @unless ($isProject)
                            <td></td>
                        @endunless
                        @if ($withSalary)
                            <td class="num">{{ $money($totals['basicCost']) }}</td>
                            <td class="num">{{ $money($totals['overtimeCost']) }}</td>
                            <td class="num">{{ $money($totals['totalCost']) }}</td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="footer">
            <span>Present Day counts a half day as 0.5. Leave days from an approved leave range appear even where no daily record exists.</span>
            <span>Al Mohafiz ERP</span>
        </div>
    </div>
</body>
</html>
