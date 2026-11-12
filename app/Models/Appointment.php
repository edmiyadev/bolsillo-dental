<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\DentalSpecialty;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'patient_id',
    'dentist_id',
    'cubicle_id',
    'service_id',
    'created_by',
    'updated_by',
    'is_custom_reason',
    'custom_reason',
    'starts_at',
    'duration_minutes',
    'ends_at',
    'status',
    'observations',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToUser, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Cubicle, $this>
     */
    public function cubicle(): BelongsTo
    {
        return $this->belongsTo(Cubicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScopes()->withTrashed();
    }

    /**
     * Scope appointments for a specific user: where they are the dentist or the creator.
     *
     * @param  Builder<$this>  $builder
     */
    public function applyUserScope(Builder $builder, User $user): void
    {
        $builder->where(function (Builder $query) use ($user): void {
            $query->where($this->qualifyColumn('dentist_id'), $user->id)
                ->orWhere($this->qualifyColumn('created_by'), $user->id);
        });
    }

    public function reasonLabel(): string
    {
        return $this->is_custom_reason
            ? (string) $this->custom_reason
            : (string) ($this->service?->name ?? $this->custom_reason ?? '');
    }

    public function getSpecialtyAttribute(): DentalSpecialty
    {
        return $this->service?->specialty ?? DentalSpecialty::General;
    }

    public function setSpecialtyAttribute(mixed $value): void
    {
        // Virtual attribute derived from related service; ignored during direct column persistence.
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_custom_reason' => 'boolean',
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }
}
