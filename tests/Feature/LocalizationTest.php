<?php

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use App\Filament\Resources\Services\ServiceResource;

it('uses spanish as the application locale', function () {
    expect(app()->getLocale())->toBe('es');
});

it('translates CommissionType enum cases to spanish', function () {
    expect(CommissionType::Fixed->getLabel())->toBe('Monto fijo')
        ->and(CommissionType::Percentage->getLabel())->toBe('Porcentaje (%)');
});

it('translates CommissionStatus enum cases to spanish', function () {
    expect(CommissionStatus::Pending->getLabel())->toBe('Pendiente')
        ->and(CommissionStatus::Paid->getLabel())->toBe('Pagado')
        ->and(CommissionStatus::Cancelled->getLabel())->toBe('Cancelado');
});

it('translates ServiceResource navigation and model labels to spanish', function () {
    expect(ServiceResource::getModelLabel())->toBe('Servicio')
        ->and(ServiceResource::getPluralModelLabel())->toBe('Servicios')
        ->and(ServiceResource::getNavigationLabel())->toBe('Servicios');
});

it('translates CommissionRecordResource navigation and model labels to spanish', function () {
    expect(CommissionRecordResource::getModelLabel())->toBe('Registro de comisión')
        ->and(CommissionRecordResource::getPluralModelLabel())->toBe('Registros de comisión')
        ->and(CommissionRecordResource::getNavigationLabel())->toBe('Registros de comisión');
});

it('translates common UI labels and notifications to spanish', function () {
    expect(__('Procedure & Service'))->toBe('Procedimiento y Servicio')
        ->and(__('Financial Details'))->toBe('Detalles Financieros')
        ->and(__('Mark as paid'))->toBe('Marcar como pagado')
        ->and(__('Commission record marked as paid'))->toBe('Registro de comisión marcado como pagado')
        ->and(__(':count commission records marked as paid', ['count' => 3]))->toBe('3 registros de comisión marcados como pagados');
});
