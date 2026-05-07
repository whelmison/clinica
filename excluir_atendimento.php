<?php
include 'config/db.php';

if (app_request_method() !== 'POST') {
    app_redirect('atendimentos.php');
}

$id = app_post_int('id');
$returnUrl = $_SERVER['HTTP_REFERER'] ?? 'atendimentos.php';

if ($id > 0) {
    $pdo = app_pdo();
    $professionalId = app_current_professional_id();
    $clinicId = app_active_clinic_id();
    $sql = 'SELECT a.id
            FROM atendimentos a
            LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
            WHERE a.clinica_id = :clinic_id AND a.id = :id';
    $params = [':clinic_id' => $clinicId, ':id' => $id];

    if (app_is_professional_user()) {
        if ($professionalId === null) {
            app_flash('danger', 'Profissional nao identificado para excluir o atendimento.');
            app_redirect($returnUrl);
        }

        $sql .= ' AND g.profissional_id = :professional_id';
        $params[':professional_id'] = $professionalId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    if ($stmt->fetch()) {
        $delete = $pdo->prepare('DELETE FROM atendimentos WHERE clinica_id = :clinic_id AND id = :id');
        $delete->execute([':clinic_id' => $clinicId, ':id' => $id]);
        app_flash('success', 'Atendimento excluido com sucesso.');
    }
}

app_redirect($returnUrl);
