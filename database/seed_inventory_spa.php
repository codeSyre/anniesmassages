<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

if (!function_exists('db_connection') || !db_connection() instanceof mysqli) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$existingItems = Inventory::all();
$existingBySku = [];
foreach ($existingItems as $item) {
    $existingBySku[strtoupper((string) ($item['sku'] ?? ''))] = $item;
}

$serviceMap = [];
foreach (Inventory::serviceOptions() as $service) {
    $serviceMap[strtolower((string) $service['name'])] = (string) $service['id'];
}

$massagesServiceId = $serviceMap['massages'] ?? null;
$blueLotusServiceId = $serviceMap['the blue lotus oxygen infusion'] ?? null;

$items = [
    [
        'name' => 'Sweet Almond Massage Oil 5L',
        'sku' => 'OIL-ALMOND-5L',
        'category' => 'Oils',
        'unit' => 'jugs',
        'quantity' => '12',
        'reorder_level' => '4',
        'cost_per_unit' => '28',
        'supplier' => 'Aroma Essentials',
        'location' => 'Supply room',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Core base oil used for full-body massage treatments.',
    ],
    [
        'name' => 'Unscented Carrier Oil 5L',
        'sku' => 'OIL-CARRIER-5L',
        'category' => 'Oils',
        'unit' => 'jugs',
        'quantity' => '10',
        'reorder_level' => '4',
        'cost_per_unit' => '24',
        'supplier' => 'Aroma Essentials',
        'location' => 'Supply room',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Neutral blending oil for scent-sensitive guests.',
    ],
    [
        'name' => 'Lavender Essential Oil 100ml',
        'sku' => 'OIL-LAVENDER-100',
        'category' => 'Oils',
        'unit' => 'bottles',
        'quantity' => '18',
        'reorder_level' => '6',
        'cost_per_unit' => '9.5',
        'supplier' => 'Botanical Lab',
        'location' => 'Treatment bar shelf A',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Used in relaxation massage blends and room aroma prep.',
    ],
    [
        'name' => 'Peppermint Essential Oil 100ml',
        'sku' => 'OIL-PEPPERMINT-100',
        'category' => 'Oils',
        'unit' => 'bottles',
        'quantity' => '14',
        'reorder_level' => '5',
        'cost_per_unit' => '8.75',
        'supplier' => 'Botanical Lab',
        'location' => 'Treatment bar shelf A',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Used for cooling blends and headache-relief add-ons.',
    ],
    [
        'name' => 'Basalt Hot Stones Set',
        'sku' => 'EQ-HOTSTONES-SET',
        'category' => 'Equipment',
        'unit' => 'sets',
        'quantity' => '4',
        'reorder_level' => '1',
        'cost_per_unit' => '95',
        'supplier' => 'SpaPro Supply',
        'location' => 'Stone suite cabinet',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Complete polished basalt stone set for hot stone sessions.',
    ],
    [
        'name' => 'Stone Warmer Unit',
        'sku' => 'EQ-STONE-WARMER',
        'category' => 'Equipment',
        'unit' => 'units',
        'quantity' => '2',
        'reorder_level' => '1',
        'cost_per_unit' => '140',
        'supplier' => 'SpaPro Supply',
        'location' => 'Main storage',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Table-side stone heater for hot stone prep.',
    ],
    [
        'name' => 'Fitted Massage Table Sheets',
        'sku' => 'LIN-TABLE-SHEETS',
        'category' => 'Linens',
        'unit' => 'sets',
        'quantity' => '36',
        'reorder_level' => '12',
        'cost_per_unit' => '7.8',
        'supplier' => 'SoftSpa Linens',
        'location' => 'Laundry staging',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Daily turnover linen sets for treatment tables.',
    ],
    [
        'name' => 'Face Cradle Covers',
        'sku' => 'LIN-FACE-CRADLE',
        'category' => 'Linens',
        'unit' => 'pieces',
        'quantity' => '72',
        'reorder_level' => '24',
        'cost_per_unit' => '1.2',
        'supplier' => 'SoftSpa Linens',
        'location' => 'Laundry staging',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Fresh cover for each massage booking.',
    ],
    [
        'name' => 'White Bath Towels',
        'sku' => 'LIN-BATH-TOWELS',
        'category' => 'Linens',
        'unit' => 'pieces',
        'quantity' => '48',
        'reorder_level' => '18',
        'cost_per_unit' => '4.9',
        'supplier' => 'SoftSpa Linens',
        'location' => 'Laundry staging',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Guest towels for treatment rooms and shower reset.',
    ],
    [
        'name' => 'Hand Towels',
        'sku' => 'LIN-HAND-TOWELS',
        'category' => 'Linens',
        'unit' => 'pieces',
        'quantity' => '60',
        'reorder_level' => '20',
        'cost_per_unit' => '2.1',
        'supplier' => 'SoftSpa Linens',
        'location' => 'Laundry staging',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Used for hand cleanup, warm compresses, and room reset.',
    ],
    [
        'name' => 'Disposable Underwear Packs',
        'sku' => 'CON-DISPOSABLE-BRIEFS',
        'category' => 'Consumables',
        'unit' => 'packs',
        'quantity' => '24',
        'reorder_level' => '8',
        'cost_per_unit' => '5.5',
        'supplier' => 'SpaGuest Essentials',
        'location' => 'Therapy room drawer',
        'used_in_services' => array_values(array_filter([$massagesServiceId])),
        'notes' => 'Guest modesty consumable for body treatments.',
    ],
    [
        'name' => 'Nitrile Gloves Box',
        'sku' => 'CON-NITRILE-GLOVES',
        'category' => 'Consumables',
        'unit' => 'boxes',
        'quantity' => '30',
        'reorder_level' => '10',
        'cost_per_unit' => '6.2',
        'supplier' => 'MediClean Supplies',
        'location' => 'Dispensary cabinet',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Protective gloves for cleaning, prep, and selected treatments.',
    ],
    [
        'name' => 'Disinfectant Surface Wipes',
        'sku' => 'CON-SANITIZER-WIPES',
        'category' => 'Consumables',
        'unit' => 'tubs',
        'quantity' => '20',
        'reorder_level' => '6',
        'cost_per_unit' => '4.8',
        'supplier' => 'MediClean Supplies',
        'location' => 'Dispensary cabinet',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Quick-turn sanitation between guest appointments.',
    ],
    [
        'name' => 'Hospital Grade Sanitizer Spray',
        'sku' => 'CON-SANITIZER-SPRAY',
        'category' => 'Consumables',
        'unit' => 'bottles',
        'quantity' => '15',
        'reorder_level' => '5',
        'cost_per_unit' => '7.4',
        'supplier' => 'MediClean Supplies',
        'location' => 'Dispensary cabinet',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Used for deep clean of beds, warmers, and hard surfaces.',
    ],
    [
        'name' => 'Laundry Detergent 10kg',
        'sku' => 'CON-LAUNDRY-10KG',
        'category' => 'Consumables',
        'unit' => 'bags',
        'quantity' => '10',
        'reorder_level' => '3',
        'cost_per_unit' => '22',
        'supplier' => 'FreshFold Pro',
        'location' => 'Laundry staging',
        'used_in_services' => [],
        'notes' => 'Primary detergent for high-turnover linen washing.',
    ],
    [
        'name' => 'Aroma Diffuser Refill',
        'sku' => 'AMB-DIFFUSER-REFILL',
        'category' => 'Ambience',
        'unit' => 'bottles',
        'quantity' => '14',
        'reorder_level' => '4',
        'cost_per_unit' => '11.5',
        'supplier' => 'CalmRoom Scents',
        'location' => 'Front storage',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Ambient scent refill for reception and treatment rooms.',
    ],
    [
        'name' => 'Soy Scented Candles',
        'sku' => 'AMB-SOY-CANDLES',
        'category' => 'Ambience',
        'unit' => 'candles',
        'quantity' => '18',
        'reorder_level' => '6',
        'cost_per_unit' => '6.8',
        'supplier' => 'CalmRoom Scents',
        'location' => 'Front storage',
        'used_in_services' => [],
        'notes' => 'Used for room ambience and guest welcome setup.',
    ],
    [
        'name' => 'Bamboo Spa Slippers',
        'sku' => 'PKG-BAMBOO-SLIPPERS',
        'category' => 'Packaging',
        'unit' => 'pairs',
        'quantity' => '40',
        'reorder_level' => '12',
        'cost_per_unit' => '2.7',
        'supplier' => 'SpaGuest Essentials',
        'location' => 'Reception cabinet',
        'used_in_services' => array_values(array_filter([$massagesServiceId, $blueLotusServiceId])),
        'notes' => 'Disposable guest slippers for wet area and treatment flow.',
    ],
    [
        'name' => 'Herbal Tea Sachets',
        'sku' => 'CON-HERBAL-TEA',
        'category' => 'Consumables',
        'unit' => 'sachets',
        'quantity' => '80',
        'reorder_level' => '20',
        'cost_per_unit' => '0.35',
        'supplier' => 'Wellness Pantry',
        'location' => 'Reception cabinet',
        'used_in_services' => [],
        'notes' => 'Complimentary post-treatment tea service for guests.',
    ],
    [
        'name' => 'Blue Lotus Oxygen Serum',
        'sku' => 'SKN-BLUELOTUS-SERUM',
        'category' => 'Skincare',
        'unit' => 'bottles',
        'quantity' => '10',
        'reorder_level' => '4',
        'cost_per_unit' => '32',
        'supplier' => 'DermSpa Clinical',
        'location' => 'Treatment bar shelf B',
        'used_in_services' => array_values(array_filter([$blueLotusServiceId])),
        'notes' => 'Primary active serum for the oxygen infusion treatment.',
    ],
    [
        'name' => 'Oxygen Infusion Ampoules',
        'sku' => 'SKN-OXYGEN-AMPOULES',
        'category' => 'Skincare',
        'unit' => 'boxes',
        'quantity' => '18',
        'reorder_level' => '6',
        'cost_per_unit' => '18.5',
        'supplier' => 'DermSpa Clinical',
        'location' => 'Cold storage',
        'used_in_services' => array_values(array_filter([$blueLotusServiceId])),
        'notes' => 'Single-use oxygen ampoules for infusion sessions.',
    ],
    [
        'name' => 'Cooling Gel Masks',
        'sku' => 'TOP-COOLING-GEL-MASK',
        'category' => 'Topicals',
        'unit' => 'pieces',
        'quantity' => '14',
        'reorder_level' => '5',
        'cost_per_unit' => '4.4',
        'supplier' => 'DermSpa Clinical',
        'location' => 'Cold storage',
        'used_in_services' => array_values(array_filter([$blueLotusServiceId])),
        'notes' => 'Post-infusion calming mask for redness control.',
    ],
    [
        'name' => 'Hydrating Sheet Masks',
        'sku' => 'SKN-HYDRATING-SHEETS',
        'category' => 'Skincare',
        'unit' => 'pieces',
        'quantity' => '25',
        'reorder_level' => '8',
        'cost_per_unit' => '3.2',
        'supplier' => 'DermSpa Clinical',
        'location' => 'Cold storage',
        'used_in_services' => array_values(array_filter([$blueLotusServiceId])),
        'notes' => 'Hydrating finish mask for premium facial treatments.',
    ],
];

$inserted = 0;
$updated = 0;

foreach ($items as $item) {
    $sku = strtoupper($item['sku']);
    $existing = $existingBySku[$sku] ?? null;
    Inventory::saveItem($item, is_array($existing) ? (string) $existing['id'] : null);

    if ($existing === null) {
        $inserted++;
        echo "Inserted {$item['sku']} - {$item['name']}\n";
    } else {
        $updated++;
        echo "Updated {$item['sku']} - {$item['name']}\n";
    }
}

echo "\nSeed complete. Inserted: {$inserted}. Updated: {$updated}.\n";
