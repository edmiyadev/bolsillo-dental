<x-filament-widgets::widget>
    @assets
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endassets

    <x-filament::section>
        <div
            x-data="appointmentCalendar({
                canCreate: @js($this->canCreate),
                createUrl: @js($this->createUrl),
            })"
            class="flex flex-col gap-6"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-filament-forms::field-wrapper :label="__('Branch')">
                    <x-filament::input.wrapper :prefix-icon="\Filament\Support\Icons\Heroicon::OutlinedBuildingOffice2">
                        <x-filament::input.select
                            x-model="branchId"
                            @change="refreshEvents()"
                        >
                            <option value="" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white">{{ __('Show All') }}</option>
                            @foreach ($this->branches as $id => $name)
                                <option value="{{ $id }}" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper :label="__('Dentist')">
                    <x-filament::input.wrapper :prefix-icon="\Filament\Support\Icons\Heroicon::OutlinedUser">
                        <x-filament::input.select
                            x-model="dentistId"
                            @change="refreshEvents()"
                        >
                            <option value="" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white">{{ __('Show All') }}</option>
                            @foreach ($this->dentists as $id => $name)
                                <option value="{{ $id }}" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper :label="__('Status')">
                    <x-filament::input.wrapper :prefix-icon="\Filament\Support\Icons\Heroicon::OutlinedClock">
                        <x-filament::input.select
                            x-model="status"
                            @change="refreshEvents()"
                        >
                            <option value="" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white">{{ __('Show All') }}</option>
                            @foreach ($this->statuses as $value => $label)
                                <option value="{{ $value }}" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
            </div>

            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
                @foreach ($this->statusLegend as $item)
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block h-3 w-3 rounded-full"
                            style="background-color: {{ $item['color'] }}"
                        ></span>
                        <span>{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div wire:ignore x-ref="calendar" class="min-h-[680px]"></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
