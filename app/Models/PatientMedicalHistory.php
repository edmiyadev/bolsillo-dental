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
     * Predefined critical alert questions (high risk medical conditions).
     *
     * @return array<string, array{label: string, short_label: string, placeholder: string}>
     */
    public static function criticalAlertQuestions(): array
    {
        return [
            'allergies' => [
                'label' => __('¿Tiene alergia a medicamentos (penicilina, anestésicos u otros) o materiales (látex)?'),
                'short_label' => __('Alergias'),
                'placeholder' => __('Especifique medicamentos o materiales...'),
            ],
            'hypertension' => [
                'label' => __('¿Padece de hipertensión arterial?'),
                'short_label' => __('Hipertensión'),
                'placeholder' => __('Especifique medicación o estado de control...'),
            ],
            'diabetes' => [
                'label' => __('¿Padece de diabetes?'),
                'short_label' => __('Diabetes'),
                'placeholder' => __('Especifique tipo (1 o 2) o control...'),
            ],
            'heart_disease' => [
                'label' => __('¿Padece de problemas cardíacos, cardiopatías o porta marcapasos?'),
                'short_label' => __('Problemas cardíacos'),
                'placeholder' => __('Especifique afección o marcapasos...'),
            ],
            'bleeding_disorders' => [
                'label' => __('¿Tiene problemas de coagulación o toma medicamentos anticoagulantes?'),
                'short_label' => __('Trastorno de coagulación'),
                'placeholder' => __('Especifique trastorno o anticoagulante...'),
            ],
            'pregnancy_or_breastfeeding' => [
                'label' => __('¿Está en estado de embarazo o período de lactancia?'),
                'short_label' => __('Embarazo / Lactancia'),
                'placeholder' => __('Especifique semanas de gestación o lactancia...'),
            ],
            'infectious_diseases' => [
                'label' => __('¿Padece de enfermedades infectocontagiosas (Hepatitis, VIH u otras)?'),
                'short_label' => __('Enfermedad infectocontagiosa'),
                'placeholder' => __('Especifique enfermedad o tratamiento...'),
            ],
        ];
    }

    /**
     * Predefined general anamnesis questions.
     *
     * @return array<string, array{label: string, short_label: string, placeholder: string}>
     */
    public static function anamnesisQuestions(): array
    {
        return [
            'under_medical_treatment' => [
                'label' => __('¿Está bajo tratamiento médico actualmente?'),
                'short_label' => __('Tratamiento médico actual'),
                'placeholder' => __('Especifique condición o médico tratante...'),
            ],
            'taking_medication' => [
                'label' => __('¿Toma medicamentos de forma continua?'),
                'short_label' => __('Medicación continua'),
                'placeholder' => __('Especifique medicamentos y dosis...'),
            ],
            'recent_hospitalization' => [
                'label' => __('¿Ha tenido cirugías u hospitalizaciones en los últimos 2 años?'),
                'short_label' => __('Cirugías / Hospitalizaciones'),
                'placeholder' => __('Especifique procedimiento y año...'),
            ],
            'respiratory_problems' => [
                'label' => __('¿Padece de problemas respiratorios (asma, dificultad al respirar)?'),
                'short_label' => __('Problemas respiratorios'),
                'placeholder' => __('Especifique condición respiratoria...'),
            ],
            'gastric_problems' => [
                'label' => __('¿Padece de problemas gástricos (reflujo severo, acidez, úlceras)?'),
                'short_label' => __('Problemas gástricos'),
                'placeholder' => __('Especifique condición gástrica...'),
            ],
            'tobacco_use' => [
                'label' => __('¿Fuma o consume tabaco / vapeo?'),
                'short_label' => __('Consumo de tabaco'),
                'placeholder' => __('Especifique frecuencia o cantidad...'),
            ],
            'adverse_anesthesia_reaction' => [
                'label' => __('¿Ha tenido reacciones adversas o malas experiencias con anestesia dental previa?'),
                'short_label' => __('Reacción a anestesia dental'),
                'placeholder' => __('Describa la reacción experimentada...'),
            ],
            'fainting_or_dental_anxiety' => [
                'label' => __('¿Tiende a marearse, desmayarse o sufre de ansiedad dental severa?'),
                'short_label' => __('Ansiedad / Mareos'),
                'placeholder' => __('Especifique detalles...'),
            ],
        ];
    }

    /**
     * Get active critical alerts (affirmative responses).
     *
     * @return array<int, array{key: string, label: string, full_label: string, detail: ?string, summary: string}>
     */
    public function activeCriticalAlerts(): array
    {
        return $this->extractActiveQuestions($this->critical_alerts, static::criticalAlertQuestions());
    }

    /**
     * Get active anamnesis conditions (affirmative responses).
     *
     * @return array<int, array{key: string, label: string, full_label: string, detail: ?string, summary: string}>
     */
    public function activeAnamnesis(): array
    {
        return $this->extractActiveQuestions($this->anamnesis_data, static::anamnesisQuestions());
    }

    /**
     * Helper to extract active questions from stored json.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, array{label: string, short_label: string, placeholder?: string}>  $catalog
     * @return array<int, array{key: string, label: string, full_label: string, detail: ?string, summary: string}>
     */
    protected function extractActiveQuestions(?array $data, array $catalog): array
    {
        if (empty($data)) {
            return [];
        }

        $active = [];

        foreach ($data as $key => $entry) {
            $isPositive = false;
            $detail = null;

            if (is_array($entry)) {
                $isPositive = (bool) ($entry['value'] ?? false);
                $detail = ! empty($entry['detail']) ? (string) $entry['detail'] : null;
            } elseif (is_bool($entry)) {
                $isPositive = $entry;
            } elseif (is_string($entry) && ! empty($entry)) {
                $isPositive = true;
                $detail = $entry;
            }

            if ($isPositive) {
                $questionInfo = $catalog[$key] ?? null;
                $label = $questionInfo['short_label'] ?? (is_string($key) ? ucfirst(str_replace('_', ' ', $key)) : (string) $entry);
                $fullLabel = $questionInfo['label'] ?? $label;
                $summary = $detail ? "{$label}: {$detail}" : $label;

                $active[] = [
                    'key' => (string) $key,
                    'label' => $label,
                    'full_label' => $fullLabel,
                    'detail' => $detail,
                    'summary' => $summary,
                ];
            }
        }

        return $active;
    }

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
