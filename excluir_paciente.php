<?php
include 'config/db.php';

if (app_request_method() !== 'POST') {
    app_redirect('pacientes.php');
}

$id = app_post_int('id');

if ($id <= 0) {
    app_redirect('pacientes.php');
}

$linkedSheets = app_stmt_one(
    $conn,
    'SELECT
        (SELECT COUNT(*) FROM paciente_fichas_avaliacao WHERE clinica_id = ? AND paciente_id = ?) +
        (SELECT COUNT(*) FROM paciente_fichas_evolucao WHERE clinica_id = ? AND paciente_id = ?) AS total',
    'iiii',
    [app_active_clinic_id(), $id, app_active_clinic_id(), $id]
);

if ((int) ($linkedSheets['total'] ?? 0) > 0) {
    app_flash('danger', 'Nao e possivel excluir este paciente pois ele possui fichas vinculadas.');
    app_redirect('pacientes.php');
}

$pdo = app_pdo();

try {
    $delete = $pdo->prepare('DELETE FROM pacientes WHERE clinica_id = :clinic_id AND id = :id');
    $delete->execute([':clinic_id' => app_active_clinic_id(), ':id' => $id]);
    app_flash('success', 'Paciente excluido com sucesso.');
} catch (Throwable $exception) {
    app_flash('danger', 'Nao e possivel excluir este paciente pois ele possui atendimentos ou guias vinculadas.');
}

app_redirect('pacientes.php');
