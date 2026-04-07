<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    rebuildConfiguredDatabase();
    echo "Database rebuilt from schema.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Database rebuild failed: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
