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
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Branch') }}
                    </label>
                    <select
                        x-model="branchId"
                        @change="refreshEvents()"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs focus:border-primary-500 focus:outline-hidden focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="" class="bg-white text-gray-900 dark:bg-gray-800 dark:text-white">{{ __('Show All') }}</option>
                        @foreach ($this->branches as $id => $name)
                            <option value="{{ $id }}" class="bg-white text-gray-900 dark:bg-gray-800 dark:text-white">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Dentist') }}
                    </label>
                    <select
                        x-model="dentistId"
                        @change="refreshEvents()"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs focus:border-primary-500 focus:outline-hidden focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="" class="bg-white text-gray-900 dark:bg-gray-800 dark:text-white">{{ __('Show All') }}</option>
                        @foreach ($this->dentists as $id => $name)
                            <option value="{{ $id }}" class="bg-white text-gray-900 dark:bg-gray-800 dark:text-white">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Status') }}
                    </label>
                    <select
                        x-model="status"
                        @change="refreshEvents()"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs focus:border-primary-500 focus:outline-hidden focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="" class="bg-white text-gray-900 dark:bg-gray-800 dark:text-white">{{ __('Show All') }}</option>
                        @foreach ($this->statuses as $value => $label)
                            <option value="{{ $value }}" class="bg-white text-gray-900 dark:bg-gray-800 dark:text-white">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
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
