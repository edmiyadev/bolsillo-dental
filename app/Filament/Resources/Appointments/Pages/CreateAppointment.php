<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Data\Appointments\CreateAppointmentData;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAppointmentAction::class)->handle(
            CreateAppointmentData::fromArray($data)
        );
    }
}

