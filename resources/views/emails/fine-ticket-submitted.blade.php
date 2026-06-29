<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h2 style="margin-bottom: 8px;">New Employee Fine Ticket</h2>
    <p>A fine ticket was submitted and needs admin review.</p>

    <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Employee</td>
            <td style="border: 1px solid #d1d5db;">{{ trim(($fine->employee?->code ? $fine->employee->code.' - ' : '').($fine->employee?->name ?? 'Unknown Employee')) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Profession</td>
            <td style="border: 1px solid #d1d5db;">{{ $fine->employee?->profession ?? '-' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Fine Date</td>
            <td style="border: 1px solid #d1d5db;">{{ $fine->fine_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Reason</td>
            <td style="border: 1px solid #d1d5db;">{{ $fine->reason }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Amount</td>
            <td style="border: 1px solid #d1d5db;">{{ number_format((float) $fine->amount, 2) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Submitted By</td>
            <td style="border: 1px solid #d1d5db;">{{ $fine->creator?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Note</td>
            <td style="border: 1px solid #d1d5db;">{{ $fine->note ?: '-' }}</td>
        </tr>
    </table>

    <p style="margin-top: 18px;">
        <a href="{{ url('/fines') }}" style="color: #0f172a; font-weight: bold;">Open Fine Module</a>
    </p>
</body>
</html>
