<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Employee Project Salary Sheet</title>
<style>@page{size:A3 landscape;margin:10mm}body{font:10px Arial;color:#111}table{border-collapse:collapse;width:100%}th,td{border:1px solid #555;padding:5px;text-align:right}th{background:#ddd;text-align:center}.name{text-align:left}.project-start{border-left:2px solid #111}.project-end{border-right:2px solid #111}thead{display:table-header-group}tfoot{display:table-row-group;font-weight:bold;background:#ddd}tr{break-inside:avoid}tbody tr:nth-child(even){background:#eee}@media print{button{display:none}}</style></head>
<body><button onclick="window.print()">Print / Save PDF</button><h1>Employee Project Salary Sheet</h1><p>{{ $filters['from'] }} to {{ $filters['to'] }} | Currency: AED</p>
<p>Days = full/half labour days. OT hours are decimal hours. Current payroll rates apply. Bonus covers saved monthly bonuses for months touched by the range and is not allocated to projects. Total = Salary + OT Cost + Bonus. Missing payroll: {{ $missingPayroll }}.</p>
@php($metrics = ['labourDays', 'overtimeHours', 'basicCost', 'overtimeCost'])
<table><thead><tr>@foreach(['S.No','Name / Code','Salary (AED)','OT Cost (AED)','Per Day (AED)','Bonus (AED)'] as $heading)<th rowspan="2">{{ $heading }}</th>@endforeach
@foreach($projects as $project)<th colspan="4" class="project-start project-end">{{ $project['code'] ? $project['code'].' - ' : '' }}{{ $project['name'] }}</th>@endforeach
@foreach(['Total Days','Total OT Hours','Total (AED)'] as $heading)<th rowspan="2">{{ $heading }}</th>@endforeach</tr><tr>
@foreach($projects as $project)@foreach(['Days','OT Hours','Salary Cost (AED)','OT Cost (AED)'] as $heading)<th class="{{ $loop->first ? 'project-start' : '' }} {{ $loop->last ? 'project-end' : '' }}">{{ $heading }}</th>@endforeach @endforeach</tr></thead>
<tbody>@forelse($rows as $index => $row)<tr><td>{{ $index + 1 }}</td><td class="name">{{ $row['name'] }} {{ $row['code'] }}{{ $row['missingPayroll'] ? ' (Missing payroll)' : '' }}</td>
@foreach(['basicCost','overtimeCost','dailySalary','bonus'] as $metric)<td>{{ $row[$metric] === null ? 'Missing' : number_format($row[$metric], 2) }}</td>@endforeach
@foreach($row['projects'] as $cell)@foreach($metrics as $metric)<td class="{{ $loop->first ? 'project-start' : '' }} {{ $loop->last ? 'project-end' : '' }}">{{ number_format($cell[$metric], 2) }}</td>@endforeach @endforeach
@foreach(['labourDays','overtimeHours','totalCost'] as $metric)<td>{{ number_format($row[$metric], 2) }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ 9 + count($projects) * 4 }}">No employees selected.</td></tr>@endforelse</tbody>
<tfoot><tr><td></td><td class="name">Totals</td><td>{{ number_format($totals['basicCost'], 2) }}</td><td>{{ number_format($totals['overtimeCost'], 2) }}</td><td>—</td><td>{{ number_format($totals['bonus'], 2) }}</td>
@foreach($totals['projects'] as $cell)@foreach($metrics as $metric)<td class="{{ $loop->first ? 'project-start' : '' }} {{ $loop->last ? 'project-end' : '' }}">{{ number_format($cell[$metric], 2) }}</td>@endforeach @endforeach
@foreach(['labourDays','overtimeHours','totalCost'] as $metric)<td>{{ number_format($totals[$metric], 2) }}</td>@endforeach</tr></tfoot></table></body></html>
