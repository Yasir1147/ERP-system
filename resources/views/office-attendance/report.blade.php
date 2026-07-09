<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Office Attendance Report</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=al-mohafiz">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=al-mohafiz">
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; background: #e5e7eb; font-size: 11px; }
        .toolbar { padding: 10px; text-align: right; }
        .toolbar button { border: 0; border-radius: 4px; background: #111827; color: #fff; padding: 8px 12px; font-weight: 700; cursor: pointer; }
        .sheet { width: 100%; min-height: 190mm; margin: 0 auto; background: #fff; padding: 9mm; border: 1px solid #cbd5e1; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { width: 44px; height: 44px; object-fit: contain; }
        h1 { margin: 0; font-size: 22px; line-height: 1.1; letter-spacing: -.02em; }
        h2 { margin: 16px 0 7px; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #334155; }
        .muted { color: #64748b; }
        .meta { text-align: right; line-height: 1.5; }
        .stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin: 8px 0 14px; }
        .stat { border: 1px solid #dbe3ef; border-radius: 7px; padding: 9px 10px; background: #f8fafc; }
        .stat-label { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; }
        .stat-value { margin-top: 3px; font-size: 15px; font-weight: 800; color: #0f172a; }
        .rule-line { margin: -4px 0 12px; color: #475569; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 7px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #edf2f7; text-align: left; font-weight: 800; color: #0f172a; }
        tbody tr:nth-child(even) { background: #fbfdff; }
        .center { text-align: center; }
        .right { text-align: right; }
        .pill { display: inline-block; border-radius: 999px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; padding: 2px 7px; font-size: 10px; font-weight: 700; white-space: nowrap; }
        .session-list { display: flex; flex-wrap: wrap; gap: 4px; }
        .session-chip { display: inline-block; border-radius: 999px; border: 1px solid #fbbf24; background: #fffbeb; color: #92400e; padding: 2px 7px; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .time { font-weight: 800; white-space: nowrap; }
        .late { color: #b91c1c; font-weight: 800; }
        .ontime { color: #047857; font-weight: 800; }
        .ot { color: #92400e; font-weight: 800; }
        .summary th:nth-child(1), .summary td:nth-child(1) { width: 28%; }
        .summary th:nth-child(2), .summary td:nth-child(2) { width: 22%; }
        .summary th:nth-child(3), .summary td:nth-child(3),
        .summary th:nth-child(4), .summary td:nth-child(4),
        .summary th:nth-child(5), .summary td:nth-child(5) { width: 10%; }
        .detail th:nth-child(1), .detail td:nth-child(1) { width: 9%; }
        .detail th:nth-child(2), .detail td:nth-child(2) { width: 18%; }
        .detail th:nth-child(3), .detail td:nth-child(3) { width: 14%; }
        .detail th:nth-child(4), .detail td:nth-child(4) { width: 9%; }
        .detail th:nth-child(5), .detail td:nth-child(5),
        .detail th:nth-child(6), .detail td:nth-child(6) { width: 8%; }
        .detail th:nth-child(7), .detail td:nth-child(7) { width: 15%; }
        .detail th:nth-child(8), .detail td:nth-child(8),
        .detail th:nth-child(9), .detail td:nth-child(9),
        .detail th:nth-child(10), .detail td:nth-child(10) { width: 7%; }
        .detail th:nth-child(11), .detail td:nth-child(11) { width: 10%; }
        .detail th:nth-child(12), .detail td:nth-child(12) { width: 7%; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { border: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print PDF</button>
    </div>

    <main class="sheet">
        <header class="header">
            <div class="brand">
                <img src="{{ asset('al-mohafiz-logo.png') }}" alt="Al Mohafiz">
                <div>
                    <h1>Office Attendance Report</h1>
                    <div class="muted">Al Mohafiz Building Contracting L.L.C.</div>
                </div>
            </div>
            <div class="meta">
                <strong>{{ $staffLabel }}</strong><br>
                {{ $fromLabel }} to {{ $toLabel }}<br>
                Generated {{ now()->format('d/m/Y h:i A') }}
            </div>
        </header>

        <section class="stats">
            <div class="stat">
                <div class="stat-label">Staff</div>
                <div class="stat-value">{{ $summaryRows->count() }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Office Days</div>
                <div class="stat-value">{{ $summaryRows->sum('officeDays') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Remote Days</div>
                <div class="stat-value">{{ $summaryRows->sum('remoteDays') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Total Records</div>
                <div class="stat-value">{{ $attendanceRows->count() }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Work Hours</div>
                <div class="stat-value">{{ $reportTotals['workLabel'] }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">OT / Late</div>
                <div class="stat-value">{{ $reportTotals['overtimeLabel'] }} / {{ $reportTotals['lateCount'] }}</div>
            </div>
        </section>

        <div class="rule-line">
            Office {{ \Carbon\Carbon::createFromFormat('H:i', $officeRules['office_start_time'])->format('g:i A') }}
            to {{ \Carbon\Carbon::createFromFormat('H:i', $officeRules['office_end_time'])->format('g:i A') }}.
            Break {{ $officeRules['break_start_time'] ? \Carbon\Carbon::createFromFormat('H:i', $officeRules['break_start_time'])->format('g:i A') : '-' }}
            to {{ $officeRules['break_end_time'] ? \Carbon\Carbon::createFromFormat('H:i', $officeRules['break_end_time'])->format('g:i A') : '-' }}
            ({{ $officeRules['break_included'] ? 'included' : 'deducted' }}).
            Grace {{ $officeRules['late_grace_minutes'] }} minutes.
        </div>

        <h2>Staff Summary</h2>
        <table class="summary">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Designation</th>
                    <th class="center">Type</th>
                    <th class="center">Office</th>
                    <th class="center">Remote</th>
                    <th class="center">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaryRows as $row)
                    <tr>
                        <td><strong>{{ $row['code'] }} - {{ $row['name'] }}</strong></td>
                        <td>{{ $row['designation'] ?: '-' }}</td>
                        <td class="center"><span class="pill">{{ $row['staffTypeLabel'] }}</span></td>
                        <td class="center">{{ $row['officeDays'] }}</td>
                        <td class="center">{{ $row['remoteDays'] }}</td>
                        <td class="center"><strong>{{ $row['totalDays'] }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="center muted">No staff found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Attendance Detail</h2>
        <table class="detail">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Staff</th>
                    <th>Designation</th>
                    <th>Work Mode</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Sessions</th>
                    <th>Work Hrs</th>
                    <th>OT</th>
                    <th>Late</th>
                    <th>Note</th>
                    <th>Submitted By</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendanceRows as $row)
                    <tr>
                        <td>{{ $row['dateLabel'] }}</td>
                        <td><strong>{{ $row['staffCode'] }} - {{ $row['staffName'] }}</strong></td>
                        <td>{{ $row['designation'] ?: '-' }}</td>
                        <td><span class="pill">{{ $row['workModeLabel'] }}</span></td>
                        <td><span class="time">{{ $row['checkInDisplay'] ?: '-' }}</span></td>
                        <td><span class="time">{{ $row['checkOutDisplay'] ?: '-' }}</span></td>
                        <td>
                            @if ($row['sessionDisplaySegments']->isNotEmpty())
                                <div class="session-list">
                                    @foreach ($row['sessionDisplaySegments'] as $session)
                                        <span class="session-chip">{{ $session }}</span>
                                    @endforeach
                                </div>
                            @else
                                -
                            @endif
                        </td>
                        <td><strong>{{ $row['workHoursLabel'] }}</strong></td>
                        <td class="{{ $row['overtimeMinutes'] > 0 ? 'ot' : '' }}">{{ $row['overtimeLabel'] }}</td>
                        <td class="{{ $row['isLate'] ? 'late' : 'ontime' }}">{{ $row['lateLabel'] }}</td>
                        <td>{{ $row['note'] ?: '-' }}</td>
                        <td>{{ $row['submittedBy'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="center muted">No attendance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
