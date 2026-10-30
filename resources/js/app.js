import { Calendar } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';

const createAppointmentCalendarComponent = ({ canCreate = false, createUrl = '' } = {}) => ({
    calendar: null,
    branchId: '',
    dentistId: '',
    status: '',
    canCreate,
    createUrl,

    init() {
        const calendarEl = this.$refs.calendar;

        if (!calendarEl) {
            return;
        }

        this.calendar = new Calendar(calendarEl, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
            locale: esLocale,
            initialView: 'timeGridWeek',
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
                    .fetchEvents(
                        info.startStr,
                        info.endStr,
                        this.branchId ? Number(this.branchId) : null,
                        this.dentistId ? Number(this.dentistId) : null,
                        this.status || null,
                    )
                    .then(successCallback)
                    .catch(failureCallback);
            },
            dateClick: (info) => {
                if (!this.canCreate || !this.createUrl) {
                    return;
                }

                const url = new URL(this.createUrl, window.location.origin);
                const startsAt =
                    info.dateStr.length > 10
                        ? info.dateStr.slice(0, 16).replace('T', ' ')
                        : `${info.dateStr} 09:00`;

                url.searchParams.set('starts_at', startsAt);

                if (this.branchId) {
                    url.searchParams.set('branch_id', this.branchId);
                }

                window.location.href = url.toString();
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

document.addEventListener('alpine:init', registerAppointmentCalendar);
registerAppointmentCalendar();
