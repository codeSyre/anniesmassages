<?php declare(strict_types=1);

require_once __DIR__ . '/Service.php';

final class Inventory
{
    private static ?array $recordCache = null;

    public static function all(array $filters = []): array
    {
        $records   = self::databaseRecords();
        $serviceMap = self::serviceNameMap();
        $items     = array_values(array_filter(
            array_map(static fn (array $item): array => self::withStockMeta($item, $serviceMap), array_values($records)),
            static fn (array $item): bool => isset($item['name'])
        ));

        $search   = strtolower(trim((string) ($filters['search'] ?? '')));
        $status   = (string) ($filters['status'] ?? 'all');
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
                $item['name'], $item['sku'], $item['category'],
                $item['supplier'], $item['location'], $item['notes'],
                implode(' ', $item['used_in_service_names']),
            ]));
            return str_contains($haystack, $search);
        }));

        usort($items, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $items;
    }

    public static function categories(): array
    {
        return [
            'Ambience', 'Consumables', 'Equipment', 'Linens',
            'Oils', 'Packaging', 'Skincare', 'Topicals', 'Tools',
        ];
    }

    public static function storageLocations(): array
    {
        return [
            'Main storage',
            'Supply room',
            'Front storage',
            'Reception cabinet',
            'Laundry staging',
            'Treatment bar shelf A',
            'Treatment bar shelf B',
            'Therapy room drawer',
            'Stone suite cabinet',
            'Cold storage',
            'Retail display shelf',
            'Dispensary cabinet',
        ];
    }

    public static function locations(): array
    {
        return self::storageLocations();
    }

    public static function movementTypes(): array
    {
        return ['stock_in', 'stock_out', 'adjustment', 'wastage', 'service_usage'];
    }

    public static function serviceOptions(): array
    {
        $services = array_map(static fn (array $s): array => ['id' => $s['id'], 'name' => $s['name']], Service::all());
        usort($services, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        return $services;
    }

    public static function stats(): array
    {
        $items      = self::all();
        $lowStock   = array_filter($items, static fn (array $i): bool => $i['stock_status'] === 'low_stock');
        $outOfStock = array_filter($items, static fn (array $i): bool => $i['stock_status'] === 'out_of_stock');
        $value      = array_sum(array_map(static fn (array $i): float => (float) $i['stock_value'], $items));

        return [
            ['label' => 'Inventory items', 'value' => (string) count($items),      'tone' => 'info'],
            ['label' => 'Low stock items', 'value' => (string) count($lowStock),   'tone' => 'warning'],
            ['label' => 'Out of stock',    'value' => (string) count($outOfStock), 'tone' => 'danger'],
            ['label' => 'Stock value',     'value' => format_money($value),        'tone' => 'success'],
        ];
    }

    public static function movementStats(): array
    {
        $movements    = self::movements();
        $today        = date('Y-m-d');
        $todayMoves   = array_filter($movements, static fn (array $m): bool => $m['movement_date'] === $today);
        $serviceUsage = array_filter($movements, static fn (array $m): bool => $m['type'] === 'service_usage');
        $wastage      = array_filter($movements, static fn (array $m): bool => $m['type'] === 'wastage');
        $itemsTouched = array_unique(array_map(static fn (array $m): string => $m['item_id'], $movements));

        return [
            ['label' => 'Movements today',    'value' => (string) count($todayMoves),   'tone' => 'info'],
            ['label' => 'Items touched',       'value' => (string) count($itemsTouched), 'tone' => 'success'],
            ['label' => 'Service usage logs',  'value' => (string) count($serviceUsage), 'tone' => 'warning'],
            ['label' => 'Wastage events',      'value' => (string) count($wastage),      'tone' => 'danger'],
        ];
    }

    public static function lowStockSummary(): array
    {
        $items              = self::lowStockItems();
        $replenishmentValue = 0.0;
        foreach ($items as $item) {
            $replenishmentValue += max(0.0, (float) $item['reorder_level'] - (float) $item['on_hand']) * (float) $item['cost_per_unit'];
        }

        return [
            ['label' => 'Flagged items',        'value' => (string) count($items), 'tone' => 'warning'],
            ['label' => 'Critical stockouts',   'value' => (string) count(array_filter($items, static fn (array $i): bool => $i['stock_status'] === 'out_of_stock')), 'tone' => 'danger'],
            ['label' => 'Replenishment value',  'value' => format_money($replenishmentValue), 'tone' => 'info'],
        ];
    }

    public static function find(string $id): ?array
    {
        $item = self::databaseRecords()[$id] ?? null;
        if ($item === null) {
            return null;
        }
        return self::withStockMeta($item, self::serviceNameMap());
    }

    public static function lowStockItems(): array
    {
        return array_values(array_filter(self::all(), static fn (array $i): bool => in_array($i['stock_status'], ['low_stock', 'out_of_stock'], true)));
    }

    public static function movements(array $filters = []): array
    {
        $serviceMap = self::serviceNameMap();
        $movements  = array_map(static fn (array $m): array => self::normalizeMovement($m, $serviceMap), array_values(self::databaseMovements()));

        $search   = strtolower(trim((string) ($filters['search'] ?? '')));
        $itemId   = (string) ($filters['item_id'] ?? 'all');
        $type     = (string) ($filters['type'] ?? 'all');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo   = (string) ($filters['date_to'] ?? '');

        $movements = array_values(array_filter($movements, static function (array $m) use ($search, $itemId, $type, $dateFrom, $dateTo): bool {
            if ($itemId !== 'all' && $m['item_id'] !== $itemId) return false;
            if ($type !== 'all' && $m['type'] !== $type) return false;
            if ($dateFrom !== '' && $m['movement_date'] < $dateFrom) return false;
            if ($dateTo !== '' && $m['movement_date'] > $dateTo) return false;
            if ($search === '') return true;
            $haystack = strtolower(implode(' ', [$m['reference'], $m['item_name'], $m['sku'], $m['type'], $m['reason'], $m['recorded_by'], $m['service_name']]));
            return str_contains($haystack, $search);
        }));

        usort($movements, static fn (array $a, array $b): int => strcmp($b['sort_key'], $a['sort_key']));

        return $movements;
    }

    public static function recentMovements(string $itemId, int $limit = 5): array
    {
        return array_slice(self::movements(['item_id' => $itemId]), 0, $limit);
    }

    public static function findMovement(string $id): ?array
    {
        $movement = self::databaseMovements()[$id] ?? null;
        if ($movement === null) {
            return null;
        }

        return self::normalizeMovement($movement, self::serviceNameMap());
    }

    public static function validateItem(array $payload, ?string $ignoreId = null): array
    {
        $errors = [];

        foreach (['name', 'sku', 'category', 'unit', 'reorder_level', 'cost_per_unit', 'location'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if ($ignoreId === null && trim((string) ($payload['quantity'] ?? '')) === '') {
            $errors['quantity'] = 'Opening quantity is required.';
        }

        foreach (['quantity', 'reorder_level', 'cost_per_unit'] as $f) {
            if (($payload[$f] ?? '') !== '' && (!is_numeric((string) $payload[$f]) || (float) $payload[$f] < 0)) {
                $errors[$f] = 'Enter a valid number that is zero or greater.';
            }
        }

        $location = trim((string) ($payload['location'] ?? ''));
        if ($location !== '' && !in_array($location, self::storageLocations(), true)) {
            $errors['location'] = 'Select a valid storage location from the list.';
        }

        $sku  = strtolower(trim((string) ($payload['sku'] ?? '')));
        $conn = self::connection();

        if ($sku !== '' && $conn instanceof mysqli) {
            $stmt = self::prepare($conn, 'SELECT id FROM inventory_items WHERE LOWER(sku) = ? LIMIT 1', 's', [$sku]);
            if ($stmt instanceof mysqli_stmt) {
                $foundId = null;
                $stmt->bind_result($foundId);
                $fetched = $stmt->fetch();
                $stmt->close();
                if ($fetched === true && $foundId !== null && $foundId !== $ignoreId) {
                    $errors['sku'] = 'SKU must be unique.';
                }
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

        $item     = trim((string) ($payload['item_id'] ?? '')) !== '' ? self::find((string) $payload['item_id']) : null;
        $type     = (string) ($payload['type'] ?? '');
        $quantity = (float) ($payload['quantity'] ?? 0);

        if ($item === null) {
            $errors['item_id'] = 'Select a valid inventory item.';
        }

        if (!in_array($type, self::movementTypes(), true)) {
            $errors['type'] = 'Select a valid movement type.';
        }

        if (!is_numeric((string) ($payload['quantity'] ?? '')) || $quantity < 0 || ($type !== 'adjustment' && $quantity <= 0)) {
            $errors['quantity'] = $type === 'adjustment' ? 'Counted quantity must be zero or greater.' : 'Quantity must be greater than zero.';
        }

        if ($item !== null && $errors === [] && in_array($type, ['stock_out', 'wastage', 'service_usage'], true) && $quantity > (float) $item['on_hand']) {
            $errors['quantity'] = 'This movement is larger than the stock currently on hand.';
        }

        if ($type === 'service_usage' && trim((string) ($payload['service_id'] ?? '')) === '') {
            $errors['service_id'] = 'Choose the service consuming this item.';
        }

        return $errors;
    }

    public static function saveItem(array $payload, ?string $id = null): array
    {
        $existing        = $id !== null ? self::find($id) : null;
        $itemId          = $existing['id'] ?? self::nextId();
        $openingQuantity = round((float) ($payload['quantity'] ?? 0), 2);

        $name         = trim((string) ($payload['name']         ?? ($existing['name']         ?? '')));
        $sku          = strtoupper(trim((string) ($payload['sku'] ?? ($existing['sku']         ?? ''))));
        $category     = trim((string) ($payload['category']     ?? ($existing['category']     ?? 'Consumables')));
        $unit         = trim((string) ($payload['unit']         ?? ($existing['unit']         ?? 'units')));
        $reorderLevel = round((float) ($payload['reorder_level'] ?? ($existing['reorder_level'] ?? 0)), 2);
        $costPerUnit  = round((float) ($payload['cost_per_unit'] ?? ($existing['cost_per_unit'] ?? 0)), 2);
        $supplier     = trim((string) ($payload['supplier']     ?? ($existing['supplier']     ?? '')));
        $location     = trim((string) ($payload['location']     ?? ($existing['location']     ?? 'Main storage')));
        $notes        = trim((string) ($payload['notes']        ?? ($existing['notes']        ?? '')));
        $services     = self::normalizeServiceLinks($payload['used_in_services'] ?? ($existing['used_in_services'] ?? []));
        $onHand       = $existing !== null ? (float) $existing['on_hand'] : 0.0;

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            mysqli_begin_transaction($conn);
            try {
                $stmt = self::prepare($conn,
                    'INSERT INTO inventory_items (id, name, sku, category, unit, on_hand, reorder_level, cost_per_unit, supplier, location, notes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE name=VALUES(name), sku=VALUES(sku), category=VALUES(category), unit=VALUES(unit),
                     reorder_level=VALUES(reorder_level), cost_per_unit=VALUES(cost_per_unit),
                     supplier=VALUES(supplier), location=VALUES(location), notes=VALUES(notes), updated_at=NOW()',
                    'sssssdddsss',
                    [$itemId, $name, $sku, $category, $unit, $onHand, $reorderLevel, $costPerUnit, $supplier, $location, $notes]
                );
                if (!$stmt instanceof mysqli_stmt) throw new RuntimeException('Unable to save inventory item.');
                $stmt->close();

                $del = self::prepare($conn, 'DELETE FROM inventory_item_services WHERE item_id = ?', 's', [$itemId]);
                if (!$del instanceof mysqli_stmt) throw new RuntimeException('Unable to reset service links.');
                $del->close();

                foreach ($services as $serviceId) {
                    $lnk = self::prepare($conn, 'INSERT IGNORE INTO inventory_item_services (item_id, service_id) VALUES (?, ?)', 'ss', [$itemId, $serviceId]);
                    if (!$lnk instanceof mysqli_stmt) throw new RuntimeException('Unable to save service link.');
                    $lnk->close();
                }

                mysqli_commit($conn);
                self::$recordCache = null;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log('Inventory saveItem failed: ' . $e->getMessage());
                throw $e;
            }
        }

        if ($existing === null && $openingQuantity > 0) {
            self::saveMovement([
                'item_id'       => $itemId,
                'movement_date' => date('Y-m-d'),
                'type'          => 'stock_in',
                'quantity'      => (string) $openingQuantity,
                'reason'        => 'Opening stock on item creation.',
                'recorded_by'   => 'Admin panel',
                'service_id'    => '',
            ]);
        }

        return self::find($itemId) ?? [];
    }

    public static function saveMovement(array $payload): array
    {
        $item = self::find((string) $payload['item_id']);

        if ($item === null) {
            throw new RuntimeException('Inventory item not found.');
        }

        $type     = (string) $payload['type'];
        $before   = (float) $item['on_hand'];
        $quantity = round((float) $payload['quantity'], 2);
        $after    = match ($type) {
            'stock_in'                          => $before + $quantity,
            'stock_out', 'wastage', 'service_usage' => max(0.0, $before - $quantity),
            'adjustment'                        => $quantity,
            default                             => $before,
        };
        $after     = round($after, 2);
        $serviceId = trim((string) ($payload['service_id'] ?? '')) ?: null;
        $date      = (string) $payload['movement_date'];
        $reason    = trim((string) $payload['reason']);
        $recordedBy = trim((string) ($payload['recorded_by'] ?? 'Admin panel'));

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            mysqli_begin_transaction($conn);
            try {
                $movementId = self::nextId();
                $reference  = self::nextReference($conn);

                $stmt = self::prepare($conn,
                    'INSERT INTO inventory_movements (id, reference, item_id, service_id, movement_date, movement_type, quantity, before_quantity, after_quantity, reason, recorded_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    'ssssssdddss',
                    [$movementId, $reference, $item['id'], $serviceId, $date, $type, $quantity, $before, $after, $reason, $recordedBy]
                );
                if (!$stmt instanceof mysqli_stmt) throw new RuntimeException('Unable to save movement.');
                $stmt->close();

                $upd = self::prepare($conn,
                    'UPDATE inventory_items SET on_hand = ?, last_movement_at = NOW(), updated_at = NOW() WHERE id = ?',
                    'ds', [$after, $item['id']]
                );
                if (!$upd instanceof mysqli_stmt) throw new RuntimeException('Unable to update stock level.');
                $upd->close();

                mysqli_commit($conn);
                self::$recordCache = null;

                return self::normalizeMovement([
                    'id' => $movementId, 'reference' => $reference,
                    'item_id' => $item['id'], 'service_id' => $serviceId ?? '',
                    'movement_date' => $date, 'type' => $type,
                    'quantity' => $quantity, 'delta' => round($after - $before, 2),
                    'before_quantity' => $before, 'after_quantity' => $after,
                    'reason' => $reason, 'recorded_by' => $recordedBy,
                ], self::serviceNameMap());
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log('Inventory saveMovement failed: ' . $e->getMessage());
                throw $e;
            }
        }

        return [];
    }

    private static function withStockMeta(array $item, array $serviceMap = []): array
    {
        if ($serviceMap === []) {
            $serviceMap = self::serviceNameMap();
        }
        $status           = self::statusForQuantity((float) $item['on_hand'], (float) $item['reorder_level']);
        $usedServiceNames = [];
        foreach ((array) ($item['used_in_services'] ?? []) as $sid) {
            $usedServiceNames[] = $serviceMap[$sid] ?? 'Unknown service';
        }

        return $item + [
            'stock_status'          => $status['status'],
            'stock_tone'            => $status['tone'],
            'stock_value'           => round((float) $item['on_hand'] * (float) $item['cost_per_unit'], 2),
            'reorder_gap'           => max(0.0, (float) $item['reorder_level'] - (float) $item['on_hand']),
            'used_in_service_names' => $usedServiceNames,
        ];
    }

    private static function statusForQuantity(float $quantity, float $reorderLevel): array
    {
        if ($quantity <= 0) return ['status' => 'out_of_stock', 'tone' => 'danger'];
        if ($quantity <= $reorderLevel) return ['status' => 'low_stock', 'tone' => 'warning'];
        return ['status' => 'in_stock', 'tone' => 'success'];
    }

    private static function normalizeMovement(array $movement, array $serviceMap = []): array
    {
        if ($serviceMap === []) {
            $serviceMap = self::serviceNameMap();
        }
        $records = self::databaseRecords();
        $itemId  = (string) ($movement['item_id'] ?? '');
        $item    = $records[$itemId] ?? null;
        $type    = (string) ($movement['type'] ?? 'stock_in');

        return [
            'id'              => (string) ($movement['id'] ?? ''),
            'reference'       => (string) ($movement['reference'] ?? ''),
            'item_id'         => $itemId,
            'item_name'       => $item['name'] ?? 'Unknown item',
            'sku'             => $item['sku'] ?? '',
            'movement_date'   => (string) ($movement['movement_date'] ?? date('Y-m-d')),
            'type'            => $type,
            'quantity'        => round((float) ($movement['quantity'] ?? 0), 2),
            'delta'           => round((float) ($movement['delta'] ?? 0), 2),
            'before_quantity' => round((float) ($movement['before_quantity'] ?? 0), 2),
            'after_quantity'  => round((float) ($movement['after_quantity'] ?? 0), 2),
            'reason'          => (string) ($movement['reason'] ?? ''),
            'recorded_by'     => (string) ($movement['recorded_by'] ?? 'Admin panel'),
            'service_id'      => (string) ($movement['service_id'] ?? ''),
            'service_name'    => $serviceMap[(string) ($movement['service_id'] ?? '')] ?? '',
            'type_tone'       => match ($type) {
                'stock_in'     => 'success',
                'stock_out', 'adjustment' => 'warning',
                'wastage'      => 'danger',
                'service_usage' => 'info',
                default        => 'info',
            },
            'sort_key' => (string) ($movement['movement_date'] ?? date('Y-m-d')) . ' ' . (string) ($movement['id'] ?? ''),
        ];
    }

    private static function normalizeServiceLinks(mixed $payload): array
    {
        $values = is_array($payload) ? $payload : [$payload];
        return array_values(array_unique(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $values))));
    }

    private static function serviceNameMap(): array
    {
        $map = [];
        foreach (self::serviceOptions() as $service) {
            $map[$service['id']] = $service['name'];
        }
        return $map;
    }

    private static function databaseRecords(): array
    {
        if (self::$recordCache !== null) {
            return self::$recordCache;
        }

        $conn = self::connection();

        if (!$conn instanceof mysqli) {
            return [];
        }

        $result = $conn->query(
            'SELECT i.id, i.name, i.sku, i.category, i.unit, i.on_hand, i.reorder_level,
                    i.cost_per_unit, i.supplier, i.location, i.notes, i.last_movement_at, i.created_at,
                    GROUP_CONCAT(iis.service_id) AS service_ids
             FROM inventory_items i
             LEFT JOIN inventory_item_services iis ON iis.item_id = i.id
             GROUP BY i.id, i.name, i.sku, i.category, i.unit, i.on_hand, i.reorder_level,
                      i.cost_per_unit, i.supplier, i.location, i.notes, i.last_movement_at, i.created_at'
        );

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $id = (string) $row['id'];
            $records[$id] = [
                'id'               => $id,
                'name'             => (string) ($row['name'] ?? ''),
                'sku'              => (string) ($row['sku'] ?? ''),
                'category'         => (string) ($row['category'] ?? 'Consumables'),
                'unit'             => (string) ($row['unit'] ?? 'units'),
                'on_hand'          => round((float) ($row['on_hand'] ?? 0), 2),
                'reorder_level'    => round((float) ($row['reorder_level'] ?? 0), 2),
                'cost_per_unit'    => round((float) ($row['cost_per_unit'] ?? 0), 2),
                'supplier'         => (string) ($row['supplier'] ?? ''),
                'location'         => (string) ($row['location'] ?? ''),
                'notes'            => (string) ($row['notes'] ?? ''),
                'last_movement_at' => ($row['last_movement_at'] ?? '') !== '' ? (string) $row['last_movement_at'] : null,
                'created_at'       => (string) ($row['created_at'] ?? ''),
                'used_in_services' => $row['service_ids'] !== null ? explode(',', (string) $row['service_ids']) : [],
            ];
        }
        $result->free();

        self::$recordCache = $records;
        return $records;
    }

    private static function databaseMovements(): array
    {
        $conn = self::connection();

        if (!$conn instanceof mysqli) {
            return [];
        }

        $result = $conn->query(
            'SELECT id, reference, item_id, service_id, movement_date,
                    movement_type AS type, quantity, before_quantity, after_quantity, reason, recorded_by, created_at
             FROM inventory_movements
             ORDER BY movement_date DESC, created_at DESC'
        );

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $movements = [];
        while ($row = $result->fetch_assoc()) {
            $id = (string) $row['id'];
            $before = (float) $row['before_quantity'];
            $after  = (float) $row['after_quantity'];
            $movements[$id] = [
                'id'              => $id,
                'reference'       => (string) ($row['reference'] ?? ''),
                'item_id'         => (string) ($row['item_id'] ?? ''),
                'service_id'      => (string) ($row['service_id'] ?? ''),
                'movement_date'   => (string) ($row['movement_date'] ?? ''),
                'type'            => (string) ($row['type'] ?? ''),
                'quantity'        => (float) $row['quantity'],
                'delta'           => round($after - $before, 2),
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'reason'          => (string) ($row['reason'] ?? ''),
                'recorded_by'     => (string) ($row['recorded_by'] ?? ''),
                'created_at'      => (string) ($row['created_at'] ?? ''),
            ];
        }
        $result->free();

        return $movements;
    }

    private static function nextId(): string
    {
        return function_exists('uuid_v4') ? uuid_v4() : self::fallbackUuid();
    }

    private static function nextReference(mysqli $conn): string
    {
        $result = $conn->query(
            "SELECT reference
             FROM inventory_movements
             WHERE reference REGEXP '^MOV-[0-9]+$'
             ORDER BY CAST(SUBSTRING(reference, 5) AS UNSIGNED) DESC
             LIMIT 1"
        );
        $last = 0;

        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc() ?: [];
            $result->free();
            $last = (int) preg_replace('/\D+/', '', (string) ($row['reference'] ?? '0'));
        }

        return 'MOV-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function fallbackUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    private static function prepare(mysqli $conn, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            error_log('Inventory prepare failed: ' . mysqli_error($conn));
            return null;
        }

        if ($types !== '' && $params !== []) {
            $refs = [$types];
            foreach ($params as $i => $v) {
                $refs[] = &$params[$i];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
                error_log('Inventory bind_param failed: ' . $stmt->error);
                $stmt->close();
                return null;
            }
        }

        if (!$stmt->execute()) {
            error_log('Inventory statement execute failed: ' . $stmt->error . ' | SQL: ' . $sql);
            $stmt->close();
            return null;
        }

        return $stmt;
    }
}
