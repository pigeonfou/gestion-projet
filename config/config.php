<?php
/**
 * Configuration globale de l'application
 */

// Chemin de base de l'application (sans slash final)
// Laissez vide '' si le projet est à la racine du DocumentRoot
// Mettez '/gestion-projet' si le projet est dans un sous-dossier
$basePath = getenv('PROJECTFLOW_BASE_PATH');
define('BASE_PATH', $basePath === false ? '/gestion-projet' : rtrim($basePath, '/'));

// Chemin absolu vers la base de données
define('DB_PATH', getenv('PROJECTFLOW_DB_PATH') ?: '/var/lib/projectflow/database.sqlite');

/**
 * Génère une URL relative à la base de l'application
 */
function url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_PATH . ($path !== '' ? '/' . $path : '');
}

/**
 * Redirection helper
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}
