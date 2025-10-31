<?php

$cfg = require __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($cfg['db_host'], $cfg['db_user'], $cfg['db_pass'], $cfg['db_name']);
    $conn->set_charset($cfg['charset']);
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}
