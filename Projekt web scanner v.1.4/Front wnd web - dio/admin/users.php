<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/helpers.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['form_action']) ? $_POST['form_action'] : '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $account = trim($_POST['account_name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($username !== '' && $password !== '' && in_array($role, ['admin','user'], true)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO scanner_users (username, password_hash, role, account_name, active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $hash, $role, $account !== '' ? $account : null, $active]);
            $message = 'Korisnik kreiran.';
        } else {
            $message = 'Nedostaje username/password ili role nije ispravan.';
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE scanner_users SET active = IF(active=1,0,1) WHERE id = ?")->execute([$id]);
        $message = 'Status promijenjen.';
    }

    if ($action === 'password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if ($id > 0 && $password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE scanner_users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
            $message = 'Lozinka promijenjena.';
        }
    }
}

$users = $pdo->query("SELECT * FROM scanner_users ORDER BY id ASC")->fetchAll();
$accounts = $pdo->query("
    SELECT DISTINCT account_name
    FROM findings
    WHERE account_name IS NOT NULL AND account_name != ''
    ORDER BY account_name
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<title>8Core Scanner Users</title>
<link rel="stylesheet" href="../assets/css/scanner.css">
</head>
<body>
<div class="header">
    <h1>Users</h1>
    <div class="meta">Logged in: <?= h(current_user()['username']) ?> / <?= h(current_user()['role']) ?></div>
</div>
<div class="nav">
    <a href="../index.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="../logout.php">Logout</a>
</div>
<div class="container">

<?php if ($message): ?><div class="notice ok"><?= h($message) ?></div><?php endif; ?>

<div class="panel">
    <h2>Dodaj korisnika</h2>
    <form method="post">
        <input type="hidden" name="form_action" value="create">
        <input type="text" name="username" placeholder="username" required>
        <input type="password" name="password" placeholder="password" required>
        <select name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
        </select>
        <input type="text" name="account_name" list="accounts" placeholder="account_name za usera">
        <datalist id="accounts">
            <?php foreach ($accounts as $a): ?><option value="<?= h($a) ?>"><?php endforeach; ?>
        </datalist>
        <label><input type="checkbox" name="active" checked> active</label>
        <button type="submit">Create</button>
    </form>
</div>

<div class="table-wrap">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Role</th>
    <th>Account</th>
    <th>Active</th>
    <th>Created</th>
    <th>Last login</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
    <td><?= (int)$u['id'] ?></td>
    <td><?= h($u['username']) ?></td>
    <td><?= h($u['role']) ?></td>
    <td><?= h($u['account_name']) ?></td>
    <td><?= (int)$u['active'] ?></td>
    <td><?= h($u['created_at']) ?></td>
    <td><?= h($u['last_login']) ?></td>
    <td>
        <form method="post" style="display:inline">
            <input type="hidden" name="form_action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit">Toggle active</button>
        </form>
        <form method="post" style="display:inline">
            <input type="hidden" name="form_action" value="password">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <input type="password" name="password" placeholder="nova lozinka" required>
            <button type="submit">Set pass</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
