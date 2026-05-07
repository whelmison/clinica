<?php
include 'config/db.php';

if (app_request_method() !== 'POST') {
    app_redirect('planos.php');
}

$id = app_post_int('id');

if ($id > 0) {
    $pdo = app_pdo();
    $delete = $pdo->prepare('DELETE FROM planos WHERE clinica_id = :clinic_id AND id = :id');
    $delete->execute([':clinic_id' => app_active_clinic_id(), ':id' => $id]);
    app_flash('success', 'Plano excluido com sucesso.');
}

app_redirect('planos.php');
