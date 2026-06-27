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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>8Core Scanner – Users</title>
<link rel="stylesheet" href="../assets/css/scanner.css">
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span class="logo-text">8Core Scanner</span>
    </div>
    <div class="logo-version">IOC Scanner v3</div>
  </div>

  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Menu</div>
    <a class="sidebar-link" href="../index.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="sidebar-link active" href="users.php">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Users
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= h(mb_substr(current_user()['username'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= h(current_user()['username']) ?></div>
        <div class="user-role"><?= h(current_user()['role']) ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Users</div>
    <div class="topbar-meta">
      <a href="../logout.php" style="color:var(--text-muted);font-size:12px;">Odjava</a>
    </div>
  </div>

  <div class="content">

    <?php if ($message): ?>
      <div class="notice ok"><?= h($message) ?></div>
    <?php endif; ?>

    <!-- ADD USER FORM -->
    <div class="panel">
      <h2>Dodaj korisnika</h2>
      <form method="post">
        <input type="hidden" name="form_action" value="create">
        <div class="form-row">
          <input type="text"     name="username"     placeholder="username" required>
          <input type="password" name="password"     placeholder="password" required>
          <select name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
          <input type="text" name="account_name" list="accounts" placeholder="account_name">
          <datalist id="accounts">
            <?php foreach ($accounts as $a): ?><option value="<?= h($a) ?>"><?php endforeach; ?>
          </datalist>
          <label><input type="checkbox" name="active" checked> active</label>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>

    <!-- USERS TABLE -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Account</th>
            <th>Status</th>
            <th>Created</th>
            <th>Last login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="small mono"><?= (int)$u['id'] ?></td>
          <td><b><?= h($u['username']) ?></b></td>
          <td><span class="badge <?= $u['role'] === 'admin' ? 'risk-medium' : 'risk-low' ?>"><?= h($u['role']) ?></span></td>
          <td class="small mono"><?= h($u['account_name'] ?? '—') ?></td>
          <td>
            <?php if ($u['active']): ?>
              <span class="user-active">Active</span>
            <?php else: ?>
              <span class="user-inactive">Inactive</span>
            <?php endif; ?>
          </td>
          <td class="small"><?= h($u['created_at']) ?></td>
          <td class="small"><?= h($u['last_login'] ?? '—') ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
              <form method="post" style="display:inline">
                <input type="hidden" name="form_action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">
                  <?= $u['active'] ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
              <form method="post" style="display:inline;display:flex;gap:5px;align-items:center;">
                <input type="hidden" name="form_action" value="password">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="password" name="password" placeholder="nova lozinka"
                       style="padding:5px 8px;font-size:12px;border:1px solid var(--border);border-radius:6px;background:var(--surface2);" required>
                <button type="submit" class="btn btn-ghost btn-sm">Set pass</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div><!-- .content -->
</div><!-- .main -->
</div><!-- .layout -->
</body>
</html>
