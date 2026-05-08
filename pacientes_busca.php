<?php
include 'config/db.php';

$term = trim((string) ($_GET['q'] ?? ''));

if (strlen($term) < 2) {
    app_json(['pacientes' => []]);
}

$types = 'is';
$params = [app_active_clinic_id(), '%' . $term . '%'];
$scopeJoin = '';
$scopeWhere = '';

if (app_is_professional_user()) {
    $professionalId = app_current_professional_id();

    if ($professionalId === null) {
        app_json(['pacientes' => []]);
    }

    $scopeJoin = ' INNER JOIN guias g ON g.paciente_id = p.id AND g.clinica_id = p.clinica_id ';
    $scopeWhere = ' AND g.profissional_id = ? ';
    $types .= 'i';
    $params[] = $professionalId;
}

$rows = app_stmt_all(
    $conn,
    'SELECT DISTINCT p.id, p.nome, p.telefone, p.dia_preferencia, p.horario_preferencia
     FROM pacientes p
     ' . $scopeJoin . '
     WHERE p.clinica_id = ?
     AND p.nome LIKE ?
     ' . $scopeWhere . '
     ORDER BY p.nome
     LIMIT 12',
    $types,
    $params
);

app_json([
    'pacientes' => array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'nome' => (string) $row['nome'],
        'telefone' => (string) ($row['telefone'] ?? ''),
        'dia_preferencia' => (string) ($row['dia_preferencia'] ?? ''),
        'horario_preferencia' => (string) ($row['horario_preferencia'] ?? ''),
    ], $rows),
]);
