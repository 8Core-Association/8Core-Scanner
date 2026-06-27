<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require_login();

try {
    $user = current_user();

    $hasAction = has_column($pdo, 'findings', 'action_status');
    $hasAccount = has_column($pdo, 'findings', 'account_name');
    $hasRel = has_column($pdo, 'findings', 'relative_path');
    $hasCtime = has_column($pdo, 'findings', 'ctime');
    $hasBirth = has_column($pdo, 'findings', 'birth_time');
    $hasDetected = has_column($pdo, 'findings', 'detected_at');
    $hasSourceGuess = has_column($pdo, 'findings', 'source_guess');
    $hasSourceType = has_column($pdo, 'findings', 'source_type');
    $hasExt = has_column($pdo, 'findings', 'file_ext');

    $accountCol = $hasAccount ? 'account_name' : 'owner_name';
    $relCol = $hasRel ? 'relative_path' : 'file_path';
    $detectedCol = $hasDetected ? 'detected_at' : 'created_at';

    $risk = isset($_GET['risk']) ? $_GET['risk'] : '';
    $account = isset($_GET['account']) ? $_GET['account'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    $where = [];
    $params = [];

    if (!is_admin()) {
        $where[] = "$accountCol = ?";
        $params[] = $user['account_name'];
        $account = $user['account_name'];
    } else if ($account !== '') {
        $where[] = "$accountCol = ?";
        $params[] = $account;
    }

    if ($risk !== '') { $where[] = "risk = ?"; $params[] = $risk; }
    if ($hasAction && $status !== '') { $where[] = "action_status = ?"; $params[] = $status; }
    if ($q !== '') {
        $where[] = "(file_path LIKE ? OR file_name LIKE ? OR rule_name LIKE ?)";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $statsStmt = $pdo->prepare("SELECT risk, COUNT(*) total FROM findings $whereSql GROUP BY risk");
    $statsStmt->execute($params);
    $stats = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $actionStats = [];
    if ($hasAction) {
        $as = $pdo->prepare("SELECT action_status, COUNT(*) total FROM findings $whereSql GROUP BY action_status");
        $as->execute($params);
        $actionStats = $as->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    if (is_admin()) {
        $accounts = $pdo->query("
            SELECT $accountCol AS account_name, COUNT(*) total
            FROM findings
            WHERE $accountCol IS NOT NULL AND $accountCol != ''
            GROUP BY $accountCol
            ORDER BY total DESC
        ")->fetchAll();
    } else {
        $accounts = [['account_name' => $user['account_name'], 'total' => 0]];
    }

    $stmt = $pdo->prepare("
        SELECT
            id, scan_id, rule_name, risk,
            $accountCol AS account_name,
            owner_name, group_name, perms, file_size,
            file_name,
            " . ($hasExt ? "file_ext" : "'' AS file_ext") . ",
            file_path,
            $relCol AS relative_path,
            mtime,
            " . ($hasCtime ? "ctime" : "NULL AS ctime") . ",
            " . ($hasBirth ? "birth_time" : "NULL AS birth_time") . ",
            $detectedCol AS detected_at,
            " . ($hasSourceGuess ? "source_guess" : "'' AS source_guess") . ",
            " . ($hasSourceType ? "source_type" : "'' AS source_type") . ",
            " . ($hasAction ? "action_status" : "'new' AS action_status") . ",
            " . ($hasAction ? "action_note" : "'' AS action_note") . ",
            sha256,
            created_at
        FROM findings
        $whereSql
        ORDER BY id DESC
        LIMIT 300
    ");
    $stmt->execute($params);
    $findings = $stmt->fetchAll();

    $lastScan = $pdo->query("SELECT * FROM scans ORDER BY id DESC LIMIT 1")->fetch();

} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>8Core Scanner error</title></head><body>';
    echo '<h2>8Core Scanner - PHP error</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<p>Pokreni <a href="migrate.php">migrate.php</a> pa zatim <a href="debug.php">debug.php</a>.</p>';
    echo '</body></html>';
    exit;
}
?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<title>8Core Scanner</title>
<link rel="stylesheet" href="assets/css/scanner.css">
</head>
<body>

<div class="header">
    <h1>8Core Scanner</h1>
    <div class="meta">
        Logged in: <?= h($user['username']) ?> / <?= h($user['role']) ?>
        <?php if (!is_admin()): ?> / account: <?= h($user['account_name']) ?><?php endif; ?>
        <br>
        Zadnji scan:
        <?php if ($lastScan): ?>
            <?= h($lastScan['started_at']) ?> / <?= h($lastScan['status']) ?> / findings: <?= h($lastScan['files_found']) ?>
        <?php else: ?>
            nema podataka
        <?php endif; ?>
    </div>
</div>

<div class="nav">
    <a href="index.php">Dashboard</a>
    <?php if (is_admin()): ?><a href="admin/users.php">Users</a><?php endif; ?>
    <a href="logout.php">Logout</a>
</div>

<div class="container">

<?php if (!$hasAction): ?>
    <div class="notice">Baza nema auth/action stupce. Otvori <a href="migrate.php">migrate.php</a>.</div>
<?php endif; ?>

<div class="cards">
    <div class="card"><div class="label">Critical</div><div class="num"><?= (int)($stats['CRITICAL'] ?? 0) ?></div></div>
    <div class="card"><div class="label">High</div><div class="num"><?= (int)($stats['HIGH'] ?? 0) ?></div></div>
    <div class="card"><div class="label">Medium</div><div class="num"><?= (int)($stats['MEDIUM'] ?? 0) ?></div></div>
    <div class="card"><div class="label">Ignored</div><div class="num"><?= (int)($actionStats['ignore'] ?? 0) ?></div></div>
    <div class="card"><div class="label">Quarantine req.</div><div class="num"><?= (int)($actionStats['quarantine_requested'] ?? 0) ?></div></div>
    <div class="card"><div class="label">Delete req.</div><div class="num"><?= (int)($actionStats['delete_requested'] ?? 0) ?></div></div>
</div>

<form class="filters" method="get">
    <select name="risk">
        <option value="">Svi rizici</option>
        <?php foreach (['CRITICAL','HIGH','MEDIUM','LOW'] as $r): ?>
            <option value="<?= h($r) ?>" <?= $risk === $r ? 'selected' : '' ?>><?= h($r) ?></option>
        <?php endforeach; ?>
    </select>

    <?php if (is_admin()): ?>
    <select name="account">
        <option value="">Svi accounti</option>
        <?php foreach ($accounts as $a): ?>
            <option value="<?= h($a['account_name']) ?>" <?= $account === $a['account_name'] ? 'selected' : '' ?>>
                <?= h($a['account_name']) ?> (<?= (int)$a['total'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <select name="status">
        <option value="">Svi statusi</option>
        <?php foreach (['new','checked','ignore','quarantine_requested','delete_requested'] as $s): ?>
            <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
    </select>

    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Pretraga file/path/rule">
    <button type="submit">Filtriraj</button>
    <a href="index.php">Reset</a>
</form>

<div class="table-wrap">
<table>
<thead>
<tr>
    <th>Risk</th>
    <th>Status</th>
    <th>Account</th>
    <th>File</th>
    <th>Datumi</th>
    <th>Perm</th>
    <th>Size</th>
    <th>Rule</th>
    <th>Source</th>
    <th>Path</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($findings as $f): ?>
<tr>
    <td><span class="badge <?= risk_class($f['risk']) ?>"><?= h($f['risk']) ?></span></td>
    <td>
        <span class="status-pill <?= action_class($f['action_status']) ?>"><?= h($f['action_status']) ?></span>
        <?php if (!empty($f['action_note'])): ?><div class="small"><?= h($f['action_note']) ?></div><?php endif; ?>
    </td>
    <td><?= h($f['account_name']) ?><div class="small">owner: <?= h($f['owner_name']) ?></div></td>
    <td><b><?= h($f['file_name']) ?></b><div class="small">ext: <?= h($f['file_ext']) ?></div></td>
    <td>
        <div class="small">mtime: <?= h($f['mtime']) ?></div>
        <div class="small">ctime: <?= h($f['ctime']) ?></div>
        <div class="small">birth: <?= h($f['birth_time']) ?></div>
        <div class="small">detected: <?= h($f['detected_at']) ?></div>
    </td>
    <td><?= h($f['perms']) ?></td>
    <td><?= number_format((int)$f['file_size']) ?> B</td>
    <td><?= h($f['rule_name']) ?></td>
    <td><?= h($f['source_guess']) ?><div class="small"><?= h($f['source_type']) ?></div></td>
    <td class="path"><?= h($f['file_path']) ?><div class="small"><?= h($f['relative_path']) ?></div></td>
    <td>
        <div class="actions">
            <?php foreach ([
                'checked' => ['Checked','btn-check'],
                'ignore' => ['Ignore','btn-ignore'],
                'quarantine_requested' => ['Quarantine','btn-quarantine'],
                'delete_requested' => ['Delete','btn-delete'],
            ] as $act => $meta): ?>
            <form method="post" action="action.php" class="action-form">
                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <input type="hidden" name="action" value="<?= h($act) ?>">
                <button class="action-btn <?= h($meta[1]) ?>" type="submit"><?= h($meta[0]) ?></button>
            </form>
            <?php endforeach; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
<script src="assets/js/scanner.js"></script>
</body>
</html>
