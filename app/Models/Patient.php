<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\IdentificationType;
use App\Models\Concerns\HasAuthor;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'created_by',
    'updated_by',
    'first_name',
    'last_name',
    'document_type',
    'identification_number',
    'email',
    'phone',
    'residential_phone',
    'work_phone',
    'health_insurance',
    'birth_date',
    'gender',
    'photo_url',
    'address',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasAuthor, HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => IdentificationType::class,
            'birth_date' => 'date',
            'gender' => Gender::class,
        ];
    }

    public function medicalHistory(): HasOne
    {
        return $this->hasOne(PatientMedicalHistory::class);
    }

    /**
     * @return HasMany<OdontogramEntry, $this>
     */
    public function odontogramEntries(): HasMany
    {
        return $this->hasMany(OdontogramEntry::class);
    }

    /**
     * @return HasMany<OdontogramSnapshot, $this>
     */
    public function odontogramSnapshots(): HasMany
    {
        return $this->hasMany(OdontogramSnapshot::class);
    }

    public function hasOdontogram(): bool
    {
        return $this->odontogramEntries()->exists() || $this->odontogramSnapshots()->exists();
    }
}
