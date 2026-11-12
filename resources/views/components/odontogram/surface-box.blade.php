@props([
    'tooth',
    'state',
    'compact' => false,
    'canUpdate' => true,
])

@php
    $polygons = $state['polygons'] ?? [];
    $hasCrown = (bool) ($state['hasCrown'] ?? false);
    $crownColor = $state['crown']['color'] ?? '#3B82F6';
    $hasImplant = (bool) ($state['hasImplant'] ?? false);
    $implantColor = $state['implant']['color'] ?? '#3B82F6';
    $hasExtraction = (bool) ($state['hasExtraction'] ?? false);
    $isMissing = (bool) ($state['isMissing'] ?? false);
    $isBlocked = (bool) ($state['isBlocked'] ?? false);
    $crossColor = $state['extraction']['color'] ?? ($state['missing']['color'] ?? '#475569');
    $hasFracture = (bool) ($state['hasFracture'] ?? false);
    $fractureColor = $state['fracture']['color'] ?? '#EF4444';
    $surfaceLabels = \App\Support\OdontogramGeometryCatalog::surfaceLabels();
    $size = $compact ? 34 : 38;

    $polygonCoords = [
        'top' => '4,4 96,4 70,30 30,30',
        'bottom' => '30,70 70,70 96,96 4,96',
        'left' => '4,4 30,30 30,70 4,96',
        'right' => '70,30 96,4 96,96 70,70',
    ];
@endphp

<div class="od-surface-wrap {{ $isBlocked ? 'is-blocked' : '' }}">
    <svg
        width="{{ $size }}"
        height="{{ $size }}"
        viewBox="0 0 100 100"
        class="od-surface-svg"
    >
        @foreach (['top', 'bottom', 'left', 'right'] as $position)
            @php
                $poly = $polygons[$position] ?? ['surface' => '', 'color' => '#FFFFFF', 'condition' => null];
                $surfaceCode = $poly['surface'] ?? '';
                $surfaceName = $surfaceLabels[$surfaceCode] ?? $surfaceCode;
                $conditionLabel = isset($poly['condition']) && $poly['condition'] ? ' — ' . $poly['condition']->getLabel() : '';
            @endphp
            <polygon
                points="{{ $polygonCoords[$position] }}"
                fill="{{ $poly['color'] ?? '#FFFFFF' }}"
                stroke="#334155"
                stroke-width="3"
                data-tooth="{{ $tooth }}"
                data-surface="{{ $surfaceCode }}"
                @if ($canUpdate)
                    x-on:click.stop="handlePolygonClick({{ $tooth }}, '{{ $surfaceCode }}', {{ $isBlocked ? 'true' : 'false' }})"
                    :stroke="isSurfaceSelected({{ $tooth }}, '{{ $surfaceCode }}') ? '#0284C7' : '#334155'"
                    :stroke-width="isSurfaceSelected({{ $tooth }}, '{{ $surfaceCode }}') ? '5.5' : '3'"
                @endif
                class="od-surface-poly {{ $canUpdate ? 'is-interactive' : '' }}"
            >
                <title>{{ $tooth }}-{{ $surfaceCode }} ({{ $surfaceName }}){{ $conditionLabel }}</title>
            </polygon>
        @endforeach

        @php
            $centerPoly = $polygons['center'] ?? ['surface' => '', 'color' => '#FFFFFF', 'condition' => null, 'isSealant' => false];
            $centerCode = $centerPoly['surface'] ?? '';
            $centerName = $surfaceLabels[$centerCode] ?? $centerCode;
            $centerConditionLabel = isset($centerPoly['condition']) && $centerPoly['condition'] ? ' — ' . $centerPoly['condition']->getLabel() : '';
        @endphp
        <rect
            x="30"
            y="30"
            width="40"
            height="40"
            fill="{{ ($centerPoly['isSealant'] ?? false) ? '#FFFFFF' : ($centerPoly['color'] ?? '#FFFFFF') }}"
            stroke="#334155"
            stroke-width="3"
            data-tooth="{{ $tooth }}"
            data-surface="{{ $centerCode }}"
            @if ($canUpdate)
                x-on:click.stop="handlePolygonClick({{ $tooth }}, '{{ $centerCode }}', {{ $isBlocked ? 'true' : 'false' }})"
                :stroke="isSurfaceSelected({{ $tooth }}, '{{ $centerCode }}') ? '#0284C7' : '#334155'"
                :stroke-width="isSurfaceSelected({{ $tooth }}, '{{ $centerCode }}') ? '5.5' : '3'"
            @endif
            class="od-surface-poly {{ $canUpdate ? 'is-interactive' : '' }}"
        >
            <title>{{ $tooth }}-{{ $centerCode }} ({{ $centerName }}){{ $centerConditionLabel }}</title>
        </rect>

        {{-- Sealant Letter S --}}
        @if ($centerPoly['isSealant'] ?? false)
            <text
                x="50"
                y="59"
                text-anchor="middle"
                font-size="28"
                font-weight="800"
                fill="{{ $centerPoly['color'] ?? '#3B82F6' }}"
                class="od-svg-no-events"
            >S</text>
        @endif

        {{-- Crown Outer Circle --}}
        @if ($hasCrown)
            <circle
                cx="50"
                cy="50"
                r="45"
                fill="none"
                stroke="{{ $crownColor }}"
                stroke-width="6.5"
                class="od-svg-no-events"
            />
        @endif

        {{-- Implant Badge --}}
        @if ($hasImplant)
            <text
                x="50"
                y="58"
                text-anchor="middle"
                font-size="24"
                font-weight="800"
                fill="{{ $implantColor }}"
                class="od-svg-no-events"
            >IMP</text>
        @endif

        {{-- Fracture Zig-Zag --}}
        @if ($hasFracture)
            <polyline
                points="14,20 42,40 28,60 82,82"
                fill="none"
                stroke="{{ $fractureColor }}"
                stroke-width="5.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="od-svg-no-events"
            />
        @endif

        {{-- Extraction / Missing X --}}
        @if ($hasExtraction || $isMissing)
            <line x1="4" y1="4" x2="96" y2="96" stroke="{{ $crossColor }}" stroke-width="6.5" stroke-linecap="round" class="od-svg-no-events" />
            <line x1="96" y1="4" x2="4" y2="96" stroke="{{ $crossColor }}" stroke-width="6.5" stroke-linecap="round" class="od-svg-no-events" />
        @endif
    </svg>
</div>
