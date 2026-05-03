<?php declare(strict_types=1);

require_once __DIR__ . '/Service.php';

final class Inventory
{
    public static function all(array $filters = []): array
    {
        $items = array_map(static fn (array $item): array => self::withStockMeta($item), array_values(self::mergedRecords()));
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = (string) ($filters['status'] ?? 'all');
        $category = (string) ($filters['category'] ?? 'all');

        $items = array_values(array_filter($items, static function (array $item) use ($search, $status, $category): bool {
            if ($status !== 'all' && $item['stock_status'] !== $status) {
                return false;
            }

            if ($category !== 'all' && $item['category'] !== $category) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $item['name'],
                $item['sku'],
                $item['category'],
                $item['supplier'],
                $item['location'],
                $item['notes'],
                implode(' ', $item['used_in_service_names']),
            ]));

            return str_contains($haystack, $search);
        }));

        usort($items, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $items;
    }

    public static function categories(): array
    {
        $categories = array_values(array_unique(array_map(static fn (array $item): string => $item['category'], array_values(self::mergedRecords()))));
        sort($categories);

        return $categories;
    }

    public static function movementTypes(): array
    {
        return ['stock_in', 'stock_out', 'adjustment', 'wastage', 'service_usage'];
    }

    public static function serviceOptions(): array
    {
        $services = array_map(static function (array $service): array {
            return [
                'id' => $service['id'],
                'name' => $service['name'],
            ];
        }, Service::all());

        usort($services, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $services;
    }

    public static function stats(): array
    {
        $items = self::all();
        $lowStock = array_filter($items, static fn (array $item): bool => $item['stock_status'] === 'low_stock');
        $outOfStock = array_filter($items, static fn (array $item): bool => $item['stock_status'] === 'out_of_stock');
        $value = array_sum(array_map(static fn (array $item): float => (float) $item['stock_value'], $items));

        return [
            ['label' => 'Inventory items', 'value' => (string) count($items), 'tone' => 'info'],
            ['label' => 'Low stock items', 'value' => (string) count($lowStock), 'tone' => 'warning'],
            ['label' => 'Out of stock', 'value' => (string) count($outOfStock), 'tone' => 'danger'],
            ['label' => 'Stock value', 'value' => format_money($value), 'tone' => 'success'],
        ];
    }

    public static function movementStats(): array
    {
        $movements = self::movements();
        $today = date('Y-m-d');
        $todayMovements = array_filter($movements, static fn (array $movement): bool => $movement['movement_date'] === $today);
        $serviceUsage = array_filter($movements, static fn (array $movement): bool => $movement['type'] === 'service_usage');
        $wastage = array_filter($movements, static fn (array $movement): bool => $movement['type'] === 'wastage');
        $itemsTouched = array_values(array_unique(array_map(static fn (array $movement): string => $movement['item_id'], $movements)));

        return [
            ['label' => 'Movements today', 'value' => (string) count($todayMovements), 'tone' => 'info'],
            ['label' => 'Items touched', 'value' => (string) count($itemsTouched), 'tone' => 'success'],
            ['label' => 'Service usage logs', 'value' => (string) count($serviceUsage), 'tone' => 'warning'],
            ['label' => 'Wastage events', 'value' => (string) count($wastage), 'tone' => 'danger'],
        ];
    }

    public static function lowStockSummary(): array
    {
        $items = self::lowStockItems();
        $replenishmentValue = 0.0;

        foreach ($items as $item) {
            $gap = max(0.0, (float) $item['reorder_level'] - (float) $item['on_hand']);
            $replenishmentValue += $gap * (float) $item['cost_per_unit'];
        }

        return [
            ['label' => 'Flagged items', 'value' => (string) count($items), 'tone' => 'warning'],
            ['label' => 'Critical stockouts', 'value' => (string) count(array_filter($items, static fn (array $item): bool => $item['stock_status'] === 'out_of_stock')), 'tone' => 'danger'],
            ['label' => 'Replenishment value', 'value' => format_money($replenishmentValue), 'tone' => 'info'],
        ];
    }

    public static function find(string $id): ?array
    {
        $item = self::mergedRecords()[$id] ?? null;

        return $item !== null ? self::withStockMeta($item) : null;
    }

    public static function lowStockItems(): array
    {
        return array_values(array_filter(self::all(), static fn (array $item): bool => in_array($item['stock_status'], ['low_stock', 'out_of_stock'], true)));
    }

    public static function movements(array $filters = []): array
    {
        $movements = array_map(static fn (array $movement): array => self::normalizeMovement($movement), array_values(self::mergedMovements()));
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $itemId = (string) ($filters['item_id'] ?? 'all');
        $type = (string) ($filters['type'] ?? 'all');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $movements = array_values(array_filter($movements, static function (array $movement) use ($search, $itemId, $type, $dateFrom, $dateTo): bool {
            if ($itemId !== 'all' && $movement['item_id'] !== $itemId) {
                return false;
            }

            if ($type !== 'all' && $movement['type'] !== $type) {
                return false;
            }

            if ($dateFrom !== '' && $movement['movement_date'] < $dateFrom) {
                return false;
            }

            if ($dateTo !== '' && $movement['movement_date'] > $dateTo) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $movement['reference'],
                $movement['item_name'],
                $movement['sku'],
                $movement['type'],
                $movement['reason'],
                $movement['recorded_by'],
                $movement['service_name'],
            ]));

            return str_contains($haystack, $search);
        }));

        usort($movements, static fn (array $left, array $right): int => strcmp($right['sort_key'], $left['sort_key']));

        return $movements;
    }

    public static function recentMovements(string $itemId, int $limit = 5): array
    {
        return array_slice(self::movements(['item_id' => $itemId]), 0, $limit);
    }

    public static function validateItem(array $payload, ?string $ignoreId = null): array
    {
        $errors = [];

        foreach (['name', 'sku', 'category', 'unit', 'reorder_level', 'cost_per_unit'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if ($ignoreId === null && trim((string) ($payload['quantity'] ?? '')) === '') {
            $errors['quantity'] = 'Opening quantity is required.';
        }

        foreach (['quantity', 'reorder_level', 'cost_per_unit'] as $numericField) {
            if (($payload[$numericField] ?? '') !== '' && (!is_numeric((string) $payload[$numericField]) || (float) $payload[$numericField] < 0)) {
                $errors[$numericField] = 'Enter a valid number that is zero or greater.';
            }
        }

        $sku = strtolower(trim((string) ($payload['sku'] ?? '')));

        foreach (self::mergedRecords() as $itemId => $item) {
            if ($ignoreId !== null && $itemId === $ignoreId) {
                continue;
            }

            if (strtolower((string) $item['sku']) === $sku && $sku !== '') {
                $errors['sku'] = 'SKU must be unique.';
                break;
            }
        }

        return $errors;
    }

    public static function validateMovement(array $payload): array
    {
        $errors = [];

        foreach (['item_id', 'movement_date', 'type', 'quantity', 'reason'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        $item = trim((string) ($payload['item_id'] ?? '')) !== '' ? self::find((string) $payload['item_id']) : null;
        $type = (string) ($payload['type'] ?? '');
        $quantity = (float) ($payload['quantity'] ?? 0);

        if ($item === null) {
            $errors['item_id'] = 'Select a valid inventory item.';
        }

        if (!in_array($type, self::movementTypes(), true)) {
            $errors['type'] = 'Select a valid movement type.';
        }

        if (!is_numeric((string) ($payload['quantity'] ?? '')) || $quantity < 0 || ($type !== 'adjustment' && $quantity <= 0)) {
            $errors['quantity'] = $type === 'adjustment'
                ? 'Counted quantity must be zero or greater.'
                : 'Quantity must be greater than zero.';
        }

        if ($item !== null && $errors === []) {
            $available = (float) $item['on_hand'];

            if (in_array($type, ['stock_out', 'wastage', 'service_usage'], true) && $quantity > $available) {
                $errors['quantity'] = 'This movement is larger than the stock currently on hand.';
            }
        }

        if ($type === 'service_usage' && trim((string) ($payload['service_id'] ?? '')) === '') {
            $errors['service_id'] = 'Choose the service consuming this item.';
        }

        return $errors;
    }

    public static function saveItem(array $payload, ?string $id = null): array
    {
        $records = $_SESSION['inventory_records'] ?? [];
        $existing = $id !== null ? self::find($id) : null;
        $itemId = $existing['id'] ?? self::nextId();
        $openingQuantity = round((float) ($payload['quantity'] ?? 0), 2);

        $item = [
            'id' => $itemId,
            'name' => trim((string) ($payload['name'] ?? ($existing['name'] ?? ''))),
            'sku' => strtoupper(trim((string) ($payload['sku'] ?? ($existing['sku'] ?? '')))),
            'category' => trim((string) ($payload['category'] ?? ($existing['category'] ?? 'Consumables'))),
            'unit' => trim((string) ($payload['unit'] ?? ($existing['unit'] ?? 'units'))),
            'on_hand' => $existing !== null ? (float) $existing['on_hand'] : 0.0,
            'reorder_level' => round((float) ($payload['reorder_level'] ?? ($existing['reorder_level'] ?? 0)), 2),
            'cost_per_unit' => round((float) ($payload['cost_per_unit'] ?? ($existing['cost_per_unit'] ?? 0)), 2),
            'supplier' => trim((string) ($payload['supplier'] ?? ($existing['supplier'] ?? ''))),
            'location' => trim((string) ($payload['location'] ?? ($existing['location'] ?? 'Main storage'))),
            'used_in_services' => self::normalizeServiceLinks($payload['used_in_services'] ?? ($existing['used_in_services'] ?? [])),
            'notes' => trim((string) ($payload['notes'] ?? ($existing['notes'] ?? ''))),
            'last_movement_at' => $existing['last_movement_at'] ?? null,
            'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        $records[$itemId] = $item;
        $_SESSION['inventory_records'] = $records;

        if ($existing === null && $openingQuantity > 0) {
            self::saveMovement([
                'item_id' => $itemId,
                'movement_date' => date('Y-m-d'),
                'type' => 'stock_in',
                'quantity' => (string) $openingQuantity,
                'reason' => 'Opening stock on item creation.',
                'recorded_by' => 'Admin panel',
                'service_id' => '',
            ]);
        }

        return self::find($itemId) ?? self::withStockMeta($item);
    }

    public static function saveMovement(array $payload): array
    {
        $item = self::find((string) $payload['item_id']);

        if ($item === null) {
            throw new RuntimeException('Inventory item not found.');
        }

        $type = (string) $payload['type'];
        $before = (float) $item['on_hand'];
        $quantity = round((float) $payload['quantity'], 2);
        $after = $before;
        $delta = 0.0;

        switch ($type) {
            case 'stock_in':
                $delta = $quantity;
                $after = $before + $quantity;
                break;
            case 'stock_out':
            case 'wastage':
            case 'service_usage':
                $delta = -$quantity;
                $after = max(0.0, $before - $quantity);
                break;
            case 'adjustment':
                $after = $quantity;
                $delta = $after - $before;
                break;
        }

        $records = $_SESSION['inventory_records'] ?? [];
        $records[$item['id']] = array_merge($records[$item['id']] ?? self::mergedRecords()[$item['id']], [
            'on_hand' => round($after, 2),
            'last_movement_at' => (string) $payload['movement_date'],
        ]);
        $_SESSION['inventory_records'] = $records;

        $movements = $_SESSION['inventory_movements'] ?? [];
        $movementId = self::nextMovementId();
        $movements[$movementId] = [
            'id' => $movementId,
            'reference' => 'MOV-' . preg_replace('/\D+/', '', $movementId),
            'item_id' => $item['id'],
            'movement_date' => (string) $payload['movement_date'],
            'type' => $type,
            'quantity' => $quantity,
            'delta' => round($delta, 2),
            'before_quantity' => round($before, 2),
            'after_quantity' => round($after, 2),
            'reason' => trim((string) $payload['reason']),
            'recorded_by' => trim((string) ($payload['recorded_by'] ?? 'Admin panel')),
            'service_id' => trim((string) ($payload['service_id'] ?? '')),
        ];
        $_SESSION['inventory_movements'] = $movements;

        return self::normalizeMovement($movements[$movementId]);
    }

    private static function withStockMeta(array $item): array
    {
        $status = self::statusForQuantity((float) $item['on_hand'], (float) $item['reorder_level']);
        $serviceMap = self::serviceNameMap();
        $usedServiceNames = [];

        foreach ((array) ($item['used_in_services'] ?? []) as $serviceId) {
            $usedServiceNames[] = $serviceMap[$serviceId] ?? 'Unknown service';
        }

        return $item + [
            'stock_status' => $status['status'],
            'stock_tone' => $status['tone'],
            'stock_value' => round((float) $item['on_hand'] * (float) $item['cost_per_unit'], 2),
            'reorder_gap' => max(0.0, (float) $item['reorder_level'] - (float) $item['on_hand']),
            'used_in_service_names' => $usedServiceNames,
        ];
    }

    private static function statusForQuantity(float $quantity, float $reorderLevel): array
    {
        if ($quantity <= 0) {
            return ['status' => 'out_of_stock', 'tone' => 'danger'];
        }

        if ($quantity <= $reorderLevel) {
            return ['status' => 'low_stock', 'tone' => 'warning'];
        }

        return ['status' => 'in_stock', 'tone' => 'success'];
    }

    private static function normalizeMovement(array $movement): array
    {
        $item = self::find((string) ($movement['item_id'] ?? ''));
        $serviceMap = self::serviceNameMap();
        $type = (string) ($movement['type'] ?? 'stock_in');

        return [
            'id' => (string) $movement['id'],
            'reference' => (string) ($movement['reference'] ?? ('MOV-' . preg_replace('/\D+/', '', (string) $movement['id']))),
            'item_id' => (string) ($movement['item_id'] ?? ''),
            'item_name' => $item['name'] ?? 'Unknown item',
            'sku' => $item['sku'] ?? '',
            'movement_date' => (string) ($movement['movement_date'] ?? date('Y-m-d')),
            'type' => $type,
            'quantity' => round((float) ($movement['quantity'] ?? 0), 2),
            'delta' => round((float) ($movement['delta'] ?? 0), 2),
            'before_quantity' => round((float) ($movement['before_quantity'] ?? 0), 2),
            'after_quantity' => round((float) ($movement['after_quantity'] ?? 0), 2),
            'reason' => (string) ($movement['reason'] ?? ''),
            'recorded_by' => (string) ($movement['recorded_by'] ?? 'Admin panel'),
            'service_id' => (string) ($movement['service_id'] ?? ''),
            'service_name' => $serviceMap[(string) ($movement['service_id'] ?? '')] ?? '',
            'type_tone' => match ($type) {
                'stock_in' => 'success',
                'stock_out', 'adjustment' => 'warning',
                'wastage' => 'danger',
                'service_usage' => 'info',
                default => 'info',
            },
            'sort_key' => ((string) ($movement['movement_date'] ?? date('Y-m-d'))) . ' ' . (string) ($movement['id'] ?? ''),
        ];
    }

    private static function normalizeServiceLinks(mixed $payload): array
    {
        $values = is_array($payload) ? $payload : [$payload];
        $values = array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $values));

        return array_values(array_unique($values));
    }

    private static function serviceNameMap(): array
    {
        $map = [];

        foreach (self::serviceOptions() as $service) {
            $map[$service['id']] = $service['name'];
        }

        return $map;
    }

    private static function mergedRecords(): array
    {
        $records = self::baseRecords();

        foreach ($_SESSION['inventory_records'] ?? [] as $id => $item) {
            $records[$id] = $item;
        }

        return $records;
    }

    private static function mergedMovements(): array
    {
        $movements = self::baseMovements();

        foreach ($_SESSION['inventory_movements'] ?? [] as $id => $movement) {
            $movements[$id] = $movement;
        }

        return $movements;
    }

    private static function nextId(): string
    {
        $max = 6000;

        foreach (array_keys(self::mergedRecords()) as $id) {
            $max = max($max, (int) preg_replace('/\D+/', '', $id));
        }

        return 'inv-' . ($max + 1);
    }

    private static function nextMovementId(): string
    {
        $max = 7000;

        foreach (array_keys(self::mergedMovements()) as $id) {
            $max = max($max, (int) preg_replace('/\D+/', '', $id));
        }

        return 'mov-' . ($max + 1);
    }

    private static function baseRecords(): array
    {
        return [
            'inv-lavender-oil' => [
                'id' => 'inv-lavender-oil',
                'name' => 'Lavender Treatment Oil',
                'sku' => 'INV-1001',
                'category' => 'Oils',
                'unit' => 'bottles',
                'on_hand' => 18.0,
                'reorder_level' => 10.0,
                'cost_per_unit' => 6.50,
                'supplier' => 'Herb & Calm Supply',
                'location' => 'Treatment bar shelf A',
                'used_in_services' => ['svc-swedish', 'svc-aroma'],
                'notes' => 'Primary calming oil used across reset and aromatherapy sessions.',
                'last_movement_at' => date('Y-m-d'),
                'created_at' => '2026-03-12 09:30:00',
            ],
            'inv-hot-stones' => [
                'id' => 'inv-hot-stones',
                'name' => 'Basalt Hot Stone Set',
                'sku' => 'INV-1002',
                'category' => 'Equipment',
                'unit' => 'sets',
                'on_hand' => 6.0,
                'reorder_level' => 4.0,
                'cost_per_unit' => 38.00,
                'supplier' => 'Stone Ritual Works',
                'location' => 'Stone suite cabinet',
                'used_in_services' => ['svc-hot-stone'],
                'notes' => 'Keep enough clean, heated sets available for premium stone bookings.',
                'last_movement_at' => date('Y-m-d', strtotime('-1 day')),
                'created_at' => '2026-03-02 11:15:00',
            ],
            'inv-linens' => [
                'id' => 'inv-linens',
                'name' => 'Massage Linen Set',
                'sku' => 'INV-1003',
                'category' => 'Linens',
                'unit' => 'sets',
                'on_hand' => 3.0,
                'reorder_level' => 8.0,
                'cost_per_unit' => 12.00,
                'supplier' => 'Soft Spa Textiles',
                'location' => 'Laundry staging',
                'used_in_services' => ['svc-swedish', 'svc-deep', 'svc-hot-stone', 'svc-aroma', 'svc-couples'],
                'notes' => 'Low stock increases turnover risk on busy same-day schedules.',
                'last_movement_at' => date('Y-m-d'),
                'created_at' => '2026-02-18 08:10:00',
            ],
            'inv-muscle-balm' => [
                'id' => 'inv-muscle-balm',
                'name' => 'Therapeutic Muscle Balm',
                'sku' => 'INV-1004',
                'category' => 'Topicals',
                'unit' => 'jars',
                'on_hand' => 11.0,
                'reorder_level' => 6.0,
                'cost_per_unit' => 9.25,
                'supplier' => 'Recovery Lab',
                'location' => 'Therapy room drawer',
                'used_in_services' => ['svc-deep'],
                'notes' => 'Used mainly for targeted deep-tissue finishes.',
                'last_movement_at' => date('Y-m-d', strtotime('-2 days')),
                'created_at' => '2026-03-20 10:45:00',
            ],
            'inv-candles' => [
                'id' => 'inv-candles',
                'name' => 'Aroma Warm Candles',
                'sku' => 'INV-1005',
                'category' => 'Ambience',
                'unit' => 'boxes',
                'on_hand' => 0.0,
                'reorder_level' => 4.0,
                'cost_per_unit' => 14.00,
                'supplier' => 'Calm Space Co',
                'location' => 'Front storage',
                'used_in_services' => ['svc-aroma', 'svc-couples'],
                'notes' => 'Out of stock and affecting the premium setup for aromatherapy rooms.',
                'last_movement_at' => date('Y-m-d', strtotime('-3 days')),
                'created_at' => '2026-02-27 16:20:00',
            ],
        ];
    }

    private static function baseMovements(): array
    {
        return [
            'mov-7001' => [
                'id' => 'mov-7001',
                'reference' => 'MOV-7001',
                'item_id' => 'inv-lavender-oil',
                'movement_date' => date('Y-m-d'),
                'type' => 'stock_out',
                'quantity' => 2.0,
                'delta' => -2.0,
                'before_quantity' => 20.0,
                'after_quantity' => 18.0,
                'reason' => 'Front desk issued two bottles into active treatment rooms.',
                'recorded_by' => 'Operations desk',
                'service_id' => '',
            ],
            'mov-7002' => [
                'id' => 'mov-7002',
                'reference' => 'MOV-7002',
                'item_id' => 'inv-linens',
                'movement_date' => date('Y-m-d'),
                'type' => 'service_usage',
                'quantity' => 4.0,
                'delta' => -4.0,
                'before_quantity' => 7.0,
                'after_quantity' => 3.0,
                'reason' => 'High-turnover bookings consumed fresh linen sets.',
                'recorded_by' => 'Laundry station',
                'service_id' => 'svc-couples',
            ],
            'mov-7003' => [
                'id' => 'mov-7003',
                'reference' => 'MOV-7003',
                'item_id' => 'inv-hot-stones',
                'movement_date' => date('Y-m-d', strtotime('-1 day')),
                'type' => 'adjustment',
                'quantity' => 6.0,
                'delta' => -1.0,
                'before_quantity' => 7.0,
                'after_quantity' => 6.0,
                'reason' => 'One damaged set removed after physical count.',
                'recorded_by' => 'Studio manager',
                'service_id' => '',
            ],
            'mov-7004' => [
                'id' => 'mov-7004',
                'reference' => 'MOV-7004',
                'item_id' => 'inv-muscle-balm',
                'movement_date' => date('Y-m-d', strtotime('-2 days')),
                'type' => 'stock_in',
                'quantity' => 6.0,
                'delta' => 6.0,
                'before_quantity' => 5.0,
                'after_quantity' => 11.0,
                'reason' => 'Supplier delivery received and shelved.',
                'recorded_by' => 'Receiving desk',
                'service_id' => '',
            ],
            'mov-7005' => [
                'id' => 'mov-7005',
                'reference' => 'MOV-7005',
                'item_id' => 'inv-candles',
                'movement_date' => date('Y-m-d', strtotime('-3 days')),
                'type' => 'wastage',
                'quantity' => 1.0,
                'delta' => -1.0,
                'before_quantity' => 1.0,
                'after_quantity' => 0.0,
                'reason' => 'One box damaged by heat during storage.',
                'recorded_by' => 'Studio manager',
                'service_id' => '',
            ],
        ];
    }
}
