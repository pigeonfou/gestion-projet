<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/settings_helper.php';
try {
    runSchemaMigrations();
} catch (Throwable $e) {
    // DB absente au premier install
}
