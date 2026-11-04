<?php

namespace App\Rules;

use App\Enums\DayOfWeek;
use App\Models\Branch;
use App\ValueObjects\WeeklySchedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CollaboratorBranchSchedulesRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $branchIds = $this->extractBranchIds($value);

        if (count($branchIds) !== count(array_unique($branchIds))) {
            $fail(__('A collaborator cannot have the same branch assigned more than once.'));

            return;
        }

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->get()
            ->keyBy('id');

        /** @var array<string, array<int, array{branch_name: string, open_time: string, close_time: string}>> $activeDayIntervals */
        $activeDayIntervals = [];

        foreach ($value as $item) {
            if (! is_array($item) || empty($item['branch_id'])) {
                continue;
            }

            $branch = $branches->get((int) $item['branch_id']);
            if (! $branch) {
                continue;
            }

            $this->validateBranchScheduleItem($item, $branch, $activeDayIntervals, $fail);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, array<int, array{branch_name: string, open_time: string, close_time: string}>>  $activeDayIntervals
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateBranchScheduleItem(array $item, Branch $branch, array &$activeDayIntervals, Closure $fail): void
    {
        $isAssignmentActive = (bool) ($item['is_active'] ?? true);
        $branchSchedule = WeeklySchedule::from($branch->opening_hours ?? DayOfWeek::defaultSchedule());
        $collaboratorSchedule = WeeklySchedule::from(is_array($item['schedule'] ?? null) ? $item['schedule'] : []);

        foreach ($collaboratorSchedule->workingDays() as $dayEntry) {
            $dayKey = (string) ($dayEntry['day'] ?? '');
            $dayLabel = DayOfWeek::tryFrom($dayKey)?->getLabel() ?? $dayKey;

            $openTime = $this->normalizeTime((string) ($dayEntry['open_time'] ?? ''));
            $closeTime = $this->normalizeTime((string) ($dayEntry['close_time'] ?? ''));

            if ($openTime === '' || $closeTime === '') {
                $fail(__('Please specify both start and end times for :day at :branch.', [
                    'day' => $dayLabel,
                    'branch' => $branch->name,
                ]));

                continue;
            }

            if ($openTime >= $closeTime) {
                $fail(__('The end time must be after the start time on :day at :branch.', [
                    'day' => $dayLabel,
                    'branch' => $branch->name,
                ]));

                continue;
            }

            if (! $branchSchedule->isOpenOn($dayKey)) {
                $fail(__('The collaborator cannot work on :day at :branch because the branch is closed on that day.', [
                    'day' => $dayLabel,
                    'branch' => $branch->name,
                ]));

                continue;
            }

            $branchInterval = $branchSchedule->intervalFor($dayKey);
            $branchOpenTime = $this->normalizeTime($branchInterval['open_time'] ?? '');
            $branchCloseTime = $this->normalizeTime($branchInterval['close_time'] ?? '');

            if ($branchOpenTime !== '' && $branchCloseTime !== '' && ($openTime < $branchOpenTime || $closeTime > $branchCloseTime)) {
                $fail(__('The schedule on :day at :branch (:start - :end) must be within the branch opening hours (:branch_start - :branch_end).', [
                    'day' => $dayLabel,
                    'branch' => $branch->name,
                    'start' => $openTime,
                    'end' => $closeTime,
                    'branch_start' => $branchOpenTime,
                    'branch_end' => $branchCloseTime,
                ]));

                continue;
            }

            if ($isAssignmentActive) {
                foreach ($activeDayIntervals[$dayKey] ?? [] as $existingInterval) {
                    if ($openTime < $existingInterval['close_time'] && $existingInterval['open_time'] < $closeTime) {
                        $fail(__('The schedule on :day overlaps between :branch_a (:start_a - :end_a) and :branch_b (:start_b - :end_b).', [
                            'day' => $dayLabel,
                            'branch_a' => $existingInterval['branch_name'],
                            'start_a' => $existingInterval['open_time'],
                            'end_a' => $existingInterval['close_time'],
                            'branch_b' => $branch->name,
                            'start_b' => $openTime,
                            'end_b' => $closeTime,
                        ]));
                    }
                }

                $activeDayIntervals[$dayKey][] = [
                    'branch_name' => $branch->name,
                    'open_time' => $openTime,
                    'close_time' => $closeTime,
                ];
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, int>
     */
    private function extractBranchIds(array $items): array
    {
        return collect($items)
            ->pluck('branch_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function normalizeTime(?string $time): string
    {
        $trimmed = trim((string) $time);
        if ($trimmed === '') {
            return '';
        }

        if (strlen($trimmed) === 4 && $trimmed[1] === ':') {
            $trimmed = '0'.$trimmed;
        }

        return substr($trimmed, 0, 5);
    }
}
