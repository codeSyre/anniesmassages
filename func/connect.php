<?php declare(strict_types=1);

if (defined('ANNIES_DB_BOOTSTRAPPED')) {
    return;
}

define('ANNIES_DB_BOOTSTRAPPED', true);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$envPath = __DIR__ . '/../.env';

if (is_file($autoloadPath) && is_file($envPath)) {
    require_once $autoloadPath;

    if (class_exists(\Dotenv\Dotenv::class)) {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }
}

if (is_file($envPath)) {
    foreach (annies_parse_env_file($envPath) as $key => $value) {
        if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

$GLOBALS['dbHost'] = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: null;
$GLOBALS['dbPort'] = (int) ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
$GLOBALS['dbName'] = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;
$GLOBALS['dbUsername'] = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: null;
$GLOBALS['dbPassword'] = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null;
$GLOBALS['db_connection'] = null;
$GLOBALS['pdo_connection'] = null;

function db_configured(): bool
{
    return trim((string) ($GLOBALS['dbHost'] ?? '')) !== ''
        && trim((string) ($GLOBALS['dbName'] ?? '')) !== ''
        && trim((string) ($GLOBALS['dbUsername'] ?? '')) !== '';
}

function db_connection(): ?mysqli
{
    if ($GLOBALS['db_connection'] instanceof mysqli) {
        return $GLOBALS['db_connection'];
    }

    if (!db_configured()) {
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $connection = @mysqli_connect(
        (string) $GLOBALS['dbHost'],
        (string) $GLOBALS['dbUsername'],
        (string) ($GLOBALS['dbPassword'] ?? ''),
        (string) $GLOBALS['dbName'],
        (int) ($GLOBALS['dbPort'] ?? 3306)
    );

    if (!$connection instanceof mysqli) {
        error_log('Database connection failed: ' . mysqli_connect_error());

        return null;
    }

    mysqli_set_charset($connection, 'utf8mb4');
    $GLOBALS['db_connection'] = $connection;

    return $connection;
}

function pdo_connection(): ?PDO
{
    if ($GLOBALS['pdo_connection'] instanceof PDO) {
        return $GLOBALS['pdo_connection'];
    }

    if (!db_configured()) {
        return null;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) $GLOBALS['dbHost'],
        (int) ($GLOBALS['dbPort'] ?? 3306),
        (string) $GLOBALS['dbName']
    );

    try {
        $pdo = new PDO(
            $dsn,
            (string) $GLOBALS['dbUsername'],
            (string) ($GLOBALS['dbPassword'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $exception) {
        error_log('PDO connection failed: ' . $exception->getMessage());

        return null;
    }

    $GLOBALS['pdo_connection'] = $pdo;

    return $pdo;
}

function annies_parse_env_file(string $path): array
{
    $values = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines)) {
        return $values;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }

        $key = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}
