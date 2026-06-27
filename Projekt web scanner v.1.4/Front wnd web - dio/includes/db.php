<?php
/**
 * Plaćena licenca
 * (c) 2026 Tomislav Galić <tomislav@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenja autora.
 */
$config = require __DIR__ . '/config.php';

$dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=' . $config['db_charset'];

$pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
