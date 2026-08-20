@php
    $project = $history['project'];
    $totals = $history['totals'];
    $employees = $history['employeeSummary'];
    $missing = $history['missingPayrollEmployees'];
    $money = fn ($value) => number_format((float) $value, 2);
    $hasOverhead = (bool) ($history['overhead']['enabled'] ?? false);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('al-mohafiz-logo.png') }}">
    <title>Employee History - {{ $project['name'] }}</title>
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

        .project-line {
            margin: 12px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 22px;
            font-size: 11.5px;
        }

        .project-line span b {
            color: #4b5563;
            font-weight: 700;
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
            border-bottom: 1px solid #e5e7eb;
        }

        .stat:nth-child(4n) { border-right: 0; }
        .stat:nth-last-child(-n+4) { border-bottom: 0; }

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
            font-size: 15px;
        }

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
        tbody tr.flagged td { background: #fef3c7; }

        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .code { color: #6b7280; font-size: 9.5px; }

        tfoot td {
            padding: 7px 6px;
            border: 1px solid #9ca3af;
            background: #e5e7eb;
            font-weight: 800;
        }

        .share-bar {
            display: block;
            height: 3px;
            margin-top: 3px;
            background: #111827;
        }

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
                    <p>Project Employee History</p>
                </div>
            </div>
            <div class="meta">
                <strong>{{ $project['name'] }}</strong>
                {{ $project['typeLabel'] }}
            </div>
        </div>

        <div class="project-line">
            @if ($project['code'])
                <span><b>Project Code:</b> {{ $project['code'] }}</span>
            @endif
            <span><b>Status:</b> {{ ucfirst($project['status']) }}</span>
            <span><b>Contract Value:</b> {{ $project['contractValue'] !== null ? 'AED '.$money($project['contractValue']) : 'Not set' }}</span>
            <span><b>Date Range:</b> {{ $history['rangeLabel'] }}</span>
            <span><b>Generated:</b> {{ $generatedAt }}</span>
        </div>

        <div class="stats">
            <div class="stat"><span>Number of Person</span><strong>{{ $totals['uniqueEmployees'] }}</strong></div>
            <div class="stat"><span>Head Count</span><strong>{{ $totals['entries'] }}</strong></div>
            <div class="stat"><span>Worked Days</span><strong>{{ $totals['workedDays'] }}</strong></div>
            <div class="stat"><span>Overtime Hours</span><strong>{{ $totals['overtimeHours'] }}</strong></div>
            <div class="stat"><span>Basic Cost</span><strong>{{ $money($totals['basicCost']) }}</strong></div>
            <div class="stat"><span>Overtime Cost</span><strong>{{ $money($totals['overtimeCost']) }}</strong></div>
            @if ($hasOverhead)
                <div class="stat"><span>Overhead</span><strong>{{ $money($totals['overheadCost']) }}</strong></div>
            @endif
            <div class="stat total"><span>Total Labour Cost</span><strong>{{ $money($totals['totalCost']) }}</strong></div>
            <div class="stat"><span>Currency</span><strong>AED</strong></div>
        </div>

        @if (count($missing))
            <p class="warning">
                Cost is incomplete. No salary setting for: {{ $missing->implode(', ') }}
            </p>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width: 8%">Code</th>
                    <th style="width: 24%">Employee</th>
                    <th style="width: 17%">Profession</th>
                    <th class="num" style="width: 7%">Entries</th>
                    <th class="num" style="width: 9%">Days</th>
                    <th class="num" style="width: 7%">OT Hrs</th>
                    <th class="num" style="width: 10%">Basic</th>
                    <th class="num" style="width: 9%">OT Cost</th>
                    @if ($hasOverhead)<th class="num" style="width: 10%">Overhead</th>@endif
                    <th class="num" style="width: 11%">Total</th>
                    <th class="num" style="width: 8%">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="{{ $employee['missingPayrollSetting'] ? 'flagged' : '' }}">
                        <td class="code">{{ $employee['employeeCode'] }}</td>
                        <td>{{ $employee['employeeName'] }}</td>
                        <td>{{ $employee['profession'] }}</td>
                        <td class="num">{{ $employee['entries'] }}</td>
                        <td class="num">{{ $employee['workedDays'] }}</td>
                        <td class="num">{{ $employee['overtimeHours'] }}</td>
                        <td class="num">{{ $money($employee['basicCost']) }}</td>
                        <td class="num">{{ $money($employee['overtimeCost']) }}</td>
                        @if ($hasOverhead)<td class="num">{{ $money($employee['overheadCost']) }}</td>@endif
                        <td class="num">{{ $money($employee['totalCost']) }}</td>
                        <td class="num">
                            {{ $employee['costShare'] }}%
                            <span class="share-bar" style="width: {{ min(100, $employee['costShare']) }}%"></span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $hasOverhead ? 11 : 10 }}" style="text-align: center; padding: 22px; color: #6b7280;">
                            No attendance recorded for this project in the selected range.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($employees))
                <tfoot>
                    <tr>
                        <td colspan="3">TOTAL</td>
                        <td class="num">{{ $totals['entries'] }}</td>
                        <td class="num">{{ $totals['workedDays'] }}</td>
                        <td class="num">{{ $totals['overtimeHours'] }}</td>
                        <td class="num">{{ $money($totals['basicCost']) }}</td>
                        <td class="num">{{ $money($totals['overtimeCost']) }}</td>
                        @if ($hasOverhead)<td class="num">{{ $money($totals['overheadCost']) }}</td>@endif
                        <td class="num">{{ $money($totals['totalCost']) }}</td>
                        <td class="num">100%</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="footer">
            <span>Rows are ordered by cost. Share is each employee's portion of the project's labour cost.</span>
            <span>Al Mohafiz ERP</span>
        </div>
    </div>
</body>
</html>
