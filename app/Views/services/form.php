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
    border-radius: 8px;
    overflow: hidden;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfd 100%);
    box-shadow: 0 18px 38px rgba(22, 51, 63, 0.12);
}
.service-form-card .card-header,
.service-form-card .card-body {
    padding: 0.72rem 0.82rem;
}
.service-form-card .card-header {
    border-bottom: 0;
    background: #0f5c4a;
}
.service-form-card .panel-title h5 {
    color: #ffffff;
}
.service-form-card .selection-chip {
    background: rgba(255, 255, 255, 0.16);
    color: #ffffff;
}
.service-form-tabs {
    width: min(880px, 100%);
    align-self: center;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.38rem;
    padding: 0.42rem;
    border: 1px solid rgba(15, 76, 92, 0.08);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 12px 26px rgba(24, 56, 69, 0.08);
}
.service-form-tabs .btn {
    min-height: 44px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.service-form-tabs .btn-primary {
    border-color: #0f5c4a;
    background: #0f5c4a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 92, 74, 0.22);
}
.service-form-tabs .btn-outline-primary,
.service-form-tabs .btn-outline-secondary {
    border-color: transparent;
    background: #eef6f3;
    color: #446473;
}
.service-tab-pane {
    padding: 0.62rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 5px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}
.service-data-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(260px, 0.8fr);
    gap: 0.95rem;
    align-items: stretch;
}
.service-summary-panel {
    height: 100%;
    padding: 0.88rem;
    border: 1px solid rgba(15, 92, 74, 0.12);
    border-radius: 8px;
    background: linear-gradient(180deg, #f7fbfd 0%, #eef6f3 100%);
}
.service-summary-title {
    margin-bottom: 0.68rem;
    color: #0f5c4a;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.service-summary-grid {
    display: grid;
    gap: 0.5rem;
}
.service-summary-item {
    padding: 0.58rem 0.62rem;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 8px 18px rgba(24, 56, 69, 0.06);
}
.service-summary-item span,
.service-summary-item strong {
    display: block;
}
.service-summary-item span {
    color: #68828f;
    font-size: 0.66rem;
    font-weight: 800;
}
.service-summary-item strong {
    color: #173642;
    font-size: 0.86rem;
    margin-top: 0.1rem;
}
.service-status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0.18rem 0.62rem;
    border-radius: 999px;
    background: rgba(15, 92, 74, 0.1);
    color: #0f5c4a;
    font-size: 0.72rem;
    font-weight: 900;
}
.service-tab-pane .form-control,
.service-tab-pane .form-select {
    min-height: 34px;
    border-radius: 8px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}
.service-tab-pane .btn {
    min-height: 34px;
    border-radius: 8px;
    font-size: 0.8rem;
}
.service-form-section {
    flex: 0 0 100%;
    width: 100%;
    margin: 0;
    padding: 0;
    color: #0f5c4a;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-align: left;
}
.service-price-flags {
    min-height: 34px;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.35rem 0.55rem;
    border: 1px solid #dbe7ec;
    border-radius: 8px;
    background: #f8fbfc;
}
.service-price-flags .form-check {
    min-height: 0;
    margin: 0;
}
.service-price-flags .form-check-label {
    font-size: 0.78rem;
}
.service-price-list {
    max-height: calc(100vh - 300px);
    overflow: auto;
}
.service-price-list .table {
    font-size: 0.76rem;
}
.service-price-list th,
.service-price-list td {
    padding: 0.34rem 0.45rem;
    vertical-align: middle;
}
.service-price-actions {
    white-space: nowrap;
}
.service-price-empty {
    color: #68828f;
    font-size: 0.82rem;
    padding: 0.7rem;
    border: 1px dashed rgba(31, 122, 140, 0.25);
    border-radius: 14px;
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
    .service-form-tabs {
        grid-template-columns: 1fr;
    }
    .service-data-layout {
        grid-template-columns: 1fr;
    }
    .service-price-list {
        max-height: none;
        overflow: visible;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>
<?php $isPriceServiceTab = ($activeServiceTab ?? 'dados') === 'precos'; ?>
<?php
$serviceScheduleLabel = ($selectedService['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'Grupo' : 'Individual';
$serviceStatusLabel = !isset($selectedService['ativo']) || (int) $selectedService['ativo'] === 1 ? 'Ativo' : 'Inativo';
$serviceDurationLabel = (int) ($selectedService['tempo_minutos'] ?? 50) . ' min';
$serviceCapacityLabel = (int) ($selectedService['capacidade_agendamento'] ?? 1);
?>

<div class="container page-shell service-form-shell">
    <div class="service-form-layout">
    <section class="page-hero service-form-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2"><?= $selectedService ? 'Editar servico' : 'Novo servico' ?></h3>
                <p><?= $selectedService ? 'Atualize os dados do servico e mantenha os precos na aba propria do cadastro.' : 'Cadastre um novo servico para uso na agenda e nos vinculos profissionais.' ?></p>
            </div>
            <a href="secretaria_servicos.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar a Lista</a>
        </div>
    </section>

    <div class="service-form-tabs">
        <a class="btn <?= $isPriceServiceTab ? 'btn-outline-primary' : 'btn-primary' ?>" href="<?= $selectedService ? 'editar_servico.php?' . app_h(app_build_query(['id' => $selectedService['id']])) : 'novo_servico.php' ?>">Dados do servico</a>
        <?php if ($selectedService): ?>
            <a class="btn <?= $isPriceServiceTab ? 'btn-primary' : 'btn-outline-primary' ?>" href="editar_servico.php?<?= app_h(app_build_query(['id' => $selectedService['id'], 'tab' => 'precos'])) ?>">Precos</a>
        <?php else: ?>
            <span class="btn btn-outline-secondary disabled" aria-disabled="true">Precos</span>
        <?php endif; ?>
    </div>

    <?php if (!$isPriceServiceTab): ?>
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
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
                    <form method="POST" class="row g-3 service-tab-pane">
                        <?php if ($selectedService): ?>
                            <input type="hidden" name="service_id" value="<?= (int) $selectedService['id'] ?>">
                        <?php endif; ?>

                        <div class="service-form-section">Dados principais</div>
                        <div class="col-12">
                            <div class="service-data-layout">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small text-muted">Nome do servico</label>
                                        <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h((string) ($selectedService['nome'] ?? '')) ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Duracao em minutos</label>
                                        <input type="number" name="tempo_minutos" class="form-control" min="1" value="<?= (int) ($selectedService['tempo_minutos'] ?? 50) ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Tipo de agenda</label>
                                        <select name="tipo_agendamento" id="serviceScheduleType" class="form-select" title="Individual ocupa um horario por paciente. Grupo permite varios pacientes no mesmo horario.">
                                            <option value="individual" <?= ($selectedService['tipo_agendamento'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Individual</option>
                                            <option value="grupo" <?= ($selectedService['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'selected' : '' ?>>Grupo</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Capacidade por horario</label>
                                        <input type="number" name="capacidade_agendamento" id="serviceCapacity" class="form-control" min="1" value="<?= (int) ($selectedService['capacidade_agendamento'] ?? 1) ?>" required title="Quantidade maxima de pacientes permitidos no mesmo horario quando o tipo for Grupo.">
                                    </div>

                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="form-check ps-1 pb-2">
                                            <input class="form-check-input" type="checkbox" name="ativo" id="serviceActive" <?= !isset($selectedService['ativo']) || (int) $selectedService['ativo'] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="serviceActive">Servico ativo</label>
                                        </div>
                                    </div>
                                </div>

                                <aside class="service-summary-panel">
                                    <div class="service-summary-title">Resumo</div>
                                    <div class="service-summary-grid">
                                        <div class="service-summary-item">
                                            <span>Agenda</span>
                                            <strong><?= app_h($serviceScheduleLabel) ?></strong>
                                        </div>
                                        <div class="service-summary-item">
                                            <span>Duracao</span>
                                            <strong><?= app_h($serviceDurationLabel) ?></strong>
                                        </div>
                                        <div class="service-summary-item">
                                            <span>Capacidade</span>
                                            <strong><?= (int) $serviceCapacityLabel ?></strong>
                                        </div>
                                        <div class="service-summary-item">
                                            <span>Status</span>
                                            <strong><span class="service-status-pill"><?= app_h($serviceStatusLabel) ?></span></strong>
                                        </div>
                                    </div>
                                </aside>
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
    <?php else: ?>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="soft-card card service-form-card">
                <div class="card-header">
                    <div class="panel-title">
                        <h5>Precos do servico</h5>
                        <span class="selection-chip"><?= app_h((string) $selectedService['nome']) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($priceMessage)): ?>
                        <div class="alert alert-warning"><?= app_h($priceMessage) ?></div>
                    <?php endif; ?>

                    <div class="service-tab-pane">
                        <form method="POST" class="row g-2 align-items-end" id="servicePriceInlineForm">
                            <input type="hidden" name="action" value="save_service_price">
                            <input type="hidden" name="service_id" value="<?= (int) $selectedService['id'] ?>">
                            <input type="hidden" name="servico_id" id="priceServiceId" value="<?= (int) $selectedService['id'] ?>">
                            <input type="hidden" name="preco_id" id="priceId" value="<?= (int) ($priceFormValues['preco_id'] ?? 0) ?>">

                            <div class="service-form-section">Tabela de valores</div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted">Plano</label>
                                <select name="plano_id" id="pricePlanId" class="form-select" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($planPriceOptions as $planOption): ?>
                                        <option value="<?= (int) $planOption['id'] ?>" <?= (int) ($priceFormValues['plano_id'] ?? 0) === (int) $planOption['id'] ? 'selected' : '' ?>>
                                            <?= app_h((string) $planOption['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small text-muted">Valor</label>
                                <input type="text" name="valor" id="priceValue" class="form-control" value="<?= app_h((string) ($priceFormValues['valor'] ?? '')) ?>" placeholder="0,00" required>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label small text-muted">Regras</label>
                                <div class="service-price-flags">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="priceActive" <?= !isset($priceFormValues['ativo']) || (int) $priceFormValues['ativo'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="priceActive">Ativo</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permite_alterar_guia" id="priceAllowGuideEdit" <?= !empty($priceFormValues['permite_alterar_guia']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="priceAllowGuideEdit">Alterar na guia</label>
                                </div>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <label class="form-label small text-muted">Observacoes</label>
                                <textarea name="observacoes" id="priceNotes" class="form-control" rows="1"><?= app_h((string) ($priceFormValues['observacoes'] ?? '')) ?></textarea>
                            </div>

                            <div class="col-md-5 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" id="priceResetButton">Novo preco</button>
                                <button class="btn btn-primary px-4">Salvar preco</button>
                            </div>
                        </form>
                    </div>

                    <div class="service-tab-pane mt-3">
                        <div class="service-form-section mb-3">Valores cadastrados</div>
                        <div class="service-price-list">
                            <?php if (empty($servicePrices)): ?>
                                <div class="service-price-empty">Nenhum preco cadastrado para este servico.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-soft align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Plano</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Guia</th>
                                            <th class="text-end">Acoes</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($servicePrices as $price): ?>
                                            <tr>
                                                <td><?= app_h((string) ($price['plano_nome'] ?: 'Sem plano')) ?></td>
                                                <td><?= app_money_br((float) $price['valor']) ?></td>
                                                <td><?= (int) $price['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                                                <td><?= !empty($price['permite_alterar_guia']) ? 'Editavel' : 'Fixo' ?></td>
                                                <td class="text-end service-price-actions">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        data-price-edit
                                                        data-preco-id="<?= (int) $price['id'] ?>"
                                                        data-plano-id="<?= (int) ($price['plano_id'] ?? 0) ?>"
                                                        data-valor="<?= app_h(number_format((float) $price['valor'], 2, ',', '.')) ?>"
                                                        data-ativo="<?= (int) $price['ativo'] ?>"
                                                        data-permite-alterar-guia="<?= (int) ($price['permite_alterar_guia'] ?? 0) ?>"
                                                        data-observacoes="<?= app_h((string) ($price['observacoes'] ?? '')) ?>"
                                                    >Editar</button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este preco?');">
                                                        <input type="hidden" name="action" value="delete_service_price">
                                                        <input type="hidden" name="service_id" value="<?= (int) $selectedService['id'] ?>">
                                                        <input type="hidden" name="preco_id" value="<?= (int) $price['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger">Excluir</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<script>
const serviceScheduleType = document.getElementById('serviceScheduleType');
const serviceCapacity = document.getElementById('serviceCapacity');
function syncServiceCapacity() {
    if (!serviceScheduleType || !serviceCapacity) return;
    if (serviceScheduleType.value === 'individual') {
        serviceCapacity.value = '1';
        serviceCapacity.readOnly = true;
    } else {
        serviceCapacity.readOnly = false;
    }
}
serviceScheduleType?.addEventListener('change', syncServiceCapacity);
syncServiceCapacity();

const priceForm = document.getElementById('servicePriceInlineForm');
const priceId = document.getElementById('priceId');
const pricePlanId = document.getElementById('pricePlanId');
const priceValue = document.getElementById('priceValue');
const priceActive = document.getElementById('priceActive');
const priceAllowGuideEdit = document.getElementById('priceAllowGuideEdit');
const priceNotes = document.getElementById('priceNotes');
const priceResetButton = document.getElementById('priceResetButton');

function resetInlinePriceForm() {
    if (!priceForm) return;
    if (priceId) priceId.value = '0';
    if (pricePlanId) pricePlanId.value = '';
    if (priceValue) priceValue.value = '';
    if (priceActive) priceActive.checked = true;
    if (priceAllowGuideEdit) priceAllowGuideEdit.checked = false;
    if (priceNotes) priceNotes.value = '';
}

priceResetButton?.addEventListener('click', resetInlinePriceForm);

document.querySelectorAll('[data-price-edit]').forEach((button) => {
    button.addEventListener('click', () => {
        if (priceId) priceId.value = button.dataset.precoId || '0';
        if (pricePlanId) pricePlanId.value = button.dataset.planoId || '';
        if (priceValue) priceValue.value = button.dataset.valor || '';
        if (priceActive) priceActive.checked = button.dataset.ativo !== '0';
        if (priceAllowGuideEdit) priceAllowGuideEdit.checked = button.dataset.permiteAlterarGuia === '1';
        if (priceNotes) priceNotes.value = button.dataset.observacoes || '';
        priceForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>

</body>
</html>
