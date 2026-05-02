<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

require_login();
require_permission('dashboard.view');

header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    [
        'generatedAt' => date(DATE_ATOM),
        'data' => Report::dashboardOverview(),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
