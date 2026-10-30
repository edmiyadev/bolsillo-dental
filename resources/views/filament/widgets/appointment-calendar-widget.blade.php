<x-filament-widgets::widget>
    @assets
        @vite(['resources/css/calendar.css', 'resources/js/app.js'])
    @endassets

    <x-filament::section>
        <div
            x-data="appointmentCalendar({
                canCreate: @js($this->canCreate),
                canUpdate: @js($this->canUpdate),
            })"
            @calendar-filters-updated.window="refreshEvents()"
        >
            <div class="appointment-calendar-toolbar">
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

                <div class="appointment-calendar-actions">
                    <x-filament::dropdown
                        :menu="false"
                        placement="bottom-start"
                        shift
                        width="sm"
                        class="appointment-calendar-dropdown-filter"
                    >
                        <x-slot name="trigger">
                            {{ $this->getFiltersTriggerAction() }}
                        </x-slot>

                        <div class="appointment-calendar-filter-content">
                            <h3 class="appointment-calendar-filter-title">
                                {{ __('filament-tables::table.filters.heading') }}
                            </h3>

                            {{ $this->filtersForm }}

                            <div class="appointment-calendar-filter-content-actions-ctn">
                                {{ $this->getFiltersApplyAction() }}
                                {{ $this->getFiltersResetAction() }}
                            </div>
                        </div>
                    </x-filament::dropdown>
                </div>
            </div>

            <div wire:ignore x-ref="calendar" class="appointment-calendar-view"></div>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
