@props([
    'tooth',
    'state',
    'inverted' => false,
    'compact' => false,
])

@php
    $morphology = $state['morphology'] ?? 'molar';
    $hasCrown = (bool) ($state['hasCrown'] ?? false);
    $crownColor = $state['crown']['color'] ?? '#3B82F6';
    $hasEndodontics = (bool) ($state['hasEndodontics'] ?? false);
    $endoColor = $state['endodontics']['color'] ?? '#3B82F6';
    $hasImplant = (bool) ($state['hasImplant'] ?? false);
    $implantColor = $state['implant']['color'] ?? '#3B82F6';
    $hasExtraction = (bool) ($state['hasExtraction'] ?? false);
    $isMissing = (bool) ($state['isMissing'] ?? false);
    $isBlocked = (bool) ($state['isBlocked'] ?? false);
    $crossColor = $state['extraction']['color'] ?? ($state['missing']['color'] ?? '#475569');
    $hasFracture = (bool) ($state['hasFracture'] ?? false);
    $fractureColor = $state['fracture']['color'] ?? '#EF4444';

    $crownFill = $hasCrown ? ($crownColor . '33') : '#FFFFFF';
    $crownStroke = $hasCrown ? $crownColor : '#334155';
    $crownStrokeWidth = $hasCrown ? '3.5' : '2.3';
    $rootFill = '#FEF3C7';
    $rootStroke = '#475569';
    $width = $compact ? 32 : 36;
    $height = $compact ? 40 : 46;
@endphp

<svg
    width="{{ $width }}"
    height="{{ $height }}"
    viewBox="0 0 60 76"
    class="od-tooth-svg {{ $isBlocked ? 'is-blocked' : '' }}"
    aria-label="{{ __('Tooth') }} {{ $tooth }}"
>
    <g @if ($inverted) transform="translate(0, 76) scale(1, -1)" @endif>
        @if ($hasImplant)
            {{-- Implant Screw in Root Area --}}
            <rect x="23" y="6" width="14" height="28" rx="3" fill="{{ $implantColor }}33" stroke="{{ $implantColor }}" stroke-width="2.2" />
            <line x1="20" y1="12" x2="40" y2="12" stroke="{{ $implantColor }}" stroke-width="2.5" stroke-linecap="round" />
            <line x1="20" y1="18" x2="40" y2="18" stroke="{{ $implantColor }}" stroke-width="2.5" stroke-linecap="round" />
            <line x1="20" y1="24" x2="40" y2="24" stroke="{{ $implantColor }}" stroke-width="2.5" stroke-linecap="round" />
            <line x1="20" y1="30" x2="40" y2="30" stroke="{{ $implantColor }}" stroke-width="2.5" stroke-linecap="round" />
        @else
            @if ($morphology === 'molar')
                <path d="M14,36 C10,18 12,8 16,4 C19,8 21,20 22,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
                <path d="M25,36 C25,14 28,3 30,2 C32,3 35,14 35,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
                <path d="M38,36 C39,20 41,8 44,4 C48,8 50,18 46,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
            @elseif ($morphology === 'premolar')
                <path d="M18,36 C15,16 19,5 23,4 C26,8 27,22 28,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
                <path d="M32,36 C33,22 34,8 37,4 C41,5 45,16 42,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
            @elseif ($morphology === 'canine')
                <path d="M21,36 C21,14 26,2 30,1 C34,2 39,14 39,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
            @else
                <path d="M22,36 C22,16 26,4 30,3 C34,4 38,16 38,36 Z" fill="{{ $rootFill }}" stroke="{{ $rootStroke }}" stroke-width="2" />
            @endif
        @endif

        {{-- Anatomical Crown --}}
        @if ($morphology === 'molar')
            <path d="M8,36 C6,48 8,64 16,70 C22,72 26,68 30,70 C34,68 38,72 44,70 C52,64 54,48 52,36 Z" fill="{{ $crownFill }}" stroke="{{ $crownStroke }}" stroke-width="{{ $crownStrokeWidth }}" />
            <path d="M16,49 Q30,55 44,49" fill="none" stroke="#94A3B8" stroke-width="1.4" />
        @elseif ($morphology === 'premolar')
            <path d="M12,36 C10,48 13,64 20,70 C26,72 34,72 40,70 C47,64 50,48 48,36 Z" fill="{{ $crownFill }}" stroke="{{ $crownStroke }}" stroke-width="{{ $crownStrokeWidth }}" />
            <path d="M20,48 Q30,53 40,48" fill="none" stroke="#94A3B8" stroke-width="1.4" />
        @elseif ($morphology === 'canine')
            <path d="M14,36 C12,48 16,62 30,73 C44,62 48,48 46,36 Z" fill="{{ $crownFill }}" stroke="{{ $crownStroke }}" stroke-width="{{ $crownStrokeWidth }}" />
        @else
            <path d="M15,36 C14,50 16,66 19,71 L41,71 C44,66 46,50 45,36 Z" fill="{{ $crownFill }}" stroke="{{ $crownStroke }}" stroke-width="{{ $crownStrokeWidth }}" />
        @endif

        {{-- Endodontics Root Canal Overlay --}}
        @if ($hasEndodontics)
            @if ($morphology === 'molar')
                <line x1="17" y1="8" x2="23" y2="48" stroke="{{ $endoColor }}" stroke-width="3.2" stroke-linecap="round" />
                <line x1="30" y1="6" x2="30" y2="48" stroke="{{ $endoColor }}" stroke-width="3.2" stroke-linecap="round" />
                <line x1="43" y1="8" x2="37" y2="48" stroke="{{ $endoColor }}" stroke-width="3.2" stroke-linecap="round" />
            @elseif ($morphology === 'premolar')
                <line x1="23" y1="8" x2="27" y2="48" stroke="{{ $endoColor }}" stroke-width="3.4" stroke-linecap="round" />
                <line x1="37" y1="8" x2="33" y2="48" stroke="{{ $endoColor }}" stroke-width="3.4" stroke-linecap="round" />
            @else
                <line x1="30" y1="6" x2="30" y2="50" stroke="{{ $endoColor }}" stroke-width="3.8" stroke-linecap="round" />
            @endif
        @endif
    </g>

    {{-- Fracture Zig-Zag Overlay --}}
    @if ($hasFracture)
        <polyline
            points="16,24 34,34 22,46 42,58"
            fill="none"
            stroke="{{ $fractureColor }}"
            stroke-width="3.4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    @endif

    {{-- Extraction / Missing X Overlay --}}
    @if ($hasExtraction || $isMissing)
        <line x1="8" y1="8" x2="52" y2="68" stroke="{{ $crossColor }}" stroke-width="4.5" stroke-linecap="round" />
        <line x1="52" y1="8" x2="8" y2="68" stroke="{{ $crossColor }}" stroke-width="4.5" stroke-linecap="round" />
    @endif
</svg>
