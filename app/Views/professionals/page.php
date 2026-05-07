<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Profissionais</title>
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

.professionals-shell {
    flex: 1;
    min-height: 0;
    padding-top: 0.35rem;
    padding-bottom: 0.85rem;
}

.professionals-shell .page-hero {
    margin-top: 0.35rem;
    padding: 0.96rem 1.05rem 0.9rem;
    border-radius: 20px;
}

.professionals-shell .page-hero h3 {
    font-size: 1.08rem;
}

.professionals-shell .page-hero p {
    font-size: 0.76rem;
    line-height: 1.18;
}

.professionals-shell .page-hero .btn {
    min-height: 38px;
    font-size: 0.78rem;
}

.professionals-shell .soft-card {
    border-radius: 18px;
}

.professionals-shell .soft-card .card-header {
    padding: 0.68rem 0.82rem 0.58rem;
}

.professionals-shell .soft-card .card-body {
    padding: 0.78rem 0.82rem;
}

.professionals-shell .toolbar-grid {
    gap: 0.7rem;
}

.professionals-shell .toolbar-grid .form-control,
.professionals-shell .toolbar-grid .btn {
    min-height: 40px;
    font-size: 0.8rem;
}

.professional-table {
    font-size: 0.76rem;
}

.professional-table thead th {
    padding: 0.34rem 0.45rem;
    font-size: 0.62rem;
    line-height: 1.08;
}

.professional-table tbody td {
    padding: 0.34rem 0.45rem;
    line-height: 1.12;
}

.professional-table tbody tr.is-active {
    background: rgba(225, 244, 245, 0.7);
}

.professional-table .professional-name {
    font-weight: 700;
    color: #16333f;
    font-size: 0.84rem;
    line-height: 1.08;
}

.professional-table .small {
    margin-top: 0.08rem;
    line-height: 1.08;
}

.professional-table .btn {
    min-height: 28px;
    padding-top: 0.22rem;
    padding-bottom: 0.22rem;
    font-size: 0.68rem;
    line-height: 1;
}

.professionals-modal .modal-dialog {
    max-width: 1120px;
}

.professionals-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}

.professionals-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.professionals-modal .modal-body {
    padding: 0.95rem 1rem 1rem;
}

.professionals-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.professionals-modal .btn-close {
    box-shadow: none;
}

.professional-form {
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
    border: 1px solid rgba(19, 74, 89, 0.08);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(248, 251, 252, 0.98), rgba(255, 255, 255, 0.98));
    padding: 0.8rem;
}

.professional-tab-panel {
    display: none;
}

.professional-tab-panel.is-active {
    display: block;
}

.professional-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem 0.75rem;
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
    min-height: 40px;
    border-radius: 12px;
    border-color: #dbe7ec;
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
}

.professional-services-grid .checkbox-card {
    padding: 0.72rem 0.8rem;
    border: 1px solid rgba(19, 74, 89, 0.1);
    border-radius: 14px;
    background: rgba(247, 250, 251, 0.92);
}

.professional-services-grid .checkbox-card.is-selected {
    border-color: rgba(31, 122, 140, 0.42);
    background: rgba(231, 246, 248, 0.95);
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
    min-height: 36px;
    margin-top: 0.35rem !important;
    border-radius: 11px;
    border-color: #dbe7ec;
    font-size: 0.8rem;
}

.professional-actions {
    display: flex;
    justify-content: space-between;
    gap: 0.45rem;
    padding-top: 0.15rem;
}

.professional-actions .btn {
    min-height: 40px;
    border-radius: 12px;
    font-size: 0.82rem;
}

.professionals-hint {
    color: #68828f;
    font-size: 0.72rem;
}

@media (max-width: 991px) {
    body {
        display: block;
    }

    .professionals-shell {
        min-height: auto;
        padding-bottom: 1rem;
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

<?php
$isEditingProfessional = (int) ($professionalFormValues['professional_id'] ?? 0) > 0;
$activeProfessionalId = (int) ($professionalFormValues['professional_id'] ?? 0);
?>

<div class="container page-shell professionals-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Profissionais</h3>
                <p>Lista de profissionais cadastrados. Gerencie o vinculo de servicos e permissoes.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="administrativo_usuarios.php" class="btn btn-light btn-sm rounded-pill px-3">Gerenciar usuarios</a>
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#professionalFormModal">+ Novo profissional</button>
            </div>
        </div>
    </section>

    <div class="soft-card card mt-3">
        <div class="card-header">
            <div class="panel-title">
                <h5>Profissionais cadastrados</h5>
                <span class="text-muted small"><?= $professionalsPagination['total'] ?> registro(s)</span>
            </div>
        </div>
        <div class="card-body">
            <form class="toolbar-grid mb-3" method="GET">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca_profissional" class="form-control" value="<?= app_h($professionalFilters['busca_profissional']) ?>" placeholder="Nome, profissao ou telefone">
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="administrativo_profissionais.php" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-soft align-middle mb-0 professional-table">
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Contato</th>
                        <th>Permissoes</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($professionalsRows as $professional): ?>
                        <?php $isActive = $activeProfessionalId > 0 && $activeProfessionalId === (int) $professional['id']; ?>
                        <tr class="<?= $isActive ? 'is-active' : '' ?>">
                            <td>
                                <div class="professional-name"><?= app_h($professional['nome']) ?></div>
                                <div class="small text-muted"><?= app_h($professional['profissao'] ?: 'Profissao nao informada') ?></div>
                            </td>
                            <td>
                                <?= app_h($professional['telefone'] ?: '-') ?>
                                <div class="small text-muted"><?= app_h($professional['endereco'] ?: 'Endereco nao informado') ?></div>
                            </td>
                            <td>
                                <div class="small"><?= !empty($professional['permite_editar_guias']) ? 'Pode editar guias' : 'Somente leitura nas guias' ?></div>
                                <div class="small"><?= !empty($professional['permite_secretaria_liberar_agenda']) ? 'Secretaria pode liberar agenda' : 'Agenda liberada pelo profissional' ?></div>
                                <div class="small text-muted"><?= app_h($professional['servicos'] ?: 'Sem servicos') ?></div>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <a class="btn btn-sm btn-outline-primary" href="administrativo_profissionais.php?<?= app_h(app_build_query(['professional_id' => $professional['id'], 'busca_profissional' => $professionalFilters['busca_profissional'], 'professional_page' => $professionalsPagination['page'] > 1 ? $professionalsPagination['page'] : null])) ?>">Editar</a>
                                    <form method="POST" onsubmit="return confirm('Excluir este profissional?')">
                                        <input type="hidden" name="action" value="delete_professional">
                                        <input type="hidden" name="professional_id" value="<?= (int) $professional['id'] ?>">
                                        <input type="hidden" name="busca_profissional" value="<?= app_h($professionalFilters['busca_profissional']) ?>">
                                        <input type="hidden" name="professional_page" value="<?= (int) $professionalsPagination['page'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($professionalsRows === []): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                Nenhum profissional encontrado para os filtros informados.
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#professionalFormModal">Cadastrar primeiro profissional</button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= app_render_pagination($professionalsPagination) ?>
        </div>
    </div>
</div>

<div class="modal fade professionals-modal" id="professionalFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1"><?= $isEditingProfessional ? 'Editar profissional' : 'Novo profissional' ?></h5>
                    <div class="small text-muted">
                        <?= $isEditingProfessional ? 'Atualize os dados, permissoes e servicos sem sair da lista.' : 'Cadastro completo de profissional em popup.' ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="professional-form">
                    <?php if ($isEditingProfessional): ?>
                        <input type="hidden" name="professional_id" value="<?= (int) $professionalFormValues['professional_id'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="busca_profissional" value="<?= app_h($professionalFilters['busca_profissional']) ?>">
                    <input type="hidden" name="professional_page" value="<?= (int) $professionalsPagination['page'] ?>">

                    <div class="professional-tabs" role="tablist" aria-label="Cadastro do profissional">
                        <button type="button" class="professional-tab-btn is-active" data-tab-target="dados">Dados</button>
                        <button type="button" class="professional-tab-btn" data-tab-target="permissoes">Permissoes</button>
                        <button type="button" class="professional-tab-btn" data-tab-target="servicos">Servicos</button>
                    </div>

                    <div class="professional-tab-panels">
                        <section class="professional-tab-panel is-active" data-tab-panel="dados">
                            <div class="professional-grid">
                                <div class="professional-field">
                                    <label>Nome</label>
                                    <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h((string) $professionalFormValues['nome']) ?>" required>
                                </div>
                                <div class="professional-field">
                                    <label>Profissao</label>
                                    <input type="text" name="profissao" class="form-control" value="<?= app_h((string) $professionalFormValues['profissao']) ?>">
                                </div>
                                <div class="professional-field">
                                    <label>Telefone</label>
                                    <input type="text" name="telefone" class="form-control" value="<?= app_h((string) $professionalFormValues['telefone']) ?>">
                                </div>
                                <div class="professional-field">
                                    <label>Endereco</label>
                                    <input type="text" name="endereco" class="form-control" value="<?= app_h((string) $professionalFormValues['endereco']) ?>">
                                </div>
                            </div>
                        </section>

                        <section class="professional-tab-panel" data-tab-panel="permissoes">
                            <div class="professional-option-list">
                                <label class="professional-option">
                                    <input class="form-check-input" type="checkbox" name="permite_editar_guias" <?= !empty($professionalFormValues['permite_editar_guias']) ? 'checked' : '' ?>>
                                    <span class="form-check-label">Permitir edicao de guias para este profissional</span>
                                </label>
                                <label class="professional-option">
                                    <input class="form-check-input" type="checkbox" name="permite_secretaria_liberar_agenda" <?= !empty($professionalFormValues['permite_secretaria_liberar_agenda']) ? 'checked' : '' ?>>
                                    <span class="form-check-label">Permitir que a secretaria libere a agenda deste profissional</span>
                                </label>
                            </div>
                        </section>

                        <section class="professional-tab-panel" data-tab-panel="servicos">
                            <div class="professional-services-grid">
                                <div class="checkbox-list">
                                    <?php foreach ($services as $service): ?>
                                        <?php
                                            $serviceId = (int) $service['id'];
                                            $checked = in_array($serviceId, $professionalFormValues['servicos'], true);
                                            $durationValue = (int) ($professionalFormValues['duracoes'][$serviceId] ?? $service['tempo_minutos']);
                                        ?>
                                        <label class="checkbox-card<?= $checked ? ' is-selected' : '' ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="servicos[]" value="<?= $serviceId ?>" <?= $checked ? 'checked' : '' ?>>
                                                <span class="fw-semibold"><?= app_h($service['nome']) ?></span>
                                            </div>
                                            <div class="small text-muted">Duracao personalizada para este profissional</div>
                                            <input type="number" name="duracoes[<?= $serviceId ?>]" class="form-control" min="1" value="<?= $durationValue ?>">
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="professional-actions">
                        <span class="professionals-hint">Vincule apenas os servicos que este profissional realmente atende.</span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($isEditingProfessional): ?>
                                <button type="submit" name="action" value="delete_professional" class="btn btn-outline-danger" onclick="return confirm('Excluir este profissional?')">Excluir</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary px-4" name="action" value="save_professional"><?= $isEditingProfessional ? 'Salvar alteracoes' : 'Salvar profissional' ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function bindProfessionalModalState(root = document) {
    root.querySelectorAll('.checkbox-card input[type="checkbox"]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            checkbox.closest('.checkbox-card')?.classList.toggle('is-selected', checkbox.checked);
        });
    });

    const tabButtons = root.querySelectorAll('[data-tab-target]');
    const tabPanels = root.querySelectorAll('[data-tab-panel]');

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.tabTarget;

            tabButtons.forEach((item) => {
                item.classList.toggle('is-active', item === button);
            });

            tabPanels.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.tabPanel === target);
            });
        });
    });
}

bindProfessionalModalState();
</script>

<?php if (!empty($autoOpenProfessionalModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('professionalFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
