<?php
include 'config/db.php';

$clinicId = app_active_clinic_id();
$patientId = app_request_method() === 'POST' ? app_post_int('paciente_id') : app_query_int('paciente_id');
$activeTab = app_request_query('tab', 'avaliacao') ?? 'avaliacao';

if ($patientId <= 0) {
    app_flash('danger', 'Paciente nao informado.');
    app_redirect('pacientes.php');
}

$patient = app_stmt_one(
    $conn,
    'SELECT p.*
     FROM pacientes p
     WHERE p.clinica_id = ?
       AND p.id = ?
       ' . app_professional_scope_exists_for_patient('p.id') . '
     LIMIT 1',
    'ii',
    [$clinicId, $patientId]
);

if (!$patient) {
    app_flash('danger', 'Paciente nao encontrado ou sem permissao.');
    app_redirect('pacientes.php');
}

function patient_sheet_text(string $key): string
{
    return trim((string) app_request_post($key, ''));
}

function patient_sheet_date(string $key): ?string
{
    $value = trim((string) app_request_post($key, ''));

    if ($value === '') {
        return date('Y-m-d');
    }

    return app_parse_date_br($value);
}

function patient_sheet_summary(?string $value, int $limit): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...');
    }

    return strlen($value) > $limit ? substr($value, 0, max(0, $limit - 3)) . '...' : $value;
}

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'save_avaliacao') {
        $dataAvaliacao = patient_sheet_date('data_avaliacao');

        if ($dataAvaliacao === null) {
            app_flash('danger', 'Informe a data da avaliacao no formato dd/mm/aaaa.');
            app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
        }

        $profissionalId = app_post_int('profissional_id') ?: null;
        $ok = app_stmt_execute(
            $conn,
            'INSERT INTO paciente_fichas_avaliacao (
                clinica_id,
                paciente_id,
                profissional_id,
                data_avaliacao,
                sexo,
                queixa_principal,
                historia_pregressa,
                historia_atual,
                lesoes_previas,
                historia_cirurgica,
                avaliacao_postura,
                amplitude_movimento,
                forca_muscular,
                sensibilidade,
                equilibrio_marcha,
                avds,
                objetivos,
                condutas,
                observacoes_finais,
                assinatura_fisioterapeuta
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iiisssssssssssssssss',
            [
                $clinicId,
                $patientId,
                $profissionalId,
                $dataAvaliacao,
                patient_sheet_text('sexo'),
                patient_sheet_text('queixa_principal'),
                patient_sheet_text('historia_pregressa'),
                patient_sheet_text('historia_atual'),
                patient_sheet_text('lesoes_previas'),
                patient_sheet_text('historia_cirurgica'),
                patient_sheet_text('avaliacao_postura'),
                patient_sheet_text('amplitude_movimento'),
                patient_sheet_text('forca_muscular'),
                patient_sheet_text('sensibilidade'),
                patient_sheet_text('equilibrio_marcha'),
                patient_sheet_text('avds'),
                patient_sheet_text('objetivos'),
                patient_sheet_text('condutas'),
                patient_sheet_text('observacoes_finais'),
                patient_sheet_text('assinatura_fisioterapeuta'),
            ]
        );

        app_flash($ok ? 'success' : 'danger', $ok ? 'Ficha de avaliacao salva.' : 'Nao foi possivel salvar a ficha de avaliacao.');
        app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
    }

    if ($action === 'save_evolucao') {
        $dataEvolucao = patient_sheet_date('data_evolucao');

        if ($dataEvolucao === null) {
            app_flash('danger', 'Informe a data da evolucao no formato dd/mm/aaaa.');
            app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'evolucao']));
        }

        $atendimentoId = app_post_int('atendimento_id') ?: null;
        $profissionalId = app_post_int('profissional_id') ?: null;
        $condutas = patient_sheet_text('condutas_observacoes');

        if ($condutas === '') {
            app_flash('danger', 'Informe as condutas realizadas e observacoes.');
            app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'evolucao']));
        }

        $ok = app_stmt_execute(
            $conn,
            'INSERT INTO paciente_fichas_evolucao (
                clinica_id,
                paciente_id,
                atendimento_id,
                profissional_id,
                data_evolucao,
                condutas_observacoes,
                assinatura_fisioterapeuta
            ) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iiiisss',
            [
                $clinicId,
                $patientId,
                $atendimentoId,
                $profissionalId,
                $dataEvolucao,
                $condutas,
                patient_sheet_text('assinatura_fisioterapeuta'),
            ]
        );

        app_flash($ok ? 'success' : 'danger', $ok ? 'Evolucao diaria salva.' : 'Nao foi possivel salvar a evolucao diaria.');
        app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'evolucao']));
    }
}

$professionals = app_fetch_profissionais($conn);
$currentProfessionalId = app_current_professional_id();

if ($currentProfessionalId !== null && app_is_professional_user()) {
    $professionals = array_values(array_filter($professionals, static fn (array $row): bool => (int) $row['id'] === $currentProfessionalId));
}

$attendanceOptions = app_stmt_all(
    $conn,
    'SELECT a.id,
            a.data,
            COALESCE(pr.nome, pr_ag.nome, "Nao informado") AS profissional_nome,
            COALESCE(s.nome, pl.nome, a.tipo, "Atendimento") AS servico_nome
     FROM atendimentos a
     LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
     LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
     LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
     LEFT JOIN agenda ag ON ag.id = a.agenda_id AND ag.clinica_id = a.clinica_id
     LEFT JOIN profissionais pr_ag ON pr_ag.id = ag.profissional_id AND pr_ag.clinica_id = ag.clinica_id
     LEFT JOIN servicos s ON s.id = ag.servico_id AND s.clinica_id = ag.clinica_id
     WHERE a.clinica_id = ?
       AND a.paciente_id = ?
     ORDER BY a.data DESC, a.id DESC
     LIMIT 60',
    'ii',
    [$clinicId, $patientId]
);

$evaluations = app_stmt_all(
    $conn,
    'SELECT fa.*, pr.nome AS profissional_nome
     FROM paciente_fichas_avaliacao fa
     LEFT JOIN profissionais pr ON pr.id = fa.profissional_id AND pr.clinica_id = fa.clinica_id
     WHERE fa.clinica_id = ?
       AND fa.paciente_id = ?
     ORDER BY fa.data_avaliacao DESC, fa.id DESC',
    'ii',
    [$clinicId, $patientId]
);

$evolutions = app_stmt_all(
    $conn,
    'SELECT fe.*, pr.nome AS profissional_nome
     FROM paciente_fichas_evolucao fe
     LEFT JOIN profissionais pr ON pr.id = fe.profissional_id AND pr.clinica_id = fe.clinica_id
     WHERE fe.clinica_id = ?
       AND fe.paciente_id = ?
     ORDER BY fe.data_evolucao DESC, fe.id DESC',
    'ii',
    [$clinicId, $patientId]
);

$activeTab = in_array($activeTab, ['avaliacao', 'evolucao'], true) ? $activeTab : 'avaliacao';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Fichas do paciente</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.patient-sheets-shell {
    padding: 0.7rem 0.9rem 1.2rem;
}

.patient-sheets-shell .page-hero {
    padding: 0.88rem 1rem;
    border-radius: 8px;
    background: #0f5c4a;
}

.sheet-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfd 100%);
    box-shadow: 0 20px 44px rgba(24, 56, 69, 0.1);
    overflow: hidden;
}

.sheet-card .card-header {
    padding: 0.68rem 0.82rem;
    background: #ffffff;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.sheet-card .card-body {
    padding: 0.82rem;
}

.sheet-form .form-control,
.sheet-form .form-select {
    min-height: 38px;
    border-radius: 12px;
    border-color: #d7e3e7;
    font-size: 0.82rem;
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.sheet-form textarea.form-control {
    min-height: 82px;
}

.sheet-form .btn,
.patient-sheets-shell .page-hero .btn {
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 700;
}

.sheet-card > .card-header > .nav .nav-link {
    min-height: 38px;
    border-radius: 6px;
    color: #315a68;
    font-size: 0.78rem;
    font-weight: 900;
    background: #eef7f3;
}

.sheet-card > .card-header > .nav .nav-link.active {
    background: #0f5c4a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 92, 74, 0.22);
}

.sheet-inner-tabs {
    grid-column: 1 / -1;
    display: grid;
    gap: 0.42rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 0.3rem;
    padding: 0.42rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 12px 26px rgba(24, 56, 69, 0.07);
}

.sheet-inner-tabs .nav-link {
    width: 100%;
    min-height: 42px;
    border: 1px solid transparent;
    border-radius: 6px;
    color: #4f6c78;
    font-size: 0.74rem;
    font-weight: 900;
    background: #eef7f3;
}

.sheet-inner-tabs .nav-link.active {
    border-color: #0f5c4a;
    background: #0f5c4a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 92, 74, 0.2);
}

.sheet-inner-content {
    grid-column: 1 / -1;
    width: 100%;
}

.sheet-pane {
    padding: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 4px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

.sheet-block-title {
    display: inline-flex;
    width: fit-content;
    margin: 0 0 0.15rem;
    padding: 0 0 0.14rem;
    border-bottom: 2px solid #0f5c4a;
    color: #0f5c4a;
    font-size: 0.68rem;
    font-weight: 800;
}

.sheet-list {
    display: grid;
    gap: 0.55rem;
}

.sheet-list-item {
    padding: 0.62rem 0.72rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 5px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

.sheet-list-item small {
    color: #68828f;
}

.sheet-side-panel {
    padding: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: #f3faf7;
}

#evolucao .sheet-form {
    padding: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 4px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

@media (max-width: 900px) {
    .sheet-inner-tabs {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid patient-sheets-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <p class="mb-1 small text-white-50 text-uppercase fw-bold">Fisioterapia</p>
                <h3 class="mb-1">Fichas de <?= app_h((string) $patient['nome']) ?></h3>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-light btn-sm px-3" href="paciente_historico.php?paciente_id=<?= (int) $patientId ?>">Historico</a>
                <a class="btn btn-outline-light btn-sm px-3" href="pacientes.php?<?= app_h(app_build_query(['paciente' => (string) $patient['nome'], 'filtrar' => 1, 'patient_id' => $patientId])) ?>">Editar paciente</a>
                <a class="btn btn-outline-light btn-sm px-3" href="pacientes.php">Voltar</a>
            </div>
        </div>
    </section>

    <div class="sheet-card mt-3">
        <div class="card-header">
            <ul class="nav nav-pills gap-2" id="sheetTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'avaliacao' ? 'active' : '' ?>" id="avaliacao-tab" data-bs-toggle="pill" data-bs-target="#avaliacao" type="button" role="tab">Avaliacao</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'evolucao' ? 'active' : '' ?>" id="evolucao-tab" data-bs-toggle="pill" data-bs-target="#evolucao" type="button" role="tab">Evolucao diaria</button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade <?= $activeTab === 'avaliacao' ? 'show active' : '' ?>" id="avaliacao" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-xl-7">
                            <form method="POST" class="row g-2 sheet-form">
                                <input type="hidden" name="action" value="save_avaliacao">
                                <input type="hidden" name="paciente_id" value="<?= (int) $patientId ?>">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Data da avaliacao</label>
                                    <input type="text" name="data_avaliacao" class="form-control" data-mask-date value="<?= app_h(date('d/m/Y')) ?>" title="Data da avaliacao no formato dd/mm/aaaa.">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Profissional</label>
                                    <select name="profissional_id" class="form-select" title="Fisioterapeuta responsavel pela avaliacao.">
                                        <option value="">Nao informado</option>
                                        <?php foreach ($professionals as $professional): ?>
                                            <option value="<?= (int) $professional['id'] ?>" <?= $currentProfessionalId !== null && (int) $professional['id'] === $currentProfessionalId ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Sexo</label>
                                    <input type="text" name="sexo" class="form-control" title="Sexo informado na ficha de avaliacao.">
                                </div>

                                <ul class="nav nav-pills sheet-inner-tabs" id="evaluationInnerTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="evalHistoryTab" data-bs-toggle="pill" data-bs-target="#evalHistoryPane" type="button" role="tab">Historico</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="evalExamTab" data-bs-toggle="pill" data-bs-target="#evalExamPane" type="button" role="tab">Exame fisico</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="evalPlanTab" data-bs-toggle="pill" data-bs-target="#evalPlanPane" type="button" role="tab">Plano</button>
                                    </li>
                                </ul>
                                <div class="sheet-inner-content tab-content">
                                    <div class="tab-pane fade show active sheet-pane" id="evalHistoryPane" role="tabpanel" aria-labelledby="evalHistoryTab">
                                        <div class="row g-2">
                                <div class="col-12 sheet-block-title">Historico</div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Queixa principal</label>
                                    <textarea name="queixa_principal" class="form-control" title="Motivo principal relatado pelo paciente."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Historia pregressa</label>
                                    <textarea name="historia_pregressa" class="form-control" title="Historico anterior relevante para o tratamento."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Historia atual</label>
                                    <textarea name="historia_atual" class="form-control" title="Evolucao atual da queixa e sintomas."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Lesoes previas</label>
                                    <textarea name="lesoes_previas" class="form-control" title="Lesoes anteriores relatadas pelo paciente."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Historia cirurgica</label>
                                    <textarea name="historia_cirurgica" class="form-control" title="Cirurgias anteriores relevantes."></textarea>
                                </div>

                                        </div>
                                    </div>
                                    <div class="tab-pane fade sheet-pane" id="evalExamPane" role="tabpanel" aria-labelledby="evalExamTab">
                                        <div class="row g-2">
                                <div class="col-12 sheet-block-title">Exame fisico</div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Avaliacao da postura</label>
                                    <textarea name="avaliacao_postura" class="form-control" title="Postura, alinhamentos e observacoes gerais."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Amplitude de movimento</label>
                                    <textarea name="amplitude_movimento" class="form-control" title="Amplitude de movimento por regiao avaliada."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Forca muscular</label>
                                    <textarea name="forca_muscular" class="form-control" title="Forca muscular por grupo avaliado."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Sensibilidade</label>
                                    <textarea name="sensibilidade" class="form-control" title="Sensibilidade tatil, termica e dolorosa."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Equilibrio e marcha</label>
                                    <textarea name="equilibrio_marcha" class="form-control" title="Equilibrio estatico, dinamico e marcha."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">AVDs</label>
                                    <textarea name="avds" class="form-control" title="Atividades de vida diaria e grau de independencia."></textarea>
                                </div>

                                        </div>
                                    </div>
                                    <div class="tab-pane fade sheet-pane" id="evalPlanPane" role="tabpanel" aria-labelledby="evalPlanTab">
                                        <div class="row g-2">
                                <div class="col-12 sheet-block-title">Plano terapeutico</div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Objetivos</label>
                                    <textarea name="objetivos" class="form-control" title="Objetivos terapeuticos definidos para o paciente."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Condutas</label>
                                    <textarea name="condutas" class="form-control" title="Condutas planejadas para o tratamento."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Observacoes finais</label>
                                    <textarea name="observacoes_finais" class="form-control" title="Observacoes e consideracoes finais da avaliacao."></textarea>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">Assinatura do fisioterapeuta</label>
                                    <input type="text" name="assinatura_fisioterapeuta" class="form-control" title="Nome do fisioterapeuta responsavel pela assinatura.">
                                </div>
                                <div class="col-md-4 d-flex align-items-end justify-content-end">
                                    <button class="btn btn-primary px-4">Salvar avaliacao</button>
                                </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-5">
                            <div class="sheet-side-panel">
                            <h6 class="mb-2">Avaliacoes salvas</h6>
                            <div class="sheet-list">
                                <?php foreach ($evaluations as $item): ?>
                                    <div class="sheet-list-item">
                                        <strong><?= app_h(app_date_br((string) $item['data_avaliacao'])) ?></strong>
                                        <small class="d-block"><?= app_h((string) ($item['profissional_nome'] ?: 'Profissional nao informado')) ?></small>
                                        <div class="small mt-1"><?= app_h(patient_sheet_summary((string) ($item['queixa_principal'] ?: $item['observacoes_finais'] ?: 'Sem resumo'), 170)) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($evaluations === []): ?>
                                    <div class="text-muted small">Nenhuma avaliacao cadastrada para este paciente.</div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $activeTab === 'evolucao' ? 'show active' : '' ?>" id="evolucao" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-xl-6">
                            <form method="POST" class="row g-2 sheet-form">
                                <input type="hidden" name="action" value="save_evolucao">
                                <input type="hidden" name="paciente_id" value="<?= (int) $patientId ?>">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Data</label>
                                    <input type="text" name="data_evolucao" class="form-control" data-mask-date value="<?= app_h(date('d/m/Y')) ?>" title="Data da evolucao no formato dd/mm/aaaa.">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">Atendimento vinculado</label>
                                    <select name="atendimento_id" class="form-select" title="Opcional. Vincule esta evolucao a um atendimento ja realizado.">
                                        <option value="">Sem vinculo direto</option>
                                        <?php foreach ($attendanceOptions as $attendance): ?>
                                            <option value="<?= (int) $attendance['id'] ?>">
                                                <?= app_h(app_date_br((string) $attendance['data']) . ' - ' . (string) $attendance['servico_nome'] . ' - ' . (string) $attendance['profissional_nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">Profissional</label>
                                    <select name="profissional_id" class="form-select" title="Fisioterapeuta responsavel por esta evolucao.">
                                        <option value="">Nao informado</option>
                                        <?php foreach ($professionals as $professional): ?>
                                            <option value="<?= (int) $professional['id'] ?>" <?= $currentProfessionalId !== null && (int) $professional['id'] === $currentProfessionalId ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Condutas realizadas e observacoes</label>
                                    <textarea name="condutas_observacoes" class="form-control" rows="6" required title="Registre as condutas realizadas, evolucao do paciente e observacoes do dia."></textarea>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">Assinatura do fisioterapeuta</label>
                                    <input type="text" name="assinatura_fisioterapeuta" class="form-control" title="Nome do fisioterapeuta responsavel pela assinatura.">
                                </div>
                                <div class="col-md-4 d-flex align-items-end justify-content-end">
                                    <button class="btn btn-primary px-4">Salvar evolucao</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-6">
                            <div class="sheet-side-panel">
                            <h6 class="mb-2">Evolucoes salvas</h6>
                            <div class="sheet-list">
                                <?php foreach ($evolutions as $item): ?>
                                    <div class="sheet-list-item">
                                        <strong><?= app_h(app_date_br((string) $item['data_evolucao'])) ?></strong>
                                        <small class="d-block"><?= app_h((string) ($item['profissional_nome'] ?: 'Profissional nao informado')) ?></small>
                                        <div class="small mt-1"><?= app_h(patient_sheet_summary((string) ($item['condutas_observacoes'] ?: 'Sem resumo'), 220)) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($evolutions === []): ?>
                                    <div class="text-muted small">Nenhuma evolucao cadastrada para este paciente.</div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sheetDigits(value) {
    return String(value || '').replace(/\D+/g, '');
}

document.querySelectorAll('[data-mask-date]').forEach((input) => {
    input.addEventListener('input', () => {
        const digits = sheetDigits(input.value).slice(0, 8);
        input.value = digits
            .replace(/^(\d{2})(\d)/, '$1/$2')
            .replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
    });
});
</script>

</body>
</html>
