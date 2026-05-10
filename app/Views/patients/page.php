<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Pacientes</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">

<style>
body {
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}

.patient-shell {
    padding: 0.7rem 0.9rem 1rem;
}

.patient-topbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.76rem 0.92rem;
    border-radius: 8px;
    background: #0f5c4a;
    color: #fff;
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.12);
}

.patient-kicker {
    margin: 0 0 0.12rem;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    opacity: 0.78;
    text-transform: uppercase;
}

.patient-topbar h3 {
    margin: 0;
    font-size: 1.12rem;
}

.patient-topbar p {
    margin: 0.16rem 0 0;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.74rem;
    line-height: 1.18;
}

.patient-top-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.42rem;
    flex-wrap: wrap;
}

.patient-top-actions .btn {
    border-radius: 8px;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.34rem 0.68rem;
}

.patient-filter-card {
    margin-top: 0.6rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.patient-filter-card .form-control,
.patient-filter-card .form-select {
    min-height: 36px;
    border-radius: 8px;
    border-color: #dbe7ec;
    font-size: 0.78rem;
}

.patient-list-panel,
.patient-alert-card {
    margin-top: 0.6rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.patient-list-panel {
    overflow: hidden;
}

.patient-list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.6rem 0.78rem;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.patient-list-head h5 {
    margin: 0;
    color: #143b49;
    font-size: 0.9rem;
}

.patient-list-head span {
    color: #6b8591;
    font-size: 0.7rem;
}

.patient-table {
    margin: 0;
    font-size: 0.76rem;
}

.patient-table thead tr:first-child th {
    padding: 0.34rem 0.44rem;
    background: #f5f9fa;
    color: #6b8591;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.patient-table thead tr:nth-child(2) th {
    padding: 0.34rem 0.44rem;
    background: #f5f9fa;
    color: #6b8591;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.patient-table th,
.patient-table td {
    vertical-align: middle;
    border-color: rgba(18, 73, 88, 0.07);
}

.patient-table tbody td {
    padding: 0.38rem 0.44rem;
    color: #1d3945;
    line-height: 1.1;
}

.patient-table tbody tr:hover {
    background: rgba(31, 122, 140, 0.055);
}

.patient-table tbody tr.is-active {
    background: rgba(225, 244, 245, 0.7);
}

.patient-table th select {
    width: 100%;
    min-height: 32px;
    border: 1px solid #d7e3e7;
    border-radius: 8px;
    color: #274b58;
    font-size: 0.72rem;
}

.patient-name {
    font-weight: 700;
    color: #16333f;
    line-height: 1.08;
}

.patient-table .small {
    margin-top: 0.08rem;
    line-height: 1.08;
}

.status-ativo,
.status-atencao,
.status-inativo {
    display: inline-flex;
    align-items: center;
    padding: 0.16rem 0.44rem;
    border-radius: 6px;
    font-size: 0.66rem;
    font-weight: 800;
}

.status-ativo {
    background: rgba(15, 92, 74, 0.14);
    color: #0f5c4a;
}

.status-atencao {
    background: rgba(15, 92, 74, 0.14);
    color: #0f5c4a;
}

.status-inativo {
    background: rgba(15, 92, 74, 0.14);
    color: #0f5c4a;
}

.acoes {
    display: flex;
    gap: 0.34rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.acoes .btn {
    border-radius: 6px;
    min-height: 28px;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.22rem 0.54rem;
}

.patient-meta-line {
    color: #6b8591;
    font-size: 0.68rem;
    line-height: 1.16;
}

.patient-emergency {
    display: inline-flex;
    margin-top: 0.12rem;
    padding: 0.1rem 0.38rem;
    border-radius: 6px;
    background: rgba(15, 92, 74, 0.14);
    color: #0f5c4a;
    font-size: 0.64rem;
    font-weight: 800;
}

.patient-table tfoot {
    background: #f5f9fa;
    color: #143b49;
    font-weight: 800;
}

.patient-alert-card {
    height: 100%;
    padding: 0.72rem;
}

.patient-alert-card.is-danger {
    border-color: rgba(15, 92, 74, 0.18);
}

.patient-alert-card.is-warning {
    border-color: rgba(15, 92, 74, 0.18);
}

.patient-alert-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    margin-bottom: 0.42rem;
    color: #143b49;
    font-weight: 800;
}

.patient-alert-count {
    display: inline-flex;
    min-width: 28px;
    justify-content: center;
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
    background: rgba(15, 92, 74, 0.12);
    color: #0f5c4a;
    font-size: 0.7rem;
}

.alerta-lista {
    display: grid;
    max-height: 190px;
    gap: 0.22rem;
    margin: 0;
    overflow: auto;
    padding-left: 1rem;
    color: #526d79;
    font-size: 0.76rem;
}

.alerta-lista li {
    margin-bottom: 0;
}

.patients-modal .modal-content {
    border: 0;
    border-radius: 8px;
    overflow: hidden;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfd 100%);
    box-shadow: 0 24px 54px rgba(22, 51, 63, 0.22);
}

.patients-modal .modal-header {
    padding: 0.95rem 1.05rem 0.82rem;
    border-bottom: 0;
    background: #0f5c4a;
}

.patients-modal .modal-body {
    padding: 0.9rem 1rem 1rem;
}

.patients-modal .modal-title {
    font-size: 1rem;
    color: #ffffff;
}

.patients-modal .btn-close {
    filter: invert(1) grayscale(1) brightness(2);
    box-shadow: none;
}

.patients-modal .modal-header .text-muted {
    color: rgba(255, 255, 255, 0.78) !important;
}

.patient-modal-tabs {
    grid-column: 1 / -1;
    padding: 0.42rem;
    border: 1px solid rgba(15, 76, 92, 0.08);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 12px 26px rgba(24, 56, 69, 0.08);
}

.patient-modal-tabs .nav {
    display: grid;
    gap: 0.38rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.patient-modal-tabs .nav-link {
    min-height: 44px;
    border: 1px solid transparent;
    border-radius: 6px;
    color: #446473;
    font-size: 0.76rem;
    font-weight: 900;
    background: #eef6f3;
}

.patient-modal-tabs .nav-link.active {
    border-color: #0f5c4a;
    background: #0f5c4a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 92, 74, 0.22);
}

.patient-tab-content {
    grid-column: 1 / -1;
}

.patient-tab-pane {
    padding: 0.74rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 4px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

.patient-tab-pane .row {
    justify-content: flex-start;
}

.patients-form .form-control,
.patients-form .form-select {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.patients-form .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}

.patients-form-section {
    grid-column: 1 / -1;
    display: inline-flex;
    width: fit-content;
    margin: 0 0 0.1rem;
    padding: 0 0 0.14rem;
    border-bottom: 2px solid #0f5c4a;
    color: #0f5c4a;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
}

.patients-form .emergency-box {
    padding: 0.65rem;
    border: 1px solid rgba(15, 92, 74, 0.24);
    border-radius: 8px;
    background: #eef7f3;
}

.patients-form .emergency-box label {
    color: #0f5c4a;
    font-weight: 800;
}

.patients-hint {
    color: #68828f;
    font-size: 0.72rem;
}

.prontuario-link {
    font-size: 0.72rem;
}

.patient-save-popup {
    position: fixed;
    top: 82px;
    right: 18px;
    z-index: 2100;
    width: min(360px, calc(100vw - 36px));
    padding: 0.78rem 0.85rem;
    border: 1px solid rgba(15, 92, 74, 0.18);
    border-left: 5px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 18px 42px rgba(22, 51, 63, 0.2);
    color: #1d3945;
}

.patient-save-popup strong,
.patient-save-popup span {
    display: block;
}

.patient-save-popup strong {
    color: #0f5c4a;
    font-size: 0.82rem;
    margin-bottom: 0.12rem;
}

.patient-save-popup span {
    font-size: 0.76rem;
}

.patient-save-popup button {
    position: absolute;
    top: 0.35rem;
    right: 0.42rem;
    width: 24px;
    height: 24px;
    border: 0;
    border-radius: 6px;
    background: #eef7f3;
    color: #0f5c4a;
    font-weight: 800;
    line-height: 1;
}

@media (max-width: 900px) {
    .patient-topbar,
    .patient-list-head {
        align-items: stretch;
        flex-direction: column;
    }

    .patient-top-actions {
        justify-content: flex-start;
    }

    .patient-list-panel {
        overflow-x: auto;
    }

    .patient-table {
        min-width: 920px;
    }

    .patient-modal-tabs .nav {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<?php include 'partials/menu.php'; ?>

<?php $isEditingPatient = (int) ($patientFormValues['patient_id'] ?? 0) > 0; ?>

<div class="container-fluid patient-shell">

<section class="patient-topbar">
<div>
<p class="patient-kicker">Cadastro de pacientes</p>
<h3>Pacientes</h3>
<p>Lista compacta com preferencia de agenda e situacao da guia.</p>
</div>
<div class="patient-top-actions">
<button type="submit" form="patientFilterForm" name="filtrar" value="1" class="btn btn-light text-secondary">Filtrar</button>
<a href="pacientes.php" class="btn btn-outline-light">Limpar filtro</a>
<button type="button" class="btn btn-outline-light" data-export-list onclick="appExportList('patientsExportArea', 'jpg', 'pacientes')">Exportar JPG</button>
<button type="button" class="btn btn-outline-light" data-export-list onclick="appExportList('patientsExportArea', 'pdf', 'pacientes')">Exportar PDF</button>
<button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#patientFormModal">+ Novo paciente</button>
</div>
</section>

<div class="patient-filter-card p-2">
<form method="GET" id="patientFilterForm" class="row g-2 align-items-end">
<div class="col-md-5">
<label class="form-label small text-muted">Paciente</label>
<input type="text" name="paciente" class="form-control" placeholder="Digite o nome" autocomplete="off" value="<?= app_h($patientFilters['paciente'] ?? '') ?>">
</div>
<div class="col-md-4">
<label class="form-label small text-muted">Plano</label>
<input type="text" name="plano" class="form-control" placeholder="Digite o plano" value="<?= app_h($patientFilters['plano'] ?? '') ?>">
</div>
<div class="col-md-3">
<label class="form-label small text-muted">Status</label>
<select name="status" class="form-select">
<option value="">Status</option>
<?php foreach (['Ativo', 'Sem guia', 'Prestes a ficar sem guia'] as $statusOption): ?>
<option value="<?= app_h($statusOption) ?>" <?= ($patientFilters['status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= app_h($statusOption) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="d-none">
<input type="hidden" name="filtrar" value="1">
</div>
</form>
</div>

<section class="patient-list-panel" id="patientsExportArea">
<div class="patient-list-head">
<h5>Lista enxuta</h5>
<span>Use os filtros acima e clique em Filtrar</span>
</div>

<table class="table patient-table">

<thead>

<tr>
<th>Paciente</th>
<th>Documento</th>
<th>Contato</th>
<th>Ultimo plano</th>
<th>Preferencia</th>
<th>Status</th>
<th>Acoes</th>
</tr>

</thead>

<tbody id="tbody">

<?php if ($shouldLoadPatients): ?>
<?php foreach ($patientRows as $patient): ?>
<tr class="<?= $isEditingPatient && (int) $patientFormValues['patient_id'] === (int) $patient['id'] ? 'is-active' : '' ?>">
<td>
    <div class="patient-name"><?= app_h((string) $patient['nome']) ?></div>
    <?php if (!empty($patient['data_nascimento'])): ?>
        <div class="patient-meta-line">Nasc.: <?= app_h((string) $patient['data_nascimento']) ?></div>
    <?php endif; ?>
</td>
<td>
    <div><?= app_h((string) ($patient['cpf'] ?: '-')) ?></div>
</td>
<td>
    <div><?= app_h((string) ($patient['telefone'] ?: '-')) ?></div>
    <?php if (!empty($patient['telefone_emergencia'])): ?>
        <div class="patient-emergency" title="Telefone usado quando o cliente passa mal">Emerg.: <?= app_h((string) $patient['telefone_emergencia']) ?></div>
    <?php endif; ?>
</td>
<td><?= app_h((string) $patient['plano']) ?></td>
<td>
    <div><?= app_h((string) ($patient['dia_preferencia'] ?: '-')) ?></div>
    <div class="patient-meta-line"><?= app_h((string) ($patient['horario_preferencia'] ?: '')) ?></div>
</td>
<td data-status="<?= app_h((string) $patient['status_text']) ?>">
    <span class="<?= app_h((string) $patient['status_class']) ?>"><?= app_h((string) $patient['status_text']) ?></span>
</td>
<td class="acoes">
    <a href="paciente_historico.php?paciente_id=<?= (int) $patient['id'] ?>" class="btn btn-sm btn-outline-success">Historico</a>
    <a href="paciente_fichas.php?paciente_id=<?= (int) $patient['id'] ?>" class="btn btn-sm btn-outline-primary">Fichas</a>
    <a href="pacientes.php?<?= app_h(app_patient_filter_query($patientFilters, ['patient_id' => $patient['id']])) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
    <form method="POST" action="excluir_paciente.php" class="d-inline" onsubmit="return confirm('Excluir este paciente?')">
        <input type="hidden" name="id" value="<?= (int) $patient['id'] ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
    </form>
</td>
</tr>
<?php endforeach; ?>

<?php if ($patientRows === []): ?>
<tr>
<td colspan="7" class="text-center py-4 text-muted">
Nenhum paciente encontrado para os filtros informados.
<div class="mt-2">
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#patientFormModal">Cadastrar primeiro paciente</button>
</div>
</td>
</tr>
<?php endif; ?>
<?php else: ?>
<tr>
<td colspan="7" class="text-center py-4 text-muted">
Use os filtros acima e clique em <strong>Filtrar</strong> para consultar os pacientes.
</td>
</tr>
<?php endif; ?>

</tbody>

<tfoot>
<tr>
<td colspan="7" id="total">Total: <?= $shouldLoadPatients ? count($patientRows) : 0 ?> pacientes</td>
</tr>
</tfoot>

</table>

</section>

<?php if ($shouldLoadPatients): ?>
<div class="row mt-3">
<div class="col-md-6 mb-3">
<div class="patient-alert-card is-danger">
<div class="patient-alert-title">
<span>Pacientes sem guia</span>
<span class="patient-alert-count"><?= count($semGuiaLista) ?></span>
</div>
<?php if ($semGuiaLista === []): ?>
<div class="small">Nenhum paciente sem guia no momento.</div>
<?php else: ?>
<ul class="mb-0 alerta-lista">
<?php foreach ($semGuiaLista as $name): ?>
<li><?= app_h($name) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="patient-alert-card is-warning">
<div class="patient-alert-title">
<span>Prestes a ficar sem guia</span>
<span class="patient-alert-count"><?= count($prestesLista) ?></span>
</div>
<?php if ($prestesLista === []): ?>
<div class="small">Nenhum paciente perto do fim da guia.</div>
<?php else: ?>
<ul class="mb-0 alerta-lista">
<?php foreach ($prestesLista as $name): ?>
<li><?= app_h($name) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</div>
</div>
<?php endif; ?>

</div>

<?php if (!empty($patientSaveError)): ?>
<div class="patient-save-popup" data-patient-save-popup role="alert">
    <button type="button" data-patient-save-popup-close aria-label="Fechar">x</button>
    <strong>Paciente nao salvo</strong>
    <span><?= app_h($patientSaveError) ?></span>
</div>
<?php endif; ?>

<div class="modal fade patients-modal" id="patientFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><?= $isEditingPatient ? 'Editar paciente' : 'Novo paciente' ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="row g-3 patients-form">
                    <?php if ($isEditingPatient): ?>
                        <input type="hidden" name="patient_id" value="<?= (int) $patientFormValues['patient_id'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="action" value="save_patient">
                    <input type="hidden" name="filter_paciente" value="<?= app_h($patientFilters['paciente'] ?? '') ?>">
                    <input type="hidden" name="filter_plano" value="<?= app_h($patientFilters['plano'] ?? '') ?>">
                    <input type="hidden" name="filter_status" value="<?= app_h($patientFilters['status'] ?? '') ?>">
                    <input type="hidden" name="filter_filtrar" value="<?= $shouldLoadPatients ? '1' : '' ?>">
                    <div class="patient-modal-tabs">
                        <ul class="nav nav-pills" id="patientTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="patientIdentityTab" data-bs-toggle="pill" data-bs-target="#patientIdentityPane" type="button" role="tab">Identificacao</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="patientAddressTab" data-bs-toggle="pill" data-bs-target="#patientAddressPane" type="button" role="tab">Endereco</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="patientClinicTab" data-bs-toggle="pill" data-bs-target="#patientClinicPane" type="button" role="tab">Clinica</button>
                            </li>
                        </ul>
                    </div>
                    <div class="patient-tab-content tab-content">
                        <div class="tab-pane fade show active patient-tab-pane" id="patientIdentityPane" role="tabpanel" aria-labelledby="patientIdentityTab">
                            <div class="row g-3">
                    <div class="patients-form-section">Dados principais</div>
                    <div class="col-md-8">
                        <label class="form-label small text-muted">Nome</label>
                        <input type="text" name="nome" class="form-control" data-page-autofocus="1" data-tab-autofocus value="<?= app_h((string) $patientFormValues['nome']) ?>" title="Nome completo do paciente usado nas agendas, guias e relatórios.">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= app_h((string) $patientFormValues['telefone']) ?>" title="Telefone para contato da secretaria e confirmação de agendamentos.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">CPF</label>
                        <input type="text" name="cpf" class="form-control" data-mask-cpf value="<?= app_h((string) $patientFormValues['cpf']) ?>" placeholder="000.000.000-00" maxlength="14" title="CPF do paciente. Se preenchido, o sistema confere se o CPF e valido.">
                        <div class="invalid-feedback">CPF invalido.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Data de nascimento</label>
                        <input type="text" name="data_nascimento" class="form-control" data-mask-date value="<?= app_h((string) $patientFormValues['data_nascimento']) ?>" placeholder="dd/mm/aaaa" maxlength="10" title="Data de nascimento no formato brasileiro: dia/mes/ano.">
                    </div>
                    <div class="col-md-3">
                        <div class="emergency-box">
                            <label class="form-label small">Telefone de emergencia</label>
                            <input type="text" name="telefone_emergencia" class="form-control" value="<?= app_h((string) $patientFormValues['telefone_emergencia']) ?>" title="Usado quando o cliente passa mal ou precisa de contato urgente.">
                            <div class="small text-danger mt-1">Usado quando o cliente passa mal.</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Quem indicou</label>
                        <input type="text" name="indicado_por" class="form-control" value="<?= app_h((string) $patientFormValues['indicado_por']) ?>" title="Nome da pessoa, profissional, empresa ou origem que indicou a clinica para o paciente.">
                    </div>
                            </div>
                        </div>
                        <div class="tab-pane fade patient-tab-pane" id="patientAddressPane" role="tabpanel" aria-labelledby="patientAddressTab">
                            <div class="row g-3">
                    <div class="patients-form-section">Endereco</div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">CEP</label>
                        <input type="text" name="cep" class="form-control" data-mask-cep data-tab-autofocus value="<?= app_h((string) $patientFormValues['cep']) ?>" placeholder="00000-000" maxlength="9" title="Digite o CEP primeiro. Ao sair do campo, o sistema tenta preencher o endereco automaticamente.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Endereco</label>
                        <input type="text" name="endereco" class="form-control" value="<?= app_h((string) $patientFormValues['endereco']) ?>" title="Rua, avenida ou logradouro do paciente.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Numero</label>
                        <input type="text" name="numero" class="form-control" value="<?= app_h((string) $patientFormValues['numero']) ?>" title="Numero do imovel do paciente.">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Complemento</label>
                        <input type="text" name="complemento" class="form-control" value="<?= app_h((string) $patientFormValues['complemento']) ?>" title="Apartamento, sala, bloco ou referencia adicional.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Bairro</label>
                        <input type="text" name="bairro" class="form-control" value="<?= app_h((string) $patientFormValues['bairro']) ?>" title="Bairro do endereco do paciente.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Cidade</label>
                        <input type="text" name="cidade" class="form-control" value="<?= app_h((string) $patientFormValues['cidade']) ?>" title="Cidade do endereco do paciente.">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">UF</label>
                        <input type="text" name="estado" class="form-control text-uppercase" value="<?= app_h((string) $patientFormValues['estado']) ?>" maxlength="2" title="Sigla do estado, exemplo: TO, GO, DF.">
                    </div>
                            </div>
                        </div>
                        <div class="tab-pane fade patient-tab-pane" id="patientClinicPane" role="tabpanel" aria-labelledby="patientClinicTab">
                            <div class="row g-3">
                    <div class="patients-form-section">Agenda, prontuario e observacoes</div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Dia da semana de preferencia</label>
                        <select name="dia_preferencia" class="form-select" data-tab-autofocus title="Dia preferido para orientar agendamentos futuros.">
                            <option value="">Nenhuma</option>
                            <?php foreach ($patientDayOptions as $option): ?>
                                <option value="<?= app_h($option) ?>" <?= (string) $patientFormValues['dia_preferencia'] === $option ? 'selected' : '' ?>><?= app_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Horario de preferencia</label>
                        <input type="time" name="horario_preferencia" class="form-control" value="<?= app_h((string) $patientFormValues['horario_preferencia']) ?>" title="Horário preferido para orientar a agenda da clínica.">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Prontuario (link Word)</label>
                        <input type="text" name="prontuario" class="form-control" value="<?= app_h((string) $patientFormValues['prontuario']) ?>" title="Link ou caminho do prontuário externo do paciente, quando existir.">
                    </div>
                    <?php if ($isEditingPatient && !empty($patientFormValues['prontuario'])): ?>
                        <div class="col-12">
                            <a href="<?= app_h((string) $patientFormValues['prontuario']) ?>" target="_blank" class="btn btn-sm btn-outline-info prontuario-link">Abrir prontuario</a>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label small text-muted">Observacao</label>
                        <textarea name="observacoes" class="form-control" rows="3" title="Observacoes gerais importantes sobre o paciente."><?= app_h((string) $patientFormValues['observacoes']) ?></textarea>
                    </div>
                    <?php if ($isEditingPatient): ?>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <a href="paciente_historico.php?paciente_id=<?= (int) $patientFormValues['patient_id'] ?>" class="btn btn-sm btn-outline-success">Historico de atendimentos</a>
                            <a href="paciente_fichas.php?paciente_id=<?= (int) $patientFormValues['patient_id'] ?>" class="btn btn-sm btn-outline-primary">Fichas de fisioterapia</a>
                        </div>
                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <span class="patients-hint">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</span>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
                            <button class="btn btn-primary px-4"><?= $isEditingPatient ? 'Salvar alteracoes' : 'Salvar paciente' ?> <span class="small">(Alt+S)</span></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="assets/list-export.js"></script>
<script>
document.addEventListener('keydown', function (event) {
    const modalElement = document.getElementById('patientFormModal');
    const form = modalElement ? modalElement.querySelector('form') : null;

    if (!modalElement || !form || !modalElement.classList.contains('show')) return;

    if (event.altKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        form.requestSubmit();
    }
});

function focusFirstPatientField(targetSelector) {
    const pane = targetSelector
        ? document.querySelector(targetSelector)
        : document.querySelector('#patientFormModal .patient-tab-pane.show.active');

    if (!pane) {
        return;
    }

    const field = pane.querySelector('[data-tab-autofocus], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');

    if (!field) {
        return;
    }

    window.setTimeout(() => {
        field.focus({ preventScroll: true });

        if (typeof field.select === 'function' && field.tagName !== 'SELECT') {
            field.select();
        }
    }, 80);
}

function openPatientTab(targetSelector) {
    const trigger = document.querySelector('#patientTabs [data-bs-target="' + targetSelector + '"]');

    if (trigger && typeof bootstrap !== 'undefined') {
        bootstrap.Tab.getOrCreateInstance(trigger).show();
        return;
    }

    focusFirstPatientField(targetSelector);
}

document.querySelectorAll('#patientTabs [data-bs-toggle="pill"]').forEach((tabButton) => {
    tabButton.addEventListener('shown.bs.tab', (event) => {
        focusFirstPatientField(event.target.getAttribute('data-bs-target'));
    });
});

document.getElementById('patientFormModal')?.addEventListener('shown.bs.modal', () => {
    focusFirstPatientField();
});

function setupPatientSavePopup(patientSavePopup) {
    if (!patientSavePopup) {
        return;
    }

    patientSavePopup.querySelector('[data-patient-save-popup-close]')?.addEventListener('click', () => {
        patientSavePopup.remove();
    });

    window.setTimeout(() => {
        patientSavePopup.remove();
    }, 6500);
}

function showPatientSavePopup(message) {
    let popup = document.querySelector('[data-patient-save-popup]');

    if (!popup) {
        popup = document.createElement('div');
        popup.className = 'patient-save-popup';
        popup.setAttribute('data-patient-save-popup', '1');
        popup.setAttribute('role', 'alert');
        popup.innerHTML = '<button type="button" data-patient-save-popup-close aria-label="Fechar">x</button><strong>Paciente nao salvo</strong><span></span>';
        document.body.appendChild(popup);
    }

    const messageElement = popup.querySelector('span');

    if (messageElement) {
        messageElement.textContent = message;
    }

    setupPatientSavePopup(popup);
}

setupPatientSavePopup(document.querySelector('[data-patient-save-popup]'));

function onlyDigits(value) {
    return String(value || '').replace(/\D+/g, '');
}

function maskCpf(value) {
    const digits = onlyDigits(value).slice(0, 11);
    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
}

function maskDateBr(value) {
    const digits = onlyDigits(value).slice(0, 8);
    return digits
        .replace(/^(\d{2})(\d)/, '$1/$2')
        .replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
}

function maskCep(value) {
    const digits = onlyDigits(value).slice(0, 8);
    return digits.replace(/^(\d{5})(\d)/, '$1-$2');
}

function cpfIsValid(value) {
    const digits = onlyDigits(value);

    if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) {
        return false;
    }

    for (let position = 9; position <= 10; position++) {
        let sum = 0;

        for (let index = 0; index < position; index++) {
            sum += Number(digits[index]) * ((position + 1) - index);
        }

        let check = (sum * 10) % 11;
        check = check === 10 ? 0 : check;

        if (check !== Number(digits[position])) {
            return false;
        }
    }

    return true;
}

document.querySelectorAll('[data-mask-cpf]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = maskCpf(input.value);
        input.classList.remove('is-invalid');
    });
    input.addEventListener('blur', () => {
        if (input.value.trim() !== '' && !cpfIsValid(input.value)) {
            input.classList.add('is-invalid');
        }
    });
});

document.querySelectorAll('[data-mask-date]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = maskDateBr(input.value);
    });
});

document.querySelectorAll('[data-mask-cep]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = maskCep(input.value);
    });

    input.addEventListener('blur', async () => {
        const cep = onlyDigits(input.value);

        if (cep.length !== 8) {
            return;
        }

        try {
            const response = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
            const data = await response.json();

            if (!data || data.erro) {
                return;
            }

            const form = input.closest('form');
            const fields = {
                endereco: data.logradouro || '',
                bairro: data.bairro || '',
                cidade: data.localidade || '',
                estado: data.uf || '',
            };

            Object.entries(fields).forEach(([name, value]) => {
                const field = form.querySelector('[name="' + name + '"]');

                if (field && field.value.trim() === '') {
                    field.value = value;
                }
            });
        } catch (error) {
            // CEP lookup is a convenience; manual typing continues normally if offline.
        }
    });
});

document.querySelector('.patients-form')?.addEventListener('submit', (event) => {
    const nameInput = event.currentTarget.querySelector('[name="nome"]');
    const cpfInput = event.currentTarget.querySelector('[data-mask-cpf]');

    if (nameInput && nameInput.value.trim() === '') {
        showPatientSavePopup('Informe o nome do paciente.');
        openPatientTab('#patientIdentityPane');
        window.setTimeout(() => nameInput.focus({ preventScroll: true }), 100);
        event.preventDefault();
        return;
    }

    if (cpfInput && cpfInput.value.trim() !== '' && !cpfIsValid(cpfInput.value)) {
        cpfInput.classList.add('is-invalid');
        showPatientSavePopup('Informe um CPF valido.');
        openPatientTab('#patientIdentityPane');
        window.setTimeout(() => cpfInput.focus({ preventScroll: true }), 100);
        event.preventDefault();
    }
});
</script>

<?php if (!empty($autoOpenPatientModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('patientFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
