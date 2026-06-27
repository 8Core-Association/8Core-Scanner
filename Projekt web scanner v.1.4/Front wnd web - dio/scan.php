<?php
/**
 * 8Core Scanner
 * (c) 2026 Tomislav Galić / 8Core
 */

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';

require_login();

$user = current_user();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$error = '';

/**
 * Kreiraj queue tablicu ako ne postoji.
 * Root worker će kasnije čitati PENDING zahtjeve.
 */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scanner_scan_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            requested_by VARCHAR(80) NOT NULL,
            requested_role VARCHAR(20) NOT NULL,
            target_type VARCHAR(30) NOT NULL DEFAULT 'account',
            target_value VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
            scan_id BIGINT UNSIGNED NULL,
            requested_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            note TEXT NULL,
            INDEX(status),
            INDEX(requested_by),
            INDEX(target_type),
            INDEX(target_value),
            INDEX(requested_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    $error = 'Ne mogu kreirati scanner_scan_requests: ' . $e->getMessage();
}

/**
 * Account lista.
 * Admin vidi sve accounte iz findings.
 * User vidi samo svoj account.
 */
$accounts = [];

try {
    if (is_admin()) {
        $accounts = $pdo->query("
            SELECT DISTINCT account_name
            FROM findings
            WHERE account_name IS NOT NULL AND account_name != ''
            ORDER BY account_name
        ")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        if (!empty($user['account_name'])) {
            $accounts = [$user['account_name']];
        }
    }
} catch (Throwable $e) {
    $accounts = [];
}

/**
 * Request scan.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['request_scan'] ?? '') === '1') {
    $targetType = $_POST['target_type'] ?? 'account';
    $targetValue = trim($_POST['target_value'] ?? '');

    if (!is_admin()) {
        $targetType = 'account';
        $targetValue = $user['account_name'] ?? '';
    }

    if ($targetType === 'all' && !is_admin()) {
        $error = 'Nemaš pravo pokrenuti globalni scan.';
    } elseif ($targetType === 'custom_path' && !is_admin()) {
        $error = 'Nemaš pravo pokrenuti custom path scan.';
    } elseif ($targetType === 'account' && $targetValue === '') {
        $error = 'Account nije odabran.';
    } elseif ($targetType === 'account' && !is_admin() && $targetValue !== ($user['account_name'] ?? '')) {
        $error = 'Ne možeš pokrenuti scan za tuđi account.';
    } elseif ($targetType === 'custom_path' && strpos($targetValue, '/home/') !== 0) {
        $error = 'Custom path mora početi sa /home/.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO scanner_scan_requests
            (requested_by, requested_role, target_type, target_value, status, requested_at)
            VALUES (?, ?, ?, ?, 'PENDING', NOW())
        ");

        $stmt->execute([
            $user['username'],
            $user['role'],
            $targetType,
            $targetType === 'all' ? '/home' : $targetValue
        ]);

        $_SESSION['flash'] = 'Scan zahtjev je dodan u queue. Root worker će ga izvršiti.';
        header('Location: scan.php');
        exit;
    }
}

/**
 * Zadnji scan.
 */
$lastScan = null;

try {
    $lastScan = $pdo->query("
        SELECT *
        FROM scans
        ORDER BY id DESC
        LIMIT 1
    ")->fetch();
} catch (Throwable $e) {}

/**
 * Zadnji zahtjevi.
 */
$requests = [];

try {
    if (is_admin()) {
        $requests = $pdo->query("
            SELECT *
            FROM scanner_scan_requests
            ORDER BY id DESC
            LIMIT 20
        ")->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT *
            FROM scanner_scan_requests
            WHERE requested_by = ?
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmt->execute([$user['username']]);
        $requests = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $requests = [];
}

?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>8Core Scanner – Pokreni scan</title>
<link rel="stylesheet" href="assets/css/scanner.css">
</head>
<body>

<div class="header">
    <h1>Pokreni scan</h1>
    <div class="meta">
        Logged in: <?= h($user['username']) ?> / <?= h($user['role']) ?>
        <?php if (!is_admin()): ?>
            / account: <?= h($user['account_name']) ?>
        <?php endif; ?>
    </div>
</div>

<div class="nav">
    <a href="index.php">Dashboard</a>
    <a href="scan.php">Pokreni scan</a>
    <?php if (is_admin()): ?>
        <a href="admin/users.php">Users</a>
    <?php endif; ?>
    <a href="logout.php">Logout</a>
</div>

<div class="container">

<?php if ($flash): ?>
    <div class="notice ok"><?= h($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="notice error"><?= h($error) ?></div>
<?php endif; ?>

<div class="panel">
    <h2>Manualni scan</h2>

    <p class="small">
        Ova stranica ne pokreće root skriptu direktno. Samo dodaje scan zahtjev u bazu.
        Root worker kasnije izvršava zahtjev.
    </p>

    <form method="post">
        <input type="hidden" name="request_scan" value="1">

        <?php if (is_admin()): ?>

            <label>Target</label><br>

            <select name="target_type" id="target_type">
                <option value="account">Jedan account</option>
                <option value="all">Svi accounti</option>
                <option value="custom_path">Custom path</option>
            </select>

            <br><br>

            <select name="target_value" id="account_select">
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= h($acc) ?>"><?= h($acc) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text"
                   name="target_value_custom"
                   id="custom_path"
                   placeholder="/home/account/public_html"
                   style="display:none;">

            <script>
            document.getElementById('target_type').addEventListener('change', function () {
                var type = this.value;
                var acc = document.getElementById('account_select');
                var custom = document.getElementById('custom_path');

                if (type === 'custom_path') {
                    acc.style.display = 'none';
                    custom.style.display = 'inline-block';
                    custom.name = 'target_value';
                    acc.name = 'target_value_disabled';
                } else if (type === 'all') {
                    acc.style.display = 'none';
                    custom.style.display = 'none';
                    acc.name = 'target_value_disabled';
                    custom.name = 'target_value_disabled';
                } else {
                    acc.style.display = 'inline-block';
                    custom.style.display = 'none';
                    acc.name = 'target_value';
                    custom.name = 'target_value_disabled';
                }
            });
            </script>

        <?php else: ?>

            <input type="hidden" name="target_type" value="account">
            <input type="hidden" name="target_value" value="<?= h($user['account_name']) ?>">

            <div class="notice ok">
                Scan će biti pokrenut samo za tvoj account:
                <strong><?= h($user['account_name']) ?></strong>
            </div>

        <?php endif; ?>

        <br><br>

        <button type="submit"
                onclick="return confirm('Dodati scan zahtjev u queue?')">
            Pokreni scan
        </button>
    </form>
</div>

<div class="panel">
    <h2>Zadnji scan</h2>

    <?php if ($lastScan): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Base</th>
                <th>Findings</th>
                <th>Status</th>
            </tr>
            <tr>
                <td><?= h($lastScan['id']) ?></td>
                <td><?= h($lastScan['started_at']) ?></td>
                <td><?= h($lastScan['finished_at']) ?></td>
                <td><?= h($lastScan['base_path']) ?></td>
                <td><?= h($lastScan['files_found']) ?></td>
                <td><?= h($lastScan['status']) ?></td>
            </tr>
        </table>
    <?php else: ?>
        <p class="small">Nema podataka o scanu.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Scan queue</h2>

    <?php if ($requests): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>By</th>
                    <th>Target</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Started</th>
                    <th>Finished</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= h($r['id']) ?></td>
                    <td><?= h($r['requested_by']) ?></td>
                    <td>
                        <?= h($r['target_type']) ?><br>
                        <span class="small"><?= h($r['target_value']) ?></span>
                    </td>
                    <td>
                        <span class="status-pill <?= h(action_class(strtolower($r['status']))) ?>">
                            <?= h($r['status']) ?>
                        </span>
                    </td>
                    <td><?= h($r['requested_at']) ?></td>
                    <td><?= h($r['started_at']) ?></td>
                    <td><?= h($r['finished_at']) ?></td>
                    <td><?= h($r['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="small">Nema scan zahtjeva.</p>
    <?php endif; ?>
</div>

</div>

</body>
</html>
