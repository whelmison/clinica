<?php include 'config/db.php'; ?>
<?php
$clinicId = app_active_clinic_id();
$filters = [
    'busca' => app_request_query('busca', '') ?? '',
    'ativo' => app_request_query('ativo', '') ?? '',
    'tipo' => app_request_query('tipo', '') ?? '',
];

$selectedId = app_query_int('id');
$selectedAccount = $selectedId > 0 ? app_stmt_one($conn, 'SELECT * FROM contas_financeiras WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $selectedId]) : null;
$accountTypes = app_financial_account_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'save') {
        $id = app_post_int('id');
        $name = trim((string) ($_POST['nome'] ?? ''));
        $type = trim((string) ($_POST['tipo'] ?? 'banco'));
        $institution = trim((string) ($_POST['instituicao'] ?? ''));
        $agency = trim((string) ($_POST['agencia'] ?? ''));
        $number = trim((string) ($_POST['conta_numero'] ?? ''));
        $openingBalance = app_parse_money((string) ($_POST['saldo_inicial'] ?? '0'));
        $color = trim((string) ($_POST['cor'] ?? '#1f7a8c'));
        $active = isset($_POST['ativo']) ? 1 : 0;

        if ($name === '' || !array_key_exists($type, $accountTypes)) {
            app_flash('danger', 'Informe nome e tipo validos para a conta financeira.');
        } elseif ($id > 0) {
            $ok = app_stmt_execute(
                $conn,
                'UPDATE contas_financeiras
                 SET nome = ?, tipo = ?, instituicao = ?, agencia = ?, conta_numero = ?, saldo_inicial = ?, cor = ?, ativo = ?
                 WHERE clinica_id = ? AND id = ?',
                'sssssdsiii',
                [$name, $type, $institution ?: null, $agency ?: null, $number ?: null, $openingBalance, $color ?: null, $active, $clinicId, $id]
            );
            app_flash($ok ? 'success' : 'danger', $ok ? 'Conta financeira atualizada.' : 'Erro ao atualizar a conta financeira.');
        } else {
            $ok = app_stmt_execute(
                $conn,
                'INSERT INTO contas_financeiras (clinica_id, nome, tipo, instituicao, agencia, conta_numero, saldo_inicial, cor, ativo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'isssssdsi',
                [$clinicId, $name, $type, $institution ?: null, $agency ?: null, $number ?: null, $openingBalance, $color ?: null, $active]
            );
            app_flash($ok ? 'success' : 'danger', $ok ? 'Conta financeira criada.' : 'Erro ao criar a conta financeira.');
        }

        app_redirect('financeiro_contas_financeiras.php');
    }

    if ($action === 'delete') {
        $id = app_post_int('id');
        $linked = app_stmt_one(
            $conn,
            'SELECT
                (SELECT COUNT(*) FROM contas_pagar WHERE clinica_id = ? AND conta_financeira_id = ?) +
                (SELECT COUNT(*) FROM contas_receber WHERE clinica_id = ? AND conta_financeira_id = ?) AS total',
            'iiii',
            [$clinicId, $id, $clinicId, $id]
        );

        if ((int) ($linked['total'] ?? 0) > 0) {
            app_flash('danger', 'Nao e possivel excluir esta conta financeira porque existem movimentos vinculados.');
        } else {
            $ok = app_stmt_execute($conn, 'DELETE FROM contas_financeiras WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $id]);
            app_flash($ok ? 'success' : 'danger', $ok ? 'Conta financeira excluida.' : 'Erro ao excluir a conta financeira.');
        }

        app_redirect('financeiro_contas_financeiras.php');
    }
}

$where = ' WHERE cf.clinica_id = ? ';
$types = 'i';
$params = [$clinicId];

if ($filters['busca'] !== '') {
    $where .= ' AND (cf.nome LIKE ? OR cf.instituicao LIKE ? OR cf.conta_numero LIKE ?) ';
    $types .= 'sss';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
}

if ($filters['ativo'] !== '' && in_array($filters['ativo'], ['1', '0'], true)) {
    $where .= ' AND cf.ativo = ? ';
    $types .= 'i';
    $params[] = (int) $filters['ativo'];
}

if ($filters['tipo'] !== '' && array_key_exists($filters['tipo'], $accountTypes)) {
    $where .= ' AND cf.tipo = ? ';
    $types .= 's';
    $params[] = $filters['tipo'];
}

$expensePaidSql = 'CASE
    WHEN cp.status IN ("pago", "parcial")
    THEN CASE
        WHEN COALESCE(cp.valor_pago, 0) > 0 THEN cp.valor_pago
        ELSE GREATEST(0, cp.valor + COALESCE(cp.juros, 0) + COALESCE(cp.multa, 0) - COALESCE(cp.desconto, 0))
    END
    ELSE 0
END';
$incomeReceivedSql = 'CASE
    WHEN cr.status IN ("pago", "parcial")
    THEN CASE
        WHEN COALESCE(cr.valor_recebido, 0) > 0 THEN cr.valor_recebido
        ELSE GREATEST(0, cr.valor + COALESCE(cr.juros, 0) + COALESCE(cr.multa, 0) - COALESCE(cr.desconto, 0))
    END
    ELSE 0
END';

$items = app_stmt_all(
    $conn,
    'SELECT cf.*,
            COALESCE(pay.total_pago, 0) AS total_pago,
            COALESCE(rec.total_recebido, 0) AS total_recebido
     FROM contas_financeiras cf
     LEFT JOIN (
         SELECT conta_financeira_id, SUM(' . $expensePaidSql . ') AS total_pago
         FROM contas_pagar cp
         WHERE cp.clinica_id = ' . $clinicId . '
         GROUP BY conta_financeira_id
     ) pay ON pay.conta_financeira_id = cf.id
     LEFT JOIN (
         SELECT conta_financeira_id, SUM(' . $incomeReceivedSql . ') AS total_recebido
         FROM contas_receber cr
         WHERE cr.clinica_id = ' . $clinicId . '
         GROUP BY conta_financeira_id
     ) rec ON rec.conta_financeira_id = cf.id
     ' . $where . '
     ORDER BY cf.ativo DESC, cf.nome',
    $types,
    $params
);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Contas Financeiras</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Contas financeiras</h3>
                <p>Controle caixa, bancos, carteira PIX e outras contas usadas pela clinica no fluxo financeiro.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="administrativo_financeiro.php" class="btn btn-light btn-sm rounded-pill px-3">Financeiro</a>
                <a href="relatorio_financeiro_fechamento.php" class="btn btn-outline-light btn-sm rounded-pill px-3">Relatorios</a>
            </div>
        </div>
    </section>

    <div class="row g-3 mt-2">
        <div class="col-xl-4">
            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5><?= $selectedAccount ? 'Editar conta' : 'Nova conta' ?></h5>
                        <?php if ($selectedAccount): ?>
                            <span class="selection-chip"><?= app_h($selectedAccount['nome']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="save">
                        <?php if ($selectedAccount): ?>
                            <input type="hidden" name="id" value="<?= (int) $selectedAccount['id'] ?>">
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= app_h((string) ($selectedAccount['nome'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <?php foreach ($accountTypes as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= ($selectedAccount['tipo'] ?? 'banco') === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Saldo inicial</label>
                            <input type="text" name="saldo_inicial" class="form-control" value="<?= app_h(number_format((float) ($selectedAccount['saldo_inicial'] ?? 0), 2, ',', '.')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instituicao</label>
                            <input type="text" name="instituicao" class="form-control" value="<?= app_h((string) ($selectedAccount['instituicao'] ?? '')) ?>" placeholder="Banco ou carteira">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Agencia</label>
                            <input type="text" name="agencia" class="form-control" value="<?= app_h((string) ($selectedAccount['agencia'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Conta</label>
                            <input type="text" name="conta_numero" class="form-control" value="<?= app_h((string) ($selectedAccount['conta_numero'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor</label>
                            <input type="color" name="cor" class="form-control form-control-color w-100" value="<?= app_h((string) ($selectedAccount['cor'] ?? '#1f7a8c')) ?>">
                        </div>
                        <div class="col-md-6 form-check align-self-end ps-5">
                            <input class="form-check-input" type="checkbox" name="ativo" id="financialAccountActive" <?= !isset($selectedAccount['ativo']) || (int) $selectedAccount['ativo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="financialAccountActive">Conta ativa</label>
                        </div>
                        <div class="col-12 d-flex justify-content-between gap-2">
                            <?php if ($selectedAccount): ?>
                                <button type="button" class="btn btn-outline-danger" id="deleteFinancialAccountBtn">Excluir</button>
                            <?php else: ?>
                                <span class="text-muted small align-self-center">Use contas separadas para caixa, banco, PIX e recebiveis.</span>
                            <?php endif; ?>
                            <button class="btn btn-primary px-4"><?= $selectedAccount ? 'Atualizar' : 'Salvar' ?></button>
                        </div>
                    </form>
                    <?php if ($selectedAccount): ?>
                        <form method="POST" class="d-none" id="deleteFinancialAccountForm">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $selectedAccount['id'] ?>">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="soft-card card mb-3">
                <div class="card-body">
                    <form class="toolbar-grid" method="GET">
                        <div>
                            <label class="form-label small text-muted">Busca</label>
                            <input type="text" name="busca" class="form-control" value="<?= app_h($filters['busca']) ?>" placeholder="Nome, banco ou conta">
                        </div>
                        <div>
                            <label class="form-label small text-muted">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($accountTypes as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= $filters['tipo'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Status</label>
                            <select name="ativo" class="form-select">
                                <option value="">Todos</option>
                                <option value="1" <?= $filters['ativo'] === '1' ? 'selected' : '' ?>>Ativas</option>
                                <option value="0" <?= $filters['ativo'] === '0' ? 'selected' : '' ?>>Inativas</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-end">
                            <button class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Contas cadastradas</h5>
                        <span class="text-muted small"><?= count($items) ?> registro(s)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Conta</th>
                                <th>Fluxo</th>
                                <th>Saldo atual</th>
                                <th>Status</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php $currentBalance = (float) $item['saldo_inicial'] + (float) $item['total_recebido'] - (float) $item['total_pago']; ?>
                                <tr class="<?= $selectedAccount && (int) $selectedAccount['id'] === (int) $item['id'] ? 'is-active' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-dot" style="background: <?= app_h($item['cor'] ?: '#1f7a8c') ?>"></span>
                                            <div>
                                                <?= app_h($item['nome']) ?>
                                                <div class="small text-muted"><?= app_h($accountTypes[$item['tipo']] ?? ucfirst((string) $item['tipo'])) ?><?php if (!empty($item['instituicao'])): ?> | <?= app_h($item['instituicao']) ?><?php endif; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-muted">Entradas: <?= app_money_br((float) $item['total_recebido']) ?></div>
                                        <div class="small text-muted">Saidas: <?= app_money_br((float) $item['total_pago']) ?></div>
                                    </td>
                                    <td class="<?= $currentBalance >= 0 ? 'amount-positive' : 'amount-negative' ?>"><?= app_money_br($currentBalance) ?></td>
                                    <td>
                                        <span class="status-dot <?= (int) $item['ativo'] === 1 ? 'status-success' : 'status-danger' ?>"></span>
                                        <?= (int) $item['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="financeiro_contas_financeiras.php?<?= app_h(app_build_query(['id' => $item['id'], 'busca' => $filters['busca'], 'ativo' => $filters['ativo'], 'tipo' => $filters['tipo']])) ?>">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($items === []): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma conta financeira encontrada.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const deleteFinancialAccountBtn = document.getElementById('deleteFinancialAccountBtn');
const deleteFinancialAccountForm = document.getElementById('deleteFinancialAccountForm');
if (deleteFinancialAccountBtn && deleteFinancialAccountForm) {
    deleteFinancialAccountBtn.addEventListener('click', () => {
        if (confirm('Excluir esta conta financeira?')) {
            deleteFinancialAccountForm.submit();
        }
    });
}
</script>

</body>
</html>
