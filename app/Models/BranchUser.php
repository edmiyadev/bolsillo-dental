<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'branch_id',
    'user_id',
    'schedule',
    'is_active',
])]
class BranchUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'branch_user';

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, string>
     */
    public function workingDaysLabels(): array
    {
        return collect($this->schedule ?? [])
            ->filter(fn (mixed $dayEntry): bool => is_array($dayEntry) && ! empty($dayEntry['is_open']))
            ->map(function (array $dayEntry): string {
                $dayKey = (string) ($dayEntry['day'] ?? '');

                return DayOfWeek::tryFrom($dayKey)?->getLabel() ?? $dayKey;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{day_label: string, open_time: string, close_time: string}>
     */
    public function workingScheduleRows(): array
    {
        return collect($this->schedule ?? [])
            ->filter(fn (mixed $dayEntry): bool => is_array($dayEntry) && ! empty($dayEntry['is_open']))
            ->map(function (array $dayEntry): array {
                $dayKey = (string) ($dayEntry['day'] ?? '');

                return [
                    'day_label' => DayOfWeek::tryFrom($dayKey)?->getLabel() ?? $dayKey,
                    'open_time' => substr((string) ($dayEntry['open_time'] ?? '-'), 0, 5),
                    'close_time' => substr((string) ($dayEntry['close_time'] ?? '-'), 0, 5),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
