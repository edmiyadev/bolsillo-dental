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
                width="md"
            >
                <x-slot name="heading">
                    {{ __('Confirm appointment reschedule') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('Are you sure you want to reschedule this appointment?') }}
                </x-slot>

                <div class="reschedule-modal-content">
                    <div
                        x-show="rescheduleDialog.patientName"
                        class="reschedule-modal-patient-row"
                        style="margin-bottom: 1.5rem !important; padding-bottom: 0.75rem;"
                    >
                        <span class="reschedule-modal-patient-label">{{ __('Patient') }}:</span>
                        <span class="reschedule-modal-patient-name" x-text="rescheduleDialog.patientName"></span>
                    </div>

                    <div class="reschedule-modal-card">
                        <div>
                            <div class="reschedule-modal-date-label">
                                {{ __('Current date and time') }}
                            </div>
                            <div class="reschedule-modal-date-row text-gray-700 dark:text-gray-300">
                                <svg class="reschedule-modal-icon text-gray-400" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 1rem; height: 1rem;">
                                    <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd"/>
                                </svg>
                                <span x-text="rescheduleDialog.oldDateFormatted"></span>
                            </div>
                        </div>

                        <div class="reschedule-modal-divider">
                            <div class="reschedule-modal-divider-line"></div>
                            <svg class="reschedule-modal-icon" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 1rem; height: 1rem;">
                                <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/>
                            </svg>
                            <div class="reschedule-modal-divider-line"></div>
                        </div>

                        <div>
                            <div class="reschedule-modal-date-label reschedule-modal-date-label-new">
                                {{ __('New date and time') }}
                            </div>
                            <div class="reschedule-modal-date-row reschedule-modal-date-value-new">
                                <svg class="reschedule-modal-icon text-primary-600 dark:text-primary-400" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 1rem; height: 1rem;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/>
                                </svg>
                                <span x-text="rescheduleDialog.newDateFormatted"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="reschedule-modal-actions" style="display: flex; align-items: center; justify-content: flex-end; gap: 1.5rem !important; padding-top: 0.5rem; width: 100%;">
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
