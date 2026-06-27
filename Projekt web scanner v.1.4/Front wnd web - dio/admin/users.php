<?php
/**
 * Plaćena licenca
 * (c) 2026 Tomislav Galić <tomislav@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenja autora.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/helpers.php';
require_admin();

$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';
        $active   = isset($_POST['active']) ? 1 : 0;
        $selectedAccounts = isset($_POST['accounts']) && is_array($_POST['accounts']) ? $_POST['accounts'] : [];

        if ($username !== '' && $password !== '' && in_array($role, ['admin','user'], true)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO scanner_users (username, password_hash, role, account_name, active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $firstAccount = !empty($selectedAccounts) ? $selectedAccounts[0] : null;
            $stmt->execute([$username, $hash, $role, $firstAccount, $active]);
            $newId = (int)$pdo->lastInsertId();

            foreach ($selectedAccounts as $acc) {
                $acc = trim($acc);
                if ($acc === '') continue;
                $ins = $pdo->prepare("INSERT IGNORE INTO scanner_user_accounts (user_id, account_name) VALUES (?, ?)");
                $ins->execute([$newId, $acc]);
            }

            $message = "Korisnik \"$username\" kreiran.";
        } else {
            $message = 'Nedostaje username/password ili role nije ispravan.';
            $messageType = 'error';
        }
    }

    if ($formAction === 'accounts') {
        $id = (int)($_POST['id'] ?? 0);
        $selectedAccounts = isset($_POST['accounts']) && is_array($_POST['accounts']) ? $_POST['accounts'] : [];

        if ($id > 0) {
            $pdo->prepare("DELETE FROM scanner_user_accounts WHERE user_id = ?")->execute([$id]);
            $firstAccount = null;
            foreach ($selectedAccounts as $acc) {
                $acc = trim($acc);
                if ($acc === '') continue;
                $ins = $pdo->prepare("INSERT IGNORE INTO scanner_user_accounts (user_id, account_name) VALUES (?, ?)");
                $ins->execute([$id, $acc]);
                if ($firstAccount === null) $firstAccount = $acc;
            }
            $pdo->prepare("UPDATE scanner_users SET account_name = ? WHERE id = ?")->execute([$firstAccount, $id]);
            $message = 'Accounti ažurirani.';
        }
    }

    if ($formAction === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE scanner_users SET active = IF(active=1,0,1) WHERE id = ?")->execute([$id]);
        $message = 'Status promijenjen.';
    }

    if ($formAction === 'password') {
        $id       = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if ($id > 0 && $password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE scanner_users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
            $message = 'Lozinka promijenjena.';
        }
    }
}

$users = $pdo->query("SELECT * FROM scanner_users ORDER BY id ASC")->fetchAll();

// Accounti dostupni iz findings
$availableAccounts = $pdo->query("
    SELECT DISTINCT account_name
    FROM findings
    WHERE account_name IS NOT NULL AND account_name != ''
    ORDER BY account_name
")->fetchAll(PDO::FETCH_COLUMN);

// Accounti po korisniku
$userAccountsMap = [];
$rows = $pdo->query("SELECT user_id, account_name FROM scanner_user_accounts ORDER BY account_name")->fetchAll();
foreach ($rows as $r) {
    $userAccountsMap[$r['user_id']][] = $r['account_name'];
}
?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>8Core Scanner – Korisnici</title>
<link rel="stylesheet" href="../assets/css/scanner.css">
<style>
.accounts-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}
.account-check {
    display: flex;
    align-items: center;
    gap: 5px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.account-check input { margin: 0; cursor: pointer; }
.account-check:has(input:checked) {
    background: var(--accent-dim, rgba(0,100,255,.12));
    border-color: var(--accent);
    color: var(--accent);
    font-weight: 600;
}
.edit-accounts-panel {
    display: none;
    margin-top: 10px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px;
}
.edit-accounts-panel.open { display: block; }
.user-accounts-pills { display: flex; flex-wrap: wrap; gap: 4px; }
.user-accounts-pill {
    background: var(--accent-dim, rgba(0,100,255,.1));
    color: var(--accent);
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 11px;
    font-weight: 600;
}
</style>
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
    <div class="logo-version">Admin Panel v1.5</div>
  </div>

  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Admin</div>
    <a class="sidebar-link" href="index.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Pregled
    </a>
    <a class="sidebar-link active" href="users.php">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Korisnici
    </a>
    <div class="sidebar-section-label" style="margin-top:16px;">Scanner</div>
    <a class="sidebar-link" href="../index.php">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Otvori Scanner
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= h(mb_substr(current_user()['username'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= h(current_user()['username']) ?></div>
        <div class="user-role">admin</div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Korisnici</div>
    <div class="topbar-meta">
      <a href="../logout.php" style="color:var(--text-muted);font-size:12px;">Odjava</a>
    </div>
  </div>

  <div class="content">

    <?php if ($message): ?>
      <div class="notice <?= $messageType === 'error' ? '' : 'ok' ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <!-- ADD USER FORM -->
    <div class="panel">
      <h2>Dodaj korisnika</h2>
      <form method="post">
        <input type="hidden" name="form_action" value="create">
        <div class="form-row" style="flex-wrap:wrap;gap:8px;">
          <input type="text"     name="username" placeholder="username" required style="flex:1;min-width:140px;">
          <input type="password" name="password" placeholder="password" required style="flex:1;min-width:140px;">
          <select name="role" style="flex:0 0 auto;">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
          <label style="display:flex;align-items:center;gap:5px;font-size:13px;">
            <input type="checkbox" name="active" checked> active
          </label>
        </div>
        <?php if (!empty($availableAccounts)): ?>
        <div style="margin-top:12px;">
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">Dodijeli accounte:</div>
          <div class="accounts-grid">
            <?php foreach ($availableAccounts as $acc): ?>
              <label class="account-check">
                <input type="checkbox" name="accounts[]" value="<?= h($acc) ?>">
                <?= h($acc) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <div style="margin-top:12px;">
          <button type="submit" class="btn btn-primary">Kreiraj korisnika</button>
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
            <th>Accounti</th>
            <th>Status</th>
            <th>Zadnji login</th>
            <th>Akcije</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <?php $uAccounts = $userAccountsMap[(int)$u['id']] ?? []; ?>
        <tr>
          <td class="small mono"><?= (int)$u['id'] ?></td>
          <td><b><?= h($u['username']) ?></b></td>
          <td><span class="badge <?= $u['role'] === 'admin' ? 'risk-medium' : 'risk-low' ?>"><?= h($u['role']) ?></span></td>
          <td>
            <?php if (!empty($uAccounts)): ?>
              <div class="user-accounts-pills">
                <?php foreach ($uAccounts as $a): ?>
                  <span class="user-accounts-pill"><?= h($a) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:12px;">—</span>
            <?php endif; ?>
            <?php if (!empty($availableAccounts)): ?>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:6px;"
                    onclick="toggleAccounts('acc-<?= (int)$u['id'] ?>')">
              Uredi accounte
            </button>
            <div class="edit-accounts-panel" id="acc-<?= (int)$u['id'] ?>">
              <form method="post">
                <input type="hidden" name="form_action" value="accounts">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <div class="accounts-grid">
                  <?php foreach ($availableAccounts as $acc): ?>
                    <label class="account-check">
                      <input type="checkbox" name="accounts[]" value="<?= h($acc) ?>"
                             <?= in_array($acc, $uAccounts, true) ? 'checked' : '' ?>>
                      <?= h($acc) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div style="margin-top:10px;">
                  <button type="submit" class="btn btn-primary btn-sm">Spremi</button>
                  <button type="button" class="btn btn-ghost btn-sm"
                          onclick="toggleAccounts('acc-<?= (int)$u['id'] ?>')">Odustani</button>
                </div>
              </form>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['active']): ?>
              <span class="user-active">Active</span>
            <?php else: ?>
              <span class="user-inactive">Inactive</span>
            <?php endif; ?>
          </td>
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

<script>
function toggleAccounts(id) {
    var el = document.getElementById(id);
    el.classList.toggle('open');
}
</script>
</body>
</html>
