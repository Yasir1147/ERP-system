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
        body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; background: #f3f4f6; font-size: 11px; }
        .toolbar { padding: 10px; text-align: right; }
        .toolbar button { border: 0; border-radius: 4px; background: #111827; color: #fff; padding: 8px 12px; font-weight: 700; cursor: pointer; }
        .sheet { width: 100%; min-height: 190mm; margin: 0 auto; background: #fff; padding: 10mm; border: 1px solid #111827; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { width: 42px; height: 42px; object-fit: contain; }
        h1 { margin: 0; font-size: 22px; line-height: 1.1; }
        h2 { margin: 16px 0 6px; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
        .muted { color: #475569; }
        .meta { text-align: right; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #475569; padding: 5px 6px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #f1f5f9; text-align: left; font-weight: 700; }
        .center { text-align: center; }
        .right { text-align: right; }
        .summary th:nth-child(1), .summary td:nth-child(1) { width: 28%; }
        .summary th:nth-child(2), .summary td:nth-child(2) { width: 22%; }
        .summary th:nth-child(3), .summary td:nth-child(3),
        .summary th:nth-child(4), .summary td:nth-child(4),
        .summary th:nth-child(5), .summary td:nth-child(5) { width: 10%; }
        .detail th:nth-child(1), .detail td:nth-child(1) { width: 10%; }
        .detail th:nth-child(2), .detail td:nth-child(2) { width: 22%; }
        .detail th:nth-child(3), .detail td:nth-child(3) { width: 16%; }
        .detail th:nth-child(4), .detail td:nth-child(4) { width: 12%; }
        .detail th:nth-child(5), .detail td:nth-child(5),
        .detail th:nth-child(6), .detail td:nth-child(6) { width: 9%; }
        .detail th:nth-child(7), .detail td:nth-child(7) { width: 12%; }
        .detail th:nth-child(8), .detail td:nth-child(8) { width: 10%; }
        .detail th:nth-child(9), .detail td:nth-child(9) { width: 8%; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { border: 0; padding: 0; }
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
                        <td class="center">{{ $row['staffTypeLabel'] }}</td>
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
                        <td>{{ $row['workModeLabel'] }}</td>
                        <td>{{ $row['checkInTime'] ?: '-' }}</td>
                        <td>{{ $row['checkOutTime'] ?: '-' }}</td>
                        <td>{{ $row['sessionSummary'] ?: '-' }}</td>
                        <td>{{ $row['note'] ?: '-' }}</td>
                        <td>{{ $row['submittedBy'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="center muted">No attendance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
