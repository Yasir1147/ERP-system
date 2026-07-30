<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h2 style="margin-bottom: 8px;">Employee Document Expiry Reminder</h2>
    <p>
        @if ($daysUntilExpiry < 0)
            This document expired {{ abs($daysUntilExpiry) }} day(s) ago. Daily reminders will continue until its notification is disabled or the document is renewed.
        @elseif ($daysUntilExpiry === 0)
            This document expires today.
        @else
            This document will expire in {{ $daysUntilExpiry }} day(s).
        @endif
    </p>

    <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Employee</td>
            <td style="border: 1px solid #d1d5db;">
                {{ trim(($document->employee?->code ? $document->employee->code.' - ' : '').($document->employee?->name ?? 'Unknown Employee')) }}
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Document</td>
            <td style="border: 1px solid #d1d5db;">{{ $document->category?->name ?? 'Document' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Document Number</td>
            <td style="border: 1px solid #d1d5db;">{{ $document->document_number ?: '-' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Issue Date</td>
            <td style="border: 1px solid #d1d5db;">{{ $document->issue_date?->format('d/m/Y') ?: '-' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db; font-weight: bold;">Expiry Date</td>
            <td style="border: 1px solid #d1d5db;">{{ $document->expiry_date->format('d/m/Y') }}</td>
        </tr>
    </table>

    <p style="margin-top: 18px;">
        <a href="{{ url('/employee-documents') }}" style="color: #0f172a; font-weight: bold;">Open Document Expiry Module</a>
    </p>
</body>
</html>
