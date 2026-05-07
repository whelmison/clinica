<?php
include 'config/db.php';

if (app_request_method() !== 'POST') {
    app_redirect('guias.php');
}

$id = app_post_int('id');

if ($id <= 0) {
    app_redirect('guias.php');
}

$pdo = app_pdo();
$clinicId = app_active_clinic_id();
$check = $pdo->prepare('SELECT COUNT(*) FROM atendimentos WHERE clinica_id = :clinic_id AND guia_id = :id');
$check->execute([':clinic_id' => $clinicId, ':id' => $id]);

if ((int) $check->fetchColumn() > 0) {
    app_flash('danger', 'Nao e possivel excluir esta guia pois ja possui atendimentos vinculados.');
    app_redirect('guias.php');
}

$delete = $pdo->prepare('DELETE FROM guias WHERE clinica_id = :clinic_id AND id = :id');
$delete->execute([':clinic_id' => $clinicId, ':id' => $id]);

app_flash('success', 'Guia excluida com sucesso.');
app_redirect('guias.php');
