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
        'current_step' => 'INTEGER DEFAULT 1',
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

    // Les anciennes installations utilisaient l'étape 0 pour la note de cadrage.
    // Elle est désormais intégrée à l'étape 1.
    try { $db->exec('UPDATE projets SET current_step = 1 WHERE current_step = 0'); } catch (Throwable $e) {}

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

    // Stocks & Matériel R&D
    $db->exec("CREATE TABLE IF NOT EXISTS stock_fournisseurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        contact TEXT,
        email TEXT,
        telephone TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS stock_articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference TEXT NOT NULL UNIQUE,
        designation TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'piece' CHECK(type IN ('piece','equipement')),
        description TEXT,
        quantite_stock REAL NOT NULL DEFAULT 0,
        quantite_min REAL NOT NULL DEFAULT 0,
        unite TEXT DEFAULT 'u',
        valeur_unitaire REAL DEFAULT 0,
        taxe TEXT DEFAULT 'HT',
        documentation TEXT,
        emplacement TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS stock_article_fournisseur (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        fournisseur_id INTEGER NOT NULL,
        reference_fournisseur TEXT,
        prix REAL DEFAULT 0,
        delai_jours INTEGER,
        preferentiel INTEGER DEFAULT 0,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (fournisseur_id) REFERENCES stock_fournisseurs(id) ON DELETE CASCADE,
        UNIQUE(article_id, fournisseur_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS stock_usages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        projet_id INTEGER NOT NULL,
        quantite REAL NOT NULL DEFAULT 1,
        date_usage DATETIME DEFAULT CURRENT_TIMESTAMP,
        note TEXT,
        source_key TEXT,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
    )");

    // Index
    foreach ([
        'CREATE INDEX IF NOT EXISTS idx_taches_projet ON taches(projet_id)',
        'CREATE INDEX IF NOT EXISTS idx_taches_assigne ON taches(assigne_a)',
        'CREATE INDEX IF NOT EXISTS idx_taches_source ON taches(source_key)',
        'CREATE INDEX IF NOT EXISTS idx_jalons_cahier ON jalons(cahier_id)',
        'CREATE INDEX IF NOT EXISTS idx_documents_projet ON documents(projet_id)',
        'CREATE INDEX IF NOT EXISTS idx_cahiers_projet ON cahiers(projet_id)',
        'CREATE INDEX IF NOT EXISTS idx_stock_articles_ref ON stock_articles(reference)',
        'CREATE INDEX IF NOT EXISTS idx_stock_articles_type ON stock_articles(type)',
        'CREATE INDEX IF NOT EXISTS idx_stock_usages_article ON stock_usages(article_id)',
        'CREATE INDEX IF NOT EXISTS idx_stock_usages_projet ON stock_usages(projet_id)',
        'CREATE INDEX IF NOT EXISTS idx_stock_af_article ON stock_article_fournisseur(article_id)',
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    $done = true;
}
