<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/domain.php';
require_once __DIR__ . '/../app/bootstrap.php';

$dbConfig = app_db_config();
$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database'],
    $dbConfig['port']
);

if ($conn->connect_error) {
    fwrite(STDERR, "Erro de conexao: {$conn->connect_error}\n");
    exit(1);
}

$conn->set_charset($dbConfig['charset']);
$conn->query('CREATE TABLE IF NOT EXISTS app_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(190) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$migrationFiles = glob(__DIR__ . '/../migrations/*.php') ?: [];
sort($migrationFiles, SORT_NATURAL);

if ($migrationFiles === []) {
    fwrite(STDOUT, "Nenhuma migracao encontrada.\n");
    exit(0);
}

$applied = [];
$result = $conn->query('SELECT migration FROM app_migrations ORDER BY migration');

while ($row = $result?->fetch_assoc()) {
    $applied[$row['migration']] = true;
}

$insert = $conn->prepare('INSERT INTO app_migrations (migration) VALUES (?)');

foreach ($migrationFiles as $file) {
    $migrationName = basename($file);

    if (isset($applied[$migrationName])) {
        fwrite(STDOUT, "Ja aplicada: {$migrationName}\n");
        continue;
    }

    $migration = require $file;

    if (!is_callable($migration)) {
        fwrite(STDERR, "Migracao invalida: {$migrationName}\n");
        exit(1);
    }

    try {
        $migration($conn);
        $insert->bind_param('s', $migrationName);
        $insert->execute();
        fwrite(STDOUT, "Aplicada: {$migrationName}\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "Falha em {$migrationName}: {$exception->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Migracoes concluidas.\n");
