<?php
/**
 * Configuration globale de l'application
 */

// Chemin de base de l'application (sans slash final)
// Laissez vide '' si le projet est à la racine du DocumentRoot
// Mettez '/gestion-projet' si le projet est dans un sous-dossier
define('BASE_PATH', '/gestion-projet');

// Chemin absolu vers la base de données
define('DB_PATH', __DIR__ . '/../database.sqlite');

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
