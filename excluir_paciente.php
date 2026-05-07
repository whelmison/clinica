<?php
include 'config/db.php';

if (app_request_method() !== 'POST') {
    app_redirect('pacientes.php');
}

$id = app_post_int('id');

if ($id <= 0) {
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
