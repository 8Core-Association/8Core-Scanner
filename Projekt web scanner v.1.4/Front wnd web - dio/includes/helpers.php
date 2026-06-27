<?php
/**
 * Plaćena licenca
 * (c) 2026 Tomislav Galić <tomislav@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenja autora.
 */
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function risk_class($risk) {
    if ($risk === 'CRITICAL') return 'risk-critical';
    if ($risk === 'HIGH') return 'risk-high';
    if ($risk === 'MEDIUM') return 'risk-medium';
    return 'risk-low';
}

function action_class($status) {
    if ($status === 'ignore') return 'status-ignore';
    if ($status === 'quarantine_requested') return 'status-quarantine';
    if ($status === 'delete_requested') return 'status-delete';
    if ($status === 'checked') return 'status-checked';
    return 'status-new';
}

function has_column(PDO $pdo, $table, $column) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

function flash_message() {
    if (!empty($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return '';
}
