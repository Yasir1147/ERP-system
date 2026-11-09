<?php

namespace App\Services\Attendance;

use App\Models\AttendanceCardTemplate;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Support\Overtime;
use Carbon\Carbon;

class AttendanceCardService
{
    public function build(array $filters): array
    {
        $month = Carbon::createFromFormat('!Y-m', $filters['month'], 'Asia/Dubai');
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $template = $filters['templateId'] ? AttendanceCardTemplate::findOrFail($filters['templateId'])->payload() : [
            'id' => null, 'name' => 'Standard Attendance Card', 'version' => 1, 'layout' => AttendanceCardTemplate::defaultLayout(),
            'logoUrl' => '/al-mohafiz-logo.png', 'signatureUrl' => null,
        ];
        $people = Employee::where('type', $filters['employeeType'])
            ->when($filters['allEmployees'], fn ($q) => $q->where('status', '!=', Employee::STATUS_LEFT), fn ($q) => $q->whereIn('id', $filters['employeeIds']))
            ->orderBy('code')->get();
        $records = AttendanceRecord::with(['project', 'overtimeProject'])->whereIn('employee_id', $people->modelKeys())
            ->whereDate('attendance_date', '>=', $from)->whereDate('attendance_date', '<=', $to)->get()->groupBy('employee_id');
        $leaves = EmployeeLeave::whereIn('employee_id', $people->modelKeys())->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get()->groupBy('employee_id');
        $holidays = Holiday::between($from, $to, $filters['employeeType']);
        $cards = $people->map(function ($employee) use ($month, $template, $records, $leaves, $holidays) {
            $daily = ($records->get($employee->id) ?? collect())->keyBy(fn ($record) => $record->attendance_date->toDateString());
            $ranges = $leaves->get($employee->id) ?? collect();
            $days = [];
            $present = $absent = $leave = $minutes = 0;
            for ($day = 1; $day <= $month->daysInMonth; $day++) {
                $date = $month->copy()->day($day);
                $record = $daily->get($date->toDateString());
                $onLeave = $ranges->contains(fn ($range) => $range->start_date->toDateString() <= $date->toDateString() && $range->end_date->toDateString() >= $date->toDateString());
                $status = $record?->status ?? ($onLeave ? 'leave' : null);
                $isPresent = $status === AttendanceRecord::STATUS_PRESENT;
                $fraction = $isPresent ? (float) ($record->attendance_fraction ?? 1) : 0;
                $otMinutes = $isPresent ? (int) round(Overtime::hours($record->overtime_hours ?? 0) * 60) : 0;
                $present += $fraction;
                $absent += $status === 'absent' ? 1 : 0;
                $leave += $status === 'leave' ? 1 : 0;
                $minutes += $otMinutes;
                $holiday = $holidays->get($date->toDateString());
                $rest = ! $status && (int) $template['layout']['rest_day'] === $date->dayOfWeek;
                $days[] = ['day' => $day, 'date' => $date->toDateString(),
                    'mark' => $isPresent ? ($fraction == 0.5 ? '½' : 'P') : match ($status) {
                        'absent' => 'A', 'leave' => 'L', default => $holiday ? 'H' : ''
                    },
                    'dayValue' => $fraction, 'leave' => $status === 'leave' ? 1 : 0,
                    'site' => $isPresent ? ($record->project?->project_code ?: $record->project?->name ?? '') : '',
                    'projectName' => $isPresent ? $record->project?->name : null,
                    'otSite' => $otMinutes > 0 ? ($record->overtimeProject?->project_code ?: $record->overtimeProject?->name ?? $record->project?->project_code ?? $record->project?->name ?? '') : '',
                    'otProjectName' => $otMinutes > 0 ? ($record->overtimeProject?->name ?? $record->project?->name) : null,
                    'overtimeMinutes' => $otMinutes, 'overtime' => $otMinutes ? intdiv($otMinutes, 60).':'.sprintf('%02d', $otMinutes % 60) : '',
                    'restLabel' => $rest && ! $holiday ? $date->format('l') : null,
                    'note' => $record?->leave_reason ?? ($onLeave ? $ranges->first(fn ($range) => $range->start_date->lte($date) && $range->end_date->gte($date))?->reason : $holiday?->name),
                ];
            }

            return ['employeeId' => $employee->id, 'name' => $employee->name, 'code' => $employee->code, 'profession' => $employee->profession,
                'month' => $month->format('M'), 'monthLabel' => $month->format('F Y'), 'year' => $month->year,
                'days' => $days, 'presentDays' => $present, 'absentDays' => $absent, 'leaveDays' => $leave,
                'overtimeMinutes' => $minutes, 'overtimeLabel' => Overtime::label($minutes / 60)];
        })->values();

        return ['filters' => $filters, 'template' => $template, 'cards' => $cards];
    }
}
