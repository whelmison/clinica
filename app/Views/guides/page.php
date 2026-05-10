<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Gestao de Guias</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<?php
$isEditingGuide = (int) ($guideFormValues['guide_id'] ?? 0) > 0;
$guideBatchOptions = $guideOptions['batches'] ?? [];
$activeBatchId = (int) ($guideFormValues['lote_id'] ?? 0);
$activeGuideCode = trim((string) ($selectedGuide['codigo'] ?? $guideFormValues['codigo'] ?? ''));
$activeGuideTitle = $activeGuideCode !== '' ? $activeGuideCode : ($isEditingGuide ? 'GUIA #' . (int) $guideFormValues['guide_id'] : 'Nova guia');
?>

<style>
body {
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}

.guide-shell {
    padding: 0.95rem 1rem 1.4rem;
}

.guide-topbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
    color: #fff;
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.12);
}

.guide-kicker {
    margin: 0 0 0.12rem;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.78;
}

.guide-topbar h3 {
    margin: 0;
    font-size: 1.28rem;
}

.guide-top-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.guide-top-actions .btn {
    min-height: 36px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
}

.guide-role-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.36rem 0.68rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
}

.guide-summary-strip {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.36rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.72rem;
}

.guide-summary-strip span {
    padding: 0.16rem 0.42rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
}

.guide-filter-card,
.guide-list-panel {
    margin-top: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.guide-filter-card {
    padding: 0.72rem;
}

.guide-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1.3fr) minmax(170px, 0.75fr) minmax(170px, 0.75fr) minmax(180px, 0.8fr) auto;
    gap: 0.5rem;
    align-items: end;
}

.guide-filter-grid label {
    margin-bottom: 0.14rem;
    color: #647d89;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.guide-filter-grid .form-control,
.guide-filter-grid .form-select,
.guide-filter-grid .btn {
    min-height: 38px;
    border-radius: 12px;
    font-size: 0.82rem;
}

.guide-filter-actions {
    display: flex;
    gap: 0.38rem;
}

.guide-list-panel {
    overflow: hidden;
}

.guide-list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.72rem 0.85rem;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.guide-list-head h5 {
    margin: 0;
    color: #143b49;
    font-size: 0.95rem;
}

.guide-list-head span {
    color: #6b8591;
    font-size: 0.72rem;
}

.guide-table {
    display: grid;
}

.guide-row {
    display: grid;
    grid-template-columns: minmax(210px, 1.35fr) minmax(150px, 0.9fr) minmax(95px, 0.55fr) minmax(150px, 0.85fr) minmax(95px, 0.55fr) 70px;
    gap: 0.6rem;
    align-items: center;
    padding: 0.52rem 0.85rem;
    border-bottom: 1px solid rgba(18, 73, 88, 0.07);
    color: #1d3945;
    text-decoration: none;
}

.guide-row:not(.guide-row-head):hover {
    background: rgba(31, 122, 140, 0.055);
    color: #123744;
}

.guide-row.is-selected {
    background: rgba(225, 244, 245, 0.82);
}

.guide-row-head {
    padding-top: 0.42rem;
    padding-bottom: 0.42rem;
    background: #f5f9fa;
    color: #6b8591;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.guide-main-cell,
.guide-value-cell,
.guide-row div {
    min-width: 0;
}

.guide-main-cell strong,
.guide-main-cell span,
.guide-value-cell strong,
.guide-value-cell span,
.guide-muted-cell,
.guide-row small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.guide-main-cell strong {
    font-size: 0.82rem;
    color: #123744;
}

.guide-main-cell span,
.guide-muted-cell,
.guide-row small,
.guide-value-cell span {
    color: #6b8591;
    font-size: 0.7rem;
}

.guide-value-cell {
    text-align: right;
}

.guide-value-cell strong {
    color: #123744;
    font-size: 0.82rem;
}

.guide-pill {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    padding: 0.18rem 0.44rem;
    border-radius: 999px;
    background: rgba(31, 122, 140, 0.08);
    color: #0f4c5c;
    font-size: 0.66rem;
    font-weight: 800;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.guide-pill.status-info {
    background: rgba(31, 122, 140, 0.1);
    color: #0f4c5c;
}

.guide-pill.status-warning {
    background: rgba(245, 166, 35, 0.15);
    color: #98630b;
}

.guide-pill.status-success {
    background: rgba(31, 157, 109, 0.14);
    color: #187047;
}

.guide-pill.status-success-soft {
    background: rgba(31, 157, 109, 0.1);
    color: #187047;
}

.guide-pill.status-muted {
    background: #edf2f4;
    color: #526973;
}

.guide-pill.status-danger {
    background: rgba(220, 53, 69, 0.12);
    color: #9b1c2a;
}

.guide-action-cell {
    text-align: right;
}

.guide-action-cell span {
    display: inline-flex;
    justify-content: center;
    min-width: 58px;
    padding: 0.26rem 0.48rem;
    border-radius: 999px;
    background: #0f4c5c;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 800;
}

.guide-empty-state {
    display: grid;
    gap: 0.14rem;
    padding: 1rem;
    color: #647d89;
}

.guide-empty-state strong {
    color: #143b49;
}

.guide-list-panel nav {
    padding: 0 0.85rem 0.85rem;
}

.guide-list-panel.is-loading {
    opacity: 0.62;
}

.guides-modal .modal-dialog {
    max-width: 1040px;
}

.guides-modal .modal-content {
    border: 0;
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 22px 44px rgba(22, 51, 63, 0.2);
}

.guides-modal .modal-header {
    padding: 0.9rem 1rem 0.82rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.guides-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.guides-modal .modal-body {
    padding: 0.95rem 1rem 1rem;
}

.guides-modal .btn-close {
    box-shadow: none;
}

.guide-form .form-control,
.guide-form .form-select,
.guide-form .btn {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.guide-form textarea.form-control {
    min-height: 108px;
}

.guide-form .form-label {
    margin-bottom: 0.18rem;
    color: #5c7783;
    font-size: 0.72rem;
    font-weight: 700;
}

.guide-form-note {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.62rem;
    border-radius: 999px;
    background: rgba(15, 76, 92, 0.08);
    color: #0f4c5c;
    font-size: 0.72rem;
    font-weight: 700;
}

.guide-form-hint {
    color: #68828f;
    font-size: 0.72rem;
}

.guide-form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
    margin-top: 0.2rem;
}

.guide-form-footer-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    justify-content: flex-end;
}

.guide-batch-help {
    margin-top: 0.22rem;
    color: #7b919c;
    font-size: 0.7rem;
}

@media (max-width: 1050px) {
    .guide-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .guide-filter-actions {
        grid-column: 1 / -1;
    }

    .guide-row {
        grid-template-columns: minmax(210px, 1.2fr) minmax(140px, 0.8fr) minmax(100px, 0.55fr) minmax(92px, 0.55fr) 74px;
    }

    .guide-row > :nth-child(4) {
        display: none;
    }

    .guide-row > :nth-child(5) {
        text-align: left;
    }

    .guide-row-head > :nth-child(5) {
        text-align: left !important;
    }
}

@media (max-width: 760px) {
    .guide-shell {
        padding: 0.7rem;
    }

    .guide-topbar,
    .guide-list-head,
    .guide-form-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .guide-top-actions,
    .guide-form-footer-actions {
        justify-content: flex-start;
    }

    .guide-filter-grid {
        grid-template-columns: 1fr;
    }

    .guide-row-head {
        display: none;
    }

    .guide-row {
        grid-template-columns: 1fr auto;
        gap: 0.36rem 0.6rem;
        padding: 0.72rem 0.85rem;
    }

    .guide-row > :nth-child(2),
    .guide-row > :nth-child(3),
    .guide-row > :nth-child(4),
    .guide-row > :nth-child(5) {
        display: none;
    }
}
</style>

<div class="container-fluid guide-shell">
    <section class="guide-topbar">
        <div>
            <p class="guide-kicker">Gestao de guias</p>
            <h3>Guias</h3>
            <div id="guideMetrics"><?= $guideMetricsHtml ?></div>
        </div>
        <div class="guide-top-actions">
            <div class="guide-role-chip">
                <?= app_h(ucfirst((string) ($currentUser['perfil'] ?? ''))) ?>
                <?php if ($scopeProfessionalId): ?>
                    <span>somente proprio profissional</span>
                <?php endif; ?>
            </div>
            <?php if ($canCreateGuides): ?>
                <button type="button" class="btn btn-light btn-sm px-3" data-bs-toggle="modal" data-bs-target="#guideFormModal">+ Nova guia</button>
            <?php endif; ?>
        </div>
    </section>

    <section class="guide-filter-card">
        <form id="guideFilterForm" class="guide-filter-grid" method="GET">
            <div>
                <label class="form-label">Busca</label>
                <input type="text" class="form-control" name="busca" data-page-autofocus="1" value="<?= app_h($filterDefaults['busca']) ?>" placeholder="Codigo, paciente ou profissional">
            </div>
            <div>
                <label class="form-label">Paciente</label>
                <select class="form-select" name="paciente_id">
                    <option value="">Todos</option>
                    <?php foreach ($guideOptions['patients'] as $option): ?>
                        <option value="<?= (int) $option['id'] ?>" <?= (int) $filterDefaults['paciente_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                            <?= app_h($option['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Profissional</label>
                <select class="form-select" name="profissional_id" <?= $scopeProfessionalId !== null ? 'disabled' : '' ?>>
                    <option value="">Todos</option>
                    <?php foreach ($guideOptions['professionals'] as $option): ?>
                        <option value="<?= (int) $option['id'] ?>" <?= (int) $filterDefaults['profissional_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                            <?= app_h($option['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($scopeProfessionalId !== null): ?>
                    <input type="hidden" name="profissional_id" value="<?= (int) $scopeProfessionalId ?>">
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label">Status operacional</label>
                <select class="form-select" name="status_operacional">
                    <option value="">Todos</option>
                    <?php foreach (app_guide_operational_statuses() as $statusValue => $statusLabel): ?>
                        <option value="<?= app_h($statusValue) ?>" <?= (string) ($filterDefaults['status_operacional'] ?? '') === $statusValue ? 'selected' : '' ?>>
                            <?= app_h($statusLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="guide-filter-actions">
                <button class="btn btn-primary" type="submit">Filtrar</button>
                <a href="gestao_guias.php<?= $scopeProfessionalId ? '?profissional_id=' . (int) $scopeProfessionalId : '' ?>" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </section>

    <section class="guide-list-panel">
        <div class="guide-list-head">
            <h5>Lista enxuta</h5>
            <span id="guideListMeta"><?= (int) ($pagination['total'] ?? 0) ?> guia(s) encontradas | <?= (int) ($pagination['per_page'] ?? 18) ?> por pagina</span>
        </div>
        <div id="guideCards">
            <?= $guideCardsHtml ?>
        </div>
    </section>
</div>

<?php if ($canCreateGuides || $isEditingGuide): ?>
<div class="modal fade guides-modal" id="guideFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1"><?= $isEditingGuide ? 'Editar guia' : 'Nova guia' ?></h5>
                    <div class="small text-muted">
                        <?= $isEditingGuide ? 'Atualize a guia selecionada sem sair da lista.' : 'Cadastro rapido de guia em popup.' ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="row g-3 guide-form">
                    <input type="hidden" name="guide_id" value="<?= (int) ($guideFormValues['guide_id'] ?? 0) ?>">
                    <input type="hidden" name="filter_guia_id" value="<?= (int) ($filterDefaults['guia_id'] ?? 0) ?>">
                    <input type="hidden" name="filter_paciente_id" value="<?= (int) ($filterDefaults['paciente_id'] ?? 0) ?>">
                    <input type="hidden" name="filter_profissional_id" value="<?= (int) ($scopeProfessionalId ?? ($filterDefaults['profissional_id'] ?? 0)) ?>">
                    <input type="hidden" name="filter_busca" value="<?= app_h((string) ($filterDefaults['busca'] ?? '')) ?>">
                    <input type="hidden" name="filter_status_operacional" value="<?= app_h((string) ($filterDefaults['status_operacional'] ?? '')) ?>">
                    <input type="hidden" name="filter_page" value="<?= (int) ($pagination['page'] ?? 1) ?>">

                    <div class="col-md-6">
                        <label class="form-label">Paciente</label>
                        <select name="paciente_id" class="form-select" data-page-autofocus="1" required>
                            <option value="">Selecione</option>
                            <?php foreach ($guideOptions['patients'] as $patient): ?>
                                <option value="<?= (int) $patient['id'] ?>" <?= (int) ($guideFormValues['paciente_id'] ?? 0) === (int) $patient['id'] ? 'selected' : '' ?>>
                                    <?= app_h($patient['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Profissional</label>
                        <?php if ($scopeProfessionalId !== null): ?>
                            <?php
                            $scopeProfessionalName = '';
                            foreach ($guideOptions['professionals'] as $professionalOption) {
                                if ((int) $professionalOption['id'] === (int) $scopeProfessionalId) {
                                    $scopeProfessionalName = (string) $professionalOption['nome'];
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="profissional_id" value="<?= (int) $scopeProfessionalId ?>">
                            <input type="text" class="form-control" value="<?= app_h($scopeProfessionalName) ?>" disabled>
                        <?php else: ?>
                            <select name="profissional_id" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($guideOptions['professionals'] as $professional): ?>
                                    <option value="<?= (int) $professional['id'] ?>" <?= (int) ($guideFormValues['profissional_id'] ?? 0) === (int) $professional['id'] ? 'selected' : '' ?>>
                                        <?= app_h($professional['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Plano</label>
                        <select name="plano_id" class="form-select">
                            <option value="">Sem plano</option>
                            <?php foreach ($guideOptions['plans'] as $plan): ?>
                                <option value="<?= (int) $plan['id'] ?>" <?= (int) ($guideFormValues['plano_id'] ?? 0) === (int) $plan['id'] ? 'selected' : '' ?>>
                                    <?= app_h($plan['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo_guia" id="guideTypeField" class="form-select" required>
                            <?php foreach (app_guide_types() as $value => $label): ?>
                                <option value="<?= app_h($value) ?>" <?= (string) ($guideFormValues['tipo_guia'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= app_h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" class="form-control" value="<?= app_h((string) ($guideFormValues['data'] ?? '')) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Sessoes</label>
                        <input type="number" name="total_sessoes" class="form-control" min="1" value="<?= (int) ($guideFormValues['total_sessoes'] ?? 1) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Codigo</label>
                        <input type="text" name="codigo" class="form-control" value="<?= app_h((string) ($guideFormValues['codigo'] ?? '')) ?>" placeholder="Gerado automaticamente">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Valor</label>
                        <input type="text" name="valor_guia" class="form-control" value="<?= app_h((string) ($guideFormValues['valor_guia'] ?? '')) ?>" placeholder="R$ 0,00">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Convenio</label>
                        <input type="text" name="convenio" class="form-control" value="<?= app_h((string) ($guideFormValues['convenio'] ?? '')) ?>" placeholder="Operadora / convenio">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status operacional</label>
                        <select name="status_operacional" class="form-select" title="Estado operacional da guia. Em uso, ultimas sessoes e finalizada tambem sao recalculados pelos atendimentos.">
                            <?php $selectedOperationalStatus = app_normalize_guide_operational_status((string) ($guideFormValues['status_operacional'] ?? 'aguardando_autorizacao')); ?>
                            <?php foreach (app_guide_operational_statuses() as $statusValue => $statusLabel): ?>
                                <option value="<?= app_h($statusValue) ?>" <?= $selectedOperationalStatus === $statusValue ? 'selected' : '' ?>>
                                    <?= app_h($statusLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Numero de lote</label>
                        <select name="lote_id" id="guideBatchField" class="form-select">
                            <option value="">Definir depois no faturamento</option>
                            <?php foreach ($guideBatchOptions as $batch): ?>
                                <option value="<?= (int) $batch['id'] ?>" <?= $activeBatchId === (int) $batch['id'] ? 'selected' : '' ?>>
                                    <?= app_h(($batch['numero_lote'] ?: ('LOTE #' . $batch['id'])) . ' | ' . ($batch['convenio'] ?: 'Sem convenio')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="guide-batch-help" id="guideBatchHelp">
                            Disponivel apenas para guias do tipo convenio por lote.
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="guide-form-note">
                            <?= app_h($activeGuideTitle) ?>
                            <?php if ($isEditingGuide && $selectedGuide): ?>
                                <span><?= app_h(guide_billing_label($selectedGuide)) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check pb-2">
                            <input class="form-check-input" type="checkbox" name="autorizada" id="guideAuthorizedField" <?= (int) ($guideFormValues['autorizada'] ?? 0) === 1 ? 'checked' : '' ?> title="Somente guias autorizadas podem gerar atendimento realizado.">
                            <label class="form-check-label" for="guideAuthorizedField">Guia autorizada</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observacoes</label>
                        <textarea name="observacoes" rows="4" class="form-control"><?= app_h((string) ($guideFormValues['observacoes'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="guide-form-footer">
                            <span class="guide-form-hint">Pacientes, tipo de faturamento e lote ficam prontos sem ocupar espaco da listagem.</span>
                            <div class="guide-form-footer-actions">
                                <?php if ($isEditingGuide && $canDeleteGuides): ?>
                                    <button type="submit" name="action" value="delete_guide" class="btn btn-outline-danger" onclick="return confirm('Excluir esta guia?')">Excluir</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary px-4" type="submit" name="action" value="save_guide"><?= $isEditingGuide ? 'Salvar alteracoes' : 'Salvar guia' ?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const guideFilterForm = document.getElementById('guideFilterForm');
const guideCards = document.getElementById('guideCards');
const guideMetrics = document.getElementById('guideMetrics');
const guideListMeta = document.getElementById('guideListMeta');
const guideListPanel = document.querySelector('.guide-list-panel');
const guideTypeField = document.getElementById('guideTypeField');
const guideBatchField = document.getElementById('guideBatchField');
const guideBatchHelp = document.getElementById('guideBatchHelp');
let guideFilterTimer = null;

async function refreshGuides() {
    const params = new URLSearchParams(new FormData(guideFilterForm));
    for (const [key, value] of Array.from(params.entries())) {
        if (String(value).trim() === '') {
            params.delete(key);
        }
    }
    params.set('ajax', '1');
    guideListPanel?.classList.add('is-loading');

    try {
        const response = await fetch('gestao_guias.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const payload = await response.json();
        guideCards.innerHTML = payload.cards;
        guideMetrics.innerHTML = payload.metrics;
        if (guideListMeta && payload.listLabel) {
            guideListMeta.textContent = payload.listLabel;
        }
        params.delete('ajax');
        window.history.replaceState({}, '', params.toString() ? 'gestao_guias.php?' + params.toString() : 'gestao_guias.php');
    } finally {
        guideListPanel?.classList.remove('is-loading');
    }
}

function syncGuideBatchField() {
    if (!guideTypeField || !guideBatchField) {
        return;
    }

    const usesBatch = guideTypeField.value === 'convenio_lote';
    guideBatchField.disabled = !usesBatch;

    if (!usesBatch) {
        guideBatchField.value = '';
    }

    if (guideBatchHelp) {
        if (usesBatch && guideBatchField.options.length <= 1) {
            guideBatchHelp.textContent = 'Nenhum lote cadastrado ainda. Cadastre no faturamento quando precisar.';
        } else if (usesBatch) {
            guideBatchHelp.textContent = 'Selecione um lote existente quando esta guia ja pertencer a um envio.';
        } else {
            guideBatchHelp.textContent = 'Disponivel apenas para guias do tipo convenio por lote.';
        }
    }
}

guideFilterForm.querySelectorAll('select, input').forEach((field) => {
    if (field.type === 'hidden') {
        return;
    }

    field.addEventListener('change', () => {
        clearTimeout(guideFilterTimer);
        guideFilterTimer = setTimeout(refreshGuides, 120);
    });

    if (field.name === 'busca') {
        field.addEventListener('input', () => {
            clearTimeout(guideFilterTimer);
            guideFilterTimer = setTimeout(refreshGuides, 260);
        });
    }
});

guideTypeField?.addEventListener('change', syncGuideBatchField);
syncGuideBatchField();
</script>

<?php if (!empty($autoOpenGuideModal) && ($canCreateGuides || $isEditingGuide)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('guideFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
