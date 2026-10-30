<?php

namespace App\Rules;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Cubicle;
use App\Models\User;
use App\ValueObjects\WeeklySchedule;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

class AppointmentAvailabilityRule implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(public ?int $ignoreAppointmentId = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $state = isset($this->data['data']) && is_array($this->data['data'])
            ? array_merge($this->data, $this->data['data'])
            : $this->data;

        $rawStartsAt = $value ?? ($state['starts_at'] ?? null);

        if (blank($rawStartsAt)) {
            return;
        }

        try {
            $startsAt = Carbon::parse($rawStartsAt);
        } catch (Throwable) {
            $fail(__('The appointment start time is invalid.'));

            return;
        }

        $duration = (int) ($state['duration_minutes'] ?? 0);

        if ($duration <= 0) {
            $fail(__('The duration must be greater than zero.'));

            return;
        }

        $endsAt = $startsAt->copy()->addMinutes($duration);

        if (! $startsAt->isSameDay($endsAt)) {
            $fail(__('The appointment must start and end on the same day.'));

            return;
        }

        $dayOfWeek = DayOfWeek::from(strtolower($startsAt->englishDayOfWeek));
        $startTime = $startsAt->format('H:i');
        $endTime = $endsAt->format('H:i');

        $specialty = $state['specialty'] ?? DentalSpecialty::General->value;
        $specialtyValue = $specialty instanceof DentalSpecialty ? $specialty->value : (string) $specialty;

        $activeStatuses = collect(AppointmentStatus::cases())
            ->filter(fn (AppointmentStatus $status): bool => $status->occupiesTimeSlot())
            ->map(fn (AppointmentStatus $status): string => $status->value)
            ->values()
            ->all();

        $branchId = $state['branch_id'] ?? null;

        if (blank($branchId)) {
            return;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch || ! $branch->is_active) {
            $fail(__('The selected branch is not active.'));

            return;
        }

        $branchSchedule = WeeklySchedule::from($branch->opening_hours ?? DayOfWeek::defaultSchedule());
        $branchInterval = $branchSchedule->intervalFor($dayOfWeek);

        if (! $branchInterval || $startTime < $branchInterval['open_time'] || $endTime > $branchInterval['close_time']) {
            $fail(__('The appointment is outside the branch opening hours.'));

            return;
        }

        $dentistId = $state['dentist_id'] ?? null;

        if (filled($dentistId)) {
            $dentist = User::query()->find($dentistId);

            if (! $dentist || ! $dentist->supportsSpecialty($specialtyValue)) {
                $fail(__('The selected dentist does not support the required specialty.'));

                return;
            }

            $branchUser = BranchUser::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $dentist->id)
                ->where('is_active', true)
                ->first();

            $dentistInterval = $branchUser
                ? WeeklySchedule::from($branchUser->schedule)->intervalFor($dayOfWeek)
                : null;

            if (! $dentistInterval || $startTime < $dentistInterval['open_time'] || $endTime > $dentistInterval['close_time']) {
                $fail(__('The selected dentist is not on shift at this branch during the requested time.'));

                return;
            }

            $dentistOverlap = Appointment::query()
                ->where('dentist_id', $dentist->id)
                ->whereIn('status', $activeStatuses)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($this->ignoreAppointmentId, fn ($query) => $query->whereKeyNot($this->ignoreAppointmentId))
                ->exists();

            if ($dentistOverlap) {
                $fail(__('The selected dentist already has an overlapping appointment in this time slot.'));

                return;
            }
        }

        $cubicleId = $state['cubicle_id'] ?? null;

        if (filled($cubicleId)) {
            $cubicle = Cubicle::query()
                ->where('branch_id', $branch->id)
                ->find($cubicleId);

            if (! $cubicle || ! $cubicle->isAvailableForSpecialtyAt($specialtyValue, $dayOfWeek, $startTime, $endTime)) {
                $fail(__('The selected cubicle is not available for the required specialty during this time slot.'));

                return;
            }

            $cubicleOverlap = Appointment::query()
                ->where('cubicle_id', $cubicle->id)
                ->whereIn('status', $activeStatuses)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($this->ignoreAppointmentId, fn ($query) => $query->whereKeyNot($this->ignoreAppointmentId))
                ->exists();

            if ($cubicleOverlap) {
                $fail(__('The selected cubicle is already occupied during this time slot.'));

                return;
            }
        }
    }
}
