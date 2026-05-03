<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payment.php';

require_login();
require_permission('payments.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'method' => (string) ($_GET['method'] ?? 'all'),
    'status' => (string) ($_GET['status'] ?? 'all'),
    'staff_id' => (string) ($_GET['staff_id'] ?? 'all'),
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to' => (string) ($_GET['date_to'] ?? ''),
];

$rows = Payment::exportRows($filters);
$filename = 'payments-ledger-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'wb');

if ($output === false) {
    exit;
}

if ($rows === []) {
    fputcsv($output, ['No payment rows matched the current filters.']);
    fclose($output);
    exit;
}

fputcsv($output, array_keys($rows[0]));

foreach ($rows as $row) {
    fputcsv($output, array_values($row));
}

fclose($output);
