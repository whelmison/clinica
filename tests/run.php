<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestSupport.php';

$testFiles = [
    __DIR__ . '/AgendaSmokeTest.php',
    __DIR__ . '/LoginSmokeTest.php',
    __DIR__ . '/FinanceiroSmokeTest.php',
];

$failures = [];
$total = 0;

foreach ($testFiles as $file) {
    $tests = require $file;

    foreach ($tests as $name => $test) {
        $total++;

        try {
            $test();
            fwrite(STDOUT, "[OK] {$name}\n");
        } catch (Throwable $exception) {
            $failures[] = "[FALHA] {$name}: {$exception->getMessage()}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }

    fwrite(STDERR, "Resumo: " . count($failures) . " falha(s) em {$total} teste(s).\n");
    exit(1);
}

fwrite(STDOUT, "Resumo: {$total} teste(s) executado(s) com sucesso.\n");
