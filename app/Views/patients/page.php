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
    border-radius: 18px;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
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
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.34rem 0.68rem;
}

.patient-filter-card {
    margin-top: 0.6rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.patient-filter-card .form-control,
.patient-filter-card .form-select {
    min-height: 36px;
    border-radius: 12px;
    border-color: #dbe7ec;
    font-size: 0.78rem;
}

.patient-list-panel,
.patient-alert-card {
    margin-top: 0.6rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
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
    border-radius: 10px;
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
    border-radius: 999px;
    font-size: 0.66rem;
    font-weight: 800;
}

.status-ativo {
    background: rgba(31, 157, 109, 0.14);
    color: #187047;
}

.status-atencao {
    background: rgba(245, 166, 35, 0.15);
    color: #98630b;
}

.status-inativo {
    background: rgba(201, 67, 54, 0.12);
    color: #a43a30;
}

.acoes {
    display: flex;
    gap: 0.34rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.acoes .btn {
    border-radius: 999px;
    min-height: 28px;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.22rem 0.54rem;
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
    border-color: rgba(201, 67, 54, 0.14);
}

.patient-alert-card.is-warning {
    border-color: rgba(245, 166, 35, 0.18);
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
    border-radius: 999px;
    background: rgba(15, 76, 92, 0.1);
    color: #0f4c5c;
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
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}

.patients-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.patients-modal .modal-body {
    padding: 0.95rem 1rem 1rem;
}

.patients-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.patients-modal .btn-close {
    box-shadow: none;
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

.patients-hint {
    color: #68828f;
    font-size: 0.72rem;
}

.prontuario-link {
    font-size: 0.72rem;
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
<th>Ultimo plano</th>
<th>Dia preferencia</th>
<th>Horario preferencia</th>
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
    <?php if (!empty($patient['telefone'])): ?>
        <div class="small text-muted"><?= app_h((string) $patient['telefone']) ?></div>
    <?php endif; ?>
</td>
<td><?= app_h((string) $patient['plano']) ?></td>
<td><?= app_h((string) $patient['dia_preferencia']) ?></td>
<td><?= app_h((string) $patient['horario_preferencia']) ?></td>
<td data-status="<?= app_h((string) $patient['status_text']) ?>">
    <span class="<?= app_h((string) $patient['status_class']) ?>"><?= app_h((string) $patient['status_text']) ?></span>
</td>
<td class="acoes">
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
<td colspan="6" class="text-center py-4 text-muted">
Nenhum paciente encontrado para os filtros informados.
<div class="mt-2">
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#patientFormModal">Cadastrar primeiro paciente</button>
</div>
</td>
</tr>
<?php endif; ?>
<?php else: ?>
<tr>
<td colspan="6" class="text-center py-4 text-muted">
Use os filtros acima e clique em <strong>Filtrar</strong> para consultar os pacientes.
</td>
</tr>
<?php endif; ?>

</tbody>

<tfoot>
<tr>
<td colspan="6" id="total">Total: <?= $shouldLoadPatients ? count($patientRows) : 0 ?> pacientes</td>
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

<div class="modal fade patients-modal" id="patientFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1"><?= $isEditingPatient ? 'Editar paciente' : 'Novo paciente' ?></h5>
                    <div class="small text-muted">
                        <?= $isEditingPatient ? 'Atualize os dados do paciente sem sair da lista.' : 'Cadastro rapido de paciente em popup.' ?>
                    </div>
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
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Nome</label>
                        <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h((string) $patientFormValues['nome']) ?>" required title="Nome completo do paciente usado nas agendas, guias e relatórios.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= app_h((string) $patientFormValues['telefone']) ?>" title="Telefone para contato da secretaria e confirmação de agendamentos.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Dia da semana de preferencia</label>
                        <select name="dia_preferencia" class="form-select" title="Dia preferido para orientar agendamentos futuros.">
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
