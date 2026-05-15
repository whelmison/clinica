<script>
const selectedAppointmentData = <?= $selectedAppointmentJson ?>;
const selectedAvailabilityData = <?= $selectedAvailabilityJson ?>;
const canManageAppointments = <?= $hasAppointmentOperation ? 'true' : 'false' ?>;
const canManageAvailability = <?= $hasAvailabilityOperation ? 'true' : 'false' ?>;
const autoSubmitProfessionalSelect = <?= $autoSubmitProfessionalSelect ? 'true' : 'false' ?>;
const autoOpenSelectedAppointmentModal = <?= $autoOpenSelectedAppointmentModal ? 'true' : 'false' ?>;
const autoOpenCalendarFilterModal = <?= $autoOpenCalendarFilterModal ? 'true' : 'false' ?>;
const appointmentUnavailableMessage = <?= json_encode($appointmentUnavailableMessage, $jsonFlags) ?>;
const appointmentReturnTo = <?= json_encode((string) ($appointmentReturnTo ?? ''), $jsonFlags) ?>;
const initialFlashMessage = <?= !empty($scheduleFlash['message']) ? json_encode((string) $scheduleFlash['message'], $jsonFlags) : 'null' ?>;
const initialFlashType = <?= !empty($scheduleFlash['type']) ? json_encode((string) $scheduleFlash['type'], $jsonFlags) : '"warning"' ?>;
const scheduleModeButtons = document.querySelectorAll('[data-schedule-mode]');
const calendarFilterForm = document.getElementById('calendarFilterForm');
const calendarProfessional = document.getElementById('calendarProfessional');
const calendarService = document.getElementById('calendarService');
const calendarServiceField = document.getElementById('calendarServiceField');
const calendarGroupQuickField = document.getElementById('calendarGroupQuickField');
const calendarGroupQuick = document.getElementById('calendarGroupQuick');
const calendarFilterModalElement = document.getElementById('calendarFilterModal');
const appointmentForm = document.getElementById('appointmentForm');
const appointmentFormHost = document.getElementById('appointmentFormHost');
const appointmentModalElement = document.getElementById('appointmentModal');
const appointmentModalMount = document.getElementById('appointmentModalMount');
const appointmentModalTitle = document.getElementById('appointmentModalTitle');
const appointmentModalSubtitle = document.getElementById('appointmentModalSubtitle');
const appointmentAction = document.getElementById('appointmentAction');
const appointmentId = document.getElementById('appointmentId');
const appointmentProfessional = document.getElementById('appointmentProfessional');
const appointmentPatient = document.getElementById('appointmentPatient');
const appointmentPatientSearch = document.getElementById('appointmentPatientSearch');
const appointmentPatientMenu = document.getElementById('appointmentPatientMenu');
const appointmentName = document.getElementById('appointmentName');
const appointmentPhone = document.getElementById('appointmentPhone');
const appointmentPreferenceDay = document.getElementById('appointmentPreferenceDay');
const appointmentPreferenceTime = document.getElementById('appointmentPreferenceTime');
const appointmentService = document.getElementById('appointmentService');
const appointmentDate = document.getElementById('appointmentDate');
const appointmentTime = document.getElementById('appointmentTime');
const appointmentStatus = document.getElementById('appointmentStatus');
const appointmentGuideWrap = document.getElementById('appointmentGuideWrap');
const appointmentGuide = document.getElementById('appointmentGuide');
const appointmentNotes = document.getElementById('appointmentNotes');
const appointmentSubmitBtn = document.getElementById('appointmentSubmitBtn');
const appointmentWhatsappBtn = document.getElementById('appointmentWhatsappBtn');
const appointmentDeleteBtn = document.getElementById('appointmentDeleteBtn');
const appointmentResetBtn = document.getElementById('appointmentResetBtn');
const availabilityForm = document.getElementById('availabilityForm');
const availabilityAction = document.getElementById('availabilityAction');
const availabilityId = document.getElementById('availabilityId');
const availabilityScope = document.getElementById('availabilityScope');
const availabilityPeriod = document.getElementById('availabilityPeriod');
const availabilityBusinessDaysWrap = document.getElementById('availabilityBusinessDaysWrap');
const availabilityBusinessDays = document.getElementById('availabilityBusinessDays');
const availabilityWeekdaysWrap = document.getElementById('availabilityWeekdaysWrap');
const availabilityWeekdayChecks = document.querySelectorAll('[name="dias_semana[]"]');
const availabilityDateWrap = document.getElementById('availabilityDateWrap');
const availabilityDate = document.getElementById('availabilityDate');
const availabilityEndDateWrap = document.getElementById('availabilityEndDateWrap');
const availabilityEndDate = document.getElementById('availabilityEndDate');
const availabilityMonthWrap = document.getElementById('availabilityMonthWrap');
const availabilityMonth = document.getElementById('availabilityMonth');
const availabilityStartWrap = document.getElementById('availabilityStartWrap');
const availabilityStart = document.getElementById('availabilityStart');
const availabilityEndWrap = document.getElementById('availabilityEndWrap');
const availabilityEnd = document.getElementById('availabilityEnd');
const availabilityNotes = document.getElementById('availabilityNotes');
const availabilitySubmitBtn = document.getElementById('availabilitySubmitBtn');
const availabilityDeletePeriodBtn = document.getElementById('availabilityDeletePeriodBtn');
const availabilityDeleteBtn = document.getElementById('availabilityDeleteBtn');
const availabilityResetBtn = document.getElementById('availabilityResetBtn');
const patientPreferenceCard = document.getElementById('patientPreferenceCard');
const patientPreferenceText = document.getElementById('patientPreferenceText');
const calendarShell = document.querySelector('.calendar-shell');
const calendarSlotMinutes = Math.max(5, parseInt(calendarShell?.dataset.slotMinutes || '30', 10) || 30);
const agendaHoverClock = document.getElementById('agendaHoverClock');
const agendaInlineNotice = document.getElementById('agendaInlineNotice');
const agendaCalendarReport = document.getElementById('agendaCalendarReport');
const printCalendarBtn = document.getElementById('printCalendarBtn');
const exportPdfBtn = document.getElementById('exportPdfBtn');
const exportJpgBtn = document.getElementById('exportJpgBtn');
const shareWhatsappJpgBtn = document.getElementById('shareWhatsappJpgBtn');
const appointmentModal = appointmentModalElement && window.bootstrap?.Modal
    ? new window.bootstrap.Modal(appointmentModalElement)
    : null;
const calendarFilterModal = calendarFilterModalElement && window.bootstrap?.Modal
    ? new window.bootstrap.Modal(calendarFilterModalElement)
    : null;
const appointmentWhatsappTemplate = <?= json_encode(trim((string) ($currentProfessional['mensagem_padrao_whatsapp'] ?? '')), $jsonFlags) ?>;
const appointmentWhatsappDefaultTemplate = 'Ola {paciente}, segue a atualizacao do seu agendamento na clinica.\nData: {data}\nHora: {hora}\nProfissional: {profissional}\nServico: {servico}\nStatus: {status}';
const appointmentProfessionalName = <?= json_encode((string) (($currentProfessional['nome'] ?? '') !== '' ? $currentProfessional['nome'] : ($selectedProfessionalName !== '' ? $selectedProfessionalName : 'Profissional')), $jsonFlags) ?>;

let draggedAppointment = null;
let currentScheduleMode = <?= json_encode($operationMode, $jsonFlags) ?>;
const availabilityRangesByDate = {};
let appointmentGuideRequestId = 0;

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatTimeBr(timeStr) {
    return String(timeStr || '').slice(0, 5);
}

function parseTimeToMinutes(timeStr) {
    const [hour, minute] = String(timeStr || '07:00').split(':');
    return (parseInt(hour || '0', 10) * 60) + parseInt(minute || '0', 10);
}

function minutesToTime(totalMinutes) {
    const hour = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
    const minute = String(totalMinutes % 60).padStart(2, '0');
    return `${hour}:${minute}`;
}

function addMinutesToTime(timeStr, minutesToAdd) {
    return minutesToTime(parseTimeToMinutes(timeStr) + minutesToAdd);
}

function formatDateLabel(dateValue) {
    if (!dateValue || !dateValue.includes('-')) {
        return '';
    }

    const [year, month, day] = dateValue.split('-');
    return `${day}/${month}/${year}`;
}

function normalizePhoneDigits(phoneValue) {
    const digits = String(phoneValue || '').replace(/\D+/g, '').replace(/^0+/, '');

    if (digits === '') {
        return '';
    }

    if (digits.startsWith('55') && digits.length >= 12) {
        return digits;
    }

    if (digits.length === 10 || digits.length === 11) {
        return `55${digits}`;
    }

    return digits;
}

function buildAppointmentWhatsappUrl() {
    const patientPhone = normalizePhoneDigits(appointmentPhone?.value || appointmentPatient?.dataset.phone || '');

    if (patientPhone === '') {
        return null;
    }

    const patientName = String(appointmentName?.value || appointmentPatient?.dataset.name || appointmentPatientSearch?.value || 'Paciente').trim() || 'Paciente';
    const dateLabel = formatDateLabel(appointmentDate?.value || '') || 'Nao informado';
    const timeLabel = String(appointmentTime?.value || '').trim() || 'Nao informado';
    const serviceLabel = String(appointmentService?.selectedOptions?.[0]?.textContent || 'Servico').trim() || 'Servico';
    const statusLabel = String(appointmentStatus?.selectedOptions?.[0]?.textContent || appointmentStatus?.value || 'Agendado').trim() || 'Agendado';
    const template = appointmentWhatsappTemplate !== '' ? appointmentWhatsappTemplate : appointmentWhatsappDefaultTemplate;
    const message = template.replace(/\{(paciente|data|hora|profissional|servico|status)\}/gi, (placeholder, key) => {
        const values = {
            paciente: patientName,
            data: dateLabel,
            hora: timeLabel,
            profissional: appointmentProfessionalName,
            servico: serviceLabel,
            status: statusLabel,
        };

        return values[key.toLowerCase()] ?? placeholder;
    });

    return `https://wa.me/${patientPhone}?text=${encodeURIComponent(message)}`;
}

function updateAppointmentWhatsappButton() {
    if (!appointmentWhatsappBtn) {
        return;
    }

    const isEditing = appointmentAction?.value === 'update_appointment' && (appointmentId?.value || '') !== '' && appointmentId.value !== '0';
    const whatsappUrl = isEditing ? buildAppointmentWhatsappUrl() : null;

    appointmentWhatsappBtn.style.display = isEditing ? 'inline-flex' : 'none';
    appointmentWhatsappBtn.href = whatsappUrl || '#';
    appointmentWhatsappBtn.classList.toggle('disabled', !whatsappUrl);
    appointmentWhatsappBtn.setAttribute('aria-disabled', whatsappUrl ? 'false' : 'true');
    appointmentWhatsappBtn.title = whatsappUrl
        ? 'Enviar mensagem para o WhatsApp do paciente'
        : 'Paciente sem telefone cadastrado';
}

function isBookableSlot(dateValue, timeValue) {
    const ranges = availabilityRangesByDate[dateValue] || [];
    const slotMinutes = parseTimeToMinutes(timeValue);

    return ranges.some((range) => slotMinutes >= range.start && slotMinutes < range.end);
}

function ensureAppointmentFormInModal() {
    if (!appointmentForm || !appointmentModalMount) {
        return;
    }

    if (appointmentForm.parentElement !== appointmentModalMount) {
        appointmentModalMount.appendChild(appointmentForm);
    }

    appointmentForm.style.display = '';
}

function restoreAppointmentFormToHost() {
    if (!appointmentForm || !appointmentFormHost) {
        return;
    }

    if (appointmentForm.parentElement !== appointmentFormHost) {
        appointmentFormHost.appendChild(appointmentForm);
    }

    appointmentForm.style.display = scheduleModeButtons.length > 0 && currentScheduleMode !== 'appointment'
        ? 'none'
        : '';
}

function updateAppointmentModalCopy() {
    if (!appointmentModalTitle || !appointmentModalSubtitle || !appointmentForm) {
        return;
    }

    const isEditing = appointmentAction?.value === 'update_appointment' && (appointmentId?.value || '') !== '' && appointmentId.value !== '0';
    const dateValue = appointmentDate?.value || '';
    const timeValue = appointmentTime?.value || '';
    const patientLabel = String(appointmentName?.value || appointmentPatientSearch?.value || '').trim();
    const serviceLabel = appointmentService?.selectedOptions?.[0]?.textContent?.trim() || '';
    const details = [];

    appointmentModalTitle.textContent = isEditing ? 'Editar agendamento' : 'Novo agendamento';

    if (dateValue) {
        details.push(formatDateLabel(dateValue));
    }

    if (timeValue) {
        details.push(timeValue);
    }

    if (patientLabel && appointmentPatient?.value) {
        details.push(patientLabel);
    }

    if (serviceLabel && appointmentService?.value) {
        details.push(serviceLabel);
    }

    appointmentModalSubtitle.textContent = details.length > 0
        ? details.join(' | ')
        : 'Selecione paciente, servico e horario para salvar.';
    updateAppointmentWhatsappButton();
}

function openAppointmentModal() {
    if (!appointmentForm || !appointmentModal) {
        return;
    }

    ensureAppointmentFormInModal();
    updateAppointmentModalCopy();
    appointmentModal.show();
}

function positionHoverClock(clientX, clientY) {
    if (!agendaHoverClock) {
        return;
    }

    const margin = 14;
    const maxLeft = Math.max(margin, window.innerWidth - agendaHoverClock.offsetWidth - margin);
    const maxTop = Math.max(margin, window.innerHeight - agendaHoverClock.offsetHeight - margin);
    const left = Math.min(clientX + 18, maxLeft);
    const top = Math.min(clientY + 14, maxTop);

    agendaHoverClock.style.left = `${left}px`;
    agendaHoverClock.style.top = `${top}px`;
}

function hideHoverClock() {
    if (agendaHoverClock) {
        agendaHoverClock.hidden = true;
    }
}

function showInlineNotice(message, type = 'warning') {
    if (!agendaInlineNotice) {
        return;
    }

    const allowedTypes = ['success', 'danger', 'warning', 'info'];
    const noticeType = allowedTypes.includes(type) ? type : 'warning';

    agendaInlineNotice.textContent = message;
    agendaInlineNotice.className = `agenda-inline-notice is-${noticeType}`;
    agendaInlineNotice.hidden = false;

    window.clearTimeout(showInlineNotice.timeoutId);
    showInlineNotice.timeoutId = window.setTimeout(() => {
        agendaInlineNotice.hidden = true;
    }, 3200);
}

function availabilityPresetRange(period) {
    switch (period) {
        case 'manha':
            return { start: '07:00', end: '12:00' };
        case 'tarde':
            return { start: '13:00', end: '18:00' };
        case 'dia_todo':
            return { start: '07:00', end: '22:00' };
        default:
            return null;
    }
}

function clearActiveEvents() {
    document.querySelectorAll('.calendar-event.is-active').forEach((element) => {
        element.classList.remove('is-active');
    });
}

function clearActiveAvailabilities() {
    document.querySelectorAll('.calendar-availability.is-active').forEach((element) => {
        element.classList.remove('is-active');
    });
}

function clearDropTargets() {
    document.querySelectorAll('.calendar-day.is-drop-target').forEach((element) => {
        element.classList.remove('is-drop-target');
    });
}

function setActiveEvent(appointmentIdValue) {
    clearActiveEvents();

    if (!appointmentIdValue) {
        return;
    }

    const activeEvent = document.querySelector(`.calendar-event[data-appointment-id="${appointmentIdValue}"]`);
    if (activeEvent) {
        activeEvent.classList.add('is-active');
    }
}

function setActiveAvailability(availabilityIdValue) {
    clearActiveAvailabilities();

    if (!availabilityIdValue) {
        return;
    }

    const activeAvailability = document.querySelector(`.calendar-availability[data-availability-id="${availabilityIdValue}"]`);
    if (activeAvailability) {
        activeAvailability.classList.add('is-active');
    }
}

function setScheduleMode(mode) {
    currentScheduleMode = mode;
    scheduleModeButtons.forEach((button) => {
        button.classList.toggle('is-active', button.dataset.scheduleMode === mode);
    });
    document.querySelectorAll('[data-mode-panel]').forEach((panel) => {
        panel.style.display = panel.dataset.modePanel === mode ? '' : 'none';
    });
}

function isRepeatedAvailabilityScope(scopeValue) {
    return scopeValue === 'mes_inteiro' || scopeValue === 'semana_inteira' || scopeValue === 'intervalo_datas';
}

function markDefaultAvailabilityWeekdays() {
    if (!availabilityWeekdayChecks.length) {
        return;
    }

    const hasSelectedDay = Array.from(availabilityWeekdayChecks).some((check) => check.checked);

    if (hasSelectedDay) {
        return;
    }

    availabilityWeekdayChecks.forEach((check) => {
        check.checked = ['1', '2', '3', '4', '5'].includes(String(check.value));
    });
}

function syncAvailabilityFormMode() {
    if (!availabilityForm || !availabilityPeriod || !availabilityScope) {
        return;
    }

    const preset = availabilityPresetRange(availabilityPeriod.value);
    const isCustom = preset === null;
    const isMonthScope = availabilityScope.value === 'mes_inteiro';
    const isRangeScope = availabilityScope.value === 'intervalo_datas';
    const isRepeatedScope = isRepeatedAvailabilityScope(availabilityScope.value);

    if (availabilityStartWrap) {
        availabilityStartWrap.style.display = isCustom ? '' : 'none';
    }

    if (availabilityEndWrap) {
        availabilityEndWrap.style.display = isCustom ? '' : 'none';
    }

    if (availabilityBusinessDaysWrap) {
        availabilityBusinessDaysWrap.style.display = isRepeatedScope ? '' : 'none';
    }

    if (availabilityWeekdaysWrap) {
        availabilityWeekdaysWrap.style.display = isRepeatedScope ? '' : 'none';
    }

    if (availabilityDateWrap) {
        availabilityDateWrap.style.display = isMonthScope ? 'none' : '';
    }

    if (availabilityEndDateWrap) {
        availabilityEndDateWrap.style.display = isRangeScope ? '' : 'none';
    }

    if (availabilityMonthWrap) {
        availabilityMonthWrap.style.display = isMonthScope ? '' : 'none';
    }

    if (!isCustom && availabilityStart && availabilityEnd) {
        availabilityStart.value = preset.start;
        availabilityEnd.value = preset.end;
    }

    if (availabilityStart) {
        availabilityStart.required = isCustom;
    }

    if (availabilityEnd) {
        availabilityEnd.required = isCustom;
    }

    if (availabilityDate) {
        availabilityDate.required = !isMonthScope;
    }

    if (availabilityEndDate) {
        availabilityEndDate.required = isRangeScope;
        if (isRangeScope && !availabilityEndDate.value && availabilityDate && availabilityDate.value) {
            availabilityEndDate.value = availabilityDate.value;
        }
    }

    if (availabilityMonth) {
        availabilityMonth.required = isMonthScope;
        if (isMonthScope && !availabilityMonth.value && availabilityDate && availabilityDate.value) {
            availabilityMonth.value = availabilityDate.value.slice(0, 7);
        }
    }

    if (availabilityBusinessDays && !isRepeatedScope) {
        availabilityBusinessDays.checked = false;
    }

    if (!isRepeatedScope) {
        availabilityWeekdayChecks.forEach((check) => {
            check.checked = false;
        });
    }
}

function showPatientPreference(dayPreference, timePreference) {
    if (!patientPreferenceCard || !patientPreferenceText) {
        return;
    }

    const parts = [];

    if (dayPreference) {
        parts.push(`Dia preferido: ${dayPreference}`);
    }

    if (timePreference) {
        parts.push(`Horario preferido: ${timePreference}`);
    }

    if (parts.length === 0) {
        patientPreferenceCard.style.display = 'none';
        patientPreferenceText.textContent = 'Sem preferencia cadastrada.';
        return;
    }

    patientPreferenceText.textContent = parts.join(' | ');
    patientPreferenceCard.style.display = 'block';
}

function setPatientPreferenceFields(dayPreference, timePreference, hasPatient) {
    if (appointmentPreferenceDay) {
        appointmentPreferenceDay.value = hasPatient
            ? (dayPreference || 'Nao informado')
            : '';
    }

    if (appointmentPreferenceTime) {
        appointmentPreferenceTime.value = hasPatient
            ? (timePreference || 'Nao informado')
            : '';
    }
}

function appointmentGuideIdFromData(data) {
    return parseInt(data?.atendimento_guia_id || data?.guia_atendimento_id || '0', 10) || 0;
}

async function loadAppointmentGuides(patientId, selectedGuideId = '') {
    if (!appointmentGuide) {
        return;
    }

    const safePatientId = parseInt(patientId || '0', 10) || 0;

    if (safePatientId <= 0) {
        appointmentGuide.innerHTML = '<option value="">Selecione o paciente primeiro</option>';
        appointmentGuide.value = '';
        return;
    }

    const requestId = ++appointmentGuideRequestId;
    appointmentGuide.innerHTML = '<option value="">Carregando guias...</option>';

    const params = new URLSearchParams({ paciente_id: String(safePatientId) });
    const professionalId = parseInt(appointmentProfessional?.value || '0', 10) || 0;
    const attendanceId = parseInt(appointmentGuide.dataset.attendanceId || '0', 10) || 0;

    if (professionalId > 0) {
        params.set('profissional_id', String(professionalId));
    }

    if (attendanceId > 0) {
        params.set('ignorar_atendimento_id', String(attendanceId));
    }

    try {
        const response = await fetch(`buscar_guias.php?${params.toString()}`);
        const html = await response.text();

        if (requestId !== appointmentGuideRequestId) {
            return;
        }

        appointmentGuide.innerHTML = html;

        if (selectedGuideId) {
            appointmentGuide.value = String(selectedGuideId);
        }

        syncAppointmentServiceFromGuide();
    } catch (error) {
        if (requestId === appointmentGuideRequestId) {
            appointmentGuide.innerHTML = '<option value="">Nao foi possivel carregar as guias</option>';
        }
    }
}

function syncAppointmentGuideRequirement(selectedGuideId = '') {
    if (!appointmentGuideWrap || !appointmentGuide || !appointmentStatus) {
        return;
    }

    const shouldRequireGuide = appointmentStatus.value === 'realizado';
    appointmentGuideWrap.hidden = !shouldRequireGuide;
    appointmentGuide.required = shouldRequireGuide;

    if (!shouldRequireGuide) {
        return;
    }

    const guideId = selectedGuideId || appointmentGuide.dataset.selectedGuideId || appointmentGuide.value || '';
    appointmentGuide.dataset.selectedGuideId = guideId;
    loadAppointmentGuides(appointmentPatient?.value || '', guideId);
}

function appointmentPatientDataFromFields() {
    return {
        id: appointmentPatient?.value || '',
        nome: appointmentPatient?.dataset.name || appointmentName?.value || appointmentPatientSearch?.value || '',
        telefone: appointmentPatient?.dataset.phone || appointmentPhone?.value || '',
        dia_preferencia: appointmentPatient?.dataset.prefDia || '',
        horario_preferencia: appointmentPatient?.dataset.prefHora || '',
    };
}

function setAppointmentPatientSelection(patient) {
    if (!appointmentPatient || !appointmentName || !appointmentPhone) {
        return;
    }

    if (!patient || !patient.id) {
        appointmentPatient.value = '';
        appointmentPatient.dataset.name = '';
        appointmentPatient.dataset.phone = '';
        appointmentPatient.dataset.prefDia = '';
        appointmentPatient.dataset.prefHora = '';
        appointmentName.value = '';
        appointmentPhone.value = '';
        if (appointmentPatientSearch) {
            appointmentPatientSearch.value = '';
        }
        setPatientPreferenceFields('', '', false);
        showPatientPreference('', '');
        syncAppointmentGuideRequirement('');
        updateAppointmentModalCopy();
        return;
    }

    const patientName = patient.nome || patient.paciente_nome || patient.cliente_nome || '';
    const patientPhone = patient.telefone || patient.paciente_telefone || patient.cliente_telefone || '';
    const preferenceDay = patient.dia_preferencia || patient.paciente_dia_preferencia || '';
    const preferenceTime = patient.horario_preferencia || patient.paciente_horario_preferencia || '';

    appointmentPatient.value = String(patient.id);
    appointmentPatient.dataset.name = patientName;
    appointmentPatient.dataset.phone = patientPhone;
    appointmentPatient.dataset.prefDia = preferenceDay;
    appointmentPatient.dataset.prefHora = preferenceTime;
    appointmentName.value = patientName;
    appointmentPhone.value = patientPhone;

    if (appointmentPatientSearch) {
        appointmentPatientSearch.value = patientName;
    }

    setPatientPreferenceFields(preferenceDay, preferenceTime, true);
    showPatientPreference(preferenceDay, preferenceTime);
    syncAppointmentGuideRequirement();
    updateAppointmentModalCopy();
}

function syncPatientFields() {
    if (!appointmentPatient || !appointmentName || !appointmentPhone) {
        return;
    }

    if (!appointmentPatient.value) {
        setAppointmentPatientSelection(null);
        return;
    }

    const data = appointmentPatientDataFromFields();
    appointmentName.value = data.nome;
    appointmentPhone.value = data.telefone;

    if (appointmentPatientSearch && appointmentPatientSearch.value.trim() === '') {
        appointmentPatientSearch.value = data.nome;
    }

    setPatientPreferenceFields(data.dia_preferencia, data.horario_preferencia, true);
    showPatientPreference(data.dia_preferencia, data.horario_preferencia);
    syncAppointmentGuideRequirement();
    updateAppointmentModalCopy();
}

function closeAppointmentPatientMenu() {
    if (!appointmentPatientMenu) {
        return;
    }

    appointmentPatientMenu.classList.remove('is-open');
    appointmentPatientMenu.innerHTML = '';
}

function setupAppointmentPatientAutocomplete() {
    if (!appointmentPatientSearch || !appointmentPatientMenu) {
        return;
    }

    let timer = null;
    let controller = null;

    function render(items) {
        appointmentPatientMenu.innerHTML = '';

        if (!items.length) {
            closeAppointmentPatientMenu();
            return;
        }

        items.forEach((patient) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'autocomplete-option';
            button.textContent = patient.nome;
            button.addEventListener('click', () => {
                closeAppointmentPatientMenu();
                setAppointmentPatientSelection(patient);
            });
            appointmentPatientMenu.appendChild(button);
        });

        appointmentPatientMenu.classList.add('is-open');
    }

    appointmentPatientSearch.addEventListener('input', () => {
        const term = appointmentPatientSearch.value.trim();

        if (appointmentPatient?.value && term !== (appointmentPatient.dataset.name || '')) {
            setAppointmentPatientSelection(null);
            appointmentPatientSearch.value = term;
        }

        window.clearTimeout(timer);

        if (term.length < 2) {
            closeAppointmentPatientMenu();
            return;
        }

        timer = window.setTimeout(async () => {
            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            try {
                const response = await fetch('pacientes_busca.php?q=' + encodeURIComponent(term), {
                    signal: controller.signal,
                });
                const data = await response.json();
                render(data.pacientes || []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    closeAppointmentPatientMenu();
                }
            }
        }, 180);
    });

    appointmentPatientSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAppointmentPatientMenu();
        }
    });

    document.addEventListener('click', (event) => {
        if (!appointmentPatientMenu.contains(event.target) && event.target !== appointmentPatientSearch) {
            closeAppointmentPatientMenu();
        }
    });
}

function resetAppointmentForm(dateValue = '', timeValue = '') {
    if (!appointmentForm) {
        return;
    }

    appointmentAction.value = 'create_appointment';
    appointmentId.value = '0';
    appointmentSubmitBtn.textContent = 'Salvar';

    if (appointmentDeleteBtn) {
        appointmentDeleteBtn.style.display = 'none';
    }

    if (appointmentPatient) {
        appointmentPatient.value = '';
        appointmentPatient.dataset.name = '';
        appointmentPatient.dataset.phone = '';
        appointmentPatient.dataset.prefDia = '';
        appointmentPatient.dataset.prefHora = '';
    }

    if (appointmentPatientSearch) {
        appointmentPatientSearch.value = '';
    }

    if (appointmentName) {
        appointmentName.value = '';
    }

    if (appointmentPhone) {
        appointmentPhone.value = '';
    }

    setPatientPreferenceFields('', '', false);

    if (appointmentStatus) {
        appointmentStatus.value = 'agendado';
    }

    if (appointmentGuide) {
        appointmentGuide.dataset.attendanceId = '0';
        appointmentGuide.dataset.selectedGuideId = '';
        appointmentGuide.innerHTML = '<option value="">Selecione o paciente primeiro</option>';
        appointmentGuide.value = '';
    }

    if (appointmentNotes) {
        appointmentNotes.value = '';
    }

    if (appointmentDate && dateValue) {
        appointmentDate.value = dateValue;
    }

    if (appointmentTime && timeValue) {
        appointmentTime.value = timeValue;
    }

    showPatientPreference('', '');
    syncAppointmentGuideRequirement('');
    clearActiveEvents();
    updateAppointmentModalCopy();
}

function resetAvailabilityForm(dateValue = '', startValue = '', endValue = '') {
    if (!availabilityForm) {
        return;
    }

    availabilityAction.value = 'create_availability';
    availabilityId.value = '0';
    availabilitySubmitBtn.textContent = 'Liberar';

    if (availabilityDeleteBtn) {
        availabilityDeleteBtn.style.display = 'none';
    }

    if (availabilityDate && dateValue) {
        availabilityDate.value = dateValue;
    }

    if (availabilityEndDate) {
        availabilityEndDate.value = dateValue || availabilityDate?.value || '';
    }

    if (availabilityMonth) {
        availabilityMonth.value = (dateValue || (availabilityDate ? availabilityDate.value : '') || '<?= app_h(substr($reportReferenceDate, 0, 7)) ?>').slice(0, 7);
    }

    if (availabilityBusinessDays && availabilityScope) {
        availabilityBusinessDays.checked = isRepeatedAvailabilityScope(availabilityScope.value);
    }

    markDefaultAvailabilityWeekdays();

    const preset = availabilityPresetRange(availabilityPeriod ? availabilityPeriod.value : 'personalizado');

    if (availabilityStart) {
        availabilityStart.value = preset ? preset.start : (startValue || availabilityStart.value || '08:00');
    }

    if (availabilityEnd) {
        availabilityEnd.value = preset
            ? preset.end
            : (endValue || (availabilityStart ? addMinutesToTime(availabilityStart.value || '08:00', calendarSlotMinutes) : '08:30'));
    }

    if (availabilityNotes) {
        availabilityNotes.value = '';
    }

    clearActiveAvailabilities();
    syncAvailabilityFormMode();
}

function fillAppointmentForm(data) {
    if (!appointmentForm || !data) {
        return;
    }

    appointmentAction.value = 'update_appointment';
    appointmentId.value = data.id || '';
    appointmentSubmitBtn.textContent = 'Atualizar';
    const currentGuideId = appointmentGuideIdFromData(data);

    if (appointmentGuide) {
        appointmentGuide.dataset.attendanceId = data.atendimento_id || '0';
        appointmentGuide.dataset.selectedGuideId = currentGuideId ? String(currentGuideId) : '';
    }

    if (appointmentDeleteBtn) {
        appointmentDeleteBtn.style.display = data.id ? 'inline-flex' : 'none';
    }

    setAppointmentPatientSelection({
        id: data.cliente_id || '',
        nome: data.paciente_nome || data.cliente_nome || '',
        telefone: data.paciente_telefone || data.cliente_telefone || '',
        dia_preferencia: data.paciente_dia_preferencia || '',
        horario_preferencia: data.paciente_horario_preferencia || '',
    });

    if (appointmentService) {
        appointmentService.value = data.servico_id || '';
    }

    if (appointmentDate) {
        appointmentDate.value = data.data_agendamento || '';
    }

    if (appointmentTime) {
        appointmentTime.value = formatTimeBr(data.hora_inicio || '');
    }

    if (appointmentStatus) {
        appointmentStatus.value = data.status || 'agendado';
    }

    syncAppointmentGuideRequirement(currentGuideId ? String(currentGuideId) : '');

    if (appointmentNotes) {
        appointmentNotes.value = data.observacoes || '';
    }

    setActiveEvent(data.id || '');
    setScheduleMode('appointment');
    updateAppointmentModalCopy();
}

function fillAvailabilityForm(data) {
    if (!availabilityForm || !data) {
        return;
    }

    availabilityAction.value = 'update_availability';
    availabilityId.value = data.id || '';
    availabilitySubmitBtn.textContent = 'Atualizar';

    if (availabilityScope) {
        availabilityScope.value = 'data_unica';
    }

    if (availabilityPeriod) {
        availabilityPeriod.value = 'personalizado';
    }

    if (availabilityDeleteBtn) {
        availabilityDeleteBtn.style.display = data.id ? 'inline-flex' : 'none';
    }

    if (availabilityDate) {
        availabilityDate.value = data.data_disponivel || '';
    }

    if (availabilityEndDate) {
        availabilityEndDate.value = data.data_disponivel || '';
    }

    if (availabilityMonth) {
        availabilityMonth.value = String(data.data_disponivel || '').slice(0, 7);
    }

    if (availabilityStart) {
        availabilityStart.value = formatTimeBr(data.hora_inicio || '');
    }

    if (availabilityEnd) {
        availabilityEnd.value = formatTimeBr(data.hora_fim || '');
    }

    if (availabilityNotes) {
        availabilityNotes.value = data.observacoes || '';
    }

    syncAvailabilityFormMode();
    setActiveAvailability(data.id || '');
    setScheduleMode('availability');
}

function nextAnimationFrame() {
    return new Promise((resolve) => {
        window.requestAnimationFrame(() => window.requestAnimationFrame(resolve));
    });
}

function calendarCaptureTarget() {
    return document.querySelector('.agenda-calendar-card') || agendaCalendarReport;
}

function setCalendarActionsBusy(isBusy) {
    [printCalendarBtn, exportPdfBtn, exportJpgBtn, shareWhatsappJpgBtn].forEach((button) => {
        if (button) {
            button.disabled = isBusy;
        }
    });
}

async function buildCalendarReportCanvas() {
    const target = calendarCaptureTarget();

    if (!target || typeof window.html2canvas !== 'function') {
        showInlineNotice('Nao foi possivel preparar a imagem da agenda.', 'danger');
        return null;
    }

    const scrollWrappers = Array.from(target.querySelectorAll('.agenda-calendar-wrapper'));
    const scrollPositions = scrollWrappers.map((element) => ({
        element,
        top: element.scrollTop,
        left: element.scrollLeft,
    }));

    try {
        setCalendarActionsBusy(true);
        showInlineNotice('Preparando a agenda completa...', 'info');
        scrollWrappers.forEach((element) => {
            element.scrollTop = 0;
            element.scrollLeft = 0;
        });
        document.body.classList.add('is-capturing-agenda');
        await nextAnimationFrame();

        const width = Math.ceil(target.scrollWidth);
        const height = Math.ceil(target.scrollHeight);

        return await window.html2canvas(target, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            width,
            height,
            windowWidth: Math.max(width, document.documentElement.clientWidth),
            windowHeight: Math.max(height, document.documentElement.clientHeight),
            scrollX: 0,
            scrollY: -window.scrollY,
        });
    } catch (error) {
        showInlineNotice('Nao foi possivel gerar a imagem da agenda.', 'danger');
        return null;
    } finally {
        document.body.classList.remove('is-capturing-agenda');
        scrollPositions.forEach(({ element, top, left }) => {
            element.scrollTop = top;
            element.scrollLeft = left;
        });
        setCalendarActionsBusy(false);
    }
}

function calendarReportFileName(extension) {
    const base = agendaCalendarReport?.dataset.fileName || 'agenda-semanal';
    return `${base}.${extension}`;
}

function downloadCanvasAsJpg(canvas) {
    if (!canvas) {
        return;
    }

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/jpeg', 0.96);
    link.download = calendarReportFileName('jpg');
    link.click();
}

function canvasToJpegFile(canvas) {
    return new Promise((resolve) => {
        if (typeof File !== 'function') {
            resolve(null);
            return;
        }

        canvas.toBlob((blob) => {
            resolve(blob ? new File([blob], calendarReportFileName('jpg'), { type: 'image/jpeg' }) : null);
        }, 'image/jpeg', 0.96);
    });
}

async function printCalendarReport() {
    const printWindow = window.open('', '_blank', 'width=1280,height=900');

    if (!printWindow) {
        showInlineNotice('Permita a abertura de janelas para imprimir a agenda.', 'warning');
        return;
    }

    printWindow.document.write(`
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Preparando impressao</title>
            <style>
                body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Arial, sans-serif; color: #0f4c5c; }
            </style>
        </head>
        <body>Preparando agenda completa...</body>
        </html>
    `);
    printWindow.document.close();

    const canvas = await buildCalendarReportCanvas();

    if (!canvas) {
        printWindow.close();
        return;
    }

    const imageUrl = canvas.toDataURL('image/jpeg', 0.96);

    printWindow.document.open();
    printWindow.document.write(`
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Agenda semanal</title>
            <style>
                @page { size: landscape; margin: 8mm; }
                html, body { margin: 0; background: #fff; }
                body { padding: 8mm; }
                img { display: block; width: 100%; height: auto; }
            </style>
        </head>
        <body>
            <img src="${imageUrl}" alt="Agenda semanal" onload="window.focus(); setTimeout(function(){ window.print(); }, 150);">
        </body>
        </html>
    `);
    printWindow.document.close();
    showInlineNotice('Imagem completa enviada para impressao.', 'success');
}

async function exportCalendarAsJpg() {
    const canvas = await buildCalendarReportCanvas();

    if (!canvas) {
        return;
    }

    downloadCanvasAsJpg(canvas);
    showInlineNotice('JPG completo da agenda baixado.', 'success');
}

async function exportCalendarAsPdf() {
    const canvas = await buildCalendarReportCanvas();

    if (!canvas || !window.jspdf?.jsPDF) {
        showInlineNotice('Nao foi possivel gerar o PDF da agenda.', 'danger');
        return;
    }

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('landscape', 'mm', 'a4');
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const maxWidth = pageWidth - 12;
    const maxHeight = pageHeight - 12;
    const scale = Math.min(maxWidth / canvas.width, maxHeight / canvas.height);
    const renderWidth = canvas.width * scale;
    const renderHeight = canvas.height * scale;

    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 6, 6, renderWidth, renderHeight);
    pdf.save(calendarReportFileName('pdf'));
    showInlineNotice('PDF da agenda baixado.', 'success');
}

async function shareCalendarJpgToWhatsapp() {
    const canTryNativeShare = Boolean(navigator.share && navigator.canShare && typeof File === 'function');
    const fallbackWhatsappWindow = canTryNativeShare ? null : window.open('', '_blank');
    const canvas = await buildCalendarReportCanvas();

    if (!canvas) {
        fallbackWhatsappWindow?.close();
        return;
    }

    const file = await canvasToJpegFile(canvas);

    if (file && navigator.canShare?.({ files: [file] }) && navigator.share) {
        try {
            await navigator.share({
                files: [file],
                title: 'Agenda semanal',
                text: 'Agenda semanal em JPG.',
            });
            showInlineNotice('JPG enviado para compartilhamento.', 'success');
            return;
        } catch (error) {
            showInlineNotice('Compartilhamento cancelado. Baixei o JPG para anexar manualmente.', 'info');
        }
    } else {
        showInlineNotice('Seu navegador nao permite compartilhar arquivo direto. Baixei o JPG para anexar no WhatsApp.', 'info');
    }

    downloadCanvasAsJpg(canvas);
    if (fallbackWhatsappWindow) {
        fallbackWhatsappWindow.location.href = 'https://web.whatsapp.com/';
    } else {
        window.open('https://web.whatsapp.com/', '_blank');
    }
}

function submitMoveAppointment(appointmentData, targetDate, targetTime) {
    if (!appointmentData || !appointmentData.id) {
        return;
    }

    document.getElementById('moveAppId').value = appointmentData.id;
    document.getElementById('moveAppDate').value = targetDate;
    document.getElementById('moveAppTime').value = targetTime;
    document.getElementById('moveAppointmentForm').submit();
}

function calculateDropTime(dayElement, clientY) {
    const startMinutes = parseTimeToMinutes(calendarShell?.dataset.dayStart || '07:00');
    const slots = dayElement.querySelectorAll('.calendar-slot').length || 1;
    const rect = dayElement.getBoundingClientRect();
    const rowHeight = rect.height / slots;
    const offsetY = Math.max(0, Math.min(clientY - rect.top, rect.height - 1));
    const slotIndex = Math.min(slots - 1, Math.max(0, Math.floor(offsetY / rowHeight)));

    return minutesToTime(startMinutes + (slotIndex * calendarSlotMinutes));
}

function resolveDropDay(element) {
    if (!element) {
        return null;
    }

    return element.classList.contains('calendar-day') ? element : element.closest('.calendar-day');
}

function showHoverClock(event, element) {
    if (!agendaHoverClock) {
        return;
    }

    const dayElement = resolveDropDay(element);

    if (!dayElement) {
        hideHoverClock();
        return;
    }

    const timeValue = calculateDropTime(dayElement, event.clientY);
    agendaHoverClock.textContent = timeValue;
    agendaHoverClock.hidden = false;
    positionHoverClock(event.clientX, event.clientY);
}

function bindDropZone(element) {
    element.addEventListener('dragover', (event) => {
        if (!draggedAppointment) {
            return;
        }

        const dayElement = resolveDropDay(element);
        if (!dayElement || dayElement.dataset.readonly === '1') {
            return;
        }

        event.preventDefault();
        clearDropTargets();
        dayElement.classList.add('is-drop-target');
    });

    element.addEventListener('drop', (event) => {
        if (!draggedAppointment) {
            return;
        }

        const dayElement = resolveDropDay(element);
        if (!dayElement || dayElement.dataset.readonly === '1') {
            return;
        }

        event.preventDefault();
        clearDropTargets();

        const targetDate = dayElement.dataset.colDate || '';
        const targetTime = calculateDropTime(dayElement, event.clientY);
        const currentTime = formatTimeBr(draggedAppointment.hora_inicio || '');

        if (targetDate === draggedAppointment.data_agendamento && targetTime === currentTime) {
            draggedAppointment = null;
            return;
        }

        const patientName = draggedAppointment.paciente_nome || draggedAppointment.cliente_nome || 'Paciente';

        if (window.confirm(`Mover ${patientName} para ${targetDate} as ${targetTime}?`)) {
            submitMoveAppointment(draggedAppointment, targetDate, targetTime);
        }

        draggedAppointment = null;
    });
}

document.querySelectorAll('.calendar-availability').forEach((availabilityElement) => {
    const dateValue = availabilityElement.dataset.date || '';
    const startValue = availabilityElement.dataset.start || '';
    const endValue = availabilityElement.dataset.end || '';

    if (!dateValue || !startValue || !endValue) {
        return;
    }

    if (!availabilityRangesByDate[dateValue]) {
        availabilityRangesByDate[dateValue] = [];
    }
    availabilityRangesByDate[dateValue].push({
        start: parseTimeToMinutes(startValue),
        end: parseTimeToMinutes(endValue),
    });
});

setupAppointmentPatientAutocomplete();

if (appointmentService) {
    appointmentService.addEventListener('change', updateAppointmentModalCopy);
}

if (appointmentDate) {
    appointmentDate.addEventListener('change', updateAppointmentModalCopy);
}

if (appointmentTime) {
    appointmentTime.addEventListener('change', updateAppointmentModalCopy);
}

if (appointmentStatus) {
    appointmentStatus.addEventListener('change', () => {
        syncAppointmentGuideRequirement();
        updateAppointmentModalCopy();
    });
}

if (appointmentGuide) {
    appointmentGuide.addEventListener('change', () => {
        appointmentGuide.dataset.selectedGuideId = appointmentGuide.value || '';
        syncAppointmentServiceFromGuide();
    });
}

if (appointmentForm) {
    appointmentForm.addEventListener('submit', (event) => {
        if (appointmentAction?.value === 'delete_appointment') {
            return;
        }

        if (!appointmentPatient?.value) {
            event.preventDefault();
            showInlineNotice('Escolha um paciente da lista de autocomplete.', 'warning');
            appointmentPatientSearch?.focus();
            return;
        }

        if (appointmentStatus?.value === 'realizado' && (!appointmentGuide || !appointmentGuide.value)) {
            event.preventDefault();
            syncAppointmentGuideRequirement();
            showInlineNotice('Informe a guia do atendimento para marcar como realizado.', 'warning');
            appointmentGuide?.focus();
        }
    });
}

if (appointmentModalElement) {
    appointmentModalElement.addEventListener('shown.bs.modal', () => {
        updateAppointmentModalCopy();
        appointmentPatientSearch?.focus();
    });

    appointmentModalElement.addEventListener('hidden.bs.modal', () => {
        restoreAppointmentFormToHost();

        if (appointmentReturnTo && autoOpenSelectedAppointmentModal) {
            window.location.href = appointmentReturnTo;
        }
    });
}

if (calendarShell) {
    calendarShell.addEventListener('mousemove', (event) => {
        const target = event.target instanceof Element
            ? event.target.closest('.calendar-slot, .calendar-event, .calendar-availability, .calendar-day')
            : null;

        if (!target) {
            hideHoverClock();
            return;
        }

        showHoverClock(event, target);
    });

    calendarShell.addEventListener('mouseleave', hideHoverClock);
}

if (availabilityScope) {
    availabilityScope.addEventListener('change', () => {
        if (availabilityBusinessDays) {
            availabilityBusinessDays.checked = isRepeatedAvailabilityScope(availabilityScope.value);
        }
        markDefaultAvailabilityWeekdays();
        syncAvailabilityFormMode();
    });
}

if (availabilityPeriod) {
    availabilityPeriod.addEventListener('change', syncAvailabilityFormMode);
}

if (printCalendarBtn) {
    printCalendarBtn.addEventListener('click', printCalendarReport);
}

if (exportJpgBtn) {
    exportJpgBtn.addEventListener('click', exportCalendarAsJpg);
}

if (exportPdfBtn) {
    exportPdfBtn.addEventListener('click', exportCalendarAsPdf);
}

if (shareWhatsappJpgBtn) {
    shareWhatsappJpgBtn.addEventListener('click', shareCalendarJpgToWhatsapp);
}

if (autoSubmitProfessionalSelect && calendarFilterForm && calendarProfessional && !calendarProfessional.disabled) {
    calendarProfessional.addEventListener('change', () => {
        calendarFilterForm.submit();
    });
}

function updateCalendarServiceOptions(professionalId, selectedServiceId = '') {
    if (!calendarService || !professionalId) {
        return;
    }

    fetch('buscar_servicos_profissional.php?profissional_id=' + encodeURIComponent(professionalId))
        .then((response) => response.json())
        .then((services) => {
            calendarService.innerHTML = '<option value="">Selecione o servico</option>';
            services.forEach((service) => {
                const option = document.createElement('option');
                option.value = service.id;
                option.dataset.tipo = service.tipo_agendamento || 'individual';
                option.dataset.capacidade = service.capacidade_agendamento || '1';
                option.textContent = `${service.nome} - ${option.dataset.tipo === 'grupo' ? 'Grupo' : 'Individual'}`;

                if (String(service.id) === String(selectedServiceId)) {
                    option.selected = true;
                }

                calendarService.appendChild(option);
            });

            syncCalendarServiceVisibility();
        })
        .catch(() => {});
}

function syncCalendarServiceVisibility() {
    if (!calendarService || !calendarServiceField) {
        return;
    }

    const serviceOptions = Array.from(calendarService.options).filter((option) => option.value !== '');
    const groupOptions = serviceOptions.filter((option) => (option.dataset.tipo || 'individual') === 'grupo');
    const hasIndividual = serviceOptions.some((option) => (option.dataset.tipo || 'individual') !== 'grupo');
    const shouldShow = groupOptions.length + (hasIndividual ? 1 : 0) > 1;

    if (!shouldShow && groupOptions.length === 1 && !hasIndividual) {
        groupOptions[0].selected = true;
    }

    calendarServiceField.style.display = shouldShow ? '' : 'none';

    if (calendarGroupQuickField) {
        const selected = calendarService.selectedOptions?.[0];
        const selectedIsGroup = (selected?.dataset.tipo || 'individual') === 'grupo';
        calendarGroupQuickField.style.display = groupOptions.length > 0 && selectedIsGroup ? '' : 'none';
    }
}

function syncAppointmentServiceFromGuide() {
    if (!appointmentGuide || !appointmentService || !appointmentGuide.value) {
        return;
    }

    const selectedOption = appointmentGuide.options[appointmentGuide.selectedIndex];
    const guideServiceId = parseInt(selectedOption?.dataset?.servicoId || '0', 10) || 0;

    if (guideServiceId > 0 && appointmentService.querySelector(`option[value="${guideServiceId}"]`)) {
        appointmentService.value = String(guideServiceId);
        updateAppointmentModalCopy();
    }
}

if (calendarProfessional && calendarService && !calendarProfessional.disabled) {
    calendarProfessional.addEventListener('change', () => {
        updateCalendarServiceOptions(calendarProfessional.value);
    });
}

if (calendarService) {
    calendarService.addEventListener('change', syncCalendarServiceVisibility);
}

if (calendarFilterForm && calendarService) {
    calendarFilterForm.addEventListener('submit', () => {
        const selected = calendarService.selectedOptions?.[0];
        const scheduleType = selected?.dataset.tipo || 'individual';
        calendarFilterForm.action = scheduleType === 'grupo'
            ? 'secretaria_agenda_grupo.php'
            : 'secretaria_agenda.php';

        if (scheduleType === 'grupo' && calendarGroupQuick && calendarGroupQuick.checked) {
            calendarGroupQuick.disabled = false;
        }
    });
}

syncCalendarServiceVisibility();

scheduleModeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        setScheduleMode(button.dataset.scheduleMode || 'appointment');
    });
});

if (appointmentResetBtn) {
    appointmentResetBtn.addEventListener('click', () => {
        resetAppointmentForm(appointmentDate ? appointmentDate.value : '', appointmentTime ? appointmentTime.value : '');
    });
}

if (appointmentDeleteBtn && appointmentForm) {
    appointmentDeleteBtn.addEventListener('click', () => {
        if (!appointmentId.value) {
            return;
        }

        if (window.confirm('Excluir este agendamento?')) {
            appointmentAction.value = 'delete_appointment';
            appointmentForm.submit();
        }
    });
}

if (appointmentWhatsappBtn) {
    appointmentWhatsappBtn.addEventListener('click', (event) => {
        const whatsappUrl = buildAppointmentWhatsappUrl();

        if (!whatsappUrl) {
            event.preventDefault();
            showInlineNotice('Cadastre um telefone no paciente para enviar no WhatsApp.', 'warning');
            return;
        }

        appointmentWhatsappBtn.href = whatsappUrl;
    });
}

if (availabilityResetBtn) {
    availabilityResetBtn.addEventListener('click', () => {
        resetAvailabilityForm(availabilityDate ? availabilityDate.value : '', availabilityStart ? availabilityStart.value : '', availabilityEnd ? availabilityEnd.value : '');
    });
}

if (availabilityDeleteBtn && availabilityForm) {
    availabilityDeleteBtn.addEventListener('click', () => {
        if (!availabilityId.value) {
            return;
        }

        if (window.confirm('Excluir esta faixa de disponibilidade?')) {
            availabilityAction.value = 'delete_availability';
            availabilityForm.submit();
        }
    });
}

if (availabilityDeletePeriodBtn && availabilityForm) {
    availabilityDeletePeriodBtn.addEventListener('click', () => {
        const scopeLabel = availabilityScope?.selectedOptions?.[0]?.textContent || 'periodo selecionado';
        const periodLabel = availabilityPeriod?.selectedOptions?.[0]?.textContent || 'horario selecionado';

        if (window.confirm(`Excluir liberacao de agenda para ${scopeLabel.toLowerCase()} / ${periodLabel.toLowerCase()}? Onde houver agendamento, a liberacao sera mantida.`)) {
            availabilityAction.value = 'delete_availability_period';
            availabilityForm.submit();
        }
    });
}

document.querySelectorAll('.calendar-slot').forEach((slot) => {
    slot.addEventListener('click', () => {
        if (slot.dataset.readonly === '1') {
            return;
        }

        if (currentScheduleMode === 'availability' && canManageAvailability) {
            if (availabilityScope) {
                availabilityScope.value = 'data_unica';
            }

            if (availabilityPeriod) {
                availabilityPeriod.value = 'personalizado';
            }

            syncAvailabilityFormMode();
            resetAvailabilityForm(slot.dataset.date, slot.dataset.time, addMinutesToTime(slot.dataset.time || '08:00', calendarSlotMinutes));
            return;
        }

        if (canManageAppointments) {
            resetAppointmentForm(slot.dataset.date, slot.dataset.time);
        }
    });

    slot.addEventListener('dblclick', (event) => {
        event.preventDefault();

        if (slot.dataset.readonly === '1' || !canManageAppointments) {
            return;
        }

        if (!isBookableSlot(slot.dataset.date || '', slot.dataset.time || '')) {
            showInlineNotice(appointmentUnavailableMessage);
            return;
        }

        resetAppointmentForm(slot.dataset.date || '', slot.dataset.time || '');
        openAppointmentModal();
    });
});

document.querySelectorAll('.calendar-availability').forEach((availabilityElement) => {
    availabilityElement.addEventListener('click', (event) => {
        event.preventDefault();

        if (currentScheduleMode === 'availability' && canManageAvailability) {
            fillAvailabilityForm(JSON.parse(availabilityElement.dataset.json || '{}'));
            return;
        }

        if (!canManageAppointments) {
            return;
        }

        const availabilityData = JSON.parse(availabilityElement.dataset.json || '{}');
        const dayElement = resolveDropDay(availabilityElement);
        const targetDate = dayElement?.dataset.colDate || availabilityData.data_disponivel || '';
        const targetTime = dayElement
            ? calculateDropTime(dayElement, event.clientY)
            : formatTimeBr(availabilityData.hora_inicio || '08:00');

        resetAppointmentForm(targetDate, targetTime);
    });

    availabilityElement.addEventListener('dblclick', (event) => {
        event.preventDefault();

        if (!canManageAppointments) {
            return;
        }

        const availabilityData = JSON.parse(availabilityElement.dataset.json || '{}');
        const dayElement = resolveDropDay(availabilityElement);
        const targetDate = dayElement?.dataset.colDate || availabilityData.data_disponivel || '';
        const targetTime = dayElement
            ? calculateDropTime(dayElement, event.clientY)
            : formatTimeBr(availabilityData.hora_inicio || '08:00');

        resetAppointmentForm(targetDate, targetTime);
        openAppointmentModal();
    });
});

document.querySelectorAll('.calendar-event').forEach((eventElement) => {
    eventElement.addEventListener('click', (event) => {
        event.preventDefault();

        if (!canManageAppointments) {
            return;
        }

        fillAppointmentForm(JSON.parse(eventElement.dataset.json || '{}'));
    });

    eventElement.addEventListener('dblclick', (event) => {
        event.preventDefault();

        if (!canManageAppointments) {
            return;
        }

        const appointmentData = JSON.parse(eventElement.dataset.json || '{}');
        fillAppointmentForm(appointmentData);
        openAppointmentModal();
    });

    eventElement.addEventListener('dragstart', (event) => {
        draggedAppointment = JSON.parse(eventElement.dataset.json || '{}');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(draggedAppointment.id || ''));
        setTimeout(() => {
            clearDropTargets();
        }, 0);
    });

    eventElement.addEventListener('dragend', () => {
        draggedAppointment = null;
        clearDropTargets();
    });
});

document.querySelectorAll('.calendar-day, .calendar-availability, .calendar-event').forEach((element) => {
    bindDropZone(element);
});

if (selectedAppointmentData) {
    fillAppointmentForm(selectedAppointmentData);
}

if (selectedAvailabilityData) {
    fillAvailabilityForm(selectedAvailabilityData);
}

if (scheduleModeButtons.length > 0) {
    setScheduleMode(currentScheduleMode);
}

syncAvailabilityFormMode();
syncPatientFields();

if (initialFlashMessage) {
    showInlineNotice(initialFlashMessage, initialFlashType);
}

if (autoOpenSelectedAppointmentModal && selectedAppointmentData) {
    openAppointmentModal();
} else if (autoOpenCalendarFilterModal && calendarFilterModal) {
    calendarFilterModal.show();
}
</script>
