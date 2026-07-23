<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('al-mohafiz-logo.png') }}">
    <title>Attendance Timesheet - {{ $typeLabel }} - {{ $monthLabel }}</title>
    <style>
        @page {
            size: {{ $page['size'] }} landscape;
            margin: {{ $page['margin'] }};
        }

        * {
            box-sizing: border-box;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: {{ $page['font'] }};
        }

        .toolbar {
            width: calc(100vw - 32px);
            margin: 12px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .toolbar button,
        .toolbar a {
            border: 0;
            border-radius: 6px;
            background: #111827;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            padding: 9px 14px;
            text-decoration: none;
        }

        .page {
            width: calc(100vw - 32px);
            margin: 12px auto;
            background: #fff;
            padding: 6mm;
            box-shadow: 0 12px 34px rgba(15, 23, 42, 0.16);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 2px solid #111827;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        h1,
        p {
            margin: 0;
        }

        h1 {
            font-size: 16px;
            line-height: 1.1;
        }

        .subtitle,
        .meta {
            color: #4b5563;
            font-size: 10px;
        }

        .meta {
            text-align: right;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border: 1.4px solid #374151;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1.2px solid #4b5563;
            vertical-align: top;
        }

        th {
            background: #eef2f7;
            color: #111827;
            font-weight: 800;
        }

        .employee-head,
        .employee-cell {
            width: {{ $page['employeeWidth'] }};
            text-align: left;
        }

        .employee-head,
        .day-head {
            padding: 2px 2px;
        }

        .employee-cell {
            padding: 2px 3px;
            line-height: 1.12;
        }

        .day-head {
            text-align: center;
            line-height: 1.1;
        }

        .weekday {
            color: #374151;
            font-size: 0.85em;
            font-weight: 700;
        }

        .day-cell {
            height: {{ $page['cellHeight'] }};
            padding: 1px 2px;
            line-height: 1.08;
            overflow: hidden;
        }

        .total-head,
        .total-cell {
            width: 12mm;
            background: #e0e7ff;
            color: #1e1b4b;
            text-align: center;
            vertical-align: middle;
            font-weight: 800;
        }

        .employee-code {
            color: #000;
            font-weight: 800;
        }

        .profession,
        .detail {
            color: #374151;
            font-size: 0.9em;
            font-weight: 700;
        }

        .present {
            background: #dff7e9;
            color: #022c22;
        }

        .absent {
            background: #fee2e2;
            color: #450a0a;
            text-align: center;
            vertical-align: middle;
            font-weight: 800;
        }

        .leave {
            background: #fef3c7;
            color: #451a03;
        }

        .weekend {
            background: #e5e7eb;
        }

        .project,
        .detail {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .project {
            color: #000;
            font-weight: 800;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .page {
                width: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('attendance.timesheet', ['type' => $filters['type'], 'month' => $filters['month']]) }}">Back</a>
        <button type="button" onclick="window.print()">Print PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <div class="brand">
                <img src="{{ asset('al-mohafiz-logo.png') }}" alt="Al Mohafiz">
                <div>
                    <h1>Attendance Timesheet</h1>
                    <p class="subtitle">Monthly employee attendance with project and overtime details.</p>
                </div>
            </div>
            <div class="meta">
                <strong>{{ $typeLabel }}</strong><br>
                {{ $monthLabel }}<br>
                {{ $page['label'] }}
            </div>
        </header>

        <table>
            <thead>
                <tr>
                    <th class="employee-head">Employee</th>
                    @foreach ($dates as $date)
                        <th class="day-head {{ $date['isWeekend'] ? 'weekend' : '' }}">
                            {{ $date['day'] }}<br>
                            <span class="weekday">{{ $date['weekday'] }}</span>
                        </th>
                    @endforeach
                    <th class="total-head">Present<br>Days</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $employee)
                    <tr>
                        <td class="employee-cell">
                            <span class="employee-code">{{ $employee['code'] }} - {{ $employee['name'] }}</span><br>
                            <span class="profession">{{ $employee['profession'] }}</span>
                        </td>
                        @foreach ($employee['days'] as $index => $day)
                            @php
                                $date = $dates[$index];
                                $status = $day['status'];
                                $class = $status ?: ($date['isWeekend'] ? 'weekend' : '');
                                $hasDifferentOvertimeProject = $day['overtimeProjectName'] && $day['overtimeProjectName'] !== $day['projectName'];
                            @endphp
                            <td class="day-cell {{ $class }}">
                                @if ($status === 'present')
                                    <span class="project">{{ $day['projectName'] ?: 'Present' }}</span>
                                    @if ($day['overtimeHours'])
                                        <span class="detail">OT {{ $day['overtimeHours'] }}H{{ $hasDifferentOvertimeProject ? ' - '.$day['overtimeProjectName'] : '' }}</span>
                                    @endif
                                @elseif ($status === 'absent')
                                    Absent
                                @elseif ($status === 'leave')
                                    <span class="project">Leave</span>
                                    @if ($day['leaveReason'])
                                        <span class="detail">{{ $day['leaveReason'] }}</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                        <td class="total-cell">{{ $employee['presentDays'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
