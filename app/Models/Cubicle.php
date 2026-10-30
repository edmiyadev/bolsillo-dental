<?php

namespace App\Models;

use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Observers\CubicleObserver;
use Database\Factories\CubicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'name',
    'code',
    'specialty_types',
    'specialty_schedules',
    'status',
])]
#[ObservedBy([CubicleObserver::class])]
class Cubicle extends Model
{
    /** @use HasFactory<CubicleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function supportsSpecialty(DentalSpecialty|string $specialty): bool
    {
        $specialtyValue = $specialty instanceof DentalSpecialty ? $specialty->value : $specialty;
        $supported = $this->specialty_types ?? [];

        return in_array($specialtyValue, $supported, true);
    }

    public function isAvailableForSpecialtyAt(DentalSpecialty|string $specialty, DayOfWeek $day, string $startTime, string $endTime): bool
    {
        if ($this->status !== CubicleStatus::Active) {
            return false;
        }

        if (! $this->supportsSpecialty($specialty)) {
            return false;
        }

        $specialtyValue = $specialty instanceof DentalSpecialty ? $specialty->value : $specialty;
        $schedules = $this->specialty_schedules ?? [];

        foreach ($schedules as $rule) {
            if (($rule['day'] ?? null) !== $day->value) {
                continue;
            }

            $ruleStart = (string) ($rule['start_time'] ?? '');
            $ruleEnd = (string) ($rule['end_time'] ?? '');

            if ($ruleStart < $endTime && $startTime < $ruleEnd) {
                if (($rule['specialty'] ?? null) !== $specialtyValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specialty_types' => 'array',
            'specialty_schedules' => 'array',
            'status' => CubicleStatus::class,
        ];
    }
}
