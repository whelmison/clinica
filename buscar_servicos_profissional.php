<?php include 'config/db.php'; ?>
<?php
header('Content-Type: application/json; charset=UTF-8');

$professionalId = (int) ($_GET['profissional_id'] ?? 0);
$planId = app_query_int('plano_id') ?: null;

if ($professionalId <= 0) {
    echo json_encode([]);
    exit;
}

echo json_encode(app_services_for_professional($conn, $professionalId, $planId));
