<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $selectedService ? 'Editar Servico' : 'Novo Servico' ?></title>
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
.service-form-shell {
    flex: 1;
    min-height: 0;
    padding-top: 0.2rem;
    padding-bottom: 0.35rem !important;
}
.service-form-layout {
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.service-form-hero {
    margin-top: 0.2rem;
    padding: 0.78rem 0.92rem 0.74rem;
}
.service-form-hero h3 {
    font-size: 1.02rem;
}
.service-form-hero p {
    font-size: 0.72rem;
}
.service-form-card {
    border-radius: 18px;
}
.service-form-card .card-header,
.service-form-card .card-body {
    padding: 0.78rem 0.88rem;
}
@media (max-width: 991px) {
    html,
    body {
        overflow: auto;
    }
    .service-form-shell,
    .service-form-layout {
        height: auto;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell service-form-shell">
    <div class="service-form-layout">
    <section class="page-hero service-form-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2"><?= $selectedService ? 'Editar servico' : 'Novo servico' ?></h3>
                <p><?= $selectedService ? 'Atualize nome, duracao e status do servico.' : 'Cadastre um novo servico para uso na agenda e nos vinculos profissionais.' ?></p>
            </div>
            <a href="secretaria_servicos.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar a Lista</a>
        </div>
    </section>

    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8">
            <div class="soft-card card service-form-card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5><?= $selectedService ? 'Dados do servico' : 'Cadastro do servico' ?></h5>
                        <?php if ($selectedService): ?>
                            <span class="selection-chip"><?= app_h((string) $selectedService['nome']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <?php if ($selectedService): ?>
                            <input type="hidden" name="service_id" value="<?= (int) $selectedService['id'] ?>">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Nome do servico</label>
                            <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h((string) ($selectedService['nome'] ?? '')) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Duracao em minutos</label>
                            <input type="number" name="tempo_minutos" class="form-control" min="1" value="<?= (int) ($selectedService['tempo_minutos'] ?? 50) ?>" required>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check ps-1 pb-2">
                                <input class="form-check-input" type="checkbox" name="ativo" id="serviceActive" <?= !isset($selectedService['ativo']) || (int) $selectedService['ativo'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="serviceActive">Servico ativo</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-between gap-2 pt-2">
                            <?php if ($selectedService): ?>
                                <button type="submit" name="action" value="delete_service" class="btn btn-outline-danger" onclick="return confirm('Excluir este servico?')">Excluir</button>
                            <?php else: ?>
                                <span class="text-muted small align-self-center">Servicos inativos saem dos novos vinculos, mas preservam o historico.</span>
                            <?php endif; ?>

                            <div class="d-flex gap-2">
                                <a href="secretaria_servicos.php" class="btn btn-outline-secondary">Cancelar</a>
                                <button class="btn btn-primary px-4" name="action" value="save_service"><?= $selectedService ? 'Salvar Alteracoes' : 'Salvar' ?></button>
                            </div>
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
