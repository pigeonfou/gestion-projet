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

    // ------------------------------------------------------------------
    // Architecture Stock R&D v2
    // Catalogue / emplacements / lots / unités / mouvements / réservations
    // ------------------------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS stock_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_id INTEGER,
        code TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL,
        type_article TEXT,
        actif INTEGER NOT NULL DEFAULT 1,
        FOREIGN KEY (parent_id) REFERENCES stock_categories(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_emplacements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_id INTEGER,
        code TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'zone',
        chemin TEXT NOT NULL,
        niveau INTEGER NOT NULL DEFAULT 0,
        actif INTEGER NOT NULL DEFAULT 1,
        notes TEXT,
        FOREIGN KEY (parent_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_lots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        fournisseur_id INTEGER,
        reference_lot TEXT,
        date_reception DATE,
        quantite_initiale REAL NOT NULL DEFAULT 0,
        cout_unitaire_ht REAL DEFAULT 0,
        tva REAL,
        statut TEXT NOT NULL DEFAULT 'libere',
        date_peremption DATE,
        certificat TEXT,
        notes TEXT,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (fournisseur_id) REFERENCES stock_fournisseurs(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_unites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        numero_serie TEXT,
        code_barres TEXT,
        uid_rfid TEXT,
        statut TEXT NOT NULL DEFAULT 'en_stock',
        emplacement_id INTEGER,
        projet_id INTEGER,
        date_acquisition DATE,
        date_mise_service DATE,
        date_fin_vie DATE,
        notes TEXT,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (emplacement_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        UNIQUE(numero_serie),
        UNIQUE(code_barres),
        UNIQUE(uid_rfid)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_mouvements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        type TEXT NOT NULL CHECK(type IN (
            'reception','consommation','affectation_projet','retour_projet',
            'transfert','correction_inventaire','rebut','demontage',
            'recuperation','reservation','liberation_reservation'
        )),
        quantite REAL NOT NULL CHECK(quantite > 0),
        emplacement_source_id INTEGER,
        emplacement_destination_id INTEGER,
        projet_id INTEGER,
        fournisseur_id INTEGER,
        lot_id INTEGER,
        unite_id INTEGER,
        reference_externe TEXT,
        note TEXT,
        user_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (emplacement_source_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL,
        FOREIGN KEY (emplacement_destination_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        FOREIGN KEY (fournisseur_id) REFERENCES stock_fournisseurs(id) ON DELETE SET NULL,
        FOREIGN KEY (lot_id) REFERENCES stock_lots(id) ON DELETE SET NULL,
        FOREIGN KEY (unite_id) REFERENCES stock_unites(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_reservations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        projet_id INTEGER NOT NULL,
        quantite REAL NOT NULL CHECK(quantite > 0),
        statut TEXT NOT NULL DEFAULT 'active' CHECK(statut IN ('active','liberee','consommee','annulee')),
        emplacement_id INTEGER,
        date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_fin DATETIME,
        note TEXT,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE,
        FOREIGN KEY (emplacement_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_equipements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        unite_id INTEGER,
        numero_serie TEXT,
        etat TEXT NOT NULL DEFAULT 'en_stock',
        projet_id INTEGER,
        emplacement_id INTEGER,
        date_achat DATE,
        date_affectation DATE,
        date_integration DATE,
        date_demontage DATE,
        origine TEXT,
        documentation TEXT,
        notes TEXT,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (unite_id) REFERENCES stock_unites(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        FOREIGN KEY (emplacement_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_recuperations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        equipement_id INTEGER NOT NULL,
        article_id INTEGER NOT NULL,
        unite_id INTEGER,
        quantite REAL NOT NULL DEFAULT 1,
        provenance TEXT,
        etat TEXT,
        test_resultat TEXT,
        destination_emplacement_id INTEGER,
        projet_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (equipement_id) REFERENCES stock_equipements(id) ON DELETE CASCADE,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (unite_id) REFERENCES stock_unites(id) ON DELETE SET NULL,
        FOREIGN KEY (destination_emplacement_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_boms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_parent_id INTEGER,
        projet_id INTEGER,
        reference TEXT,
        designation TEXT,
        version TEXT NOT NULL DEFAULT '1.0',
        statut TEXT NOT NULL DEFAULT 'brouillon',
        notes TEXT,
        FOREIGN KEY (article_parent_id) REFERENCES stock_articles(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_bom_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bom_id INTEGER NOT NULL,
        article_id INTEGER NOT NULL,
        quantite REAL NOT NULL DEFAULT 1,
        designation TEXT,
        reference_position TEXT,
        obligatoire INTEGER NOT NULL DEFAULT 1,
        notes TEXT,
        FOREIGN KEY (bom_id) REFERENCES stock_boms(id) ON DELETE CASCADE,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_inventaires (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        date_inventaire DATETIME DEFAULT CURRENT_TIMESTAMP,
        emplacement_id INTEGER,
        statut TEXT NOT NULL DEFAULT 'ouvert',
        valide_par INTEGER,
        date_validation DATETIME,
        notes TEXT,
        FOREIGN KEY (emplacement_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS stock_inventaire_lignes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        inventaire_id INTEGER NOT NULL,
        article_id INTEGER NOT NULL,
        quantite_theorique REAL NOT NULL DEFAULT 0,
        quantite_comptee REAL,
        ecart REAL,
        emplacement_id INTEGER,
        note TEXT,
        FOREIGN KEY (inventaire_id) REFERENCES stock_inventaires(id) ON DELETE CASCADE,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        FOREIGN KEY (emplacement_id) REFERENCES stock_emplacements(id) ON DELETE SET NULL
    )");

    // Attributs techniques communs au catalogue. Les attributs spécifiques
    // pourront ensuite être spécialisés par catégorie sans gonfler stock_articles.
    $extraArticleCols = [
        'categorie_id' => 'INTEGER',
        'fabricant' => 'TEXT',
        'reference_fabricant' => 'TEXT',
        'boitier' => 'TEXT',
        'valeur_technique' => 'TEXT',
        'tolerance' => 'TEXT',
        'puissance' => 'TEXT',
        'tension' => 'TEXT',
        'plage_temperature' => 'TEXT',
        'rohs_reach' => 'TEXT',
        'masse_g' => 'REAL',
        'matiere' => 'TEXT',
        'dangereux' => 'INTEGER DEFAULT 0',
        'flux_dechet' => 'TEXT',
        'recyclable' => 'INTEGER DEFAULT 0',
    ];
    foreach ($extraArticleCols as $col => $def) {
        $acols = $db->query('PRAGMA table_info(stock_articles)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($col, $acols, true)) {
            try { $db->exec("ALTER TABLE stock_articles ADD COLUMN $col $def"); } catch (Throwable $e) {}
        }
    }

    // Emplacement racine et catégories de base : idempotent.
    $root = $db->query("SELECT id FROM stock_emplacements WHERE code='LABO' LIMIT 1")->fetchColumn();
    if ($root === false) {
        $db->prepare("INSERT INTO stock_emplacements (code,nom,type,chemin,niveau) VALUES (?,?,?,?,0)")
           ->execute(['LABO','LABO','zone','LABO']);
        $root = (int)$db->lastInsertId();
    }
    $defaultLocations = [
        ['CMS','CMS','zone','LABO/CMS'],
        ['TRAVERSANTS','Composants traversants','zone','LABO/TRAVERSANTS'],
        ['CONNECTIQUE','Connectique','zone','LABO/CONNECTIQUE'],
        ['MODULES','Modules','zone','LABO/MODULES'],
        ['EQUIPEMENTS','Équipements','zone','LABO/EQUIPEMENTS'],
        ['MECANIQUE','Mécanique','zone','LABO/MECANIQUE'],
        ['BOITIERS','Boîtiers','zone','LABO/BOITIERS'],
        ['CABLES','Câbles','zone','LABO/CABLES'],
        ['PROJETS','Projets','zone','LABO/PROJETS'],
    ];
    foreach ($defaultLocations as $loc) {
        $st = $db->prepare("INSERT OR IGNORE INTO stock_emplacements (parent_id,code,nom,type,chemin,niveau) VALUES (?,?,?,?,?,1)");
        $st->execute([(int)$root,$loc[0],$loc[1],$loc[2],$loc[3]]);
    }

    // Migration idempotente de l'ancien stock direct vers le journal.
    $mig = $db->query("SELECT valeur FROM parametres WHERE cle='stock_v2_legacy_migrated'")->fetchColumn();
    if ($mig !== '1') {
        $lab = (int)$root;
        $rows = $db->query("SELECT id, quantite_stock, valeur_unitaire FROM stock_articles")->fetchAll(PDO::FETCH_ASSOC);
        $ins = $db->prepare("INSERT INTO stock_mouvements
            (article_id,type,quantite,emplacement_destination_id,reference_externe,note)
            VALUES (?,?,?,?,?,?)");
        foreach ($rows as $row) {
            $qty = (float)$row['quantite_stock'];
            if ($qty > 0) {
                $exists = $db->prepare("SELECT 1 FROM stock_mouvements WHERE article_id=? AND type='correction_inventaire' AND reference_externe='MIGRATION_V2' LIMIT 1");
                $exists->execute([(int)$row['id']]);
                if (!$exists->fetchColumn()) {
                    $ins->execute([(int)$row['id'],'correction_inventaire',$qty,$lab,'MIGRATION_V2','Reprise du stock historique avant passage au journal des mouvements']);
                }
            }
        }
        $db->prepare("INSERT INTO parametres(cle,valeur) VALUES('stock_v2_legacy_migrated','1') ON CONFLICT(cle) DO UPDATE SET valeur=excluded.valeur, updated_at=CURRENT_TIMESTAMP")->execute();
    }

    // ------------------------------------------------------------------
    // Socle système de management : qualité / environnement / traçabilité.
    // Générique volontairement : les exigences ISO sont portées par les
    // processus et documents, pas codées en dur dans le module Stock.
    // ------------------------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS processus (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL,
        pilote TEXT,
        description TEXT,
        actif INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS documents_controles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference TEXT NOT NULL UNIQUE,
        titre TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'document',
        processus_id INTEGER,
        projet_id INTEGER,
        statut TEXT NOT NULL DEFAULT 'brouillon',
        version TEXT NOT NULL DEFAULT '1.0',
        auteur TEXT,
        verificateur TEXT,
        approbateur TEXT,
        date_creation DATE,
        date_approbation DATE,
        date_effet DATE,
        date_revue DATE,
        document_parent_id INTEGER,
        chemin_fichier TEXT,
        mime TEXT,
        confidentialite TEXT DEFAULT 'interne',
        description TEXT,
        FOREIGN KEY (processus_id) REFERENCES processus(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        FOREIGN KEY (document_parent_id) REFERENCES documents_controles(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS documents_controles_revisions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        document_id INTEGER NOT NULL,
        version TEXT NOT NULL,
        motif TEXT,
        auteur TEXT,
        date_revision DATETIME DEFAULT CURRENT_TIMESTAMP,
        chemin_fichier TEXT,
        FOREIGN KEY (document_id) REFERENCES documents_controles(id) ON DELETE CASCADE,
        UNIQUE(document_id, version)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS risques_opportunites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        processus_id INTEGER,
        projet_id INTEGER,
        type TEXT NOT NULL DEFAULT 'risque',
        description TEXT NOT NULL,
        cause TEXT,
        consequence TEXT,
        probabilite INTEGER,
        impact INTEGER,
        criticite INTEGER,
        action_prevue TEXT,
        responsable TEXT,
        echeance DATE,
        statut TEXT NOT NULL DEFAULT 'ouvert',
        efficacite TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (processus_id) REFERENCES processus(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS actions_qualite (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        processus_id INTEGER,
        projet_id INTEGER,
        type TEXT NOT NULL DEFAULT 'action',
        origine TEXT,
        description TEXT NOT NULL,
        responsable TEXT,
        echeance DATE,
        statut TEXT NOT NULL DEFAULT 'ouverte',
        date_cloture DATE,
        preuve_document_id INTEGER,
        verification_efficacite TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (processus_id) REFERENCES processus(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        FOREIGN KEY (preuve_document_id) REFERENCES documents_controles(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS non_conformites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference TEXT NOT NULL UNIQUE,
        processus_id INTEGER,
        projet_id INTEGER,
        article_id INTEGER,
        lot_id INTEGER,
        unite_id INTEGER,
        mouvement_id INTEGER,
        description TEXT NOT NULL,
        detection TEXT,
        disposition TEXT,
        cause TEXT,
        action_immediate TEXT,
        action_corrective TEXT,
        responsable TEXT,
        echeance DATE,
        statut TEXT NOT NULL DEFAULT 'ouverte',
        date_detection DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_cloture DATE,
        verification_efficacite TEXT,
        FOREIGN KEY (processus_id) REFERENCES processus(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE SET NULL,
        FOREIGN KEY (lot_id) REFERENCES stock_lots(id) ON DELETE SET NULL,
        FOREIGN KEY (unite_id) REFERENCES stock_unites(id) ON DELETE SET NULL,
        FOREIGN KEY (mouvement_id) REFERENCES stock_mouvements(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS enregistrements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        reference TEXT,
        processus_id INTEGER,
        projet_id INTEGER,
        document_id INTEGER,
        source_table TEXT,
        source_id INTEGER,
        date_evenement DATETIME DEFAULT CURRENT_TIMESTAMP,
        resultat TEXT,
        statut TEXT,
        donnees_json TEXT,
        notes TEXT,
        FOREIGN KEY (processus_id) REFERENCES processus(id) ON DELETE SET NULL,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE SET NULL,
        FOREIGN KEY (document_id) REFERENCES documents_controles(id) ON DELETE SET NULL
    );

    $db->exec("CREATE TABLE IF NOT EXISTS stock_environnement (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL,
        masse_g REAL,
        matiere TEXT,
        substance_reglementee TEXT,
        dangereux INTEGER NOT NULL DEFAULT 0,
        filiere_dechet TEXT,
        recyclage_possible INTEGER NOT NULL DEFAULT 0,
        emballage TEXT,
        consignes TEXT,
        FOREIGN KEY (article_id) REFERENCES stock_articles(id) ON DELETE CASCADE,
        UNIQUE(article_id)
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
