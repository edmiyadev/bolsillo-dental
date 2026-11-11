<?php

namespace App\Models;

use App\Models\Concerns\HasAuthor;
use Database\Factories\PatientMedicalHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'created_by', 'updated_by', 'medical_data', 'general_observations'])]
class PatientMedicalHistory extends Model
{
    /** @use HasFactory<PatientMedicalHistoryFactory> */
    use HasAuthor, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'medical_data' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
