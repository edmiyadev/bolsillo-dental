<?php

namespace App\Actions\Tenants;

use App\Enums\DayOfWeek;
use App\Models\Branch;
use App\Models\Clinic;

class ProvisionTenantMainBranchAction
{
    public function handle(Clinic $clinic, ?string $phone = null, ?string $address = null): Branch
    {
        return $clinic->branches()->firstOrCreate(
            ['is_main' => true],
            [
                'name' => 'Sede Principal',
                'code' => 'MAIN',
                'phone' => $phone ?? $clinic->phone,
                'address' => $address ?? $clinic->address,
                'opening_hours' => DayOfWeek::defaultSchedule(),
                'is_active' => true,
            ],
        );
    }
}
