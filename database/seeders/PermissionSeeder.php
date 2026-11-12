<?php

namespace Database\Seeders;

use App\Enums\PermissionOperationsEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use App\Support\ModelHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $models = ModelHelper::getModelsWithExclusions();

        foreach ($models as $modelName) {
            $module = Str::snake(Str::pluralStudly($modelName));

            foreach (PermissionOperationsEnum::cases() as $operation) {
                if ($operation === PermissionOperationsEnum::ViewAll && ! ModelHelper::hasOwnRecordScope($modelName)) {
                    continue;
                }

                Permission::findOrCreate("{$operation->value}_{$module}", 'web');
            }
        }

        $adminRole = Role::findOrCreate(RoleEnum::Admin->value, 'web');
        $adminRole->syncPermissions(Permission::all());

        $dentistRole = Role::findOrCreate(RoleEnum::Dentist->value, 'web');
        $dentistRole->syncPermissions([
            Permission::findOrCreate('view_patients', 'web'),
            Permission::findOrCreate('create_patients', 'web'),
            Permission::findOrCreate('update_patients', 'web'),
            Permission::findOrCreate('view_services', 'web'),
            Permission::findOrCreate('create_services', 'web'),
            Permission::findOrCreate('update_services', 'web'),
            Permission::findOrCreate('view_commission_records', 'web'),
            Permission::findOrCreate('create_commission_records', 'web'),
            Permission::findOrCreate('view_appointments', 'web'),
            Permission::findOrCreate('create_appointments', 'web'),
            Permission::findOrCreate('update_appointments', 'web'),
        ]);

        $receptionistRole = Role::findOrCreate(RoleEnum::Receptionist->value, 'web');
        $receptionistRole->syncPermissions([
            Permission::findOrCreate('view_patients', 'web'),
            Permission::findOrCreate('create_patients', 'web'),
            Permission::findOrCreate('update_patients', 'web'),
            Permission::findOrCreate('view_branches', 'web'),
            Permission::findOrCreate('view_cubicles', 'web'),
            Permission::findOrCreate('view_appointments', 'web'),
            Permission::findOrCreate('view_all_appointments', 'web'),
            Permission::findOrCreate('create_appointments', 'web'),
            Permission::findOrCreate('update_appointments', 'web'),
            Permission::findOrCreate('delete_appointments', 'web'),
        ]);

        $dentalAssistantRole = Role::findOrCreate(RoleEnum::DentalAssistant->value, 'web');
        $dentalAssistantRole->syncPermissions([
            Permission::findOrCreate('view_patients', 'web'),
            Permission::findOrCreate('view_services', 'web'),
            Permission::findOrCreate('view_all_services', 'web'),
            Permission::findOrCreate('view_cubicles', 'web'),
            Permission::findOrCreate('view_appointments', 'web'),
            Permission::findOrCreate('view_all_appointments', 'web'),
        ]);
    }
}
