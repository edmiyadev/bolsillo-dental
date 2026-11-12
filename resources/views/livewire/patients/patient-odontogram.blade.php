@php
    $permanent = \App\Support\OdontogramGeometryCatalog::permanentQuadrants();
    $primary = \App\Support\OdontogramGeometryCatalog::primaryQuadrants();
    $projection = $this->projection;
    $teeth = $projection['teeth'];
    $bridges = collect($projection['bridges']);
    $archEntries = $projection['archEntries'];
    $canUpdate = $this->canUpdatePatient();
@endphp

@assets
    @vite(['resources/css/odontogram.css'])
@endassets

<div
    x-data="{
        dentitionTab: @entangle('dentitionTab'),
        timelineMode: @entangle('timelineMode'),
        interactionMode: @entangle('interactionMode'),
        quickCondition: @entangle('quickCondition'),
        selectedTooth: null,
        selectedSurfaces: [],
        bridgeMode: false,
        bridgeFromTooth: null,
        canUpdate: @js($canUpdate),

        isSurfaceSelected(tooth, surface) {
            return this.selectedTooth === tooth && this.selectedSurfaces.includes(surface);
        },

        isToothSelected(tooth) {
            return this.selectedTooth === tooth || this.bridgeFromTooth === tooth;
        },

        clearSelection() {
            this.selectedTooth = null;
            this.selectedSurfaces = [];
            this.bridgeFromTooth = null;
        },

        handlePolygonClick(tooth, surface, isBlocked) {
            if (!this.canUpdate) {
                return;
            }

            if (this.bridgeMode) {
                this.handleToothClick(tooth);
                return;
            }

            if (this.interactionMode === 'mode_a') {
                $wire.recordQuickFinding(tooth, surface, this.quickCondition);
                return;
            }

            if (isBlocked) {
                this.selectedTooth = tooth;
                this.selectedSurfaces = [];
                $wire.mountAction('registerTreatment', {
                    scope: 'tooth',
                    tooth_number: tooth,
                });
                return;
            }

            if (this.selectedTooth !== tooth) {
                this.selectedTooth = tooth;
                this.selectedSurfaces = [surface];
                return;
            }

            if (this.selectedSurfaces.includes(surface)) {
                this.selectedSurfaces = this.selectedSurfaces.filter((item) => item !== surface);
                if (this.selectedSurfaces.length === 0) {
                    this.selectedTooth = null;
                }
            } else {
                this.selectedSurfaces = [...this.selectedSurfaces, surface];
            }
        },

        handleToothClick(tooth) {
            if (!this.canUpdate) {
                return;
            }

            if (this.bridgeMode) {
                if (this.bridgeFromTooth === null) {
                    this.bridgeFromTooth = tooth;
                    return;
                }

                if (this.bridgeFromTooth === tooth) {
                    this.bridgeFromTooth = null;
                    return;
                }

                const fromTooth = this.bridgeFromTooth;
                this.bridgeFromTooth = null;
                this.bridgeMode = false;

                $wire.mountAction('registerTreatment', {
                    scope: 'range',
                    tooth_number: fromTooth,
                    bridge_to_tooth: tooth,
                });
                return;
            }

            if (this.interactionMode === 'mode_a') {
                $wire.recordQuickFinding(tooth, null, this.quickCondition);
                return;
            }

            this.selectedTooth = tooth;
            this.selectedSurfaces = [];
            $wire.mountAction('registerTreatment', {
                scope: 'tooth',
                tooth_number: tooth,
            });
        },

        openSurfaceTreatmentModal() {
            if (!this.selectedTooth || this.selectedSurfaces.length === 0) {
                return;
            }

            $wire.mountAction('registerTreatment', {
                scope: 'surface',
                tooth_number: this.selectedTooth,
                surfaces: this.selectedSurfaces,
            });
        },

        openFullToothTreatmentModal() {
            if (!this.selectedTooth) {
                return;
            }

            $wire.mountAction('registerTreatment', {
                scope: 'tooth',
                tooth_number: this.selectedTooth,
            });
        },
    }"
    x-on:odontogram-selection-cleared.window="clearSelection()"
    class="od-root"
>
    {{-- Top Control Bar --}}
    <div class="od-card od-toolbar">
        {{-- Dentition Tabs: Permanent vs Primary --}}
        <div class="od-segmented">
            <button
                type="button"
                x-on:click="dentitionTab = 'permanent'; clearSelection()"
                :class="dentitionTab === 'permanent' ? 'is-active' : ''"
                class="od-seg-btn"
            >
                {{ __('Permanent (Adult - 32)') }}
            </button>
            <button
                type="button"
                x-on:click="dentitionTab = 'primary'; clearSelection()"
                :class="dentitionTab === 'primary' ? 'is-active' : ''"
                class="od-seg-btn"
            >
                {{ __('Primary (Pediatric - 20)') }}
            </button>
        </div>

        {{-- Timeline Switcher --}}
        <div class="od-segmented">
            <button
                type="button"
                wire:click="$set('timelineMode', 'current')"
                class="od-seg-btn {{ $timelineMode === 'current' ? 'is-timeline-active' : '' }}"
            >
                {{ __('Current Odontogram (Evolutionary)') }}
            </button>
            <button
                type="button"
                wire:click="$set('timelineMode', 'initial')"
                class="od-seg-btn {{ $timelineMode === 'initial' ? 'is-timeline-active' : '' }}"
            >
                {{ __('Initial Odontogram (Base Diagnosis)') }}
            </button>
        </div>

        {{-- Full Mouth / Arch Procedure Button --}}
        <div>
            {{ $this->registerMouthProcedureAction }}
        </div>
    </div>

    {{-- Mode A / Mode B Toolbar & Legend --}}
    @if ($canUpdate)
        <div class="od-card od-toolbar">
            <div class="od-mode-group">
                <button
                    type="button"
                    x-on:click="interactionMode = 'mode_b'; bridgeMode = false; clearSelection()"
                    :class="interactionMode === 'mode_b' && !bridgeMode ? 'is-active-primary' : ''"
                    class="od-mode-btn"
                >
                    {{ __('Mode B: Plan / Complete Treatment') }}
                </button>

                <button
                    type="button"
                    x-on:click="interactionMode = 'mode_a'; bridgeMode = false; clearSelection()"
                    :class="interactionMode === 'mode_a' ? 'is-active-danger' : ''"
                    class="od-mode-btn"
                >
                    {{ __('Mode A: Quick 1-Click Diagnosis') }}
                </button>

                <button
                    type="button"
                    x-on:click="interactionMode = 'mode_b'; bridgeMode = !bridgeMode; bridgeFromTooth = null; selectedTooth = null; selectedSurfaces = []"
                    :class="bridgeMode ? 'is-active-info' : ''"
                    class="od-mode-btn"
                >
                    {{ __('Mark Fixed Bridge') }}
                </button>
            </div>

            {{-- Quick Condition Palette in Mode A --}}
            <div x-show="interactionMode === 'mode_a'" x-cloak class="od-quick-palette">
                @foreach ($this->quickConditions as $key => $item)
                    <button
                        type="button"
                        x-on:click="quickCondition = '{{ $key }}'"
                        :class="quickCondition === '{{ $key }}' ? 'is-selected' : ''"
                        class="od-quick-pill {{ $item['status'] === \App\Enums\OdontogramStatus::Finding ? 'is-finding' : 'is-preexisting' }}"
                    >
                        {{ $item['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Bridge Helper Banner --}}
            <div x-show="bridgeMode" x-cloak class="od-bridge-banner">
                <span x-show="bridgeFromTooth === null">{{ __('Click the starting abutment tooth of the bridge...') }}</span>
                <span x-show="bridgeFromTooth !== null">{{ __('Starting tooth:') }} <strong x-text="bridgeFromTooth"></strong>. {{ __('Now click the ending abutment tooth in the same arch.') }}</span>
            </div>

            {{-- Color Legend --}}
            <div class="od-legend">
                <span class="od-legend-item">
                    <span class="od-legend-dot is-red"></span>
                    {{ __('Finding / Planned') }}
                </span>
                <span class="od-legend-item">
                    <span class="od-legend-dot is-blue"></span>
                    {{ __('Completed') }}
                </span>
                <span class="od-legend-item">
                    <span class="od-legend-dot is-green"></span>
                    {{ __('Pre-existing') }}
                </span>
            </div>
        </div>
    @endif

    {{-- Multi-Surface Floating Selection Bar (Mode B) --}}
    <div
        x-show="interactionMode === 'mode_b' && !bridgeMode && selectedTooth !== null && selectedSurfaces.length > 0"
        x-cloak
        class="od-selection-bar"
    >
        <div class="od-selection-text">
            {{ __('Tooth') }} <strong x-text="selectedTooth"></strong> — {{ __('Selected surfaces:') }}
            <strong x-text="selectedSurfaces.join('-')"></strong>
        </div>

        <div class="od-selection-actions">
            <button
                type="button"
                x-on:click="openSurfaceTreatmentModal()"
                class="od-btn-primary"
            >
                {{ __('Register surface treatment') }}
            </button>
            <button
                type="button"
                x-on:click="openFullToothTreatmentModal()"
                class="od-btn-outline"
            >
                {{ __('Full tooth (Crown / Endo / Ext)') }}
            </button>
            <button
                type="button"
                x-on:click="clearSelection()"
                class="od-btn-ghost"
            >
                {{ __('Clear') }}
            </button>
        </div>
    </div>

    {{-- Active Arch / Full Mouth Badges --}}
    @if ($archEntries->isNotEmpty())
        <div class="od-arch-badges">
            <span class="od-arch-badges-label">{{ __('General / Arch procedures:') }}</span>
            @foreach ($archEntries as $archEntry)
                <span
                    class="od-arch-badge"
                    style="background-color: {{ $archEntry->status->hexColor() }}20; color: {{ $archEntry->status->hexColor() }};"
                >
                    <span>{{ $archEntry->condition_code->getLabel() }}</span>
                    <span>({{ $archEntry->arch?->getLabel() }})</span>
                    <span>• {{ $archEntry->status->getLabel() }}</span>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Dental Matrix Card --}}
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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
                            :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''"
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

    {{-- Evolutionary Clinical History Table --}}
    <div>
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
