<?php
include 'config/db.php';

$term = trim((string) ($_GET['q'] ?? ''));
$digits = preg_replace('/\D+/', '', $term);

if (strlen($term) < 2) {
    app_json(['pacientes' => []]);
}

$cpfDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.cpf, ''), '.', ''), '-', ''), '/', ''), ' ', '')";
$phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
$emergencyDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone_emergencia, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
$birthDateSql = "DATE_FORMAT(p.data_nascimento, '%d/%m/%Y')";
$birthIsoSql = "DATE_FORMAT(p.data_nascimento, '%Y-%m-%d')";
$birthDigitsSql = "DATE_FORMAT(p.data_nascimento, '%d%m%Y')";
$termLike = '%' . $term . '%';
$searchClauses = ['p.nome LIKE ?', $birthDateSql . ' LIKE ?', $birthIsoSql . ' LIKE ?'];
$types = 'isss';
$params = [app_active_clinic_id(), $termLike, $termLike, $termLike];
$orderSql = 'p.nome';
$orderTypes = '';
$orderParams = [];
$scopeWhere = '';

if (strlen($digits) >= 2) {
    $digitLike = '%' . $digits . '%';
    $searchClauses[] = $cpfDigitsSql . ' LIKE ?';
    $searchClauses[] = $phoneDigitsSql . ' LIKE ?';
    $searchClauses[] = $emergencyDigitsSql . ' LIKE ?';
    $searchClauses[] = $birthDigitsSql . ' LIKE ?';
    $types .= 'ssss';
    $params[] = $digitLike;
    $params[] = $digitLike;
    $params[] = $digitLike;
    $params[] = $digitLike;

    $orderSql = 'CASE
        WHEN ' . $birthDigitsSql . ' LIKE ? THEN 0
        WHEN ' . $cpfDigitsSql . ' LIKE ? THEN 1
        WHEN ' . $phoneDigitsSql . ' LIKE ? THEN 2
        WHEN ' . $emergencyDigitsSql . ' LIKE ? THEN 3
        ELSE 4
    END, p.nome';
    $orderTypes = 'ssss';
    $orderParams = [$digitLike, $digitLike, $digitLike, $digitLike];
}

if (app_is_professional_user()) {
    $scopeWhere = app_professional_scope_exists_for_patient('p.id');
}

if ($orderTypes !== '') {
    $types .= $orderTypes;
    $params = array_merge($params, $orderParams);
}

$rows = app_stmt_all(
    $conn,
    'SELECT DISTINCT p.id, p.nome, p.telefone, p.cpf, p.data_nascimento, p.dia_preferencia, p.horario_preferencia
     FROM pacientes p
     WHERE p.clinica_id = ?
     AND (' . implode(' OR ', $searchClauses) . ')
     ' . $scopeWhere . '
     ORDER BY ' . $orderSql . '
     LIMIT 12',
    $types,
    $params
);

app_json([
    'pacientes' => array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'nome' => (string) $row['nome'],
        'telefone' => (string) ($row['telefone'] ?? ''),
        'cpf' => (string) ($row['cpf'] ?? ''),
        'data_nascimento' => !empty($row['data_nascimento']) ? app_date_br((string) $row['data_nascimento']) : '',
        'dia_preferencia' => (string) ($row['dia_preferencia'] ?? ''),
        'horario_preferencia' => (string) ($row['horario_preferencia'] ?? ''),
    ], $rows),
]);
