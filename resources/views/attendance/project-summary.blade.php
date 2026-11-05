<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Project Attendance &amp; Labour Cost Summary</title>
<style>
@page { size: A4 landscape; margin: 10mm; } body { font: 11px Arial, sans-serif; color: #172b40; margin: 20px; } h1 { font-size: 18px; } h2 { font-size: 15px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: right; } th:first-child, td:first-child { text-align: left; } thead { display: table-header-group; background: #1b4b73; color: white; } tr { break-inside: avoid; } .total { font-weight: bold; background: #edf0f3; } .warning { color: #9b2c2c; } .note { font-size: 10px; line-height: 1.5; } button { padding: 8px 16px; } @media print { body { margin: 0; } .controls { display: none; } * { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
</style></head><body>
<div class="controls"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>Al Mohafiz Building Contracting LLC</h1><h2>Project Attendance &amp; Labour Cost Summary</h2>
<p>{{ $filters['from'] }} to {{ $filters['to'] }} · Internal salary information · Currency: AED</p>
@if($totals['missingPayroll'])<p class="warning">Incomplete cost: {{ $totals['missingPayroll'] }} person(s) have no payroll settings and are costed at zero.</p>@endif
<table><thead><tr><th>Project</th><th>Worked Days</th><th>Persons</th><th>Labour Days</th><th>Total Overtime</th><th>Salary Cost (AED)</th><th>OT Cost (AED)</th><th>Total Cost (AED)</th></tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ $row['code'] ? $row['code'].' - ' : '' }}{{ $row['name'] }}@if($row['missingPayroll'])<div class="warning">Incomplete: {{ $row['missingPayroll'] }} missing payroll</div>@endif</td><td>{{ $row['workedDays'] }}</td><td>{{ $row['persons'] }}</td><td>{{ $row['labourDays'] }}</td><td style="white-space: nowrap">{{ \App\Support\Overtime::label($row['overtimeHours']) }}</td><td>{{ number_format($row['basicCost'], 2) }}</td><td>{{ number_format($row['overtimeCost'], 2) }}</td><td>{{ number_format($row['totalCost'], 2) }}</td></tr>
@empty<tr><td colspan="8">No projects selected.</td></tr>@endforelse
<tr class="total"><td>Totals (persons unique)</td><td>{{ $totals['workedDays'] }}</td><td>{{ $totals['persons'] }}</td><td>{{ $totals['labourDays'] }}</td><td style="white-space: nowrap">{{ \App\Support\Overtime::label($totals['overtimeHours']) }}</td><td>{{ number_format($totals['basicCost'], 2) }}</td><td>{{ number_format($totals['overtimeCost'], 2) }}</td><td>{{ number_format($totals['totalCost'], 2) }}</td></tr>
</tbody></table>
<p class="note">Worked Days counts distinct dates with work or overtime on each project; the total adds project dates. Persons counts each worker once per project and once across all projects in the total. Labour Days adds full/half days on the main project. Costs use current payroll rates: daily salary (monthly salary / 30 for Fixed 30 Days) plus overtime at daily salary / standard hours. Disabled overtime pay costs zero. Separate overtime projects receive their own hours and overtime cost. Overhead, purchases and expenses are excluded.</p>
</body></html>
