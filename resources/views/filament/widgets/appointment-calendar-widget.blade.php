<x-filament-widgets::widget>
    @assets
        @vite(['resources/css/calendar.css', 'resources/js/app.js'])
    @endassets

    <x-filament::section>
        <div
            x-data="appointmentCalendar({
                canCreate: @js($this->canCreate),
                canUpdate: @js($this->canUpdate),
                pastDateError: @js(__('Cannot schedule appointments in the past.')),
            })"
            @calendar-filters-updated.window="refreshEvents()"
            @modal-closed.window="handleModalClosed($event)"
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

            <x-filament::modal
                id="confirm-reschedule-appointment"
                icon="heroicon-o-clock"
                icon-color="primary"
                width="md"
            >
                <x-slot name="heading">
                    {{ __('Confirm appointment reschedule') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('Are you sure you want to reschedule this appointment?') }}
                </x-slot>

                <div class="mt-2 space-y-3 text-sm">
                    <template x-if="rescheduleDialog.patientName">
                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Patient') }}:</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="rescheduleDialog.patientName"></span>
                        </div>
                    </template>

                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3.5 bg-gray-50/75 dark:bg-gray-900/60 space-y-2.5">
                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-0.5">
                                {{ __('Current date and time') }}
                            </div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                <x-filament::icon icon="heroicon-m-calendar" class="h-4 w-4 text-gray-400" />
                                <span x-text="rescheduleDialog.oldDateFormatted"></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 my-1 text-primary-600 dark:text-primary-400">
                            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></div>
                            <x-filament::icon icon="heroicon-m-arrow-down" class="h-4 w-4 shrink-0" />
                            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></div>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-primary-600 dark:text-primary-400 uppercase tracking-wider mb-0.5">
                                {{ __('New date and time') }}
                            </div>
                            <div class="text-sm font-bold text-primary-700 dark:text-primary-300 flex items-center gap-1.5">
                                <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4 text-primary-500" />
                                <span x-text="rescheduleDialog.newDateFormatted"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="flex items-center justify-end gap-x-3">
                        <x-filament::button
                            color="gray"
                            x-on:click="cancelReschedule()"
                        >
                            {{ __('Cancel') }}
                        </x-filament::button>

                        <x-filament::button
                            color="primary"
                            x-on:click="confirmReschedule()"
                            x-bind:disabled="rescheduleDialog.isSubmitting"
                        >
                            <span x-show="!rescheduleDialog.isSubmitting">{{ __('Confirm change') }}</span>
                            <span x-show="rescheduleDialog.isSubmitting" style="display: none;">{{ __('Updating...') }}</span>
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::modal>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
