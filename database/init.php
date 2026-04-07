<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    initializeConfiguredDatabase();
    echo "Database schema initialized.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Database init failed: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
