<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/connection.php';

$dbConfig = app_db_config();

$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database'],
    $dbConfig['port']
);

if ($conn->connect_error) {
    die('Erro: ' . $conn->connect_error);
}

$conn->set_charset($dbConfig['charset']);

require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/../app/bootstrap.php';

app_bootstrap($conn);
app_validate_csrf_request();
app_start_csrf_output_buffer();

$pdo = app_pdo();
