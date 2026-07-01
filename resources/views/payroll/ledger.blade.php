<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('al-mohafiz-logo.png') }}">
    <title>Ledger - {{ $employee->name }} - {{ $fromMonthLabel }} to {{ $toMonthLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }
        .toolbar {
            width: 297mm;
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
            width: 297mm;
            min-height: 210mm;
            margin: 16px auto;
            background: #fff;
            padding: 12mm;
            box-shadow: 0 12px 34px rgba(15, 23, 42, 0.16);
        }
        .header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            border-bottom: 3px solid #111827;
            padding-bottom: 12px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand img {
            width: 54px;
            height: 54px;
            object-fit: contain;
        }
        .brand h1 {
            margin: 0;
            font-size: 22px;
        }
        .brand p,
        .meta p {
            margin: 3px 0 0;
            color: #6b7280;
        }
        .meta {
            text-align: right;
        }
        .meta h2 {
            margin: 0;
            font-size: 21px;
            text-transform: uppercase;
        }
        .info {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .info-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
        }
        .info-card span {
            display: block;
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 4px;
        }
        .info-card strong {
            font-size: 14px;
        }
        .section {
            margin-top: 16px;
        }
        .section-title {
            margin: 0 0 8px;
            color: #374151;
            font-size: 13px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
        }
        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 7px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        td.amount,
        th.amount {
            text-align: right;
        }
        tr:last-child td {
            border-bottom: 0;
        }
        .total-row td {
            background: #f9fafb;
            font-weight: 700;
        }
        .balance {
            font-weight: 700;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .summary-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
        }
        .summary-card span {
            display: block;
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .summary-card strong {
            font-size: 16px;
        }
        .footer {
            margin-top: 18px;
            color: #9ca3af;
            font-size: 10px;
            text-align: center;
        }
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Download / Print PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <div class="brand">
                <img src="{{ asset('al-mohafiz-logo.png') }}" alt="Al Mohafiz">
                <div>
                    <h1>Al Mohafiz</h1>
                    <p>Building Contracting L.L.C.</p>
                    <p>Employee salary ledger</p>
                </div>
            </div>
            <div class="meta">
                <h2>Employee Ledger</h2>
                <p><strong>{{ $fromMonthLabel }} to {{ $toMonthLabel }}</strong></p>
                <p>Generated: {{ $generatedAt }}</p>
            </div>
        </header>

        <section class="info">
            <div class="info-card"><span>Employee</span><strong>{{ $employee->name }}</strong></div>
            <div class="info-card"><span>Profession</span><strong>{{ $employee->profession }}</strong></div>
            <div class="info-card"><span>Employee Type</span><strong>{{ $employeeTypeLabel }}</strong></div>
            <div class="info-card"><span>Period</span><strong>{{ $fromMonthLabel }} - {{ $toMonthLabel }}</strong></div>
        </section>

        <section class="section">
            <h3 class="section-title">Monthly Ledger</h3>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="amount">Days</th>
                        <th class="amount">Absent</th>
                        <th class="amount">Per Day</th>
                        <th class="amount">Salary</th>
                        <th class="amount">Absent Ded.</th>
                        <th class="amount">OT Hrs</th>
                        <th class="amount">OT Salary</th>
                        <th class="amount">New Total</th>
                        <th class="amount">Bonus</th>
                        <th class="amount">Pr. Balance</th>
                        <th class="amount">Total Balance</th>
                        <th class="amount">Deduction</th>
                        <th class="amount">Paid Cash</th>
                        <th class="amount">Balance</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><strong>{{ $row['monthLabel'] }}</strong></td>
                            <td class="amount">{{ $row['presentDays'] }}</td>
                            <td class="amount">{{ $row['absentDays'] }}</td>
                            <td class="amount">{{ number_format($row['dailySalary'], 2) }}</td>
                            <td class="amount">{{ number_format($row['basicSalary'], 2) }}</td>
                            <td class="amount">{{ number_format($row['absenceDeduction'], 2) }}</td>
                            <td class="amount">{{ $row['overtimeHours'] }}</td>
                            <td class="amount">{{ number_format($row['overtimeAmount'], 2) }}</td>
                            <td class="amount"><strong>{{ number_format($row['totalSalary'], 2) }}</strong></td>
                            <td class="amount">{{ number_format($row['bonusExtra'], 2) }}</td>
                            <td class="amount">{{ number_format($row['previousBalance'], 2) }}</td>
                            <td class="amount">{{ number_format($row['totalBalance'], 2) }}</td>
                            <td class="amount">{{ number_format($row['deduction'], 2) }}</td>
                            <td class="amount">{{ number_format($row['paidByCash'], 2) }}</td>
                            <td class="amount balance">{{ number_format($row['balance'], 2) }}</td>
                            <td>{{ $row['remarks'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="amount">{{ $totals['presentDays'] }}</td>
                        <td class="amount">{{ $totals['absentDays'] }}</td>
                        <td></td>
                        <td class="amount">{{ number_format($totals['basicSalary'], 2) }}</td>
                        <td class="amount">{{ number_format($totals['absenceDeduction'], 2) }}</td>
                        <td class="amount">{{ $totals['overtimeHours'] }}</td>
                        <td class="amount">{{ number_format($totals['overtimeAmount'], 2) }}</td>
                        <td class="amount">{{ number_format($totals['totalSalary'], 2) }}</td>
                        <td class="amount">{{ number_format($totals['bonusExtra'], 2) }}</td>
                        <td></td>
                        <td></td>
                        <td class="amount">{{ number_format($totals['deduction'], 2) }}</td>
                        <td class="amount">{{ number_format($totals['paidByCash'], 2) }}</td>
                        <td class="amount">{{ number_format($totals['endingBalance'], 2) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="summary">
            <div class="summary-card"><span>Present Days</span><strong>{{ $totals['presentDays'] }}</strong></div>
            <div class="summary-card"><span>Absent Days</span><strong>{{ $totals['absentDays'] }}</strong></div>
            <div class="summary-card"><span>OT Hours</span><strong>{{ $totals['overtimeHours'] }}</strong></div>
            <div class="summary-card"><span>Total Salary</span><strong>{{ number_format($totals['totalSalary'], 2) }}</strong></div>
            <div class="summary-card"><span>Absent Deduction</span><strong>{{ number_format($totals['absenceDeduction'], 2) }}</strong></div>
            <div class="summary-card"><span>Ending Balance</span><strong>{{ number_format($totals['endingBalance'], 2) }}</strong></div>
        </section>

        <footer class="footer">
            This is a system generated ledger for {{ $employee->name }}.
        </footer>
    </main>
</body>
</html>
