<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Overtime
{
    public static function hours(mixed $value): float
    {
        return round((float) $value * 60) / 60;
    }

    public static function label(mixed $value): string
    {
        $minutes = (int) round((float) $value * 60);

        return trim(($minutes >= 60 ? intdiv($minutes, 60).' hr ' : '').($minutes % 60 ? ($minutes % 60).' min' : ($minutes === 0 ? '0 hr' : '')));
    }

    public static function prepare(Request $request, ?string $type): void
    {
        if (! $request->boolean('has_overtime') || $request->input('status') !== AttendanceRecord::STATUS_PRESENT) {
            $request->merge(['overtime_hours' => null]);

            return;
        }
        if ($type !== 'rope_access' || ! $request->boolean('has_overtime') || $request->input('status') !== AttendanceRecord::STATUS_PRESENT || ! $request->has('overtime_minutes')) {
            return;
        }
        $data = $request->validate([
            'overtime_hours' => ['required', 'integer', 'between:0,10'],
            'overtime_minutes' => ['required', 'integer', 'between:0,59'],
        ]);
        $minutes = (int) $data['overtime_hours'] * 60 + (int) $data['overtime_minutes'];
        if ($minutes < 1 || $minutes > 600) {
            throw ValidationException::withMessages(['overtime_hours' => 'Overtime must be between 1 minute and 10 hours.']);
        }
        $request->merge(['overtime_hours' => $minutes / 60]);
    }

    public static function rules(?string $type): array
    {
        return $type === 'rope_access'
            ? ['numeric', 'between:0.01666666,10', function ($attribute, $value, $fail) {
                if (abs((float) $value * 60 - round((float) $value * 60)) > 0.00001) {
                    $fail('Overtime must use whole minutes.');
                }
            }]
            : ['integer', 'between:1,10'];
    }
}
