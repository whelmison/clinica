<?php
include 'config/db.php';

$plans = app_stmt_all(
    $conn,
    'SELECT id, nome, valor_sessao FROM planos WHERE clinica_id = ? ORDER BY nome',
    'i',
    [app_active_clinic_id()]
);

app_json([
    'planos' => array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'nome' => (string) $row['nome'],
        'valor_sessao' => (float) $row['valor_sessao'],
    ], $plans),
]);
