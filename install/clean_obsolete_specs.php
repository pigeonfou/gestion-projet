<?php
/**
 * Nettoyage des champs obsolètes du CDC structuré
 * (onglets Performance, Environnement, Technique, Support — supprimés).
 *
 * Usage CLI (recommandé) :
 *   php install/clean_obsolete_specs.php
 *
 * Ou navigateur (compte admin) :
 *   /install/clean_obsolete_specs.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cahier_specs.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/../includes/auth.php';
    requerirConnexion();
    if (!estAdmin()) {
        http_response_code(403);
        echo 'Accès réservé aux administrateurs.';
        exit;
    }
}

$updated = cleanAllObsoleteSpecs();

if ($isCli) {
    echo "Nettoyage terminé.\n";
    echo "Cahiers modifiés : {$updated}\n";
    exit(0);
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Nettoyage CDC</title></head><body style="font-family:system-ui;padding:2rem">';
echo '<h1>Nettoyage des champs obsolètes du CDC</h1>';
echo '<p>Cahiers modifiés : <strong>' . (int)$updated . '</strong></p>';
echo '<p>Les clés des anciens onglets 3–6 ont été retirées de <code>specs_json</code>.</p>';
echo '<p><a href="../projets.php">Retour aux projets</a></p>';
echo '</body></html>';
