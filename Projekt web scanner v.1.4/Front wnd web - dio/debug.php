<?php
header('Content-Type: text/plain; charset=utf-8');

echo "8Core Scanner debug\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? "OK" : "MISSING") . "\n";

try {
    require __DIR__ . '/includes/db.php';
    echo "DB connection: OK\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "tables: " . implode(', ', $tables) . "\n";
    if (in_array('findings', $tables, true)) {
        $cols = $pdo->query("SHOW COLUMNS FROM findings")->fetchAll(PDO::FETCH_COLUMN);
        echo "findings columns: " . implode(', ', $cols) . "\n";
    }
    if (in_array('scanner_users', $tables, true)) {
        echo "users: " . $pdo->query("SELECT COUNT(*) FROM scanner_users")->fetchColumn() . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
