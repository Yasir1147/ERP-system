<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Maps a name written in a WhatsApp message to an employee record.
 *
 * The chat uses first names only, spelled however the writer felt that day:
 * Godwin/Godwine, Suleman/Sulaiman/Suleiman, Younas/Younis/yunis/Youn. A
 * single first name can also belong to more than one employee.
 *
 * The matcher therefore reports its confidence rather than returning a
 * single answer. Anything short of one clear match is handed back for a
 * person to resolve, because a wrong match writes attendance, and pay,
 * against the wrong worker.
 */
class ChatEmployeeMatcher
{
    public const MATCHED = 'matched';
    public const AMBIGUOUS = 'ambiguous';
    public const NOT_FOUND = 'not_found';

    /** @var Collection<int, array<string, mixed>> */
    private Collection $employees;

    /**
     * @param  Collection<int, Employee>  $employees
     */
    public function __construct(Collection $employees)
    {
        $this->employees = $employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'code' => $employee->code,
            'name' => $employee->name,
            'tokens' => $this->tokens($employee->name),
        ])->values();
    }

    /**
     * @return array{status: string, employee: array<string, mixed>|null, candidates: list<string>}
     */
    public function match(string $sourceName): array
    {
        $queryTokens = $this->tokens($sourceName);

        if ($queryTokens === []) {
            return ['status' => self::NOT_FOUND, 'employee' => null, 'candidates' => []];
        }

        $scored = $this->employees
            ->map(fn (array $employee) => $employee + ['score' => $this->score($employee['tokens'], $queryTokens)])
            ->filter(fn (array $employee) => $employee['score'] > 0)
            ->sortByDesc('score')
            ->values();

        if ($scored->isEmpty()) {
            return ['status' => self::NOT_FOUND, 'employee' => null, 'candidates' => []];
        }

        $best = $scored->first();
        $tied = $scored->where('score', $best['score']);

        if ($tied->count() > 1) {
            return [
                'status' => self::AMBIGUOUS,
                'employee' => null,
                'candidates' => $tied
                    ->map(fn (array $employee) => trim($employee['code'].' - '.$employee['name']))
                    ->all(),
            ];
        }

        return [
            'status' => self::MATCHED,
            'employee' => ['id' => $best['id'], 'code' => $best['code'], 'name' => $best['name']],
            'candidates' => [],
        ];
    }

    /**
     * @param  list<string>  $employeeTokens
     * @param  list<string>  $queryTokens
     */
    private function score(array $employeeTokens, array $queryTokens): int
    {
        $total = 0;

        foreach ($queryTokens as $queryToken) {
            $best = 0;

            foreach ($employeeTokens as $employeeToken) {
                $best = max($best, $this->tokenScore($employeeToken, $queryToken));
            }

            // Every part of the written name has to land somewhere. "Ali Raza"
            // must not match an employee called only "Ali".
            if ($best === 0) {
                return 0;
            }

            $total += $best;
        }

        return $total;
    }

    private function tokenScore(string $employeeToken, string $queryToken): int
    {
        if ($employeeToken === $queryToken) {
            return 100;
        }

        // "Youn" for Younas, "Nick" for Nickson.
        if (mb_strlen($queryToken) >= 3 && str_starts_with($employeeToken, $queryToken)) {
            return 70;
        }

        if (mb_strlen($queryToken) >= 4) {
            $distance = levenshtein($employeeToken, $queryToken);
            $allowed = mb_strlen($queryToken) <= 6 ? 1 : 2;

            if ($distance <= $allowed) {
                return 40 - $distance;
            }
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;

        $tokens = array_values(array_filter(preg_split('/\s+/', trim($value)) ?: []));

        // "MD" and "Mohammad" are used interchangeably as a prefix and carry
        // no identifying information on their own.
        $noise = ['md', 'mohammad', 'muhammad', 'mohamed', 'mr'];

        $meaningful = array_values(array_diff($tokens, $noise));

        return $meaningful !== [] ? $meaningful : $tokens;
    }
}
