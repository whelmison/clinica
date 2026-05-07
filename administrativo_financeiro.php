<?php include 'config/db.php'; ?>
<?php
$today = date('Y-m-d');
$weekStart = app_week_start($today);
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$clinicId = app_active_clinic_id();

$payableExpectedSql = 'GREATEST(0, cp.valor + COALESCE(cp.juros, 0) + COALESCE(cp.multa, 0) - COALESCE(cp.desconto, 0))';
$receivableExpectedSql = 'GREATEST(0, cr.valor + COALESCE(cr.juros, 0) + COALESCE(cr.multa, 0) - COALESCE(cr.desconto, 0))';
$payableSettledSql = 'CASE
    WHEN cp.status IN ("pago", "parcial")
    THEN CASE
        WHEN COALESCE(cp.valor_pago, 0) > 0 THEN cp.valor_pago
        ELSE ' . $payableExpectedSql . '
    END
    ELSE 0
END';
$receivableSettledSql = 'CASE
    WHEN cr.status IN ("pago", "parcial")
    THEN CASE
        WHEN COALESCE(cr.valor_recebido, 0) > 0 THEN cr.valor_recebido
        ELSE ' . $receivableExpectedSql . '
    END
    ELSE 0
END';
$payableOpenSql = 'CASE
    WHEN cp.status IN ("pago", "cancelado") THEN 0
    WHEN cp.status = "parcial" THEN GREATEST(0, ' . $payableExpectedSql . ' - COALESCE(cp.valor_pago, 0))
    ELSE ' . $payableExpectedSql . '
END';
$receivableOpenSql = 'CASE
    WHEN cr.status IN ("pago", "cancelado") THEN 0
    WHEN cr.status = "parcial" THEN GREATEST(0, ' . $receivableExpectedSql . ' - COALESCE(cr.valor_recebido, 0))
    ELSE ' . $receivableExpectedSql . '
END';

$summary = [
    'receivable_open' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $receivableOpenSql . '), 0) AS total FROM contas_receber cr WHERE cr.clinica_id = ?', 'i', [$clinicId]) ?? [])['total'] ?? 0),
    'payable_open' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $payableOpenSql . '), 0) AS total FROM contas_pagar cp WHERE cp.clinica_id = ?', 'i', [$clinicId]) ?? [])['total'] ?? 0),
    'received_month' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $receivableSettledSql . '), 0) AS total FROM contas_receber cr WHERE cr.clinica_id = ? AND cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ?', 'iss', [$clinicId, $monthStart, $monthEnd]) ?? [])['total'] ?? 0),
    'paid_month' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $payableSettledSql . '), 0) AS total FROM contas_pagar cp WHERE cp.clinica_id = ? AND cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ?', 'iss', [$clinicId, $monthStart, $monthEnd]) ?? [])['total'] ?? 0),
    'received_week' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $receivableSettledSql . '), 0) AS total FROM contas_receber cr WHERE cr.clinica_id = ? AND cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ?', 'iss', [$clinicId, $weekStart, $weekEnd]) ?? [])['total'] ?? 0),
    'paid_week' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $payableSettledSql . '), 0) AS total FROM contas_pagar cp WHERE cp.clinica_id = ? AND cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ?', 'iss', [$clinicId, $weekStart, $weekEnd]) ?? [])['total'] ?? 0),
    'receivable_overdue' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $receivableOpenSql . '), 0) AS total FROM contas_receber cr WHERE cr.clinica_id = ? AND cr.vencimento < ? AND cr.status NOT IN ("pago", "cancelado")', 'is', [$clinicId, $today]) ?? [])['total'] ?? 0),
    'payable_overdue' => (float) ((app_stmt_one($conn, 'SELECT COALESCE(SUM(' . $payableOpenSql . '), 0) AS total FROM contas_pagar cp WHERE cp.clinica_id = ? AND cp.vencimento < ? AND cp.status NOT IN ("pago", "cancelado")', 'is', [$clinicId, $today]) ?? [])['total'] ?? 0),
];
$summary['net_month'] = $summary['received_month'] - $summary['paid_month'];
$summary['net_week'] = $summary['received_week'] - $summary['paid_week'];

$counts = [
    'plans' => (int) (($conn->query("SELECT COUNT(*) AS total FROM plano_contas WHERE clinica_id = {$clinicId} AND ativo = 1")->fetch_assoc()['total'] ?? 0)),
    'centers' => (int) (($conn->query("SELECT COUNT(*) AS total FROM centros_custo WHERE clinica_id = {$clinicId} AND ativo = 1")->fetch_assoc()['total'] ?? 0)),
    'accounts' => (int) (($conn->query("SELECT COUNT(*) AS total FROM contas_financeiras WHERE clinica_id = {$clinicId} AND ativo = 1")->fetch_assoc()['total'] ?? 0)),
];

$balanceRows = app_db_all(
    $conn,
    'SELECT cf.*,
            COALESCE(pay.total_pago, 0) AS total_pago,
            COALESCE(rec.total_recebido, 0) AS total_recebido
     FROM contas_financeiras cf
     LEFT JOIN (
         SELECT conta_financeira_id, SUM(' . $payableSettledSql . ') AS total_pago
         FROM contas_pagar cp
         WHERE cp.clinica_id = ' . $clinicId . '
         GROUP BY conta_financeira_id
     ) pay ON pay.conta_financeira_id = cf.id
     LEFT JOIN (
         SELECT conta_financeira_id, SUM(' . $receivableSettledSql . ') AS total_recebido
         FROM contas_receber cr
         WHERE cr.clinica_id = ' . $clinicId . '
         GROUP BY conta_financeira_id
     ) rec ON rec.conta_financeira_id = cf.id
     WHERE cf.clinica_id = ' . $clinicId . ' AND cf.ativo = 1
     ORDER BY cf.nome'
);

$overduePayables = app_stmt_all(
    $conn,
    'SELECT cp.id, cp.descricao, cp.vencimento, cp.status, pc.nome AS plano_nome,
            ' . $payableOpenSql . ' AS saldo_aberto
     FROM contas_pagar cp
     LEFT JOIN plano_contas pc ON pc.id = cp.plano_conta_id AND pc.clinica_id = cp.clinica_id
     WHERE cp.clinica_id = ? AND cp.vencimento < ? AND cp.status NOT IN ("pago", "cancelado")
     ORDER BY cp.vencimento ASC, cp.id DESC
     LIMIT 5',
    'is',
    [$clinicId, $today]
);

$overdueReceivables = app_stmt_all(
    $conn,
    'SELECT cr.id, cr.descricao, cr.vencimento, cr.status, pc.nome AS plano_nome,
            ' . $receivableOpenSql . ' AS saldo_aberto
     FROM contas_receber cr
     LEFT JOIN plano_contas pc ON pc.id = cr.plano_conta_id AND pc.clinica_id = cr.clinica_id
     WHERE cr.clinica_id = ? AND cr.vencimento < ? AND cr.status NOT IN ("pago", "cancelado")
     ORDER BY cr.vencimento ASC, cr.id DESC
     LIMIT 5',
    'is',
    [$clinicId, $today]
);

$upcomingRows = app_stmt_all(
    $conn,
    'SELECT "receber" AS tipo, cr.descricao, cr.vencimento,
            ' . $receivableOpenSql . ' AS valor
     FROM contas_receber cr
     WHERE cr.clinica_id = ? AND cr.vencimento BETWEEN ? AND ? AND cr.status NOT IN ("pago", "cancelado")
     UNION ALL
     SELECT "pagar" AS tipo, cp.descricao, cp.vencimento,
            ' . $payableOpenSql . ' AS valor
     FROM contas_pagar cp
     WHERE cp.clinica_id = ? AND cp.vencimento BETWEEN ? AND ? AND cp.status NOT IN ("pago", "cancelado")
     ORDER BY vencimento ASC
     LIMIT 8',
    'ississ',
    [$clinicId, $today, date('Y-m-d', strtotime($today . ' +7 days')), $clinicId, $today, date('Y-m-d', strtotime($today . ' +7 days'))]
);

$cashFlowRows = app_db_all(
    $conn,
    'SELECT base.referencia,
            SUM(base.receitas) AS receitas,
            SUM(base.despesas) AS despesas
     FROM (
         SELECT DATE_FORMAT(cr.recebimento, "%Y-%m") AS referencia, SUM(' . $receivableSettledSql . ') AS receitas, 0 AS despesas
         FROM contas_receber cr
         WHERE cr.clinica_id = ' . $clinicId . ' AND cr.status IN ("pago", "parcial") AND cr.recebimento IS NOT NULL
         GROUP BY DATE_FORMAT(cr.recebimento, "%Y-%m")
         UNION ALL
         SELECT DATE_FORMAT(cp.pagamento, "%Y-%m") AS referencia, 0 AS receitas, SUM(' . $payableSettledSql . ') AS despesas
         FROM contas_pagar cp
         WHERE cp.clinica_id = ' . $clinicId . ' AND cp.status IN ("pago", "parcial") AND cp.pagamento IS NOT NULL
         GROUP BY DATE_FORMAT(cp.pagamento, "%Y-%m")
     ) base
     GROUP BY base.referencia
     ORDER BY base.referencia DESC
     LIMIT 6'
);
$cashFlowRows = array_reverse($cashFlowRows);
$cashFlowLabels = [];
$cashFlowIncome = [];
$cashFlowExpense = [];
foreach ($cashFlowRows as $row) {
    $cashFlowLabels[] = app_month_label((string) $row['referencia']);
    $cashFlowIncome[] = round((float) $row['receitas'], 2);
    $cashFlowExpense[] = round((float) $row['despesas'], 2);
}

$methodsReceived = app_stmt_all(
    $conn,
    'SELECT COALESCE(cr.forma_pagamento, "nao_informado") AS metodo, SUM(' . $receivableSettledSql . ') AS total
     FROM contas_receber cr
     WHERE cr.clinica_id = ? AND cr.status IN ("pago", "parcial") AND cr.recebimento BETWEEN ? AND ?
     GROUP BY COALESCE(cr.forma_pagamento, "nao_informado")
     ORDER BY total DESC',
    'iss',
    [$clinicId, $monthStart, $monthEnd]
);
$methodsPaid = app_stmt_all(
    $conn,
    'SELECT COALESCE(cp.forma_pagamento, "nao_informado") AS metodo, SUM(' . $payableSettledSql . ') AS total
     FROM contas_pagar cp
     WHERE cp.clinica_id = ? AND cp.status IN ("pago", "parcial") AND cp.pagamento BETWEEN ? AND ?
     GROUP BY COALESCE(cp.forma_pagamento, "nao_informado")
     ORDER BY total DESC',
    'iss',
    [$clinicId, $monthStart, $monthEnd]
);

$paymentLabelsMap = app_financial_payment_methods();
$receivedMethodLabels = [];
$receivedMethodValues = [];
foreach ($methodsReceived as $row) {
    $receivedMethodLabels[] = $paymentLabelsMap[$row['metodo']] ?? 'Nao informado';
    $receivedMethodValues[] = round((float) $row['total'], 2);
}
$paidMethodLabels = [];
$paidMethodValues = [];
foreach ($methodsPaid as $row) {
    $paidMethodLabels[] = $paymentLabelsMap[$row['metodo']] ?? 'Nao informado';
    $paidMethodValues[] = round((float) $row['total'], 2);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Financeiro Administrativo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Financeiro da clinica</h3>
                <p>Painel completo com fluxo de caixa, contas, atrasos, formas de pagamento e visao operacional da clinica.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="nova_conta_receber.php" class="btn btn-success btn-sm rounded-pill px-3">+ Nova conta a receber</a>
                <a href="nova_conta_pagar.php" class="btn btn-light btn-sm rounded-pill px-3">+ Nova conta a pagar</a>
                <a href="relatorio_financeiro_fechamento.php" class="btn btn-outline-light btn-sm rounded-pill px-3">Relatorios mensal e semanal</a>
            </div>
        </div>
    </section>

    <div class="finance-grid mt-4">
        <div class="metric-card metric-success">
            <div class="small text-uppercase fw-semibold mb-1">Recebido no mes</div>
            <strong><?= app_money_br($summary['received_month']) ?></strong>
            <span class="text-muted small">Semana atual: <?= app_money_br($summary['received_week']) ?></span>
        </div>
        <div class="metric-card metric-danger">
            <div class="small text-uppercase fw-semibold mb-1">Pago no mes</div>
            <strong><?= app_money_br($summary['paid_month']) ?></strong>
            <span class="text-muted small">Semana atual: <?= app_money_br($summary['paid_week']) ?></span>
        </div>
        <div class="metric-card <?= $summary['net_month'] >= 0 ? 'metric-primary' : 'metric-warning' ?>">
            <div class="small text-uppercase fw-semibold mb-1">Resultado do mes</div>
            <strong><?= app_money_br($summary['net_month']) ?></strong>
            <span class="text-muted small">Resultado semanal: <?= app_money_br($summary['net_week']) ?></span>
        </div>
        <div class="metric-card metric-accent">
            <div class="small text-uppercase fw-semibold mb-1">Em aberto</div>
            <strong><?= app_money_br($summary['receivable_open'] - $summary['payable_open']) ?></strong>
            <span class="text-muted small">Receber <?= app_money_br($summary['receivable_open']) ?> | Pagar <?= app_money_br($summary['payable_open']) ?></span>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-8">
            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Fluxo de caixa realizado</h5>
                        <span class="selection-chip"><?= app_month_label(date('Y-m')) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-chart-shell">
                        <canvas id="cashFlowChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Visao rapida</h5>
                        <span class="text-muted small">Semanal e mensal</span>
                    </div>
                </div>
                <div class="card-body d-grid gap-3">
                    <div class="finance-stat">
                        <div class="eyebrow">Receitas atrasadas</div>
                        <strong><?= app_money_br($summary['receivable_overdue']) ?></strong>
                        <small>Titulos vencidos que ainda nao entraram no caixa.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Despesas atrasadas</div>
                        <strong><?= app_money_br($summary['payable_overdue']) ?></strong>
                        <small>Contas vencidas exigindo baixa ou renegociacao.</small>
                    </div>
                    <div class="finance-stat">
                        <div class="eyebrow">Estrutura financeira</div>
                        <strong><?= $counts['accounts'] ?></strong>
                        <small><?= $counts['centers'] ?> centro(s) de custo e <?= $counts['plans'] ?> plano(s) ativos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="soft-card card mt-4">
        <div class="card-header">
            <div class="panel-title">
                <h5>Operacao financeira da clinica</h5>
                <span class="text-muted small">Inspirado nos modulos de contas, centros de custo e fluxo de caixa usados em sistemas de clinica</span>
            </div>
        </div>
        <div class="card-body">
            <div class="finance-quick-grid">
                <div class="finance-quick-card">
                    <h6>Plano de contas</h6>
                    <p class="text-muted mb-3">Estruture receitas e despesas por categorias reais da clinica.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="finance-chip"><?= $counts['plans'] ?> ativos</span>
                        <a href="financeiro_plano_contas.php" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </div>
                <div class="finance-quick-card">
                    <h6>Centros de custo</h6>
                    <p class="text-muted mb-3">Separe atendimento, recepcao, administrativo, convenios e marketing.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="finance-chip"><?= $counts['centers'] ?> centros</span>
                        <a href="financeiro_centros_custo.php" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </div>
                <div class="finance-quick-card">
                    <h6>Contas financeiras</h6>
                    <p class="text-muted mb-3">Controle caixa, banco, PIX e outras contas de movimentacao da clinica.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="finance-chip"><?= $counts['accounts'] ?> contas</span>
                        <a href="financeiro_contas_financeiras.php" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </div>
                <div class="finance-quick-card">
                    <h6>Contas a receber</h6>
                    <p class="text-muted mb-3">Baixa financeira, formas de pagamento e controle por profissional ou fonte pagadora.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="finance-chip chip-success">Caixa de entrada</span>
                        <a href="financeiro_contas_receber.php" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </div>
                <div class="finance-quick-card">
                    <h6>Contas a pagar</h6>
                    <p class="text-muted mb-3">Despesas da clinica com baixa, vencimento, conta e metodo de pagamento.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="finance-chip chip-danger">Controle de saidas</span>
                        <a href="financeiro_contas_pagar.php" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </div>
                <div class="finance-quick-card">
                    <h6>Relatorios financeiros</h6>
                    <p class="text-muted mb-3">Visao semanal, mensal e customizada com graficos e demonstrativos.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="finance-chip chip-info">Gestao</span>
                        <a href="relatorio_financeiro_fechamento.php" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Recebimentos por metodo</h5>
                        <span class="text-muted small">Mes atual</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-chart-shell">
                        <canvas id="receivedMethodsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Pagamentos por metodo</h5>
                        <span class="text-muted small">Mes atual</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-chart-shell">
                        <canvas id="paidMethodsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Saldo por conta financeira</h5>
                        <span class="text-muted small">Caixa e bancos</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($balanceRows as $row): ?>
                            <?php $currentBalance = (float) $row['saldo_inicial'] + (float) $row['total_recebido'] - (float) $row['total_pago']; ?>
                            <div class="finance-balance-card">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold"><?= app_h($row['nome']) ?></div>
                                        <div class="small text-muted"><?= app_h(app_financial_account_types()[$row['tipo']] ?? ucfirst((string) $row['tipo'])) ?></div>
                                    </div>
                                    <span class="status-dot" style="background: <?= app_h($row['cor'] ?: '#1f7a8c') ?>"></span>
                                </div>
                                <strong class="<?= $currentBalance >= 0 ? 'amount-positive' : 'amount-negative' ?>"><?= app_money_br($currentBalance) ?></strong>
                                <div class="small text-muted">Inicial <?= app_money_br((float) $row['saldo_inicial']) ?> | Entradas <?= app_money_br((float) $row['total_recebido']) ?> | Saidas <?= app_money_br((float) $row['total_pago']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($balanceRows === []): ?>
                            <div class="text-muted small">Cadastre contas financeiras para acompanhar saldos da clinica.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Receitas vencidas</h5>
                        <span class="finance-chip chip-danger"><?= count($overdueReceivables) ?> item(ns)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($overdueReceivables as $item): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($item['descricao']) ?></div>
                                    <div class="meta">Vencimento <?= app_date_br($item['vencimento']) ?><?php if (!empty($item['plano_nome'])): ?> | <?= app_h($item['plano_nome']) ?><?php endif; ?></div>
                                </div>
                                <div class="amount amount-positive"><?= app_money_br((float) $item['saldo_aberto']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($overdueReceivables === []): ?>
                            <div class="text-muted small">Nenhuma receita atrasada no momento.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Despesas vencidas</h5>
                        <span class="finance-chip chip-danger"><?= count($overduePayables) ?> item(ns)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($overduePayables as $item): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($item['descricao']) ?></div>
                                    <div class="meta">Vencimento <?= app_date_br($item['vencimento']) ?><?php if (!empty($item['plano_nome'])): ?> | <?= app_h($item['plano_nome']) ?><?php endif; ?></div>
                                </div>
                                <div class="amount amount-negative"><?= app_money_br((float) $item['saldo_aberto']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($overduePayables === []): ?>
                            <div class="text-muted small">Nenhuma despesa atrasada no momento.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Proximos vencimentos</h5>
                        <span class="text-muted small">7 dias</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <?php foreach ($upcomingRows as $item): ?>
                            <div class="finance-ledger-item">
                                <div>
                                    <div class="title"><?= app_h($item['descricao']) ?></div>
                                    <div class="meta"><?= $item['tipo'] === 'receber' ? 'Receber' : 'Pagar' ?> em <?= app_date_br($item['vencimento']) ?></div>
                                </div>
                                <div class="amount <?= $item['tipo'] === 'receber' ? 'amount-positive' : 'amount-negative' ?>"><?= app_money_br((float) $item['valor']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($upcomingRows === []): ?>
                            <div class="text-muted small">Nenhum vencimento relevante nos proximos dias.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const cashFlowLabels = <?= json_encode($cashFlowLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const cashFlowIncome = <?= json_encode($cashFlowIncome, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const cashFlowExpense = <?= json_encode($cashFlowExpense, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const receivedMethodLabels = <?= json_encode($receivedMethodLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const receivedMethodValues = <?= json_encode($receivedMethodValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const paidMethodLabels = <?= json_encode($paidMethodLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const paidMethodValues = <?= json_encode($paidMethodValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const chartCommon = {
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                boxWidth: 12,
                color: '#385664',
            },
        },
    },
};

new Chart(document.getElementById('cashFlowChart'), {
    type: 'bar',
    data: {
        labels: cashFlowLabels,
        datasets: [
            {
                label: 'Recebido',
                data: cashFlowIncome,
                backgroundColor: '#1f9d6d',
                borderRadius: 12,
            },
            {
                label: 'Pago',
                data: cashFlowExpense,
                backgroundColor: '#d65858',
                borderRadius: 12,
            },
        ],
    },
    options: {
        ...chartCommon,
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

new Chart(document.getElementById('receivedMethodsChart'), {
    type: 'doughnut',
    data: {
        labels: receivedMethodLabels.length ? receivedMethodLabels : ['Sem recebimentos'],
        datasets: [{
            data: receivedMethodValues.length ? receivedMethodValues : [1],
            backgroundColor: ['#1f7a8c', '#1f9d6d', '#2e7fe2', '#f4a636', '#7a4ff6', '#d65858', '#5c7c89'],
            borderWidth: 0,
        }],
    },
    options: chartCommon,
});

new Chart(document.getElementById('paidMethodsChart'), {
    type: 'doughnut',
    data: {
        labels: paidMethodLabels.length ? paidMethodLabels : ['Sem pagamentos'],
        datasets: [{
            data: paidMethodValues.length ? paidMethodValues : [1],
            backgroundColor: ['#d65858', '#f4a636', '#1f7a8c', '#2e7fe2', '#7a4ff6', '#1f9d6d', '#5c7c89'],
            borderWidth: 0,
        }],
    },
    options: chartCommon,
});
</script>

</body>
</html>
