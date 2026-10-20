<?php
function pfRecodeMigration(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS document_operations(id INTEGER PRIMARY KEY AUTOINCREMENT,projet_id INTEGER NOT NULL REFERENCES projets(id),remote_path TEXT NOT NULL,metadata_json TEXT NOT NULL,statut TEXT NOT NULL DEFAULT 'pending',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $cols = $db->query('PRAGMA table_info(taches)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('source_uid', $cols, true)) $db->exec('ALTER TABLE taches ADD COLUMN source_uid TEXT');
    foreach ([
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_taches_source_uid ON taches(projet_id,source_uid) WHERE source_uid IS NOT NULL',
        'CREATE INDEX IF NOT EXISTS idx_mov_article_source ON stock_mouvements(article_id,emplacement_source_id)',
        'CREATE INDEX IF NOT EXISTS idx_mov_article_destination ON stock_mouvements(article_id,emplacement_destination_id)',
        'CREATE INDEX IF NOT EXISTS idx_mov_article_lot ON stock_mouvements(article_id,lot_id)',
        'CREATE INDEX IF NOT EXISTS idx_decisions_project_date ON projet_decisions(projet_id,date_decision,id)',
        'CREATE INDEX IF NOT EXISTS idx_reservation_available ON stock_reservations(article_id,emplacement_id,statut,projet_id)',
    ] as $sql) $db->exec($sql);
}
