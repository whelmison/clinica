<?php
include 'config/db.php';

if (app_request_method() !== 'POST') {
    app_json(['ok' => false, 'message' => 'Metodo invalido.'], 405);
}

if (app_is_professional_user() || !app_has_any_role(['secretaria', 'administrativo', 'desenvolvedor'])) {
    app_json(['ok' => false, 'message' => 'Glosa e uma marcacao administrativa.'], 403);
}

$id = app_post_int('id');
$status = trim((string) ($_POST['status'] ?? 'Realizado'));
$allowedStatuses = ['Realizado', 'Glosado'];

if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
    app_json(['ok' => false, 'message' => 'Dados invalidos.'], 422);
}

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
        app_json(['ok' => false, 'message' => 'Profissional nao identificado.'], 403);
    }

    $sql .= ' AND g.profissional_id = :professional_id';
    $params[':professional_id'] = $professionalId;
}

$stmt = $pdo->prepare($sql . ' LIMIT 1');
$stmt->execute($params);

if (!$stmt->fetch()) {
    app_json(['ok' => false, 'message' => 'Atendimento nao encontrado.'], 404);
}

$update = $pdo->prepare('UPDATE atendimentos SET status_atendimento = :status WHERE clinica_id = :clinic_id AND id = :id');
$update->execute([
    ':clinic_id' => $clinicId,
    ':status' => $status,
    ':id' => $id,
]);

app_json(['ok' => true, 'message' => 'Status atualizado com sucesso.']);
