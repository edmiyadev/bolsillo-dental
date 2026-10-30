---
name: fullcalendar
description: Use when installing, configuring, styling, or debugging FullCalendar (specifically v7), especially within Laravel, Livewire, Filament, or Alpine.js applications.
---

# FullCalendar v7 Integration Guide

FullCalendar v7 is a modern, high-performance event calendar library for JavaScript applications. It introduces a completely overhauled architecture compared to v6, with unified package imports, native support for modern CSS themes, the TC39 Temporal polyfill peer dependency, and direct color-scheme toggling.

This skill provides comprehensive instructions, patterns, and architectural best practices for integrating FullCalendar v7 with **Laravel**, **Filament PHP**, **Livewire**, and **Alpine.js**.

---

## 1. Key Architectural Principles (v7 vs. v6)

FullCalendar v7 is **not backward-compatible** with v6. Do not mix v6 packages or configuration options with v7.

### Critical Differences

| Feature | FullCalendar v6 | FullCalendar v7 |
| :--- | :--- | :--- |
| **Package structure** | Fragmented (`@fullcalendar/core`, `@fullcalendar/daygrid`, etc.) | **Single unified package**: `fullcalendar` |
| **View imports** | `import dayGridPlugin from '@fullcalendar/daygrid'` | `import dayGridPlugin from 'fullcalendar/daygrid'` |
| **Locales** | `import esLocale from '@fullcalendar/core/locales/es'` | `import esLocale from 'fullcalendar/locales/es'` |
| **Theming option** | `themeSystem: 'standard' \| 'bootstrap5'` | **Removed**. Themes are plugins (e.g. `fullcalendar/themes/monarch`) |
| **Peer Dependencies** | None / optional moment/luxon | **`temporal-polyfill`** is an obligatory peer dependency |
| **Base CSS** | Injected or per-plugin CSS files | **`fullcalendar/skeleton.css`** is mandatory for all layouts |
| **Dark mode** | Custom CSS class overrides on `.fc` | Built-in `colorScheme: 'light' \| 'dark'` + CSS variables |

### Installation

Install `fullcalendar` alongside its required peer dependency:

```bash
npm install fullcalendar temporal-polyfill
```

Verify in `package.json`:

```json
{
  "dependencies": {
    "fullcalendar": "^7.1.0",
    "temporal-polyfill": "^1.0.6"
  }
}
```

---

## 2. Theming and Styling System

In v7, layout sizing and structural styles are decoupled from visual themes.

### Required CSS Imports

1. **`fullcalendar/skeleton.css`**: The structural backbone. Sets up CSS grid, flex layouts, position calculations, and slot geometry. **Without this file, views collapse or render without borders and columns.**
2. **Stock Theme**: v7 includes curated themes such as `monarch` (modern clean), `breezy`, or `classic`.
3. **Palette (Optional)**: Provides accent colors (e.g. `palettes/blue.css`, `palettes/cyan.css`).

In your main entrypoint (e.g., `resources/js/app.js`):

```javascript
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/monarch/theme.css';
import 'fullcalendar/themes/monarch/palettes/blue.css';
```

### Dark Mode Synchronization (Filament / Tailwind)

Filament and Tailwind toggle dark mode using the `.dark` class on `document.documentElement` (`<html>`). To synchronize FullCalendar v7:

1. Read the initial scheme with `document.documentElement.classList.contains('dark') ? 'dark' : 'light'`.
2. Pass `colorScheme` to the `new Calendar(el, { colorScheme, ... })` constructor.
3. Observe changes dynamically with a `MutationObserver` targeting the `class` attribute of `document.documentElement`.
4. Update the calendar dynamically via `this.calendar.setOption('colorScheme', nextScheme)`.

#### Custom CSS Variables Override (`resources/css/calendar.css`)

Support both `.dark .fc` and `.fc[data-color-scheme="dark"]` for robust theming:

```css
/* FullCalendar v7 Theme Overrides (Monarch Theme) */
:root {
    --fc-monarch-primary: #0891b2;
    --fc-monarch-primary-foreground: #ffffff;
    --fc-monarch-primary-over: #0e7490;
    --fc-monarch-primary-down: #155e75;
    --fc-monarch-highlight: rgba(8, 145, 178, 0.08);
}

/* Dark Mode Synchronization */
.dark .fc,
.fc[data-color-scheme="dark"] {
    --fc-monarch-primary: #22d3ee;
    --fc-monarch-primary-foreground: #083344;
    --fc-monarch-primary-over: #67e8f9;
    --fc-monarch-primary-down: #a5f3fc;
    --fc-monarch-highlight: rgba(34, 211, 238, 0.12);
    --fc-monarch-background: transparent;
    --fc-monarch-border: rgba(255, 255, 255, 0.1);
    --fc-monarch-strong-border: rgba(255, 255, 255, 0.16);
    --fc-monarch-faint: rgba(255, 255, 255, 0.03);
    --fc-monarch-muted: rgba(255, 255, 255, 0.06);
}

.fc {
    font-family: inherit;
}

.fc .fc-toolbar-title {
    font-size: 1.125rem;
    font-weight: 600;
    text-transform: capitalize;
}

.fc .fc-event {
    cursor: pointer;
    border-radius: 0.375rem;
    padding: 0.125rem 0.25rem;
}
```

---

## 3. Date Formatting, Timezones, and The UTC Offset Trap

> [!CAUTION]
> **The ISO Offset Trap (`height: 0px` & Shifted Appointments)**:
> Never send event start/end timestamps formatted with UTC offsets (such as `+00:00` or `Z`) for appointments governed by local clinic operating hours.
>
> If a server outputs `2026-10-30T09:00:00+00:00` and the user's browser is in UTC-4 (e.g. America/Caracas or America/Santiago), the browser converts this to `05:00:00`. If `slotMinTime` is set to `'07:00:00'`, the appointment falls **outside** the visible slots! FullCalendar's coordinate mapper fails to find the corresponding vertical grid lane, causing the event element to collapse with `height: 0px` or disappear completely.

### The Correct Practice: Naive Local ISO Strings

Emit naive ISO-8601 strings without offset suffixes: `YYYY-MM-DDTHH:mm:ss`.

In Laravel Eloquent / Resource mapping:

```php
// Good: Local Naive Datetime (preserves visual hour across all clients)
'start' => $appointment->starts_at?->format('Y-m-d\TH:i:s'),
'end'   => $appointment->ends_at?->format('Y-m-d\TH:i:s'),

// BAD: Causes slot collapse and timezone offset conversion issues
'start' => $appointment->starts_at?->toIso8601String(), // '2026-10-30T09:00:00+00:00'
```

When querying date ranges in Laravel controllers or Livewire widgets, parse the calendar's boundary strings using Carbon:

```php
public function fetchEvents(string $start, string $end): array
{
    $startDate = Carbon::parse($start);
    $endDate = Carbon::parse($end);

    return Appointment::query()
        ->where('starts_at', '<', $endDate)
        ->where('ends_at', '>', $startDate)
        ->get()
        ->map(fn (Appointment $apt) => [
            'id'    => (string) $apt->id,
            'title' => $apt->title,
            'start' => $apt->starts_at?->format('Y-m-d\TH:i:s'),
            'end'   => $apt->ends_at?->format('Y-m-d\TH:i:s'),
            'color' => $apt->status_color,
        ])
        ->all();
}
```

---

## 4. Livewire & Alpine.js Reactive Integration Pattern

When embedding FullCalendar in a Filament Page, Filament Widget, or Livewire Component, adhere to this robust pattern:

### 1. Alpine.js Component Registration (`resources/js/app.js`)

```javascript
import { Calendar } from 'fullcalendar';
import themePlugin from 'fullcalendar/themes/monarch';
import dayGridPlugin from 'fullcalendar/daygrid';
import timeGridPlugin from 'fullcalendar/timegrid';
import listPlugin from 'fullcalendar/list';
import interactionPlugin from 'fullcalendar/interaction';
import esLocale from 'fullcalendar/locales/es';

import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/monarch/theme.css';
import 'fullcalendar/themes/monarch/palettes/blue.css';

const getDocumentColorScheme = () => (
    document.documentElement.classList.contains('dark') ? 'dark' : 'light'
);

const createAppointmentCalendarComponent = ({ canCreate = false, createUrl = '' } = {}) => ({
    calendar: null,
    themeObserver: null,
    canCreate,
    createUrl,

    init() {
        const calendarEl = this.$refs.calendar;
        if (!calendarEl) return;

        const colorScheme = getDocumentColorScheme();

        this.calendar = new Calendar(calendarEl, {
            plugins: [themePlugin, dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            locale: esLocale,
            colorScheme,
            initialView: 'timeGridWeek',
            eventDisplay: 'block',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            },
            slotMinTime: '07:00:00',
            slotMaxTime: '20:00:00',
            allDaySlot: false,
            nowIndicator: true,
            height: 'auto',

            // 1. Reactive event fetching through Livewire
            events: (info, successCallback, failureCallback) => {
                this.$wire
                    .fetchEvents(info.startStr, info.endStr)
                    .then((events) => successCallback(events))
                    .catch((error) => failureCallback(error));
            },

            // 2. Click to create pre-filled appointment
            dateClick: (info) => {
                if (!this.canCreate || !this.createUrl) return;

                const url = new URL(this.createUrl, window.location.origin);
                const startsAt = info.dateStr.length > 10
                    ? info.dateStr.slice(0, 16).replace('T', ' ')
                    : `${info.dateStr} 09:00`;

                url.searchParams.set('starts_at', startsAt);

                const branchId = typeof this.$wire.get === 'function'
                    ? this.$wire.get('data.branch_id')
                    : (this.$wire.data?.branch_id || null);

                if (branchId) {
                    url.searchParams.set('branch_id', branchId);
                }

                window.location.href = url.toString();
            },

            // 3. Informative tooltips on mount
            eventDidMount: (info) => {
                const props = info.event.extendedProps || {};
                const details = [
                    props.patient ? `Paciente: ${props.patient}` : null,
                    props.reason ? `Motivo: ${props.reason}` : null,
                    props.dentist ? `Odontólogo: ${props.dentist}` : null,
                    props.cubicle ? `Cubículo: ${props.cubicle}` : null,
                    props.status ? `Estado: ${props.status}` : null,
                ].filter(Boolean);

                if (details.length > 0) {
                    info.el.setAttribute('title', details.join('\n'));
                }
            },
        });

        this.calendar.render();

        // 4. Live dark mode observation
        this.themeObserver = new MutationObserver(() => {
            if (this.calendar) {
                const nextScheme = getDocumentColorScheme();
                if (this.calendar.getOption('colorScheme') !== nextScheme) {
                    this.calendar.setOption('colorScheme', nextScheme);
                }
            }
        });

        this.themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    },

    // 5. Cleanup to prevent memory leaks
    destroy() {
        if (this.themeObserver) {
            this.themeObserver.disconnect();
            this.themeObserver = null;
        }
        if (this.calendar) {
            this.calendar.destroy();
            this.calendar = null;
        }
    },

    // 6. External trigger to re-query current view
    refreshEvents() {
        if (this.calendar) {
            this.calendar.refetchEvents();
        }
    },
});

const registerAppointmentCalendar = () => {
    if (window.Alpine) {
        window.Alpine.data('appointmentCalendar', createAppointmentCalendarComponent);
    }
};

if (window.Alpine) {
    registerAppointmentCalendar();
} else {
    document.addEventListener('alpine:init', registerAppointmentCalendar);
}
```

### 2. Blade Widget Template (`appointment-calendar-widget.blade.php`)

```blade
<x-filament-widgets::widget>
    @assets
        @vite(['resources/css/calendar.css', 'resources/js/app.js'])
    @endassets

    <x-filament::section>
        <div
            x-data="appointmentCalendar({
                canCreate: @js($this->canCreate),
                createUrl: @js($this->createUrl),
            })"
            @calendar-filters-updated.window="refreshEvents()"
        >
            {{-- Filter Form (Filament Form Schema) --}}
            <div class="mb-6">
                {{ $this->form }}
            </div>

            {{-- Optional Status Color Legend --}}
            <div class="appointment-calendar-legend">
                @foreach ($this->statusLegend as $item)
                    <div class="appointment-calendar-legend-item">
                        <span class="appointment-calendar-legend-dot" style="background-color: {{ $item['color'] }};"></span>
                        <span>{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>

            {{-- IMPORTANT: wire:ignore prevents Livewire from wiping FullCalendar DOM --}}
            <div wire:ignore x-ref="calendar" style="min-height: 680px;"></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

### 3. Livewire Widget PHP Class (`AppointmentCalendarWidget.php`)

When filter state updates in Livewire, dispatch the window event to trigger `calendar.refetchEvents()` without re-rendering the calendar DOM:

```php
public function updatedData(): void
{
    $this->dispatch('calendar-filters-updated');
}
```

---

## 5. Common Pitfalls & Troubleshooting

| Pitfall | Cause | Solution |
| :--- | :--- | :--- |
| **`Cannot find module 'temporal-polyfill'`** | FullCalendar v7 requires TC39 Temporal polyfill as a peer dependency. | Run `npm install temporal-polyfill` and add it to `package.json`. |
| **Import errors with `@fullcalendar/*`** | Using v6-style package names (`@fullcalendar/daygrid`). | In v7, import directly from the unified package: `import dayGridPlugin from 'fullcalendar/daygrid'`. |
| **Calendar layout broken / missing borders** | Omission of `skeleton.css`. | Add `import 'fullcalendar/skeleton.css';` to your CSS/JS entrypoint before any theme CSS. |
| **Calendar disappears on form interaction** | Livewire DOM diffing overwriting FullCalendar's injected elements. | Add `wire:ignore` to the container element hosting `x-ref="calendar"`. |
| **Events collapse with `height: 0px`** | Backend dates formatted with UTC offset `+00:00` or `Z`, shifting hours outside `slotMinTime`. | Format datetimes on the server as local naive strings: `$apt->starts_at?->format('Y-m-d\TH:i:s')`. |
| **`Unknown option 'themeSystem'` warning** | FullCalendar v7 eliminated `themeSystem`. | Remove `themeSystem`. Import a theme plugin (e.g. `fullcalendar/themes/monarch`) and pass it to `plugins: [...]`. |
| **Dark mode does not toggle colors** | Missing `colorScheme` option or lack of dynamic theme listener. | Use `MutationObserver` on `document.documentElement` to call `calendar.setOption('colorScheme', nextScheme)`. |
| **Memory leaks in Livewire / SPA navigation** | Active `MutationObserver` or `Calendar` instance not torn down. | Implement Alpine's `destroy()` lifecycle method to disconnect observers and invoke `calendar.destroy()`. |
| **Calendar height jumps or collapses** | Missing minimum height on parent container. | Set an explicit inline style or Tailwind class (e.g., `style="min-height: 680px;"`) on the calendar container. |

---

## 6. Quick Reference

### Recommended View Types

- `dayGridMonth`: Standard monthly grid.
- `timeGridWeek`: Vertical daily columns with hourly time slots across the week.
- `timeGridDay`: Detailed single-day hourly schedule.
- `listWeek`: Chronological list of events for the week (excellent for mobile responsive viewports).

### Core Calendar Options (v7)

```javascript
{
    plugins: [themePlugin, dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    locale: esLocale,                     // fullcalendar/locales/es
    colorScheme: 'light',                 // 'light' | 'dark'
    initialView: 'timeGridWeek',
    slotMinTime: '07:00:00',              // Start of clinic business day
    slotMaxTime: '20:00:00',              // End of clinic business day
    allDaySlot: false,                    // Hide all-day row if not used
    nowIndicator: true,                   // Red line showing current time
    height: 'auto',                       // Expand to fit slot range without internal scrollbar
    eventDisplay: 'block',                // Solid block events
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },
}
```

### Event Object Shape

```javascript
{
    id: "123",
    title: "John Doe — Dental Cleaning",
    start: "2026-10-30T09:00:00",         // Naive ISO string (no offset!)
    end: "2026-10-30T10:00:00",           // Naive ISO string (no offset!)
    url: "/admin/appointments/123/edit",  // Click navigation target
    color: "#0891b2",
    backgroundColor: "#0891b2",
    borderColor: "#0891b2",
    extendedProps: {                      // Arbitrary custom payload
        patient: "John Doe",
        dentist: "Dr. Smith",
        status: "Confirmed",
        branch: "Main Clinic"
    }
}
```

### Essential Instance Methods

- `calendar.render()`: Initializes and renders the calendar DOM inside the target element.
- `calendar.destroy()`: Unmounts the calendar and restores the original container DOM.
- `calendar.refetchEvents()`: Reloads events from the configured `events` fetcher function.
- `calendar.setOption(name, value)`: Dynamically updates options (e.g. `colorScheme`).
- `calendar.getOption(name)`: Reads current option value.
- `calendar.gotoDate(dateStr)`: Moves the calendar view to a specific date.

---

## 7. Official Documentation Reference

The complete FullCalendar v7 LLM reference documentation is maintained at:
- `.agents/skills/fullcalendar/references/llms.md`

Agents working on FullCalendar features, plugins, options, callbacks, or troubleshooting should consult `references/llms.md` for the exhaustive API reference and option catalog.

