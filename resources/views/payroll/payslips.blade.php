<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('al-mohafiz-logo.png') }}">
    <title>Merged Payslips - {{ $monthLabel }}</title>
    <style>
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
            font-size: 13px;
        }
        .toolbar {
            width: 210mm;
            margin: 18px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .toolbar p {
            margin: 0;
            color: #4b5563;
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
            margin: 18px auto;
            background: #fff;
            border: 1.5px solid #111827;
            padding: 18mm;
            box-shadow: 0 12px 34px rgba(15, 23, 42, 0.16);
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            border-bottom: 3px solid #111827;
            padding-bottom: 14px;
        }
        .brand {
            display: flex;
            gap: 14px;
            align-items: center;
        }
        .brand img {
            width: 62px;
            height: 62px;
            object-fit: contain;
        }
        .brand h1 {
            margin: 0;
            font-size: 24px;
            line-height: 1.1;
        }
        .brand p,
        .meta p {
            margin: 4px 0 0;
            color: #374151;
            font-weight: 600;
        }
        .meta {
            text-align: right;
        }
        .meta h2 {
            margin: 0;
            font-size: 22px;
            text-transform: uppercase;
        }
        .section {
            margin-top: 18px;
        }
        .section-title {
            margin: 0 0 8px;
            color: #111827;
            font-size: 13px;
            letter-spacing: 0.04em;
            font-weight: 800;
            text-transform: uppercase;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border: 1.4px solid #4b5563;
            border-radius: 8px;
            overflow: hidden;
        }
        .info-item {
            display: grid;
            grid-template-columns: 130px 1fr;
            border-bottom: 1.2px solid #4b5563;
            min-height: 42px;
        }
        .info-item:nth-child(odd) {
            border-right: 1.2px solid #4b5563;
        }
        .info-item:nth-last-child(-n+2) {
            border-bottom: 0;
        }
        .info-item span {
            background: #f3f4f6;
            border-right: 1.2px solid #4b5563;
            color: #374151;
            font-weight: 700;
            padding: 12px;
        }
        .info-item strong {
            color: #000;
            font-weight: 800;
            padding: 12px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1.4px solid #4b5563;
            border-radius: 8px;
            overflow: hidden;
        }
        th,
        td {
            border-right: 1.2px solid #4b5563;
            border-bottom: 1.2px solid #4b5563;
            padding: 11px 12px;
            text-align: left;
        }
        th:last-child,
        td:last-child {
            border-right: 0;
        }
        tbody tr:last-child td {
            border-bottom: 0;
        }
        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 800;
        }
        td {
            color: #000;
            font-weight: 700;
        }
        td.amount,
        th.amount {
            text-align: right;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 86mm;
            gap: 18px;
            align-items: start;
        }
        .note {
            min-height: 92px;
            border: 1.4px solid #4b5563;
            border-radius: 8px;
            padding: 12px;
            color: #111827;
            font-weight: 600;
        }
        .total-box {
            border: 2px solid #111827;
            border-radius: 8px;
            overflow: hidden;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1.2px solid #4b5563;
            color: #000;
            font-weight: 700;
            padding: 11px 12px;
        }
        .total-row:last-child {
            border-bottom: 0;
        }
        .total-row.final {
            background: #111827;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-top: 34px;
        }
        .signature {
            border-top: 1px solid #111827;
            padding-top: 8px;
            color: #6b7280;
            text-align: center;
        }
        .footer {
            margin-top: 22px;
            color: #9ca3af;
            font-size: 11px;
            text-align: center;
        }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            html,
            body {
                width: 210mm;
                margin: 0;
                background: #fff;
            }
            .toolbar { display: none; }
            .page {
                display: block;
                width: 100%;
                min-height: 0;
                break-after: page;
                page-break-after: always;
                margin: 0;
                border: 1.5px solid #111827;
                padding: 10mm;
                box-shadow: none;
            }
            .page:last-child {
                break-after: auto;
                page-break-after: auto;
            }
            .section { margin-top: 14px; }
            th,
            td { padding: 9px 10px; }
            .note { min-height: 78px; }
            .total-row { padding: 9px 10px; }
            .signatures { margin-top: 26px; }
            .footer { margin-top: 16px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <p>{{ $slips->count() }} payslip{{ $slips->count() === 1 ? '' : 's' }} selected for {{ $monthLabel }}</p>
        <button type="button" onclick="window.print()">Download / Print PDF</button>
    </div>

    @foreach ($slips as $slip)
        @php
            $row = $slip['row'];
        @endphp

        <main class="page">
            <header class="header">
                <div class="brand">
                    <img src="{{ asset('al-mohafiz-logo.png') }}" alt="Al Mohafiz">
                    <div>
                        <h1>Al Mohafiz</h1>
                        <p>Building Contracting L.L.C.</p>
                        <p>Employee salary payslip</p>
                    </div>
                </div>
                <div class="meta">
                    <h2>Payslip</h2>
                    <p><strong>{{ $monthLabel }}</strong></p>
                    <p>{{ $periodLabel }}</p>
                </div>
            </header>

            <section class="section">
                <h3 class="section-title">Employee Details</h3>
                <div class="info-grid">
                    <div class="info-item"><span>Employee</span><strong>{{ $row['employeeName'] }}</strong></div>
                    <div class="info-item"><span>Profession</span><strong>{{ $row['employeeProfession'] }}</strong></div>
                    <div class="info-item"><span>Category</span><strong>{{ $slip['employeeTypeLabel'] }}</strong></div>
                    <div class="info-item"><span>Generated</span><strong>{{ $generatedAt }}</strong></div>
                </div>
            </section>

            <section class="section">
                <h3 class="section-title">Attendance & Salary</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Days</th>
                            <th>Per Day</th>
                            <th>Basic Salary</th>
                            <th>OT Hours</th>
                            <th>OT Salary</th>
                            <th class="amount">New Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $row['presentDays'] }}</td>
                            <td>{{ number_format($row['dailySalary'], 2) }}</td>
                            <td>{{ number_format($row['basicSalary'], 2) }}</td>
                            <td>{{ $row['overtimeHours'] }}</td>
                            <td>{{ number_format($row['overtimeAmount'], 2) }}</td>
                            <td class="amount"><strong>{{ number_format($row['totalSalary'], 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="section summary-grid">
                <div>
                    <h3 class="section-title">Remarks</h3>
                    <div class="note">{{ $row['remarks'] ?: 'No remarks added.' }}</div>
                </div>
                <div>
                    <h3 class="section-title">Pay Summary</h3>
                    <div class="total-box">
                        <div class="total-row"><span>New Total</span><strong>{{ number_format($row['totalSalary'], 2) }}</strong></div>
                        <div class="total-row"><span>Bonus / Extra</span><strong>{{ number_format($row['bonusExtra'], 2) }}</strong></div>
                        <div class="total-row"><span>Previous Balance</span><strong>{{ number_format($row['previousBalance'], 2) }}</strong></div>
                        <div class="total-row"><span>Total Balance</span><strong>{{ number_format($row['totalBalance'], 2) }}</strong></div>
                        <div class="total-row"><span>Deduction</span><strong>{{ number_format($row['deduction'], 2) }}</strong></div>
                        <div class="total-row"><span>Paid Cash</span><strong>{{ number_format($row['paidByCash'], 2) }}</strong></div>
                        <div class="total-row final"><span>Balance</span><strong>{{ number_format($row['balance'], 2) }}</strong></div>
                    </div>
                </div>
            </section>

            <section class="signatures">
                <div class="signature">Employee Signature</div>
                <div class="signature">Authorized Signature</div>
            </section>

            <footer class="footer">
                This is a system generated payslip for {{ $row['employeeName'] }}.
            </footer>
        </main>
    @endforeach
</body>
</html>
