<?php
/**
 * Gestion des paramètres globaux (key/value en base)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/schema.php';

function ensureSettingsTable(): void {
    runSchemaMigrations();
}

function getSetting(string $key, ?string $default = null): ?string {
    ensureSettingsTable();
    $stmt = getDB()->prepare('SELECT valeur FROM parametres WHERE cle = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v !== false ? $v : $default;
}

function setSetting(string $key, ?string $value): void {
    ensureSettingsTable();
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO parametres (cle, valeur, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(cle) DO UPDATE SET valeur = excluded.valeur, updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$key, $value]);
}

function getAllSettings(): array {
    ensureSettingsTable();
    $rows = getDB()->query('SELECT cle, valeur FROM parametres')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['cle']] = $r['valeur'];
    return $out;
}

/** Defaults for first install */
function defaultSettings(): array {
    return [
        'site_nom' => 'ProjectFlow',
        'site_tagline' => 'Gestion de projets, processus & documentation',
        'nextcloud_url' => 'https://nextcloud.procomm.rd',
        'nextcloud_user' => getenv('PROJECTFLOW_NEXTCLOUD_USER') ?: '',
        'nextcloud_password' => getenv('PROJECTFLOW_NEXTCLOUD_PASSWORD') ?: '',
        'nextcloud_webdav' => getenv('PROJECTFLOW_NEXTCLOUD_WEBDAV') ?: '',
        'nextcloud_root' => 'ProjectFlow',
        'nextcloud_enabled' => '0',
        'items_per_page' => '20',
        'timezone' => 'Europe/Paris',
        'date_format' => 'd/m/Y',
        'allow_all_mime' => '0',
        'max_upload_mb' => '50',
    ];
}

function seedSettingsIfEmpty(): void {
    ensureSettingsTable();
    $count = (int) getDB()->query('SELECT COUNT(*) FROM parametres')->fetchColumn();
    if ($count === 0) {
        foreach (defaultSettings() as $k => $v) {
            setSetting($k, $v);
        }
    }
}

function phaseFolderName(string $phase): string {
    return match ($phase) {
        'cahier' => '01_Expression_besoin',
        'capacite' => '02_Etude_capacite',
        'investissement' => '03_Besoin_investissement',
        'proto' => '04_Proto',
        'tests' => '05_Tests_conformite',
        'production' => '06_Production',
        'livraison' => '07_Livraison',
        default => '00_Divers',
    };
}

function sanitizePathSegment(string $name): string {
    $name = preg_replace('/[^\p{L}\p{N}\_\-\.\s]/u', '', $name) ?? '';
    $name = preg_replace('/\s+/', '_', trim($name)) ?? '';
    return $name !== '' ? $name : 'Sans_nom';
}
