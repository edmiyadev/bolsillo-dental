<x-filament-widgets::widget>
    @assets
        @vite(['resources/css/calendar.css', 'resources/js/app.js'])
    @endassets

    <x-filament::section>
        <div
            x-data="appointmentCalendar({
                canCreate: @js($this->canCreate),
                createUrl: @js($this->createUrl),
            })"
            @calendar-filters-updated.window="refreshEvents()"
        >
            <div  style="margin-bottom: 2.25rem;">
                {{ $this->form }}
            </div>

            <div class="appointment-calendar-legend">
                @foreach ($this->statusLegend as $item)
                    <div class="appointment-calendar-legend-item">
                        <span
                            class="appointment-calendar-legend-dot"
                            style="background-color: {{ $item['color'] }};"
                        ></span>
                        <span>{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div wire:ignore x-ref="calendar" style="min-height: 680px;"></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
