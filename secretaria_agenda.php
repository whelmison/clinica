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
$patients = $scheduleRepository->patients();
$allowedProfessionalIds = array_map(static fn (array $professional): int => (int) $professional['id'], $professionals);
$selectedProfessionalId = app_query_int('professional_id') ?: (int) ($allowedProfessionalIds[0] ?? 0);
$reportReferenceDate = app_request_query('report_date', date('Y-m-d')) ?? date('Y-m-d');
$reportReferenceDate = strtotime($reportReferenceDate) ? date('Y-m-d', strtotime($reportReferenceDate)) : date('Y-m-d');

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

$appointmentPageData = $scheduleRepository->paginateAppointments($appointmentFilters, max(1, app_query_int('appointment_page', 1)), 8);
$appointments = $appointmentPageData['items'];
$appointmentPagination = $appointmentPageData['pagination'];
$appointmentPagination['query'] = array_merge($appointmentPagination['query'], [
    'week_start' => $weekStart,
    'professional_id' => $selectedProfessionalId,
]);
$selectedAppointment = $selectedAppointmentId ? $scheduleRepository->findAppointment($selectedAppointmentId) : null;
$appointmentFormDateValue = (string) ($selectedAppointment['data_agendamento'] ?? $weekStart);
$appointmentFormTimeValue = app_time_br((string) ($selectedAppointment['hora_inicio'] ?? '08:00:00'));
$selectedAvailability = null;
$selectedProfessionalServices = $selectedProfessionalId > 0 ? $scheduleRepository->servicesForProfessional($selectedProfessionalId) : [];
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

$pageTitle = 'Agenda Clinica';
$operationTitle = 'Operacao de agenda';
$operationDescription = 'Cadastro rapido de agendamentos da secretaria.';
$calendarTitle = 'Grade semanal';
$calendarTitleProfessional = (string) ($currentProfessional['nome'] ?? '');
$calendarDescription = 'Passe o mouse para ver o horario. Duplo clique abre um popup para cadastrar ou editar o agendamento.';
$calendarResetPath = 'secretaria_agenda.php?' . app_build_query([
    'professional_id' => $selectedProfessionalId,
    'report_date' => $reportReferenceDate,
]);
$autoSubmitProfessionalSelect = false;
$calendarOnlyLayout = true;
$showTopCalendarFilters = false;
$showCalendarFilterModal = true;
$showOperationPanel = false;
$appointmentModalHighlightSubtitle = true;
$appointmentUnavailableMessage = 'Este horario ainda nao foi liberado para agendamento.';
$autoOpenSelectedAppointmentModal = $selectedAppointment !== null;
$floatingFlashMessages = true;
$autoOpenCalendarFilterModal = $selectedAppointment === null
    && !isset($_GET['professional_id'])
    && !isset($_GET['week_start']);

include __DIR__ . '/app/Views/schedule/page.php';
