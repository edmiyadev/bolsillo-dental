<?php

namespace App\Support;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Support\Facades\File;

class ModelHelper
{
    /**
     * Get list of model class names from app/Models directory excluding specified models.
     *
     * @param  array<string>  $excludedModels
     * @return array<string>
     */
    public static function getModelsWithExclusions(array $excludedModels = []): array
    {
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return [];
        }

        $defaultExclusions = ['Client', 'Tenant', 'Domain', 'BranchUser', 'OdontogramEntry', 'OdontogramSnapshot'];
        $allExclusions = array_unique(array_merge($defaultExclusions, $excludedModels));

        $modelFiles = File::files($modelPath);
        $models = [];

        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getFilenameWithoutExtension();

            if (! in_array($modelName, $allExclusions, true)) {
                $models[] = $modelName;
            }
        }

        return $models;
    }

    /**
     * Check if a model implements the BelongsToUser concern (has own record scope).
     */
    public static function hasOwnRecordScope(string $modelName): bool
    {
        $fqcn = "App\\Models\\{$modelName}";

        if (! class_exists($fqcn)) {
            return false;
        }

        return in_array(BelongsToUser::class, class_uses_recursive($fqcn), true);
    }
}
