<?php

namespace App\Models;

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\Concerns\HasAuthor;
use Database\Factories\OdontogramEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'patient_id',
    'branch_id',
    'appointment_id',
    'service_id',
    'dentist_id',
    'assistant_id',
    'scope',
    'tooth_number',
    'bridge_to_tooth',
    'arch',
    'surfaces',
    'condition_code',
    'status',
    'notes',
    'created_by',
    'updated_by',
])]
class OdontogramEntry extends Model
{
    /** @use HasFactory<OdontogramEntryFactory> */
    use HasAuthor, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScopes()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_id')->withTrashed();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where($this->qualifyColumn('patient_id'), $patientId);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActiveInCurrentView(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), [
            OdontogramStatus::Finding->value,
            OdontogramStatus::PreExisting->value,
            OdontogramStatus::Planned->value,
            OdontogramStatus::Completed->value,
        ]);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForInitialView(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), [
            OdontogramStatus::Finding->value,
            OdontogramStatus::PreExisting->value,
            OdontogramStatus::Superseded->value,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => OdontogramScope::class,
            'tooth_number' => 'integer',
            'bridge_to_tooth' => 'integer',
            'arch' => DentalArch::class,
            'surfaces' => 'array',
            'condition_code' => OdontogramCondition::class,
            'status' => OdontogramStatus::class,
        ];
    }
}
