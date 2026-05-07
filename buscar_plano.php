<?php
include 'config/db.php';

$plano = $_GET['plano'];

$res = $conn->query("SELECT * FROM planos WHERE nome='$plano'");
$p = $res->fetch_assoc();

echo json_encode([
    "valor" => number_format($p['valor'],2,',','.')
]);