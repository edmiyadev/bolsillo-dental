import { Calendar } from 'fullcalendar';
import themePlugin from 'fullcalendar/themes/monarch';
import dayGridPlugin from 'fullcalendar/daygrid';
import timeGridPlugin from 'fullcalendar/timegrid';
import listPlugin from 'fullcalendar/list';
import interactionPlugin from 'fullcalendar/interaction';
import esLocale from 'fullcalendar/locales/es';

import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/monarch/theme.css';

const getDocumentColorScheme = () => (
    document.documentElement.classList.contains('dark') ? 'dark' : 'light'
);

const createAppointmentCalendarComponent = ({ canCreate = false, canUpdate = false } = {}) => ({
    calendar: null,
    themeObserver: null,
    canCreate,
    canUpdate,

    init() {
        const calendarEl = this.$refs.calendar;

        if (!calendarEl) {
            return;
        }

        this.calendar = new Calendar(calendarEl, {
            plugins: [themePlugin, dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            locale: esLocale,
            colorScheme: getDocumentColorScheme(),
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
            events: (info, successCallback, failureCallback) => {
                this.$wire
                    .fetchEvents(info.startStr, info.endStr)
                    .then(successCallback)
                    .catch(failureCallback);
            },
            dateClick: (info) => {
                if (!this.canCreate) {
                    return;
                }

                const startsAt =
                    info.dateStr.length > 10
                        ? info.dateStr.slice(0, 16).replace('T', ' ')
                        : `${info.dateStr} 09:00`;

                const branchId = typeof this.$wire.get === 'function'
                    ? (this.$wire.get('filters.branch_id') || this.$wire.get('data.branch_id') || null)
                    : (this.$wire.filters?.branch_id || this.$wire.data?.branch_id || null);

                this.$wire.mountAction('createAppointment', {
                    starts_at: startsAt,
                    branch_id: branchId,
                });
            },
            eventClick: (info) => {
                info.jsEvent.preventDefault();

                const appointmentId = info.event.id;
                if (!appointmentId) {
                    return;
                }

                const action = this.canUpdate ? 'editAppointment' : 'viewAppointment';
                this.$wire.mountAction(action, { record: appointmentId });
            },
            eventDidMount: (info) => {
                const props = info.event.extendedProps || {};
                const details = [
                    props.patient ? `Paciente: ${props.patient}` : null,
                    props.reason ? `Motivo: ${props.reason}` : null,
                    props.dentist ? `Odontólogo: ${props.dentist}` : null,
                    props.cubicle ? `Cubículo: ${props.cubicle}` : null,
                    props.branch ? `Sucursal: ${props.branch}` : null,
                    props.status ? `Estado: ${props.status}` : null,
                ].filter(Boolean);

                if (details.length > 0) {
                    info.el.setAttribute('title', details.join('\n'));
                }
            },
        });

        this.calendar.render();

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

        if (typeof this.$cleanup === 'function') {
            this.$cleanup(() => this.destroy());
        }
    },

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
