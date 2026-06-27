<?php
require __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/includes/config.php';

$messages = [];

function column_exists(PDO $pdo, $table, $column) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

function add_column(PDO $pdo, array &$messages, $table, $column, $definition) {
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $messages[] = "ADDED: $table.$column";
    } else {
        $messages[] = "OK: $table.$column";
    }
}

try {
    add_column($pdo, $messages, 'findings', 'account_name', "VARCHAR(80) NULL");
    add_column($pdo, $messages, 'findings', 'relative_path', "TEXT NULL");
    add_column($pdo, $messages, 'findings', 'ctime', "DATETIME NULL");
    add_column($pdo, $messages, 'findings', 'birth_time', "DATETIME NULL");
    add_column($pdo, $messages, 'findings', 'detected_at', "DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
    add_column($pdo, $messages, 'findings', 'source_guess', "VARCHAR(255) NULL");
    add_column($pdo, $messages, 'findings', 'source_type', "VARCHAR(80) NULL");
    add_column($pdo, $messages, 'findings', 'file_ext', "VARCHAR(30) NULL");
    add_column($pdo, $messages, 'findings', 'action_status', "VARCHAR(40) NOT NULL DEFAULT 'new'");
    add_column($pdo, $messages, 'findings', 'action_note', "TEXT NULL");
    add_column($pdo, $messages, 'findings', 'action_at', "DATETIME NULL");
    add_column($pdo, $messages, 'findings', 'action_by', "VARCHAR(80) NULL");

    $pdo->exec("
        UPDATE findings
        SET
            account_name = IF(account_name IS NULL OR account_name='', owner_name, account_name),
            relative_path = IF(relative_path IS NULL OR relative_path='', file_path, relative_path),
            detected_at = IF(detected_at IS NULL, created_at, detected_at)
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scanner_actions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            finding_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(40) NOT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            created_by VARCHAR(80) NULL,
            INDEX(finding_id),
            INDEX(action),
            INDEX(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "OK: scanner_actions";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scanner_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            account_name VARCHAR(80) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            last_login DATETIME NULL,
            INDEX(role),
            INDEX(account_name),
            INDEX(active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "OK: scanner_users";

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM scanner_users")->fetchColumn();
    if ($cnt === 0) {
        $hash = password_hash($config['default_admin_pass'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO scanner_users (username, password_hash, role, account_name, active, created_at)
            VALUES (?, ?, 'admin', NULL, 1, NOW())
        ");
        $stmt->execute([$config['default_admin_user'], $hash]);
        $messages[] = "CREATED DEFAULT ADMIN: " . $config['default_admin_user'];
    } else {
        $messages[] = "OK: users already exist";
    }

    $messages[] = "MIGRATION FINISHED";
} catch (Throwable $e) {
    $messages[] = "ERROR: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<title>8Core Scanner Migration</title>
<link rel="stylesheet" href="assets/css/scanner.css">
</head>
<body>
<div class="header"><h1>8Core Scanner Migration</h1><div class="meta">Database schema update</div></div>
<div class="container">
    <div class="notice ok">
        <?php foreach ($messages as $m): ?><div><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    </div>
    <p><a href="login.php">Login</a> | <a href="index.php">Dashboard</a> | <a href="debug.php">Debug</a></p>
</div>
</body>
</html>
