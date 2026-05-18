<?php
include 'config/db.php';

use Clinic\Repositories\ScheduleRepository;
use Clinic\Services\ScheduleService;

require_once __DIR__ . '/app/Views/schedule/render.php';

function app_schedule_safe_return_to(?string $returnTo): string
{
    $returnTo = trim((string) $returnTo);

    if ($returnTo === '') {
        return '';
    }

    $parts = parse_url($returnTo);

    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return '';
    }

    $path = (string) ($parts['path'] ?? '');

    if ($path !== 'agenda_lista_agendamentos.php' && $path !== '/clinica_fisiolife/agenda_lista_agendamentos.php') {
        return '';
    }

    return $returnTo;
}

$pdo = app_pdo();
$scheduleRepository = new ScheduleRepository($pdo);
$scheduleService = new ScheduleService($pdo, $scheduleRepository);
$currentUser = app_current_user() ?? [];
$statuses = app_schedule_statuses();
$professionals = $scheduleRepository->professionalsWithServices();
$patients = [];
$allowedProfessionalIds = array_map(static fn (array $professional): int => (int) $professional['id'], $professionals);
$professionalChosenInRequest = trim((string) app_request_query('professional_id', '')) !== '';
$selectedProfessionalId = app_query_int('professional_id') ?: (int) ($allowedProfessionalIds[0] ?? 0);
$professionalScopeId = app_is_professional_user() ? (app_current_professional_id() ?? 0) : null;
$reportReferenceDate = app_request_query('report_date', date('Y-m-d')) ?? date('Y-m-d');
$reportReferenceDate = strtotime($reportReferenceDate) ? date('Y-m-d', strtotime($reportReferenceDate)) : date('Y-m-d');

if ($professionalScopeId !== null) {
    $professionals = array_values(array_filter(
        $professionals,
        static fn (array $professional): bool => (int) ($professional['id'] ?? 0) === $professionalScopeId
    ));
    $allowedProfessionalIds = $professionalScopeId > 0 ? [$professionalScopeId] : [];
    $selectedProfessionalId = $professionalScopeId;
    $professionalChosenInRequest = true;
}

if ($selectedProfessionalId > 0 && !in_array($selectedProfessionalId, $allowedProfessionalIds, true)) {
    $selectedProfessionalId = (int) ($allowedProfessionalIds[0] ?? 0);
}

$currentProfessional = $selectedProfessionalId > 0 ? $scheduleRepository->findProfessional($selectedProfessionalId) : null;
$weekStart = app_week_start(app_request_query('week_start', date('Y-m-d')));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$selectedAppointmentId = app_query_int('appointment_id') ?: null;
$appointmentReturnTo = app_schedule_safe_return_to(
    app_request_method() === 'POST'
        ? (app_request_post('return_to', '') ?? '')
        : (app_request_query('return_to', '') ?? '')
);
$selectedAvailabilityId = null;
$canManageAppointments = $scheduleService->canManageAppointments($currentUser);
$canManageAvailability = false;
$whatsappUrl = $_SESSION['app_whatsapp_url'] ?? null;
unset($_SESSION['app_whatsapp_url']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (app_is_professional_user()) {
        app_flash('warning', 'A agenda do profissional esta disponivel somente para consulta.');
        app_redirect('secretaria_agenda.php?' . app_build_query([
            'professional_id' => $selectedProfessionalId,
            'week_start' => $weekStart,
            'report_date' => $reportReferenceDate,
        ]));
    }

    $action = app_request_post('action', '') ?? '';
    $skipWhatsapp = app_request_post('skip_whatsapp', '0') === '1';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'create_appointment') {
        $result = $scheduleService->createAppointment($_POST, $currentUser);
    } elseif ($action === 'update_appointment') {
        $result = $scheduleService->updateAppointment(app_post_int('appointment_id'), $_POST, $currentUser);
    } elseif ($action === 'move_appointment') {
        $result = $scheduleService->moveAppointment(app_post_int('appointment_id'), $_POST, $currentUser);
    } elseif ($action === 'cancel_appointment') {
        $result = $scheduleService->cancelAppointment(app_post_int('appointment_id'), $currentUser);
    } elseif ($action === 'delete_appointment') {
        $result = $scheduleService->deleteAppointment(app_post_int('appointment_id'), $currentUser);
    }

    if (!$skipWhatsapp && !empty($result['whatsapp_url'])) {
        $_SESSION['app_whatsapp_url'] = $result['whatsapp_url'];
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    $redirectProfessionalId = (int) ($_POST['profissional_id'] ?? $selectedProfessionalId);
    $redirectQuery = [
        'professional_id' => $redirectProfessionalId,
        'week_start' => $weekStart,
        'report_date' => $reportReferenceDate,
    ];

    if (!($result['ok'] ?? false) && $action === 'update_appointment') {
        $redirectQuery['appointment_id'] = app_post_int('appointment_id');
    }

    if (($result['ok'] ?? false) && $appointmentReturnTo !== '') {
        app_redirect($appointmentReturnTo);
    }

    if ($appointmentReturnTo !== '') {
        $redirectQuery['return_to'] = $appointmentReturnTo;
    }

    app_redirect('secretaria_agenda.php?' . app_build_query($redirectQuery));
}

$appointmentFilters = [
    'guia_id' => app_query_int('guia_id'),
    'paciente_id' => app_query_int('paciente_id'),
    'profissional_id' => app_query_int('profissional_filtro_id'),
    'status' => app_request_query('status', '') ?? '',
];

if ($professionalScopeId !== null) {
    $appointmentFilters['profissional_id'] = $professionalScopeId > 0 ? $professionalScopeId : 0;
}

$appointmentPageData = $scheduleRepository->paginateAppointments($appointmentFilters, max(1, app_query_int('appointment_page', 1)), 8, $professionalScopeId);
$appointments = $appointmentPageData['items'];
$appointmentPagination = $appointmentPageData['pagination'];
$appointmentPagination['query'] = array_merge($appointmentPagination['query'], [
    'week_start' => $weekStart,
    'professional_id' => $selectedProfessionalId,
]);
$selectedAppointment = $selectedAppointmentId ? $scheduleRepository->findAppointment($selectedAppointmentId) : null;

if ($professionalScopeId !== null && $selectedAppointment && (int) ($selectedAppointment['profissional_id'] ?? 0) !== $professionalScopeId) {
    $selectedAppointment = null;
    $selectedAppointmentId = null;
}

$appointmentFormDateValue = (string) ($selectedAppointment['data_agendamento'] ?? $weekStart);
$appointmentFormTimeValue = app_time_br((string) ($selectedAppointment['hora_inicio'] ?? '08:00:00'));
$selectedAvailability = null;
$selectedProfessionalServices = $selectedProfessionalId > 0 ? $scheduleRepository->servicesForProfessional($selectedProfessionalId) : [];
$selectedCalendarServiceId = app_query_int('service_id');
$selectedProfessionalServiceIds = array_map(static fn (array $service): int => (int) ($service['id'] ?? 0), $selectedProfessionalServices);

if ($selectedCalendarServiceId > 0 && !in_array($selectedCalendarServiceId, $selectedProfessionalServiceIds, true)) {
    $selectedCalendarServiceId = 0;
}

if ($selectedCalendarServiceId <= 0 && count($selectedProfessionalServices) === 1) {
    $selectedCalendarServiceId = (int) ($selectedProfessionalServices[0]['id'] ?? 0);
}

foreach ($selectedProfessionalServices as $calendarService) {
    if (!app_is_professional_user()
        && $professionalChosenInRequest
        && $selectedCalendarServiceId > 0
        && (int) $calendarService['id'] === $selectedCalendarServiceId
        && ($calendarService['tipo_agendamento'] ?? 'individual') === 'grupo') {
        app_redirect('secretaria_agenda_grupo.php?' . app_build_query([
            'professional_id' => $selectedProfessionalId,
            'service_id' => $selectedCalendarServiceId,
            'week_start' => $weekStart,
            'modelo' => app_request_query('modelo', '') === 'rapido' ? 'rapido' : '',
        ]));
    }
}
$calendarFilterServices = $selectedProfessionalServices;
$calendarIndividualServices = array_values(array_filter(
    $selectedProfessionalServices,
    static fn (array $service): bool => ($service['tipo_agendamento'] ?? 'individual') !== 'grupo'
));
$calendarGroupServices = array_values(array_filter(
    $selectedProfessionalServices,
    static fn (array $service): bool => ($service['tipo_agendamento'] ?? 'individual') === 'grupo'
));
$showCalendarServiceSelect = count($selectedProfessionalServices) > 1;
$calendarFilterRequiresService = $showCalendarServiceSelect;
$calendarFilterNeedsService = !app_is_professional_user()
    && $professionalChosenInRequest
    && $calendarFilterRequiresService
    && $selectedCalendarServiceId <= 0
    && $selectedAppointment === null;
$calendarFilterMessage = $calendarFilterNeedsService
    ? 'Este profissional possui mais de um servico. Escolha o servico para continuar.'
    : '';
$weekDays = app_week_days($weekStart);
$calendarData = $selectedProfessionalId > 0 ? $scheduleRepository->calendar($weekStart, $selectedProfessionalId) : ['availabilities' => [], 'appointments' => []];
$calendarNavigationQuery = ['report_date' => $reportReferenceDate];
$agendaWhatsappLinks = [
    'day' => $selectedProfessionalId > 0 ? $scheduleService->buildProfessionalAgendaWhatsappUrl($selectedProfessionalId, 'day', $reportReferenceDate) : null,
    'week' => $selectedProfessionalId > 0 ? $scheduleService->buildProfessionalAgendaWhatsappUrl($selectedProfessionalId, 'week', $reportReferenceDate) : null,
    'month' => $selectedProfessionalId > 0 ? $scheduleService->buildProfessionalAgendaWhatsappUrl($selectedProfessionalId, 'month', $reportReferenceDate) : null,
];
$reportPeriodLabels = [
    'day' => app_date_br($reportReferenceDate),
    'week' => app_date_br(app_week_start($reportReferenceDate)) . ' a ' . app_date_br(date('Y-m-d', strtotime(app_week_start($reportReferenceDate) . ' +6 days'))),
    'month' => app_month_label(date('Y-m', strtotime($reportReferenceDate))),
];
$scheduleMetricsHtml = render_schedule_metrics($calendarData);
$serviceDurations = array_values(array_filter(
    array_map(static fn (array $service): int => (int) ($service['tempo_minutos'] ?? 0), $selectedProfessionalServices),
    static fn (int $minutes): bool => $minutes > 0
));
$minimumServiceDuration = !empty($serviceDurations) ? min($serviceDurations) : 30;
$calendarSlotMinutes = $minimumServiceDuration >= 60 ? 60 : 30;
$appointmentTimeStepSeconds = $calendarSlotMinutes * 60;
$calendarDisplayStart = '08:00:00';
$calendarDisplayEnd = '18:00:00';
$calendarPixelsPerMinute = $calendarSlotMinutes >= 60 ? 0.52 : 0.9;
$calendarHtml = render_week_calendar($weekDays, $calendarData, $selectedAppointmentId, $canManageAppointments, $canManageAvailability, $weekStart, $selectedAvailabilityId, $selectedProfessionalId, $calendarNavigationQuery, $calendarDisplayStart, $calendarDisplayEnd, $calendarPixelsPerMinute, $calendarSlotMinutes);

$pageTitle = app_is_professional_user() ? 'Minha Agenda' : 'Agenda Clinica';
$operationTitle = 'Operacao de agenda';
$operationDescription = app_is_professional_user()
    ? 'Consulta da agenda do profissional.'
    : 'Cadastro rapido de agendamentos da secretaria.';
$calendarTitle = app_is_professional_user() ? 'Minha agenda' : 'Grade semanal';
$calendarTitleProfessional = (string) ($currentProfessional['nome'] ?? '');
$calendarDescription = app_is_professional_user()
    ? 'Consulta dos horarios e pacientes da sua agenda.'
    : 'Passe o mouse para ver o horario. Duplo clique abre um popup para cadastrar ou editar o agendamento.';
$calendarResetPath = 'secretaria_agenda.php?' . app_build_query([
    'professional_id' => $selectedProfessionalId,
    'report_date' => $reportReferenceDate,
]);
$autoSubmitProfessionalSelect = false;
$calendarOnlyLayout = true;
$showTopCalendarFilters = false;
$showCalendarFilterModal = !app_is_professional_user();
$showOperationPanel = false;
$appointmentModalHighlightSubtitle = true;
$appointmentUnavailableMessage = 'Este horario ainda nao foi liberado para agendamento.';
$autoOpenSelectedAppointmentModal = $selectedAppointment !== null;
$floatingFlashMessages = true;
$autoOpenCalendarFilterModal = $calendarFilterNeedsService
    || (!app_is_professional_user() && !$professionalChosenInRequest && $selectedAppointment === null && count($professionals) > 1);

include __DIR__ . '/app/Views/schedule/page.php';
