<?php
include 'config/db.php';

$pdo = app_pdo();

$data = json_decode(file_get_contents("php://input"), true);

$appointmentId = $data['appointment_id'] ?? null;
$horario = $data['horario'] ?? null;

if (!$appointmentId || !$horario) {
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE appointments 
    SET data_horario = ? 
    WHERE id = ?
");

$ok = $stmt->execute([$horario, $appointmentId]);

echo json_encode(['ok' => $ok]);