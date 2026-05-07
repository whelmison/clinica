<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Planos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
html,
body {
    height: 100%;
}

body {
    display: flex;
    flex-direction: column;
}

.plans-shell {
    flex: 1;
    min-height: 0;
    padding-top: 0.35rem;
    padding-bottom: 0.85rem;
}

.plans-shell .page-hero {
    margin-top: 0.35rem;
    padding: 1rem 1.1rem 0.92rem;
    border-radius: 20px;
}

.plans-shell .page-hero h3 {
    font-size: 1.1rem;
}

.plans-shell .page-hero p {
    font-size: 0.76rem;
    line-height: 1.18;
}

.plans-shell .soft-card {
    border-radius: 18px;
}

.plans-shell .soft-card .card-header {
    padding: 0.68rem 0.82rem 0.58rem;
}

.plans-shell .soft-card .card-body {
    padding: 0.78rem 0.82rem;
}

.plans-shell .page-hero .btn {
    min-height: 38px;
    font-size: 0.78rem;
}

.plans-form .form-control {
    min-height: 42px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.plans-form .btn {
    min-height: 42px;
    border-radius: 14px;
    font-size: 0.82rem;
}

.plans-note {
    color: #68828f;
    font-size: 0.72rem;
}

.plans-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}

.plans-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.plans-modal .modal-body {
    padding: 0.95rem 1rem 1rem;
}

.plans-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.plans-modal .btn-close {
    box-shadow: none;
}

.plans-shell .toolbar-grid {
    gap: 0.7rem;
}

.plans-shell .toolbar-grid .form-control,
.plans-shell .toolbar-grid .btn {
    min-height: 40px;
    font-size: 0.8rem;
}

.plans-table .plan-name {
    font-weight: 700;
    color: #16333f;
    font-size: 0.84rem;
    line-height: 1.08;
}

.plans-table .plan-value {
    font-weight: 700;
    color: #0f4c5c;
    font-size: 0.82rem;
    line-height: 1;
}

.plans-table .btn {
    min-width: 78px;
    min-height: 28px;
    font-size: 0.68rem;
    line-height: 1;
    padding-top: 0.22rem;
    padding-bottom: 0.22rem;
}

.plans-table.table-soft {
    font-size: 0.76rem;
}

.plans-table.table-soft thead th {
    padding: 0.34rem 0.45rem;
    font-size: 0.62rem;
    line-height: 1.08;
}

.plans-table.table-soft tbody td {
    padding: 0.34rem 0.45rem;
    line-height: 1.12;
}

.plans-table .small {
    line-height: 1.08;
    margin-top: 0.08rem;
}

@media (max-width: 991px) {
    body {
        display: block;
    }

    .plans-shell {
        min-height: auto;
        padding-bottom: 1rem;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell plans-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Planos</h3>
                <p>Cadastre, filtre e acompanhe os valores padrao de sessao em um fluxo alinhado ao restante do sistema.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#planCreateModal">+ Novo plano</button>
                <a href="planos.php" class="btn btn-light btn-sm rounded-pill px-3">Atualizar lista</a>
            </div>
        </div>
    </section>

    <div class="soft-card card mt-3">
        <div class="card-header">
            <div class="panel-title">
                <h5>Planos cadastrados</h5>
                <span class="text-muted small"><?= (int) ($plansPagination['total'] ?? 0) ?> registro(s)</span>
            </div>
        </div>
        <div class="card-body">
            <form class="toolbar-grid mb-3" method="GET">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca_plano" class="form-control" value="<?= app_h($planFilters['busca_plano']) ?>" placeholder="Nome do plano">
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="planos.php" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-soft align-middle mb-0 plans-table">
                    <thead>
                    <tr>
                        <th>Plano</th>
                        <th>Valor da sessao</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plansRows as $plan): ?>
                        <tr>
                            <td>
                                <div class="plan-name"><?= app_h((string) $plan['nome']) ?></div>
                                <div class="small text-muted">Plano cadastrado no catalogo administrativo</div>
                            </td>
                            <td>
                                <span class="plan-value"><?= app_money_br((float) $plan['valor_sessao']) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <a href="editar_plano.php?<?= app_h(app_build_query(['id' => $plan['id']])) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form method="POST" action="excluir_plano.php" class="d-inline" onsubmit="return confirm('Excluir plano?')">
                                        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($plansRows === []): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">
                                Nenhum plano encontrado para os filtros informados.
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#planCreateModal">Cadastrar primeiro plano</button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?= app_render_pagination($plansPagination) ?>
        </div>
    </div>
</div>

<div class="modal fade plans-modal" id="planCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Novo plano</h5>
                    <div class="small text-muted">Cadastro rapido sem ocupar espaco da listagem.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="plans-form">
                    <input type="hidden" name="action" value="save_plan">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small text-muted">Nome do plano</label>
                            <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h($formValues['nome']) ?>" placeholder="Ex.: Unimed, Bradesco, particular" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Valor da sessao</label>
                            <input type="text" name="valor_sessao" class="form-control" inputmode="decimal" value="<?= app_h($formValues['valor_sessao']) ?>" placeholder="R$ 0,00" required>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-3">
                        <span class="plans-note">Mantenha um nome claro e o valor padrao usado no faturamento.</span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary px-4">Salvar plano</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($autoOpenCreateModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('planCreateModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
