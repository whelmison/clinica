<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Editar Profissional</title>
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
.professional-page {
    flex: 1;
    min-height: 0;
    padding: 0.35rem 0.6rem 0.5rem;
    overflow: hidden;
}
.professional-layout {
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.professional-hero {
    margin-top: 0;
    padding: 0.8rem 0.95rem 0.75rem;
    border-radius: 20px;
}
.professional-hero h3 {
    margin: 0 0 0.16rem;
    font-size: 1.12rem;
}
.professional-hero p {
    font-size: 0.78rem;
    line-height: 1.25;
}
.professional-card-wrap {
    flex: 1;
    min-height: 0;
}
.professional-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    border-radius: 20px;
}
.professional-card .card-header {
    padding: 0.7rem 0.9rem 0.62rem;
}
.professional-card .card-body {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 0.75rem 0.9rem 0.85rem;
    gap: 0.55rem;
}
.professional-form {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}
.professional-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
}
.professional-tab-btn {
    min-height: 34px;
    border: 1px solid rgba(19, 74, 89, 0.12);
    border-radius: 12px;
    background: rgba(246, 250, 252, 0.95);
    color: #33515d;
    font-size: 0.74rem;
    font-weight: 700;
}
.professional-tab-btn.is-active {
    background: #0f4c5c;
    border-color: #0f4c5c;
    color: #fff;
}
.professional-tab-panels {
    flex: 1;
    min-height: 0;
    border: 1px solid rgba(19, 74, 89, 0.08);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(248, 251, 252, 0.98), rgba(255, 255, 255, 0.98));
    padding: 0.8rem;
    overflow: hidden;
}
.professional-tab-panel {
    display: none;
    height: 100%;
}
.professional-tab-panel.is-active {
    display: block;
}
.professional-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem 0.75rem;
}
.professional-grid .full {
    grid-column: 1 / -1;
}
.professional-field label,
.professional-tab-panel .form-check-label {
    display: block;
    margin-bottom: 0.18rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #55717e;
}
.professional-field .form-control {
    min-height: 36px;
    border-radius: 12px;
    font-size: 0.82rem;
}
.professional-option-list {
    display: grid;
    gap: 0.55rem;
}
.professional-option {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0;
    padding: 0.75rem 0.85rem;
    border: 1px solid rgba(19, 74, 89, 0.1);
    border-radius: 14px;
    background: rgba(247, 250, 251, 0.92);
}
.professional-option .form-check-input {
    margin: 0;
    flex: 0 0 auto;
}
.professional-option .form-check-label {
    margin: 0;
    font-size: 0.78rem;
    color: #234552;
}
.professional-services-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}
.professional-services-grid .checkbox-list {
    display: contents;
    max-height: none;
    overflow: visible;
}
.professional-services-grid .checkbox-card {
    padding: 0.72rem 0.8rem;
    border-radius: 14px;
}
.professional-services-grid .checkbox-card .form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}
.professional-services-grid .checkbox-card .form-check-input {
    margin: 0;
}
.professional-services-grid .checkbox-card .small {
    margin-top: 0.2rem !important;
    font-size: 0.68rem;
}
.professional-services-grid .checkbox-card .form-control {
    min-height: 34px;
    margin-top: 0.35rem !important;
    border-radius: 11px;
    font-size: 0.8rem;
}
.professional-actions {
    display: flex;
    justify-content: space-between;
    gap: 0.45rem;
    padding-top: 0.15rem;
}
.professional-actions .btn {
    min-height: 36px;
    border-radius: 12px;
    font-size: 0.82rem;
}
@media (max-width: 991px) {
    html,
    body {
        overflow: auto;
    }
    .professional-page {
        overflow: visible;
        padding: 0.55rem 0.4rem 1rem;
    }
    .professional-layout,
    .professional-card,
    .professional-form,
    .professional-tab-panels,
    .professional-tab-panel {
        height: auto;
    }
    .professional-tabs,
    .professional-grid,
    .professional-services-grid {
        grid-template-columns: 1fr;
    }
    .professional-actions {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid professional-page">
    <div class="professional-layout">
        <section class="page-hero professional-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
                <div>
                    <h3>Editar Profissional</h3>
                    <p>Atualize os dados, permissoes e servicos vinculados deste profissional.</p>
                </div>
                <a href="administrativo_profissionais.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar a Lista</a>
            </div>
        </section>

        <div class="row justify-content-center professional-card-wrap">
            <div class="col-12 col-xl-10">
                <div class="soft-card card professional-card">
                    <div class="card-header">
                        <div class="panel-title">
                            <h5>Dados do profissional</h5>
                            <span class="selection-chip"><?= app_h($selectedProfessional['nome']) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="professional-form">
                            <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessional['id'] ?>">

                            <div class="professional-tabs" role="tablist" aria-label="Edicao do profissional">
                                <button type="button" class="professional-tab-btn is-active" data-tab-target="dados">Dados</button>
                                <button type="button" class="professional-tab-btn" data-tab-target="permissoes">Permissoes</button>
                                <button type="button" class="professional-tab-btn" data-tab-target="servicos">Servicos</button>
                            </div>

                            <div class="professional-tab-panels">
                                <section class="professional-tab-panel is-active" data-tab-panel="dados">
                                    <div class="professional-grid">
                                        <div class="professional-field">
                                            <label>Nome</label>
                                            <input type="text" name="nome" class="form-control" value="<?= app_h((string) ($selectedProfessional['nome'] ?? '')) ?>" required>
                                        </div>
                                        <div class="professional-field">
                                            <label>Profissao</label>
                                            <input type="text" name="profissao" class="form-control" value="<?= app_h((string) ($selectedProfessional['profissao'] ?? '')) ?>">
                                        </div>
                                        <div class="professional-field">
                                            <label>Telefone</label>
                                            <input type="text" name="telefone" class="form-control" value="<?= app_h((string) ($selectedProfessional['telefone'] ?? '')) ?>">
                                        </div>
                                        <div class="professional-field">
                                            <label>Endereco</label>
                                            <input type="text" name="endereco" class="form-control" value="<?= app_h((string) ($selectedProfessional['endereco'] ?? '')) ?>">
                                        </div>
                                    </div>
                                </section>

                                <section class="professional-tab-panel" data-tab-panel="permissoes">
                                    <div class="professional-option-list">
                                        <label class="professional-option">
                                            <input class="form-check-input" type="checkbox" name="permite_editar_guias" id="allowGuideEditing" <?= !empty($selectedProfessional['permite_editar_guias']) ? 'checked' : '' ?>>
                                            <span class="form-check-label">Permitir edicao de guias para este profissional</span>
                                        </label>
                                        <label class="professional-option">
                                            <input class="form-check-input" type="checkbox" name="permite_secretaria_liberar_agenda" id="allowSecretaryAvailability" <?= !empty($selectedProfessional['permite_secretaria_liberar_agenda']) ? 'checked' : '' ?>>
                                            <span class="form-check-label">Permitir que a secretaria libere a agenda deste profissional</span>
                                        </label>
                                    </div>
                                </section>

                                <section class="professional-tab-panel" data-tab-panel="servicos">
                                    <div class="professional-services-grid">
                                        <div class="checkbox-list">
                                            <?php foreach ($services as $service): ?>
                                                <?php $checked = array_key_exists((int) $service['id'], $selectedProfessionalServiceMap); ?>
                                                <label class="checkbox-card<?= $checked ? ' is-selected' : '' ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="servicos[]" value="<?= (int) $service['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                                        <span class="fw-semibold"><?= app_h($service['nome']) ?></span>
                                                    </div>
                                                    <div class="small text-muted">Duracao personalizada para este profissional</div>
                                                    <input type="number" name="duracoes[<?= (int) $service['id'] ?>]" class="form-control" min="1" value="<?= (int) ($selectedProfessionalServiceMap[(int) $service['id']] ?? $service['tempo_minutos']) ?>">
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="professional-actions">
                                <button type="submit" name="action" value="delete_professional" class="btn btn-outline-danger" onclick="return confirm('Excluir este profissional?')">Excluir</button>
                                <button class="btn btn-primary px-4" name="action" value="save_professional">Salvar Alteracoes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.checkbox-card input[type="checkbox"]').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
        checkbox.closest('.checkbox-card').classList.toggle('is-selected', checkbox.checked);
    });
});

const professionalTabButtons = document.querySelectorAll('[data-tab-target]');
const professionalTabPanels = document.querySelectorAll('[data-tab-panel]');

professionalTabButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const target = button.dataset.tabTarget;

        professionalTabButtons.forEach((item) => {
            item.classList.toggle('is-active', item === button);
        });

        professionalTabPanels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.tabPanel === target);
        });
    });
});
</script>

</body>
</html>
