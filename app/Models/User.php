<?php

namespace App\Models;

use App\Enums\DentalSpecialty;
use App\Models\Concerns\HasAuthor;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'specialties', 'created_by', 'updated_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasAuthor, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'created_by');
    }

    /**
     * @return HasMany<CommissionRecord, $this>
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'created_by');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'dentist_id');
    }

    /**
     * @return BelongsToMany<Branch, $this, BranchUser>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->using(BranchUser::class)
            ->withPivot(['id', 'schedule', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function branchSchedules(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function supportsSpecialty(DentalSpecialty|string $specialty): bool
    {
        $specialtyValue = $specialty instanceof DentalSpecialty ? $specialty->value : $specialty;
        $specialties = $this->specialties ?? [];

        if ($specialties === []) {
            return $specialtyValue === DentalSpecialty::General->value;
        }

        $supportedValues = array_map(
            fn (mixed $item): string => $item instanceof DentalSpecialty ? $item->value : (string) $item,
            $specialties,
        );

        return in_array($specialtyValue, $supportedValues, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($panel->getId(), ['admin', 'central'], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'specialties' => 'array',
        ];
    }
}
