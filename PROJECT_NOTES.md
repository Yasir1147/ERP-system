# Al Mohafiz ERP / Attendance System - Project Notes

This file documents the local setup, business rules, and development workflow for the project.

## Project Location

Local project folder:

```powershell
D:\projects\attendance-system
```

## Git Repository

Remote repository:

```text
https://github.com/Yasir1147/ERP-system.git
```

Default branch:

```text
main
```

Before making new changes, always check:

```powershell
git status --short
git log --oneline -5
```

Recommended workflow:

```powershell
git add .
git commit -m "Clear description of the change"
git push origin main
```

Do not commit `.env`, database dumps, or private passwords.

## Tech Stack

- Laravel 12
- PHP 8.2
- Inertia.js
- Vue 3
- Vite
- MySQL / MariaDB through XAMPP
- Tailwind-based UI components

## Local Run Steps

1. Start XAMPP.
2. Start Apache.
3. Start MySQL.
4. Open terminal in the project folder:

```powershell
cd D:\projects\attendance-system
```

5. Clear cached Laravel files if needed:

```powershell
php artisan optimize:clear
```

6. Start Laravel:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

7. In a second terminal, start Vite:

```powershell
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```

For production/build verification:

```powershell
npm run build
```

## Database

The app uses MySQL through XAMPP. Check `.env` locally for the database name and credentials. Do not push `.env` to GitHub.

Common Laravel database commands:

```powershell
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed
```

Only run `migrate:fresh --seed` when it is safe to delete all current local data.

## Database Backup

Create a backup folder outside the project, for example:

```text
D:\backups\attendance-system
```

Backup command example:

```powershell
D:\xampp\mysql\bin\mysqldump.exe -uroot attendance-system > D:\backups\attendance-system\attendance-system-YYYY-MM-DD.sql
```

Restore command example:

```powershell
D:\xampp\mysql\bin\mysql.exe -uroot attendance-system < D:\backups\attendance-system\attendance-system-YYYY-MM-DD.sql
```

Replace `attendance-system` with the actual database name from `.env` if different.

## Important URLs

Authentication:

```text
/login
/dashboard
```

The login page uses a two-column desktop layout with a rope-access/construction collage image background, light overlay, prominent white feature cards, brand/system overview panel, and focused login form. Mobile keeps a single-column login form.

/fines is the admin employee fine module. Allowed attendance users can submit fine tickets from /fines/create or the Mark Attendance page link; the Mark Attendance link passes the current employee type so Rope Access pages only list Rope Access employees and Contracting pages only list Contracting employees. Admin users review pending fines, waive them, or apply them to a selected payroll month. Applying a fine increases that employee's payroll deduction and appends a "Fine: reason - amount" remark on the payroll adjustment.

Attendance users can be restricted to All Employee Types, Rope Access only, or Contracting only from the Users page. This restriction is independent from backdate access and applies to Mark Attendance routes and fine ticket creation routes, so a Rope Access-only user cannot open Contracting attendance or Contracting fine ticket links.

When an already-authenticated attendance user hits a restricted employee-type route, the Access Denied page should offer a logout action. Do not send authenticated users directly to /login from that state, because /login is a guest route and will not switch accounts cleanly.

Fine ticket email notifications are controlled from Settings > Mail. SMTP settings are stored in the database, with the SMTP password encrypted. Admin users have a "Receive fine ticket emails" checkbox on the Users page; only checked admin users receive the new fine ticket email. If SMTP is disabled or fails, the fine ticket is still saved and the mail failure is logged.

When applying a pending fine to payroll, admin can reduce the deduction amount before applying it. The original fine amount remains on the fine ticket, while the reduced applied amount is used for payroll deduction and noted in payroll remarks.

The admin fine list uses server-side pagination with 5/10/15/25 per-page options, backend search, search-aware counts, and previous/next controls so page load does not grow with fine record count.

Fine list backend search must qualify `employee_fines` columns when joins are present. In particular, use `employee_fines.status` rather than plain `status`, otherwise MySQL can throw an ambiguous column error when searching fines.

Attendance marking pages:

```text
/mark-attendance
/mark-attendance/contracting
/mark-attendance/rope-access
```

Dashboard and admin modules:

```text
/dashboard
/attendance
/attendance/timesheet
/employees/rope_access
/employees/contracting
/projects/overview
/projects/rope_access
/projects/contracting
/employee-leaves
/payroll
/payroll/report
/users
```

## User Roles

Current role concept:

- `admin`: full backend/admin access.
- `attendance_user`: can access attendance marking pages only.

Normal attendance users can log in with username-only according to the current business requirement. Admin users still use password login.

Public self-registration is disabled. New users must be created by an admin from the Users page, and the login page must not show a sign-up link.

Admin can temporarily allow an attendance user to enter older missing attendance from the Users page. The access is controlled per user with:

- `attendance_backdate_enabled`
- `attendance_backdate_from`
- `attendance_backdate_to`

When enabled, the attendance user can only submit dates inside that range, and future dates are still blocked. When disabled, the user goes back to the default attendance date rule.

## Core Business Rules

### Employees

Employees are split into two categories:

- Rope Access Employee
- Contracting Employee

Employee records should stay in admin even if the employee leaves the job. Employees marked as left should not appear in the attendance marking selector.

### Projects

Projects are split into two categories:

- Rope Access Projects
- Contracting Projects

The Projects Overview page calculates project labor cost from attendance records and salary settings.

If an attendance record has a separate overtime project, project reports split the cost: basic salary/day count belongs to the main attendance project, while overtime hours and overtime cost belong to the overtime project.

### Attendance

Attendance supports these statuses:

- Present
- Absent
- Leave

Present requires employee, project, date, and optional overtime hours.

When overtime is applied, the attendance form also supports an optional overtime project. If the overtime project is left blank, the system uses the main selected project for overtime. If a different overtime project is selected, normal attendance/day cost remains on the main project and overtime hours/cost are assigned to the selected overtime project.

Absent requires employee and date.

Leave requires employee, date, and leave reason.

Normal attendance users can only select today or the previous two dates by default. Future dates are disabled.

Admins can grant a temporary backdate date range from the Users section for attendance users who forgot to mark attendance. This must be validated in both the frontend date picker and the backend controller.

Duplicate attendance should not be allowed for the same employee and same date.

Attendance records track who submitted the record.

The public attendance form supports selecting one or more employees before submitting. A bulk submission creates one attendance record per selected employee using the same selected status, date, project, and overtime values. Backend validation blocks duplicate attendance and employees who are on leave.

After a successful attendance submission, the public attendance form clears the selected employee, overtime fields, and leave reason, but keeps the selected status, date, and main project. This supports entering multiple employees on the same project with fewer repeated selections.

Public attendance custom dropdowns should close when the user clicks outside the open dropdown.

Monthly attendance timesheet is available at:

```text
/attendance/timesheet
```

Timesheet supports employee type and month filters, sticky employee rows, row highlighting on click, and a print/PDF view. The legacy CSV route still exists for internal use:

```text
/attendance/timesheet-export
```

Timesheet also supports a print/PDF view with A3 landscape and A4 landscape page-size options:

```text
/attendance/timesheet-print
```

### Leaves

There are two leave types in the UI:

- Daily leave submitted through the attendance page.
- Long leave created from the Leaves admin page.

Long leave is considered more than 3 days.

Admins can edit and delete both daily leave attendance records and long leave range records from the Leaves page. Daily leave remains a single-day attendance record, so its end date is not separately editable. Daily absent attendance records are also shown on the Leaves page as read-only records so admin can review leave/absent activity from one place.

Leave payroll decisions are admin controlled. A leave record is not converted into an attendance absent record. Instead, admin can apply part or all of a leave as payroll absent days by setting `Deduct Days`, `Payroll Month`, and an optional note on the Leaves page. Payroll then adds those approved leave deduction days to the existing Absent count for that selected month, and the payroll remarks include the leave reason. Admin can also waive a leave deduction.

Attendance users may submit future dates only when the selected status is Leave. Present and Absent remain limited to the configured attendance date range and cannot be marked for future dates. Leave submissions support a start/end date range; the system creates leave range records for admin review instead of creating daily attendance rows for every leave day.

Admins can correct existing daily attendance records from Attendance > Overview. The edit action updates the existing record instead of creating a duplicate, and supports status, employee/date, project, overtime, and leave reason changes. This is the correct workflow when an allowed user accidentally marks Absent instead of Present.

Employees on active leave should not be selectable for attendance marking.

Dashboard should show a notification when a long leave has completed and needs admin review/status update.

Dashboard monthly leave summary counts leave records/events, not leave days. One 20-day long leave counts as 1 leave record. If the same employee has a separate daily leave and a long leave in the same month, those count as separate leave records.

### Payroll

Payroll calculates monthly salary from attendance and salary settings.

Supported payroll concepts:

- Per-day salary
- Fixed 30 days rule
- Present-days rule
- Overtime hours
- Overtime salary
- Absent days
- Leave days applied as absent
- Automatic absent deduction
- Bonus / extra
- Previous balance
- Manual previous balance override
- Total balance
- Deduction
- Paid cash
- Final balance
- Remarks

Previous balance carries forward month-to-month. Admin can manually override previous balance when needed.

Payroll > Absence Deduction Rule controls absent salary deduction. The current recommended rule is a global on/off setting that applies to Fixed 30 Days employees only. When enabled, fixed-salary employees keep their fixed 30-day basic salary, but payroll subtracts `absent days * daily salary` as a separate Absent Deduction before manual deduction and paid cash. Present-days employees are already paid only for attended days, so absent deduction remains 0 for them.

Leaves approved for payroll deduction are included inside the existing Absent count instead of adding a separate payroll column. For example, if admin applies 2 days from a 3-day leave as absent, payroll shows those 2 days inside Absent and the Absent Deduction follows the active absence deduction rule. Remarks show the leave deduction reason.

Fixed 30 Days payroll caps payable/present-day payroll count at 30 days. In 31-day months, extra attendance records remain visible in attendance reports, but payroll present days and fixed-salary absent deduction are capped so fixed employees are not paid or deducted beyond the 30-day salary basis.

Payroll report supports:

- Filtering by employee type, employee, and month
- Showing present days, absent days, and absent deduction
- Editing payroll adjustments
- Saving one row
- Saving selected rows in bulk
- Applying one remark to selected employees
- Employee ledger modal
- Payslip PDF/print
- Payslip CSV/Excel export
- Merged selected payslips PDF/print
- Payroll report PDF/print

Payroll salary settings list shows employee codes with names and supports searching by code, name, profession, and employee type. The salary settings table can also be sorted ascending or descending by employee, daily salary, salary rule, hours per day, and overtime status.

Employee-facing admin/report lists should show employee code with the employee name when available. Search should include employee code on payroll report, attendance report, dashboard daily attendance, leave list, and fine list.

Core admin/reporting lists use clickable sortable headers with ascending/descending direction indicators. Sorting is available on employee lists, project lists, attendance detail, leaves, fines, payroll salary settings, payroll report, and projects overview. Fines sorting is handled through server-side query sorting so pagination remains accurate.

Leave list rows are converted to base collections before merging long leave, daily leave, and absent records. This avoids Laravel Eloquent collection merge errors when the rows are already mapped to arrays.

Payroll print pages use `public/al-mohafiz-logo.png` for both the document logo and browser tab favicon.

Payslip print pages use visible page borders, full grid borders on salary tables, stronger inner summary borders, and high-contrast text so printed salary details remain readable.

Payroll save actions handle expired CSRF/session tokens by showing a clear message and refreshing the page so a new token is loaded. To avoid repeated CSRF issues during local development, keep using `http://127.0.0.1:8000` instead of switching between `localhost` and `127.0.0.1`.

## Project Overview

The Projects Overview page should help answer:

- How many days since the project started?
- How many attendance entries exist for the project?
- How many unique employees worked on the project?
- How many overtime hours were used?
- What is the basic labor cost?
- What is the overtime cost?
- What is the total labor cost?

Project employee history is available from the project row action. The current desired modal is focused on Employee Summary only, not detailed attendance records.

The Projects Overview table uses compact column widths so the History action remains visible at normal browser zoom on common desktop screens.

Employee Summary should show:

- Employee
- Profession
- Entries
- Worked days
- Overtime hours
- Basic cost
- Overtime cost
- Total cost
- Submitted by

## UI Language Rule

All application UI text, validation messages, alerts, and exports should be in English only.

Do not add Urdu or Roman Urdu text in code, Vue pages, Blade views, controllers, validation messages, or exported PDF/CSV labels.

## Safety Checklist Before Major Changes

1. Run `git status --short`.
2. Commit or push a checkpoint if the current work is stable.
3. Backup the database before schema changes.
4. Run migrations carefully.
5. Run `php artisan optimize:clear` after route/controller/view changes.
6. Run `npm run build` after frontend changes.
7. Check important pages in the browser.
8. Do not delete XAMPP MySQL data folders without a backup.

## XAMPP MySQL Recovery Note

MySQL previously crashed in XAMPP. A non-destructive recovery was done by restoring XAMPP MySQL `data` from backup and copying user databases plus original InnoDB files from old data.

Do not delete these folders unless a full backup exists:

```text
D:\xampp\mysql\data-old
D:\xampp\mysql\data-broken-*
```

## Recommended Future Improvements

- Add admin activity log for payroll, attendance, leave, and project changes.
- Add database backup button or scheduled backup script.
- Add export PDF/Excel for Project Employee History.
- Add stricter role middleware and route permission checks.
- Add tests for payroll carry-forward balance logic.
- Add tests for duplicate attendance prevention.
- Add tests for date restrictions on attendance forms.
- Add audit columns like `created_by`, `updated_by`, and `deleted_by` where needed.

## Development Checklist

When continuing this project:

1. Work only in `D:\projects\attendance-system`.
2. Read `routes/web.php` first to understand available pages.
3. Read these controllers next:
   - `app/Http/Controllers/DashboardController.php`
   - `app/Http/Controllers/PublicAttendanceController.php`
   - `app/Http/Controllers/PayrollController.php`
   - `app/Http/Controllers/ProjectController.php`
4. Read these frontend pages next:
   - `resources/js/pages/Payroll/Report.vue`
   - `resources/js/pages/Projects/Overview.vue`
   - `resources/js/pages/Attendance/Timesheet.vue`
   - `resources/js/components/AppSidebar.vue`
5. Keep UI text English only.
6. Do not assume the database is disposable.
7. Ask before destructive commands.
