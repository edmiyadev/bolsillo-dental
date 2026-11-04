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

const createAppointmentCalendarComponent = ({
    canCreate = false,
    canUpdate = false,
    pastDateError = 'No se pueden programar citas en el pasado.',
} = {}) => ({
    calendar: null,
    themeObserver: null,
    canCreate,
    canUpdate,
    pastDateError,
    rescheduleDialog: {
        isOpen: false,
        appointmentId: null,
        patientName: '',
        dentistName: '',
        oldDateFormatted: '',
        newDateFormatted: '',
        startsAt: null,
        isSubmitting: false,
        confirmed: false,
        revertFn: null,
    },

    showNotification(title, status = 'danger') {
        if (typeof window.FilamentNotification !== 'undefined') {
            const notif = new window.FilamentNotification().title(title);
            if (status === 'danger') {
                notif.danger();
            } else if (status === 'success') {
                notif.success();
            }
            notif.send();
        } else {
            console.warn(title);
        }
    },

    confirmReschedule() {
        if (this.rescheduleDialog.isSubmitting) {
            return;
        }

        this.rescheduleDialog.isSubmitting = true;
        this.rescheduleDialog.confirmed = true;

        this.$wire
            .updateAppointmentDate(this.rescheduleDialog.appointmentId, this.rescheduleDialog.startsAt)
            .then((success) => {
                if (!success && typeof this.rescheduleDialog.revertFn === 'function') {
                    this.rescheduleDialog.revertFn();
                }
            })
            .catch(() => {
                if (typeof this.rescheduleDialog.revertFn === 'function') {
                    this.rescheduleDialog.revertFn();
                }
            })
            .finally(() => {
                this.rescheduleDialog.isSubmitting = false;
                this.$dispatch('close-modal', { id: 'confirm-reschedule-appointment' });
                this.rescheduleDialog.revertFn = null;
            });
    },

    cancelReschedule() {
        if (typeof this.rescheduleDialog.revertFn === 'function') {
            this.rescheduleDialog.revertFn();
            this.rescheduleDialog.revertFn = null;
        }
        this.$dispatch('close-modal', { id: 'confirm-reschedule-appointment' });
    },

    handleModalClosed(event) {
        if (event.detail?.id === 'confirm-reschedule-appointment') {
            if (!this.rescheduleDialog.confirmed && typeof this.rescheduleDialog.revertFn === 'function') {
                this.rescheduleDialog.revertFn();
                this.rescheduleDialog.revertFn = null;
            }
            this.rescheduleDialog.isOpen = false;
            this.rescheduleDialog.confirmed = false;
        }
    },

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
            editable: this.canUpdate,
            eventStartEditable: this.canUpdate,
            eventDurationEditable: false,
            eventAllow: (dropInfo) => {
                return dropInfo.start >= new Date();
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            },
            dayHeaderClass: (arg) => (arg.isToday ? 'fc-day-today' : ''),
            dayHeaderClassNames: (arg) => (arg.isToday ? ['fc-day-today'] : []),
            dayCellClass: (arg) => (arg.isToday ? 'fc-day-today' : ''),
            dayCellClassNames: (arg) => (arg.isToday ? ['fc-day-today'] : []),
            dayHeaderContent: (arg) => {
                if (arg.isToday) {
                    return {
                        html: `<div class="fc-day-header-today-container"><span class="fc-day-header-text">${arg.text}</span><span class="fc-today-pill">HOY</span></div>`,
                    };
                }

                return arg.text;
            },
            dayCellTopContent: (arg) => {
                if (arg.isToday) {
                    const text = arg.text || arg.dayNumberText || '';
                    return {
                        html: `<span class="fc-month-today-number">${text}</span>`,
                    };
                }

                return arg.text;
            },
            slotMinTime: '07:00:00',
            slotMaxTime: '20:00:00',
            slotHeaderFormat: {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            },
            slotLabelFormat: {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            },
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            },
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

                const clickedDate = new Date(info.date);
                const now = new Date();

                if (info.allDay) {
                    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    if (clickedDate < startOfToday) {
                        this.showNotification(this.pastDateError, 'danger');
                        return;
                    }
                } else if (clickedDate < now) {
                    this.showNotification(this.pastDateError, 'danger');
                    return;
                }

                const pad = (n) => String(n).padStart(2, '0');
                let startsAt;

                if (info.dateStr.length > 10) {
                    startsAt = info.dateStr.slice(0, 16).replace('T', ' ');
                } else {
                    const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
                    if (info.dateStr === todayStr) {
                        const nextHour = new Date(now.getTime() + 60 * 60 * 1000);
                        startsAt = `${todayStr} ${pad(nextHour.getHours())}:00`;
                    } else {
                        startsAt = `${info.dateStr} 09:00`;
                    }
                }

                const branchId = typeof this.$wire.get === 'function'
                    ? (this.$wire.get('filters.branch_id') || this.$wire.get('data.branch_id') || null)
                    : (this.$wire.filters?.branch_id || this.$wire.data?.branch_id || null);

                this.$wire.mountAction('createAppointment', {
                    starts_at: startsAt,
                    branch_id: branchId,
                });
            },
            eventDrop: (info) => {
                if (!this.canUpdate) {
                    info.revert();
                    return;
                }

                const newStart = info.event.start;
                const now = new Date();

                if (newStart < now) {
                    this.showNotification(this.pastDateError, 'danger');
                    info.revert();
                    return;
                }

                const pad = (n) => String(n).padStart(2, '0');
                const startsAt = `${newStart.getFullYear()}-${pad(newStart.getMonth() + 1)}-${pad(newStart.getDate())} ${pad(newStart.getHours())}:${pad(newStart.getMinutes())}:${pad(newStart.getSeconds())}`;

                const oldStart = info.oldEvent.start;

                const formatDateTime = (date) => {
                    if (!date) return '';
                    const dayOptions = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
                    let datePart = date.toLocaleDateString('es-ES', dayOptions);
                    datePart = datePart.charAt(0).toUpperCase() + datePart.slice(1);

                    const h = date.getHours();
                    const m = pad(date.getMinutes());
                    const hour12 = h % 12 || 12;
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    const timePart = `${hour12}:${m} ${ampm}`;

                    return `${datePart}, ${timePart}`;
                };

                this.rescheduleDialog = {
                    isOpen: true,
                    appointmentId: Number(info.event.id),
                    patientName: info.event.extendedProps?.patient || info.event.title || '',
                    dentistName: info.event.extendedProps?.dentist || '',
                    oldDateFormatted: formatDateTime(oldStart),
                    newDateFormatted: formatDateTime(newStart),
                    startsAt: startsAt,
                    isSubmitting: false,
                    confirmed: false,
                    revertFn: info.revert,
                };

                this.$dispatch('open-modal', { id: 'confirm-reschedule-appointment' });
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
