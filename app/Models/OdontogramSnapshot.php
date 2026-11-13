<?php

namespace App\Models;

use App\Enums\OdontogramSnapshotType;
use App\Models\Concerns\HasAuthor;
use Database\Factories\OdontogramSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'patient_id',
    'type',
    'title',
    'notes',
    'payload',
    'created_by',
    'updated_by',
])]
class OdontogramSnapshot extends Model
{
    /** @use HasFactory<OdontogramSnapshotFactory> */
    use HasAuthor, HasFactory;

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OdontogramSnapshotType::class,
            'payload' => 'array',
        ];
    }
}
