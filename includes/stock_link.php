<?php
/**
 * Lie les lignes Matériel (étape 4) aux articles stock par référence.
 */
function syncStockUsagesFromComposants(int $projetId, array $composants): void
{
    runSchemaMigrations();
    $db = getDB();
    // Nettoyer usages auto précédents de ce projet
    $db->prepare("DELETE FROM stock_usages WHERE projet_id = ? AND source_key LIKE 'auto:%'")->execute([$projetId]);

    $find = $db->prepare('SELECT id FROM stock_articles WHERE reference = ? COLLATE NOCASE LIMIT 1');
    $ins = $db->prepare('INSERT INTO stock_usages (article_id, projet_id, quantite, note, source_key) VALUES (?,?,?,?,?)');

    foreach ($composants as $stId => $items) {
        if (!is_array($items)) continue;
        foreach ($items as $it) {
            $ref = trim((string)($it['reference'] ?? ''));
            if ($ref === '') continue;
            // Seulement si c'est du matériel (présence designation/ref typique)
            if (!isset($it['designation']) && !isset($it['quantite'])) continue;
            $find->execute([$ref]);
            $aid = (int)$find->fetchColumn();
            if ($aid <= 0) continue;
            $qty = (float)($it['quantite'] ?? 1);
            if ($qty <= 0) $qty = 1;
            $note = trim((string)($it['designation'] ?? ''));
            $key = 'auto:' . $projetId . ':' . $stId . ':' . $ref;
            $ins->execute([$aid, $projetId, $qty, $note !== '' ? $note : null, $key]);
        }
    }
}
