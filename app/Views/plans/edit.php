<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Editar Plano</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
html,
body {
    height: 100%;
}

.plan-edit-shell {
    padding: 0.28rem 0.6rem 0.85rem;
}

.plan-edit-layout {
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
}

.plan-edit-hero {
    margin-top: 0;
    padding: 0.82rem 0.96rem 0.76rem;
}

.plan-edit-hero h3 {
    font-size: 1.08rem;
}

.plan-edit-hero p {
    font-size: 0.76rem;
}

.plan-edit-card {
    border-radius: 18px;
}

.plan-edit-card .card-header {
    padding: 0.72rem 0.88rem 0.64rem;
}

.plan-edit-card .card-body {
    padding: 0.88rem;
}

.plan-edit-form .form-control {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.plan-edit-actions .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid plan-edit-shell">
    <div class="plan-edit-layout">
        <section class="page-hero plan-edit-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
                <div>
                    <h3 class="mb-2">Editar plano</h3>
                    <p>Ajuste o nome do plano usado para vincular os precos dentro do cadastro de servicos.</p>
                </div>
                <a href="planos.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar a lista</a>
            </div>
        </section>

        <div class="row justify-content-center">
            <div class="col-12 col-xl-8 col-xxl-6">
                <div class="soft-card card plan-edit-card">
                    <div class="card-header">
                        <div class="panel-title">
                            <h5>Dados do plano</h5>
                            <span class="text-muted small">Precos nos servicos</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="plan-edit-form">
                            <input type="hidden" name="action" value="save_plan">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small text-muted">Nome do plano</label>
                                    <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h($formValues['nome']) ?>" required>
                                </div>
                            </div>
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mt-4 plan-edit-actions">
                                <a href="planos.php" class="btn btn-outline-secondary">Cancelar</a>
                                <button class="btn btn-primary px-4">Salvar alteracoes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
