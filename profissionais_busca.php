<?php
include 'config/db.php';

$term = trim((string) ($_GET['q'] ?? ''));
$digits = preg_replace('/\D+/', '', $term);

if (strlen($term) < 2) {
    app_json(['profissionais' => []]);
}

$phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
$termLike = '%' . $term . '%';
$termStart = $term . '%';
$termWord = '% ' . $term . '%';
$searchClauses = [
    'p.nome LIKE ?',
    'p.nome LIKE ?',
    'p.profissao LIKE ?',
    'p.telefone LIKE ?',
];
$types = 'issss';
$params = [app_active_clinic_id(), $termStart, $termWord, $termLike, $termLike];
$orderSql = 'CASE
    WHEN p.nome LIKE ? THEN 0
    WHEN p.nome LIKE ? THEN 1
    WHEN p.profissao LIKE ? THEN 2
    WHEN p.telefone LIKE ? THEN 3
    ELSE 4
END, p.nome';
$orderTypes = 'ssss';
$orderParams = [$termStart, $termWord, $termLike, $termLike];

if (strlen($digits) >= 2) {
    $digitLike = '%' . $digits . '%';
    $searchClauses[] = $phoneDigitsSql . ' LIKE ?';
    $types .= 's';
    $params[] = $digitLike;
    $orderSql = 'CASE
        WHEN p.nome LIKE ? THEN 0
        WHEN p.nome LIKE ? THEN 1
        WHEN ' . $phoneDigitsSql . ' LIKE ? THEN 2
        WHEN p.profissao LIKE ? THEN 3
        ELSE 4
    END, p.nome';
    $orderTypes = 'ssss';
    $orderParams = [$termStart, $termWord, $digitLike, $termLike];
}

$types .= $orderTypes;
$params = array_merge($params, $orderParams);

$rows = app_stmt_all(
    $conn,
    'SELECT p.id, p.nome, p.telefone, p.profissao
     FROM profissionais p
     WHERE p.clinica_id = ?
       AND (' . implode(' OR ', $searchClauses) . ')
     ORDER BY ' . $orderSql . '
     LIMIT 12',
    $types,
    $params
);

app_json([
    'profissionais' => array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'nome' => (string) $row['nome'],
        'telefone' => (string) ($row['telefone'] ?? ''),
        'profissao' => (string) ($row['profissao'] ?? ''),
    ], $rows),
]);
