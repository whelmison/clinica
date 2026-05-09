<?php include 'config/db.php'; ?>
<?php
$canManageLegacyGuides = app_has_any_role(['administrativo', 'secretaria', 'desenvolvedor']);
$clinicId = app_active_clinic_id();
$guideMessage = '';
$autoOpenGuideModal = app_query_int('open_new') === 1;
$guideFormValues = [
    'paciente_id' => 0,
    'profissional_id' => 0,
    'plano_id' => 0,
    'codigo' => '',
    'total_sessoes' => '',
    'valor_guia' => '',
    'data' => date('Y-m-d'),
    'autorizada' => 0,
    'status_operacional' => 'criada',
];
$editGuideId = app_query_int('edit_id');
$editGuide = null;
$editMessage = '';
$autoOpenEditGuideModal = false;
$isGuidePost = app_request_method() === 'POST';
$guideFilters = [
    'paciente' => trim((string) ($isGuidePost ? app_request_post('filter_paciente', '') : app_request_query('paciente', ''))),
    'guia' => trim((string) ($isGuidePost ? app_request_post('filter_guia', '') : app_request_query('guia', ''))),
    'profissional_id' => $isGuidePost ? app_post_int('filter_profissional_id') : app_query_int('profissional_id'),
    'status_guia' => trim((string) ($isGuidePost ? app_request_post('filter_status_guia', '') : app_request_query('status_guia', ''))),
    'status_financeiro' => trim((string) ($isGuidePost ? app_request_post('filter_status_financeiro', '') : app_request_query('status_financeiro', ''))),
    'autorizada' => trim((string) ($isGuidePost ? app_request_post('filter_autorizada', '') : app_request_query('autorizada', ''))),
    'mes' => trim((string) ($isGuidePost ? app_request_post('filter_mes', '') : app_request_query('mes', ''))),
];
$guideFilters['status_guia'] = $guideFilters['status_guia'] !== ''
    ? app_normalize_guide_operational_status($guideFilters['status_guia'])
    : '';
$shouldLoadGuides = app_request_query('filtrar', '') === '1'
    || ($isGuidePost && app_request_post('filter_filtrar', '') === '1')
    || $editGuideId > 0;
$professionalsForFilter = app_stmt_all($conn, 'SELECT id, nome FROM profissionais WHERE clinica_id = ? ORDER BY nome', 'i', [$clinicId]);
$guideOperationalStatuses = app_guide_operational_statuses();

if (!function_exists('app_legacy_guide_filter_query')) {
    function app_legacy_guide_filter_query(array $filters, array $extra = []): string
    {
        return app_build_query([
            'paciente' => $filters['paciente'] ?? '',
            'guia' => $filters['guia'] ?? '',
            'profissional_id' => (int) ($filters['profissional_id'] ?? 0) ?: null,
            'status_guia' => $filters['status_guia'] ?? '',
            'status_financeiro' => $filters['status_financeiro'] ?? '',
            'autorizada' => $filters['autorizada'] ?? '',
            'mes' => $filters['mes'] ?? '',
            'filtrar' => 1,
        ], $extra);
    }
}

if (!function_exists('app_legacy_guide_filter_inputs')) {
    function app_legacy_guide_filter_inputs(array $filters, bool $shouldLoadGuides): string
    {
        $inputs = [
            'filter_paciente' => $filters['paciente'] ?? '',
            'filter_guia' => $filters['guia'] ?? '',
            'filter_profissional_id' => (int) ($filters['profissional_id'] ?? 0),
            'filter_status_guia' => $filters['status_guia'] ?? '',
            'filter_status_financeiro' => $filters['status_financeiro'] ?? '',
            'filter_autorizada' => $filters['autorizada'] ?? '',
            'filter_mes' => $filters['mes'] ?? '',
            'filter_filtrar' => $shouldLoadGuides ? '1' : '',
        ];
        $html = '';

        foreach ($inputs as $name => $value) {
            $html .= '<input type="hidden" name="' . app_h($name) . '" value="' . app_h((string) $value) . '">' . PHP_EOL;
        }

        return $html;
    }
}

if (app_request_method() === 'POST' && ($_POST['action'] ?? '') === 'save_legacy_guide') {
    if (!$canManageLegacyGuides) {
        app_flash('danger', 'Sem permissao para cadastrar guias.');
        app_redirect('guias.php');
    }

    $guideFormValues = [
        'paciente_id' => app_post_int('paciente_id'),
        'profissional_id' => app_post_int('profissional_id'),
        'plano_id' => app_post_int('plano_id'),
        'codigo' => trim((string) ($_POST['codigo'] ?? '')),
        'total_sessoes' => app_post_int('total_sessoes'),
        'valor_guia' => (string) ($_POST['valor_guia'] ?? ''),
        'data' => (string) ($_POST['data'] ?? date('Y-m-d')),
        'autorizada' => isset($_POST['autorizada']) ? 1 : 0,
        'status_operacional' => app_normalize_guide_operational_status((string) ($_POST['status_operacional'] ?? 'criada')),
    ];

    if ($guideFormValues['status_operacional'] === 'cancelada') {
        $guideFormValues['autorizada'] = 0;
    } elseif ($guideFormValues['status_operacional'] === 'autorizada') {
        $guideFormValues['autorizada'] = 1;
    } elseif ((int) $guideFormValues['autorizada'] === 1 && in_array($guideFormValues['status_operacional'], ['criada', 'aguardando_autorizacao'], true)) {
        $guideFormValues['status_operacional'] = 'autorizada';
    }

    if ($guideFormValues['paciente_id'] <= 0) {
        $guideMessage = 'Selecione o paciente.';
    } elseif ($guideFormValues['profissional_id'] <= 0) {
        $guideMessage = 'Selecione o profissional.';
    } elseif ($guideFormValues['plano_id'] <= 0) {
        $guideMessage = 'Selecione o plano.';
    } elseif ($guideFormValues['total_sessoes'] <= 0) {
        $guideMessage = 'Quantidade de sessoes deve ser maior que zero.';
    } else {
        $pacienteId = (int) $guideFormValues['paciente_id'];
        $profissionalId = (int) $guideFormValues['profissional_id'];
        $planoId = (int) $guideFormValues['plano_id'];
        $totalSessoes = (int) $guideFormValues['total_sessoes'];
        $dataGuia = $conn->real_escape_string($guideFormValues['data'] ?: date('Y-m-d'));
        $codigo = $guideFormValues['codigo'];

        if ($codigo === '') {
            $last = app_stmt_one($conn, 'SELECT MAX(id) as max FROM guias WHERE clinica_id = ?', 'i', [$clinicId])['max'] ?? 0;
            $codigo = 'GUIA-' . str_pad(((int) $last) + 1, 3, '0', STR_PAD_LEFT);
            $guideFormValues['codigo'] = $codigo;
        }

        $codigoEsc = $conn->real_escape_string($codigo);

        $existe = app_stmt_one($conn, 'SELECT id FROM guias WHERE clinica_id = ? AND codigo = ? LIMIT 1', 'is', [$clinicId, $codigo]);

        if ($existe) {
            $guideMessage = 'Ja existe uma guia com esse codigo.';
        } else {
            $check = $conn->query("
                SELECT COUNT(*) as total
                FROM guias g
                WHERE g.clinica_id = {$clinicId}
                AND g.paciente_id = {$pacienteId}
                AND g.profissional_id = {$profissionalId}
                AND COALESCE(g.status_operacional, 'criada') NOT IN ('cancelada', 'finalizada')
                AND (
                    SELECT COUNT(*) FROM atendimentos a WHERE a.clinica_id = g.clinica_id AND a.guia_id = g.id
                ) < g.total_sessoes
            ")->fetch_assoc();

            if ((int) ($check['total'] ?? 0) > 0) {
                $guideMessage = 'Este paciente ja possui uma guia em aberto para este profissional.';
            } else {
                $plano = $conn->query("
                    SELECT nome, valor_sessao
                    FROM planos
                    WHERE clinica_id = {$clinicId}
                    AND id = {$planoId}
                    LIMIT 1
                ")->fetch_assoc();

                $planoNome = (string) ($plano['nome'] ?? '');
                $valorSessao = (float) ($plano['valor_sessao'] ?? 0);
                $valorManual = $guideFormValues['valor_guia'];

                if ($planoNome === 'Particular' && trim($valorManual) !== '') {
                    $valorLimpo = trim(str_replace(['R$', ' '], '', $valorManual));
                    $valorLimpo = str_contains($valorLimpo, ',')
                        ? str_replace(',', '.', str_replace('.', '', $valorLimpo))
                        : $valorLimpo;
                    $valorGuia = (float) $valorLimpo;
                } else {
                    $valorGuia = $valorSessao * $totalSessoes;
                }

                $statusOperacional = $conn->real_escape_string($guideFormValues['status_operacional']);
                $ok = $conn->query("
                    INSERT INTO guias (clinica_id, codigo, paciente_id, profissional_id, plano_id, total_sessoes, data, valor_guia, recebido, autorizada, status_operacional)
                    VALUES ({$clinicId}, '{$codigoEsc}', {$pacienteId}, {$profissionalId}, {$planoId}, {$totalSessoes}, '{$dataGuia}', {$valorGuia}, 0, " . (int) $guideFormValues['autorizada'] . ", '{$statusOperacional}')
                ");

                if ($ok) {
                    app_flash('success', 'Guia cadastrada com sucesso.');
                    app_redirect('guias.php?' . app_legacy_guide_filter_query($guideFilters));
                }

                $guideMessage = 'Nao foi possivel cadastrar a guia.';
            }
        }
    }

    $autoOpenGuideModal = true;
}

if (app_request_method() === 'POST' && ($_POST['action'] ?? '') === 'authorize_legacy_guide') {
    if (!$canManageLegacyGuides) {
        app_flash('danger', 'Sem permissao para autorizar guias.');
        app_redirect('guias.php');
    }

    $guideId = app_post_int('guide_id');

    if ($guideId <= 0) {
        app_flash('danger', 'Guia nao encontrada.');
    } else {
        $ok = app_stmt_execute(
            $conn,
            'UPDATE guias SET autorizada = 1, status_operacional = ? WHERE clinica_id = ? AND id = ?',
            'sii',
            ['autorizada', $clinicId, $guideId]
        );
        app_flash($ok ? 'success' : 'danger', $ok ? 'Guia autorizada com sucesso.' : 'Nao foi possivel autorizar a guia.');
    }

    app_redirect('guias.php?' . app_legacy_guide_filter_query($guideFilters));
}

if (app_request_method() === 'POST' && ($_POST['action'] ?? '') === 'update_legacy_guide') {
    if (!$canManageLegacyGuides) {
        app_flash('danger', 'Sem permissao para editar guias.');
        app_redirect('guias.php');
    }

    $guideId = app_post_int('guide_id');
    $codigo = trim((string) ($_POST['codigo'] ?? ''));
    $profissionalId = app_post_int('profissional_id');
    $data = (string) ($_POST['data'] ?? date('Y-m-d'));
    $total = app_post_int('total_sessoes');
    $valorManual = (string) ($_POST['valor_guia'] ?? '');
    $autorizada = isset($_POST['autorizada']) ? 1 : 0;
    $statusOperacional = app_normalize_guide_operational_status((string) ($_POST['status_operacional'] ?? 'criada'));

    if ($statusOperacional === 'cancelada') {
        $autorizada = 0;
    } elseif ($statusOperacional === 'autorizada') {
        $autorizada = 1;
    } elseif ($autorizada === 1 && in_array($statusOperacional, ['criada', 'aguardando_autorizacao'], true)) {
        $statusOperacional = 'autorizada';
    }

    $editGuide = app_stmt_one(
        $conn,
        'SELECT g.*, pl.nome AS plano, pl.valor_sessao
         FROM guias g
         LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
         WHERE g.clinica_id = ? AND g.id = ?
         LIMIT 1',
        'ii',
        [$clinicId, $guideId]
    );

    if (!$editGuide) {
        $editMessage = 'Guia nao encontrada.';
    } elseif ($codigo === '') {
        $editMessage = 'Informe o codigo da guia.';
    } elseif ($profissionalId <= 0) {
        $editMessage = 'Selecione o profissional.';
    } elseif ($total <= 0) {
        $editMessage = 'Quantidade de sessoes deve ser maior que zero.';
    } else {
        $exists = app_stmt_one($conn, 'SELECT id FROM guias WHERE clinica_id = ? AND codigo = ? AND id != ? LIMIT 1', 'isi', [$clinicId, $codigo, $guideId]);

        if ($exists) {
            $editMessage = 'Ja existe uma guia com esse codigo.';
        } else {
            $valorSessao = (float) ($editGuide['valor_sessao'] ?? 0);
            $planoNome = (string) ($editGuide['plano'] ?? '');

            if ($planoNome === 'Particular' && trim($valorManual) !== '') {
                $valorLimpo = trim(str_replace(['R$', ' '], '', $valorManual));
                $valorLimpo = str_contains($valorLimpo, ',')
                    ? str_replace(',', '.', str_replace('.', '', $valorLimpo))
                    : $valorLimpo;
                $valorGuia = (float) $valorLimpo;
            } else {
                $valorGuia = $valorSessao * $total;
            }

            $ok = app_stmt_execute(
                $conn,
                'UPDATE guias SET codigo = ?, profissional_id = ?, data = ?, total_sessoes = ?, valor_guia = ?, autorizada = ?, status_operacional = ? WHERE clinica_id = ? AND id = ?',
                'sisidisii',
                [$codigo, $profissionalId, $data, $total, $valorGuia, $autorizada, $statusOperacional, $clinicId, $guideId]
            );

            if ($ok) {
                app_flash('success', 'Guia atualizada com sucesso.');
                app_redirect('guias.php?' . app_legacy_guide_filter_query($guideFilters));
            }

            $editMessage = 'Nao foi possivel atualizar a guia.';
        }
    }

    $editGuideId = $guideId;
    $autoOpenEditGuideModal = true;
}

if ($editGuideId > 0 && !$editGuide) {
    $editGuide = app_stmt_one(
        $conn,
        'SELECT g.*, p.nome AS paciente_nome, pl.nome AS plano, pl.valor_sessao,
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
         WHERE g.clinica_id = ? AND g.id = ?
         LIMIT 1',
        'iii',
        [$clinicId, $clinicId, $editGuideId]
    );
    $autoOpenEditGuideModal = $editGuide !== null;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Guias</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">

<style>
body {
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}

.guide-shell {
    padding: 0.7rem 0.9rem 1rem;
}

.guide-topbar {
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

.guide-kicker {
    margin: 0 0 0.12rem;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    opacity: 0.78;
    text-transform: uppercase;
}

.guide-topbar h3 {
    margin: 0;
    font-size: 1.12rem;
}

.guide-topbar p {
    margin: 0.16rem 0 0;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.74rem;
    line-height: 1.18;
}

.guide-top-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.42rem;
    flex-wrap: wrap;
}

.guide-top-actions .btn {
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.34rem 0.68rem;
}

table { font-size: 12px; }
td, th { white-space: nowrap; }
.acoes a, .acoes button { padding: 3px 6px; font-size: 11px; }
.valor { text-align: right; }
tfoot { font-weight: bold; background: #f8f9fa; }

.status-finalizado { background: #d9f2e3; }
.status-finalizando { background: #fff4cc; }
.status-andamento { background: #d7eef7; }
tr.status-muted { background: #f7f9fa; }
tr.status-info { background: #d7eef7; }
tr.status-warning { background: #fff4cc; }
tr.status-success,
tr.status-success-soft { background: #d9f2e3; }
tr.status-danger { background: #ffe3e6; }

.badge-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.badge-finalizado { background: #198754; color: #fff; }
.badge-finalizando { background: #ffc107; color: #5c4300; }
.badge-andamento { background: #0dcaf0; color: #083c4b; }
.badge-status.status-muted { background: #edf2f4; color: #526973; }
.badge-status.status-info { background: #0dcaf0; color: #083c4b; }
.badge-status.status-warning { background: #ffc107; color: #5c4300; }
.badge-status.status-success { background: #198754; color: #fff; }
.badge-status.status-success-soft { background: #d9f2e3; color: #146c43; }
.badge-status.status-danger { background: #dc3545; color: #fff; }

.guide-filter-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.guide-filter-card .form-control,
.guide-filter-card .form-select {
    min-height: 36px;
    border-radius: 12px;
    border-color: #dbe7ec;
    font-size: 0.78rem;
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

.readonly {
    background:#e9ecef;
    font-weight:bold;
}

.guide-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}

.guide-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.guide-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.guide-form .form-control,
.guide-form .form-select {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.guide-form .btn,
.guide-modal .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}

.guide-shortcuts {
    color: #68828f;
    font-size: 0.72rem;
}

@media (max-width: 900px) {
    .guide-topbar {
        align-items: stretch;
        flex-direction: column;
    }

    .guide-top-actions {
        justify-content: flex-start;
    }
}

@media print {
button, select, a { display: none !important; }
}
</style>

</head>

<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid guide-shell">

<section class="guide-topbar mb-3">
<div>
<p class="guide-kicker">Gestao de guias</p>
<h3>Guias</h3>
<p>Acompanhe sessoes, faturamento, glosas e recebimentos.</p>
</div>
<div class="guide-top-actions">
<button type="submit" form="guideFilterForm" name="filtrar" value="1" class="btn btn-light text-secondary">Filtrar</button>
<a href="guias.php" class="btn btn-outline-light">Limpar filtro</a>
<?php if ($canManageLegacyGuides): ?>
<button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#novaGuiaModal">+ Nova Guia</button>
<?php endif; ?>
<div class="dropdown">
<button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Acoes</button>
<ul class="dropdown-menu dropdown-menu-end">
<li><button class="dropdown-item" type="button" onclick="imprimir()">Exportar PDF</button></li>
<li><button class="dropdown-item" type="button" onclick="exportarJpg()">Exportar JPG</button></li>
<li><button class="dropdown-item" type="button" onclick="enviarWhatsappJpg()">Enviar WhatsApp JPG</button></li>
</ul>
</div>
</div>
</section>

<div class="guide-filter-card p-2 mb-3">
<form method="GET" id="guideFilterForm" class="row g-2 align-items-end">

<div class="col-md-3">
<label class="form-label small text-muted">Paciente</label>
<div class="autocomplete-wrap">
<input type="text" id="fPaciente" name="paciente" class="form-control" placeholder="Digite o nome" autocomplete="off" value="<?= app_h($guideFilters['paciente']) ?>">
<div class="autocomplete-menu" id="fPacienteMenu"></div>
</div>
</div>

<div class="col-md-1">
<label class="form-label small text-muted">Guia</label>
<input type="text" id="fGuia" name="guia" class="form-control" placeholder="Numero/codigo" value="<?= app_h($guideFilters['guia']) ?>">
</div>

<div class="col-md-2">
<label class="form-label small text-muted">Profissional</label>
<select id="fProfissional" name="profissional_id" class="form-control">
<option value="">Profissional</option>
<?php foreach ($professionalsForFilter as $professional): ?>
<option value="<?= (int) $professional['id'] ?>" <?= (int) $guideFilters['profissional_id'] === (int) $professional['id'] ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<label class="form-label small text-muted">Status operacional</label>
<select id="fStatusGuia" name="status_guia" class="form-control">
<option value="">Todos</option>
<?php foreach ($guideOperationalStatuses as $statusValue => $statusLabel): ?>
<option value="<?= app_h($statusValue) ?>" <?= $guideFilters['status_guia'] === $statusValue ? 'selected' : '' ?>><?= app_h($statusLabel) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-1">
<label class="form-label small text-muted">Situacao</label>
<select id="fStatusFin" name="status_financeiro" class="form-control">
<option value="">Situacao</option>
<option value="Aberta" <?= $guideFilters['status_financeiro'] === 'Aberta' ? 'selected' : '' ?>>Aberta</option>
<option value="Parcial" <?= $guideFilters['status_financeiro'] === 'Parcial' ? 'selected' : '' ?>>Parcial</option>
<option value="Paga" <?= $guideFilters['status_financeiro'] === 'Paga' ? 'selected' : '' ?>>Paga</option>
</select>
</div>

<div class="col-md-1">
<label class="form-label small text-muted">Autorizada</label>
<select id="fAutorizada" name="autorizada" class="form-control">
<option value="">Todas</option>
<option value="1" <?= $guideFilters['autorizada'] === '1' ? 'selected' : '' ?>>Sim</option>
<option value="0" <?= $guideFilters['autorizada'] === '0' ? 'selected' : '' ?>>Nao</option>
</select>
</div>

<div class="col-md-1">
<label class="form-label small text-muted">Mes</label>
<input type="month" id="fMes" name="mes" class="form-control" value="<?= app_h($guideFilters['mes']) ?>">
</div>

<div class="d-none">
<input type="hidden" name="filtrar" value="1">
</div>

</form>
</div>

<div class="card p-3">

<table class="table table-bordered">

<thead>
<tr>
<th>Guia</th>
<th>Paciente</th>
<th>Profissional</th>
<th>Data</th>
<th>Sess</th>
<th>Usadas</th>
<th>Rest</th>
<th>Status operacional</th>
<th>Autorizada</th>
<th>Situacao</th>
<th class="valor">Guia</th>
<th class="valor">Fat</th>
<th class="valor">Glosa</th>
<th class="valor">Rec</th>
<th class="valor">Saldo</th>
<th>Acoes</th>
</tr>
</thead>

<tbody id="tbody">

<?php
if ($shouldLoadGuides):
    $where = ["g.clinica_id = {$clinicId}"];

    if ($guideFilters['paciente'] !== '') {
        $pacienteFiltro = $conn->real_escape_string($guideFilters['paciente']);
        $where[] = "p.nome LIKE '%{$pacienteFiltro}%'";
    }

    if ($guideFilters['guia'] !== '') {
        $guiaFiltro = $conn->real_escape_string($guideFilters['guia']);
        $where[] = "g.codigo LIKE '%{$guiaFiltro}%'";
    }

    if ($guideFilters['mes'] !== '' && preg_match('/^\d{4}-\d{2}$/', $guideFilters['mes'])) {
        $mesFiltro = $conn->real_escape_string($guideFilters['mes']);
        $where[] = "g.data LIKE '{$mesFiltro}%'";
    }

    if ($guideFilters['profissional_id'] > 0) {
        $where[] = 'g.profissional_id = ' . (int) $guideFilters['profissional_id'];
    }

    if ($guideFilters['autorizada'] === '1') {
        $where[] = 'g.autorizada = 1';
    } elseif ($guideFilters['autorizada'] === '0') {
        $where[] = 'g.autorizada = 0';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $res = $conn->query("
    SELECT
        g.*,
        p.nome as paciente_nome,
        pr.nome as profissional_nome,
        pl.valor_sessao,
        COALESCE(a.usadas, 0) AS usadas,
        COALESCE(a.glosas, 0) AS glosas
    FROM guias g
    LEFT JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
    LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
    LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
    LEFT JOIN (
        SELECT
            guia_id,
            COUNT(*) AS usadas,
            SUM(CASE WHEN status_atendimento = 'Glosado' THEN 1 ELSE 0 END) AS glosas
        FROM atendimentos
        WHERE clinica_id = {$clinicId}
        GROUP BY guia_id
    ) a ON a.guia_id = g.id
    {$whereSql}
    ORDER BY g.data DESC, p.nome ASC
    LIMIT 500
    ");

while ($g = $res->fetch_assoc()):
    $usadas = (int) $g['usadas'];
    $glosas = (int) $g['glosas'];

    $faturadas = $usadas - $glosas;
    $valorSessao = floatval($g['valor_sessao']);
    $valorFaturado = $faturadas * $valorSessao;
    $valorGlosado = $glosas * $valorSessao;
    $saldo = $valorFaturado - $g['recebido'];
    $saldoBaixa = max(0, (float) $g['valor_guia'] - (float) $g['recebido']);
    $restantes = (int) $g['total_sessoes'] - (int) $usadas;

    $statusData = app_guide_operational_status_data($g);
    $statusGuia = (string) $statusData['value'];
    $class = (string) $statusData['class'];
    $badgeStatusGuia = "<span class='badge-status {$class}'>" . app_h((string) $statusData['label']) . "</span>";

    $statusFin = 'Aberta';
    if ($g['recebido'] > 0 && $saldo > 0) $statusFin = 'Parcial';
    if ($saldo <= 0 && $g['recebido'] > 0) $statusFin = 'Paga';

    if ($guideFilters['status_guia'] !== '' && $guideFilters['status_guia'] !== $statusGuia) {
        continue;
    }

    if ($guideFilters['status_financeiro'] !== '' && $guideFilters['status_financeiro'] !== $statusFin) {
        continue;
    }

    $dataMes = substr($g['data'], 0, 7);
    $pacienteNome = htmlspecialchars($g['paciente_nome'] ?? '', ENT_QUOTES);
    $profissionalNome = htmlspecialchars($g['profissional_nome'] ?? '', ENT_QUOTES);
    $codigoGuia = htmlspecialchars($g['codigo'] ?? '', ENT_QUOTES);
    $editGuideUrl = 'guias.php?' . app_legacy_guide_filter_query($guideFilters, ['edit_id' => (int) $g['id']]);
    $editGuideUrlEsc = htmlspecialchars($editGuideUrl, ENT_QUOTES);
    $badgeAutorizada = (int) ($g['autorizada'] ?? 0) === 1
        ? "<span class='badge-status badge-finalizado'>Sim</span>"
        : "<span class='badge-status badge-finalizando'>Nao</span>";
    $authorizeButton = '';

    if ($canManageLegacyGuides && (int) ($g['autorizada'] ?? 0) !== 1 && $statusGuia !== 'cancelada') {
        $authorizeButton = "<form method='POST' class='d-inline'>
    <input type='hidden' name='action' value='authorize_legacy_guide'>
    <input type='hidden' name='guide_id' value='{$g['id']}'>
    " . app_legacy_guide_filter_inputs($guideFilters, $shouldLoadGuides) . "
    <button type='submit' class='btn btn-sm btn-outline-success' title='Marcar esta guia como autorizada'>Autorizar</button>
    </form>";
    }

    echo "<tr class='$class'
    data-paciente='$pacienteNome'
    data-profissional='$profissionalNome'
    data-profissional-id='{$g['profissional_id']}'
    data-guia='$codigoGuia'
    data-statusguia='$statusGuia'
    data-statusfin='$statusFin'
    data-autorizada='" . (int) ($g['autorizada'] ?? 0) . "'
    data-mes='$dataMes'
    data-data='{$g['data']}'
    data-saldo='$saldoBaixa'
    data-finalizada='" . ($statusGuia === 'finalizada' ? 1 : 0) . "'
    data-id='{$g['id']}'
    >
    <td>{$g['codigo']}</td>
    <td>{$g['paciente_nome']}</td>
    <td>{$g['profissional_nome']}</td>
    <td>" . date('d/m/Y', strtotime($g['data'])) . "</td>
    <td>{$g['total_sessoes']}</td>
    <td>$usadas</td>
    <td>$restantes</td>
    <td>$badgeStatusGuia</td>
    <td>$badgeAutorizada</td>
    <td>$statusFin</td>
    <td class='valor'>" . number_format($g['valor_guia'], 2, ',', '.') . "</td>
    <td class='valor'>" . number_format($valorFaturado, 2, ',', '.') . "</td>
    <td class='valor text-danger'>" . number_format($valorGlosado, 2, ',', '.') . "</td>
    <td class='valor text-success'>" . number_format($g['recebido'], 2, ',', '.') . "</td>
    <td class='valor'>" . number_format($saldo, 2, ',', '.') . "</td>
    <td class='acoes'>
    " . ($canManageLegacyGuides
        ? "<a href='{$editGuideUrlEsc}' class='btn btn-sm btn-outline-primary'>Editar</a>
    {$authorizeButton}
    <form method='POST' action='excluir_guia.php' class='d-inline' onsubmit=\"return confirm('Excluir esta guia?')\">
    <input type='hidden' name='id' value='{$g['id']}'>
    <button type='submit' class='btn btn-sm btn-outline-danger'>Excluir</button>
    </form>
    <button onclick='baixar(this)' class='btn btn-sm btn-outline-success'>Baixar</button>"
        : "<span class='text-muted small'>Somente leitura</span>") . "
    </td>
    </tr>";
endwhile;
else:
?>
<tr>
<td colspan="16" class="text-center py-4 text-muted">
Use os filtros acima e clique em <strong>Filtrar</strong> para consultar as guias.
</td>
</tr>
<?php endif; ?>

</tbody>

<tfoot>
<tr>
<td colspan="16" id="totais">Totais</td>
</tr>
</tfoot>

</table>

</div>

</div>

<?php if ($canManageLegacyGuides): ?>
<div class="modal fade guide-modal" id="novaGuiaModal" tabindex="-1" aria-labelledby="novaGuiaModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<div>
<h5 class="modal-title" id="novaGuiaModalLabel">Nova guia</h5>
</div>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
</div>
<div class="modal-body">

<?php if ($guideMessage): ?>
<div class="alert alert-warning"><?= app_h($guideMessage) ?></div>
<?php endif; ?>

<form method="POST" id="novaGuiaForm" class="guide-form">
<input type="hidden" name="action" value="save_legacy_guide">
<input type="hidden" name="filter_paciente" value="<?= app_h($guideFilters['paciente']) ?>">
<input type="hidden" name="filter_guia" value="<?= app_h($guideFilters['guia']) ?>">
<input type="hidden" name="filter_profissional_id" value="<?= (int) $guideFilters['profissional_id'] ?>">
<input type="hidden" name="filter_status_guia" value="<?= app_h($guideFilters['status_guia']) ?>">
<input type="hidden" name="filter_status_financeiro" value="<?= app_h($guideFilters['status_financeiro']) ?>">
<input type="hidden" name="filter_autorizada" value="<?= app_h($guideFilters['autorizada']) ?>">
<input type="hidden" name="filter_mes" value="<?= app_h($guideFilters['mes']) ?>">
<input type="hidden" name="filter_filtrar" value="<?= $shouldLoadGuides ? '1' : '' ?>">

<div class="row g-3">
<div class="col-md-6">
<label class="form-label">Paciente</label>
<input type="hidden" name="paciente_id" id="paciente_id" value="<?= (int) $guideFormValues['paciente_id'] ?>">
<div class="autocomplete-wrap">
<input type="text" id="pacienteBusca" class="form-control" autocomplete="off" required
       title="Digite parte do nome e escolha o paciente da lista."
       placeholder="Digite para buscar o paciente">
<div class="autocomplete-menu" id="pacienteBuscaMenu"></div>
</div>
</div>

<div class="col-md-6">
<label class="form-label">Profissional</label>
<select name="profissional_id" id="profissional" class="form-control" required title="Profissional responsavel por atender esta guia.">
<option value="">Selecione</option>
<?php foreach ($professionalsForFilter as $professional): ?>
<option value="<?= (int) $professional['id'] ?>" <?= (int) $guideFormValues['profissional_id'] === (int) $professional['id'] ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Plano</label>
<select name="plano_id" id="plano" class="form-control" required title="Escolha o plano para calcular o valor da guia.">
<option value="">Selecione</option>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Guia</label>
<input type="text" name="codigo" class="form-control" value="<?= app_h($guideFormValues['codigo']) ?>"
       title="Informe a numeracao quando existir. Se deixar vazio, o sistema gera automaticamente."
       placeholder="Opcional: deixe vazio para gerar automatico">
</div>

<div class="col-md-3">
<label class="form-label">Total de sessoes</label>
<input type="number" name="total_sessoes" id="total" class="form-control" value="<?= app_h((string) $guideFormValues['total_sessoes']) ?>" min="1" required
       title="Quantidade total de sessoes autorizadas nesta guia.">
</div>

<div class="col-md-3">
<label class="form-label">Data de emissao</label>
<input type="date" name="data" class="form-control" value="<?= app_h($guideFormValues['data']) ?>"
       title="Data em que a guia foi emitida ou cadastrada.">
</div>

<div class="col-md-6">
<label class="form-label">Status operacional</label>
<select name="status_operacional" class="form-control" title="Estado operacional da guia. Em uso, ultimas sessoes e finalizada tambem sao recalculados pelos atendimentos.">
<?php foreach ($guideOperationalStatuses as $statusValue => $statusLabel): ?>
<option value="<?= app_h($statusValue) ?>" <?= ($guideFormValues['status_operacional'] ?? 'criada') === $statusValue ? 'selected' : '' ?>><?= app_h($statusLabel) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Valor da guia</label>
<input type="text" name="valor_guia" id="valor_guia" class="form-control readonly" value="<?= app_h($guideFormValues['valor_guia']) ?>"
       title="Calculado pelo valor da sessao vezes o total. No plano Particular pode ser preenchido manualmente.">
</div>

<div class="col-md-6 d-flex align-items-end">
<div class="form-check pb-2">
<input class="form-check-input" type="checkbox" name="autorizada" id="guiaAutorizada" <?= (int) ($guideFormValues['autorizada'] ?? 0) === 1 ? 'checked' : '' ?>
       title="Somente guias autorizadas podem gerar atendimento realizado.">
<label class="form-check-label" for="guiaAutorizada">Guia autorizada</label>
</div>
</div>
</div>
</form>
</div>
<div class="modal-footer">
<div class="me-auto guide-shortcuts">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</div>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
<button type="submit" form="novaGuiaForm" class="btn btn-primary px-4">Salvar guia <span class="small">(Alt+S)</span></button>
</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if ($canManageLegacyGuides && $editGuide): ?>
<div class="modal fade guide-modal" id="editarGuiaModal" tabindex="-1" aria-labelledby="editarGuiaModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<div>
<h5 class="modal-title" id="editarGuiaModalLabel">Editar guia</h5>
</div>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
</div>
<div class="modal-body">

<?php if ($editMessage): ?>
<div class="alert alert-warning"><?= app_h($editMessage) ?></div>
<?php endif; ?>

<form method="POST" id="editarGuiaForm" class="guide-form">
<input type="hidden" name="action" value="update_legacy_guide">
<input type="hidden" name="guide_id" value="<?= (int) $editGuide['id'] ?>">
<input type="hidden" name="filter_paciente" value="<?= app_h($guideFilters['paciente']) ?>">
<input type="hidden" name="filter_guia" value="<?= app_h($guideFilters['guia']) ?>">
<input type="hidden" name="filter_profissional_id" value="<?= (int) $guideFilters['profissional_id'] ?>">
<input type="hidden" name="filter_status_guia" value="<?= app_h($guideFilters['status_guia']) ?>">
<input type="hidden" name="filter_status_financeiro" value="<?= app_h($guideFilters['status_financeiro']) ?>">
<input type="hidden" name="filter_autorizada" value="<?= app_h($guideFilters['autorizada']) ?>">
<input type="hidden" name="filter_mes" value="<?= app_h($guideFilters['mes']) ?>">
<input type="hidden" name="filter_filtrar" value="<?= $shouldLoadGuides ? '1' : '' ?>">

<div class="row g-3">
<div class="col-md-6">
<label class="form-label">Paciente</label>
<input class="form-control readonly" value="<?= app_h((string) ($editGuide['paciente_nome'] ?? '')) ?>" readonly title="Paciente vinculado a esta guia.">
</div>

<div class="col-md-6">
<label class="form-label">Profissional</label>
<select name="profissional_id" id="editProfissional" class="form-control" required title="Profissional responsavel por atender esta guia.">
<option value="">Selecione</option>
<?php foreach ($professionalsForFilter as $professional): ?>
<option value="<?= (int) $professional['id'] ?>" <?= (int) ($editGuide['profissional_id'] ?? 0) === (int) $professional['id'] ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Plano</label>
<input class="form-control readonly" value="<?= app_h((string) ($editGuide['plano'] ?? '')) ?>" readonly title="Plano vinculado a esta guia.">
</div>

<div class="col-md-6">
<label class="form-label">Guia</label>
<input type="text" name="codigo" class="form-control" value="<?= app_h((string) $editGuide['codigo']) ?>" required title="Numero ou codigo da guia.">
</div>

<div class="col-md-3">
<label class="form-label">Total de sessoes</label>
<input type="number" name="total_sessoes" id="editTotal" class="form-control" value="<?= (int) $editGuide['total_sessoes'] ?>" min="1" required title="Quantidade total de sessoes autorizadas nesta guia.">
</div>

<div class="col-md-3">
<label class="form-label">Sessoes usadas</label>
<input class="form-control readonly" value="<?= (int) ($editGuide['usadas'] ?? 0) ?>" readonly title="Quantidade de atendimentos ja vinculados a esta guia.">
</div>

<div class="col-md-3">
<label class="form-label">Data de emissao</label>
<input type="date" name="data" class="form-control" value="<?= app_h((string) $editGuide['data']) ?>" title="Data em que a guia foi emitida ou cadastrada.">
</div>

<div class="col-md-3">
<label class="form-label">Status operacional</label>
<select name="status_operacional" class="form-control" title="Estado operacional da guia. Em uso, ultimas sessoes e finalizada tambem sao recalculados pelos atendimentos.">
<?php $editStatusOperacional = app_normalize_guide_operational_status((string) ($editGuide['status_operacional'] ?? 'criada')); ?>
<?php foreach ($guideOperationalStatuses as $statusValue => $statusLabel): ?>
<option value="<?= app_h($statusValue) ?>" <?= $editStatusOperacional === $statusValue ? 'selected' : '' ?>><?= app_h($statusLabel) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Valor da guia</label>
<input type="text" name="valor_guia" id="editValorGuia" class="form-control readonly" value="R$ <?= number_format((float) $editGuide['valor_guia'], 2, ',', '.') ?>" title="Calculado pelo valor da sessao vezes o total. No plano Particular pode ser preenchido manualmente.">
</div>

<div class="col-md-6 d-flex align-items-end">
<div class="form-check pb-2">
<input class="form-check-input" type="checkbox" name="autorizada" id="editGuiaAutorizada" <?= (int) ($editGuide['autorizada'] ?? 0) === 1 ? 'checked' : '' ?>
       title="Somente guias autorizadas podem gerar atendimento realizado.">
<label class="form-check-label" for="editGuiaAutorizada">Guia autorizada</label>
</div>
</div>
</div>
</form>
</div>
<div class="modal-footer">
<div class="me-auto guide-shortcuts">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</div>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
<button type="submit" form="editarGuiaForm" class="btn btn-primary px-4">Salvar guia <span class="small">(Alt+S)</span></button>
</div>
</div>
</div>
</div>
<?php endif; ?>

<script>
let rows = document.querySelectorAll("tbody tr");
let fPaciente = document.getElementById("fPaciente");
let fGuia = document.getElementById("fGuia");
let fProfissional = document.getElementById("fProfissional");
let fStatusGuia = document.getElementById("fStatusGuia");
let fStatusFin = document.getElementById("fStatusFin");
let fAutorizada = document.getElementById("fAutorizada");
let fMes = document.getElementById("fMes");

function filtrar() {
    let p = fPaciente.value.trim().toLowerCase();
    let g = fGuia.value.trim().toLowerCase();
    let pr = fProfissional ? fProfissional.value : '';
    let sg = fStatusGuia.value;
    let sf = fStatusFin.value;
    let au = fAutorizada ? fAutorizada.value : '';
    let m = fMes.value;

    rows.forEach(r => {
        let show = true;

        if (p && !(r.dataset.paciente || '').toLowerCase().includes(p)) show = false;
        if (g && !(r.dataset.guia || '').toLowerCase().includes(g)) show = false;
        if (pr && r.dataset.profissionalId != pr) show = false;
        if (sg && r.dataset.statusguia != sg) show = false;
        if (sf && r.dataset.statusfin != sf) show = false;
        if (au && r.dataset.autorizada != au) show = false;
        if (m && r.dataset.mes != m) show = false;

        r.style.display = show ? '' : 'none';
    });

    ordenar();
    totais();
}

function ordenar() {
    let tbody = document.getElementById("tbody");
    let vis = [...rows].filter(r => r.style.display != 'none');

    vis.sort((a, b) => {
        return b.dataset.data.localeCompare(a.dataset.data) ||
        a.dataset.paciente.localeCompare(b.dataset.paciente);
    });

    vis.forEach(tr => tbody.appendChild(tr));
}

function moedaParaNumero(valor) {
    return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
}

function totais() {
    let prev = 0, fat = 0, glo = 0, rec = 0, sal = 0;

    rows.forEach(r => {
        if (r.style.display != 'none' && r.children.length >= 15) {
            prev += moedaParaNumero(r.children[10].innerText);
            fat += moedaParaNumero(r.children[11].innerText);
            glo += moedaParaNumero(r.children[12].innerText);
            rec += moedaParaNumero(r.children[13].innerText);
            sal += moedaParaNumero(r.children[14].innerText);
        }
    });

    document.getElementById("totais").innerHTML =
    "Previsto: R$ " + prev.toFixed(2) +
    " | Faturado: R$ " + fat.toFixed(2) +
    " | <span style='color:red'>Glosa: R$ " + glo.toFixed(2) + "</span>" +
    " | Recebido: R$ " + rec.toFixed(2) +
    " | Saldo: R$ " + sal.toFixed(2);
}

function baixar(btn) {
    let tr = btn.closest("tr");

    if (tr.dataset.finalizada != '1') {
        alert("Guia nao finalizada!");
        return;
    }

    let saldo = parseFloat(tr.dataset.saldo);

    if (saldo <= 0) {
        alert("Ja paga!");
        return;
    }

    window.location = "baixar_guia.php?id=" + tr.dataset.id + "&return_to=" + encodeURIComponent("guias.php" + window.location.search);
}

function limpar() {
    document.querySelectorAll(".guide-filter-card select,.guide-filter-card input").forEach(e => e.value = '');
    rows.forEach(r => r.style.display = 'none');
    totais();
}

function mostrarTodos() {
    rows.forEach(r => r.style.display = '');
    ordenar();
    totais();
}

function imprimir() {
    window.print();
}

function exportarJpg() {
    gerarImagemGuias().then(({ blob, filename }) => {
        baixarBlob(blob, filename);
    }).catch((error) => {
        alert(error.message || 'Nao foi possivel exportar JPG.');
    });
}

function enviarWhatsappJpg() {
    gerarImagemGuias().then(async ({ blob, filename }) => {
        const file = new File([blob], filename, { type: 'image/jpeg' });

        if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
            await navigator.share({
                title: 'Guias filtradas',
                text: 'Guias filtradas do sistema',
                files: [file]
            });
            return;
        }

        baixarBlob(blob, filename);
        window.open('https://web.whatsapp.com/send?text=' + encodeURIComponent('Imagem JPG das guias filtradas foi baixada. Anexe o arquivo ' + filename + ' nesta conversa.'), '_blank');
    }).catch((error) => {
        alert(error.message || 'Nao foi possivel preparar o envio para WhatsApp.');
    });
}

function linhasVisiveisDaTabela() {
    return [...document.querySelectorAll('#tbody tr')]
        .filter((row) => row.style.display !== 'none' && row.children.length > 1);
}

function htmlEscape(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function montarHtmlExportacao() {
    const rows = linhasVisiveisDaTabela();

    if (!rows.length) {
        throw new Error('Use os filtros e carregue ao menos uma guia antes de exportar.');
    }

    const headers = [...document.querySelectorAll('thead th')]
        .slice(0, -1)
        .map((cell) => `<th>${htmlEscape(cell.innerText.trim())}</th>`)
        .join('');
    const body = rows.map((row) => {
        const cells = [...row.children]
            .slice(0, -1)
            .map((cell) => `<td>${htmlEscape(cell.innerText.trim())}</td>`)
            .join('');
        return `<tr>${cells}</tr>`;
    }).join('');
    const totals = document.getElementById('totais')?.innerText || '';

    return `
        <div xmlns="http://www.w3.org/1999/xhtml" style="width:1600px;background:#fff;color:#16333f;font-family:Arial,Helvetica,sans-serif;padding:22px;">
            <h2 style="margin:0 0 4px;font-size:24px;">Guias filtradas</h2>
            <div style="margin-bottom:14px;color:#607985;font-size:13px;">Gerado em ${new Date().toLocaleString('pt-BR')}</div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#f2f7f8;">${headers}</tr></thead>
                <tbody>${body}</tbody>
            </table>
            <div style="margin-top:14px;font-weight:700;font-size:13px;">${htmlEscape(totals)}</div>
            <style>
                th,td{border:1px solid #dbe7ec;padding:7px 8px;text-align:left;vertical-align:top;}
                th{font-weight:800;color:#0f4c5c;text-transform:uppercase;font-size:10px;}
                td:nth-child(10),td:nth-child(11),td:nth-child(12),td:nth-child(13),td:nth-child(14){text-align:right;}
            </style>
        </div>`;
}

function gerarImagemGuias() {
    return new Promise((resolve, reject) => {
        let html;
        try {
            html = montarHtmlExportacao();
        } catch (error) {
            reject(error);
            return;
        }

        const rowCount = linhasVisiveisDaTabela().length;
        const width = 1644;
        const height = Math.min(28000, 130 + (rowCount * 34));
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
                <foreignObject width="100%" height="100%">${html}</foreignObject>
            </svg>`;
        const image = new Image();
        const svgUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));

        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, width, height);
            context.drawImage(image, 0, 0);
            URL.revokeObjectURL(svgUrl);

            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Nao foi possivel gerar a imagem.'));
                    return;
                }

                resolve({
                    blob,
                    filename: 'guias-filtradas-' + new Date().toISOString().slice(0, 10) + '.jpg'
                });
            }, 'image/jpeg', 0.92);
        };

        image.onerror = () => {
            URL.revokeObjectURL(svgUrl);
            reject(new Error('Nao foi possivel renderizar a imagem JPG.'));
        };

        image.src = svgUrl;
    });
}

function baixarBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

const plano = document.getElementById('plano');
const total = document.getElementById('total');
const valorGuia = document.getElementById('valor_guia');
const novaGuiaForm = document.getElementById('novaGuiaForm');
const pacienteBusca = document.getElementById('pacienteBusca');
const pacienteId = document.getElementById('paciente_id');
const novaGuiaModal = document.getElementById('novaGuiaModal');
let pacientesAutocomplete = [];
let guideModalDataLoaded = false;
const selectedGuideForm = {
    pacienteId: <?= (int) $guideFormValues['paciente_id'] ?>,
    planoId: <?= (int) $guideFormValues['plano_id'] ?>
};

async function carregarDadosModalGuia() {
    if (guideModalDataLoaded || !plano) return;

    const response = await fetch('guia_modal_dados.php');
    const data = await response.json();

    plano.innerHTML = '<option value="">Selecione</option>';
    (data.planos || []).forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.nome;
        option.dataset.valor = item.valor_sessao;
        if (Number(item.id) === selectedGuideForm.planoId) {
            option.selected = true;
        }
        plano.appendChild(option);
    });

    if (selectedGuideForm.pacienteId && pacienteBusca) {
        pacienteId.value = selectedGuideForm.pacienteId;
    }

    guideModalDataLoaded = true;
    calcularValorGuia();
}

function setupPatientAutocomplete(input, menu, onSelect) {
    if (!input || !menu) return;

    let timer = null;
    let controller = null;

    function closeMenu() {
        menu.classList.remove('is-open');
        menu.innerHTML = '';
    }

    function render(items) {
        menu.innerHTML = '';

        if (!items.length) {
            closeMenu();
            return;
        }

        items.forEach((patient) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'autocomplete-option';
            button.textContent = patient.nome;
            button.addEventListener('click', () => {
                input.value = patient.nome;
                closeMenu();
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

        if (term.length < 2) {
            closeMenu();
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
                if (error.name !== 'AbortError') closeMenu();
            }
        }, 180);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && event.target !== input) {
            closeMenu();
        }
    });
}

function sincronizarPacienteSelecionado() {
    if (!pacienteBusca || !pacienteId) return false;

    return pacienteId.value !== '';
}

function calcularValorGuia() {
    if (!plano || !total || !valorGuia) return;

    let selected = plano.options[plano.selectedIndex];
    let planoAtual = selected ? selected.text : '';
    let valorSessao = selected ? parseFloat(selected.getAttribute('data-valor')) || 0 : 0;
    let totalS = parseInt(total.value || 0);

    if (planoAtual === 'Particular') {
        valorGuia.classList.remove('readonly');
        valorGuia.readOnly = false;
        return;
    }

    valorGuia.classList.add('readonly');
    valorGuia.readOnly = true;

    let v = valorSessao * totalS;
    valorGuia.value = v > 0 ? "R$ " + v.toFixed(2).replace('.', ',') : "";
}

if (plano && total && valorGuia) {
    plano.addEventListener('change', calcularValorGuia);
    total.addEventListener('input', calcularValorGuia);
    calcularValorGuia();
}

const editTotal = document.getElementById('editTotal');
const editValorGuia = document.getElementById('editValorGuia');
const editGuideForm = document.getElementById('editarGuiaForm');
const editGuideCalc = {
    plano: <?= json_encode((string) ($editGuide['plano'] ?? '')) ?>,
    valorSessao: <?= json_encode((float) ($editGuide['valor_sessao'] ?? 0)) ?>
};

function calcularValorGuiaEdit() {
    if (!editTotal || !editValorGuia) return;

    if (editGuideCalc.plano === 'Particular') {
        editValorGuia.classList.remove('readonly');
        editValorGuia.readOnly = false;
        return;
    }

    editValorGuia.classList.add('readonly');
    editValorGuia.readOnly = true;

    const totalS = parseInt(editTotal.value || 0);
    const v = editGuideCalc.valorSessao * totalS;
    editValorGuia.value = v > 0 ? "R$ " + v.toFixed(2).replace('.', ',') : "";
}

if (editTotal && editValorGuia) {
    editTotal.addEventListener('input', calcularValorGuiaEdit);
    calcularValorGuiaEdit();
}

if (novaGuiaModal) {
    novaGuiaModal.addEventListener('show.bs.modal', carregarDadosModalGuia);
}

setupPatientAutocomplete(
    fPaciente,
    document.getElementById('fPacienteMenu'),
    () => {}
);

setupPatientAutocomplete(
    pacienteBusca,
    document.getElementById('pacienteBuscaMenu'),
    (patient) => {
        pacienteId.value = patient ? patient.id : '';
    }
);

if (novaGuiaForm) {
    novaGuiaForm.addEventListener('submit', function (event) {
        if (!sincronizarPacienteSelecionado()) {
            event.preventDefault();
            alert('Escolha um paciente da lista de autocomplete.');
            pacienteBusca.focus();
        }
    });
}

document.addEventListener('keydown', function (event) {
    if (event.altKey && event.key.toLowerCase() === 's') {
        if (novaGuiaModal && novaGuiaModal.classList.contains('show')) {
            event.preventDefault();
            novaGuiaForm.requestSubmit();
        }

        const editModal = document.getElementById('editarGuiaModal');
        if (editModal && editModal.classList.contains('show') && editGuideForm) {
            event.preventDefault();
            editGuideForm.requestSubmit();
        }
    }
});

<?php if ($shouldLoadGuides): ?>
filtrar();
<?php else: ?>
totais();
<?php endif; ?>
</script>

<?php if ($autoOpenGuideModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('novaGuiaModal');
    if (modalElement && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
<?php endif; ?>

<?php if ($autoOpenEditGuideModal && $editGuide): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('editarGuiaModal');
    if (modalElement && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
<?php endif; ?>

</body>
</html>
