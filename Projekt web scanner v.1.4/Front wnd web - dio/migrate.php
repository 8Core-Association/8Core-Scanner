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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>8Core Scanner – Migration</title>
<link rel="stylesheet" href="assets/css/scanner.css">
</head>
<body>
<div class="util-wrap">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
    <div style="width:34px;height:34px;background:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </div>
    <div>
      <div style="font-size:15px;font-weight:700;color:var(--text);">8Core Scanner</div>
      <div style="font-size:12px;color:var(--text-muted);">Database Migration</div>
    </div>
  </div>

  <div class="panel notice ok" style="margin-bottom:16px;">
    <?php foreach ($messages as $m): ?>
      <div class="log-line"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
  </div>

  <div class="util-links">
    <a href="login.php">Login</a>
    <a href="index.php">Dashboard</a>
    <a href="debug.php">Debug</a>
  </div>
</div>
</body>
</html>
