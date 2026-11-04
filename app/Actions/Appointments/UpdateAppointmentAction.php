<?php

namespace App\Actions\Appointments;

use App\Data\Appointments\UpdateAppointmentData;
use App\Models\Appointment;

class UpdateAppointmentAction
{
    public function __construct(
        protected UpsertAppointmentAction $upsertAppointment,
    ) {}

    /**
     * @param  UpdateAppointmentData|array<string, mixed>  $data
     */
    public function handle(Appointment $appointment, UpdateAppointmentData|array $data): Appointment
    {
        $dto = $data instanceof UpdateAppointmentData
            ? $data
            : UpdateAppointmentData::fromArray($data, $appointment);

        return $this->upsertAppointment->handle($dto, $appointment);
    }
}
