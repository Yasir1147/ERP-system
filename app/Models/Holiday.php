<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A company holiday.
 *
 * A paid holiday is a day everyone is paid for without working it. Anyone who
 * does work that day keeps the paid holiday and has their hours counted as
 * overtime, because they were not obliged to be there.
 */
class Holiday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'is_paid',
        'employee_type',
        'note',
        'created_by',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_paid' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Paid holiday dates inside a range, keyed by date, for one employee type.
     *
     * A holiday with no employee type is observed by everyone, so both it and
     * the type-specific ones are returned.
     *
     * @return Collection<string, Holiday>
     */
    public static function paidBetween(string $from, string $to, ?string $employeeType = null): Collection
    {
        return static::query()
            ->where('is_paid', true)
            ->whereDate('holiday_date', '>=', $from)
            ->whereDate('holiday_date', '<=', $to)
            ->when($employeeType, fn ($query) => $query
                ->where(fn ($scope) => $scope
                    ->whereNull('employee_type')
                    ->orWhere('employee_type', $employeeType)))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());
    }

    /**
     * Every holiday in a range keyed by date, whether paid or not.
     *
     * @return Collection<string, Holiday>
     */
    public static function between(string $from, string $to, ?string $employeeType = null): Collection
    {
        return static::query()
            ->whereDate('holiday_date', '>=', $from)
            ->whereDate('holiday_date', '<=', $to)
            ->when($employeeType, fn ($query) => $query
                ->where(fn ($scope) => $scope
                    ->whereNull('employee_type')
                    ->orWhere('employee_type', $employeeType)))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());
    }
}
