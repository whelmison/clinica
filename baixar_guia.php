<?php
include 'config/db.php';

$id = app_query_int('id');
$clinicId = app_active_clinic_id();
$guia = app_stmt_one(
    $conn,
    'SELECT valor_guia, recebido FROM guias WHERE clinica_id = ? AND id = ? LIMIT 1',
    'ii',
    [$clinicId, $id]
);

if (!$guia) {
    die("Guia nao encontrada.");
}

$saldo = floatval($guia['valor_guia']) - floatval($guia['recebido']);

if ($saldo <= 0) {
    header('Location: guias.php');
    exit;
}

app_stmt_execute(
    $conn,
    'UPDATE guias SET recebido = recebido + ? WHERE clinica_id = ? AND id = ?',
    'dii',
    [$saldo, $clinicId, $id]
);

header('Location: recebimentos.php');
exit;
