<?php

namespace App\Actions\Appointments;

use App\Data\Appointments\CreateAppointmentData;
use App\Models\Appointment;

class CreateAppointmentAction
{
    public function __construct(
        protected UpsertAppointmentAction $upsertAppointment,
    ) {}

    /**
     * @param  CreateAppointmentData|array<string, mixed>  $data
     */
    public function handle(CreateAppointmentData|array $data): Appointment
    {
        $dto = $data instanceof CreateAppointmentData
            ? $data
            : CreateAppointmentData::fromArray($data);

        return $this->upsertAppointment->handle($dto);
    }
}
