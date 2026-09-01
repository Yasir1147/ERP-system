<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company holidays.
 *
 * A paid holiday pays everyone on the books for a day nobody was asked to
 * work. Anyone who came in anyway keeps that paid day and has the hours
 * counted as overtime.
 */
class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) ($request->query('year') ?: now()->year);

        return Inertia::render('Holidays/Index', [
            'holidays' => Holiday::query()
                ->with('creator:id,name')
                ->whereYear('holiday_date', $year)
                ->orderBy('holiday_date')
                ->get()
                ->map(fn (Holiday $holiday) => [
                    'id' => $holiday->id,
                    'date' => $holiday->holiday_date->toDateString(),
                    'dateLabel' => $holiday->holiday_date->format('d/m/Y'),
                    'weekday' => $holiday->holiday_date->format('l'),
                    'name' => $holiday->name,
                    'isPaid' => $holiday->is_paid,
                    'employeeType' => $holiday->employee_type,
                    'employeeTypeLabel' => $holiday->employee_type
                        ? (Employee::TYPES[$holiday->employee_type] ?? $holiday->employee_type)
                        : 'All employees',
                    'note' => $holiday->note,
                    'createdBy' => $holiday->creator?->name,
                ]),
            'filters' => ['year' => $year],
            'years' => $this->years(),
            'employeeTypes' => collect(Employee::TYPES)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Holiday::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Holiday saved.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($this->validated($request, $holiday));

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        $data = $request->validate([
            'holiday_date' => [
                'required',
                'date',
                // Checked here rather than with Rule::unique: "all employees"
                // is stored as null, and neither `= NULL` in SQL nor the
                // database's own unique index treats two nulls as a clash, so
                // the duplicate would slip through and pay the day twice.
                function (string $attribute, mixed $value, callable $fail) use ($request, $holiday) {
                    $type = $request->input('employee_type') ?: null;

                    $clash = Holiday::query()
                        ->whereDate('holiday_date', $value)
                        ->when($holiday, fn ($query) => $query->whereKeyNot($holiday->id))
                        ->when(
                            $type === null,
                            fn ($query) => $query->whereNull('employee_type'),
                            fn ($query) => $query->where('employee_type', $type),
                        )
                        ->exists();

                    if ($clash) {
                        $fail('This date is already a holiday for these employees.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:120'],
            'is_paid' => ['required', 'boolean'],
            'employee_type' => ['nullable', Rule::in(array_keys(Employee::TYPES))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['employee_type'] = $data['employee_type'] ?: null;

        return $data;
    }

    /**
     * @return list<int>
     */
    private function years(): array
    {
        $current = now()->year;

        return range($current - 3, $current + 1);
    }
}
