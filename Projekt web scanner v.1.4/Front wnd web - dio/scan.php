<?php
/**
 * Plaćena licenca
 * (c) 2026 Tomislav Galić <tomislav@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenja autora.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require_admin();

$config = require __DIR__ . '/includes/config.php';
define('SCAN_SCRIPT', $config['scan_script'] ?? '/root/ioc_scan.sh');
define('SCAN_LOG',    $config['scan_log']    ?? '/root/ioc-scan-live.log');

$status  = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    if (!file_exists(SCAN_SCRIPT)) {
        $status  = 'Skripta nije pronađena: ' . SCAN_SCRIPT;
        $isError = true;
    } elseif (!is_executable(SCAN_SCRIPT)) {
        $status  = 'Skripta nije izvršiva. Pokreni: chmod +x ' . SCAN_SCRIPT;
        $isError = true;
    } else {
        $cmd = 'sudo ' . escapeshellarg(SCAN_SCRIPT) . ' > /dev/null 2>&1 &';
        exec($cmd, $out, $ret);

        if ($ret === 0 || $ret === 1) {
            $status = 'Scan pokrenut u pozadini. Prati status na dashboard-u.';
        } else {
            $cmd2 = 'bash ' . escapeshellarg(SCAN_SCRIPT) . ' > /dev/null 2>&1 &';
            exec($cmd2, $out2, $ret2);
            if ($ret2 === 0) {
                $status = 'Scan pokrenut (bez sudo). Prati status na dashboard-u.';
            } else {
                $status  = 'Scan nije mogao biti pokrenut. Provjeri sudo konfiguraciju.';
                $isError = true;
            }
        }
    }

    if (!$isError) {
        $_SESSION['flash'] = $status;
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['stop'] ?? '') === '1') {
    $scriptName = basename(SCAN_SCRIPT);
    exec('sudo pkill -f ' . escapeshellarg($scriptName) . ' 2>&1', $killOut, $killRet);
    if ($killRet === 0) {
        $_SESSION['flash'] = 'Scan zaustavljen.';
    } else {
        $_SESSION['flash'] = 'Scan nije bio aktivan ili zaustavljanje nije uspjelo.';
    }
    header('Location: scan.php');
    exit;
}

// Prikaz zadnjih redaka loga
$logLines = [];
if (file_exists(SCAN_LOG) && is_readable(SCAN_LOG)) {
    $all = file(SCAN_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice($all, -40);
}

// Je li scan aktivan — traži samo root-owned proces (sudo), ne www-data PHP procese
$scanRunning = false;
exec('pgrep -u root -f ' . escapeshellarg(SCAN_SCRIPT) . ' 2>/dev/null', $pids, $pgrepRet);
$scanRunning = !empty(array_filter($pids));

// Flash poruka
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$lastScan = null;
try {
    $lastScan = $pdo->query("SELECT * FROM scans ORDER BY id DESC LIMIT 1")->fetch();
} catch (Throwable $e) {}
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
    <div class="logo-version">IOC Scanner v1.5</div>
  </div>

  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Menu</div>
    <a class="sidebar-link" href="index.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="sidebar-link active" href="scan.php">
      <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      Pokreni scan
    </a>
    <a class="sidebar-link" href="admin/index.php">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Admin panel
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
    <div class="topbar-title">Pokreni scan</div>
    <div class="topbar-meta">
      <?php if ($lastScan): ?>
        <span class="scan-dot <?= $lastScan['status'] === 'RUNNING' ? 'running' : '' ?>"></span>
        Zadnji scan: <?= h($lastScan['started_at']) ?>
        &nbsp;&middot;&nbsp; <?= h($lastScan['status']) ?>
      <?php else: ?>
        <span class="scan-dot" style="background:#94a3b8"></span>
        Nema podataka o scanu
      <?php endif; ?>
      &nbsp;&nbsp;
      <a href="logout.php" style="color:var(--text-muted);font-size:12px;">Odjava</a>
    </div>
  </div>

  <div class="content">

    <?php if ($flash): ?>
      <div class="notice ok"><?= h($flash) ?></div>
    <?php endif; ?>

    <?php if ($status && $isError): ?>
      <div class="notice"><?= h($status) ?></div>
    <?php endif; ?>

    <!-- TRIGGER PANEL -->
    <div class="panel">
      <h2>Manualni scan</h2>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">
        Pokreće <code class="rule-pattern"><?= h(SCAN_SCRIPT) ?></code>
        asinhrono u pozadini. Potrebno: <strong>sudo</strong> za web korisnika ili executable bit na skripti.
      </p>

      <div class="scan-sudo-hint">
        <b class="scan-sudo-hint-title">Postavljanje sudo permisije (jednom, kao root):</b>
        <code class="scan-sudo-cmd">
          echo "www-data ALL=(root) NOPASSWD: <?= h(SCAN_SCRIPT) ?>" &gt;&gt; /etc/sudoers.d/scanner
        </code>
      </div>

      <div class="scan-action-row">
        <?php if ($scanRunning): ?>
          <div class="scan-running-indicator">
            <span class="scan-dot running"></span>
            Scan je aktivan...
          </div>
          <form method="post">
            <input type="hidden" name="stop" value="1">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Zaustaviti aktivni scan?')">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
              Stop scan
            </button>
          </form>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Pokrenuti manualni IOC scan sada?')">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              Pokreni scan
            </button>
            <a href="index.php" class="btn btn-ghost" style="margin-left:8px;">Odustani</a>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- LIVE LOG -->
    <?php if (!empty($logLines)): ?>
    <div class="panel">
      <h2>Zadnjih 40 linija loga</h2>
      <div style="background:var(--bg);border-radius:8px;padding:14px 16px;overflow-x:auto;max-height:420px;overflow-y:auto;">
        <?php foreach ($logLines as $line): ?>
          <div style="font-family:monospace;font-size:12px;color:#94a3b8;line-height:1.7;white-space:pre;">
            <?= h($line) ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:8px;font-size:11px;color:var(--text-muted);">
        Log: <?= h(SCAN_LOG) ?>
      </div>
    </div>
    <?php else: ?>
    <div class="panel" style="color:var(--text-muted);font-size:13px;">
      Log fajl nije dostupan ili je prazan: <code><?= h(SCAN_LOG) ?></code>
    </div>
    <?php endif; ?>

  </div><!-- .content -->
</div><!-- .main -->
</div><!-- .layout -->
</body>
</html>
