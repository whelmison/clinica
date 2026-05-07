<?php

require_once __DIR__ . '/../config/connection.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Clinic\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/Support/helpers.php';
