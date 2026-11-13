@php
    $projection = $this->projection;
    $archEntries = $projection['archEntries'];
    $canUpdate = $this->canUpdatePatient();
    $snapshots = $this->snapshots;
    $selectedSnapshot = $this->selectedSnapshot;
    $snapshotProjection = $this->snapshotProjection;
    $hasInitialSnapshot = $this->hasInitialSnapshot;
    $hasPendingChanges = $this->hasPendingChanges();
    $isInitialSave = ! $hasInitialSnapshot || $saveAsNewInitial;
    $canSave = $isInitialSave || $hasPendingChanges;
@endphp

@assets
    @vite(['resources/css/odontogram.css'])
@endassets

<div
    x-data="{
        viewSubTab: @entangle('viewSubTab'),
        dentitionTab: @entangle('dentitionTab'),
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
        {{-- Sub-tabs: Current Odontogram vs History --}}
        <div class="od-segmented">
            <button
                type="button"
                x-on:click="viewSubTab = 'current'"
                :class="viewSubTab === 'current' ? 'is-timeline-active' : ''"
                class="od-seg-btn"
            >
                {{ __('Current Odontogram') }}
            </button>
            <button
                type="button"
                x-on:click="viewSubTab = 'history'; clearSelection()"
                :class="viewSubTab === 'history' ? 'is-timeline-active' : ''"
                class="od-seg-btn"
            >
                {{ __('History') }}
                <span class="od-tab-count">{{ $snapshots->count() }}</span>
            </button>
        </div>

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

        {{-- Top Actions in Edit Mode --}}
        @if ($canUpdate)
            <div x-show="viewSubTab === 'current'" class="od-top-actions">
                {{ $this->registerMouthProcedureAction }}
                <button
                    type="button"
                    wire:click="clearCanvas"
                    wire:confirm="{{ __('Are you sure you want to clear the entire odontogram canvas in the draft? Prior active records will be archived when you save.') }}"
                    class="od-btn-danger-outline"
                >
                    {{ __('Clear entire canvas') }}
                </button>
            </div>
        @endif
    </div>

    {{-- SUB-TAB 1: CURRENT ODONTOGRAM --}}
    <div x-show="viewSubTab === 'current'" class="od-root">
        {{-- Mode A / Mode B Toolbar & Legend (Edit mode only) --}}
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

            {{-- Dedicated Odontogram Save & Draft Bar --}}
            <div class="od-save-bar {{ $hasPendingChanges ? 'has-draft' : '' }}">
                <div class="od-save-summary">
                    <div class="od-save-heading">
                        @if ($hasPendingChanges)
                            <span class="od-draft-badge">
                                {{ trans_choice(':count pending change|:count pending changes', $this->pendingChangesCount(), ['count' => $this->pendingChangesCount()]) }}
                            </span>
                        @elseif (! $hasInitialSnapshot)
                            <span class="od-initial-hint">
                                {{ __('Mark initial findings or save directly to generate the first Initial Odontogram snapshot.') }}
                            </span>
                        @else
                            <span class="od-saved-hint">
                                {{ __('All odontogram changes are saved. Click any tooth or surface to stage adjustments.') }}
                            </span>
                        @endif
                    </div>

                    @if ($hasPendingChanges)
                        <div class="od-draft-pills">
                            @if ($pendingClearCanvas)
                                <span class="od-draft-pill is-clear">
                                    {{ __('Full canvas reset staged') }}
                                </span>
                            @endif

                            @foreach ($pendingAdditions as $item)
                                <span class="od-draft-pill">
                                    <span>+ {{ $this->formatPendingItemLabel($item) }}</span>
                                    <button
                                        type="button"
                                        wire:click="removePendingAddition('{{ $item['draft_key'] }}')"
                                        class="od-draft-remove"
                                        title="{{ __('Remove from draft') }}"
                                    >&times;</button>
                                </span>
                            @endforeach

                            @foreach ($pendingCompletions as $entryId)
                                <span class="od-draft-pill is-complete">
                                    <span>{{ __('Complete #:id', ['id' => $entryId]) }}</span>
                                    <button
                                        type="button"
                                        wire:click="removePendingCompletion({{ $entryId }})"
                                        class="od-draft-remove"
                                        title="{{ __('Remove from draft') }}"
                                    >&times;</button>
                                </span>
                            @endforeach

                            @foreach ($pendingCancellations as $entryId)
                                <span class="od-draft-pill is-cancel">
                                    <span>{{ __('Cancel #:id', ['id' => $entryId]) }}</span>
                                    <button
                                        type="button"
                                        wire:click="removePendingCancellation({{ $entryId }})"
                                        class="od-draft-remove"
                                        title="{{ __('Remove from draft') }}"
                                    >&times;</button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="od-save-controls">
                    @if ($hasInitialSnapshot)
                        <label class="od-initial-toggle">
                            <input type="checkbox" wire:model.live="saveAsNewInitial" class="od-checkbox" />
                            <span>{{ __('Register as new Initial Odontogram (Base Diagnosis)') }}</span>
                        </label>
                    @endif

                    @if ($hasPendingChanges)
                        <button
                            type="button"
                            wire:click="discardPendingChanges"
                            class="od-btn-ghost"
                        >
                            {{ __('Discard draft') }}
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click="saveOdontogram"
                        @if ($isInitialSave)
                            wire:confirm="{{ __('Confirm saving and generating an Initial Odontogram snapshot?') }}"
                        @endif
                        wire:loading.attr="disabled"
                        @disabled(! $canSave)
                        class="od-btn-save {{ $isInitialSave ? 'is-initial' : '' }}"
                    >
                        {{ $isInitialSave ? __('Save Initial Odontogram') : __('Save Odontogram') }}
                    </button>
                </div>
            </div>
        @else
            {{-- Read-only Color Legend --}}
            <div class="od-card od-toolbar">
                <span class="od-readonly-label">{{ __('Read-only clinical view — open Edit Patient to modify the odontogram.') }}</span>
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
        @if ($canUpdate)
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
        @endif

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
        <x-odontogram.dental-chart :projection="$projection" :can-update="$canUpdate" />

        {{-- Evolutionary Clinical History Table --}}
        <div>
            {{ $this->table }}
        </div>
    </div>

    {{-- SUB-TAB 2: SNAPSHOT HISTORY --}}
    <div x-show="viewSubTab === 'history'" x-cloak class="od-root">
        @if ($snapshots->isEmpty())
            <div class="od-card od-empty-history">
                <p class="od-empty-title">{{ __('No odontogram snapshots yet') }}</p>
                <p class="od-empty-desc">{{ __('Snapshots are captured automatically whenever an Initial Odontogram is saved.') }}</p>
            </div>
        @else
            <div class="od-snapshot-list">
                @foreach ($snapshots as $snapshot)
                    @php $isSelected = $selectedSnapshot?->id === $snapshot->id; @endphp
                    <button
                        type="button"
                        wire:click="selectSnapshot({{ $snapshot->id }})"
                        class="od-snapshot-card {{ $isSelected ? 'is-selected' : '' }}"
                    >
                        <div class="od-snapshot-card-top">
                            <span class="od-snapshot-title">{{ $snapshot->title }}</span>
                            <span class="od-snapshot-type">{{ $snapshot->type->getLabel() }}</span>
                        </div>
                        <div class="od-snapshot-meta">
                            <span>{{ $snapshot->created_at?->format('d/m/Y H:i') }}</span>
                            <span>•</span>
                            <span>{{ $snapshot->creator?->name ?? '-' }}</span>
                        </div>
                    </button>
                @endforeach
            </div>

            @if ($selectedSnapshot && $snapshotProjection)
                <div class="od-card od-snapshot-header">
                    <div>
                        <h3 class="od-snapshot-detail-title">{{ $selectedSnapshot->title }}</h3>
                        <p class="od-snapshot-detail-meta">
                            {{ $selectedSnapshot->type->getLabel() }} — {{ $selectedSnapshot->created_at?->format('d/m/Y H:i') }}
                            @if ($selectedSnapshot->creator)
                                • {{ $selectedSnapshot->creator->name }}
                            @endif
                        </p>
                    </div>
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

                @if ($snapshotProjection['archEntries']->isNotEmpty())
                    <div class="od-arch-badges">
                        <span class="od-arch-badges-label">{{ __('General / Arch procedures:') }}</span>
                        @foreach ($snapshotProjection['archEntries'] as $archEntry)
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

                <x-odontogram.dental-chart :projection="$snapshotProjection" :can-update="false" />

                @php $snapshotEntries = collect($selectedSnapshot->payload['entries'] ?? []); @endphp
                <div class="od-card od-snapshot-table-wrap">
                    <h4 class="od-snapshot-table-title">{{ __('Records included in this snapshot') }} ({{ $snapshotEntries->count() }})</h4>
                    @if ($snapshotEntries->isEmpty())
                        <p class="od-empty-desc">{{ __('This snapshot represents a healthy mouth with no registered findings or treatments.') }}</p>
                    @else
                        <div class="od-snapshot-table-scroll">
                            <table class="od-snapshot-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Piece / Arch') }}</th>
                                        <th>{{ __('Surfaces') }}</th>
                                        <th>{{ __('Condition') }}</th>
                                        <th>{{ __('Service') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Dentist') }}</th>
                                        <th>{{ __('Notes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($snapshotEntries as $entryItem)
                                        @php
                                            $itemCondition = \App\Enums\OdontogramCondition::tryFrom((string) ($entryItem['condition_code'] ?? ''));
                                            $itemStatus = \App\Enums\OdontogramStatus::tryFrom((string) ($entryItem['status'] ?? ''));
                                            $itemScope = \App\Enums\OdontogramScope::tryFrom((string) ($entryItem['scope'] ?? ''));
                                            $itemArch = filled($entryItem['arch'] ?? null) ? \App\Enums\DentalArch::tryFrom((string) $entryItem['arch']) : null;
                                            $pieceLabel = match (true) {
                                                $itemScope === \App\Enums\OdontogramScope::Range => ($entryItem['tooth_number'] ?? '') . ' → ' . ($entryItem['bridge_to_tooth'] ?? ''),
                                                filled($entryItem['tooth_number'] ?? null) => (string) $entryItem['tooth_number'],
                                                default => $itemArch?->getLabel() ?? '-',
                                            };
                                            $surfaceLabel = ! empty($entryItem['surfaces']) ? implode('-', $entryItem['surfaces']) : '-';
                                        @endphp
                                        <tr>
                                            <td><span class="od-snapshot-piece">{{ $pieceLabel }}</span></td>
                                            <td>{{ $surfaceLabel }}</td>
                                            <td>{{ $itemCondition?->getLabel() ?? ($entryItem['condition_code'] ?? '-') }}</td>
                                            <td>{{ $entryItem['service_name'] ?? '-' }}</td>
                                            <td>
                                                <span
                                                    class="od-snapshot-status"
                                                    style="background-color: {{ $itemStatus?->hexColor() ?? '#64748B' }}20; color: {{ $itemStatus?->hexColor() ?? '#64748B' }};"
                                                >
                                                    {{ $itemStatus?->getLabel() ?? ($entryItem['status'] ?? '-') }}
                                                </span>
                                            </td>
                                            <td>{{ $entryItem['dentist_name'] ?? '-' }}</td>
                                            <td>{{ $entryItem['notes'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>

    <x-filament-actions::modals />
</div>
