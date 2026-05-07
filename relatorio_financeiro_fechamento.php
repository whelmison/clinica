<?php include 'config/db.php'; ?>
<?php
if (!app_has_any_role(['secretaria', 'administrativo', 'desenvolvedor'])) {
    app_flash('warning', 'Sem permissao para acessar este relatorio.');
    app_redirect('index.php');
}

$today = date('Y-m-d');
$currentWeekStart = app_week_start($today);
$currentWeekEnd = date('Y-m-d', strtotime($currentWeekStart . ' +6 days'));
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');
$clinicId = app_active_clinic_id();

$filter = app_request_query('filter', 'mensal') ?? 'mensal';
$startDate = app_request_query('start_date', $currentMonthStart) ?? $currentMonthStart;
$endDate = app_request_query('end_date', $currentMonthEnd) ?? $currentMonthEnd;

if ($filter === 'diario') {
    $startDate = $today;
    $endDate = $today;
} elseif ($filter === 'semanal') {
    $startDate = $currentWeekStart;
    $endDate = $currentWeekEnd;
} elseif ($filter === 'mensal') {
    $startDate = $currentMonthStart;
    $endDate = $currentMonthEnd;
}

$paymentMethods = app_financial_payment_methods();
$costCenters = app_fetch_centros_custo($conn, true);
$financialAccounts = app_fetch_contas_financeiras($conn, true);
$professionals = app_fetch_profissionais($conn);

$filters = [
    'centro_custo_id' => (int) ($_GET['centro_custo_id'] ?? 0),
    'conta_financeira_id' => (int) ($_GET['conta_financeira_id'] ?? 0),
    'profissional_id' => (int) ($_GET['profissional_id'] ?? 0),
    'forma_pagamento' => trim((string) ($_GET['forma_pagamento'] ?? '')),
];

$receivableExpectedSql = app_financial_expected_sql('cr');
$receivableSettledSql = app_financial_settled_sql('cr', 'valor_recebido');
$receivableOpenSql = app_financial_open_sql('cr', 'valor_recebido');
$payableExpectedSql = app_financial_expected_sql('cp');
$payableSettledSql = app_financial_settled_sql('cp', 'valor_pago');
$payableOpenSql = app_financial_open_sql('cp', 'valor_pago');

$receivableFilterSql = ' AND cr.clinica_id = ? ';
$receivableFilterTypes = 'i';
$receivableFilterParams = [$clinicId];
$payableFilterSql = ' AND cp.clinica_id = ? ';
$payableFilterTypes = 'i';
$payableFilterParams = [$clinicId];

if ($filters['centro_custo_id'] > 0) {
    $receivableFilterSql .= ' AND cr.centro_custo_id = ? ';
    $receivableFilterTypes .= 'i';
    $receivableFilterParams[] = $filters['centro_custo_id'];

    $payableFilterSql .= ' AND cp.centro_custo_id = ? ';
    $payableFilterTypes .= 'i';
    $payableFilterParams[] = $filters['centro_custo_id'];
}

if ($filters['conta_financeira_id'] > 0) {
    $receivableFilterSql .= ' AND cr.conta_financeira_id = ? ';
    $receivableFilterTypes .= 'i';
    $receivableFilterParams[] = $filters['conta_financeira_id'];

    $payableFilterSql .= ' AND cp.conta_financeira_id = ? ';
    $payableFilterTypes .= 'i';
    $payableFilterParams[] = $filters['conta_financeira_id'];
}

if ($filters['profissional_id'] > 0) {
    $receivableFilterSql .= ' AND cr.profissional_id = ? ';
    $receivableFilterTypes .= 'i';
    $receivableFilterParams[] = $filters['profissional_id'];
}

if ($filters['forma_pagamento'] !== '' && array_key_exists($filters['forma_pagamento'], $paymentMethods)) {
    $receivableFilterSql .= ' AND cr.forma_pagamento = ? ';
    $receivableFilterTypes .= 's';
    $receivableFilterParams[] = $filters['forma_pagamento'];

    $payableFilterSql .= ' AND cp.forma_pagamento = ? ';
    $payableFilterTypes .= 's';
    $payableFilterParams[] = $filters['forma_pagamento'];
}

$metricValue = static function (string $sql, string $types = '', array $params = []) use ($conn): float {
    return (float) ((app_stmt_one($conn, $sql, $types, $params)['total'] ?? 0));
};

$sumRange = static function (string $tableAlias, string $dateColumn, string $settledSql, string $baseTable, string $extraSql, string $extraTypes, array $extraParams, string $from, string $to) use ($metricValue): float {
    $sql = 'SELECT COALESCE(SUM(' . $settledSql . '), 0) AS total FROM ' . $baseTable . ' ' . $tableAlias . ' WHERE ' . $tableAlias . '.status IN ("pago", "parcial") AND ' . $tableAlias . '.' . $dateColumn . ' BETWEEN ? AND ? ' . $extraSql;
    return $metricValue($sql, 'ss' . $extraTypes, array_merge([$from, $to], $extraParams));
};

$summary = [
    'received' => $sumRange('cr', 'recebimento', $receivableSettledSql, 'contas_receber', $receivableFilterSql, $receivableFilterTypes, $receivableFilterParams, $startDate, $endDate),
    'paid' => $sumRange('cp', 'pagamento', $payableSettledSql, 'contas_pagar', $payableFilterSql, $payableFilterTypes, $payableFilterParams, $startDate, $endDate),
    'receivable_open' => $metricValue(
        'SELECT COALESCE(SUM(' . $receivableOpenSql . '), 0) AS total FROM contas_receber cr WHERE cr.status NOT IN ("pago", "cancelado") AND cr.vencimento BETWEEN ? AND ? ' . $receivableFilterSql,
        'ss' . $receivableFilterTypes,
        array_merge([$startDate, $endDate], $receivableFilterParams)
    ),
    'payable_open' => $metricValue(
        'SELECT COALESCE(SUM(' . $payableOpenSql . '), 0) AS total FROM contas_pagar cp WHERE cp.status NOT IN ("pago", "cancelado") AND cp.vencimento BETWEEN ? AND ? ' . $payableFilterSql,
        'ss' . $payableFilterTypes,
        array_merge([$startDate, $endDate], $payableFilterParams)
    ),
    'receivable_overdue' => $metricValue(
        'SELECT COALESCE(SUM(' . $receivableOpenSql . '), 0) AS total FROM contas_receber cr WHERE cr.status NOT IN ("pago", "cancelado") AND cr.vencimento < ? ' . $receivableFilterSql,
        's' . $receivableFilterTypes,
        array_merge([$today], $receivableFilterParams)
    ),
    'payable_overdue' => $metricValue(
        'SELECT COALESCE(SUM(' . $payableOpenSql . '), 0) AS total FROM contas_pagar cp WHERE cp.status NOT IN ("pago", "cancelado") AND cp.vencimento < ? ' . $payableFilterSql,
        's' . $payableFilterTypes,
        array_merge([$today], $payableFilterParams)
    ),
];
$summary['net'] = $summary['received'] - $summary['paid'];

$snapshotWeek = [
    'received' => $sumRange('cr', 'recebimento', $receivableSettledSql, 'contas_receber', $receivableFilterSql, $receivableFilterTypes, $receivableFilterParams, $currentWeekStart, $currentWeekEnd),
    'paid' => $sumRange('cp', 'pagamento', $payableSettledSql, 'contas_pagar', $payableFilterSql, $payableFilterTypes, $payableFilterParams, $currentWeekStart, $currentWeekEnd),
];
$snapshotWeek['net'] = $snapshotWeek['received'] - $snapshotWeek['paid'];

$snapshotMonth = [
    'received' => $sumRange('cr', 'recebimento', $receivableSettledSql, 'contas_receber', $receivableFilterSql, $receivableFilterTypes, $receivableFilterParams, $currentMonthStart, $currentMonthEnd),
    'paid' => $sumRange('cp', 'pagamento', $payableSettledSql, 'contas_pagar', $payableFilterSql, $payableFilterTypes, $payableFilterParams, $currentMonthStart, $currentMonthEnd),
];
$snapshotMonth['net'] = $snapshotMonth['received'] - $snapshotMonth['paid'];

$dailyFlowRows = app_stmt_all(
    $conn,
    'SELECT base.data_referencia,
            SUM(base.receitas) AS receitas,
            SUM(base.despesas) AS despesas
     FROM (
        SELECT cr.recebimento AS data_referencia, SUM(' . $receivableSettledSql . ') AS receitas, 0 AS despesas
        FROM contas_receber cr
        WHERE cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ? ' . $receivableFilterSql . '
        GROUP BY cr.recebimento
        UNION ALL
        SELECT cp.pagamento AS data_referencia, 0 AS receitas, SUM(' . $payableSettledSql . ') AS despesas
        FROM contas_pagar cp
        WHERE cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ? ' . $payableFilterSql . '
        GROUP BY cp.pagamento
     ) base
     GROUP BY base.data_referencia
     ORDER BY base.data_referencia ASC',
    'ss' . $receivableFilterTypes . 'ss' . $payableFilterTypes,
    array_merge([$startDate, $endDate], $receivableFilterParams, [$startDate, $endDate], $payableFilterParams)
);

$dailyFlowMap = [];
foreach ($dailyFlowRows as $row) {
    $dailyFlowMap[$row['data_referencia']] = [
        'receitas' => (float) $row['receitas'],
        'despesas' => (float) $row['despesas'],
    ];
}

$flowLabels = [];
$flowIncome = [];
$flowExpense = [];
$cursor = new DateTime($startDate);
$limitDate = new DateTime($endDate);
while ($cursor <= $limitDate) {
    $key = $cursor->format('Y-m-d');
    $flowLabels[] = $cursor->format('d/m');
    $flowIncome[] = round((float) ($dailyFlowMap[$key]['receitas'] ?? 0), 2);
    $flowExpense[] = round((float) ($dailyFlowMap[$key]['despesas'] ?? 0), 2);
    $cursor->modify('+1 day');
}

$topRevenuePlans = app_stmt_all(
    $conn,
    'SELECT COALESCE(pc.nome, "Sem plano") AS nome, SUM(' . $receivableSettledSql . ') AS total
     FROM contas_receber cr
     LEFT JOIN plano_contas pc ON pc.id = cr.plano_conta_id
     WHERE cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ? ' . $receivableFilterSql . '
     GROUP BY COALESCE(pc.nome, "Sem plano")
     ORDER BY total DESC
     LIMIT 6',
    'ss' . $receivableFilterTypes,
    array_merge([$startDate, $endDate], $receivableFilterParams)
);

$topExpensePlans = app_stmt_all(
    $conn,
    'SELECT COALESCE(pc.nome, "Sem plano") AS nome, SUM(' . $payableSettledSql . ') AS total
     FROM contas_pagar cp
     LEFT JOIN plano_contas pc ON pc.id = cp.plano_conta_id
     WHERE cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ? ' . $payableFilterSql . '
     GROUP BY COALESCE(pc.nome, "Sem plano")
     ORDER BY total DESC
     LIMIT 6',
    'ss' . $payableFilterTypes,
    array_merge([$startDate, $endDate], $payableFilterParams)
);

$receivedByMethod = app_stmt_all(
    $conn,
    'SELECT COALESCE(cr.forma_pagamento, "nao_informado") AS metodo, SUM(' . $receivableSettledSql . ') AS total
     FROM contas_receber cr
     WHERE cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ? ' . $receivableFilterSql . '
     GROUP BY COALESCE(cr.forma_pagamento, "nao_informado")
     ORDER BY total DESC',
    'ss' . $receivableFilterTypes,
    array_merge([$startDate, $endDate], $receivableFilterParams)
);

$paidByMethod = app_stmt_all(
    $conn,
    'SELECT COALESCE(cp.forma_pagamento, "nao_informado") AS metodo, SUM(' . $payableSettledSql . ') AS total
     FROM contas_pagar cp
     WHERE cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ? ' . $payableFilterSql . '
     GROUP BY COALESCE(cp.forma_pagamento, "nao_informado")
     ORDER BY total DESC',
    'ss' . $payableFilterTypes,
    array_merge([$startDate, $endDate], $payableFilterParams)
);

$receivedMethodLabels = [];
$receivedMethodValues = [];
foreach ($receivedByMethod as $row) {
    $receivedMethodLabels[] = app_financial_payment_method_label($row['metodo']);
    $receivedMethodValues[] = round((float) $row['total'], 2);
}

$paidMethodLabels = [];
$paidMethodValues = [];
foreach ($paidByMethod as $row) {
    $paidMethodLabels[] = app_financial_payment_method_label($row['metodo']);
    $paidMethodValues[] = round((float) $row['total'], 2);
}

$realizedReceipts = app_stmt_all(
    $conn,
    'SELECT cr.*, pc.nome AS plano_nome, cc.nome AS centro_nome, cf.nome AS conta_nome, pr.nome AS profissional_nome, pa.nome AS paciente_nome
     FROM contas_receber cr
     LEFT JOIN plano_contas pc ON pc.id = cr.plano_conta_id
     LEFT JOIN centros_custo cc ON cc.id = cr.centro_custo_id
     LEFT JOIN contas_financeiras cf ON cf.id = cr.conta_financeira_id
     LEFT JOIN profissionais pr ON pr.id = cr.profissional_id
     LEFT JOIN pacientes pa ON pa.id = cr.paciente_id
     WHERE cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ? ' . $receivableFilterSql . '
     ORDER BY cr.recebimento DESC, cr.id DESC
     LIMIT 30',
    'ss' . $receivableFilterTypes,
    array_merge([$startDate, $endDate], $receivableFilterParams)
);

$realizedPayments = app_stmt_all(
    $conn,
    'SELECT cp.*, pc.nome AS plano_nome, cc.nome AS centro_nome, cf.nome AS conta_nome
     FROM contas_pagar cp
     LEFT JOIN plano_contas pc ON pc.id = cp.plano_conta_id
     LEFT JOIN centros_custo cc ON cc.id = cp.centro_custo_id
     LEFT JOIN contas_financeiras cf ON cf.id = cp.conta_financeira_id
     WHERE cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ? ' . $payableFilterSql . '
     ORDER BY cp.pagamento DESC, cp.id DESC
     LIMIT 30',
    'ss' . $payableFilterTypes,
    array_merge([$startDate, $endDate], $payableFilterParams)
);

$openReceivables = app_stmt_all(
    $conn,
    'SELECT cr.descricao, cr.vencimento, ' . $receivableOpenSql . ' AS saldo_aberto, pc.nome AS plano_nome
     FROM contas_receber cr
     LEFT JOIN plano_contas pc ON pc.id = cr.plano_conta_id
     WHERE cr.status NOT IN ("pago", "cancelado") AND cr.vencimento BETWEEN ? AND ? ' . $receivableFilterSql . '
     ORDER BY cr.vencimento ASC, cr.id DESC
     LIMIT 12',
    'ss' . $receivableFilterTypes,
    array_merge([$startDate, $endDate], $receivableFilterParams)
);

$openPayables = app_stmt_all(
    $conn,
    'SELECT cp.descricao, cp.vencimento, ' . $payableOpenSql . ' AS saldo_aberto, pc.nome AS plano_nome
     FROM contas_pagar cp
     LEFT JOIN plano_contas pc ON pc.id = cp.plano_conta_id
     WHERE cp.status NOT IN ("pago", "cancelado") AND cp.vencimento BETWEEN ? AND ? ' . $payableFilterSql . '
     ORDER BY cp.vencimento ASC, cp.id DESC
     LIMIT 12',
    'ss' . $payableFilterTypes,
    array_merge([$startDate, $endDate], $payableFilterParams)
);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Relatorio de Fechamento Financeiro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .page-shell { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    .no-print { display: none !important; }
    .soft-card { border: none !important; box-shadow: none !important; }
}
</style>
</head>
<body>

<div class="no-print">
<?php include 'partials/menu.php'; ?>
</div>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Relatorio financeiro da clinica</h3>
                <p>Controle semanal e mensal com fluxo realizado, contas em aberto, atrasos e distribuicao por categoria e forma de pagamento.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <a href="administrativo_financeiro.php" class="btn btn-light btn-sm rounded-pill px-3">Financeiro</a>
                <button class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="window.print()">Imprimir PDF</button>
            </div>
        </div>
    </section>

    <div class="soft-card card mt-4 no-print">
        <div class="card-body">
            <form method="GET" class="toolbar-grid">
                <div>
                    <label class="form-label small text-muted">Periodo rapido</label>
                    <select name="filter" class="form-select">
                        <option value="mensal" <?= $filter === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                        <option value="semanal" <?= $filter === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="diario" <?= $filter === 'diario' ? 'selected' : '' ?>>Diario</option>
                        <option value="personalizado" <?= $filter === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Data inicial</label>
                    <input type="date" name="start_date" class="form-control" value="<?= app_h($startDate) ?>">
                </div>
                <div>
                    <label class="form-label small text-muted">Data final</label>
                    <input type="date" name="end_date" class="form-control" value="<?= app_h($endDate) ?>">
                </div>
                <div>
                    <label class="form-label small text-muted">Centro de custo</label>
                    <select name="centro_custo_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($costCenters as $center): ?>
                            <option value="<?= (int) $center['id'] ?>" <?= $filters['centro_custo_id'] === (int) $center['id'] ? 'selected' : '' ?>><?= app_h($center['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Conta financeira</label>
                    <select name="conta_financeira_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($financialAccounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>" <?= $filters['conta_financeira_id'] === (int) $account['id'] ? 'selected' : '' ?>><?= app_h($account['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Profissional</label>
                    <select name="profissional_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($professionals as $prof): ?>
                            <option value="<?= (int) $prof['id'] ?>" <?= $filters['profissional_id'] === (int) $prof['id'] ? 'selected' : '' ?>><?= app_h($prof['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Forma de pagamento</label>
                    <select name="forma_pagamento" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($paymentMethods as $value => $label): ?>
                            <option value="<?= app_h($value) ?>" <?= $filters['forma_pagamento'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Atualizar relatorio</button>
                </div>
            </form>
        </div>
    </div>

    <div class="finance-grid mt-4">
        <div class="metric-card metric-success">
            <div class="small text-uppercase fw-semibold mb-1">Recebido no periodo</div>
            <strong><?= app_money_br($summary['received']) ?></strong>
            <span class="text-muted small"><?= app_financial_period_label($startDate, $endDate) ?></span>
        </div>
        <div class="metric-card metric-danger">
            <div class="small text-uppercase fw-semibold mb-1">Pago no periodo</div>
            <strong><?= app_money_br($summary['paid']) ?></strong>
            <span class="text-muted small"><?= app_financial_period_label($startDate, $endDate) ?></span>
        </div>
        <div class="metric-card <?= $summary['net'] >= 0 ? 'metric-primary' : 'metric-warning' ?>">
            <div class="small text-uppercase fw-semibold mb-1">Resultado do periodo</div>
            <strong><?= app_money_br($summary['net']) ?></strong>
            <span class="text-muted small">Recebido menos pago.</span>
        </div>
        <div class="metric-card metric-accent">
            <div class="small text-uppercase fw-semibold mb-1">Aberto no periodo</div>
            <strong><?= app_money_br($summary['receivable_open'] - $summary['payable_open']) ?></strong>
            <span class="text-muted small">Receber <?= app_money_br($summary['receivable_open']) ?> | Pagar <?= app_money_br($summary['payable_open']) ?></span>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Resumo semanal atual</h5>
                        <span class="selection-chip"><?= app_financial_period_label($currentWeekStart, $currentWeekEnd) ?></span>
                    </div>
                </div>
                <div class="card-body d-grid gap-3">
                    <div class="finance-stat">
                        <div class="eyebrow">Recebido</div>
                        <strong><?= app_money_br($snapshotWeek['received']) ?></strong>
                        <small>Entradas realizadas nesta semana.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Pago</div>
                        <strong><?= app_money_br($snapshotWeek['paid']) ?></strong>
                        <small>Saidas realizadas nesta semana.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Resultado semanal</div>
                        <strong><?= app_money_br($snapshotWeek['net']) ?></strong>
                        <small>Saldo operacional da semana corrente.</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Resumo mensal atual</h5>
                        <span class="selection-chip"><?= app_month_label(date('Y-m')) ?></span>
                    </div>
                </div>
                <div class="card-body d-grid gap-3">
                    <div class="finance-stat">
                        <div class="eyebrow">Recebido</div>
                        <strong><?= app_money_br($snapshotMonth['received']) ?></strong>
                        <small>Entradas realizadas no mes corrente.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Pago</div>
                        <strong><?= app_money_br($snapshotMonth['paid']) ?></strong>
                        <small>Saidas realizadas no mes corrente.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Resultado mensal</div>
                        <strong><?= app_money_br($snapshotMonth['net']) ?></strong>
                        <small>Saldo operacional do mes corrente.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-8">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Fluxo de caixa realizado</h5>
                        <span class="text-muted small"><?= app_financial_period_label($startDate, $endDate) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-chart-shell">
                        <canvas id="dailyFlowChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Alertas operacionais</h5>
                    </div>
                </div>
                <div class="card-body d-grid gap-3">
                    <div class="finance-stat">
                        <div class="eyebrow">Receitas atrasadas</div>
                        <strong><?= app_money_br($summary['receivable_overdue']) ?></strong>
                        <small>Titulos vencidos e ainda nao recebidos.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Despesas atrasadas</div>
                        <strong><?= app_money_br($summary['payable_overdue']) ?></strong>
                        <small>Contas vencidas exigindo baixa ou negociacao.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Saldo aberto do periodo</div>
                        <strong><?= app_money_br($summary['receivable_open'] + $summary['payable_open']) ?></strong>
                        <small>Volume financeiro ainda pendente dentro do intervalo filtrado.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Recebimentos por metodo</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-chart-shell">
                        <canvas id="receivedMethodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Pagamentos por metodo</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-chart-shell">
                        <canvas id="paidMethodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Top categorias</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($topRevenuePlans as $row): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($row['nome']) ?></div>
                                    <div class="meta">Receita realizada</div>
                                </div>
                                <div class="amount amount-positive"><?= app_money_br((float) $row['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($topExpensePlans as $row): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($row['nome']) ?></div>
                                    <div class="meta">Despesa realizada</div>
                                </div>
                                <div class="amount amount-negative"><?= app_money_br((float) $row['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($topRevenuePlans === [] && $topExpensePlans === []): ?>
                            <div class="text-muted small">Sem movimentacoes realizadas no periodo filtrado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Contas a receber realizadas</h5>
                        <span class="text-muted small"><?= count($realizedReceipts) ?> item(ns)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descricao</th>
                                <th class="text-end">Valor</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($realizedReceipts as $item): ?>
                                <?php $amounts = app_financial_row_amounts($item, 'valor_recebido'); ?>
                                <tr>
                                    <td><?= app_date_br($item['recebimento']) ?></td>
                                    <td>
                                        <?= app_h($item['descricao']) ?>
                                        <div class="small text-muted"><?= app_h($item['paciente_nome'] ?: $item['fonte_pagadora'] ?: 'Sem fonte pagadora') ?><?php if (!empty($item['profissional_nome'])): ?> | <?= app_h($item['profissional_nome']) ?><?php endif; ?></div>
                                    </td>
                                    <td class="text-end amount-positive"><?= app_money_br($amounts['settled']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($realizedReceipts === []): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhum recebimento realizado no periodo.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Contas a pagar realizadas</h5>
                        <span class="text-muted small"><?= count($realizedPayments) ?> item(ns)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descricao</th>
                                <th class="text-end">Valor</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($realizedPayments as $item): ?>
                                <?php $amounts = app_financial_row_amounts($item, 'valor_pago'); ?>
                                <tr>
                                    <td><?= app_date_br($item['pagamento']) ?></td>
                                    <td>
                                        <?= app_h($item['descricao']) ?>
                                        <div class="small text-muted"><?= app_h($item['favorecido'] ?: 'Sem favorecido') ?><?php if (!empty($item['plano_nome'])): ?> | <?= app_h($item['plano_nome']) ?><?php endif; ?></div>
                                    </td>
                                    <td class="text-end amount-negative"><?= app_money_br($amounts['settled']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($realizedPayments === []): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhum pagamento realizado no periodo.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Receitas em aberto no periodo</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($openReceivables as $item): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($item['descricao']) ?></div>
                                    <div class="meta">Vencimento <?= app_date_br($item['vencimento']) ?><?php if (!empty($item['plano_nome'])): ?> | <?= app_h($item['plano_nome']) ?><?php endif; ?></div>
                                </div>
                                <div class="amount amount-positive"><?= app_money_br((float) $item['saldo_aberto']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($openReceivables === []): ?>
                            <div class="text-muted small">Nenhuma receita em aberto no intervalo filtrado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Despesas em aberto no periodo</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($openPayables as $item): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($item['descricao']) ?></div>
                                    <div class="meta">Vencimento <?= app_date_br($item['vencimento']) ?><?php if (!empty($item['plano_nome'])): ?> | <?= app_h($item['plano_nome']) ?><?php endif; ?></div>
                                </div>
                                <div class="amount amount-negative"><?= app_money_br((float) $item['saldo_aberto']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($openPayables === []): ?>
                            <div class="text-muted small">Nenhuma despesa em aberto no intervalo filtrado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const flowLabels = <?= json_encode($flowLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const flowIncome = <?= json_encode($flowIncome, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const flowExpense = <?= json_encode($flowExpense, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const receivedMethodLabels = <?= json_encode($receivedMethodLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const receivedMethodValues = <?= json_encode($receivedMethodValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const paidMethodLabels = <?= json_encode($paidMethodLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const paidMethodValues = <?= json_encode($paidMethodValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const chartOptions = {
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: '#385664',
                boxWidth: 12,
            },
        },
    },
};

new Chart(document.getElementById('dailyFlowChart'), {
    type: 'bar',
    data: {
        labels: flowLabels,
        datasets: [
            {
                label: 'Recebido',
                data: flowIncome,
                backgroundColor: '#1f9d6d',
                borderRadius: 10,
            },
            {
                label: 'Pago',
                data: flowExpense,
                backgroundColor: '#d65858',
                borderRadius: 10,
            },
        ],
    },
    options: {
        ...chartOptions,
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#607985' },
            },
            y: {
                beginAtZero: true,
                ticks: { color: '#607985' },
            },
        },
    },
});

new Chart(document.getElementById('receivedMethodChart'), {
    type: 'doughnut',
    data: {
        labels: receivedMethodLabels.length ? receivedMethodLabels : ['Sem recebimentos'],
        datasets: [{
            data: receivedMethodValues.length ? receivedMethodValues : [1],
            backgroundColor: ['#1f7a8c', '#1f9d6d', '#2e7fe2', '#f4a636', '#7a4ff6', '#d65858', '#5c7c89'],
            borderWidth: 0,
        }],
    },
    options: chartOptions,
});

new Chart(document.getElementById('paidMethodChart'), {
    type: 'doughnut',
    data: {
        labels: paidMethodLabels.length ? paidMethodLabels : ['Sem pagamentos'],
        datasets: [{
            data: paidMethodValues.length ? paidMethodValues : [1],
            backgroundColor: ['#d65858', '#f4a636', '#1f7a8c', '#2e7fe2', '#7a4ff6', '#1f9d6d', '#5c7c89'],
            borderWidth: 0,
        }],
    },
    options: chartOptions,
});
</script>

</body>
</html>
