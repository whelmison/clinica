<?php include 'config/db.php'; ?>
<?php
$accountStatuses = app_account_statuses();
$paymentMethods = app_financial_payment_methods();
$clinicId = app_active_clinic_id();
$professionals = app_fetch_profissionais($conn);
$costCenters = app_fetch_centros_custo($conn, true);
$financialAccounts = app_fetch_contas_financeiras($conn, true);
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$weekEnd = date('Y-m-d', strtotime($today . ' +7 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $ok = app_stmt_execute($conn, 'DELETE FROM contas_receber WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $id]);
        app_flash($ok ? 'success' : 'danger', $ok ? 'Conta excluida.' : 'Erro ao excluir a conta.');
        app_redirect('financeiro_contas_receber.php');
    }

    if ($action === 'settle') {
        $id = (int) ($_POST['id'] ?? 0);
        $settleItem = app_stmt_one($conn, 'SELECT * FROM contas_receber WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $id]);
        $receiveDate = trim((string) ($_POST['recebimento'] ?? ''));
        $paymentMethod = trim((string) ($_POST['forma_pagamento'] ?? ''));
        $financialAccountId = (int) ($_POST['conta_financeira_id'] ?? 0);
        $receivedTotal = app_parse_money((string) ($_POST['valor_recebido_total'] ?? '0'));

        if (!$settleItem) {
            app_flash('danger', 'Conta a receber nao encontrada.');
        } elseif ($paymentMethod === '' || !array_key_exists($paymentMethod, $paymentMethods)) {
            app_flash('danger', 'Informe uma forma de recebimento valida para a baixa.');
        } else {
            $amounts = app_financial_row_amounts($settleItem, 'valor_recebido');
            $expected = $amounts['expected'];
            $receivedTotal = $receivedTotal > 0 ? min($receivedTotal, $expected) : $expected;
            $status = $receivedTotal < $expected ? 'parcial' : 'pago';
            $ok = app_stmt_execute(
                $conn,
                'UPDATE contas_receber SET status = ?, recebimento = ?, valor_recebido = ?, forma_pagamento = ?, conta_financeira_id = ? WHERE clinica_id = ? AND id = ?',
                'ssdsiii',
                [
                    $status,
                    $receiveDate !== '' ? $receiveDate : date('Y-m-d'),
                    $receivedTotal,
                    $paymentMethod,
                    $financialAccountId > 0 ? $financialAccountId : ($settleItem['conta_financeira_id'] ?? null),
                    $clinicId,
                    $id,
                ]
            );

            app_flash($ok ? 'success' : 'danger', $ok ? 'Baixa financeira registrada.' : 'Erro ao registrar a baixa.');
        }

        app_redirect('financeiro_contas_receber.php');
    }
}

$filters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'busca' => trim((string) ($_GET['busca'] ?? '')),
    'mes' => trim((string) ($_GET['mes'] ?? '')),
    'profissional_id' => (int) ($_GET['profissional_id'] ?? 0),
    'centro_custo_id' => (int) ($_GET['centro_custo_id'] ?? 0),
    'forma_pagamento' => trim((string) ($_GET['forma_pagamento'] ?? '')),
];

$where = ' WHERE cr.clinica_id = ? ';
$params = [$clinicId];
$types = 'i';

if ($filters['status'] !== '' && array_key_exists($filters['status'], $accountStatuses)) {
    $where .= ' AND cr.status = ? ';
    $types .= 's';
    $params[] = $filters['status'];
}
if ($filters['busca'] !== '') {
    $where .= ' AND (cr.descricao LIKE ? OR cr.numero_documento LIKE ? OR cr.fonte_pagadora LIKE ? OR pc.nome LIKE ? OR pa.nome LIKE ?) ';
    $types .= 'sssss';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
}
if ($filters['mes'] !== '') {
    $where .= ' AND (cr.vencimento LIKE ? OR cr.competencia LIKE ?) ';
    $types .= 'ss';
    $params[] = $filters['mes'] . '%';
    $params[] = $filters['mes'] . '%';
}
if ($filters['profissional_id'] > 0) {
    $where .= ' AND cr.profissional_id = ? ';
    $types .= 'i';
    $params[] = $filters['profissional_id'];
}
if ($filters['centro_custo_id'] > 0) {
    $where .= ' AND cr.centro_custo_id = ? ';
    $types .= 'i';
    $params[] = $filters['centro_custo_id'];
}
if ($filters['forma_pagamento'] !== '' && array_key_exists($filters['forma_pagamento'], $paymentMethods)) {
    $where .= ' AND cr.forma_pagamento = ? ';
    $types .= 's';
    $params[] = $filters['forma_pagamento'];
}

$expectedSql = app_financial_expected_sql('cr');
$settledSql = app_financial_settled_sql('cr', 'valor_recebido');
$openSql = app_financial_open_sql('cr', 'valor_recebido');

$items = app_stmt_all(
    $conn,
    'SELECT cr.*,
            pc.nome AS plano_nome,
            pc.codigo AS plano_codigo,
            cc.nome AS centro_nome,
            cf.nome AS conta_nome,
            pr.nome AS profissional_nome,
            pa.nome AS paciente_nome,
            ' . $expectedSql . ' AS valor_previsto,
            ' . $settledSql . ' AS valor_liquidado,
            ' . $openSql . ' AS saldo_aberto
     FROM contas_receber cr
     LEFT JOIN plano_contas pc ON pc.id = cr.plano_conta_id AND pc.clinica_id = cr.clinica_id
     LEFT JOIN centros_custo cc ON cc.id = cr.centro_custo_id AND cc.clinica_id = cr.clinica_id
     LEFT JOIN contas_financeiras cf ON cf.id = cr.conta_financeira_id AND cf.clinica_id = cr.clinica_id
     LEFT JOIN profissionais pr ON pr.id = cr.profissional_id AND pr.clinica_id = cr.clinica_id
     LEFT JOIN pacientes pa ON pa.id = cr.paciente_id AND pa.clinica_id = cr.clinica_id
     ' . $where . '
     ORDER BY cr.vencimento ASC, cr.id DESC
     LIMIT 150',
    $types,
    $params
);

$summary = [
    'open' => 0.0,
    'settled_month' => 0.0,
    'overdue' => 0.0,
    'due_week' => 0.0,
];

foreach ($items as $item) {
    $summary['open'] += (float) ($item['saldo_aberto'] ?? 0);

    if (!empty($item['recebimento']) && $item['recebimento'] >= $monthStart && $item['recebimento'] <= $monthEnd) {
        $summary['settled_month'] += (float) ($item['valor_liquidado'] ?? 0);
    }

    if (($item['status'] ?? '') !== 'pago' && ($item['status'] ?? '') !== 'cancelado' && !empty($item['vencimento']) && $item['vencimento'] < $today) {
        $summary['overdue'] += (float) ($item['saldo_aberto'] ?? 0);
    }

    if (($item['status'] ?? '') !== 'pago' && ($item['status'] ?? '') !== 'cancelado' && !empty($item['vencimento']) && $item['vencimento'] >= $today && $item['vencimento'] <= $weekEnd) {
        $summary['due_week'] += (float) ($item['saldo_aberto'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Contas a Receber</title>
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
                <h3 class="mb-2">Contas a receber</h3>
                <p>Controle cobrancas de pacientes, convenios e guias com baixa financeira completa e rastreabilidade.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button class="btn btn-outline-secondary rounded-pill px-3" onclick="window.print()">Imprimir PDF</button>
                <a href="administrativo_financeiro.php" class="btn btn-light btn-sm rounded-pill px-3">Financeiro</a>
                <a href="nova_conta_receber.php" class="btn btn-success btn-sm rounded-pill px-3">Nova conta a receber</a>
            </div>
        </div>
    </section>

    <div class="finance-grid mt-4">
        <div class="metric-card metric-info">
            <div class="small text-uppercase fw-semibold mb-1">Em aberto</div>
            <strong><?= app_money_br($summary['open']) ?></strong>
            <span class="text-muted small">Saldo ainda nao recebido.</span>
        </div>
        <div class="metric-card metric-success">
            <div class="small text-uppercase fw-semibold mb-1">Recebido no mes</div>
            <strong><?= app_money_br($summary['settled_month']) ?></strong>
            <span class="text-muted small">Total baixado no caixa no periodo atual.</span>
        </div>
        <div class="metric-card metric-accent">
            <div class="small text-uppercase fw-semibold mb-1">Vence em 7 dias</div>
            <strong><?= app_money_br($summary['due_week']) ?></strong>
            <span class="text-muted small">Receitas previstas para a semana.</span>
        </div>
        <div class="metric-card metric-warning">
            <div class="small text-uppercase fw-semibold mb-1">Atrasado</div>
            <strong><?= app_money_br($summary['overdue']) ?></strong>
            <span class="text-muted small">Titulos vencidos que ainda nao entraram.</span>
        </div>
    </div>

    <div class="soft-card card mt-4 no-print">
        <div class="card-body">
            <form class="toolbar-grid" method="GET">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca" class="form-control" value="<?= app_h($filters['busca']) ?>" placeholder="Descricao, documento, fonte pagadora, paciente ou plano">
                </div>
                <div>
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($accountStatuses as $value => $label): ?>
                            <option value="<?= app_h($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Mes</label>
                    <input type="month" name="mes" class="form-control" value="<?= app_h($filters['mes']) ?>">
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
                    <label class="form-label small text-muted">Centro de custo</label>
                    <select name="centro_custo_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($costCenters as $center): ?>
                            <option value="<?= (int) $center['id'] ?>" <?= $filters['centro_custo_id'] === (int) $center['id'] ? 'selected' : '' ?>><?= app_h($center['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Forma de recebimento</label>
                    <select name="forma_pagamento" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($paymentMethods as $value => $label): ?>
                            <option value="<?= app_h($value) ?>" <?= $filters['forma_pagamento'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="soft-card card mt-3">
        <div class="card-header">
            <div class="panel-title">
                <h5>Receitas cadastradas</h5>
                <span class="text-muted small"><?= count($items) ?> registro(s)</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-soft align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Conta</th>
                        <th>Vinculos</th>
                        <th>Datas</th>
                        <th>Valores</th>
                        <th>Baixa</th>
                        <th class="text-end no-print">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $statusMeta = app_financial_status_meta((string) $item['status'], $item['vencimento'] ?? null); ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= app_h($item['descricao']) ?></div>
                                <div class="small text-muted">
                                    <?= app_h($item['fonte_pagadora'] ?: 'Fonte pagadora nao informada') ?>
                                    <?php if (!empty($item['numero_documento'])): ?> | Doc. <?= app_h($item['numero_documento']) ?><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="small text-muted"><?= app_h(($item['plano_codigo'] ?: 'SEM-COD') . ' | ' . ($item['plano_nome'] ?: 'Sem plano')) ?></div>
                                <div class="small text-muted">Paciente: <?= app_h($item['paciente_nome'] ?: 'Nao vinculado') ?></div>
                                <div class="small text-muted">Profissional: <?= app_h($item['profissional_nome'] ?: 'Nao vinculado') ?></div>
                            </td>
                            <td>
                                <div class="small text-muted">Competencia: <?= app_date_br($item['competencia']) ?></div>
                                <div class="small text-muted">Vencimento: <?= app_date_br($item['vencimento']) ?></div>
                                <div class="small text-muted">Recebimento: <?= app_date_br($item['recebimento']) ?></div>
                            </td>
                            <td>
                                <div class="small text-muted">Previsto: <?= app_money_br((float) $item['valor_previsto']) ?></div>
                                <div class="small text-muted">Recebido: <?= app_money_br((float) $item['valor_liquidado']) ?></div>
                                <div class="small fw-semibold <?= (float) $item['saldo_aberto'] > 0 ? 'amount-positive' : 'amount-neutral' ?>">Saldo: <?= app_money_br((float) $item['saldo_aberto']) ?></div>
                            </td>
                            <td>
                                <span class="selection-chip <?= app_h($statusMeta['chip']) ?>"><?= app_h($statusMeta['label']) ?></span>
                                <div class="small text-muted mt-1"><?= app_financial_payment_method_label($item['forma_pagamento'] ?? null) ?></div>
                                <div class="small text-muted"><?= app_h($item['conta_nome'] ?: 'Sem conta financeira') ?></div>
                                <div class="small text-muted"><?= app_h($item['centro_nome'] ?: 'Sem centro de custo') ?></div>
                            </td>
                            <td class="text-end no-print">
                                <?php if (($item['status'] ?? '') !== 'pago' && ($item['status'] ?? '') !== 'cancelado'): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#settleReceivableModal"
                                        data-id="<?= (int) $item['id'] ?>"
                                        data-descricao="<?= app_h($item['descricao']) ?>"
                                        data-previsto="<?= number_format((float) $item['valor_previsto'], 2, ',', '.') ?>"
                                        data-recebido="<?= number_format((float) $item['valor_liquidado'], 2, ',', '.') ?>"
                                        data-data="<?= app_h($item['recebimento'] ?: date('Y-m-d')) ?>"
                                        data-forma="<?= app_h((string) ($item['forma_pagamento'] ?? '')) ?>"
                                        data-conta="<?= (int) ($item['conta_financeira_id'] ?? 0) ?>"
                                    >Dar baixa</button>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-outline-primary" href="editar_conta_receber.php?id=<?= (int) $item['id'] ?>">Editar</a>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta conta?')">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma conta a receber encontrada.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="settleReceivableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="settle">
                <input type="hidden" name="id" id="settleReceivableId">
                <div class="modal-header">
                    <h5 class="modal-title">Dar baixa no recebimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="finance-ledger-item mb-3">
                        <div>
                            <div class="title" id="settleReceivableDescription">Conta</div>
                            <div class="meta">Previsto <span id="settleReceivableExpected"></span> | Ja recebido <span id="settleReceivableSettled"></span></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Data do recebimento</label>
                            <input type="date" name="recebimento" id="settleReceivableDate" class="form-control" value="<?= app_h(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor recebido acumulado</label>
                            <input type="text" name="valor_recebido_total" id="settleReceivableAmount" class="form-control" placeholder="0,00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Forma de recebimento</label>
                            <select name="forma_pagamento" id="settleReceivableMethod" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($paymentMethods as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>"><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Conta financeira</label>
                            <select name="conta_financeira_id" id="settleReceivableAccount" class="form-select">
                                <option value="">Manter atual</option>
                                <?php foreach ($financialAccounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>"><?= app_h($account['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success">Registrar baixa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const settleReceivableModal = document.getElementById('settleReceivableModal');
if (settleReceivableModal) {
    settleReceivableModal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        document.getElementById('settleReceivableId').value = button.getAttribute('data-id') || '';
        document.getElementById('settleReceivableDescription').textContent = button.getAttribute('data-descricao') || 'Conta';
        document.getElementById('settleReceivableExpected').textContent = `R$ ${button.getAttribute('data-previsto') || '0,00'}`;
        document.getElementById('settleReceivableSettled').textContent = `R$ ${button.getAttribute('data-recebido') || '0,00'}`;
        document.getElementById('settleReceivableDate').value = button.getAttribute('data-data') || '';
        document.getElementById('settleReceivableAmount').value = button.getAttribute('data-previsto') || '';
        document.getElementById('settleReceivableMethod').value = button.getAttribute('data-forma') || '';
        document.getElementById('settleReceivableAccount').value = button.getAttribute('data-conta') || '';
    });
}
</script>

</body>
</html>
