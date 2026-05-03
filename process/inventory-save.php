<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

require_login();
require_permission('inventory.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/inventory/list.php');
}

$action = trim((string) ($_POST['form_action'] ?? 'item'));

if ($action === 'movement') {
    $itemId = trim((string) ($_POST['item_id'] ?? ''));
    $returnTo = trim((string) ($_POST['return_to'] ?? '/inventory/movements.php'));
    $payload = [
        'item_id' => $itemId,
        'movement_date' => trim((string) ($_POST['movement_date'] ?? date('Y-m-d'))),
        'type' => trim((string) ($_POST['type'] ?? 'stock_in')),
        'quantity' => trim((string) ($_POST['quantity'] ?? '')),
        'reason' => trim((string) ($_POST['reason'] ?? '')),
        'service_id' => trim((string) ($_POST['service_id'] ?? '')),
        'recorded_by' => trim((string) ($_POST['recorded_by'] ?? 'Admin panel')),
    ];

    $errors = Inventory::validateMovement($payload);

    if ($errors !== []) {
        flash_set('inventory_movement_errors', $errors);
        remember_old_input($payload);
        redirect_to($returnTo);
    }

    $movement = Inventory::saveMovement($payload);
    clear_old_input();
    flash_set('inventory_success', 'Stock movement recorded successfully.');

    redirect_to($returnTo);
}

$itemId = trim((string) ($_POST['id'] ?? ''));
$selectedServices = $_POST['used_in_services'] ?? [];

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'sku' => trim((string) ($_POST['sku'] ?? '')),
    'category' => trim((string) ($_POST['category'] ?? 'Consumables')),
    'unit' => trim((string) ($_POST['unit'] ?? 'units')),
    'quantity' => trim((string) ($_POST['quantity'] ?? '0')),
    'reorder_level' => trim((string) ($_POST['reorder_level'] ?? '0')),
    'cost_per_unit' => trim((string) ($_POST['cost_per_unit'] ?? '0')),
    'supplier' => trim((string) ($_POST['supplier'] ?? '')),
    'location' => trim((string) ($_POST['location'] ?? 'Main storage')),
    'used_in_services' => is_array($selectedServices) ? $selectedServices : [],
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

$errors = Inventory::validateItem($payload, $itemId !== '' ? $itemId : null);

if ($errors !== []) {
    flash_set('inventory_item_errors', $errors);
    remember_old_input($payload);
    $redirect = $itemId === '' ? '/inventory/create.php' : '/inventory/edit.php?id=' . urlencode($itemId);
    redirect_to($redirect);
}

$item = Inventory::saveItem($payload, $itemId !== '' ? $itemId : null);
clear_old_input();
flash_set('inventory_success', $itemId === '' ? 'Inventory item created successfully.' : 'Inventory item updated successfully.');

redirect_to('/inventory/edit.php?id=' . urlencode($item['id']));
