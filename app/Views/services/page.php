<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Servicos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
html,
body {
    height: 100%;
    overflow: hidden;
}
body {
    display: flex;
    flex-direction: column;
}
.services-shell {
    flex: 1;
    min-height: 0;
    padding-top: 0.2rem;
    padding-bottom: 0.35rem !important;
}
.services-layout {
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.services-hero {
    margin-top: 0.2rem;
    padding: 0.78rem 0.92rem 0.74rem;
}
.services-hero h3 {
    font-size: 1.02rem;
}
.services-hero p {
    font-size: 0.72rem;
}
.services-card {
    border-radius: 18px;
}
.services-card .card-body,
.services-card .card-header {
    padding: 0.72rem 0.82rem;
}
.services-table-card {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.services-table-card .card-body {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.services-table-wrap {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.services-table-wrap .table {
    font-size: 0.74rem;
}
.services-table-wrap .table thead th {
    padding: 0.42rem 0.48rem;
    font-size: 0.6rem;
}
.services-table-wrap .table tbody td {
    padding: 0.42rem 0.48rem;
}
.services-table-wrap .btn {
    min-height: 28px;
    font-size: 0.68rem;
}
.services-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}
.services-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}
.services-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}
.services-form .form-control,
.services-form .form-select {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}
.services-form .btn,
.services-modal .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}
.services-hint {
    color: #68828f;
    font-size: 0.72rem;
}
@media (max-width: 991px) {
    html,
    body {
        overflow: auto;
    }
    .services-shell,
    .services-layout,
    .services-table-card,
    .services-table-card .card-body,
    .services-table-wrap {
        height: auto;
        min-height: auto;
        overflow: visible;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell services-shell">
    <div class="services-layout">
    <section class="page-hero services-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Catalogo de servicos</h3>
                <p>Lista central de servicos com filtro, duracao padrao, status e acesso separado para novo e editar.</p>
            </div>
            <button type="button" class="btn btn-light btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#serviceFormModal">+ Novo servico</button>
        </div>
    </section>

    <div class="soft-card card services-card">
        <div class="card-body">
            <form class="toolbar-grid" method="GET">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca_servico" class="form-control" data-page-autofocus="1" value="<?= app_h($filters['busca_servico']) ?>" placeholder="Nome do servico">
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="secretaria_servicos.php" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="soft-card card services-card services-table-card">
        <div class="card-header">
            <div class="panel-title">
                <h5>Servicos cadastrados</h5>
                <span class="text-muted small">novo, editar, listar e filtrar</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive services-table-wrap">
                <table class="table table-soft align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Servico</th>
                        <th>Duracao</th>
                        <th>Profissionais</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($servicesRows as $service): ?>
                        <tr>
                            <td><?= app_h((string) $service['nome']) ?></td>
                            <td><?= (int) $service['tempo_minutos'] ?> min</td>
                            <td><?= (int) $service['total_profissionais'] ?></td>
                            <td><?= (int) $service['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="editar_servico.php?<?= app_h(app_build_query(['id' => $service['id']])) ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= app_render_pagination($pagination) ?>
        </div>
    </div>
    </div>
</div>

<div class="modal fade services-modal" id="serviceFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Novo servico</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($serviceMessage)): ?>
                    <div class="alert alert-warning"><?= app_h($serviceMessage) ?></div>
                <?php endif; ?>

                <form method="POST" class="row g-3 services-form" id="serviceForm">
                    <input type="hidden" name="action" value="save_service">

                    <div class="col-12">
                        <label class="form-label small text-muted">Nome do servico</label>
                        <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h((string) ($serviceFormValues['nome'] ?? '')) ?>" required title="Nome que aparecera na agenda e nos vinculos com profissionais.">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small text-muted">Duracao em minutos</label>
                        <input type="number" name="tempo_minutos" class="form-control" min="1" value="<?= (int) ($serviceFormValues['tempo_minutos'] ?? 50) ?>" required title="Tempo padrao usado para calcular o horario final dos atendimentos.">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check ps-1 pb-2">
                            <input class="form-check-input" type="checkbox" name="ativo" id="serviceActiveNew" <?= !isset($serviceFormValues['ativo']) || (int) $serviceFormValues['ativo'] === 1 ? 'checked' : '' ?> title="Servico ativo fica disponivel para novos agendamentos e vinculos.">
                            <label class="form-check-label" for="serviceActiveNew">Servico ativo</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <span class="services-hint">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</span>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
                            <button class="btn btn-primary px-4">Salvar servico <span class="small">(Alt+S)</span></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('keydown', function (event) {
    const modalElement = document.getElementById('serviceFormModal');
    const form = document.getElementById('serviceForm');

    if (!modalElement || !form || !modalElement.classList.contains('show')) return;

    if (event.altKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        form.requestSubmit();
    }
});
</script>

<?php if (!empty($autoOpenServiceModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('serviceFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') return;
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
