<?php

namespace App\Actions\Services;

use App\Enums\CommissionType;
use App\Models\User;

class CreateDefaultServicesForUserAction
{
    /**
     * @return array<int, array{name: string, default_price: ?float, default_commission: float, commission_type: CommissionType}>
     */
    public function defaultServices(): array
    {
        return [
            [
                'name' => 'Asistencia general',
                'default_price' => null,
                'default_commission' => 250.00,
                'commission_type' => CommissionType::Fixed,
            ],
            [
                'name' => 'Limpieza bucal (Profilaxis)',
                'default_price' => 1500.00,
                'default_commission' => 20.00,
                'commission_type' => CommissionType::Percentage,
            ],
            [
                'name' => 'Blanqueamiento dental (Asistencia)',
                'default_price' => 5000.00,
                'default_commission' => 500.00,
                'commission_type' => CommissionType::Fixed,
            ],
            [
                'name' => 'Aplicación de flúor / Sellantes',
                'default_price' => 800.00,
                'default_commission' => 25.00,
                'commission_type' => CommissionType::Percentage,
            ],
        ];
    }

    public function handle(User $user): void
    {
        if ($user->services()->withoutGlobalScopes()->exists()) {
            return;
        }

        foreach ($this->defaultServices() as $serviceData) {
            $user->services()->create($serviceData);
        }
    }
}
