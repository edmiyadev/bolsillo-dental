<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'clinic_id',
    'name',
    'code',
    'phone',
    'address',
    'opening_hours',
    'is_main',
    'is_active',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Clinic, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * @return array<string, string>
     */
    public static function daysOfWeek(): array
    {
        return [
            'monday' => __('Monday'),
            'tuesday' => __('Tuesday'),
            'wednesday' => __('Wednesday'),
            'thursday' => __('Thursday'),
            'friday' => __('Friday'),
            'saturday' => __('Saturday'),
            'sunday' => __('Sunday'),
        ];
    }

    /**
     * @return array<string, array{is_open: bool, open_time: ?string, close_time: ?string}>
     */
    public static function defaultOpeningHours(): array
    {
        return [
            'monday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '18:00'],
            'tuesday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '18:00'],
            'wednesday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '18:00'],
            'thursday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '18:00'],
            'friday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '18:00'],
            'saturday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '13:00'],
            'sunday' => ['is_open' => false, 'open_time' => null, 'close_time' => null],
        ];
    }

    public function isOnlyActiveBranch(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return static::query()
            ->where('clinic_id', $this->clinic_id)
            ->where('is_active', true)
            ->where('id', '!=', $this->id)
            ->doesntExist();
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch): void {
            if (blank($branch->clinic_id)) {
                $branch->clinic_id = Clinic::query()->value('id');
            }
        });

        static::saving(function (Branch $branch): void {
            if ($branch->exists && ! $branch->is_active && $branch->getOriginal('is_active', true) && $branch->isOnlyActiveBranch()) {
                throw ValidationException::withMessages([
                    'is_active' => __('You cannot deactivate the only active branch.'),
                ]);
            }

            if ($branch->is_main && filled($branch->clinic_id)) {
                static::query()
                    ->where('clinic_id', $branch->clinic_id)
                    ->where('id', '!=', $branch->id)
                    ->update(['is_main' => false]);
            }
        });

        static::deleting(function (Branch $branch): void {
            if ($branch->clinic && $branch->clinic->branches()->count() <= 1) {
                throw new DomainException(__('You cannot delete the only branch of the clinic.'));
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            'opening_hours' => 'array',
        ];
    }
}
