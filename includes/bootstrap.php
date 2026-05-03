<?php declare(strict_types=1);

$appConfig = require __DIR__ . '/../config/app.php';

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$dbConnectorPath = __DIR__ . '/../func/connect.php';
if (is_file($dbConnectorPath)) {
    require_once $dbConnectorPath;
}
