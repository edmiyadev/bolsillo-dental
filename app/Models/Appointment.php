<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\DentalSpecialty;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    use HasFactory, SoftDeletes;

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
