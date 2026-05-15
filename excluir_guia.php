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
$guideStmt = $pdo->prepare(
    'SELECT g.*, COALESCE(a.total_atendimentos, 0) AS total_atendimentos
     FROM guias g
     LEFT JOIN (
        SELECT guia_id, COUNT(*) AS total_atendimentos
        FROM atendimentos
        WHERE clinica_id = :attendance_clinic_id
        GROUP BY guia_id
     ) a ON a.guia_id = g.id
     WHERE g.clinica_id = :clinic_id AND g.id = :id
     LIMIT 1'
);
$guideStmt->execute([
    ':attendance_clinic_id' => $clinicId,
    ':clinic_id' => $clinicId,
    ':id' => $id,
]);
$guide = $guideStmt->fetch();

if (!$guide) {
    app_flash('danger', 'Guia nao encontrada.');
    app_redirect('guias.php');
}

$statusData = app_guide_operational_status_data($guide);

if ((int) ($guide['total_atendimentos'] ?? 0) > 0 || $statusData['value'] === 'finalizada') {
    app_flash('danger', 'Nao e possivel excluir uma guia em uso ou finalizada.');
    app_redirect('guias.php');
}

$delete = $pdo->prepare('DELETE FROM guias WHERE clinica_id = :clinic_id AND id = :id');
$delete->execute([':clinic_id' => $clinicId, ':id' => $id]);

app_flash('success', 'Guia excluida com sucesso.');
app_redirect('guias.php');
