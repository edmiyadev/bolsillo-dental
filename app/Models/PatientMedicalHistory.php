<?php

namespace App\Models;

use Database\Factories\PatientMedicalHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'critical_alerts', 'anamnesis_data', 'general_observations'])]
class PatientMedicalHistory extends Model
{
    /** @use HasFactory<PatientMedicalHistoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'critical_alerts' => 'array',
            'anamnesis_data' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
