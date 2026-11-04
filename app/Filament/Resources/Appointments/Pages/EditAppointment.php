<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Actions\Appointments\UpdateAppointmentAction;
use App\Data\Appointments\UpdateAppointmentData;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  Appointment  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Appointment $record */
        return app(UpdateAppointmentAction::class)->handle(
            $record,
            UpdateAppointmentData::fromArray($data, $record),
        );
    }
}
