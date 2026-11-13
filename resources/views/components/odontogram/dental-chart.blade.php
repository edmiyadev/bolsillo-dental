@props([
    'projection',
    'canUpdate' => false,
])

@php
    $permanent = \App\Support\OdontogramGeometryCatalog::permanentQuadrants();
    $primary = \App\Support\OdontogramGeometryCatalog::primaryQuadrants();
    $teeth = $projection['teeth'];
    $bridges = collect($projection['bridges']);
@endphp

<div class="od-chart-card">
    {{-- PERMANENT DENTITION TAB --}}
    <div x-show="dentitionTab === 'permanent'" class="od-chart-inner od-chart-inner--permanent">
        <div class="od-side-labels">
            <span>{{ __('Patient Right Side (Q1 / Q4)') }}</span>
            <span>{{ __('Midline') }}</span>
            <span>{{ __('Patient Left Side (Q2 / Q3)') }}</span>
        </div>

        {{-- Upper Permanent Bridge Brackets --}}
        @php
            $upperPermOrder = array_merge($permanent['upper_right'], $permanent['upper_left']);
            $upperPermBridges = $bridges->where('isUpper', true)->where('isPrimary', false)->values();
        @endphp
        @if ($upperPermBridges->isNotEmpty())
            <div class="od-bridge-stack">
                @foreach ($upperPermBridges as $bridge)
                    @php
                        $idxA = array_search($bridge['fromTooth'], $upperPermOrder, true);
                        $idxB = array_search($bridge['toTooth'], $upperPermOrder, true);
                    @endphp
                    @if ($idxA !== false && $idxB !== false)
                        @php
                            $startIdx = min($idxA, $idxB);
                            $endIdx = max($idxA, $idxB);
                            $step = 48;
                            $gapOffset = ($startIdx >= 8 ? 20 : 0);
                            $endGapOffset = ($endIdx >= 8 ? 20 : 0);
                            $x1 = ($startIdx * $step) + $gapOffset + 22;
                            $x2 = ($endIdx * $step) + $endGapOffset + 22;
                        @endphp
                        <svg width="784" height="16" class="od-bridge-svg">
                            <polyline points="{{ $x1 }},15 {{ $x1 }},3 {{ $x2 }},3 {{ $x2 }},15" fill="none" stroke="{{ $bridge['color'] }}" stroke-width="2.5" />
                        </svg>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Upper Permanent Row: Q1 (18..11) | Q2 (21..28) --}}
        <div class="od-row-upper">
            <div class="od-quadrant">
                @foreach ($permanent['upper_right'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="false" />
                        </button>
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :can-update="$canUpdate" />
                    </div>
                @endforeach
            </div>

            <div class="od-midline">
                <div class="od-midline-line"></div>
            </div>

            <div class="od-quadrant">
                @foreach ($permanent['upper_left'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="false" />
                        </button>
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :can-update="$canUpdate" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="od-occlusal-line"></div>

        {{-- Lower Permanent Row: Q4 (48..41) | Q3 (31..38) --}}
        <div class="od-row-lower">
            <div class="od-quadrant">
                @foreach ($permanent['lower_right'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :can-update="$canUpdate" />
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="true" />
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="od-midline">
                <div class="od-midline-line"></div>
            </div>

            <div class="od-quadrant">
                @foreach ($permanent['lower_left'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :can-update="$canUpdate" />
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="true" />
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lower Permanent Bridge Brackets --}}
        @php
            $lowerPermOrder = array_merge($permanent['lower_right'], $permanent['lower_left']);
            $lowerPermBridges = $bridges->where('isUpper', false)->where('isPrimary', false)->values();
        @endphp
        @if ($lowerPermBridges->isNotEmpty())
            <div class="od-bridge-stack">
                @foreach ($lowerPermBridges as $bridge)
                    @php
                        $idxA = array_search($bridge['fromTooth'], $lowerPermOrder, true);
                        $idxB = array_search($bridge['toTooth'], $lowerPermOrder, true);
                    @endphp
                    @if ($idxA !== false && $idxB !== false)
                        @php
                            $startIdx = min($idxA, $idxB);
                            $endIdx = max($idxA, $idxB);
                            $step = 48;
                            $gapOffset = ($startIdx >= 8 ? 20 : 0);
                            $endGapOffset = ($endIdx >= 8 ? 20 : 0);
                            $x1 = ($startIdx * $step) + $gapOffset + 22;
                            $x2 = ($endIdx * $step) + $endGapOffset + 22;
                        @endphp
                        <svg width="784" height="16" class="od-bridge-svg">
                            <polyline points="{{ $x1 }},1 {{ $x1 }},13 {{ $x2 }},13 {{ $x2 }},1" fill="none" stroke="{{ $bridge['color'] }}" stroke-width="2.5" />
                        </svg>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- PRIMARY / PEDIATRIC DENTITION TAB --}}
    <div x-show="dentitionTab === 'primary'" x-cloak class="od-chart-inner od-chart-inner--primary">
        <div class="od-side-labels">
            <span>{{ __('Patient Right Side (Q5 / Q8)') }}</span>
            <span>{{ __('Midline') }}</span>
            <span>{{ __('Patient Left Side (Q6 / Q7)') }}</span>
        </div>

        {{-- Upper Primary Bridge Brackets --}}
        @php
            $upperPrimOrder = array_merge($primary['upper_right'], $primary['upper_left']);
            $upperPrimBridges = $bridges->where('isUpper', true)->where('isPrimary', true)->values();
        @endphp
        @if ($upperPrimBridges->isNotEmpty())
            <div class="od-bridge-stack">
                @foreach ($upperPrimBridges as $bridge)
                    @php
                        $idxA = array_search($bridge['fromTooth'], $upperPrimOrder, true);
                        $idxB = array_search($bridge['toTooth'], $upperPrimOrder, true);
                    @endphp
                    @if ($idxA !== false && $idxB !== false)
                        @php
                            $startIdx = min($idxA, $idxB);
                            $endIdx = max($idxA, $idxB);
                            $step = 48;
                            $gapOffset = ($startIdx >= 5 ? 20 : 0);
                            $endGapOffset = ($endIdx >= 5 ? 20 : 0);
                            $x1 = ($startIdx * $step) + $gapOffset + 22;
                            $x2 = ($endIdx * $step) + $endGapOffset + 22;
                        @endphp
                        <svg width="496" height="16" class="od-bridge-svg">
                            <polyline points="{{ $x1 }},15 {{ $x1 }},3 {{ $x2 }},3 {{ $x2 }},15" fill="none" stroke="{{ $bridge['color'] }}" stroke-width="2.5" />
                        </svg>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Upper Primary Row: Q5 (55..51) | Q6 (61..65) --}}
        <div class="od-row-upper">
            <div class="od-quadrant">
                @foreach ($primary['upper_right'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="false" :compact="true" />
                        </button>
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :compact="true" :can-update="$canUpdate" />
                    </div>
                @endforeach
            </div>

            <div class="od-midline">
                <div class="od-midline-line"></div>
            </div>

            <div class="od-quadrant">
                @foreach ($primary['upper_left'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="false" :compact="true" />
                        </button>
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :compact="true" :can-update="$canUpdate" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="od-occlusal-line"></div>

        {{-- Lower Primary Row: Q8 (85..81) | Q7 (71..75) --}}
        <div class="od-row-lower">
            <div class="od-quadrant">
                @foreach ($primary['lower_right'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :compact="true" :can-update="$canUpdate" />
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="true" :compact="true" />
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="od-midline">
                <div class="od-midline-line"></div>
            </div>

            <div class="od-quadrant">
                @foreach ($primary['lower_left'] as $tooth)
                    @php $state = $teeth[$tooth]; @endphp
                    <div
                        class="od-tooth-slot"
                        @if ($canUpdate) :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''" @endif
                    >
                        <x-odontogram.surface-box :tooth="$tooth" :state="$state" :compact="true" :can-update="$canUpdate" />
                        <span class="od-tooth-number">{{ $tooth }}</span>
                        <button
                            type="button"
                            @if ($canUpdate) x-on:click="handleToothClick({{ $tooth }})" @endif
                            class="od-tooth-btn {{ $canUpdate ? '' : 'is-readonly' }}"
                            title="{{ __('Tooth') }} {{ $tooth }}"
                        >
                            <x-odontogram.tooth-svg :tooth="$tooth" :state="$state" :inverted="true" :compact="true" />
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lower Primary Bridge Brackets --}}
        @php
            $lowerPrimOrder = array_merge($primary['lower_right'], $primary['lower_left']);
            $lowerPrimBridges = $bridges->where('isUpper', false)->where('isPrimary', true)->values();
        @endphp
        @if ($lowerPrimBridges->isNotEmpty())
            <div class="od-bridge-stack">
                @foreach ($lowerPrimBridges as $bridge)
                    @php
                        $idxA = array_search($bridge['fromTooth'], $lowerPrimOrder, true);
                        $idxB = array_search($bridge['toTooth'], $lowerPrimOrder, true);
                    @endphp
                    @if ($idxA !== false && $idxB !== false)
                        @php
                            $startIdx = min($idxA, $idxB);
                            $endIdx = max($idxA, $idxB);
                            $step = 48;
                            $gapOffset = ($startIdx >= 5 ? 20 : 0);
                            $endGapOffset = ($endIdx >= 5 ? 20 : 0);
                            $x1 = ($startIdx * $step) + $gapOffset + 22;
                            $x2 = ($endIdx * $step) + $endGapOffset + 22;
                        @endphp
                        <svg width="496" height="16" class="od-bridge-svg">
                            <polyline points="{{ $x1 }},1 {{ $x1 }},13 {{ $x2 }},13 {{ $x2 }},1" fill="none" stroke="{{ $bridge['color'] }}" stroke-width="2.5" />
                        </svg>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
