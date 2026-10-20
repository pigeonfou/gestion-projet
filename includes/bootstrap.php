<?php
require_once __DIR__ . '/../src/Security/Session.php';
pfStartSession();
require_once __DIR__ . '/../src/Services/Transaction.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/settings_helper.php';
try {
    runSchemaMigrations();
} catch (Throwable $e) {
    error_log('ProjectFlow migration: ' . $e->getMessage());
    http_response_code(503);
    exit('Initialisation de la base impossible. Consultez le journal serveur.');
}

