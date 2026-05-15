<?php
include 'config/db.php';

$plans = app_stmt_all(
    $conn,
    'SELECT id, nome FROM planos WHERE clinica_id = ? ORDER BY nome',
    'i',
    [app_active_clinic_id()]
);

app_json([
    'planos' => array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'nome' => (string) $row['nome'],
    ], $plans),
]);
