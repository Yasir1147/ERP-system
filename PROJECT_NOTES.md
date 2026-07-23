# Al Mohafiz ERP / Attendance System

Project notes for setup, deployment, business rules, and module behavior.

## Project

Local path:

```powershell
D:\projects\attendance-system
```

Repository:

```text
https://github.com/Yasir1147/ERP-system.git
```

Default branch:

```text
main
```

Tech stack:

- Laravel 12
- PHP 8.2
- MySQL / MariaDB
- Inertia.js
- Vue 3
- Vite
- Tailwind-based UI components

## Local Setup

Start XAMPP Apache and MySQL, then run:

```powershell
cd D:\projects\attendance-system
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```powershell
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```

Use `127.0.0.1` consistently during local development. Switching between `localhost` and `127.0.0.1` can create session or CSRF confusion.

Production build check:

```powershell
npm run build
```

## Database

The local app uses MySQL through XAMPP. Database credentials are stored in `.env`; do not commit `.env`.

Common commands:

```powershell
php artisan migrate
php artisan db:seed
php artisan optimize:clear
```

Only run this when deleting all current local data is acceptable:

```powershell
php artisan migrate:fresh --seed
```

Backup example:

```powershell
D:\xampp\mysql\bin\mysqldump.exe -uroot attendance-system > D:\backups\attendance-system\attendance-system-YYYY-MM-DD.sql
```

Restore example:

```powershell
D:\xampp\mysql\bin\mysql.exe -uroot attendance-system < D:\backups\attendance-system\attendance-system-YYYY-MM-DD.sql
```

Replace `attendance-system` if the actual database name is different.

## Git Workflow

Before starting changes:

```powershell
git status --short
git log --oneline -5
```

Typical commit flow:

```powershell
git add .
git commit -m "Clear description of the change"
git push origin main
```

Do not commit:

- `.env`
- database dumps
- private passwords
- temporary zip files
- local backups

## Server Deployment Notes

Live domain currently uses a Laravel app folder and a public web folder. The public web folder must point to the Laravel app through `index.php`.

After pulling/uploading code on server:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan storage:link
```

If frontend assets changed, build locally and upload/copy `public/build` to the server public folder.

Server `.env` must include:

```text
APP_TIMEZONE=Asia/Dubai
```

Clear config cache after changing `.env`.

## Important URLs

Authentication:

```text
/login
/dashboard
```

Attendance:

```text
/mark-attendance
/mark-attendance/contracting
/mark-attendance/rope-access
/attendance
/attendance/timesheet
/attendance/timesheet-print
/contracting-duty-plans
```

## Contracting Duty Planning

Contracting duty plans are operational schedules and remain separate from attendance until final review.

- Allowed Contracting users can prepare a dated duty plan and assign employees to project-based Duty Plan accordions.
- New assignments default to Present. Field users only change exceptions to Absent, Leave, or Removed and add overtime when required.
- A newer dated duty may be prepared while an older duty is pending, but attendance must be submitted in date order.
- Removed employees create no attendance record. Absent and Leave create their matching attendance status.
- Present employees may have overtime hours and an optional different overtime project. The main project is used when no overtime project is selected.
- Submit Attendance uses one database transaction and stops on duplicate attendance rather than saving a partial result.
- Submitted plans are locked. Admin corrections continue through the Attendance Report.

Admin modules:

```text
/employees/rope_access
/employees/contracting
/employee-leaves
/fines
/expenses
/payroll
/payroll/report
/projects/overview
/projects/rope_access
/projects/contracting
/office-staff
/office-attendance/report
/users
```

## UI Rules

All application UI text, validation messages, alerts, and exported labels must be English only.

Do not add Urdu or Roman Urdu text in code, Vue pages, Blade views, controllers, validation messages, PDFs, or CSV exports.

The default appearance is Light. Users may switch to Dark or System from Settings > Appearance.

The admin sidebar uses non-clickable visual section headings to group related modules: Overview, Attendance & Workforce, Operations, Finance, Office Management, and Administration. Existing expandable module menus and route permissions remain unchanged.

Browser favicons must use Al Mohafiz assets only. Use `public/favicon-32x32.png` or `public/favicon.ico` with cache-busting where needed.

The login page uses a two-column desktop layout with a rope-access/construction collage background and a focused login form. Mobile keeps a single-column login form.

Public self-registration is disabled. The login page must not show a sign-up link.

## User Roles

Current roles:

- `admin`: full backend/admin access.
- `attendance_user`: can access attendance marking and allowed employee-type routes.
- `office_staff`: can access office attendance marking only.

Attendance users can log in with username only. Admin users use password login.

Attendance users can be restricted to:

- All Employee Types
- Rope Access only
- Contracting only

This restriction applies to Mark Attendance and fine ticket creation links. A Rope Access-only user must not open Contracting attendance or Contracting fine ticket links.

When an authenticated attendance user opens a restricted employee-type route, show the Access Denied page with a logout action. Do not redirect them directly to `/login`, because `/login` is a guest route and will not switch accounts cleanly.

Admin can temporarily allow an attendance user to enter older attendance from the Users page using:

- `attendance_backdate_enabled`
- `attendance_backdate_from`
- `attendance_backdate_to`

Future dates remain blocked for Present and Absent.

## Employees

Employee categories:

- Rope Access Employee
- Contracting Employee

Employee records should remain in admin history even after an employee leaves. Employees marked as left should not appear in public attendance selectors.

Employee-facing admin/report lists should show employee code with employee name where available. Search should include employee code.

## Projects

Project categories:

- Rope Access Projects
- Contracting Projects

Projects Overview calculates labor cost from attendance records and payroll settings.

If an attendance record has a separate overtime project:

- basic salary/day count belongs to the main attendance project
- overtime hours and overtime cost belong to the overtime project

Projects Overview should show:

- project days
- attendance entries
- unique labor count
- overtime hours
- basic labor cost
- overtime cost
- total labor cost

Project employee history is available from each project row. The current desired modal focuses on Employee Summary, not detailed attendance rows.

## Attendance

Statuses:

- Present
- Absent
- Leave

Present requires employee, date, project, and optional overtime.

Absent requires employee and date.

Leave requires employee, date, and leave reason.

Normal attendance users can select today or the previous two dates by default. Future dates are disabled except when status is Leave.

Leave submissions can use a start/end date range and are stored for admin review instead of creating daily attendance rows for every leave day.

Duplicate attendance is blocked for the same employee and same date.

Attendance records track who submitted the record.

The public attendance form supports one or more selected employees. Bulk submit creates one attendance record per selected employee using the same status, date, project, and overtime values.

After a successful attendance submit:

- selected employee is cleared
- overtime fields are cleared
- leave reason is cleared
- selected status, date, and main project stay selected

This makes repeated entries for the same project faster.

Public attendance custom dropdowns close on outside click. When the employee dropdown closes, the search text is cleared but selected employees remain selected.

Admins can correct existing daily attendance records from Attendance > Overview. Edit updates the existing record and supports status, employee/date, project, overtime, and leave reason changes. This is the correct workflow if a user marks Absent instead of Present.

Admins can delete real daily attendance records from Attendance > Overview. Delete removes the selected attendance row and its embedded attendance details, including status, project, overtime, and leave reason. Synthetic leave-range rows are not deleted from this report.

The public Mark Attendance page shows a read-only "Today's Submitted Attendance" panel. It lists only records submitted today by the logged-in attendance user for the current employee type, so the user can review the day before leaving the page.

The same-day submitted attendance review list is capped with an internal scroll so the Mark Attendance page does not become too long when many employees are submitted together.

Employees on active leave should not be selectable for attendance marking.

## Timesheet

Monthly attendance timesheet:

```text
/attendance/timesheet
```

Timesheets combine daily attendance records with overlapping employee leave ranges. Every date covered by a leave range displays as yellow Leave with its reason when no daily attendance record exists. A real daily Present, Absent, or Leave record takes precedence over the synthetic leave-range cell.

Each employee row ends with a Present Days total that counts only daily records with Present status. The same total is included in the browser timesheet, CSV export, and A3/A4 print/PDF views.

Features:

- employee type filter
- month filter
- sticky employee column
- row highlight on click
- search by code/name/profession
- print/PDF view
- A3 landscape and A4 landscape options

Print route:

```text
/attendance/timesheet-print
```

Legacy CSV route remains for internal use:

```text
/attendance/timesheet-export
```

## Leaves

Leave UI contains:

- Daily leave submitted from attendance page
- Long leave created from Leaves admin page
- Daily absent records shown for review

Long leave means more than 3 days.

Admin can edit and delete daily leave records and long leave range records from the Leaves page. Daily leave remains a single-day attendance record.

Leave payroll decisions are admin controlled. A leave is not converted into an attendance absent record. Admin can apply part or all of a leave as payroll absent days by setting:

- Deduct Days
- Payroll Month
- optional note

Payroll adds approved leave deduction days into the existing Absent count for that month. Payroll remarks include the leave reason. Admin can also waive a leave deduction.

Dashboard monthly leave summary counts leave records/events, not leave days. One 20-day long leave counts as 1 leave record. Separate daily leave and long leave records count separately.

Dashboard should show a notification when a long leave has completed and needs admin review/status update.

## Payroll

Payroll calculates salary from attendance, leave deduction decisions, fines, and payroll settings.

Supported concepts:

- per-day salary
- exact monthly salary for Fixed 30 Days employees
- Fixed 30 Days rule
- Present Days rule
- overtime hours
- overtime salary
- absent days
- leave days applied as absent
- automatic absent deduction
- bonus / extra
- previous balance
- manual previous balance override
- total balance
- deduction
- paid cash
- final balance
- remarks

Fixed 30 Days employees use `Monthly Salary` as exact base salary. Daily and hourly basis are derived from:

```text
monthly salary / 30
```

This avoids rounded daily salary issues such as `56.65 * 30 = 1699.50` when the intended salary is `1700.00`.

Present Days employees use per-day salary only.

Payroll > Absence Deduction Rule applies to Fixed 30 Days employees only. When enabled, payroll subtracts:

```text
absent days * fixed daily basis
```

Present Days employees already receive pay only for attended days, so absent deduction remains 0 for them.

Fixed 30 Days payroll caps payable/present-day payroll count at 30 days. In 31-day months, extra attendance remains visible in attendance reports but payroll does not pay or deduct beyond the 30-day salary basis.

Previous balance carries forward month-to-month. Admin can manually override previous balance when needed.

Payroll report supports:

- employee type, employee, and month filters
- present days, absent days, and absent deduction
- row save and selected row bulk save
- one remark applied to selected employees
- employee ledger modal
- payslip PDF/print
- merged selected payslips PDF/print
- payroll report PDF/print

Payroll salary settings list supports:

- employee code/name display
- search by code, name, profession, and type
- sorting by employee, daily salary, monthly salary, salary rule, hours/day, and overtime

Payslip print pages must keep:

- visible page border
- full grid borders
- strong inner summary borders
- high-contrast text

## Fines

Admin fine module:

```text
/fines
```

Attendance users can submit fine tickets from:

```text
/fines/create
```

The Mark Attendance page link passes the current employee type so Rope Access pages list Rope Access employees and Contracting pages list Contracting employees.

Fine ticket create pages include a Back to Mark Attendance link that returns to the current employee-type attendance page.

Admin can:

- review pending fines
- waive fines
- apply fines to a payroll month
- reduce deduction amount before applying

Applying a fine increases payroll deduction and appends a payroll remark in this format:

```text
Fine: reason - amount
```

The original fine amount remains on the fine ticket. The reduced applied amount is used for payroll deduction.

Fine ticket email notifications are controlled from Settings > Mail. SMTP settings are stored in the database and SMTP password is encrypted.

Admin users have a "Receive fine ticket emails" checkbox on the Users page. Only checked admin users receive fine ticket email notifications.

If SMTP is disabled or fails, the fine ticket is still saved and mail failure is logged.

Fine list uses server-side pagination, backend search, search-aware counts, and previous/next controls.

Fine search must qualify joined columns. Use `employee_fines.status`, not plain `status`, when joins exist.

## Expenses

Admin expense module:

```text
/expenses
```

Rope Access attendance users can submit expense bills from:

```text
/expenses/create?type=rope_access
```

The Rope Access Mark Attendance page also has a Create Expense Bill link.

Expense bill create pages include a Back to Mark Attendance link that returns to the current employee-type attendance page.

Expense form stores:

- expense date
- purpose
- amount
- optional note
- receipt image

The file input uses mobile camera capture hints, so phones can take a receipt photo directly from the upload control.

Selected receipt photos are compressed in the browser before upload. The server allows receipt images up to 10 MB.

Client-side OCR reads selected receipt images and suggests purpose and total amount. Users must verify/edit the fields before submitting because receipt quality varies.

Receipt images are stored on the Laravel public disk under:

```text
expense-receipts
```

Server must have:

```bash
php artisan storage:link
```

Admin can:

- filter by date range, status, and search
- see totals
- open receipt images
- approve expense bills
- reject expense bills
- delete expense bills

Deleting an expense also removes the stored receipt image.

## Office Staff Attendance

Office staff are managed separately from regular attendance users.

Admin creates staff from:

```text
Office Staff > Staff List
```

The system creates a linked `office_staff` user automatically with username-only login.

Admin can edit staff username from the Office Staff list. This updates the linked login user and must remain unique.

Office staff users are hidden from the normal Users admin page. Manage them only from the Office Staff module.

Office staff can mark only their own daily office attendance from:

```text
/office-attendance/mark
```

Office staff login must always redirect to `/office-attendance/mark`, even if the browser previously opened an admin-only page.

Work modes:

- Office Work
- Remote Work

Office attendance supports:

- current server-time Check In
- current server-time Check Out
- multiple check-in/check-out sessions in the same day
- daily note
- configurable office duty timing
- configurable break timing
- configurable late grace minutes
- office overtime summary

Check In is available when there is no open session. After check-in, Check In is disabled and Check Out remains available. After checkout, Check In becomes available again as "Check In Again".

Checkout before check-in and duplicate checkout attempts are blocked by the controller.

Office attendance rules are managed from Office Staff > Attendance Report. Defaults are:

- Office time: 09:00 to 19:00
- Break time: 13:00 to 15:00
- Break included in duty time
- Late grace: 30 minutes
- Overtime enabled

Late is calculated from the first check-in after office start plus grace time. Overtime is calculated from the last checkout after office end. Work hours are calculated from check-in/check-out sessions. If break is set as deducted, overlapping break time is removed from work hours.

Admin report:

```text
/office-attendance/report
```

Report supports:

- date range filter, including multi-month ranges
- all staff or single staff filter
- remote/office mode filter
- search by code, name, designation, or note
- staff summary rows
- Details popup per staff member
- inline admin edits
- selected-staff PDF link
- print/PDF report from `/office-attendance/report-print`
- work hours, overtime, and late summaries
- same rule summary in browser report and print/PDF report

Database tables:

- `office_staff`
- `office_staff_attendances`
- `office_staff_attendance_sessions`

## Sorting And Search

Core admin/report tables should use clickable sortable headers with ascending/descending indicators.

Sorting is available on:

- employee lists
- project lists
- attendance detail
- leaves
- fines
- payroll salary settings
- payroll report
- projects overview

Fines sorting is handled through server-side query sorting so pagination remains accurate.

Search should include employee code wherever employee names are searchable.

## Known Technical Notes

Leave list rows are converted to base collections before merging long leave, daily leave, and absent records. This avoids Laravel Eloquent collection merge errors when rows are mapped to arrays.

Payroll save actions handle expired CSRF/session tokens by showing a clear message and refreshing the page so a new token is loaded.

MySQL previously crashed in XAMPP. Do not delete these folders unless a full backup exists:

```text
D:\xampp\mysql\data-old
D:\xampp\mysql\data-broken-*
```

## Safety Checklist

Before major changes:

1. Run `git status --short`.
2. Commit or push a checkpoint if current work is stable.
3. Backup database before schema changes.
4. Run migrations carefully.
5. Run `php artisan optimize:clear` after route/controller/view changes.
6. Run `npm run build` after frontend changes.
7. Check important pages in the browser.
8. Do not delete XAMPP MySQL data folders without a backup.

## Development Checklist

When continuing this project:

1. Work only in `D:\projects\attendance-system`.
2. Read `routes/web.php` first.
3. Read these controllers:
   - `app/Http/Controllers/DashboardController.php`
   - `app/Http/Controllers/PublicAttendanceController.php`
   - `app/Http/Controllers/PayrollController.php`
   - `app/Http/Controllers/ProjectController.php`
   - `app/Http/Controllers/EmployeeExpenseController.php`
   - `app/Http/Controllers/OfficeStaffController.php`
   - `app/Http/Controllers/OfficeAttendanceController.php`
   - `app/Http/Controllers/OfficeAttendanceReportController.php`
4. Read these frontend pages:
   - `resources/js/pages/Public/MarkAttendance.vue`
   - `resources/js/pages/Payroll/Index.vue`
   - `resources/js/pages/Payroll/Report.vue`
   - `resources/js/pages/Projects/Overview.vue`
   - `resources/js/pages/Attendance/Timesheet.vue`
   - `resources/js/pages/Expenses/Create.vue`
   - `resources/js/pages/Expenses/Index.vue`
   - `resources/js/pages/OfficeAttendance/Mark.vue`
   - `resources/js/pages/OfficeAttendance/Report.vue`
   - `resources/js/pages/OfficeStaff/Index.vue`
   - `resources/js/components/AppSidebar.vue`
5. Keep UI text English only.
6. Do not assume database data is disposable.
7. Ask before destructive commands.

## Recommended Future Improvements

- Add admin activity log for payroll, attendance, leave, fine, expense, and project changes.
- Add database backup button or scheduled backup script.
- Add export PDF/Excel for Project Employee History.
- Add stricter role middleware and route permission checks.
- Add tests for payroll carry-forward balance logic.
- Add tests for duplicate attendance prevention.
- Add tests for attendance date restrictions.
- Add tests for office staff check-in/check-out sessions.
- Add audit columns like `created_by`, `updated_by`, and `deleted_by` where needed.

## Cheque Format Management

Admin users can manage reusable cheque layouts from:

```text
/cheque-formats
```

The module includes:

- a reusable bank master created from the cheque format form
- searchable and bank-filtered cheque format listing
- create, edit, duplicate, and delete actions
- ten stable cheque field keys with individual positioning and formatting
- native mouse and touch dragging inside a bounded cheque preview
- synchronized manual coordinates and directional movement controls
- unsaved-change and concurrent-update protection
- optional cheque template images used only in designer and preparation previews
- a separate printable company logo with configurable position, size, and visibility
- Party Master with contact details and inline party creation
- cheque preparation with format and party selection
- physical cheque books are registered against a bank cheque format with a reference and fixed start/end leaf range
- only one cheque book may remain active for a cheque format; a new book is created after the active book is exhausted or closed
- cheque preparation allocates the next available book leaf transactionally and never permits a manual skip or number reuse
- issued cheque leaves retain their issue date, cheque date, payee, amount, purpose/remarks, and print actions in the cheque-book history
- book-backed cheques are voided instead of deleted so their physical leaf numbers remain auditable; older unassigned records remain untouched in the database but are not displayed in the cheque-book interface
- cheque creation uses a unique submission token so repeated Save requests cannot allocate duplicate cheque leaves
- cheque books preserve fixed-width physical serials, including leading zeros such as `00100` through `00300`, across preparation, history, preview, voucher, and print output
- print choices include Cheque Only, Voucher Only, and Voucher with Cheque Copy
- Add Party and Add Bank use modal dialogs instead of expanding the page inline
- automatic English amount-in-words generation
- UAE fractional amounts use Fils instead of Cents; each cheque can optionally force Fils onto the second amount-in-words line
- optional payment voucher details with cheque-derived number, dates, amount, words, and beneficiary
- separate cheque and A4 payment-voucher print pages; either page can be saved as PDF from the browser print dialog
- prepared cheque history with edit, print, and delete actions
- immutable format snapshots saved with prepared cheques

Cheque dimensions, field positions, and optional field sizes are stored in millimetres. Font sizes are stored in points. Preview zoom changes only the screen representation and never changes saved measurements.

Coordinate inputs support 0.01 mm precision so dragged and manually entered values use the same validation step.

The designer canvas, uploaded template image, and field guides are alignment aids only. Cheque printing outputs positioned text and the optional company logo onto a physical pre-printed cheque leaf supplied by the user. The print page does not receive or render the cheque template image, canvas outline, bank artwork, application layout, or designer guides. Browser printing should use 100% / Actual Size with headers and footers disabled. Printer-specific X/Y calibration remains a recommended future improvement.

Cheque workflow URLs:

```text
/cheques
/cheques/create
/cheque-parties
/cheque-formats
```

Database tables:

- `banks`
- `cheque_formats`
- `cheque_format_fields`
- `cheque_parties`
- `cheque_books`
- `cheque_book_leaves`
- `cheques`

## Latest Change Notes

- Contracting duty workflow is simplified for field users: new duty employees default to Present, Planned/Mark Planned Present/Publish controls are hidden, and one Submit Attendance action performs the protected final submission. Legacy Planned assignments are treated as Present when submitted.
- Contracting duty assignments are now displayed as project-wise Duty Plan accordions. A date can contain multiple project duties, each accordion contains only that project's employees, and users can copy all duties as project-grouped text for WhatsApp sharing.

- Office staff attendance now has a reception/tablet staff list at `/office-attendance/staff`.
- Office staff users are redirected to the staff list after login, and each active staff card opens that staff member's attendance form.
- Office attendance submissions from the staff list store the selected staff member's linked user as `submitted_by`.
- Office staff records now support an optional profile photo uploaded from the Office Staff admin page.
- Staff cards show photo or initials, designation, today's status, work mode, check-in time, and check-out time.
- The office staff attendance list uses a profile-card layout with photo/avatar, live status dot, work mode, session count, and check-in/check-out summary for reception/tablet use.
- Office staff cards display latest check-in/check-out in 12-hour AM/PM format; open sessions show `Open`. Report detail session summaries are highlighted as readable time chips.
- Office attendance print/PDF reports use the same readable 12-hour AM/PM time format and show repeated same-day sessions as highlighted session chips.
- Office attendance print/PDF reports include a cleaner summary-card header and improved table styling so browser print/download keeps the same layout.
- Office attendance report now has admin-configurable office start/end time, break time, break inclusion, late grace minutes, and overtime setting. Reports show work hours, overtime, and late status from the configured rules.
- Office staff attendance board `/office-attendance/staff` and staff profile attendance links are public for reception/tablet use. When a profile submits attendance, `submitted_by` is saved as that staff member's linked user, not the currently logged-in admin or another user.
- Office staff type display now uses `Remote` and `Office Work`; the existing database value for office staff remains `on_site` for compatibility.
- Office staff attendance board cards now use a profile-card design with a large rounded photo area, soft shadow, status badge, verified-style indicator, compact work/session stats, and a pill-style attendance action.
# Procurement Phase 1

- Admin-only procurement pages are available at `/suppliers`, `/purchase-bills`, and `/equipment`.
- Supplier outstanding balance is derived from opening balance plus purchase bills minus recorded supplier payments.
- Purchase bills support multiple material/equipment lines, VAT, discount, optional project linkage, and JPG/PNG/WebP/PDF attachments.
- Bill totals are recalculated by the backend. Supplier payments are transactional, may be partial, and cannot exceed the bill balance.
- Equipment can optionally link to an equipment-type purchase bill line, a project, and an employee.
- Suppliers and purchase bills with related financial/equipment records are protected from unsafe deletion.
- Uploaded procurement documents use Laravel's `public` disk; production requires `php artisan storage:link`.
