<?php
include 'config/db.php';

use Clinic\Repositories\ReportRepository;
use Clinic\Repositories\ScheduleRepository;
use Clinic\Services\ScheduleService;

$pdo = app_pdo();
$scheduleRepository = new ScheduleRepository($pdo);
$scheduleService = new ScheduleService($pdo, $scheduleRepository);
$reportRepository = new ReportRepository($pdo);
$currentUser = app_current_user() ?? [];
$canManageAppointments = $scheduleService->canManageAppointments($currentUser);
$statuses = app_schedule_statuses();
$professionals = $scheduleRepository->professionals();

$appointmentFilters = [
    'guia_id' => app_query_int('guia_id'),
    'paciente_id' => app_query_int('paciente_id'),
    'paciente' => trim((string) app_request_query('paciente', '')),
    'profissional_id' => app_query_int('profissional_id'),
    'status' => app_request_query('status', '') ?? '',
    'data_inicio' => app_request_query('data_inicio', ''),
    'data_fim' => app_request_query('data_fim', ''),
];

if (app_is_professional_user()) {
    $appointmentFilters['profissional_id'] = (int) app_current_professional_id();
}

if ((int) $appointmentFilters['paciente_id'] > 0 && $appointmentFilters['paciente'] === '') {
    $selectedPatient = $scheduleRepository->findPatient((int) $appointmentFilters['paciente_id']);
    $appointmentFilters['paciente'] = (string) ($selectedPatient['nome'] ?? '');
}

$pageData = $scheduleRepository->paginateAppointments(
    $appointmentFilters,
    max(1, app_query_int('page', 1)),
    20,
    app_is_professional_user() ? app_current_professional_id() : null,
    'agenda_lista_agendamentos.php',
    'page'
);
$appointments = $pageData['items'];
$pagination = $pageData['pagination'];
$groupWhere = ['g.clinica_id = ?'];
$groupTypes = 'i';
$groupParams = [$clinicId = app_active_clinic_id()];
$patientSearch = trim((string) ($appointmentFilters['paciente'] ?? ''));
$patientDigits = preg_replace('/\D+/', '', $patientSearch);

if (!empty($appointmentFilters['data_inicio'])) {
    $groupWhere[] = 'g.data_agendamento >= ?';
    $groupTypes .= 's';
    $groupParams[] = $appointmentFilters['data_inicio'];
}

if (!empty($appointmentFilters['data_fim'])) {
    $groupWhere[] = 'g.data_agendamento <= ?';
    $groupTypes .= 's';
    $groupParams[] = $appointmentFilters['data_fim'];
}

if (!empty($appointmentFilters['profissional_id'])) {
    $groupWhere[] = 'g.profissional_id = ?';
    $groupTypes .= 'i';
    $groupParams[] = (int) $appointmentFilters['profissional_id'];
}

if (!empty($appointmentFilters['guia_id'])) {
    $groupWhere[] = 'EXISTS (
        SELECT 1
        FROM agenda_grupo_pacientes gp_filter
        WHERE gp_filter.clinica_id = g.clinica_id
          AND gp_filter.grupo_id = g.id
          AND gp_filter.guia_id = ?
    )';
    $groupTypes .= 'i';
    $groupParams[] = (int) $appointmentFilters['guia_id'];
}

if (!empty($appointmentFilters['paciente_id'])) {
    $groupWhere[] = 'EXISTS (
        SELECT 1
        FROM agenda_grupo_pacientes gp_filter
        WHERE gp_filter.clinica_id = g.clinica_id
          AND gp_filter.grupo_id = g.id
          AND gp_filter.paciente_id = ?
    )';
    $groupTypes .= 'i';
    $groupParams[] = (int) $appointmentFilters['paciente_id'];
} elseif ($patientSearch !== '') {
    $groupPatientClauses = [
        'pa_filter.nome LIKE ?',
        "DATE_FORMAT(pa_filter.data_nascimento, '%d/%m/%Y') LIKE ?",
        "DATE_FORMAT(pa_filter.data_nascimento, '%Y-%m-%d') LIKE ?",
    ];
    $groupPatientTypes = 'sss';
    $groupPatientParams = ['%' . $patientSearch . '%', '%' . $patientSearch . '%', '%' . $patientSearch . '%'];

    if (strlen($patientDigits) >= 2) {
        $digitLike = '%' . $patientDigits . '%';
        $groupPatientClauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pa_filter.cpf, ''), '.', ''), '-', ''), '/', ''), ' ', '') LIKE ?";
        $groupPatientClauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pa_filter.telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') LIKE ?";
        $groupPatientClauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pa_filter.telefone_emergencia, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') LIKE ?";
        $groupPatientClauses[] = "DATE_FORMAT(pa_filter.data_nascimento, '%d%m%Y') LIKE ?";
        $groupPatientTypes .= 'ssss';
        $groupPatientParams[] = $digitLike;
        $groupPatientParams[] = $digitLike;
        $groupPatientParams[] = $digitLike;
        $groupPatientParams[] = $digitLike;
    }

    $groupWhere[] = 'EXISTS (
        SELECT 1
        FROM agenda_grupo_pacientes gp_filter
        INNER JOIN pacientes pa_filter ON pa_filter.id = gp_filter.paciente_id AND pa_filter.clinica_id = gp_filter.clinica_id
        WHERE gp_filter.clinica_id = g.clinica_id
          AND gp_filter.grupo_id = g.id
          AND (' . implode(' OR ', $groupPatientClauses) . ')
    )';
    $groupTypes .= $groupPatientTypes;
    $groupParams = array_merge($groupParams, $groupPatientParams);
}

if ($appointmentFilters['status'] !== '') {
    $groupWhere[] = 'EXISTS (
        SELECT 1
        FROM agenda_grupo_pacientes gp_filter
        WHERE gp_filter.clinica_id = g.clinica_id
          AND gp_filter.grupo_id = g.id
          AND gp_filter.status = ?
    )';
    $groupTypes .= 's';
    $groupParams[] = $appointmentFilters['status'];
}

$groupAppointments = app_stmt_all(
    $conn,
    'SELECT g.*,
            p.nome AS profissional_nome,
            s.nome AS servico_nome,
            COUNT(gp.id) AS pacientes,
            SUM(CASE WHEN gp.status = ? THEN 1 ELSE 0 END) AS realizados
     FROM agenda_grupos g
     INNER JOIN profissionais p ON p.id = g.profissional_id AND p.clinica_id = g.clinica_id
     INNER JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
     LEFT JOIN agenda_grupo_pacientes gp ON gp.grupo_id = g.id AND gp.clinica_id = g.clinica_id
     WHERE ' . implode(' AND ', $groupWhere) . '
     GROUP BY g.id
     ORDER BY g.data_agendamento DESC, g.hora_inicio ASC
     LIMIT 80',
    's' . $groupTypes,
    array_merge(['realizado'], $groupParams)
);
$appointmentListReturnQuery = app_build_query([
    'guia_id' => $appointmentFilters['guia_id'] ?: null,
    'paciente_id' => $appointmentFilters['paciente_id'] ?: null,
    'paciente' => $appointmentFilters['paciente'],
    'profissional_id' => $appointmentFilters['profissional_id'] ?: null,
    'status' => $appointmentFilters['status'],
    'data_inicio' => $appointmentFilters['data_inicio'],
    'data_fim' => $appointmentFilters['data_fim'],
    'page' => app_query_int('page') ?: null,
]);
$appointmentListReturnPath = 'agenda_lista_agendamentos.php' . ($appointmentListReturnQuery !== '' ? '?' . $appointmentListReturnQuery : '');

$reportOptions = $reportRepository->filters();
$appointmentReportLines = [];
$appointmentReportMetrics = [
    'Agendamentos individuais: ' . count($appointments),
    'Agendamentos em grupo: ' . count($groupAppointments),
];

if (!empty($appointmentFilters['data_inicio']) || !empty($appointmentFilters['data_fim'])) {
    $appointmentReportLines[] = 'Periodo: '
        . ($appointmentFilters['data_inicio'] ? app_date_br((string) $appointmentFilters['data_inicio']) : 'inicio')
        . ' a '
        . ($appointmentFilters['data_fim'] ? app_date_br((string) $appointmentFilters['data_fim']) : 'fim');
} else {
    $appointmentReportLines[] = 'Periodo: todos';
}

if ($appointmentFilters['paciente'] !== '') {
    $appointmentReportLines[] = 'Paciente: ' . $appointmentFilters['paciente'];
}

if (!empty($appointmentFilters['profissional_id'])) {
    foreach ($professionals as $professional) {
        if ((int) $professional['id'] === (int) $appointmentFilters['profissional_id']) {
            $appointmentReportLines[] = 'Profissional: ' . (string) $professional['nome'];
            break;
        }
    }
}

if ($appointmentFilters['status'] !== '') {
    $appointmentReportLines[] = 'Status: ' . ($statuses[$appointmentFilters['status']] ?? $appointmentFilters['status']);
}

function format_whatsapp_link($phone, $message) {
    if (empty($phone)) return '#';
    $cleanPhone = app_normalize_phone((string) $phone);
    if (strlen($cleanPhone) < 12) return '#';
    return 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($message);
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Lista de Agendamentos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.schedule-list-shell {
    padding-top: 0.45rem;
    padding-bottom: 0.75rem;
}
.schedule-list-shell .page-hero {
    margin-top: 0.3rem;
    padding: 0.72rem 0.82rem 0.68rem;
    border-radius: 16px;
}
.schedule-list-shell .page-hero h3 {
    font-size: 0.96rem;
}
.schedule-list-shell .page-hero p {
    font-size: 0.68rem;
}
.schedule-list-shell .soft-card {
    border-radius: 16px;
}
.schedule-list-shell .soft-card .card-body {
    padding: 0.62rem;
}
.schedule-list-shell .toolbar-grid {
    grid-template-columns: repeat(auto-fit, minmax(115px, 1fr));
    gap: 0.32rem;
}
.schedule-list-shell .toolbar-grid .form-label {
    margin-bottom: 0.1rem;
    font-size: 0.6rem;
    font-weight: 700;
}
.schedule-list-shell .toolbar-grid .form-control,
.schedule-list-shell .toolbar-grid .form-select,
.schedule-list-shell .toolbar-grid .btn {
    min-height: 29px;
    border-radius: 9px;
    font-size: 0.69rem;
    padding-top: 0.15rem;
    padding-bottom: 0.15rem;
}
.schedule-list-shell .table {
    font-size: 0.67rem;
}
.schedule-list-shell .table thead th {
    padding: 0.36rem 0.42rem;
    font-size: 0.56rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #667f8b;
    white-space: nowrap;
}
.schedule-list-shell .table tbody td {
    padding: 0.34rem 0.42rem;
    line-height: 1.15;
}
.schedule-list-shell .table .small,
.schedule-list-shell .table small {
    font-size: 0.58rem !important;
}
.schedule-list-shell .table .fw-bold,
.schedule-list-shell .table .fw-semibold {
    font-size: 0.69rem;
}
.schedule-list-shell .badge {
    font-size: 0.56rem;
    padding: 0.18rem 0.34rem;
}
.schedule-list-shell .btn-sm {
    font-size: 0.58rem;
    padding: 0.16rem 0.34rem;
    border-radius: 7px;
}
.schedule-list-shell .compact-actions {
    flex-wrap: wrap;
    gap: 0.25rem !important;
}
.schedule-list-shell .pagination {
    gap: 0.2rem;
}
.schedule-list-shell .pagination .page-link {
    padding: 0.32rem 0.5rem;
    font-size: 0.72rem;
}
.schedule-patient-filter {
    min-width: 230px;
}
.schedule-autocomplete-wrap {
    position: relative;
}
.schedule-autocomplete-menu {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    left: 0;
    z-index: 1050;
    display: none;
    max-height: 260px;
    overflow: auto;
    padding: 0.28rem;
    border: 1px solid rgba(18, 73, 88, 0.14);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 14px 32px rgba(22, 51, 63, 0.16);
}
.schedule-autocomplete-menu.is-open {
    display: grid;
    gap: 0.18rem;
}
.schedule-autocomplete-option {
    width: 100%;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #1d3945;
    text-align: left;
    padding: 0.42rem 0.48rem;
}
.schedule-autocomplete-option:hover,
.schedule-autocomplete-option:focus,
.schedule-autocomplete-option.is-active {
    background: rgba(15, 92, 74, 0.1);
    outline: none;
}
.schedule-autocomplete-name {
    display: block;
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1.15;
}
.schedule-autocomplete-meta {
    display: block;
    margin-top: 0.12rem;
    color: #68828f;
    font-size: 0.62rem;
    line-height: 1.15;
}
<?= app_report_print_header_css() ?>
@media print {
    .schedule-list-shell {
        padding: 0 !important;
    }
    .schedule-list-shell .row {
        display: block !important;
        margin: 0 !important;
    }
    .schedule-list-shell .col-12 {
        width: 100% !important;
        padding: 0 !important;
    }
    .schedule-list-report-section + .schedule-list-report-section {
        margin-top: 6mm !important;
    }
}
</style>
</head>
<body class="app-print-page">

<div class="no-print">
<?php include 'partials/menu.php'; ?>
</div>

<div class="container page-shell schedule-list-shell">
    <section class="page-hero app-print-hide">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Lista de Agendamentos</h3>
                <p>Todos os atendimentos marcados com filtros compactos para secretaria.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button class="btn btn-outline-secondary rounded-pill px-3 btn-sm" data-export-list onclick="appExportList('appointmentsReportArea', 'jpg', 'lista_agendamentos')">Exportar JPG</button>
                <button class="btn btn-outline-secondary rounded-pill px-3 btn-sm" onclick="window.print()">Imprimir relatorio</button>
                <a href="secretaria_agenda.php" class="btn btn-light rounded-pill px-3 btn-sm">Voltar</a>
            </div>
        </div>
    </section>

    <div class="soft-card card mt-4 app-print-hide">
        <div class="card-body no-print">
            <form class="toolbar-grid" method="GET" id="appointmentListFilterForm">
                <div>
                    <label class="form-label small text-muted">Data Inicio</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= app_h($appointmentFilters['data_inicio']) ?>">
                </div>
                <div>
                    <label class="form-label small text-muted">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= app_h($appointmentFilters['data_fim']) ?>">
                </div>
                <div>
                    <label class="form-label small text-muted">Guia</label>
                    <select class="form-select" name="guia_id">
                        <option value="">Todas</option>
                        <?php foreach ($reportOptions['guides'] as $guide): ?>
                            <option value="<?= (int) $guide['id'] ?>" <?= (int) $appointmentFilters['guia_id'] === (int) $guide['id'] ? 'selected' : '' ?>>
                                <?= app_h($guide['codigo'] ?: ('GUIA #' . $guide['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="schedule-patient-filter">
                    <label class="form-label small text-muted">Paciente</label>
                    <input type="hidden" name="paciente_id" id="appointmentFilterPacienteId" value="<?= (int) ($appointmentFilters['paciente_id'] ?? 0) > 0 ? (int) $appointmentFilters['paciente_id'] : '' ?>">
                    <div class="schedule-autocomplete-wrap">
                        <input type="text" name="paciente" id="appointmentFilterPacienteBusca" class="form-control" placeholder="Nome, CPF, contato ou nascimento" autocomplete="off" value="<?= app_h((string) ($appointmentFilters['paciente'] ?? '')) ?>">
                        <div class="schedule-autocomplete-menu" id="appointmentFilterPacienteMenu"></div>
                    </div>
                </div>
                <div>
                    <label class="form-label small text-muted">Profissional</label>
                    <select class="form-select" name="profissional_id" <?= app_is_professional_user() ? 'disabled' : '' ?>>
                        <option value="">Todos</option>
                        <?php foreach ($professionals as $professional): ?>
                            <option value="<?= (int) $professional['id'] ?>" <?= (int) $appointmentFilters['profissional_id'] === (int) $professional['id'] ? 'selected' : '' ?>>
                                <?= app_h($professional['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= app_h($value) ?>" <?= ($appointmentFilters['status'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= app_h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="agenda_lista_agendamentos.php" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="app-print-report-area" id="appointmentsReportArea">
    <?= app_report_print_header($conn, 'Lista de agendamentos', $appointmentReportLines, $appointmentReportMetrics) ?>

    <div class="row g-3 mt-1 schedule-list-report-section">
        <div class="col-12">
            <div class="soft-card card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Paciente</th>
                                <th>Profissional</th>
                                <th>Status</th>
                                <th class="text-end no-print">Acoes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <?php 
                                    // Prepare Patient WA message
                                    $profData = $scheduleRepository->findProfessional($appointment['profissional_id']);
                                    $msgPadrao = $profData['mensagem_padrao_whatsapp'] ?? 'Ola {paciente}, lembramos do seu agendamento no dia {data} as {hora} com {profissional}.';
                                    $msgPadrao = str_replace(
                                        ['{paciente}', '{data}', '{hora}', '{profissional}'], 
                                        [$appointment['paciente_nome'], app_date_br($appointment['data_agendamento']), app_time_br($appointment['hora_inicio']), $appointment['profissional_nome']], 
                                        $msgPadrao
                                    );
                                    $waPatient = format_whatsapp_link($appointment['cliente_telefone'] ?? '', $msgPadrao);

                                    // Prepare Prof WA message
                                    $msgProf = "Novo agendamento: Paciente {$appointment['paciente_nome']} no dia " . app_date_br($appointment['data_agendamento']) . " as " . app_time_br($appointment['hora_inicio']);
                                    $waProf = format_whatsapp_link($profData['telefone'] ?? '', $msgProf);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= app_date_br($appointment['data_agendamento']) ?></div>
                                        <div class="small text-muted"><?= app_time_br($appointment['hora_inicio']) ?> - <?= app_time_br($appointment['hora_fim']) ?></div>
                                    </td>
                                    <td>
                                        <?= app_h($appointment['paciente_nome']) ?>
                                        <div class="small text-muted"><?= app_h($appointment['servico_nome']) ?></div>
                                    </td>
                                    <td><?= app_h($appointment['profissional_nome']) ?></td>
                                    <td>
                                        <?php
                                            $st = app_h($appointment['status']);
                                            $stLabel = app_schedule_statuses()[$appointment['status']] ?? $appointment['status'];
                                            $badgeClass = 'bg-secondary';
                                            if ($st === 'confirmado') $badgeClass = 'bg-primary';
                                            if ($st === 'realizado') $badgeClass = 'bg-success';
                                            if ($st === 'cancelado') $badgeClass = 'bg-danger';
                                            if ($st === 'agendado') $badgeClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= app_h($stLabel) ?></span>
                                    </td>
                                    <td class="text-end no-print">
                                        <div class="d-flex justify-content-end compact-actions">
                                            <a class="btn btn-sm btn-outline-success" target="_blank" href="<?= $waPatient ?>" title="WhatsApp Paciente">
                                                Paciente
                                            </a>
                                            <a class="btn btn-sm btn-outline-success" target="_blank" href="<?= $waProf ?>" title="WhatsApp Profissional">
                                                Prof.
                                            </a>
                                            <?php if ($canManageAppointments): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="secretaria_agenda.php?<?= app_h(app_build_query(['professional_id' => $appointment['profissional_id'], 'week_start' => $appointment['data_agendamento'], 'appointment_id' => $appointment['id'], 'return_to' => $appointmentListReturnPath])) ?>">Abrir</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($appointments)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum agendamento encontrado para estes filtros.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-3 no-print">
                <?= app_render_pagination($pagination) ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1 schedule-list-report-section">
        <div class="col-12">
            <div class="soft-card card">
                <div class="card-header no-print">
                    <div class="panel-title">
                        <h5>Agendamentos em grupo</h5>
                        <span class="text-muted small">horarios com capacidade por servico</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Servico</th>
                                <th>Profissional</th>
                                <th>Pacientes</th>
                                <th class="text-end no-print">Acoes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($groupAppointments as $groupAppointment): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= app_date_br($groupAppointment['data_agendamento']) ?></div>
                                        <div class="small text-muted"><?= app_time_br($groupAppointment['hora_inicio']) ?> - <?= app_time_br($groupAppointment['hora_fim']) ?></div>
                                    </td>
                                    <td><?= app_h((string) $groupAppointment['servico_nome']) ?></td>
                                    <td><?= app_h((string) $groupAppointment['profissional_nome']) ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?= (int) $groupAppointment['pacientes'] ?>/<?= (int) $groupAppointment['capacidade'] ?></span>
                                        <span class="badge bg-success"><?= (int) $groupAppointment['realizados'] ?> realizados</span>
                                    </td>
                                    <td class="text-end no-print">
                                        <a class="btn btn-sm btn-outline-primary" href="secretaria_agenda_grupo.php?<?= app_h(app_build_query(['professional_id' => $groupAppointment['profissional_id'], 'service_id' => $groupAppointment['servico_id'], 'week_start' => $groupAppointment['data_agendamento'], 'group_id' => $groupAppointment['id']])) ?>">Abrir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($groupAppointments)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum grupo encontrado para estes filtros.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<script src="assets/list-export.js"></script>
<script>
const appointmentFilterForm = document.getElementById('appointmentListFilterForm');
const appointmentPatientInput = document.getElementById('appointmentFilterPacienteBusca');
const appointmentPatientMenu = document.getElementById('appointmentFilterPacienteMenu');
const appointmentPatientId = document.getElementById('appointmentFilterPacienteId');

function closeAppointmentPatientMenu() {
    if (!appointmentPatientMenu) return;
    appointmentPatientMenu.classList.remove('is-open');
    appointmentPatientMenu.innerHTML = '';
}

function submitAppointmentFilters() {
    if (!appointmentFilterForm) return;
    if (typeof appointmentFilterForm.requestSubmit === 'function') {
        appointmentFilterForm.requestSubmit();
        return;
    }
    appointmentFilterForm.submit();
}

function setupAppointmentPatientAutocomplete() {
    if (!appointmentFilterForm || !appointmentPatientInput || !appointmentPatientMenu || !appointmentPatientId) return;

    let timer = null;
    let controller = null;
    let results = [];
    let activeIndex = -1;

    function setActiveOption(nextIndex) {
        const options = Array.from(appointmentPatientMenu.querySelectorAll('.schedule-autocomplete-option'));
        if (!options.length) {
            activeIndex = -1;
            return;
        }

        activeIndex = (nextIndex + options.length) % options.length;
        options.forEach((option, index) => {
            option.classList.toggle('is-active', index === activeIndex);
            option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
        });
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function choosePatient(patient) {
        if (!patient) return;
        appointmentPatientInput.value = patient.nome || '';
        appointmentPatientId.value = String(patient.id || '');
        closeAppointmentPatientMenu();
        submitAppointmentFilters();
    }

    function renderMenu(items) {
        results = items;
        activeIndex = -1;
        appointmentPatientMenu.innerHTML = '';

        if (!items.length) {
            closeAppointmentPatientMenu();
            return;
        }

        items.forEach((patient, index) => {
            const button = document.createElement('button');
            const name = document.createElement('span');
            const meta = document.createElement('span');
            const metaParts = [];

            button.type = 'button';
            button.className = 'schedule-autocomplete-option';
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');

            name.className = 'schedule-autocomplete-name';
            name.textContent = patient.nome || '';
            button.appendChild(name);

            if (patient.cpf) metaParts.push('CPF/CNPJ: ' + patient.cpf);
            if (patient.data_nascimento) metaParts.push('Nasc.: ' + patient.data_nascimento);
            if (patient.telefone) metaParts.push('Contato: ' + patient.telefone);

            if (metaParts.length) {
                meta.className = 'schedule-autocomplete-meta';
                meta.textContent = metaParts.join(' | ');
                button.appendChild(meta);
            }

            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
            });
            button.addEventListener('click', () => {
                choosePatient(results[index]);
            });
            appointmentPatientMenu.appendChild(button);
        });

        appointmentPatientMenu.classList.add('is-open');
    }

    appointmentPatientInput.addEventListener('input', () => {
        const term = appointmentPatientInput.value.trim();
        appointmentPatientId.value = '';
        window.clearTimeout(timer);

        if (term.length < 2) {
            if (controller) controller.abort();
            closeAppointmentPatientMenu();
            return;
        }

        timer = window.setTimeout(async () => {
            if (controller) controller.abort();
            controller = new AbortController();

            try {
                const response = await fetch('pacientes_busca.php?q=' + encodeURIComponent(term), {
                    signal: controller.signal
                });
                const data = await response.json();
                renderMenu(data.pacientes || []);
            } catch (error) {
                if (error.name !== 'AbortError') closeAppointmentPatientMenu();
            }
        }, 160);
    });

    appointmentPatientInput.addEventListener('keydown', (event) => {
        const isOpen = appointmentPatientMenu.classList.contains('is-open');

        if (event.key === 'ArrowDown' && results.length) {
            event.preventDefault();
            if (!isOpen) appointmentPatientMenu.classList.add('is-open');
            setActiveOption(activeIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp' && results.length) {
            event.preventDefault();
            setActiveOption(activeIndex <= 0 ? results.length - 1 : activeIndex - 1);
            return;
        }

        if (event.key === 'Enter' && isOpen && activeIndex >= 0) {
            event.preventDefault();
            choosePatient(results[activeIndex]);
            return;
        }

        if (event.key === 'Escape') closeAppointmentPatientMenu();
    });

    document.addEventListener('click', (event) => {
        if (!appointmentPatientMenu.contains(event.target) && event.target !== appointmentPatientInput) {
            closeAppointmentPatientMenu();
        }
    });
}

setupAppointmentPatientAutocomplete();
</script>

</body>
</html>
