<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a workbook in the shape the importer expects.
 *
 * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4?: string}>  $rows  date, name, project, status, day
 * @param  array<string, string>  $map  chat name => employee code
 */
function importWorkbook(array $rows, array $map): string
{
    $spreadsheet = new Spreadsheet();

    $attendance = $spreadsheet->getActiveSheet();
    $attendance->setTitle('Attendance');
    $attendance->fromArray(
        ['Date', 'Chat Name', 'Project', 'Status', 'Day', 'Overtime Hours', 'Overtime Project', 'Note'],
        null,
        'A1',
    );

    $line = 2;

    foreach ($rows as $row) {
        $attendance->fromArray([
            $row[0], $row[1], $row[2], $row[3], $row[4] ?? 'Full', $row[5] ?? '', '', $row[6] ?? '',
        ], null, 'A'.$line);

        $line++;
    }

    $mapSheet = $spreadsheet->createSheet();
    $mapSheet->setTitle('Employee Map');
    $mapSheet->fromArray(['Chat Name', 'Employee Code'], null, 'A3');

    $line = 4;

    foreach ($map as $name => $code) {
        $mapSheet->fromArray([$name, $code], null, 'A'.$line);
        $line++;
    }

    $path = storage_path('app/test-import-'.uniqid().'.xlsx');
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function importAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function importEmployee(string $code, string $name): Employee
{
    return Employee::create([
        'code' => $code,
        'name' => $name,
        'profession' => 'Level 1',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
}

function importProject(string $name = 'Sobha Opulence'): Project
{
    return Project::create(['name' => $name, 'status' => 'ongoing', 'type' => 'contracting']);
}

it('previews without writing anything', function () {
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present']],
        ['Asghar' => '101'],
    );

    $preview = app(AttendanceImportService::class)->preview($path);

    expect($preview['summary']['create'])->toBe(1);
    expect($preview['summary']['error'])->toBe(0);
    expect(AttendanceRecord::count())->toBe(0);
});

it('imports the rows the preview accepted', function () {
    $admin = importAdmin();
    $employee = importEmployee('101', 'Asghar Ali');
    $project = importProject();

    $path = importWorkbook(
        [
            ['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present'],
            ['2026-05-14', 'Asghar', 'Sobha Opulence', 'Absent'],
        ],
        ['Asghar' => '101'],
    );

    $result = app(AttendanceImportService::class)->import($path, $admin);

    expect($result['created'])->toBe(2);
    expect(AttendanceRecord::count())->toBe(2);

    $present = AttendanceRecord::where('status', 'present')->first();
    expect($present->employee_id)->toBe($employee->id);
    expect($present->project_id)->toBe($project->id);
    expect($present->submitted_by)->toBe($admin->id);

    // An absent day carries no project, matching how the app records it.
    expect(AttendanceRecord::where('status', 'absent')->first()->project_id)->toBeNull();
});

it('maps two chat spellings of one person to the same employee', function () {
    $admin = importAdmin();
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [
            ['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present'],
            ['2026-05-14', 'Asghar Ali', 'Sobha Opulence', 'Present'],
        ],
        ['Asghar' => '101', 'Asghar Ali' => '101'],
    );

    expect(app(AttendanceImportService::class)->import($path, $admin)['created'])->toBe(2);
});

it('skips a day that is already recorded instead of replacing it', function () {
    $admin = importAdmin();
    $employee = importEmployee('101', 'Asghar Ali');
    $project = importProject();

    AttendanceRecord::create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'submitted_by' => $admin->id,
        'status' => 'present',
        'attendance_date' => '2026-05-13',
        'attendance_fraction' => 1,
        'has_overtime' => false,
    ]);

    $path = importWorkbook(
        [
            ['2026-05-13', 'Asghar', 'Sobha Opulence', 'Absent'],
            ['2026-05-14', 'Asghar', 'Sobha Opulence', 'Present'],
        ],
        ['Asghar' => '101'],
    );

    $result = app(AttendanceImportService::class)->import($path, $admin);

    expect($result['created'])->toBe(1);
    expect($result['summary']['skip'])->toBe(1);

    // The existing day is untouched, still Present, not the imported Absent.
    expect(AttendanceRecord::whereDate('attendance_date', '2026-05-13')->first()->status)->toBe('present');
    expect(AttendanceRecord::count())->toBe(2);
});

it('rejects a name with no employee code mapped', function () {
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Mystery Person', 'Sobha Opulence', 'Present']],
        ['Asghar' => '101'],
    );

    $preview = app(AttendanceImportService::class)->preview($path);

    expect($preview['summary']['error'])->toBe(1);
    expect($preview['rows'][0]['errors'][0])->toContain('No employee code mapped');
});

it('rejects a code that does not exist', function () {
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present']],
        ['Asghar' => '999'],
    );

    $preview = app(AttendanceImportService::class)->preview($path);

    expect($preview['summary']['error'])->toBe(1);
    expect($preview['rows'][0]['errors'][0])->toContain('does not exist');
});

it('rejects a present row whose project is not found', function () {
    importEmployee('101', 'Asghar Ali');

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Nowhere Tower', 'Present']],
        ['Asghar' => '101'],
    );

    $preview = app(AttendanceImportService::class)->preview($path);

    expect($preview['summary']['error'])->toBe(1);
    expect($preview['rows'][0]['errors'][0])->toContain('not found');
});

it('flags the same employee and date appearing twice in one file', function () {
    $admin = importAdmin();
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [
            ['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present'],
            ['2026-05-13', 'Asghar Ali', 'Sobha Opulence', 'Present'],
        ],
        ['Asghar' => '101', 'Asghar Ali' => '101'],
    );

    $result = app(AttendanceImportService::class)->import($path, $admin);

    expect($result['created'])->toBe(1);
    expect($result['summary']['duplicate'])->toBe(1);
    expect(AttendanceRecord::count())->toBe(1);
});

it('reads a half day and overtime', function () {
    $admin = importAdmin();
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present', 'Half', '3']],
        ['Asghar' => '101'],
    );

    app(AttendanceImportService::class)->import($path, $admin);

    $record = AttendanceRecord::first();
    expect((float) $record->attendance_fraction)->toBe(0.5);
    expect($record->has_overtime)->toBeTrue();
    expect($record->overtime_hours)->toBe(3);
});

it('stores a leave reason from the note column', function () {
    $admin = importAdmin();
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Sobha Opulence', 'Leave', 'Full', '', 'sick']],
        ['Asghar' => '101'],
    );

    app(AttendanceImportService::class)->import($path, $admin);

    expect(AttendanceRecord::first()->leave_reason)->toBe('sick');
});

it('rejects a workbook without the expected sheets', function () {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->setTitle('Something Else');
    $path = storage_path('app/test-bad-'.uniqid().'.xlsx');
    (new Xlsx($spreadsheet))->save($path);

    $preview = app(AttendanceImportService::class)->preview($path);

    expect($preview['fatal'])->toContain('Attendance');
});

it('lets an admin open the import page and blocks everyone else', function () {
    $this->actingAs(importAdmin())->get('/attendance/import')->assertOk();

    $attendanceUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);

    $this->actingAs($attendanceUser)->get('/attendance/import')->assertForbidden();
});

it('previews a workbook uploaded through the screen', function () {
    $admin = importAdmin();
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present']],
        ['Asghar' => '101'],
    );

    $upload = new \Illuminate\Http\UploadedFile($path, 'sobha.xlsx', null, null, true);

    $response = $this->actingAs($admin)
        ->post('/attendance/import/preview', ['file' => $upload]);

    $response->assertRedirect();
    $response->assertSessionHas('import_token');

    $preview = session('import_preview');
    expect($preview['fatal'])->toBeNull();
    expect($preview['summary']['create'])->toBe(1);
    expect(AttendanceRecord::count())->toBe(0);
});

it('imports the uploaded workbook after confirmation', function () {
    $admin = importAdmin();
    importEmployee('101', 'Asghar Ali');
    importProject();

    $path = importWorkbook(
        [['2026-05-13', 'Asghar', 'Sobha Opulence', 'Present']],
        ['Asghar' => '101'],
    );

    $upload = new \Illuminate\Http\UploadedFile($path, 'sobha.xlsx', null, null, true);

    $this->actingAs($admin)->post('/attendance/import/preview', ['file' => $upload]);

    $token = session('import_token');
    expect($token)->not->toBeNull();

    $this->actingAs($admin)
        ->post('/attendance/import', ['token' => $token])
        ->assertRedirect();

    expect(AttendanceRecord::count())->toBe(1);
});
