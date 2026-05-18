<?php
include 'config/db.php';

$clinicId = app_active_clinic_id();
$currentUser = app_current_user() ?? [];
$isProfessional = app_is_professional_user();
$scopeProfessionalId = $isProfessional ? (app_current_professional_id() ?? 0) : null;

if (!$isProfessional && !app_has_any_role(['secretaria', 'administrativo', 'desenvolvedor'])) {
    app_flash('warning', 'Sem permissao para acessar este relatorio.');
    app_redirect(app_profile_home());
}

$filter = app_request_query('filter', 'mensal');
$startDate = app_request_query('start_date', date('Y-m-01'));
$endDate = app_request_query('end_date', date('Y-m-t'));

if ($filter === 'diario') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($filter === 'semanal') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter === 'mensal' && app_request_query('start_date') === null) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $startDate)) {
    $startDate = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $endDate)) {
    $endDate = date('Y-m-t');
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$professionals = app_stmt_all($conn, 'SELECT id, nome FROM profissionais WHERE clinica_id = ? ORDER BY nome', 'i', [$clinicId]);
$selectedProfessionalId = $scopeProfessionalId ?? app_query_int('profissional_id');

if (!$isProfessional && $selectedProfessionalId <= 0 && $professionals !== []) {
    $selectedProfessionalId = (int) $professionals[0]['id'];
}

$professional = null;

if ($selectedProfessionalId > 0) {
    $professional = app_stmt_one(
        $conn,
        'SELECT id, nome, salario_fixo, comissao_percentual, imposto_fixo, imposto_percentual
         FROM profissionais
         WHERE clinica_id = ? AND id = ?
         LIMIT 1',
        'ii',
        [$clinicId, $selectedProfessionalId]
    );
}

if (!$professional) {
    $professional = [
        'id' => 0,
        'nome' => 'Profissional nao vinculado',
        'salario_fixo' => 0,
        'comissao_percentual' => 0,
        'imposto_fixo' => 0,
        'imposto_percentual' => 0,
    ];
}

$guideRows = [];
$summary = [
    'total_guias' => 0,
    'guias_pagas' => 0,
    'guias_parciais' => 0,
    'guias_abertas' => 0,
    'guias_glosadas' => 0,
    'valor_previsto' => 0.0,
    'valor_glosado' => 0.0,
    'valor_faturavel' => 0.0,
    'valor_recebido' => 0.0,
    'valor_saldo' => 0.0,
];

if ((int) $professional['id'] > 0) {
    $guideRows = app_stmt_all(
        $conn,
        'SELECT g.id, g.codigo, g.data, g.total_sessoes, g.valor_guia, g.recebido, g.autorizada, g.status_operacional,
                pa.nome AS paciente_nome,
                pl.nome AS plano_nome,
                s.nome AS servico_nome,
                COALESCE(att.usadas, 0) AS usadas,
                COALESCE(att.glosas, 0) AS glosas
         FROM guias g
         LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
         LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
         LEFT JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
         LEFT JOIN (
            SELECT guia_id,
                   COUNT(*) AS usadas,
                   SUM(CASE WHEN status_atendimento = \'Glosado\' THEN 1 ELSE 0 END) AS glosas
            FROM atendimentos
            WHERE clinica_id = ?
            GROUP BY guia_id
         ) att ON att.guia_id = g.id
         WHERE g.clinica_id = ?
           AND g.profissional_id = ?
           AND g.data BETWEEN ? AND ?
         ORDER BY g.data DESC, pa.nome ASC, g.id DESC',
        'iiiss',
        [$clinicId, $clinicId, (int) $professional['id'], $startDate, $endDate]
    );
}

$commissionPercent = (float) ($professional['comissao_percentual'] ?? 0);

foreach ($guideRows as $index => $guide) {
    $totalSessions = max(0, (int) ($guide['total_sessoes'] ?? 0));
    $value = (float) ($guide['valor_guia'] ?? 0);
    $received = (float) ($guide['recebido'] ?? 0);
    $glosaCount = (int) ($guide['glosas'] ?? 0);
    $sessionValue = $value > 0 && $totalSessions > 0 ? $value / $totalSessions : 0.0;
    $glosaValue = min($value, $glosaCount * $sessionValue);
    $billable = max(0, $value - $glosaValue);
    $balance = max(0, $billable - $received);
    $status = 'aberta';

    if ($billable > 0 && $received >= $billable) {
        $status = 'paga';
    } elseif ($received > 0) {
        $status = 'parcial';
    }

    $guideRows[$index]['valor_glosado_calculado'] = $glosaValue;
    $guideRows[$index]['valor_faturavel_calculado'] = $billable;
    $guideRows[$index]['saldo_calculado'] = $balance;
    $guideRows[$index]['status_financeiro_calculado'] = $status;
    $guideRows[$index]['repasse_calculado'] = $received * ($commissionPercent / 100);

    $summary['total_guias']++;
    $summary['valor_previsto'] += $value;
    $summary['valor_glosado'] += $glosaValue;
    $summary['valor_faturavel'] += $billable;
    $summary['valor_recebido'] += $received;
    $summary['valor_saldo'] += $balance;

    if ($status === 'paga') {
        $summary['guias_pagas']++;
    } elseif ($status === 'parcial') {
        $summary['guias_parciais']++;
    } else {
        $summary['guias_abertas']++;
    }

    if ($glosaCount > 0) {
        $summary['guias_glosadas']++;
    }
}

$salary = (float) ($professional['salario_fixo'] ?? 0);
$commission = $summary['valor_recebido'] * ($commissionPercent / 100);
$grossPay = $salary + $commission;
$taxDiscount = (float) ($professional['imposto_fixo'] ?? 0) + ($grossPay * ((float) ($professional['imposto_percentual'] ?? 0) / 100));
$netPay = max(0, $grossPay - $taxDiscount);
$paidPercent = $summary['valor_faturavel'] > 0 ? min(100, ($summary['valor_recebido'] / $summary['valor_faturavel']) * 100) : 0;
$openCount = $summary['guias_abertas'] + $summary['guias_parciais'];

$reportLines = [
    'Profissional: ' . (string) $professional['nome'],
    'Periodo: ' . app_date_br($startDate) . ' a ' . app_date_br($endDate),
    'Base do repasse: valor recebido no periodo das guias filtradas',
];
$reportMetrics = [
    'Liquido a receber: ' . app_money_br($netPay),
    'Guias a pagar: ' . $openCount,
    'Guias pagas: ' . (int) $summary['guias_pagas'],
    'Parciais: ' . (int) $summary['guias_parciais'],
    'Recebido: ' . app_money_br($summary['valor_recebido']),
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Financeiro do Profissional</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
body {
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}
.professional-finance-shell {
    padding: 0.95rem 1rem 1.4rem;
}
.finance-topbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
    color: #fff;
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.12);
}
.finance-topbar h3 {
    margin: 0;
    font-size: 1.28rem;
}
.finance-kicker {
    margin: 0 0 0.12rem;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.78;
}
.finance-topbar p {
    margin: 0.16rem 0 0;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.76rem;
}
.finance-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
}
.finance-actions .btn {
    min-height: 36px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
}
.filter-card,
.finance-panel,
.metric-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}
.filter-card {
    margin-top: 0.75rem;
    padding: 0.85rem;
}
.filter-card .form-control,
.filter-card .form-select,
.filter-card .btn {
    min-height: 38px;
    border-radius: 12px;
    font-size: 0.82rem;
}
.metric-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.65rem;
    margin-top: 0.75rem;
}
.metric-card {
    padding: 0.82rem;
}
.metric-card span {
    display: block;
    color: #68828f;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.metric-card strong {
    display: block;
    margin-top: 0.28rem;
    color: #123744;
    font-size: 1.25rem;
    line-height: 1.1;
}
.metric-card small {
    display: block;
    margin-top: 0.22rem;
    color: #6b8591;
    font-size: 0.7rem;
}
.metric-card.is-success strong { color: #146c43; }
.metric-card.is-warning strong { color: #98630b; }
.metric-card.is-danger strong { color: #b42318; }
.finance-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 0.75fr);
    gap: 0.75rem;
    margin-top: 0.75rem;
}
.finance-panel {
    overflow: hidden;
}
.finance-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.78rem 0.9rem;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}
.finance-panel-head h5 {
    margin: 0;
    color: #143b49;
    font-size: 0.96rem;
}
.finance-panel-head span {
    color: #6b8591;
    font-size: 0.72rem;
}
.analysis-list {
    display: grid;
    gap: 0.55rem;
    padding: 0.78rem;
}
.analysis-item {
    padding: 0.68rem;
    border-left: 4px solid #1f7a8c;
    border-radius: 10px;
    background: #f6fafb;
}
.analysis-item.is-success { border-left-color: #1f9d6d; }
.analysis-item.is-warning { border-left-color: #f5a623; }
.analysis-item.is-danger { border-left-color: #dc3545; }
.analysis-item strong {
    display: block;
    margin-bottom: 0.16rem;
    color: #123744;
    font-size: 0.82rem;
}
.analysis-item p {
    margin: 0;
    color: #526973;
    font-size: 0.74rem;
    line-height: 1.36;
}
.table-wrap {
    overflow-x: auto;
}
.finance-table {
    margin: 0;
    font-size: 0.78rem;
}
.finance-table th {
    color: #68828f;
    font-size: 0.64rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.finance-status {
    display: inline-flex;
    min-width: 70px;
    justify-content: center;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    font-size: 0.66rem;
    font-weight: 800;
}
.finance-status.is-paid { background: #d9f2e3; color: #146c43; }
.finance-status.is-partial { background: #fff4cc; color: #98630b; }
.finance-status.is-open { background: #edf2f4; color: #526973; }
.print-summary-table {
    display: none;
}
<?= app_report_print_header_css() ?>
@media (max-width: 1100px) {
    .metric-grid,
    .finance-layout {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 720px) {
    .finance-topbar,
    .finance-layout {
        grid-template-columns: 1fr;
    }
    .finance-topbar {
        align-items: stretch;
        flex-direction: column;
    }
    .metric-grid {
        grid-template-columns: 1fr;
    }
}
@media print {
    body.app-print-page .screen-metrics,
    body.app-print-page .screen-analysis,
    body.app-print-page .finance-panel-head .app-print-hide {
        display: none !important;
    }
    body.app-print-page .print-summary-table {
        display: table !important;
        margin-bottom: 4mm !important;
    }
    body.app-print-page .finance-panel {
        border: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        overflow: visible !important;
    }
    body.app-print-page .finance-panel-head {
        padding: 0 0 2mm !important;
        border: 0 !important;
    }
    body.app-print-page .finance-panel-head h5 {
        color: #12333e !important;
        font-size: 11pt !important;
        font-weight: 800 !important;
    }
    body.app-print-page .finance-table {
        table-layout: fixed !important;
        font-size: 8.1pt !important;
    }
    body.app-print-page .finance-table th:nth-child(1),
    body.app-print-page .finance-table td:nth-child(1) { width: 9%; }
    body.app-print-page .finance-table th:nth-child(2),
    body.app-print-page .finance-table td:nth-child(2) { width: 16%; }
    body.app-print-page .finance-table th:nth-child(3),
    body.app-print-page .finance-table td:nth-child(3) { width: 12%; }
    body.app-print-page .finance-table th:nth-child(4),
    body.app-print-page .finance-table td:nth-child(4) { width: 9%; }
    body.app-print-page .finance-table th:nth-child(5),
    body.app-print-page .finance-table td:nth-child(5),
    body.app-print-page .finance-table th:nth-child(6),
    body.app-print-page .finance-table td:nth-child(6),
    body.app-print-page .finance-table th:nth-child(7),
    body.app-print-page .finance-table td:nth-child(7),
    body.app-print-page .finance-table th:nth-child(8),
    body.app-print-page .finance-table td:nth-child(8) { width: 9%; }
}
</style>
</head>
<body class="app-print-page">

<?php include 'partials/menu.php'; ?>

<div class="container-fluid professional-finance-shell">
    <section class="finance-topbar app-print-hide">
        <div>
            <p class="finance-kicker">Financeiro do profissional</p>
            <h3>Relatorio financeiro</h3>
            <p>Acompanhe guias pagas, parciais, pendentes e valor estimado de repasse.</p>
        </div>
        <div class="finance-actions">
            <button type="submit" form="professionalFinanceFilter" class="btn btn-light text-secondary">Filtrar</button>
            <button type="button" class="btn btn-outline-light" onclick="window.print()">Imprimir relatorio</button>
        </div>
    </section>

    <section class="filter-card app-print-hide">
        <form method="GET" id="professionalFinanceFilter" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted">Periodo</label>
                <select name="filter" class="form-select" id="financePeriodFilter">
                    <option value="mensal" <?= $filter === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                    <option value="semanal" <?= $filter === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                    <option value="diario" <?= $filter === 'diario' ? 'selected' : '' ?>>Diario</option>
                    <option value="personalizado" <?= $filter === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Data inicial</label>
                <input type="date" name="start_date" class="form-control" value="<?= app_h($startDate) ?>" <?= $filter !== 'personalizado' ? 'readonly' : '' ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Data final</label>
                <input type="date" name="end_date" class="form-control" value="<?= app_h($endDate) ?>" <?= $filter !== 'personalizado' ? 'readonly' : '' ?>>
            </div>
            <?php if (!$isProfessional): ?>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Profissional</label>
                    <select name="profissional_id" class="form-select">
                        <?php foreach ($professionals as $option): ?>
                            <option value="<?= (int) $option['id'] ?>" <?= (int) $professional['id'] === (int) $option['id'] ? 'selected' : '' ?>>
                                <?= app_h((string) $option['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Atualizar</button>
            </div>
        </form>
    </section>

    <section class="metric-grid screen-metrics">
        <div class="metric-card is-success">
            <span>Liquido a receber</span>
            <strong><?= app_money_br($netPay) ?></strong>
            <small>Comissao + fixo - descontos</small>
        </div>
        <div class="metric-card">
            <span>Produzido</span>
            <strong><?= app_money_br($summary['valor_previsto']) ?></strong>
            <small><?= (int) $summary['total_guias'] ?> guia(s) no periodo</small>
        </div>
        <div class="metric-card is-warning">
            <span>Guias a pagar</span>
            <strong><?= (int) $openCount ?></strong>
            <small><?= app_money_br($summary['valor_saldo']) ?> em saldo</small>
        </div>
        <div class="metric-card is-success">
            <span>Guias pagas</span>
            <strong><?= (int) $summary['guias_pagas'] ?></strong>
            <small><?= app_money_br($summary['valor_recebido']) ?> recebido</small>
        </div>
        <div class="metric-card is-danger">
            <span>Parciais / glosas</span>
            <strong><?= (int) $summary['guias_parciais'] ?> / <?= (int) $summary['guias_glosadas'] ?></strong>
            <small><?= app_money_br($summary['valor_glosado']) ?> glosado</small>
        </div>
    </section>

    <section class="finance-layout screen-analysis">
        <div class="finance-panel">
            <div class="finance-panel-head">
                <h5>Sintese do periodo</h5>
                <span><?= number_format($paidPercent, 1, ',', '.') ?>% do faturavel recebido</span>
            </div>
            <div class="table-wrap">
                <table class="table finance-table align-middle">
                    <tbody>
                        <tr><th>Valor previsto</th><td class="text-end"><?= app_money_br($summary['valor_previsto']) ?></td><th>Valor faturavel</th><td class="text-end"><?= app_money_br($summary['valor_faturavel']) ?></td></tr>
                        <tr><th>Recebido</th><td class="text-end"><?= app_money_br($summary['valor_recebido']) ?></td><th>Saldo</th><td class="text-end"><?= app_money_br($summary['valor_saldo']) ?></td></tr>
                        <tr><th>Salario fixo</th><td class="text-end"><?= app_money_br($salary) ?></td><th>Comissao <?= app_h(number_format($commissionPercent, 2, ',', '.')) ?>%</th><td class="text-end"><?= app_money_br($commission) ?></td></tr>
                        <tr><th>Descontos</th><td class="text-end text-danger"><?= app_money_br($taxDiscount) ?></td><th>Liquido</th><td class="text-end text-success fw-bold"><?= app_money_br($netPay) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="finance-panel">
            <div class="finance-panel-head">
                <h5>Analise do mes</h5>
                <span>pontos de acompanhamento</span>
            </div>
            <div class="analysis-list">
                <div class="analysis-item is-success">
                    <strong>Guias pagas</strong>
                    <p><?= (int) $summary['guias_pagas'] ?> guia(s) ja estao quitadas, somando <?= app_money_br($summary['valor_recebido']) ?> recebido no recorte.</p>
                </div>
                <div class="analysis-item is-warning">
                    <strong>Pendencias de recebimento</strong>
                    <p><?= (int) $openCount ?> guia(s) ainda possuem saldo em aberto. Saldo atual: <?= app_money_br($summary['valor_saldo']) ?>.</p>
                </div>
                <div class="analysis-item <?= $summary['guias_parciais'] > 0 ? 'is-warning' : '' ?>">
                    <strong>Pagamentos parciais</strong>
                    <p><?= (int) $summary['guias_parciais'] ?> guia(s) receberam parte do valor e precisam de acompanhamento ate a baixa total.</p>
                </div>
                <div class="analysis-item <?= $summary['guias_glosadas'] > 0 ? 'is-danger' : '' ?>">
                    <strong>Glosas</strong>
                    <p><?= (int) $summary['guias_glosadas'] ?> guia(s) tiveram glosa registrada, com impacto estimado de <?= app_money_br($summary['valor_glosado']) ?>.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="finance-panel mt-3 app-print-report-area" id="professionalFinanceReport">
        <?= app_report_print_header($conn, 'Financeiro do profissional', $reportLines, $reportMetrics) ?>
        <table class="table finance-table print-summary-table">
            <thead>
                <tr>
                    <th>Previsto</th>
                    <th>Faturavel</th>
                    <th>Recebido</th>
                    <th>Saldo</th>
                    <th>Comissao</th>
                    <th>Descontos</th>
                    <th>Liquido</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= app_money_br($summary['valor_previsto']) ?></td>
                    <td><?= app_money_br($summary['valor_faturavel']) ?></td>
                    <td><?= app_money_br($summary['valor_recebido']) ?></td>
                    <td><?= app_money_br($summary['valor_saldo']) ?></td>
                    <td><?= app_money_br($commission) ?></td>
                    <td><?= app_money_br($taxDiscount) ?></td>
                    <td><strong><?= app_money_br($netPay) ?></strong></td>
                </tr>
            </tbody>
        </table>
        <div class="finance-panel-head">
            <h5>Guias do periodo</h5>
            <span class="app-print-hide">detalhamento para conferencia</span>
        </div>
        <div class="table-wrap">
            <table class="table finance-table align-middle">
                <thead>
                    <tr>
                        <th>Guia</th>
                        <th>Paciente</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Recebido</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-end">Repasse</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($guideRows === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Nenhuma guia encontrada para o periodo.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($guideRows as $guide): ?>
                        <?php
                        $financialStatus = (string) $guide['status_financeiro_calculado'];
                        $statusLabel = $financialStatus === 'paga' ? 'Paga' : ($financialStatus === 'parcial' ? 'Parcial' : 'Aberta');
                        $statusClass = $financialStatus === 'paga' ? 'is-paid' : ($financialStatus === 'parcial' ? 'is-partial' : 'is-open');
                        ?>
                        <tr>
                            <td><?= app_h((string) ($guide['codigo'] ?: ('#' . $guide['id']))) ?></td>
                            <td><?= app_h((string) ($guide['paciente_nome'] ?: 'Paciente nao informado')) ?></td>
                            <td><?= app_h((string) ($guide['plano_nome'] ?: 'Sem plano')) ?></td>
                            <td><span class="finance-status <?= app_h($statusClass) ?>"><?= app_h($statusLabel) ?></span></td>
                            <td class="text-end"><?= app_money_br((float) $guide['valor_guia']) ?></td>
                            <td class="text-end"><?= app_money_br((float) $guide['recebido']) ?></td>
                            <td class="text-end"><?= app_money_br((float) $guide['saldo_calculado']) ?></td>
                            <td class="text-end"><?= app_money_br((float) $guide['repasse_calculado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Totais</td>
                        <td class="text-end"><?= app_money_br($summary['valor_previsto']) ?></td>
                        <td class="text-end"><?= app_money_br($summary['valor_recebido']) ?></td>
                        <td class="text-end"><?= app_money_br($summary['valor_saldo']) ?></td>
                        <td class="text-end"><?= app_money_br($commission) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<script>
const financePeriodFilter = document.getElementById('financePeriodFilter');
if (financePeriodFilter) {
    financePeriodFilter.addEventListener('change', () => {
        const custom = financePeriodFilter.value === 'personalizado';
        document.querySelectorAll('#professionalFinanceFilter input[type="date"]').forEach((input) => {
            input.readOnly = !custom;
        });

        if (!custom) {
            financePeriodFilter.form.submit();
        }
    });
}
</script>
</body>
</html>
