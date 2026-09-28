<?php
/**
 * Migrations schéma unifiées (main + index). Une fois par requête.
 */
require_once __DIR__ . '/../config/db.php';

function runSchemaMigrations(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db = getDB();
    $db->exec('PRAGMA foreign_keys = ON');

    // Cahiers
    $cols = $db->query('PRAGMA table_info(cahiers)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('specs_json', $cols, true)) {
        $db->exec('ALTER TABLE cahiers ADD COLUMN specs_json TEXT');
    }
    if (!in_array('date_maj', $cols, true)) {
        try { $db->exec('ALTER TABLE cahiers ADD COLUMN date_maj DATETIME'); } catch (Throwable $e) {}
    }

    // Projets (processus R1b + cadrage)
    $pcols = $db->query('PRAGMA table_info(projets)')->fetchAll(PDO::FETCH_COLUMN, 1);
    $projetCols = [
        'current_step' => 'INTEGER DEFAULT 0',
        'go_decision' => 'TEXT',
        'step_notes' => 'TEXT',
        'status' => "TEXT DEFAULT 'actif'",
        'cadrage_commerciale' => 'INTEGER DEFAULT 0',
        'cadrage_technique' => 'INTEGER DEFAULT 0',
        'cadrage_destination' => 'TEXT',
    ];
    foreach ($projetCols as $col => $def) {
        if (!in_array($col, $pcols, true)) {
            $db->exec("ALTER TABLE projets ADD COLUMN $col $def");
        }
    }

    // Jalons
    $db->exec("CREATE TABLE IF NOT EXISTS jalons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cahier_id INTEGER NOT NULL,
        nom TEXT NOT NULL,
        date_prevue DATE,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");

    // Paramètres & documents
    $db->exec("CREATE TABLE IF NOT EXISTS parametres (
        cle TEXT PRIMARY KEY,
        valeur TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL,
        phase TEXT NOT NULL DEFAULT 'cahier',
        nom_fichier TEXT NOT NULL,
        chemin_nextcloud TEXT NOT NULL,
        taille INTEGER DEFAULT 0,
        mime TEXT,
        uploader_id INTEGER,
        date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
    )");

    // Tâches étendues
    $tcols = $db->query('PRAGMA table_info(taches)')->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach (['assigne_a' => 'TEXT', 'source_key' => 'TEXT', 'kanban_status' => "TEXT DEFAULT 'a_faire'"] as $col => $def) {
        if (!in_array($col, $tcols, true)) {
            $db->exec("ALTER TABLE taches ADD COLUMN $col $def");
        }
    }

    // Index
    foreach ([
        'CREATE INDEX IF NOT EXISTS idx_taches_projet ON taches(projet_id)',
        'CREATE INDEX IF NOT EXISTS idx_taches_assigne ON taches(assigne_a)',
        'CREATE INDEX IF NOT EXISTS idx_taches_source ON taches(source_key)',
        'CREATE INDEX IF NOT EXISTS idx_jalons_cahier ON jalons(cahier_id)',
        'CREATE INDEX IF NOT EXISTS idx_documents_projet ON documents(projet_id)',
        'CREATE INDEX IF NOT EXISTS idx_cahiers_projet ON cahiers(projet_id)',
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    $done = true;
}
