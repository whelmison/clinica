<?php include 'config/db.php'; ?>
<?php
$clinicId = app_active_clinic_id();
$filters = [
    'busca' => app_request_query('busca', '') ?? '',
    'ativo' => app_request_query('ativo', '') ?? '',
];

$selectedId = app_query_int('id');
$selectedCenter = $selectedId > 0 ? app_stmt_one($conn, 'SELECT * FROM centros_custo WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $selectedId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'save') {
        $id = app_post_int('id');
        $name = trim((string) ($_POST['nome'] ?? ''));
        $description = trim((string) ($_POST['descricao'] ?? ''));
        $active = isset($_POST['ativo']) ? 1 : 0;

        if ($name === '') {
            app_flash('danger', 'Informe o nome do centro de custo.');
        } elseif ($id > 0) {
            $ok = app_stmt_execute($conn, 'UPDATE centros_custo SET nome = ?, descricao = ?, ativo = ? WHERE clinica_id = ? AND id = ?', 'ssiii', [$name, $description ?: null, $active, $clinicId, $id]);
            app_flash($ok ? 'success' : 'danger', $ok ? 'Centro de custo atualizado.' : 'Erro ao atualizar o centro de custo.');
        } else {
            $ok = app_stmt_execute($conn, 'INSERT INTO centros_custo (clinica_id, nome, descricao, ativo) VALUES (?, ?, ?, ?)', 'issi', [$clinicId, $name, $description ?: null, $active]);
            app_flash($ok ? 'success' : 'danger', $ok ? 'Centro de custo criado.' : 'Erro ao criar o centro de custo.');
        }

        app_redirect('financeiro_centros_custo.php');
    }

    if ($action === 'delete') {
        $id = app_post_int('id');
        $linked = app_stmt_one(
            $conn,
            'SELECT
                (SELECT COUNT(*) FROM contas_pagar WHERE clinica_id = ? AND centro_custo_id = ?) +
                (SELECT COUNT(*) FROM contas_receber WHERE clinica_id = ? AND centro_custo_id = ?) AS total',
            'iiii',
            [$clinicId, $id, $clinicId, $id]
        );

        if ((int) ($linked['total'] ?? 0) > 0) {
            app_flash('danger', 'Nao e possivel excluir este centro de custo porque ja existem lancamentos vinculados.');
        } else {
            $ok = app_stmt_execute($conn, 'DELETE FROM centros_custo WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $id]);
            app_flash($ok ? 'success' : 'danger', $ok ? 'Centro de custo excluido.' : 'Erro ao excluir o centro de custo.');
        }

        app_redirect('financeiro_centros_custo.php');
    }
}

$where = ' WHERE cc.clinica_id = ? ';
$types = 'i';
$params = [$clinicId];

if ($filters['busca'] !== '') {
    $where .= ' AND (cc.nome LIKE ? OR cc.descricao LIKE ?) ';
    $types .= 'ss';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
}

if ($filters['ativo'] !== '' && in_array($filters['ativo'], ['1', '0'], true)) {
    $where .= ' AND cc.ativo = ? ';
    $types .= 'i';
    $params[] = (int) $filters['ativo'];
}

$items = app_stmt_all(
    $conn,
    'SELECT cc.*,
            ((SELECT COUNT(*) FROM contas_pagar cp WHERE cp.clinica_id = cc.clinica_id AND cp.centro_custo_id = cc.id) +
             (SELECT COUNT(*) FROM contas_receber cr WHERE cr.clinica_id = cc.clinica_id AND cr.centro_custo_id = cc.id)) AS total_movimentos
     FROM centros_custo cc ' . $where . '
     ORDER BY cc.ativo DESC, cc.nome',
    $types,
    $params
);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Centros de Custo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Centros de custo</h3>
                <p>Separe as receitas e despesas por area da clinica, setor ou unidade operacional.</p>
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
                        <h5><?= $selectedCenter ? 'Editar centro' : 'Novo centro' ?></h5>
                        <?php if ($selectedCenter): ?>
                            <span class="selection-chip"><?= app_h($selectedCenter['nome']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="save">
                        <?php if ($selectedCenter): ?>
                            <input type="hidden" name="id" value="<?= (int) $selectedCenter['id'] ?>">
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= app_h((string) ($selectedCenter['nome'] ?? '')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descricao</label>
                            <textarea name="descricao" rows="4" class="form-control" placeholder="Ex.: Recepcao, atendimento, convenios ou administrativo."><?= app_h((string) ($selectedCenter['descricao'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12 form-check">
                            <input class="form-check-input" type="checkbox" name="ativo" id="centerActive" <?= !isset($selectedCenter['ativo']) || (int) $selectedCenter['ativo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="centerActive">Centro de custo ativo</label>
                        </div>
                        <div class="col-12 d-flex justify-content-between gap-2">
                            <?php if ($selectedCenter): ?>
                                <button type="button" class="btn btn-outline-danger" id="deleteCostCenterBtn">Excluir</button>
                            <?php else: ?>
                                <span class="text-muted small align-self-center">Use centros para separar setores, unidades e operacoes.</span>
                            <?php endif; ?>
                            <button class="btn btn-primary px-4"><?= $selectedCenter ? 'Atualizar' : 'Salvar' ?></button>
                        </div>
                    </form>
                    <?php if ($selectedCenter): ?>
                        <form method="POST" class="d-none" id="deleteCostCenterForm">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $selectedCenter['id'] ?>">
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
                            <input type="text" name="busca" class="form-control" value="<?= app_h($filters['busca']) ?>" placeholder="Nome ou descricao">
                        </div>
                        <div>
                            <label class="form-label small text-muted">Status</label>
                            <select name="ativo" class="form-select">
                                <option value="">Todos</option>
                                <option value="1" <?= $filters['ativo'] === '1' ? 'selected' : '' ?>>Ativos</option>
                                <option value="0" <?= $filters['ativo'] === '0' ? 'selected' : '' ?>>Inativos</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-end">
                            <button class="btn btn-primary w-100">Filtrar</button>
                        </div>
                        <div class="d-flex align-items-end">
                            <a href="financeiro_centros_custo.php" class="btn btn-outline-secondary w-100">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="soft-card card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Centros cadastrados</h5>
                        <span class="text-muted small"><?= count($items) ?> registro(s)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Centro</th>
                                <th>Movimentos</th>
                                <th>Status</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="<?= $selectedCenter && (int) $selectedCenter['id'] === (int) $item['id'] ? 'is-active' : '' ?>">
                                    <td>
                                        <?= app_h($item['nome']) ?>
                                        <div class="small text-muted"><?= app_h($item['descricao'] ?: 'Sem descricao adicional') ?></div>
                                    </td>
                                    <td><?= (int) $item['total_movimentos'] ?> lancamento(s)</td>
                                    <td>
                                        <span class="status-dot <?= (int) $item['ativo'] === 1 ? 'status-success' : 'status-danger' ?>"></span>
                                        <?= (int) $item['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="financeiro_centros_custo.php?<?= app_h(app_build_query(['id' => $item['id'], 'busca' => $filters['busca'], 'ativo' => $filters['ativo']])) ?>">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($items === []): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhum centro de custo encontrado.</td></tr>
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
const deleteCostCenterForm = document.getElementById('deleteCostCenterForm');
const deleteCenterBtn = document.getElementById('deleteCostCenterBtn');
if (deleteCenterBtn && deleteCostCenterForm) {
    deleteCenterBtn.addEventListener('click', () => {
        if (confirm('Excluir este centro de custo?')) {
            deleteCostCenterForm.submit();
        }
    });
}
</script>

</body>
</html>
