<?php include 'config/db.php'; ?>
<?php
$canManageAttendances = app_has_any_role(['profissional', 'secretaria', 'administrativo', 'desenvolvedor']);
$clinicId = app_active_clinic_id();
$isAttendancePost = app_request_method() === 'POST';
$attendanceMessage = '';
$autoOpenAttendanceModal = app_query_int('open_new') === 1;
$editAttendanceId = app_query_int('edit_id');
$editAttendance = null;
$autoOpenEditAttendanceModal = false;
$attendanceFilters = [
    'paciente' => trim((string) ($isAttendancePost ? app_request_post('filter_paciente', '') : app_request_query('paciente', ''))),
    'paciente_id' => $isAttendancePost ? app_post_int('filter_paciente_id') : app_query_int('paciente_id'),
    'guia' => trim((string) ($isAttendancePost ? app_request_post('filter_guia', '') : app_request_query('guia', ''))),
    'guia_id' => $isAttendancePost ? app_post_int('filter_guia_id') : app_query_int('guia_id'),
    'plano' => trim((string) ($isAttendancePost ? app_request_post('filter_plano', '') : app_request_query('plano', ''))),
    'status' => trim((string) ($isAttendancePost ? app_request_post('filter_status', '') : app_request_query('status', ''))),
    'mes' => trim((string) ($isAttendancePost ? app_request_post('filter_mes', '') : app_request_query('mes', ''))),
    'ano' => trim((string) ($isAttendancePost ? app_request_post('filter_ano', '') : app_request_query('ano', ''))),
];
$shouldLoadAttendances = app_request_query('filtrar', '') === '1'
    || ($isAttendancePost && app_request_post('filter_filtrar', '') === '1')
    || $editAttendanceId > 0;
$attendanceFormValues = [
    'paciente_id' => 0,
    'paciente_nome' => '',
    'guia_id' => 0,
    'data' => date('Y-m-d'),
];

if (!function_exists('app_attendance_filter_query')) {
    function app_attendance_filter_query(array $filters, array $extra = []): string
    {
        return app_build_query([
            'paciente' => $filters['paciente'] ?? '',
            'paciente_id' => (int) ($filters['paciente_id'] ?? 0),
            'guia' => $filters['guia'] ?? '',
            'guia_id' => (int) ($filters['guia_id'] ?? 0),
            'plano' => $filters['plano'] ?? '',
            'status' => $filters['status'] ?? '',
            'mes' => $filters['mes'] ?? '',
            'ano' => $filters['ano'] ?? '',
            'filtrar' => 1,
        ], $extra);
    }
}

function app_attendance_professional_where(array &$where, string &$types, array &$params, string $column = 'g.profissional_id'): void
{
    if (!app_is_professional_user()) {
        return;
    }

    $professionalId = app_current_professional_id();

    if ($professionalId === null) {
        $where[] = '1 = 0';
        return;
    }

    $where[] = $column . ' = ?';
    $types .= 'i';
    $params[] = $professionalId;
}

function app_attendance_find(mysqli $conn, int $attendanceId): ?array
{
    $where = ['a.clinica_id = ?', 'a.id = ?'];
    $types = 'ii';
    $params = [app_active_clinic_id(), $attendanceId];
    app_attendance_professional_where($where, $types, $params);

    return app_stmt_one(
        $conn,
        'SELECT a.*, p.nome AS paciente_nome, g.codigo AS guia_codigo, pl.nome AS plano_nome
         FROM atendimentos a
         LEFT JOIN pacientes p ON p.id = a.paciente_id AND p.clinica_id = a.clinica_id
         LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
         LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
         WHERE ' . implode(' AND ', $where) . '
         LIMIT 1',
        $types,
        $params
    );
}

function app_attendance_available_guide(mysqli $conn, int $guideId): ?array
{
    $where = ['g.clinica_id = ?', 'g.id = ?', "COALESCE(g.status_operacional, 'criada') NOT IN ('cancelada', 'finalizada')"];
    $types = 'ii';
    $params = [app_active_clinic_id(), $guideId];
    app_attendance_professional_where($where, $types, $params);

    return app_stmt_one(
        $conn,
        'SELECT g.*, p.nome AS paciente_nome, pl.nome AS plano_nome,
                COALESCE(a.usadas, 0) AS usadas
         FROM guias g
         LEFT JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
         LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
         LEFT JOIN (
            SELECT guia_id, COUNT(*) AS usadas
            FROM atendimentos
            WHERE clinica_id = ?
            GROUP BY guia_id
         ) a ON a.guia_id = g.id
         WHERE ' . implode(' AND ', $where) . '
         LIMIT 1',
        'i' . $types,
        array_merge([app_active_clinic_id()], $params)
    );
}

if ($isAttendancePost && ($_POST['action'] ?? '') === 'save_attendance') {
    if (!$canManageAttendances) {
        app_flash('danger', 'Sem permissao para cadastrar atendimento.');
        app_redirect('atendimentos.php');
    }

    $attendanceFormValues = [
        'paciente_id' => app_post_int('paciente_id'),
        'paciente_nome' => trim((string) app_request_post('paciente_nome', '')),
        'guia_id' => app_post_int('guia_id'),
        'data' => trim((string) app_request_post('data', date('Y-m-d'))),
    ];

    $guide = $attendanceFormValues['guia_id'] > 0 ? app_attendance_available_guide($conn, $attendanceFormValues['guia_id']) : null;

    if ($attendanceFormValues['paciente_id'] <= 0) {
        $attendanceMessage = 'Selecione o paciente pelo autocomplete.';
    } elseif (!$guide || (int) $guide['paciente_id'] !== (int) $attendanceFormValues['paciente_id']) {
        $attendanceMessage = 'Selecione uma guia valida para o paciente.';
    } elseif ((int) ($guide['usadas'] ?? 0) >= (int) ($guide['total_sessoes'] ?? 0)) {
        $attendanceMessage = 'Esta guia ja esta finalizada.';
    } elseif ($attendanceFormValues['data'] === '' || !strtotime($attendanceFormValues['data'])) {
        $attendanceMessage = 'Informe uma data valida.';
    } else {
        $data = date('Y-m-d', strtotime($attendanceFormValues['data']));
        $ok = app_stmt_execute(
            $conn,
            'INSERT INTO atendimentos (clinica_id, paciente_id, data, tipo, pago, guia_id, status_atendimento)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iisssis',
            [
                $clinicId,
                (int) $guide['paciente_id'],
                $data,
                (string) ($guide['plano_nome'] ?? ''),
                'Pendente',
                (int) $guide['id'],
                'Realizado',
            ]
        );

        if ($ok) {
            app_flash('success', 'Atendimento cadastrado com sucesso.');
            app_redirect('atendimentos.php?' . app_attendance_filter_query($attendanceFilters));
        }

        $attendanceMessage = 'Nao foi possivel salvar o atendimento.';
    }

    if ($guide) {
        $attendanceFormValues['paciente_nome'] = (string) ($guide['paciente_nome'] ?? $attendanceFormValues['paciente_nome']);
    }

    $autoOpenAttendanceModal = true;
}

if ($isAttendancePost && ($_POST['action'] ?? '') === 'update_attendance') {
    if (!$canManageAttendances) {
        app_flash('danger', 'Sem permissao para editar atendimento.');
        app_redirect('atendimentos.php');
    }

    $attendanceId = app_post_int('attendance_id');
    $data = trim((string) app_request_post('data', date('Y-m-d')));
    $status = trim((string) app_request_post('status_atendimento', 'Realizado'));
    $allowedStatuses = ['Realizado', 'Glosado'];
    $editAttendance = app_attendance_find($conn, $attendanceId);

    if (!$editAttendance) {
        $attendanceMessage = 'Atendimento nao encontrado ou sem permissao.';
    } elseif ($data === '' || !strtotime($data)) {
        $attendanceMessage = 'Informe uma data valida.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $attendanceMessage = 'Status invalido.';
    } else {
        $ok = app_stmt_execute(
            $conn,
            'UPDATE atendimentos SET data = ?, status_atendimento = ? WHERE clinica_id = ? AND id = ?',
            'ssii',
            [date('Y-m-d', strtotime($data)), $status, $clinicId, $attendanceId]
        );

        if ($ok) {
            app_flash('success', 'Atendimento atualizado com sucesso.');
            app_redirect('atendimentos.php?' . app_attendance_filter_query($attendanceFilters));
        }

        $attendanceMessage = 'Nao foi possivel atualizar o atendimento.';
    }

    $editAttendanceId = $attendanceId;
    $autoOpenEditAttendanceModal = true;
}

if ($editAttendanceId > 0 && !$editAttendance) {
    $editAttendance = app_attendance_find($conn, $editAttendanceId);
    $autoOpenEditAttendanceModal = $editAttendance !== null;
}

$attendanceRows = [];

if ($shouldLoadAttendances) {
    $where = ['a.clinica_id = ?'];
    $types = 'i';
    $params = [$clinicId];

    if ((int) ($attendanceFilters['paciente_id'] ?? 0) > 0) {
        $where[] = 'a.paciente_id = ?';
        $types .= 'i';
        $params[] = (int) $attendanceFilters['paciente_id'];
    } elseif ($attendanceFilters['paciente'] !== '') {
        $where[] = 'p.nome LIKE ?';
        $types .= 's';
        $params[] = '%' . $attendanceFilters['paciente'] . '%';
    }

    if ((int) ($attendanceFilters['guia_id'] ?? 0) > 0) {
        $where[] = 'g.id = ?';
        $types .= 'i';
        $params[] = (int) $attendanceFilters['guia_id'];
    } elseif ($attendanceFilters['guia'] !== '') {
        $where[] = 'g.codigo LIKE ?';
        $types .= 's';
        $params[] = '%' . $attendanceFilters['guia'] . '%';
    }

    if ($attendanceFilters['plano'] !== '') {
        $where[] = 'pl.nome LIKE ?';
        $types .= 's';
        $params[] = '%' . $attendanceFilters['plano'] . '%';
    }

    if ($attendanceFilters['status'] !== '') {
        $where[] = 'a.status_atendimento = ?';
        $types .= 's';
        $params[] = $attendanceFilters['status'];
    }

    if ($attendanceFilters['mes'] !== '' && preg_match('/^\d{2}$/', $attendanceFilters['mes'])) {
        $where[] = 'MONTH(a.data) = ?';
        $types .= 'i';
        $params[] = (int) $attendanceFilters['mes'];
    }

    if ($attendanceFilters['ano'] !== '' && preg_match('/^\d{4}$/', $attendanceFilters['ano'])) {
        $where[] = 'YEAR(a.data) = ?';
        $types .= 'i';
        $params[] = (int) $attendanceFilters['ano'];
    }

    app_attendance_professional_where($where, $types, $params);
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $attendanceRows = app_stmt_all(
        $conn,
        'SELECT a.*, p.nome AS paciente_nome, g.codigo AS guia_codigo,
                pl.nome AS plano_nome, pl.valor_sessao
         FROM atendimentos a
         LEFT JOIN pacientes p ON p.id = a.paciente_id AND p.clinica_id = a.clinica_id
         LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
         LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
         ' . $whereSql . '
         ORDER BY a.data DESC, p.nome ASC
         LIMIT 500',
        $types,
        $params
    );
}

$monthOptions = [
    '01' => 'Janeiro',
    '02' => 'Fevereiro',
    '03' => 'Marco',
    '04' => 'Abril',
    '05' => 'Maio',
    '06' => 'Junho',
    '07' => 'Julho',
    '08' => 'Agosto',
    '09' => 'Setembro',
    '10' => 'Outubro',
    '11' => 'Novembro',
    '12' => 'Dezembro',
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Atendimentos</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">

<style>
body {
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}

.attendance-shell {
    padding: 0.7rem 0.9rem 1rem;
}

.attendance-topbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.76rem 0.92rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
    color: #fff;
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.12);
}

.attendance-kicker {
    margin: 0 0 0.12rem;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    opacity: 0.78;
    text-transform: uppercase;
}

.attendance-topbar h3 {
    margin: 0;
    font-size: 1.12rem;
}

.attendance-topbar p {
    margin: 0.16rem 0 0;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.74rem;
    line-height: 1.18;
}

.attendance-top-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.42rem;
    flex-wrap: wrap;
}

.attendance-top-actions .btn {
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.34rem 0.68rem;
}

.attendance-filter-card,
.attendance-list-panel {
    margin-top: 0.6rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.attendance-filter-card .form-control,
.attendance-filter-card .form-select {
    min-height: 36px;
    border-radius: 12px;
    border-color: #dbe7ec;
    font-size: 0.78rem;
}

.attendance-list-panel {
    overflow: hidden;
}

.attendance-table {
    margin: 0;
    font-size: 0.76rem;
}

.attendance-table th,
.attendance-table td {
    vertical-align: middle;
    border-color: rgba(18, 73, 88, 0.07);
    white-space: nowrap;
}

.attendance-table thead th {
    padding: 0.34rem 0.44rem;
    background: #f5f9fa;
    color: #6b8591;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.attendance-table tbody td {
    padding: 0.38rem 0.44rem;
    color: #1d3945;
    line-height: 1.1;
}

.attendance-table tbody tr:hover {
    background: rgba(31, 122, 140, 0.055);
}

.attendance-name {
    font-weight: 700;
    color: #16333f;
}

.acoes {
    display: flex;
    gap: 0.34rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.acoes .btn {
    border-radius: 999px;
    min-height: 28px;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.22rem 0.54rem;
}

.autocomplete-wrap {
    position: relative;
}

.autocomplete-menu {
    position: absolute;
    z-index: 1056;
    top: calc(100% + 4px);
    right: 0;
    left: 0;
    display: none;
    max-height: 220px;
    overflow: auto;
    border: 1px solid #dbe7ec;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 18px 34px rgba(22, 51, 63, 0.16);
}

.autocomplete-menu.is-open {
    display: block;
}

.autocomplete-option {
    width: 100%;
    border: 0;
    background: transparent;
    padding: 0.48rem 0.68rem;
    color: #16333f;
    font-size: 0.78rem;
    text-align: left;
}

.autocomplete-option:hover,
.autocomplete-option:focus {
    background: rgba(31, 122, 140, 0.1);
    outline: none;
}

.attendance-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: visible;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}

.attendance-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.attendance-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.attendance-form .form-control,
.attendance-form .form-select {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.attendance-form .btn,
.attendance-modal .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}

.attendance-shortcuts {
    color: #68828f;
    font-size: 0.72rem;
}

@media (max-width: 900px) {
    .attendance-topbar {
        align-items: stretch;
        flex-direction: column;
    }

    .attendance-top-actions {
        justify-content: flex-start;
    }

    .attendance-list-panel {
        overflow-x: auto;
    }

    .attendance-table {
        min-width: 820px;
    }
}

@media print {
    button, select, a, .attendance-filter-card, .topbar-clinica { display: none !important; }
    body { background: #fff !important; }
}
</style>
</head>

<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid attendance-shell">

<section class="attendance-topbar">
<div>
<p class="attendance-kicker">Atendimentos</p>
<h3>Atendimentos</h3>
<p>Registre sessoes realizadas e acompanhe glosas por guia.</p>
</div>
<div class="attendance-top-actions">
<button type="submit" form="attendanceFilterForm" name="filtrar" value="1" class="btn btn-light text-secondary">Filtrar</button>
<a href="atendimentos.php" class="btn btn-outline-light">Limpar filtro</a>
<?php if ($canManageAttendances): ?>
<button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#attendanceModal">+ Novo atendimento</button>
<?php endif; ?>
<div class="dropdown">
<button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Acoes</button>
<ul class="dropdown-menu dropdown-menu-end">
<li><button class="dropdown-item" type="button" onclick="window.print()">Exportar PDF</button></li>
</ul>
</div>
</div>
</section>

<div class="attendance-filter-card p-2">
<form method="GET" id="attendanceFilterForm" class="row g-2 align-items-end">
<div class="col-md-3">
<label class="form-label small text-muted">Paciente</label>
<input type="hidden" name="paciente_id" id="attendanceFilterPacienteId" value="<?= (int) ($attendanceFilters['paciente_id'] ?? 0) ?>">
<div class="autocomplete-wrap">
<input type="text" name="paciente" id="attendanceFilterPacienteBusca" class="form-control" placeholder="Digite o nome" autocomplete="off" value="<?= app_h($attendanceFilters['paciente']) ?>">
<div class="autocomplete-menu" id="attendanceFilterPacienteMenu"></div>
</div>
</div>
<div class="col-md-2">
<label class="form-label small text-muted">Guia</label>
<input type="hidden" name="guia_id" id="attendanceFilterGuiaId" value="<?= (int) ($attendanceFilters['guia_id'] ?? 0) ?>">
<input type="text" name="guia" id="attendanceFilterGuiaText" class="form-control" placeholder="Numero/codigo" value="<?= app_h($attendanceFilters['guia']) ?>">
<select id="attendanceFilterGuiaSelect" class="form-select" title="Escolha uma guia do paciente selecionado." hidden>
<option value="">Selecione o paciente</option>
</select>
</div>
<div class="col-md-2">
<label class="form-label small text-muted">Plano</label>
<input type="text" name="plano" class="form-control" placeholder="Plano" value="<?= app_h($attendanceFilters['plano']) ?>">
</div>
<div class="col-md-2">
<label class="form-label small text-muted">Status</label>
<select name="status" class="form-select">
<option value="">Status</option>
<?php foreach (['Realizado', 'Glosado'] as $statusOption): ?>
<option value="<?= app_h($statusOption) ?>" <?= $attendanceFilters['status'] === $statusOption ? 'selected' : '' ?>><?= app_h($statusOption) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-1">
<label class="form-label small text-muted">Mes</label>
<select name="mes" class="form-select">
<option value="">Mes</option>
<?php foreach ($monthOptions as $monthNumber => $monthName): ?>
<option value="<?= app_h($monthNumber) ?>" <?= $attendanceFilters['mes'] === $monthNumber ? 'selected' : '' ?>><?= app_h($monthName) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-1">
<label class="form-label small text-muted">Ano</label>
<select name="ano" class="form-select">
<option value="">Ano</option>
<?php for ($year = (int) date('Y'); $year >= 2020; $year--): ?>
<option value="<?= $year ?>" <?= $attendanceFilters['ano'] === (string) $year ? 'selected' : '' ?>><?= $year ?></option>
<?php endfor; ?>
</select>
</div>
<div class="d-none">
<input type="hidden" name="filtrar" value="1">
</div>
</form>
</div>

<section class="attendance-list-panel">
<table class="table table-bordered attendance-table">
<thead>
<tr>
<th>Paciente</th>
<th>Plano</th>
<th>Guia</th>
<th>Data</th>
<th class="text-center">Glosa</th>
<th class="text-end">Acoes</th>
</tr>
</thead>
<tbody id="tbody">
<?php if ($shouldLoadAttendances): ?>
<?php foreach ($attendanceRows as $row): ?>
<?php
    $status = (string) ($row['status_atendimento'] ?? 'Realizado');
    $editUrl = 'atendimentos.php?' . app_attendance_filter_query($attendanceFilters, ['edit_id' => (int) $row['id']]);
?>
<tr>
<td><span class="attendance-name"><?= app_h((string) ($row['paciente_nome'] ?? '')) ?></span></td>
<td><?= app_h((string) ($row['plano_nome'] ?? '')) ?></td>
<td><?= app_h((string) ($row['guia_codigo'] ?? '')) ?></td>
<td><?= app_h(app_date_br((string) $row['data'])) ?></td>
<td class="text-center">
<input type="checkbox" <?= $status === 'Glosado' ? 'checked' : '' ?> onclick="toggleGlosa(<?= (int) $row['id'] ?>, this)" title="Marque para indicar glosa neste atendimento.">
</td>
<td class="acoes">
<?php if ($canManageAttendances): ?>
<a href="<?= app_h($editUrl) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
<form method="POST" action="excluir_atendimento.php" class="d-inline" onsubmit="return confirm('Excluir este atendimento?')">
<input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
<button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
</form>
<?php else: ?>
<span class="text-muted small">Somente leitura</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if ($attendanceRows === []): ?>
<tr>
<td colspan="6" class="text-center py-4 text-muted">Nenhum atendimento encontrado para os filtros informados.</td>
</tr>
<?php endif; ?>
<?php else: ?>
<tr>
<td colspan="6" class="text-center py-4 text-muted">
Use os filtros acima e clique em <strong>Filtrar</strong> para consultar os atendimentos.
</td>
</tr>
<?php endif; ?>
</tbody>
<tfoot>
<tr>
<td colspan="6" id="total">Total: <?= $shouldLoadAttendances ? count($attendanceRows) : 0 ?> atendimento(s)</td>
</tr>
</tfoot>
</table>
</section>

</div>

<?php if ($canManageAttendances): ?>
<div class="modal fade attendance-modal" id="attendanceModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<div>
<h5 class="modal-title">Novo atendimento</h5>
</div>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
</div>
<div class="modal-body">
<?php if ($attendanceMessage && !$editAttendance): ?>
<div class="alert alert-warning"><?= app_h($attendanceMessage) ?></div>
<?php endif; ?>
<form method="POST" id="attendanceForm" class="attendance-form">
<input type="hidden" name="action" value="save_attendance">
<input type="hidden" name="filter_paciente" value="<?= app_h($attendanceFilters['paciente']) ?>">
<input type="hidden" name="filter_paciente_id" value="<?= (int) ($attendanceFilters['paciente_id'] ?? 0) ?>">
<input type="hidden" name="filter_guia" value="<?= app_h($attendanceFilters['guia']) ?>">
<input type="hidden" name="filter_guia_id" value="<?= (int) ($attendanceFilters['guia_id'] ?? 0) ?>">
<input type="hidden" name="filter_plano" value="<?= app_h($attendanceFilters['plano']) ?>">
<input type="hidden" name="filter_status" value="<?= app_h($attendanceFilters['status']) ?>">
<input type="hidden" name="filter_mes" value="<?= app_h($attendanceFilters['mes']) ?>">
<input type="hidden" name="filter_ano" value="<?= app_h($attendanceFilters['ano']) ?>">
<input type="hidden" name="filter_filtrar" value="<?= $shouldLoadAttendances ? '1' : '' ?>">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">Paciente</label>
<input type="hidden" name="paciente_id" id="attendancePacienteId" value="<?= (int) $attendanceFormValues['paciente_id'] ?>">
<input type="hidden" name="paciente_nome" id="attendancePacienteNome" value="<?= app_h($attendanceFormValues['paciente_nome']) ?>">
<div class="autocomplete-wrap">
<input type="text" id="attendancePacienteBusca" class="form-control" autocomplete="off" required
       value="<?= app_h($attendanceFormValues['paciente_nome']) ?>"
       title="Digite parte do nome e escolha o paciente da lista."
       placeholder="Digite para buscar o paciente">
<div class="autocomplete-menu" id="attendancePacienteMenu"></div>
</div>
</div>
<div class="col-md-6">
<label class="form-label">Guia</label>
<select name="guia_id" id="attendanceGuia" class="form-select" required title="Escolha uma guia em aberto para registrar a sessao.">
<option value="">Selecione o paciente primeiro</option>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Data</label>
<input type="date" name="data" class="form-control" value="<?= app_h($attendanceFormValues['data']) ?>" required title="Data em que a sessao foi realizada.">
</div>
</div>
</form>
</div>
<div class="modal-footer">
<div class="me-auto attendance-shortcuts">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</div>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
<button type="submit" form="attendanceForm" class="btn btn-primary px-4">Salvar atendimento <span class="small">(Alt+S)</span></button>
</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if ($canManageAttendances && $editAttendance): ?>
<div class="modal fade attendance-modal" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<div>
<h5 class="modal-title">Editar atendimento</h5>
</div>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
</div>
<div class="modal-body">
<?php if ($attendanceMessage): ?>
<div class="alert alert-warning"><?= app_h($attendanceMessage) ?></div>
<?php endif; ?>
<form method="POST" id="editAttendanceForm" class="attendance-form">
<input type="hidden" name="action" value="update_attendance">
<input type="hidden" name="attendance_id" value="<?= (int) $editAttendance['id'] ?>">
<input type="hidden" name="filter_paciente" value="<?= app_h($attendanceFilters['paciente']) ?>">
<input type="hidden" name="filter_paciente_id" value="<?= (int) ($attendanceFilters['paciente_id'] ?? 0) ?>">
<input type="hidden" name="filter_guia" value="<?= app_h($attendanceFilters['guia']) ?>">
<input type="hidden" name="filter_guia_id" value="<?= (int) ($attendanceFilters['guia_id'] ?? 0) ?>">
<input type="hidden" name="filter_plano" value="<?= app_h($attendanceFilters['plano']) ?>">
<input type="hidden" name="filter_status" value="<?= app_h($attendanceFilters['status']) ?>">
<input type="hidden" name="filter_mes" value="<?= app_h($attendanceFilters['mes']) ?>">
<input type="hidden" name="filter_ano" value="<?= app_h($attendanceFilters['ano']) ?>">
<input type="hidden" name="filter_filtrar" value="<?= $shouldLoadAttendances ? '1' : '' ?>">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">Paciente</label>
<input class="form-control" value="<?= app_h((string) ($editAttendance['paciente_nome'] ?? '')) ?>" readonly title="Paciente vinculado ao atendimento.">
</div>
<div class="col-md-3">
<label class="form-label">Plano</label>
<input class="form-control" value="<?= app_h((string) ($editAttendance['plano_nome'] ?? '')) ?>" readonly title="Plano da guia usada neste atendimento.">
</div>
<div class="col-md-3">
<label class="form-label">Guia</label>
<input class="form-control" value="<?= app_h((string) ($editAttendance['guia_codigo'] ?? '')) ?>" readonly title="Guia vinculada ao atendimento.">
</div>
<div class="col-md-4">
<label class="form-label">Data</label>
<input type="date" name="data" class="form-control" value="<?= app_h((string) $editAttendance['data']) ?>" required title="Data em que a sessao foi realizada.">
</div>
<div class="col-md-4">
<label class="form-label">Status</label>
<select name="status_atendimento" class="form-select" title="Use Glosado quando a sessao nao deve ser faturada normalmente.">
<?php foreach (['Realizado', 'Glosado'] as $statusOption): ?>
<option value="<?= app_h($statusOption) ?>" <?= (string) ($editAttendance['status_atendimento'] ?? 'Realizado') === $statusOption ? 'selected' : '' ?>><?= app_h($statusOption) ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
</form>
</div>
<div class="modal-footer">
<div class="me-auto attendance-shortcuts">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</div>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
<button type="submit" form="editAttendanceForm" class="btn btn-primary px-4">Salvar atendimento <span class="small">(Alt+S)</span></button>
</div>
</div>
</div>
</div>
<?php endif; ?>

<script>
const csrfToken = <?= json_encode(app_csrf_token()) ?>;
const attendanceForm = document.getElementById('attendanceForm');
const editAttendanceForm = document.getElementById('editAttendanceForm');
const attendancePacienteBusca = document.getElementById('attendancePacienteBusca');
const attendancePacienteMenu = document.getElementById('attendancePacienteMenu');
const attendancePacienteId = document.getElementById('attendancePacienteId');
const attendancePacienteNome = document.getElementById('attendancePacienteNome');
const attendanceGuia = document.getElementById('attendanceGuia');
const selectedAttendanceGuide = <?= (int) $attendanceFormValues['guia_id'] ?>;
const attendanceFilterPacienteBusca = document.getElementById('attendanceFilterPacienteBusca');
const attendanceFilterPacienteMenu = document.getElementById('attendanceFilterPacienteMenu');
const attendanceFilterPacienteId = document.getElementById('attendanceFilterPacienteId');
const attendanceFilterGuiaText = document.getElementById('attendanceFilterGuiaText');
const attendanceFilterGuiaId = document.getElementById('attendanceFilterGuiaId');
const attendanceFilterGuiaSelect = document.getElementById('attendanceFilterGuiaSelect');
const selectedFilterPatientId = <?= (int) ($attendanceFilters['paciente_id'] ?? 0) ?>;
const selectedFilterGuideId = <?= (int) ($attendanceFilters['guia_id'] ?? 0) ?>;

function toggleGlosa(id, el) {
    const status = el.checked ? 'Glosado' : 'Realizado';

    fetch('toggle_glosa.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-Token': csrfToken
        },
        body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status)
    }).catch(() => {
        el.checked = !el.checked;
    });
}

function closeAutocomplete(menu) {
    if (!menu) return;
    menu.classList.remove('is-open');
    menu.innerHTML = '';
}

function setupPatientAutocomplete(input, menu, onSelect, options = {}) {
    if (!input || !menu) return;

    let timer = null;
    let controller = null;
    const minChars = options.minChars || 1;

    function render(items) {
        menu.innerHTML = '';

        if (!items.length) {
            closeAutocomplete(menu);
            return;
        }

        items.forEach((patient) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'autocomplete-option';
            button.textContent = patient.nome;
            button.addEventListener('click', () => {
                input.value = patient.nome;
                closeAutocomplete(menu);
                onSelect(patient);
            });
            menu.appendChild(button);
        });

        menu.classList.add('is-open');
    }

    input.addEventListener('input', () => {
        const term = input.value.trim();
        onSelect(null);
        window.clearTimeout(timer);

        if (term.length < minChars) {
            closeAutocomplete(menu);
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
                render(data.pacientes || []);
            } catch (error) {
                if (error.name !== 'AbortError') closeAutocomplete(menu);
            }
        }, 180);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAutocomplete(menu);
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && event.target !== input) {
            closeAutocomplete(menu);
        }
    });
}

async function loadGuideOptionsForPatient(selectElement, patientId, selectedGuideId = 0, includeFinished = false) {
    if (!selectElement) return;

    if (!patientId) {
        selectElement.innerHTML = '<option value="">Selecione o paciente primeiro</option>';
        return;
    }

    selectElement.innerHTML = '<option value="">Carregando...</option>';
    const params = new URLSearchParams({ paciente_id: String(patientId) });

    if (includeFinished) {
        params.set('incluir_finalizadas', '1');
    }

    const response = await fetch('buscar_guias.php?' + params.toString());
    const html = await response.text();
    selectElement.innerHTML = html;

    if (selectedGuideId > 0) {
        selectElement.value = String(selectedGuideId);
    }
}

async function loadGuidesForPatient(patientId, selectedGuideId = 0) {
    await loadGuideOptionsForPatient(attendanceGuia, patientId, selectedGuideId);
}

function setFilterGuideTextMode() {
    if (attendanceFilterGuiaText) {
        attendanceFilterGuiaText.hidden = false;
        attendanceFilterGuiaText.disabled = false;
    }

    if (attendanceFilterGuiaSelect) {
        attendanceFilterGuiaSelect.hidden = true;
        attendanceFilterGuiaSelect.disabled = true;
        attendanceFilterGuiaSelect.innerHTML = '<option value="">Selecione o paciente</option>';
    }

    if (attendanceFilterGuiaId) {
        attendanceFilterGuiaId.value = '';
    }
}

async function loadFilterGuidesForPatient(patientId, selectedGuideId = 0) {
    if (!attendanceFilterGuiaSelect || !attendanceFilterGuiaText) return;

    if (!patientId) {
        setFilterGuideTextMode();
        return;
    }

    attendanceFilterGuiaText.value = '';
    attendanceFilterGuiaText.hidden = true;
    attendanceFilterGuiaText.disabled = true;
    attendanceFilterGuiaSelect.hidden = false;
    attendanceFilterGuiaSelect.disabled = false;
    await loadGuideOptionsForPatient(attendanceFilterGuiaSelect, patientId, selectedGuideId, true);

    if (attendanceFilterGuiaId) {
        attendanceFilterGuiaId.value = selectedGuideId > 0 ? String(selectedGuideId) : '';
    }
}

setupPatientAutocomplete(attendancePacienteBusca, attendancePacienteMenu, (patient) => {
    attendancePacienteId.value = patient ? patient.id : '';
    attendancePacienteNome.value = patient ? patient.nome : '';
    loadGuidesForPatient(patient ? patient.id : 0);
});

setupPatientAutocomplete(attendanceFilterPacienteBusca, attendanceFilterPacienteMenu, (patient) => {
    if (attendanceFilterPacienteId) {
        attendanceFilterPacienteId.value = patient ? patient.id : '';
    }

    if (patient) {
        loadFilterGuidesForPatient(patient.id);
        return;
    }

    setFilterGuideTextMode();
});

if (attendanceFilterGuiaSelect) {
    attendanceFilterGuiaSelect.addEventListener('change', () => {
        if (attendanceFilterGuiaId) {
            attendanceFilterGuiaId.value = attendanceFilterGuiaSelect.value || '';
        }
    });
}

if (attendancePacienteId && attendancePacienteId.value) {
    loadGuidesForPatient(attendancePacienteId.value, selectedAttendanceGuide);
}

if (selectedFilterPatientId > 0) {
    loadFilterGuidesForPatient(selectedFilterPatientId, selectedFilterGuideId);
} else {
    setFilterGuideTextMode();
}

if (attendanceForm) {
    attendanceForm.addEventListener('submit', (event) => {
        if (!attendancePacienteId.value) {
            event.preventDefault();
            alert('Escolha um paciente da lista de autocomplete.');
            attendancePacienteBusca.focus();
            return;
        }

        if (!attendanceGuia.value) {
            event.preventDefault();
            alert('Escolha uma guia em aberto.');
            attendanceGuia.focus();
        }
    });
}

document.addEventListener('keydown', (event) => {
    if (!event.altKey || event.key.toLowerCase() !== 's') return;

    const attendanceModal = document.getElementById('attendanceModal');
    const editAttendanceModal = document.getElementById('editAttendanceModal');

    if (attendanceModal && attendanceModal.classList.contains('show') && attendanceForm) {
        event.preventDefault();
        attendanceForm.requestSubmit();
    }

    if (editAttendanceModal && editAttendanceModal.classList.contains('show') && editAttendanceForm) {
        event.preventDefault();
        editAttendanceForm.requestSubmit();
    }
});
</script>

<?php if ($autoOpenAttendanceModal): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('attendanceModal');
    if (modalElement && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
<?php endif; ?>

<?php if ($autoOpenEditAttendanceModal && $editAttendance): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('editAttendanceModal');
    if (modalElement && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
<?php endif; ?>

</body>
</html>
