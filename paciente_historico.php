<?php
include 'config/db.php';

$clinicId = app_active_clinic_id();
$patientId = app_query_int('paciente_id');

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

$historyRows = app_stmt_all(
    $conn,
    'SELECT a.id,
            a.data,
            a.status_atendimento,
            a.tipo,
            g.codigo AS guia_codigo,
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
     LIMIT 250',
    'ii',
    [$clinicId, $patientId]
);

$fichaCounts = app_stmt_one(
    $conn,
    'SELECT
        (SELECT COUNT(*) FROM paciente_fichas_avaliacao fa WHERE fa.clinica_id = ? AND fa.paciente_id = ?) AS avaliacoes,
        (SELECT COUNT(*) FROM paciente_fichas_evolucao fe WHERE fe.clinica_id = ? AND fe.paciente_id = ?) AS evolucoes',
    'iiii',
    [$clinicId, $patientId, $clinicId, $patientId]
) ?: ['avaliacoes' => 0, 'evolucoes' => 0];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Historico do paciente</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.patient-history-shell {
    padding: 0.7rem 0.9rem 1.2rem;
}

.patient-history-shell .page-hero {
    padding: 0.88rem 1rem;
    border-radius: 18px;
}

.history-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.history-card .card-header {
    padding: 0.68rem 0.82rem;
    background: transparent;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.history-card .card-body {
    padding: 0.82rem;
}

.history-meta {
    color: #68828f;
    font-size: 0.76rem;
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid patient-history-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <p class="mb-1 small text-white-50 text-uppercase fw-bold">Paciente</p>
                <h3 class="mb-1"><?= app_h((string) $patient['nome']) ?></h3>
                <p>Historico resumido dos atendimentos com profissional, servico e data.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if (app_is_professional_user()): ?>
                <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="paciente_fichas.php?paciente_id=<?= (int) $patientId ?>">Fichas</a>
                <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="index.php">Voltar</a>
                <?php else: ?>
                <a class="btn btn-light btn-sm rounded-pill px-3" href="pacientes.php?<?= app_h(app_build_query(['paciente' => (string) $patient['nome'], 'filtrar' => 1, 'patient_id' => $patientId])) ?>">Editar paciente</a>
                <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="pacientes.php">Voltar</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="history-card h-100">
                <div class="card-header">
                    <h5 class="mb-0 fs-6">Resumo do cadastro</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Telefone</strong>
                        <div class="history-meta"><?= app_h((string) ($patient['telefone'] ?? '-')) ?></div>
                    </div>
                    <div class="mb-2">
                        <strong>CPF/CNPJ</strong>
                        <div class="history-meta"><?= app_h((string) ($patient['cpf'] ?? '-')) ?></div>
                    </div>
                    <div class="mb-2">
                        <strong>Nascimento</strong>
                        <div class="history-meta"><?= app_h(app_date_br((string) ($patient['data_nascimento'] ?? ''))) ?></div>
                    </div>
                    <div class="mb-2">
                        <strong>Emergencia</strong>
                        <div class="history-meta"><?= app_h((string) ($patient['telefone_emergencia'] ?? '-')) ?></div>
                    </div>
                    <div>
                        <strong>Fichas</strong>
                        <div class="history-meta"><?= (int) $fichaCounts['avaliacoes'] ?> avaliacao(oes), <?= (int) $fichaCounts['evolucoes'] ?> evolucao(oes)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="history-card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5 class="mb-0 fs-6">Atendimentos</h5>
                    <span class="text-muted small"><?= count($historyRows) ?> registro(s)</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Profissional</th>
                                    <th>Servico</th>
                                    <th>Guia</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historyRows as $row): ?>
                                    <tr>
                                        <td><?= app_h(app_date_br((string) $row['data'])) ?></td>
                                        <td><?= app_h((string) $row['profissional_nome']) ?></td>
                                        <td><?= app_h((string) $row['servico_nome']) ?></td>
                                        <td><?= app_h((string) ($row['guia_codigo'] ?: '-')) ?></td>
                                        <td><?= app_h((string) ($row['status_atendimento'] ?: '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($historyRows === []): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Nenhum atendimento registrado para este paciente.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
