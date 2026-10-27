<?php

namespace App\Support;

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

        $defaultExclusions = ['Client', 'Tenant', 'Domain'];
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
}
