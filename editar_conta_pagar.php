<?php include 'config/db.php'; ?>
<?php
$accountStatuses = app_account_statuses();
$paymentMethods = app_financial_payment_methods();
$clinicId = app_active_clinic_id();
$expensePlans = app_fetch_planos_conta_options($conn, 'despesa');
$costCenters = app_fetch_centros_custo($conn, true);
$financialAccounts = app_fetch_contas_financeiras($conn, true);

$id = (int) ($_GET['id'] ?? 0);
$editItem = app_stmt_one($conn, 'SELECT * FROM contas_pagar WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $id]);

if (!$editItem) {
    app_flash('danger', 'Conta a pagar nao encontrada.');
    app_redirect('financeiro_contas_pagar.php');
}

$form = [
    'descricao' => trim((string) ($_POST['descricao'] ?? $editItem['descricao'] ?? '')),
    'numero_documento' => trim((string) ($_POST['numero_documento'] ?? $editItem['numero_documento'] ?? '')),
    'favorecido' => trim((string) ($_POST['favorecido'] ?? $editItem['favorecido'] ?? '')),
    'competencia' => trim((string) ($_POST['competencia'] ?? $editItem['competencia'] ?? '')),
    'vencimento' => trim((string) ($_POST['vencimento'] ?? $editItem['vencimento'] ?? '')),
    'valor' => trim((string) ($_POST['valor'] ?? number_format((float) ($editItem['valor'] ?? 0), 2, ',', '.'))),
    'juros' => trim((string) ($_POST['juros'] ?? number_format((float) ($editItem['juros'] ?? 0), 2, ',', '.'))),
    'multa' => trim((string) ($_POST['multa'] ?? number_format((float) ($editItem['multa'] ?? 0), 2, ',', '.'))),
    'desconto' => trim((string) ($_POST['desconto'] ?? number_format((float) ($editItem['desconto'] ?? 0), 2, ',', '.'))),
    'plano_conta_id' => (int) ($_POST['plano_conta_id'] ?? $editItem['plano_conta_id'] ?? 0),
    'centro_custo_id' => (int) ($_POST['centro_custo_id'] ?? $editItem['centro_custo_id'] ?? 0),
    'conta_financeira_id' => (int) ($_POST['conta_financeira_id'] ?? $editItem['conta_financeira_id'] ?? 0),
    'status' => trim((string) ($_POST['status'] ?? $editItem['status'] ?? 'aberto')),
    'pagamento' => trim((string) ($_POST['pagamento'] ?? $editItem['pagamento'] ?? '')),
    'valor_pago' => trim((string) ($_POST['valor_pago'] ?? number_format((float) ($editItem['valor_pago'] ?? 0), 2, ',', '.'))),
    'forma_pagamento' => trim((string) ($_POST['forma_pagamento'] ?? $editItem['forma_pagamento'] ?? '')),
    'observacoes' => trim((string) ($_POST['observacoes'] ?? $editItem['observacoes'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $descricao = $form['descricao'];
        $valor = app_parse_money($form['valor'] !== '' ? $form['valor'] : '0');
        $juros = app_parse_money($form['juros'] !== '' ? $form['juros'] : '0');
        $multa = app_parse_money($form['multa'] !== '' ? $form['multa'] : '0');
        $desconto = app_parse_money($form['desconto'] !== '' ? $form['desconto'] : '0');
        $vencimento = $form['vencimento'];
        $competencia = $form['competencia'] !== '' ? $form['competencia'] : ($vencimento !== '' ? date('Y-m-01', strtotime($vencimento)) : date('Y-m-01'));
        $status = array_key_exists($form['status'], $accountStatuses) ? $form['status'] : 'aberto';
        $planoContaId = $form['plano_conta_id'] > 0 ? $form['plano_conta_id'] : null;
        $centroCustoId = $form['centro_custo_id'] > 0 ? $form['centro_custo_id'] : null;
        $contaFinanceiraId = $form['conta_financeira_id'] > 0 ? $form['conta_financeira_id'] : null;
        $pagamento = $form['pagamento'] !== '' ? $form['pagamento'] : null;
        $valorPago = $form['valor_pago'] !== '' ? app_parse_money($form['valor_pago']) : 0;
        $formaPagamento = $form['forma_pagamento'] !== '' ? $form['forma_pagamento'] : null;
        $expected = app_financial_net_amount($valor, $juros, $multa, $desconto);

        if ($descricao === '' || $valor <= 0 || $vencimento === '') {
            app_flash('danger', 'Preencha descricao, valor e vencimento da conta.');
        } elseif ($status === 'parcial' && $valorPago <= 0) {
            app_flash('danger', 'Informe o valor pago para contas parciais.');
        } elseif (in_array($status, ['pago', 'parcial'], true) && $formaPagamento === null) {
            app_flash('danger', 'Informe a forma de pagamento para a baixa financeira.');
        } else {
            if (in_array($status, ['pago', 'parcial'], true)) {
                if ($pagamento === null) {
                    $pagamento = date('Y-m-d');
                }

                if ($status === 'pago' && $valorPago <= 0) {
                    $valorPago = $expected;
                }

                if ($valorPago > 0 && $valorPago < $expected) {
                    $status = 'parcial';
                } elseif ($valorPago >= $expected) {
                    $status = 'pago';
                    $valorPago = $expected;
                }
            } else {
                $pagamento = null;
                $valorPago = null;
                $formaPagamento = null;
            }

            $ok = app_stmt_execute(
                $conn,
                'UPDATE contas_pagar
                 SET descricao = ?, valor = ?, vencimento = ?, competencia = ?, pagamento = ?, status = ?,
                     plano_conta_id = ?, centro_custo_id = ?, conta_financeira_id = ?, forma_pagamento = ?,
                     valor_pago = ?, juros = ?, multa = ?, desconto = ?, numero_documento = ?, favorecido = ?, observacoes = ?
                 WHERE clinica_id = ? AND id = ?',
                'sdssssiiisddddsssii',
                [
                    $descricao,
                    $valor,
                    $vencimento,
                    $competencia,
                    $pagamento,
                    $status,
                    $planoContaId,
                    $centroCustoId,
                    $contaFinanceiraId,
                    $formaPagamento,
                    $valorPago,
                    $juros,
                    $multa,
                    $desconto,
                    $form['numero_documento'] ?: null,
                    $form['favorecido'] ?: null,
                    $form['observacoes'] ?: null,
                    $clinicId,
                    $id,
                ]
            );

            app_flash($ok ? 'success' : 'danger', $ok ? 'Conta a pagar atualizada.' : 'Erro ao atualizar a conta a pagar.');

            if ($ok) {
                app_redirect('financeiro_contas_pagar.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Editar Conta a Pagar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Editar conta a pagar</h3>
                <p>Atualize a despesa, refaca a classificacao e registre a baixa financeira com total controle.</p>
            </div>
            <a href="financeiro_contas_pagar.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar para contas a pagar</a>
        </div>
    </section>

    <div class="row g-3 mt-2">
        <div class="col-xl-8">
            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Conta #<?= (int) $editItem['id'] ?></h5>
                        <span class="selection-chip"><?= app_h($editItem['descricao']) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3" id="payableForm">
                        <input type="hidden" name="action" value="update">
                        <div class="col-md-8">
                            <label class="form-label">Descricao</label>
                            <input type="text" name="descricao" class="form-control" value="<?= app_h($form['descricao']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Documento</label>
                            <input type="text" name="numero_documento" class="form-control" value="<?= app_h($form['numero_documento']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Favorecido</label>
                            <input type="text" name="favorecido" class="form-control" value="<?= app_h($form['favorecido']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Competencia</label>
                            <input type="date" name="competencia" class="form-control" value="<?= app_h($form['competencia']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vencimento</label>
                            <input type="date" name="vencimento" class="form-control" value="<?= app_h($form['vencimento']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Valor principal</label>
                            <input type="text" name="valor" class="form-control js-money-source" value="<?= app_h($form['valor']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Juros</label>
                            <input type="text" name="juros" class="form-control js-money-source" value="<?= app_h($form['juros']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Multa</label>
                            <input type="text" name="multa" class="form-control js-money-source" value="<?= app_h($form['multa']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desconto</label>
                            <input type="text" name="desconto" class="form-control js-money-source" value="<?= app_h($form['desconto']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plano de contas</label>
                            <select name="plano_conta_id" class="form-select">
                                <option value="">Selecione</option>
                                <?php foreach ($expensePlans as $plan): ?>
                                    <option value="<?= (int) $plan['id'] ?>" <?= $form['plano_conta_id'] === (int) $plan['id'] ? 'selected' : '' ?>>
                                        <?= app_h(($plan['codigo'] ?: 'SEM-COD') . ' | ' . $plan['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Centro de custo</label>
                            <select name="centro_custo_id" class="form-select">
                                <option value="">Nao vincular</option>
                                <?php foreach ($costCenters as $center): ?>
                                    <option value="<?= (int) $center['id'] ?>" <?= $form['centro_custo_id'] === (int) $center['id'] ? 'selected' : '' ?>><?= app_h($center['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Conta financeira</label>
                            <select name="conta_financeira_id" class="form-select">
                                <option value="">Nao vincular</option>
                                <?php foreach ($financialAccounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= $form['conta_financeira_id'] === (int) $account['id'] ? 'selected' : '' ?>><?= app_h($account['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="payableStatus">
                                <?php foreach ($accountStatuses as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 settlement-field">
                            <label class="form-label">Data do pagamento</label>
                            <input type="date" name="pagamento" class="form-control" value="<?= app_h($form['pagamento']) ?>">
                        </div>
                        <div class="col-md-3 settlement-field">
                            <label class="form-label">Valor pago</label>
                            <input type="text" name="valor_pago" class="form-control" value="<?= app_h($form['valor_pago']) ?>">
                        </div>
                        <div class="col-md-3 settlement-field">
                            <label class="form-label">Forma de pagamento</label>
                            <select name="forma_pagamento" class="form-select">
                                <option value="">Selecione</option>
                                <?php foreach ($paymentMethods as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= $form['forma_pagamento'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observacoes</label>
                            <textarea name="observacoes" rows="4" class="form-control"><?= app_h($form['observacoes']) ?></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center gap-3 mt-4 flex-wrap">
                            <div class="selection-chip chip-info">Total previsto: <strong id="payableTotalPreview">R$ 0,00</strong></div>
                            <div class="d-flex gap-2">
                                <a href="financeiro_contas_pagar.php" class="btn btn-outline-secondary">Cancelar</a>
                                <button class="btn btn-primary px-4">Atualizar conta</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Resumo da baixa</h5>
                    </div>
                </div>
                <div class="card-body">
                    <?php $amounts = app_financial_row_amounts($editItem, 'valor_pago'); ?>
                    <div class="finance-ledger">
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Previsto</div>
                                <div class="meta">Valor total da despesa com ajustes.</div>
                            </div>
                            <div class="amount amount-negative"><?= app_money_br($amounts['expected']) ?></div>
                        </div>
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Ja pago</div>
                                <div class="meta">Valor contabilizado na baixa atual.</div>
                            </div>
                            <div class="amount amount-negative"><?= app_money_br($amounts['settled']) ?></div>
                        </div>
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Saldo em aberto</div>
                                <div class="meta">Valor que ainda falta sair do caixa.</div>
                            </div>
                            <div class="amount <?= $amounts['open'] > 0 ? 'amount-negative' : 'amount-positive' ?>"><?= app_money_br($amounts['open']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const payableStatus = document.getElementById('payableStatus');
const payableForm = document.getElementById('payableForm');
const payableSettlementFields = payableForm.querySelectorAll('.settlement-field');
const payableMoneyInputs = payableForm.querySelectorAll('.js-money-source');
const payableTotalPreview = document.getElementById('payableTotalPreview');

function parseMoneyBr(value) {
    const normalized = (value || '').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatMoneyBr(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function refreshPayableState() {
    const requiresSettlement = ['pago', 'parcial'].includes(payableStatus.value);
    payableSettlementFields.forEach((field) => {
        field.style.display = requiresSettlement ? '' : 'none';
    });
}

function refreshPayableTotal() {
    const total = parseMoneyBr(payableForm.querySelector('[name="valor"]').value)
        + parseMoneyBr(payableForm.querySelector('[name="juros"]').value)
        + parseMoneyBr(payableForm.querySelector('[name="multa"]').value)
        - parseMoneyBr(payableForm.querySelector('[name="desconto"]').value);
    payableTotalPreview.textContent = formatMoneyBr(Math.max(0, total));
}

payableStatus.addEventListener('change', refreshPayableState);
payableMoneyInputs.forEach((input) => input.addEventListener('input', refreshPayableTotal));
refreshPayableState();
refreshPayableTotal();
</script>

</body>
</html>
