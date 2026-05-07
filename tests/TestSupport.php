<?php

declare(strict_types=1);

function test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' | esperado: ' . var_export($expected, true) . ' obtido: ' . var_export($actual, true));
    }
}
