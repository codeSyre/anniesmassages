<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$connection = db_connection();

if (!$connection instanceof mysqli) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$openingQuantities = [
    'OIL-ALMOND-5L' => 12.0,
    'OIL-CARRIER-5L' => 10.0,
    'OIL-LAVENDER-100' => 18.0,
    'OIL-PEPPERMINT-100' => 14.0,
    'EQ-HOTSTONES-SET' => 4.0,
    'EQ-STONE-WARMER' => 2.0,
    'LIN-TABLE-SHEETS' => 36.0,
    'LIN-FACE-CRADLE' => 72.0,
    'LIN-BATH-TOWELS' => 48.0,
    'LIN-HAND-TOWELS' => 60.0,
    'CON-DISPOSABLE-BRIEFS' => 24.0,
    'CON-NITRILE-GLOVES' => 30.0,
    'CON-SANITIZER-WIPES' => 20.0,
    'CON-SANITIZER-SPRAY' => 15.0,
    'CON-LAUNDRY-10KG' => 10.0,
    'AMB-DIFFUSER-REFILL' => 14.0,
    'AMB-SOY-CANDLES' => 18.0,
    'PKG-BAMBOO-SLIPPERS' => 40.0,
    'CON-HERBAL-TEA' => 80.0,
    'SKN-BLUELOTUS-SERUM' => 10.0,
    'SKN-OXYGEN-AMPOULES' => 18.0,
    'TOP-COOLING-GEL-MASK' => 14.0,
    'SKN-HYDRATING-SHEETS' => 25.0,
];

$result = $connection->query(
    "SELECT i.id, i.name, i.sku, i.on_hand, i.created_at, i.last_movement_at, COUNT(m.id) AS movement_count
     FROM inventory_items i
     LEFT JOIN inventory_movements m ON m.item_id = i.id
     GROUP BY i.id, i.name, i.sku, i.on_hand, i.created_at, i.last_movement_at
     HAVING movement_count = 0
     ORDER BY i.created_at ASC"
);

if (!$result instanceof mysqli_result) {
    fwrite(STDERR, "Unable to load inventory items for repair.\n");
    exit(1);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$result->free();

if ($items === []) {
    echo "No missing movement rows detected.\n";
    exit(0);
}

$referenceResult = $connection->query(
    "SELECT reference
     FROM inventory_movements
     WHERE reference REGEXP '^MOV-[0-9]+$'
     ORDER BY CAST(SUBSTRING(reference, 5) AS UNSIGNED) DESC
     LIMIT 1"
);
$lastReference = 0;
if ($referenceResult instanceof mysqli_result) {
    $row = $referenceResult->fetch_assoc() ?: [];
    $referenceResult->free();
    $lastReference = (int) preg_replace('/\D+/', '', (string) ($row['reference'] ?? '0'));
}

$insertMovement = static function (
    mysqli $connection,
    int &$lastReference,
    string $itemId,
    string $movementType,
    float $quantity,
    float $before,
    float $after,
    string $movementDate,
    string $createdAt,
    string $reason
): void {
    $movementId = function_exists('uuid_v4') ? uuid_v4() : (static function (): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    })();
    $lastReference++;
    $reference = 'MOV-' . str_pad((string) $lastReference, 4, '0', STR_PAD_LEFT);

    $statement = $connection->prepare(
        'INSERT INTO inventory_movements (id, reference, item_id, service_id, movement_date, movement_type, quantity, before_quantity, after_quantity, reason, recorded_by, created_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$statement instanceof mysqli_stmt) {
        throw new RuntimeException('Unable to prepare repair insert.');
    }

    $recordedBy = 'System repair';
    $statement->bind_param(
        'sssssdddsss',
        $movementId,
        $reference,
        $itemId,
        $movementDate,
        $movementType,
        $quantity,
        $before,
        $after,
        $reason,
        $recordedBy,
        $createdAt
    );

    if (!$statement->execute()) {
        $message = $statement->error;
        $statement->close();
        throw new RuntimeException('Repair insert failed: ' . $message);
    }

    $statement->close();
};

$repaired = 0;

mysqli_begin_transaction($connection);

try {
    foreach ($items as $item) {
        $sku = (string) ($item['sku'] ?? '');
        $itemId = (string) ($item['id'] ?? '');
        $createdAt = (string) ($item['created_at'] ?? date('Y-m-d H:i:s'));
        $lastMovementAt = (string) ($item['last_movement_at'] ?? '');
        $currentOnHand = round((float) ($item['on_hand'] ?? 0), 2);

        if (!array_key_exists($sku, $openingQuantities)) {
            continue;
        }

        $openingQuantity = round((float) $openingQuantities[$sku], 2);
        $openingDate = substr($createdAt, 0, 10) ?: date('Y-m-d');

        if ($openingQuantity > 0) {
            $insertMovement(
                $connection,
                $lastReference,
                $itemId,
                'stock_in',
                $openingQuantity,
                0.0,
                $openingQuantity,
                $openingDate,
                $createdAt,
                'Backfilled opening stock after failed movement insert.'
            );
            $repaired++;
        }

        if ($lastMovementAt !== '' && $lastMovementAt > $createdAt && $currentOnHand !== $openingQuantity) {
            $movementDate = substr($lastMovementAt, 0, 10) ?: $openingDate;
            $delta = round($currentOnHand - $openingQuantity, 2);
            $movementType = $delta < 0 ? 'wastage' : 'stock_in';
            $quantity = abs($delta);
            $reason = $delta < 0
                ? 'Backfilled wastage after failed movement insert.'
                : 'Backfilled stock-in after failed movement insert.';

            $insertMovement(
                $connection,
                $lastReference,
                $itemId,
                $movementType,
                $quantity,
                $openingQuantity,
                $currentOnHand,
                $movementDate,
                $lastMovementAt,
                $reason
            );
            $repaired++;
        }
    }

    mysqli_commit($connection);
} catch (Throwable $exception) {
    mysqli_rollback($connection);
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

echo "Repaired {$repaired} movement rows.\n";
