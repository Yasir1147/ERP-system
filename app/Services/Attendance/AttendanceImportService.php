<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports historical attendance from the reviewed workbook.
 *
 * The workbook has two sheets: the attendance rows keyed on the name as the
 * chat wrote it, and a map from those names to employee codes filled in by a
 * person. Joining them here means a name can only ever resolve one way.
 *
 * Every run is checked before anything is written, and a row that would
 * duplicate an existing day is skipped and reported rather than replacing
 * what is already there. Existing attendance is the record of what was paid.
 */
class AttendanceImportService
{
    public const SHEET_ATTENDANCE = 'Attendance';
    public const SHEET_MAP = 'Employee Map';

    /**
     * Reads and validates the workbook without touching the database.
     *
     * @return array<string, mixed>
     */
    public function preview(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $attendanceSheet = $spreadsheet->getSheetByName(self::SHEET_ATTENDANCE);
        $mapSheet = $spreadsheet->getSheetByName(self::SHEET_MAP);

        if (! $attendanceSheet || ! $mapSheet) {
            return [
                'fatal' => 'The file must contain an "'.self::SHEET_ATTENDANCE.'" sheet and an "'.self::SHEET_MAP.'" sheet.',
                'rows' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $map = $this->readMap($mapSheet);
        $rows = $this->readAttendance($attendanceSheet);

        $employees = Employee::query()
            ->whereIn('code', array_values(array_filter($map)))
            ->get()
            ->keyBy('code');

        $projects = Project::query()->get();

        $rows = array_map(
            fn (array $row) => $this->resolve($row, $map, $employees, $projects),
            $rows,
        );

        $rows = $this->flagDuplicatesWithinFile($rows);
        $rows = $this->flagExistingAttendance($rows);

        return [
            'fatal' => null,
            'rows' => $rows,
            'summary' => $this->summarise($rows),
        ];
    }

    /**
     * Writes the importable rows in one transaction.
     *
     * @return array<string, mixed>
     */
    public function import(string $path, User $user): array
    {
        $preview = $this->preview($path);

        if ($preview['fatal']) {
            return $preview;
        }

        $importable = array_values(array_filter($preview['rows'], fn (array $row) => $row['action'] === 'create'));

        if ($importable === []) {
            return $preview + ['created' => 0];
        }

        DB::transaction(function () use ($importable, $user) {
            foreach ($importable as $row) {
                $isPresent = $row['status'] === AttendanceRecord::STATUS_PRESENT;

                AttendanceRecord::create([
                    'employee_id' => $row['employeeId'],
                    'project_id' => $isPresent ? $row['projectId'] : null,
                    'overtime_project_id' => $isPresent && $row['overtimeHours']
                        ? ($row['overtimeProjectId'] ?: $row['projectId'])
                        : null,
                    'submitted_by' => $user->id,
                    'status' => $row['status'],
                    'attendance_fraction' => $isPresent ? $row['fraction'] : 1,
                    'leave_reason' => $row['status'] === AttendanceRecord::STATUS_LEAVE
                        ? ($row['note'] ?: 'Imported from site records')
                        : null,
                    'attendance_date' => $row['date'],
                    'has_overtime' => (bool) ($isPresent && $row['overtimeHours']),
                    'overtime_hours' => $isPresent ? $row['overtimeHours'] : null,
                    'overtime_time' => null,
                ]);
            }
        });

        // Reported per project so a person can see what was actually written,
        // rather than a single number that looks the same whichever file was
        // uploaded.
        $byProject = [];

        foreach ($importable as $row) {
            $name = $row['projectName'] ?: 'No project';
            $byProject[$name] = ($byProject[$name] ?? 0) + 1;
        }

        arsort($byProject);

        return $preview + [
            'created' => count($importable),
            'createdByProject' => $byProject,
        ];
    }

    /**
     * @return array<string, string>  chat name (lowercased) => employee code
     */
    private function readMap(Worksheet $sheet): array
    {
        $map = [];

        foreach ($sheet->getRowIterator() as $row) {
            $index = $row->getRowIndex();

            $name = trim((string) $sheet->getCell('A'.$index)->getValue());
            $code = trim((string) $sheet->getCell('B'.$index)->getValue());

            if ($name === '' || mb_strtolower($name) === 'chat name') {
                continue;
            }

            $map[mb_strtolower($name)] = $code;
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readAttendance(Worksheet $sheet): array
    {
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $index = $row->getRowIndex();

            $date = $sheet->getCell('A'.$index)->getValue();
            $name = trim((string) $sheet->getCell('B'.$index)->getValue());

            if ($name === '' || mb_strtolower($name) === 'chat name') {
                continue;
            }

            $rows[] = [
                'line' => $index,
                'rawDate' => $date,
                'sourceName' => $name,
                'project' => trim((string) $sheet->getCell('C'.$index)->getValue()),
                'rawStatus' => trim((string) $sheet->getCell('D'.$index)->getValue()),
                'rawDay' => trim((string) $sheet->getCell('E'.$index)->getValue()),
                'overtimeHours' => trim((string) $sheet->getCell('F'.$index)->getValue()),
                'overtimeProject' => trim((string) $sheet->getCell('G'.$index)->getValue()),
                'note' => trim((string) $sheet->getCell('H'.$index)->getValue()),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $map
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     * @param  \Illuminate\Support\Collection<int, Project>  $projects
     * @return array<string, mixed>
     */
    private function resolve(array $row, array $map, $employees, $projects): array
    {
        $errors = [];

        $date = $this->readDate($row['rawDate']);

        if ($date === null) {
            $errors[] = 'Date could not be read.';
        }

        $code = $map[mb_strtolower($row['sourceName'])] ?? '';
        $employee = $code !== '' ? $employees->get($code) : null;

        if ($code === '') {
            $errors[] = 'No employee code mapped for "'.$row['sourceName'].'".';
        } elseif (! $employee) {
            $errors[] = 'Employee code '.$code.' does not exist.';
        }

        $project = $this->findProject($projects, $row['project']);

        $status = $this->readStatus($row['rawStatus']);

        if ($status === null) {
            $errors[] = 'Status must be Present, Absent or Leave.';
        }

        if ($status === AttendanceRecord::STATUS_PRESENT && ! $project) {
            $errors[] = 'Project "'.$row['project'].'" not found.';
        }

        $overtimeHours = $row['overtimeHours'] === '' ? null : (int) $row['overtimeHours'];

        if ($overtimeHours !== null && ($overtimeHours < 1 || $overtimeHours > 10)) {
            $errors[] = 'Overtime hours must be between 1 and 10.';
        }

        return [
            'line' => $row['line'],
            'date' => $date,
            'sourceName' => $row['sourceName'],
            'employeeId' => $employee?->id,
            'employeeCode' => $employee?->code ?? $code,
            'employeeName' => $employee?->name,
            'projectId' => $project?->id,
            'projectName' => $project?->name ?? $row['project'],
            'status' => $status,
            'fraction' => str_starts_with(mb_strtolower($row['rawDay']), 'h') ? 0.5 : 1.0,
            'overtimeHours' => $overtimeHours,
            'overtimeProjectId' => $this->findProject($projects, $row['overtimeProject'])?->id,
            'note' => $row['note'],
            'errors' => $errors,
            'action' => $errors ? 'error' : 'create',
        ];
    }

    /**
     * The same employee cannot be imported twice for one day, even if the
     * chat listed them under two spellings that map to one person.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function flagDuplicatesWithinFile(array $rows): array
    {
        $seen = [];

        foreach ($rows as $index => $row) {
            if ($row['action'] !== 'create') {
                continue;
            }

            $key = $row['employeeId'].'|'.$row['date'];

            if (isset($seen[$key])) {
                $rows[$index]['action'] = 'duplicate';
                $rows[$index]['errors'][] = 'Same employee and date already appears on line '.$seen[$key].' of this file.';

                continue;
            }

            $seen[$key] = $row['line'];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function flagExistingAttendance(array $rows): array
    {
        $candidates = array_values(array_filter($rows, fn (array $row) => $row['action'] === 'create'));

        if ($candidates === []) {
            return $rows;
        }

        $existing = AttendanceRecord::query()
            ->whereIn('employee_id', array_unique(array_column($candidates, 'employeeId')))
            ->whereDate('attendance_date', '>=', min(array_column($candidates, 'date')))
            ->whereDate('attendance_date', '<=', max(array_column($candidates, 'date')))
            ->get(['employee_id', 'attendance_date'])
            ->map(fn (AttendanceRecord $record) => $record->employee_id.'|'.$record->attendance_date->toDateString())
            ->flip();

        foreach ($rows as $index => $row) {
            if ($row['action'] !== 'create') {
                continue;
            }

            if ($existing->has($row['employeeId'].'|'.$row['date'])) {
                $rows[$index]['action'] = 'skip';
                $rows[$index]['errors'][] = 'Attendance already recorded for this employee on this date.';
            }
        }

        return $rows;
    }

    private function readDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Excel may hand back a serial number rather than a string.
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = Carbon::createFromFormat($format, trim((string) $value));

            if ($date && $date->format($format) === trim((string) $value)) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function readStatus(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'present', 'p' => AttendanceRecord::STATUS_PRESENT,
            'absent', 'a' => AttendanceRecord::STATUS_ABSENT,
            'leave', 'l' => AttendanceRecord::STATUS_LEAVE,
            default => null,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Project>  $projects
     */
    private function findProject($projects, string $name): ?Project
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return $projects->first(fn (Project $project) => mb_strtolower($project->name) === mb_strtolower($name))
            ?? $projects->first(fn (Project $project) => mb_strtolower((string) $project->project_code) === mb_strtolower($name));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarise(array $rows): array
    {
        $dates = array_values(array_filter(array_column($rows, 'date')));
        sort($dates);

        return [
            'total' => count($rows),
            'create' => count(array_filter($rows, fn ($r) => $r['action'] === 'create')),
            'skip' => count(array_filter($rows, fn ($r) => $r['action'] === 'skip')),
            'duplicate' => count(array_filter($rows, fn ($r) => $r['action'] === 'duplicate')),
            'error' => count(array_filter($rows, fn ($r) => $r['action'] === 'error')),
            'firstDate' => $dates[0] ?? null,
            'lastDate' => $dates ? end($dates) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0, 'create' => 0, 'skip' => 0,
            'duplicate' => 0, 'error' => 0, 'firstDate' => null, 'lastDate' => null,
        ];
    }
}
