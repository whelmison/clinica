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

.professional-autocomplete-wrap {
    position: relative;
}

.professional-autocomplete-menu {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    left: 0;
    z-index: 1050;
    display: none;
    max-height: 260px;
    overflow: auto;
    padding: 0.28rem;
    border: 1px solid rgba(18, 73, 88, 0.14);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 14px 32px rgba(22, 51, 63, 0.16);
}

.professional-autocomplete-menu.is-open {
    display: grid;
    gap: 0.18rem;
}

.professional-autocomplete-option {
    width: 100%;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #1d3945;
    text-align: left;
    padding: 0.42rem 0.48rem;
}

.professional-autocomplete-option:hover,
.professional-autocomplete-option:focus,
.professional-autocomplete-option.is-active {
    background: rgba(15, 92, 74, 0.1);
    outline: none;
}

.professional-autocomplete-name {
    display: block;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.15;
}

.professional-autocomplete-meta {
    display: block;
    margin-top: 0.12rem;
    color: #68828f;
    font-size: 0.68rem;
    line-height: 1.15;
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
    min-height: 40px;
    border-radius: 12px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.professional-photo-field {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.professional-photo-preview {
    width: 82px;
    height: 82px;
    flex: 0 0 82px;
    border: 1px solid #dbe7ec;
    border-radius: 50%;
    background: #f7fbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #68828f;
    font-size: 0.68rem;
    line-height: 1.1;
    text-align: center;
}

.professional-photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.professional-photo-field .form-control {
    min-width: min(100%, 260px);
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

.professional-service-picker {
    display: grid;
    grid-template-columns: minmax(190px, 1.2fr) minmax(120px, 0.65fr) minmax(115px, 0.55fr) minmax(110px, 0.5fr) minmax(105px, 0.5fr) auto;
    gap: 0.5rem;
    align-items: end;
    padding: 0.72rem;
    border: 1px solid rgba(19, 74, 89, 0.1);
    border-radius: 14px;
    background: rgba(247, 250, 251, 0.92);
}

.professional-service-picker label,
.professional-service-table th {
    margin-bottom: 0.16rem;
    color: #55717e;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.professional-service-picker .form-control,
.professional-service-picker .form-select {
    min-height: 38px;
    border-radius: 11px;
    border-color: #dbe7ec;
    font-size: 0.78rem;
}

.professional-service-list {
    margin-top: 0.65rem;
    overflow: hidden;
    border: 1px solid rgba(19, 74, 89, 0.08);
    border-radius: 14px;
    background: #fff;
}

.professional-service-table {
    margin: 0;
    font-size: 0.76rem;
}

.professional-service-table th,
.professional-service-table td {
    vertical-align: middle;
}

.professional-service-table .form-control,
.professional-service-table .form-select {
    min-height: 34px;
    border-radius: 10px;
    border-color: #dbe7ec;
    font-size: 0.76rem;
}

.professional-service-empty {
    padding: 0.85rem;
    color: #68828f;
    font-size: 0.78rem;
    text-align: center;
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
    .professional-service-picker {
        grid-template-columns: 1fr;
    }

    .professional-actions {
        flex-direction: column;
    }
}
<?= app_report_print_header_css() ?>
</style>
</head>
<body class="app-print-page">

<?php include 'partials/menu.php'; ?>

<?php
$isEditingProfessional = (int) ($professionalFormValues['professional_id'] ?? 0) > 0;
$activeProfessionalId = (int) ($professionalFormValues['professional_id'] ?? 0);
$professionalModalTab = $professionalModalTab ?? 'dados';
?>

<div class="container page-shell professionals-shell">
    <section class="page-hero app-print-hide">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Profissionais</h3>
                <p>Lista de profissionais cadastrados. Gerencie o vinculo de servicos e permissoes.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" data-export-list onclick="appExportList('professionalsExportArea', 'jpg', 'profissionais')">Exportar JPG</button>
                <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="window.print()">Imprimir relatorio</button>
                <a href="administrativo_usuarios.php" class="btn btn-light btn-sm rounded-pill px-3">Gerenciar usuarios</a>
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#professionalFormModal">+ Novo profissional</button>
            </div>
        </div>
    </section>

    <div class="soft-card card mt-3 app-print-report-area" id="professionalsExportArea">
        <div class="card-header app-print-hide">
            <div class="panel-title">
                <h5>Profissionais cadastrados</h5>
                <span class="text-muted small"><?= $professionalsPagination['total'] ?> registro(s)</span>
            </div>
        </div>
        <div class="card-body">
            <?= app_report_print_header($conn, 'Profissionais cadastrados', [
                trim((string) ($professionalFilters['busca_profissional'] ?? '')) !== ''
                    ? 'Busca: ' . trim((string) $professionalFilters['busca_profissional'])
                    : 'Todos os profissionais',
            ], [
                'Registros: ' . (int) $professionalsPagination['total'],
            ]) ?>
            <form class="toolbar-grid mb-3 app-print-hide" method="GET" id="professionalFilterForm">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="hidden" name="profissional_id" id="professionalFilterId" value="<?= (int) ($professionalFilters['profissional_id'] ?? 0) > 0 ? (int) $professionalFilters['profissional_id'] : '' ?>">
                    <div class="professional-autocomplete-wrap">
                        <input type="text" name="busca_profissional" id="professionalFilterBusca" class="form-control" value="<?= app_h($professionalFilters['busca_profissional']) ?>" placeholder="Nome, profissao ou telefone" autocomplete="off">
                        <div class="professional-autocomplete-menu" id="professionalFilterMenu"></div>
                    </div>
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
                        <th class="text-end app-print-actions">Acoes</th>
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
                                <div class="small"><?= !empty($professional['permite_editar_guias']) ? 'Pode editar guias pendentes' : 'Somente leitura nas guias' ?></div>
                                <div class="small"><?= !empty($professional['permite_secretaria_liberar_agenda']) ? 'Secretaria pode liberar agenda' : 'Agenda liberada pelo profissional' ?></div>
                                <div class="small text-muted"><?= app_h($professional['servicos'] ?: 'Sem servicos') ?></div>
                            </td>
                            <td class="text-end app-print-actions">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <a class="btn btn-sm btn-outline-primary" href="administrativo_profissionais.php?<?= app_h(app_build_query(['professional_id' => $professional['id'], 'busca_profissional' => $professionalFilters['busca_profissional'], 'profissional_id' => (int) ($professionalFilters['profissional_id'] ?? 0) > 0 ? (int) $professionalFilters['profissional_id'] : null, 'professional_page' => $professionalsPagination['page'] > 1 ? $professionalsPagination['page'] : null])) ?>">Editar</a>
                                    <form method="POST" onsubmit="return confirm('Excluir este profissional?')">
                                        <input type="hidden" name="action" value="delete_professional">
                                        <input type="hidden" name="professional_id" value="<?= (int) $professional['id'] ?>">
                                        <input type="hidden" name="busca_profissional" value="<?= app_h($professionalFilters['busca_profissional']) ?>">
                                        <input type="hidden" name="profissional_id" value="<?= (int) ($professionalFilters['profissional_id'] ?? 0) > 0 ? (int) $professionalFilters['profissional_id'] : '' ?>">
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
                <form method="POST" enctype="multipart/form-data" class="professional-form">
                    <?php if ($isEditingProfessional): ?>
                        <input type="hidden" name="professional_id" value="<?= (int) $professionalFormValues['professional_id'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="busca_profissional" value="<?= app_h($professionalFilters['busca_profissional']) ?>">
                    <input type="hidden" name="profissional_id" value="<?= (int) ($professionalFilters['profissional_id'] ?? 0) > 0 ? (int) $professionalFilters['profissional_id'] : '' ?>">
                    <input type="hidden" name="professional_page" value="<?= (int) $professionalsPagination['page'] ?>">

                    <div class="professional-tabs" role="tablist" aria-label="Cadastro do profissional">
                        <button type="button" class="professional-tab-btn <?= $professionalModalTab === 'dados' ? 'is-active' : '' ?>" data-tab-target="dados">Dados</button>
                        <button type="button" class="professional-tab-btn <?= $professionalModalTab === 'permissoes' ? 'is-active' : '' ?>" data-tab-target="permissoes">Permissoes</button>
                        <button type="button" class="professional-tab-btn <?= $professionalModalTab === 'servicos' ? 'is-active' : '' ?>" data-tab-target="servicos">Servicos</button>
                    </div>

                    <div class="professional-tab-panels">
                        <section class="professional-tab-panel <?= $professionalModalTab === 'dados' ? 'is-active' : '' ?>" data-tab-panel="dados">
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
                                <div class="professional-field full">
                                    <?php
                                        $currentProfessionalPhoto = ltrim(str_replace('\\', '/', trim((string) ($professionalFormValues['foto'] ?? ''))), '/');
                                        $currentProfessionalPhotoExists = $currentProfessionalPhoto !== ''
                                            && !str_contains($currentProfessionalPhoto, '..')
                                            && is_file(__DIR__ . '/../../../' . str_replace('/', DIRECTORY_SEPARATOR, $currentProfessionalPhoto));
                                    ?>
                                    <label>Foto do profissional</label>
                                    <div class="professional-photo-field">
                                        <div class="professional-photo-preview">
                                            <?php if ($currentProfessionalPhotoExists): ?>
                                                <img src="<?= app_h($currentProfessionalPhoto) ?>" alt="Foto atual do profissional">
                                            <?php else: ?>
                                                Sem foto
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" name="foto" class="form-control" accept="image/png,image/jpeg,image/webp">
                                            <div class="form-text">Use JPG, PNG ou WEBP com ate 3 MB.</div>
                                            <?php if ($currentProfessionalPhoto !== ''): ?>
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" name="remover_foto" id="removerFotoProfissional" value="1">
                                                    <label class="form-check-label" for="removerFotoProfissional">Remover foto atual</label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="professional-tab-panel <?= $professionalModalTab === 'permissoes' ? 'is-active' : '' ?>" data-tab-panel="permissoes">
                            <div class="professional-option-list">
                                <label class="professional-option">
                                    <input class="form-check-input" type="checkbox" name="permite_editar_guias" <?= !empty($professionalFormValues['permite_editar_guias']) ? 'checked' : '' ?>>
                                    <span class="form-check-label">Permitir edicao de guias pendentes deste profissional</span>
                                </label>
                                <label class="professional-option">
                                    <input class="form-check-input" type="checkbox" name="permite_secretaria_liberar_agenda" <?= !empty($professionalFormValues['permite_secretaria_liberar_agenda']) ? 'checked' : '' ?>>
                                    <span class="form-check-label">Permitir que a secretaria libere a agenda deste profissional</span>
                                </label>
                            </div>
                        </section>

                        <section class="professional-tab-panel <?= $professionalModalTab === 'servicos' ? 'is-active' : '' ?>" data-tab-panel="servicos">
                            <div class="professional-service-picker">
                                <div>
                                    <label for="professionalServiceSelect">Servico</label>
                                    <select class="form-select" id="professionalServiceSelect">
                                        <option value="">Selecione um servico</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?= (int) $service['id'] ?>"><?= app_h((string) $service['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="professionalServiceChargeType">Cobranca</label>
                                    <select class="form-select" id="professionalServiceChargeType">
                                        <option value="percentual">% do servico</option>
                                        <option value="valor">Valor fixo</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="professionalServiceChargeValue">Valor/%</label>
                                    <input type="text" class="form-control" id="professionalServiceChargeValue" placeholder="0,00">
                                </div>
                                <div>
                                    <label for="professionalServiceDuration">Duracao</label>
                                    <input type="number" class="form-control" id="professionalServiceDuration" min="1" value="50">
                                </div>
                                <div>
                                    <label for="professionalServiceTaxPercent">Imposto %</label>
                                    <input type="text" class="form-control" id="professionalServiceTaxPercent" placeholder="0,00">
                                </div>
                                <div>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" id="professionalServiceTaxEnabled">
                                        <label class="form-check-label" for="professionalServiceTaxEnabled">Paga imposto</label>
                                    </div>
                                    <button type="button" class="btn btn-primary w-100" id="professionalServiceAdd">Adicionar</button>
                                </div>
                            </div>

                            <div class="professional-service-list">
                                <div class="table-responsive">
                                    <table class="table professional-service-table align-middle">
                                        <thead>
                                        <tr>
                                            <th>Servico prestado</th>
                                            <th>Duracao</th>
                                            <th>Forma de cobrar</th>
                                            <th>Valor/%</th>
                                            <th>Imposto</th>
                                            <th class="text-end">Acoes</th>
                                        </tr>
                                        </thead>
                                        <tbody id="professionalServiceRows">
                                        <?php foreach ($professionalFormValues['servicos'] as $serviceId): ?>
                                            <?php
                                                $serviceId = (int) $serviceId;
                                                $serviceName = '';
                                                foreach ($services as $serviceOption) {
                                                    if ((int) $serviceOption['id'] === $serviceId) {
                                                        $serviceName = (string) $serviceOption['nome'];
                                                        break;
                                                    }
                                                }
                                                if ($serviceName === '') {
                                                    continue;
                                                }
                                                $durationValue = (int) ($professionalFormValues['duracoes'][$serviceId] ?? 50);
                                                $chargeType = (string) ($professionalFormValues['cobranca_tipo'][$serviceId] ?? 'percentual');
                                                $chargeValue = (string) ($professionalFormValues['cobranca_valor'][$serviceId] ?? '0');
                                                $taxEnabled = !empty($professionalFormValues['cobra_imposto'][$serviceId]);
                                                $taxPercent = (string) ($professionalFormValues['imposto_percentual'][$serviceId] ?? '0');
                                            ?>
                                            <tr data-service-id="<?= $serviceId ?>">
                                                <td>
                                                    <strong><?= app_h($serviceName) ?></strong>
                                                    <input type="hidden" name="servicos[]" value="<?= $serviceId ?>">
                                                </td>
                                                <td><input type="number" name="duracoes[<?= $serviceId ?>]" class="form-control" min="1" value="<?= $durationValue ?>"></td>
                                                <td>
                                                    <select name="cobranca_tipo[<?= $serviceId ?>]" class="form-select" data-charge-type>
                                                        <option value="percentual" <?= $chargeType === 'percentual' ? 'selected' : '' ?>>% do servico</option>
                                                        <option value="valor" <?= $chargeType === 'valor' ? 'selected' : '' ?>>Valor fixo</option>
                                                    </select>
                                                </td>
                                                <td><input type="text" name="cobranca_valor[<?= $serviceId ?>]" class="form-control" value="<?= app_h($chargeValue) ?>" placeholder="0,00"></td>
                                                <td>
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <input class="form-check-input m-0" type="checkbox" name="cobra_imposto[<?= $serviceId ?>]" value="1" <?= $taxEnabled ? 'checked' : '' ?> data-tax-toggle>
                                                        <input type="text" name="imposto_percentual[<?= $serviceId ?>]" class="form-control" value="<?= app_h($taxPercent) ?>" placeholder="0,00" <?= $taxEnabled ? '' : 'disabled' ?> data-tax-percent>
                                                    </div>
                                                </td>
                                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-service>Remover</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="professional-service-empty" id="professionalServiceEmpty">Nenhum servico vinculado ao profissional.</div>
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

<script src="assets/list-export.js"></script>
<script>
const professionalFilterForm = document.getElementById('professionalFilterForm');
const professionalFilterInput = document.getElementById('professionalFilterBusca');
const professionalFilterMenu = document.getElementById('professionalFilterMenu');
const professionalFilterId = document.getElementById('professionalFilterId');
const professionalServiceCatalog = <?= json_encode(array_map(static fn (array $service): array => [
    'id' => (int) $service['id'],
    'nome' => (string) $service['nome'],
    'tempo_minutos' => (int) $service['tempo_minutos'],
], $services), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function closeProfessionalFilterMenu() {
    if (!professionalFilterMenu) {
        return;
    }

    professionalFilterMenu.classList.remove('is-open');
    professionalFilterMenu.innerHTML = '';
}

function submitProfessionalFilter() {
    if (!professionalFilterForm) {
        return;
    }

    if (typeof professionalFilterForm.requestSubmit === 'function') {
        professionalFilterForm.requestSubmit();
        return;
    }

    professionalFilterForm.submit();
}

function setupProfessionalFilterAutocomplete() {
    if (!professionalFilterForm || !professionalFilterInput || !professionalFilterMenu || !professionalFilterId) {
        return;
    }

    let timer = null;
    let controller = null;
    let results = [];
    let activeIndex = -1;

    function setActiveOption(nextIndex) {
        const options = Array.from(professionalFilterMenu.querySelectorAll('.professional-autocomplete-option'));

        if (!options.length) {
            activeIndex = -1;
            return;
        }

        activeIndex = (nextIndex + options.length) % options.length;

        options.forEach((option, index) => {
            option.classList.toggle('is-active', index === activeIndex);
            option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
        });

        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function chooseProfessional(professional) {
        if (!professional) {
            return;
        }

        professionalFilterInput.value = professional.nome || '';
        professionalFilterId.value = String(professional.id || '');
        closeProfessionalFilterMenu();
        submitProfessionalFilter();
    }

    function renderMenu(items) {
        results = items;
        activeIndex = -1;
        professionalFilterMenu.innerHTML = '';

        if (!items.length) {
            closeProfessionalFilterMenu();
            return;
        }

        items.forEach((professional, index) => {
            const button = document.createElement('button');
            const name = document.createElement('span');
            const meta = document.createElement('span');
            const metaParts = [];

            button.type = 'button';
            button.className = 'professional-autocomplete-option';
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');

            name.className = 'professional-autocomplete-name';
            name.textContent = professional.nome || '';
            button.appendChild(name);

            if (professional.profissao) {
                metaParts.push(professional.profissao);
            }

            if (professional.telefone) {
                metaParts.push('Contato: ' + professional.telefone);
            }

            if (metaParts.length) {
                meta.className = 'professional-autocomplete-meta';
                meta.textContent = metaParts.join(' | ');
                button.appendChild(meta);
            }

            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
            });

            button.addEventListener('click', () => {
                chooseProfessional(results[index]);
            });

            professionalFilterMenu.appendChild(button);
        });

        professionalFilterMenu.classList.add('is-open');
    }

    professionalFilterInput.addEventListener('input', () => {
        const term = professionalFilterInput.value.trim();
        professionalFilterId.value = '';
        window.clearTimeout(timer);

        if (term.length < 2) {
            if (controller) {
                controller.abort();
            }

            closeProfessionalFilterMenu();
            return;
        }

        timer = window.setTimeout(async () => {
            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            try {
                const response = await fetch('profissionais_busca.php?q=' + encodeURIComponent(term), {
                    signal: controller.signal
                });
                const data = await response.json();
                renderMenu(data.profissionais || []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    closeProfessionalFilterMenu();
                }
            }
        }, 160);
    });

    professionalFilterInput.addEventListener('keydown', (event) => {
        const isOpen = professionalFilterMenu.classList.contains('is-open');

        if (event.key === 'ArrowDown' && results.length) {
            event.preventDefault();

            if (!isOpen) {
                professionalFilterMenu.classList.add('is-open');
            }

            setActiveOption(activeIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp' && results.length) {
            event.preventDefault();
            setActiveOption(activeIndex <= 0 ? results.length - 1 : activeIndex - 1);
            return;
        }

        if (event.key === 'Enter' && isOpen && activeIndex >= 0) {
            event.preventDefault();
            chooseProfessional(results[activeIndex]);
            return;
        }

        if (event.key === 'Escape') {
            closeProfessionalFilterMenu();
        }
    });

    document.addEventListener('click', (event) => {
        if (!professionalFilterMenu.contains(event.target) && event.target !== professionalFilterInput) {
            closeProfessionalFilterMenu();
        }
    });
}

setupProfessionalFilterAutocomplete();

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function bindProfessionalServices(root = document) {
    const serviceSelect = root.getElementById ? root.getElementById('professionalServiceSelect') : document.getElementById('professionalServiceSelect');
    const chargeType = document.getElementById('professionalServiceChargeType');
    const chargeValue = document.getElementById('professionalServiceChargeValue');
    const duration = document.getElementById('professionalServiceDuration');
    const taxEnabled = document.getElementById('professionalServiceTaxEnabled');
    const taxPercent = document.getElementById('professionalServiceTaxPercent');
    const addButton = document.getElementById('professionalServiceAdd');
    const rows = document.getElementById('professionalServiceRows');
    const empty = document.getElementById('professionalServiceEmpty');

    if (!serviceSelect || !addButton || !rows) {
        return;
    }

    function moneyValue(value) {
        return String(value || '').trim();
    }

    function updateEmptyState() {
        if (empty) {
            empty.hidden = rows.querySelectorAll('tr[data-service-id]').length > 0;
        }
    }

    function syncServiceOptions() {
        const selected = new Set(Array.from(rows.querySelectorAll('tr[data-service-id]')).map((row) => row.dataset.serviceId));

        Array.from(serviceSelect.options).forEach((option) => {
            if (!option.value) {
                return;
            }

            option.disabled = selected.has(option.value);
        });
    }

    function syncTaxInput(scope = rows) {
        scope.querySelectorAll('[data-tax-toggle]').forEach((checkbox) => {
            const input = checkbox.closest('td')?.querySelector('[data-tax-percent]');
            if (!input) {
                return;
            }

            input.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                input.value = '';
            }
        });
    }

    function rowHtml(service) {
        const id = Number(service.id);
        const name = escapeHtml(service.nome || '');
        const minutes = Number(duration?.value || service.tempo_minutos || 50) || 50;
        const selectedChargeType = chargeType?.value === 'valor' ? 'valor' : 'percentual';
        const selectedChargeValue = escapeHtml(moneyValue(chargeValue?.value));
        const selectedTaxPercent = escapeHtml(moneyValue(taxPercent?.value));
        const checked = taxEnabled?.checked ? 'checked' : '';
        const disabled = taxEnabled?.checked ? '' : 'disabled';

        return `
            <tr data-service-id="${id}">
                <td>
                    <strong>${name}</strong>
                    <input type="hidden" name="servicos[]" value="${id}">
                </td>
                <td><input type="number" name="duracoes[${id}]" class="form-control" min="1" value="${minutes}"></td>
                <td>
                    <select name="cobranca_tipo[${id}]" class="form-select" data-charge-type>
                        <option value="percentual" ${selectedChargeType === 'percentual' ? 'selected' : ''}>% do servico</option>
                        <option value="valor" ${selectedChargeType === 'valor' ? 'selected' : ''}>Valor fixo</option>
                    </select>
                </td>
                <td><input type="text" name="cobranca_valor[${id}]" class="form-control" value="${selectedChargeValue}" placeholder="0,00"></td>
                <td>
                    <div class="d-flex gap-2 align-items-center">
                        <input class="form-check-input m-0" type="checkbox" name="cobra_imposto[${id}]" value="1" ${checked} data-tax-toggle>
                        <input type="text" name="imposto_percentual[${id}]" class="form-control" value="${selectedTaxPercent}" placeholder="0,00" ${disabled} data-tax-percent>
                    </div>
                </td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-service>Remover</button></td>
            </tr>`;
    }

    serviceSelect.addEventListener('change', () => {
        const service = professionalServiceCatalog.find((item) => String(item.id) === serviceSelect.value);
        if (service && duration && (!duration.value || duration.value === '50')) {
            duration.value = String(service.tempo_minutos || 50);
        }
    });

    taxEnabled?.addEventListener('change', () => {
        if (taxPercent) {
            taxPercent.disabled = !taxEnabled.checked;
            if (!taxEnabled.checked) {
                taxPercent.value = '';
            }
        }
    });

    addButton.addEventListener('click', () => {
        const service = professionalServiceCatalog.find((item) => String(item.id) === serviceSelect.value);

        if (!service) {
            alert('Selecione um servico para adicionar.');
            serviceSelect.focus();
            return;
        }

        if (rows.querySelector(`tr[data-service-id="${service.id}"]`)) {
            alert('Este servico ja esta na lista do profissional.');
            return;
        }

        if (chargeType?.value === 'percentual') {
            const numeric = parseFloat(String(chargeValue?.value || '0').replace(/\./g, '').replace(',', '.')) || 0;
            if (numeric < 0 || numeric > 100) {
                alert('Informe uma porcentagem entre 0 e 100.');
                chargeValue?.focus();
                return;
            }
        }

        rows.insertAdjacentHTML('beforeend', rowHtml(service));
        serviceSelect.value = '';
        if (chargeValue) chargeValue.value = '';
        if (taxPercent) taxPercent.value = '';
        if (taxEnabled) taxEnabled.checked = false;
        syncTaxInput(rows);
        updateEmptyState();
        syncServiceOptions();
    });

    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-service]');
        if (!button) {
            return;
        }

        button.closest('tr[data-service-id]')?.remove();
        updateEmptyState();
        syncServiceOptions();
    });

    rows.addEventListener('change', (event) => {
        if (event.target.matches('[data-tax-toggle]')) {
            syncTaxInput(event.target.closest('tr') || rows);
        }
    });

    syncTaxInput(rows);
    updateEmptyState();
    syncServiceOptions();
}

function bindProfessionalModalState(root = document) {
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

    bindProfessionalServices(root);
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
