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

/** User-scoped preferences used for personal interface choices. */
function ensureUserPreferencesTable(): void {
    static $ready = false;
    if ($ready) return;
    getDB()->exec("CREATE TABLE IF NOT EXISTS preferences_utilisateur (
        utilisateur_id INTEGER NOT NULL,
        cle TEXT NOT NULL,
        valeur TEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (utilisateur_id, cle),
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
    )");
    $ready = true;
}

function getUserPreference(int $userId, string $key, ?string $default = null): ?string {
    if ($userId < 1) return $default;
    ensureUserPreferencesTable();
    $stmt = getDB()->prepare('SELECT valeur FROM preferences_utilisateur WHERE utilisateur_id = ? AND cle = ?');
    $stmt->execute([$userId, $key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
}

function setUserPreference(int $userId, string $key, string $value): void {
    if ($userId < 1) throw new InvalidArgumentException('Utilisateur invalide.');
    ensureUserPreferencesTable();
    $stmt = getDB()->prepare('INSERT INTO preferences_utilisateur (utilisateur_id, cle, valeur, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(utilisateur_id, cle) DO UPDATE SET valeur = excluded.valeur, updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$userId, $key, $value]);
}

/** The existing OddWorks appearance is kept as a selectable fallback. */
function appearancePresets(): array {
    return [
        'classic' => ['label' => 'OddWorks classique', 'description' => 'L’apparence actuelle d’OddWorks.', 'swatches' => ['#081726', '#09b9c9', '#ff7a21']],
        'space' => ['label' => 'Space', 'description' => 'Centre de pilotage R&D, bleu nuit et tracés lumineux.', 'swatches' => ['#071522', '#19c8d2', '#ff7a21']],
        'carnet-laboratoire' => ['label' => 'Carnet de laboratoire', 'description' => 'Papier ivoire, dessins de terrain et vert profond.', 'swatches' => ['#f2ead7', '#294637', '#bd5537']],
        'bauhaus-industriel' => ['label' => 'Bauhaus industriel', 'description' => 'Formes franches, noir, rouge signal et jaune.', 'swatches' => ['#f6f1e5', '#171717', '#e6352b']],
        'organique-contemporain' => ['label' => 'Organique contemporain', 'description' => 'Surfaces souples, sauge, sable et argile.', 'swatches' => ['#f0eee4', '#71866a', '#c97755']],
        'grille-typographique-suisse' => ['label' => 'Grille typographique suisse', 'description' => 'Grille nette, noir et blanc, accent vermillon.', 'swatches' => ['#fafafa', '#171717', '#ed302c']],
        'arcade-y2k' => ['label' => 'Arcade Y2K', 'description' => 'Lavande, rose vif, vert acide et surfaces brillantes.', 'swatches' => ['#ece8f7', '#f000a8', '#a8f500']],
        'atelier-art-deco' => ['label' => 'Atelier Art déco', 'description' => 'Géométrie architecturale, bordeaux et laiton.', 'swatches' => ['#f1e7d4', '#581a20', '#c7a35d']],
        'roman-graphique' => ['label' => 'Roman graphique', 'description' => 'Cases encrées, trames imprimées, rouge et jaune.', 'swatches' => ['#f5efdd', '#171717', '#e53b32']],
        'bureau-studio' => ['label' => 'Bureau Studio', 'description' => 'Fenêtres de travail flottantes et violet doux.', 'swatches' => ['#efedf5', '#4b3d64', '#c7e755']],
        'atelier-isometrique' => ['label' => 'Atelier isométrique', 'description' => 'Maquette d’atelier, béton, brique et moutarde.', 'swatches' => ['#e8e5dd', '#59675a', '#b9543b']],
        'console-studio' => ['label' => 'Console de studio', 'description' => 'Pistes de séquençage, commandes tactiles et voyants.', 'swatches' => ['#272a2b', '#d85a32', '#89bd54']],
    ];
}

function validAppearancePreset(?string $preset): string {
    return is_string($preset) && array_key_exists($preset, appearancePresets()) ? $preset : 'classic';
}

function interfacePreset(?int $userId = null): string {
    $userId ??= (int)($_SESSION['user_id'] ?? 0);
    return validAppearancePreset(getUserPreference($userId, 'ui_preset', 'classic'));
}

/** Defaults for first install */
function defaultSettings(): array {
    return [
        'site_nom' => 'OddWorks',
        'ui_theme' => 'light',
        'search_highlight_color' => '#ffad42',
        'site_tagline' => 'Des idées un peu différentes pour des projets bien réels',
        'nextcloud_url' => 'https://nextcloud.procomm.rd',
        'nextcloud_user' => getenv('PROJECTFLOW_NEXTCLOUD_USER') ?: '',
        'nextcloud_password' => getenv('PROJECTFLOW_NEXTCLOUD_PASSWORD') ?: '',
        'nextcloud_webdav' => getenv('PROJECTFLOW_NEXTCLOUD_WEBDAV') ?: '',
        'nextcloud_root' => 'OddWorks',
        'nextcloud_enabled' => '0',
        'items_per_page' => '20',
        'timezone' => 'Europe/Paris',
        'date_format' => 'd/m/Y',
        'allow_all_mime' => '1',
        'max_upload_mb' => '0',
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

    // Migration de marque uniquement pour les anciennes valeurs par défaut.
    // Les personnalisations utilisateur et le dossier Nextcloud existant sont conservés.
    $db = getDB();
    $db->exec("UPDATE parametres SET valeur = 'OddWorks', updated_at = CURRENT_TIMESTAMP WHERE cle = 'site_nom' AND valeur = 'ProjectFlow'");
    $db->exec("UPDATE parametres SET valeur = 'Des idées un peu différentes pour des projets bien réels', updated_at = CURRENT_TIMESTAMP WHERE cle = 'site_tagline' AND valeur = 'Gestion de projets, processus & documentation'");
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

/** Restrict appearance values to CSS hex colors. */
function searchHighlightColor(?string $value = null): string {
    $value = $value ?? getSetting('search_highlight_color', '#ffad42');
    return preg_match('/^#[0-9a-fA-F]{6}$/D', $value ?? '') ? strtolower($value) : '#ffad42';
}

/** Validated account color preference; older accounts inherit the global default. */
function interfaceTheme(?int $userId = null): string {
    $userId ??= (int)($_SESSION['user_id'] ?? 0);
    $theme = $userId > 0 ? getUserPreference($userId, 'ui_theme') : null;
    $theme ??= getSetting('ui_theme', 'light');
    return in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'light';
}
