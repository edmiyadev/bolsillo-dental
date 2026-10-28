<?php

namespace App\ValueObjects;

use App\Enums\DayOfWeek;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

class WeeklySchedule implements Arrayable, Castable, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    /**
     * @param  array<int, array{day: string, is_open?: bool, open_time?: ?string, close_time?: ?string}>  $days
     */
    public function __construct(public array $days = []) {}

    public static function from(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return new self(is_array($decoded) ? $decoded : []);
        }

        if (is_array($data)) {
            return new self($data);
        }

        return new self([]);
    }

    /**
     * @return array<int, array{day: string, is_open?: bool, open_time?: ?string, close_time?: ?string}>
     */
    public function all(): array
    {
        return $this->days;
    }

    /**
     * @return array<int, array{day: string, is_open?: bool, open_time?: ?string, close_time?: ?string}>
     */
    public function workingDays(): array
    {
        return collect($this->days)
            ->filter(fn (mixed $dayEntry): bool => is_array($dayEntry) && ! empty($dayEntry['is_open']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function workingDaysLabels(): array
    {
        return collect($this->workingDays())
            ->map(function (array $dayEntry): string {
                $dayKey = (string) ($dayEntry['day'] ?? '');

                return DayOfWeek::tryFrom($dayKey)?->getLabel() ?? $dayKey;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{day_label: string, open_time: string, close_time: string}>
     */
    public function workingScheduleRows(): array
    {
        return collect($this->workingDays())
            ->map(function (array $dayEntry): array {
                $dayKey = (string) ($dayEntry['day'] ?? '');

                return [
                    'day_label' => DayOfWeek::tryFrom($dayKey)?->getLabel() ?? $dayKey,
                    'open_time' => substr((string) ($dayEntry['open_time'] ?? '-'), 0, 5),
                    'close_time' => substr((string) ($dayEntry['close_time'] ?? '-'), 0, 5),
                ];
            })
            ->values()
            ->all();
    }

    public function daySchedule(string|DayOfWeek $day): ?array
    {
        $dayKey = $day instanceof DayOfWeek ? $day->value : $day;

        return collect($this->days)->first(fn (mixed $entry): bool => is_array($entry) && ($entry['day'] ?? null) === $dayKey);
    }

    public function isOpenOn(string|DayOfWeek $day): bool
    {
        $daySchedule = $this->daySchedule($day);

        return is_array($daySchedule) && ! empty($daySchedule['is_open']);
    }

    /**
     * @return array{open_time: string, close_time: string}|null
     */
    public function intervalFor(string|DayOfWeek $day): ?array
    {
        $daySchedule = $this->daySchedule($day);

        if (! is_array($daySchedule)) {
            return null;
        }

        return [
            'open_time' => substr((string) ($daySchedule['open_time'] ?? ''), 0, 5),
            'close_time' => substr((string) ($daySchedule['close_time'] ?? ''), 0, 5),
        ];
    }

    public function toArray(): array
    {
        return $this->days;
    }

    public function jsonSerialize(): array
    {
        return $this->days;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function count(): int
    {
        return count($this->days);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->days);
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ?WeeklySchedule
            {
                if (is_null($value)) {
                    return null;
                }

                return WeeklySchedule::from($value);
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if (is_null($value)) {
                    return null;
                }

                if ($value instanceof WeeklySchedule) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode($value);
                }

                return (string) $value;
            }
        };
    }
}
