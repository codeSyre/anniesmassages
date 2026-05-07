<?php declare(strict_types=1);

require_once __DIR__ . '/connect.php';

function db_is_online(): bool
{
    return db_connection() instanceof mysqli;
}
