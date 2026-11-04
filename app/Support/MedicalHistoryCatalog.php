<?php

namespace App\Support;

class MedicalHistoryCatalog
{
    /**
     * Predefined medical history questions catalog.
     *
     * @return array<string, array{label: string, short_label: string, placeholder: string, is_critical: bool, has_detail: bool}>
     */
    public static function questions(): array
    {
        return [
            'allergies' => [
                'label' => __('¿Tiene alergia a medicamentos (penicilina, anestésicos u otros) o materiales (látex)?'),
                'short_label' => __('Alergias'),
                'placeholder' => __('Especifique medicamentos o materiales...'),
                'is_critical' => true,
                'has_detail' => false,
            ],
            'hypertension' => [
                'label' => __('¿Padece de hipertensión arterial?'),
                'short_label' => __('Hipertensión'),
                'placeholder' => __('Especifique medicación o estado de control...'),
                'is_critical' => true,
                'has_detail' => true,
            ],
            'diabetes' => [
                'label' => __('¿Padece de diabetes?'),
                'short_label' => __('Diabetes'),
                'placeholder' => __('Especifique tipo (1 o 2) o control...'),
                'is_critical' => true,
                'has_detail' => true,
            ],
            'heart_disease' => [
                'label' => __('¿Padece de problemas cardíacos, cardiopatías o porta marcapasos?'),
                'short_label' => __('Problemas cardíacos'),
                'placeholder' => __('Especifique afección o marcapasos...'),
                'is_critical' => true,
                'has_detail' => true,
            ],
            'bleeding_disorders' => [
                'label' => __('¿Tiene problemas de coagulación o toma medicamentos anticoagulantes?'),
                'short_label' => __('Trastorno de coagulación'),
                'placeholder' => __('Especifique trastorno o anticoagulante...'),
                'is_critical' => true,
                'has_detail' => true,
            ],
            'pregnancy_or_breastfeeding' => [
                'label' => __('¿Está en estado de embarazo o período de lactancia?'),
                'short_label' => __('Embarazo / Lactancia'),
                'placeholder' => __('Especifique semanas de gestación o lactancia...'),
                'is_critical' => true,
                'has_detail' => true,
            ],
            'infectious_diseases' => [
                'label' => __('¿Padece de enfermedades infectocontagiosas (Hepatitis, VIH u otras)?'),
                'short_label' => __('Enfermedad infectocontagiosa'),
                'placeholder' => __('Especifique enfermedad o tratamiento...'),
                'is_critical' => true,
                'has_detail' => true,
            ],
            'under_medical_treatment' => [
                'label' => __('¿Está bajo tratamiento médico actualmente?'),
                'short_label' => __('Tratamiento médico actual'),
                'placeholder' => __('Especifique condición o médico tratante...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'taking_medication' => [
                'label' => __('¿Toma medicamentos de forma continua?'),
                'short_label' => __('Medicación continua'),
                'placeholder' => __('Especifique medicamentos y dosis...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'recent_hospitalization' => [
                'label' => __('¿Ha tenido cirugías u hospitalizaciones en los últimos 2 años?'),
                'short_label' => __('Cirugías / Hospitalizaciones'),
                'placeholder' => __('Especifique procedimiento y año...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'respiratory_problems' => [
                'label' => __('¿Padece de problemas respiratorios (asma, dificultad al respirar)?'),
                'short_label' => __('Problemas respiratorios'),
                'placeholder' => __('Especifique condición respiratoria...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'gastric_problems' => [
                'label' => __('¿Padece de problemas gástricos (reflujo severo, acidez, úlceras)?'),
                'short_label' => __('Problemas gástricos'),
                'placeholder' => __('Especifique condición gástrica...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'tobacco_use' => [
                'label' => __('¿Fuma o consume tabaco / vapeo?'),
                'short_label' => __('Consumo de tabaco'),
                'placeholder' => __('Especifique frecuencia o cantidad...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'adverse_anesthesia_reaction' => [
                'label' => __('¿Ha tenido reacciones adversas o malas experiencias con anestesia dental previa?'),
                'short_label' => __('Reacción a anestesia dental'),
                'placeholder' => __('Describa la reacción experimentada...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
            'fainting_or_dental_anxiety' => [
                'label' => __('¿Tiende a marearse, desmayarse o sufre de ansiedad dental severa?'),
                'short_label' => __('Ansiedad / Mareos'),
                'placeholder' => __('Especifique detalles...'),
                'is_critical' => false,
                'has_detail' => true,
            ],
        ];
    }

    /**
     * Get active critical alerts (affirmative responses where is_critical is true).
     *
     * @param  array<string, mixed>|null  $medicalData
     * @return array<int, array{key: string, label: string, full_label: string, detail: ?string, summary: string}>
     */
    public static function activeCriticalAlerts(?array $medicalData): array
    {
        return static::extractActiveQuestions($medicalData, criticalOnly: true);
    }

    /**
     * Get active non-critical anamnesis conditions (affirmative responses where is_critical is false).
     *
     * @param  array<string, mixed>|null  $medicalData
     * @return array<int, array{key: string, label: string, full_label: string, detail: ?string, summary: string}>
     */
    public static function activeConditions(?array $medicalData): array
    {
        return static::extractActiveQuestions($medicalData, criticalOnly: false);
    }

    /**
     * Helper to extract active questions from unified medical_data.
     *
     * @param  array<string, mixed>|null  $medicalData
     * @return array<int, array{key: string, label: string, full_label: string, detail: ?string, summary: string}>
     */
    public static function extractActiveQuestions(?array $medicalData, bool $criticalOnly = false): array
    {
        if (empty($medicalData)) {
            return [];
        }

        $catalog = static::questions();
        $active = [];

        foreach ($medicalData as $key => $entry) {
            $isPositive = false;
            $detail = null;

            if (is_array($entry)) {
                $isPositive = (bool) ($entry['value'] ?? false);
                $detail = ! empty($entry['detail']) ? (string) $entry['detail'] : null;
                $isCritical = isset($entry['is_critical'])
                    ? (bool) $entry['is_critical']
                    : (bool) ($catalog[$key]['is_critical'] ?? false);
            } elseif (is_bool($entry)) {
                $isPositive = $entry;
                $isCritical = (bool) ($catalog[$key]['is_critical'] ?? false);
            } elseif (is_string($entry) && ! empty($entry)) {
                $isPositive = true;
                $detail = $entry;
                $isCritical = (bool) ($catalog[$key]['is_critical'] ?? false);
            } else {
                continue;
            }

            if (! $isPositive) {
                continue;
            }

            if ($criticalOnly && ! $isCritical) {
                continue;
            }

            if (! $criticalOnly && $isCritical) {
                continue;
            }

            $questionInfo = $catalog[$key] ?? null;
            $label = $questionInfo['short_label'] ?? (is_string($key) ? ucfirst(str_replace('_', ' ', $key)) : (string) $key);
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

        return $active;
    }
}
