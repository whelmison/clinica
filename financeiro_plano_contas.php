<?php include 'config/db.php'; ?>
<?php
$clinicId = app_active_clinic_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $hasPagar = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM contas_pagar WHERE clinica_id = ? AND plano_conta_id = ?', 'ii', [$clinicId, $id]);
        $hasReceber = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM contas_receber WHERE clinica_id = ? AND plano_conta_id = ?', 'ii', [$clinicId, $id]);
        $hasFilhos = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM plano_contas WHERE clinica_id = ? AND categoria_pai_id = ?', 'ii', [$clinicId, $id]);

        if (((int) ($hasPagar['total'] ?? 0)) > 0 || ((int) ($hasReceber['total'] ?? 0)) > 0 || ((int) ($hasFilhos['total'] ?? 0)) > 0) {
            app_flash('danger', 'Nao e possivel excluir este plano porque existem lancamentos ou categorias filhas vinculadas.');
        } else {
            $ok = app_stmt_execute($conn, 'DELETE FROM plano_contas WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $id]);
            app_flash($ok ? 'success' : 'danger', $ok ? 'Plano de contas excluido.' : 'Erro ao excluir o plano de contas.');
        }

        app_redirect('financeiro_plano_contas.php');
    }
}

$filters = [
    'tipo' => trim((string) ($_GET['tipo'] ?? '')),
    'busca' => trim((string) ($_GET['busca'] ?? '')),
    'ativo' => trim((string) ($_GET['ativo'] ?? '')),
    'lancavel' => trim((string) ($_GET['lancavel'] ?? '')),
];

$where = ' WHERE pc.clinica_id = ? ';
$params = [$clinicId];
$types = 'i';

if ($filters['tipo'] !== '' && in_array($filters['tipo'], ['receita', 'despesa'], true)) {
    $where .= ' AND pc.tipo = ? ';
    $types .= 's';
    $params[] = $filters['tipo'];
}
if ($filters['busca'] !== '') {
    $where .= ' AND (pc.nome LIKE ? OR pc.codigo LIKE ? OR pc.descricao LIKE ? OR pai.nome LIKE ?) ';
    $types .= 'ssss';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
    $params[] = '%' . $filters['busca'] . '%';
}
if ($filters['ativo'] !== '' && in_array($filters['ativo'], ['1', '0'], true)) {
    $where .= ' AND pc.ativo = ? ';
    $types .= 'i';
    $params[] = (int) $filters['ativo'];
}
if ($filters['lancavel'] !== '' && in_array($filters['lancavel'], ['1', '0'], true)) {
    $where .= ' AND pc.aceita_lancamento = ? ';
    $types .= 'i';
    $params[] = (int) $filters['lancavel'];
}

$items = app_stmt_all(
    $conn,
    'SELECT pc.*,
            pai.nome AS pai_nome,
            pai.codigo AS pai_codigo,
            ((SELECT COUNT(*) FROM contas_pagar cp WHERE cp.clinica_id = pc.clinica_id AND cp.plano_conta_id = pc.id) +
             (SELECT COUNT(*) FROM contas_receber cr WHERE cr.clinica_id = pc.clinica_id AND cr.plano_conta_id = pc.id)) AS total_movimentos
     FROM plano_contas pc
     LEFT JOIN plano_contas pai ON pai.id = pc.categoria_pai_id AND pai.clinica_id = pc.clinica_id
     ' . $where . '
     ORDER BY pc.tipo, COALESCE(pai.codigo, pc.codigo), pc.ordem_exibicao, pc.codigo, pc.nome',
    $types,
    $params
);

$summary = [
    'total' => count($items),
    'receitas' => 0,
    'despesas' => 0,
    'lancaveis' => 0,
    'inativos' => 0,
];

foreach ($items as $item) {
    if (($item['tipo'] ?? '') === 'receita') {
        $summary['receitas']++;
    } else {
        $summary['despesas']++;
    }

    if ((int) ($item['aceita_lancamento'] ?? 0) === 1) {
        $summary['lancaveis']++;
    }

    if ((int) ($item['ativo'] ?? 1) === 0) {
        $summary['inativos']++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Plano de Contas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Plano de contas</h3>
                <p>Estruture receitas e despesas da clinica por codigo, hierarquia, uso nos lancamentos e visao gerencial.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="administrativo_financeiro.php" class="btn btn-light btn-sm rounded-pill px-3">Financeiro</a>
                <a href="novo_plano_contas.php" class="btn btn-success btn-sm rounded-pill px-3">Novo plano</a>
            </div>
        </div>
    </section>

    <div class="finance-grid mt-4">
        <div class="metric-card metric-primary">
            <div class="small text-uppercase fw-semibold mb-1">Planos listados</div>
            <strong><?= (int) $summary['total'] ?></strong>
            <span class="text-muted small">Filtros aplicados na tela atual.</span>
        </div>
        <div class="metric-card metric-success">
            <div class="small text-uppercase fw-semibold mb-1">Receitas</div>
            <strong><?= (int) $summary['receitas'] ?></strong>
            <span class="text-muted small">Categorias de entrada da clinica.</span>
        </div>
        <div class="metric-card metric-danger">
            <div class="small text-uppercase fw-semibold mb-1">Despesas</div>
            <strong><?= (int) $summary['despesas'] ?></strong>
            <span class="text-muted small">Categorias de saida e custo operacional.</span>
        </div>
        <div class="metric-card metric-accent">
            <div class="small text-uppercase fw-semibold mb-1">Lancaveis / Inativos</div>
            <strong><?= (int) $summary['lancaveis'] ?> / <?= (int) $summary['inativos'] ?></strong>
            <span class="text-muted small">Controle do que entra no dia a dia financeiro.</span>
        </div>
    </div>

    <div class="soft-card card mt-4">
        <div class="card-body">
            <form class="toolbar-grid" method="GET">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca" class="form-control" value="<?= app_h($filters['busca']) ?>" placeholder="Codigo, nome, descricao ou categoria pai">
                </div>
                <div>
                    <label class="form-label small text-muted">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="receita" <?= $filters['tipo'] === 'receita' ? 'selected' : '' ?>>Receita</option>
                        <option value="despesa" <?= $filters['tipo'] === 'despesa' ? 'selected' : '' ?>>Despesa</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Status</label>
                    <select name="ativo" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?= $filters['ativo'] === '1' ? 'selected' : '' ?>>Ativos</option>
                        <option value="0" <?= $filters['ativo'] === '0' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Aceita lancamento</label>
                    <select name="lancavel" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?= $filters['lancavel'] === '1' ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= $filters['lancavel'] === '0' ? 'selected' : '' ?>>Nao</option>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="financeiro_plano_contas.php" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="soft-card card mt-3">
        <div class="card-header">
            <div class="panel-title">
                <h5>Categorias cadastradas</h5>
                <span class="text-muted small"><?= count($items) ?> registro(s)</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-soft align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Plano</th>
                        <th>Hierarquia</th>
                        <th>Uso</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $isRevenue = ($item['tipo'] ?? '') === 'receita';
                        $statusChip = (int) ($item['ativo'] ?? 1) === 1 ? 'chip-success' : 'chip-danger';
                        $launchChip = (int) ($item['aceita_lancamento'] ?? 0) === 1 ? 'chip-info' : 'chip-warning';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= app_h(($item['codigo'] ?: 'SEM-COD') . ' | ' . $item['nome']) ?></div>
                                <div class="small text-muted"><?= app_h($item['descricao'] ?: 'Sem descricao complementar.') ?></div>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="status-dot <?= $isRevenue ? 'status-success' : 'status-danger' ?>"></span>
                                    <?= app_h(ucfirst((string) $item['tipo'])) ?>
                                </div>
                                <div class="small text-muted">
                                    Pai:
                                    <?= app_h($item['pai_nome'] ? (($item['pai_codigo'] ?: 'SEM-COD') . ' | ' . $item['pai_nome']) : 'Raiz') ?>
                                </div>
                                <div class="small text-muted">Ordem <?= (int) ($item['ordem_exibicao'] ?? 0) ?></div>
                            </td>
                            <td>
                                <div class="small text-muted"><?= (int) ($item['total_movimentos'] ?? 0) ?> lancamento(s) vinculado(s)</div>
                                <span class="selection-chip <?= $launchChip ?>">
                                    <?= (int) ($item['aceita_lancamento'] ?? 0) === 1 ? 'Aceita lancamento' : 'Somente agrupador' ?>
                                </span>
                            </td>
                            <td>
                                <span class="selection-chip <?= $statusChip ?>">
                                    <?= (int) ($item['ativo'] ?? 1) === 1 ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="editar_plano_contas.php?id=<?= (int) $item['id'] ?>">Editar</a>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este plano de contas?')">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum plano de contas encontrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
