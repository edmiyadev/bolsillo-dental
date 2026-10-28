<?php

namespace App\Models;

use App\Observers\BranchObserver;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
#[ObservedBy([BranchObserver::class])]
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
     * @return HasMany<Cubicle, $this>
     */
    public function cubicles(): HasMany
    {
        return $this->hasMany(Cubicle::class);
    }

    /**
     * @return BelongsToMany<User, $this, BranchUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(BranchUser::class)
            ->withPivot(['id', 'schedule', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function collaboratorSchedules(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function isOnlyActiveBranch(): bool
    {
        return $this->is_active && static::query()
            ->where('clinic_id', $this->clinic_id)
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->doesntExist();
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
