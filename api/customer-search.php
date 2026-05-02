<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

require_login();
require_permission('customers.view');

$query = (string) ($_GET['q'] ?? '');

header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    [
        'query' => $query,
        'results' => array_map(static function (array $customer): array {
            return [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'preference' => $customer['preference'],
                'booking_count' => $customer['booking_count'],
            ];
        }, Customer::search($query)),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
