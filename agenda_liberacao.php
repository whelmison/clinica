<?php
include 'config/db.php';

use Clinic\Repositories\ScheduleRepository;
use Clinic\Services\ScheduleService;

require_once __DIR__ . '/app/Views/schedule/render.php';

$pdo = app_pdo();
$scheduleRepository = new ScheduleRepository($pdo);
$scheduleService = new ScheduleService($pdo, $scheduleRepository);
$currentUser = app_current_user() ?? [];
$allProfessionals = $scheduleRepository->professionalsWithServices();

if (app_is_professional_user()) {
    $professionals = array_values(array_filter(
        $allProfessionals,
        static fn (array $professional): bool => (int) $professional['id'] === (int) app_current_professional_id()
    ));
} elseif (app_current_profile() === 'secretaria') {
    $professionals = array_values(array_filter(
        $allProfessionals,
        static fn (array $professional): bool => !empty($professional['permite_secretaria_liberar_agenda'])
    ));
} else {
    $professionals = $allProfessionals;
}

$allowedProfessionalIds = array_map(static fn (array $professional): int => (int) $professional['id'], $professionals);
$selectedProfessionalId = app_is_professional_user()
    ? (int) app_current_professional_id()
    : (app_query_int('professional_id') ?: (int) ($allowedProfessionalIds[0] ?? 0));
$reportReferenceDate = app_request_query('report_date', date('Y-m-d')) ?? date('Y-m-d');
$reportReferenceDate = strtotime($reportReferenceDate) ? date('Y-m-d', strtotime($reportReferenceDate)) : date('Y-m-d');

if ($selectedProfessionalId > 0 && !in_array($selectedProfessionalId, $allowedProfessionalIds, true)) {
    $selectedProfessionalId = (int) ($allowedProfessionalIds[0] ?? 0);
}

$currentProfessional = $selectedProfessionalId > 0 ? $scheduleRepository->findProfessional($selectedProfessionalId) : null;
$weekStart = app_week_start(app_request_query('week_start', date('Y-m-d')));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$selectedAppointmentId = null;
$selectedAvailabilityId = app_query_int('availability_id') ?: null;
$selectedAppointment = null;
$selectedAvailability = $selectedAvailabilityId && $selectedProfessionalId > 0
    ? $scheduleRepository->findAvailability($selectedAvailabilityId, app_is_professional_user() ? app_current_professional_id() : null)
    : null;
$canManageAppointments = false;
$canManageAvailability = $selectedProfessionalId > 0
    ? $scheduleService->canManageAvailabilityForProfessional($currentUser, $selectedProfessionalId)
    : false;
$whatsappUrl = null;
$statuses = [];
$patients = [];
$selectedProfessionalServices = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'create_availability') {
        $result = $scheduleService->createAvailability($_POST, $currentUser);
    } elseif ($action === 'update_availability') {
        $result = $scheduleService->updateAvailability(app_post_int('availability_id'), $_POST, $currentUser);
    } elseif ($action === 'delete_availability') {
        $result = $scheduleService->deleteAvailability(app_post_int('availability_id'), $currentUser);
    } elseif ($action === 'delete_availability_period') {
        $result = $scheduleService->deleteAvailabilityPeriod($_POST, $currentUser);
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    $redirectProfessionalId = (int) ($_POST['profissional_id'] ?? $selectedProfessionalId);
    $redirectQuery = [
        'professional_id' => $redirectProfessionalId,
        'week_start' => $weekStart,
        'report_date' => $reportReferenceDate,
    ];

    if (!($result['ok'] ?? false) && $action === 'update_availability') {
        $redirectQuery['availability_id'] = app_post_int('availability_id');
    }

    app_redirect('agenda_liberacao.php?' . app_build_query($redirectQuery));
}

$weekDays = app_week_days($weekStart);
$calendarData = $selectedProfessionalId > 0
    ? $scheduleRepository->calendar($weekStart, $selectedProfessionalId)
    : ['availabilities' => [], 'appointments' => []];
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
$calendarHtml = render_week_calendar(
    $weekDays,
    $calendarData,
    $selectedAppointmentId,
    $canManageAppointments,
    $canManageAvailability,
    $weekStart,
    $selectedAvailabilityId,
    $selectedProfessionalId,
    $calendarNavigationQuery
);

$pageTitle = 'Liberacao de Agenda';
$operationTitle = 'Liberacao de agenda';
$operationDescription = 'Libere um horario, um turno, a semana inteira ou o mes escolhido.';
$calendarTitle = 'Grade semanal';
$calendarDescription = 'Clique em um slot ou em uma faixa para ajustar a disponibilidade.';
$calendarResetPath = 'agenda_liberacao.php?' . app_build_query([
    'professional_id' => $selectedProfessionalId,
    'report_date' => $reportReferenceDate,
]);
$operationEmptyMessage = match (app_current_profile()) {
    'secretaria' => $selectedProfessionalId === 0 ? 'Nenhum profissional com servicos vinculados delegou a liberacao da agenda para a secretaria.' : '',
    'profissional' => $selectedProfessionalId === 0 ? 'Seu cadastro profissional ainda nao possui servicos vinculados para liberar agenda.' : '',
    default => $selectedProfessionalId === 0 ? 'Nenhum profissional com servicos vinculados foi encontrado para liberar agenda.' : '',
};
$autoSubmitProfessionalSelect = true;

include __DIR__ . '/app/Views/schedule/page.php';
