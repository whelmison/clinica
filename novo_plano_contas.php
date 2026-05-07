<?php include 'config/db.php'; ?>
<?php
$typeOptions = [
    'receita' => 'Receita',
    'despesa' => 'Despesa',
];
$clinicId = app_active_clinic_id();

$allPlans = app_fetch_planos_conta($conn, null);
$form = [
    'codigo' => trim((string) ($_POST['codigo'] ?? '')),
    'nome' => trim((string) ($_POST['nome'] ?? '')),
    'tipo' => trim((string) ($_POST['tipo'] ?? 'receita')),
    'categoria_pai_id' => (int) ($_POST['categoria_pai_id'] ?? 0),
    'descricao' => trim((string) ($_POST['descricao'] ?? '')),
    'ordem_exibicao' => (int) ($_POST['ordem_exibicao'] ?? 0),
    'ativo' => $_SERVER['REQUEST_METHOD'] === 'POST' ? (isset($_POST['ativo']) ? 1 : 0) : 1,
    'aceita_lancamento' => $_SERVER['REQUEST_METHOD'] === 'POST' ? (isset($_POST['aceita_lancamento']) ? 1 : 0) : 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $parentId = $form['categoria_pai_id'] > 0 ? $form['categoria_pai_id'] : null;
        $parent = $parentId ? app_stmt_one($conn, 'SELECT id, tipo FROM plano_contas WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $parentId]) : null;
        $existingCode = app_stmt_one($conn, 'SELECT id FROM plano_contas WHERE clinica_id = ? AND codigo = ? LIMIT 1', 'is', [$clinicId, $form['codigo']]);

        if ($form['codigo'] === '' || $form['nome'] === '' || !array_key_exists($form['tipo'], $typeOptions)) {
            app_flash('danger', 'Preencha codigo, nome e tipo do plano de contas.');
        } elseif ($existingCode) {
            app_flash('danger', 'Ja existe um plano de contas com este codigo.');
        } elseif ($parentId && (!$parent || $parent['tipo'] !== $form['tipo'])) {
            app_flash('danger', 'A categoria pai precisa existir e ter o mesmo tipo do plano.');
        } else {
            $ok = app_stmt_execute(
                $conn,
                'INSERT INTO plano_contas (clinica_id, codigo, nome, tipo, categoria_pai_id, descricao, ativo, aceita_lancamento, ordem_exibicao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'isssisiii',
                [
                    $clinicId,
                    $form['codigo'],
                    $form['nome'],
                    $form['tipo'],
                    $parentId,
                    $form['descricao'] ?: null,
                    $form['ativo'],
                    $form['aceita_lancamento'],
                    $form['ordem_exibicao'],
                ]
            );

            app_flash($ok ? 'success' : 'danger', $ok ? 'Plano de contas salvo.' : 'Erro ao salvar o plano de contas.');

            if ($ok) {
                app_redirect('financeiro_plano_contas.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Novo Plano de Contas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Novo plano de contas</h3>
                <p>Organize receitas e despesas da clinica com codigo, hierarquia, status e uso operacional.</p>
            </div>
            <a href="financeiro_plano_contas.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar para o plano de contas</a>
        </div>
    </section>

    <div class="row g-3 mt-2">
        <div class="col-xl-8">
            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Cadastro do plano</h5>
                        <span class="selection-chip">Estrutura financeira da clinica</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="create">
                        <div class="col-md-4">
                            <label class="form-label">Codigo</label>
                            <input type="text" name="codigo" class="form-control" value="<?= app_h($form['codigo']) ?>" placeholder="Ex.: R.001.005" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= app_h($form['nome']) ?>" placeholder="Ex.: Taxas de cartao" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <?php foreach ($typeOptions as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= $form['tipo'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Categoria pai</label>
                            <select name="categoria_pai_id" class="form-select">
                                <option value="">Sem categoria pai</option>
                                <?php foreach ($allPlans as $plan): ?>
                                    <option value="<?= (int) $plan['id'] ?>" <?= $form['categoria_pai_id'] === (int) $plan['id'] ? 'selected' : '' ?>>
                                        <?= app_h(($plan['codigo'] ?: 'SEM-COD') . ' | ' . $plan['nome'] . ' (' . ucfirst((string) $plan['tipo']) . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordem de exibicao</label>
                            <input type="number" name="ordem_exibicao" class="form-control" value="<?= (int) $form['ordem_exibicao'] ?>" min="0" step="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descricao</label>
                            <textarea name="descricao" rows="4" class="form-control" placeholder="Descreva quando essa categoria deve ser usada no financeiro da clinica."><?= app_h($form['descricao']) ?></textarea>
                        </div>
                        <div class="col-md-6 form-check ps-5">
                            <input class="form-check-input" type="checkbox" name="ativo" id="planActive" <?= $form['ativo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="planActive">Plano ativo</label>
                        </div>
                        <div class="col-md-6 form-check ps-5">
                            <input class="form-check-input" type="checkbox" name="aceita_lancamento" id="planLaunch" <?= $form['aceita_lancamento'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="planLaunch">Aceita lancamentos financeiros</label>
                        </div>
                        <div class="col-12 d-flex gap-2 mt-4">
                            <button class="btn btn-primary px-4">Salvar plano</button>
                            <a href="financeiro_plano_contas.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="soft-card card h-100">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Boas praticas</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="finance-ledger">
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Use codigos consistentes</div>
                                <div class="meta">Padroes como R.001.001 e D.003.002 ajudam nos relatorios.</div>
                            </div>
                        </div>
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Separe grupos e categorias</div>
                                <div class="meta">Categorias pai organizam o plano e as filhas recebem os lancamentos do dia a dia.</div>
                            </div>
                        </div>
                        <div class="finance-ledger-item">
                            <div>
                                <div class="title">Bloqueie categorias administrativas</div>
                                <div class="meta">Desmarque "aceita lancamentos" quando o plano for apenas agrupador.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
