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
$patients = $scheduleRepository->patients();

$appointmentFilters = [
    'guia_id' => app_query_int('guia_id'),
    'paciente_id' => app_query_int('paciente_id'),
    'profissional_id' => app_query_int('profissional_id'),
    'status' => app_request_query('status', '') ?? '',
    'data_inicio' => app_request_query('data_inicio', ''),
    'data_fim' => app_request_query('data_fim', ''),
];

if (app_is_professional_user()) {
    $appointmentFilters['profissional_id'] = (int) app_current_professional_id();
}

$pageData = $scheduleRepository->paginateAppointments($appointmentFilters, max(1, app_query_int('page', 1)), 20, app_is_professional_user() ? app_current_professional_id() : null);
$appointments = $pageData['items'];
$pagination = $pageData['pagination'];
$groupWhere = ['g.clinica_id = ?'];
$groupTypes = 'i';
$groupParams = [$clinicId = app_active_clinic_id()];

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
    'profissional_id' => $appointmentFilters['profissional_id'] ?: null,
    'status' => $appointmentFilters['status'],
    'data_inicio' => $appointmentFilters['data_inicio'],
    'data_fim' => $appointmentFilters['data_fim'],
    'page' => app_query_int('page') ?: null,
]);
$appointmentListReturnPath = 'agenda_lista_agendamentos.php' . ($appointmentListReturnQuery !== '' ? '?' . $appointmentListReturnQuery : '');

$reportOptions = $reportRepository->filters();

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
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .page-shell { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    .no-print { display: none !important; }
    .soft-card { border: none !important; box-shadow: none !important; }
    .table th { background-color: #f8f9fa !important; color: #000 !important; }
    .page-hero { display: none !important; }
    .table td .d-flex { display: none !important; }
    h3 { margin-bottom: 1rem !important; }
}
</style>
</head>
<body>

<div class="no-print">
<?php include 'partials/menu.php'; ?>
</div>

<div class="container page-shell schedule-list-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Lista de Agendamentos</h3>
                <p>Todos os atendimentos marcados com filtros compactos para secretaria.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button class="btn btn-outline-secondary rounded-pill px-3 btn-sm" onclick="window.print()">Imprimir</button>
                <a href="secretaria_agenda.php" class="btn btn-light rounded-pill px-3 btn-sm">Voltar</a>
            </div>
        </div>
    </section>

    <div class="d-none d-print-block mb-4">
        <h2>Lista de Agendamentos</h2>
        <p>Filtrado por periodo: <?= app_h($appointmentFilters['data_inicio']) ?> a <?= app_h($appointmentFilters['data_fim']) ?></p>
    </div>

    <div class="soft-card card mt-4">
        <div class="card-body no-print">
            <form class="toolbar-grid" method="GET">
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
                <div>
                    <label class="form-label small text-muted">Paciente</label>
                    <select class="form-select" name="paciente_id">
                        <option value="">Todos</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>" <?= (int) $appointmentFilters['paciente_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
                                <?= app_h($patient['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

    <div class="row g-3 mt-1">
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

    <div class="row g-3 mt-1">
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

</body>
</html>
