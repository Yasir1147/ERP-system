<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Office Staff Monthly Timesheet - {{ $monthLabel }}</title>
    <style>
        @page { size: A3 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font: 11px Arial, sans-serif; color: #172b40; margin: 20px; }
        h1 { font-size: 20px; margin-bottom: 6px; } h2 { font-size: 16px; font-weight: normal; }
        .summary { margin: 16px 0; padding: 12px; border: 1px solid #cad3dc; }
        .legend { margin: 12px 0; color: #405366; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9px; }
        th, td { border: 1px solid #cbd5e1; text-align: center; padding: 6px 2px; overflow-wrap: anywhere; }
        thead th { background: #1e293b; color: white; } th.staff { width: 170px; text-align: left; padding-left: 6px; }
        th.total { width: 48px; } .weekday { font-size: 8px; font-weight: normal; margin-top: 4px; }
        .P { background: #dcfce7; color: #166534; font-weight: bold; } .L { background: #fef3c7; color: #92400e; } .LP { background: #dbeafe; color: #1e40af; }
        .totals { background: #edf0f3; font-weight: bold; } tr { break-inside: avoid; } thead { display: table-header-group; }
        button { padding: 9px 16px; cursor: pointer; } .hint { color: #586879; }
        @media print { body { margin: 0; } .controls { display: none; } * { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="controls"><button onclick="window.print()">Print / Save as PDF</button><p>Select A3 landscape for the full monthly grid.</p></div>
    <h1>Al Mohafiz Building Contracting LLC</h1>
    <h2>Office Staff Monthly Timesheet · {{ $monthLabel }}</h2>
    <div class="summary">Staff: <strong>{{ $totals['staff'] }}</strong> &nbsp; | &nbsp; Present days: <strong>{{ $totals['present'] }}</strong> &nbsp; | &nbsp; Approved leave: <strong>{{ $totals['leave'] }}</strong> &nbsp; | &nbsp; Pending leave: <strong>{{ $totals['pending'] }}</strong> &nbsp; | &nbsp; Work hours: <strong>{{ $totals['workLabel'] }}</strong></div>
    <p class="legend">P = Present &nbsp; L = Approved leave &nbsp; LP = Pending leave &nbsp; – = No record (not marked absent)</p>
    <table>
        <thead><tr><th class="staff" scope="col">Staff</th>@foreach($days as $day)<th scope="col">{{ $day['number'] }}<div class="weekday">{{ $day['weekday'] }}</div></th>@endforeach<th class="total" scope="col">Present</th><th class="total" scope="col">Leave</th><th class="total" scope="col">Pending</th><th class="total" scope="col">Work Hours</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr><th class="staff" scope="row">{{ $row['code'] }} - {{ $row['name'] }}</th>@foreach($row['cells'] as $cell)<td class="{{ $cell['status'] }}">{{ $cell['status'] === '-' ? '–' : $cell['status'] }}</td>@endforeach<td>{{ $row['present'] }}</td><td>{{ $row['leave'] }}</td><td>{{ $row['pending'] }}</td><td>{{ $row['workLabel'] }}</td></tr>
        @empty
            <tr><td colspan="{{ count($days) + 5 }}">No staff match these filters.</td></tr>
        @endforelse
        <tr class="totals"><th class="staff" scope="row">Daily present / Totals</th>@foreach($dailyPresent as $count)<td>{{ $count }}</td>@endforeach<td>{{ $totals['present'] }}</td><td>{{ $totals['leave'] }}</td><td>{{ $totals['pending'] }}</td><td>{{ $totals['workLabel'] }}</td></tr>
        </tbody>
    </table>
    <p class="hint">All calendar dates are shown. Weekly off-days are not assumed. Work hours follow Attendance Report rules. Open sessions today show elapsed hours.</p>
</body>
</html>
