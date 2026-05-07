<?php include 'config/db.php'; ?>
<?php
$accountStatuses = app_account_statuses();
$paymentMethods = app_financial_payment_methods();
$clinicId = app_active_clinic_id();
$professionals = app_fetch_profissionais($conn);
$patients = app_fetch_pacientes($conn);
$revenuePlans = app_fetch_planos_conta_options($conn, 'receita');
$costCenters = app_fetch_centros_custo($conn, true);
$financialAccounts = app_fetch_contas_financeiras($conn, true);

$form = [
    'descricao' => trim((string) ($_POST['descricao'] ?? '')),
    'numero_documento' => trim((string) ($_POST['numero_documento'] ?? '')),
    'fonte_pagadora' => trim((string) ($_POST['fonte_pagadora'] ?? '')),
    'paciente_id' => (int) ($_POST['paciente_id'] ?? 0),
    'profissional_id' => (int) ($_POST['profissional_id'] ?? 0),
    'competencia' => trim((string) ($_POST['competencia'] ?? date('Y-m-01'))),
    'vencimento' => trim((string) ($_POST['vencimento'] ?? date('Y-m-d'))),
    'valor' => trim((string) ($_POST['valor'] ?? '')),
    'juros' => trim((string) ($_POST['juros'] ?? '0,00')),
    'multa' => trim((string) ($_POST['multa'] ?? '0,00')),
    'desconto' => trim((string) ($_POST['desconto'] ?? '0,00')),
    'plano_conta_id' => (int) ($_POST['plano_conta_id'] ?? 0),
    'centro_custo_id' => (int) ($_POST['centro_custo_id'] ?? 0),
    'conta_financeira_id' => (int) ($_POST['conta_financeira_id'] ?? 0),
    'status' => trim((string) ($_POST['status'] ?? 'aberto')),
    'recebimento' => trim((string) ($_POST['recebimento'] ?? '')),
    'valor_recebido' => trim((string) ($_POST['valor_recebido'] ?? '')),
    'forma_pagamento' => trim((string) ($_POST['forma_pagamento'] ?? '')),
    'observacoes' => trim((string) ($_POST['observacoes'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
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
        $pacienteId = $form['paciente_id'] > 0 ? $form['paciente_id'] : null;
        $profissionalId = $form['profissional_id'] > 0 ? $form['profissional_id'] : null;
        $recebimento = $form['recebimento'] !== '' ? $form['recebimento'] : null;
        $valorRecebido = $form['valor_recebido'] !== '' ? app_parse_money($form['valor_recebido']) : 0;
        $formaPagamento = $form['forma_pagamento'] !== '' ? $form['forma_pagamento'] : null;
        $expected = app_financial_net_amount($valor, $juros, $multa, $desconto);

        if ($descricao === '' || $valor <= 0 || $vencimento === '') {
            app_flash('danger', 'Preencha descricao, valor e vencimento da conta.');
        } elseif ($status === 'parcial' && $valorRecebido <= 0) {
            app_flash('danger', 'Informe o valor recebido para contas parciais.');
        } elseif (in_array($status, ['pago', 'parcial'], true) && $formaPagamento === null) {
            app_flash('danger', 'Informe a forma de recebimento para a baixa financeira.');
        } else {
            if (in_array($status, ['pago', 'parcial'], true)) {
                if ($recebimento === null) {
                    $recebimento = date('Y-m-d');
                }

                if ($status === 'pago' && $valorRecebido <= 0) {
                    $valorRecebido = $expected;
                }

                if ($valorRecebido > 0 && $valorRecebido < $expected) {
                    $status = 'parcial';
                } elseif ($valorRecebido >= $expected) {
                    $status = 'pago';
                    $valorRecebido = $expected;
                }
            } else {
                $recebimento = null;
                $valorRecebido = null;
                $formaPagamento = null;
            }

            $ok = app_stmt_execute(
                $conn,
                'INSERT INTO contas_receber (
                    clinica_id, descricao, valor, vencimento, competencia, recebimento, status, plano_conta_id, profissional_id,
                    centro_custo_id, conta_financeira_id, forma_pagamento, valor_recebido, juros, multa, desconto,
                    numero_documento, fonte_pagadora, paciente_id, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'isdssssiiiisddddssis',
                [
                    $clinicId,
                    $descricao,
                    $valor,
                    $vencimento,
                    $competencia,
                    $recebimento,
                    $status,
                    $planoContaId,
                    $profissionalId,
                    $centroCustoId,
                    $contaFinanceiraId,
                    $formaPagamento,
                    $valorRecebido,
                    $juros,
                    $multa,
                    $desconto,
                    $form['numero_documento'] ?: null,
                    $form['fonte_pagadora'] ?: null,
                    $pacienteId,
                    $form['observacoes'] ?: null,
                ]
            );

            app_flash($ok ? 'success' : 'danger', $ok ? 'Conta a receber registrada.' : 'Erro ao registrar a conta a receber.');

            if ($ok) {
                app_redirect('financeiro_contas_receber.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Nova Conta a Receber</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Nova conta a receber</h3>
                <p>Controle recebimentos de pacientes, convenios e guias com baixa financeira completa.</p>
            </div>
            <a href="financeiro_contas_receber.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar para contas a receber</a>
        </div>
    </section>

    <div class="row g-3 mt-2">
        <div class="col-xl-8">
            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Lancamento da receita</h5>
                        <span class="selection-chip">Recebimento particular, convenio ou guia</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3" id="receivableForm">
                        <input type="hidden" name="action" value="create">
                        <div class="col-md-8">
                            <label class="form-label">Descricao</label>
                            <input type="text" name="descricao" class="form-control" value="<?= app_h($form['descricao']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Documento</label>
                            <input type="text" name="numero_documento" class="form-control" value="<?= app_h($form['numero_documento']) ?>" placeholder="Guia, NF ou contrato">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fonte pagadora</label>
                            <input type="text" name="fonte_pagadora" class="form-control" value="<?= app_h($form['fonte_pagadora']) ?>" placeholder="Paciente, convenio ou empresa">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Paciente</label>
                            <select name="paciente_id" class="form-select">
                                <option value="">Nao vincular</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?= (int) $patient['id'] ?>" <?= $form['paciente_id'] === (int) $patient['id'] ? 'selected' : '' ?>><?= app_h($patient['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Profissional</label>
                            <select name="profissional_id" class="form-select">
                                <option value="">Nao vincular</option>
                                <?php foreach ($professionals as $prof): ?>
                                    <option value="<?= (int) $prof['id'] ?>" <?= $form['profissional_id'] === (int) $prof['id'] ? 'selected' : '' ?>><?= app_h($prof['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                            <input type="text" name="valor" class="form-control js-money-source" value="<?= app_h($form['valor']) ?>" placeholder="0,00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Juros</label>
                            <input type="text" name="juros" class="form-control js-money-source" value="<?= app_h($form['juros']) ?>" placeholder="0,00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Multa</label>
                            <input type="text" name="multa" class="form-control js-money-source" value="<?= app_h($form['multa']) ?>" placeholder="0,00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desconto</label>
                            <input type="text" name="desconto" class="form-control js-money-source" value="<?= app_h($form['desconto']) ?>" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plano de contas</label>
                            <select name="plano_conta_id" class="form-select">
                                <option value="">Selecione</option>
                                <?php foreach ($revenuePlans as $plan): ?>
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
                            <select name="status" class="form-select" id="receivableStatus">
                                <?php foreach ($accountStatuses as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 settlement-field">
                            <label class="form-label">Data do recebimento</label>
                            <input type="date" name="recebimento" class="form-control" value="<?= app_h($form['recebimento']) ?>">
                        </div>
                        <div class="col-md-3 settlement-field">
                            <label class="form-label">Valor recebido</label>
                            <input type="text" name="valor_recebido" class="form-control" value="<?= app_h($form['valor_recebido']) ?>" placeholder="0,00">
                        </div>
                        <div class="col-md-3 settlement-field">
                            <label class="form-label">Forma de recebimento</label>
                            <select name="forma_pagamento" class="form-select">
                                <option value="">Selecione</option>
                                <?php foreach ($paymentMethods as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= $form['forma_pagamento'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observacoes</label>
                            <textarea name="observacoes" rows="4" class="form-control" placeholder="Detalhes do convenio, paciente, repasse ou contexto de cobranca."><?= app_h($form['observacoes']) ?></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center gap-3 mt-4 flex-wrap">
                            <div class="selection-chip chip-success">Total previsto: <strong id="receivableTotalPreview">R$ 0,00</strong></div>
                            <div class="d-flex gap-2">
                                <a href="financeiro_contas_receber.php" class="btn btn-outline-secondary">Cancelar</a>
                                <button class="btn btn-primary px-4">Salvar conta</button>
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
                        <h5>Controles clinicos</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Fonte pagadora</div>
                                <div class="meta">Identifique se o recebimento vem do paciente, convenio, empresa ou parceiro.</div>
                            </div>
                        </div>
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Baixa por metodo</div>
                                <div class="meta">Registre se entrou por boleto, dinheiro, PIX, cartao ou transferencia.</div>
                            </div>
                        </div>
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Rastreabilidade</div>
                                <div class="meta">Conecte paciente, profissional e conta financeira para relatarios completos.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const receivableStatus = document.getElementById('receivableStatus');
const receivableForm = document.getElementById('receivableForm');
const receivableSettlementFields = receivableForm.querySelectorAll('.settlement-field');
const receivableMoneyInputs = receivableForm.querySelectorAll('.js-money-source');
const receivableTotalPreview = document.getElementById('receivableTotalPreview');

function parseMoneyBr(value) {
    const normalized = (value || '').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatMoneyBr(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function refreshReceivableState() {
    const requiresSettlement = ['pago', 'parcial'].includes(receivableStatus.value);
    receivableSettlementFields.forEach((field) => {
        field.style.display = requiresSettlement ? '' : 'none';
    });
}

function refreshReceivableTotal() {
    const total = parseMoneyBr(receivableForm.querySelector('[name="valor"]').value)
        + parseMoneyBr(receivableForm.querySelector('[name="juros"]').value)
        + parseMoneyBr(receivableForm.querySelector('[name="multa"]').value)
        - parseMoneyBr(receivableForm.querySelector('[name="desconto"]').value);
    receivableTotalPreview.textContent = formatMoneyBr(Math.max(0, total));
}

receivableStatus.addEventListener('change', refreshReceivableState);
receivableMoneyInputs.forEach((input) => input.addEventListener('input', refreshReceivableTotal));
refreshReceivableState();
refreshReceivableTotal();
</script>

</body>
</html>
