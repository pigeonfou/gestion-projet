<?php
/**
 * Fonctions communes du stock R&D.
 *
 * Principe : le stock physique est calculé à partir des mouvements.
 * stock_articles.quantite_stock reste temporairement un champ de compatibilité
 * avec l'ancien module et est synchronisé par stockSyncLegacyQuantity().
 */
require_once __DIR__ . '/../bootstrap.php';

function stockEnsureSchema(): void {
    runSchemaMigrations();
}

/** @return array<int,array{id:int,name:string,level:int}> */
function stockLocationTree(): array {
    $db = getDB();
    return $db->query("SELECT id, nom AS name, niveau AS level FROM stock_emplacements ORDER BY chemin, nom")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function stockDefaultLocationId(): ?int {
    $id = getDB()->query("SELECT id FROM stock_emplacements WHERE code = 'LABO' LIMIT 1")->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * Calcule le stock d'un article à partir du journal des mouvements.
 * Les mouvements positifs entrent dans le stock, les négatifs en sortent.
 */
function stockQuantity(int $articleId, ?int $locationId = null): float {
    $db = getDB();
    if ($locationId !== null) {
        $st = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM stock_mouvements WHERE article_id=? AND emplacement_destination_id=?");
        $st->execute([$articleId, $locationId]);
        $in = (float)$st->fetchColumn();
        $st = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM stock_mouvements WHERE article_id=? AND emplacement_source_id=?");
        $st->execute([$articleId, $locationId]);
        return $in - (float)$st->fetchColumn();
    }
    $st = $db->prepare("SELECT COALESCE(SUM(
        CASE
            WHEN type='correction_inventaire' AND emplacement_source_id IS NOT NULL AND emplacement_destination_id IS NULL THEN -quantite WHEN type IN ('reception','retour_projet','recuperation','correction_inventaire') THEN quantite
            WHEN type IN ('consommation','affectation_projet','rebut','demontage') THEN -quantite
            ELSE 0
        END
    ),0) FROM stock_mouvements WHERE article_id=?");
    $st->execute([$articleId]);
    return (float)$st->fetchColumn();
}

/** Synchronise le cache historique quantite_stock. */
function stockSyncLegacyQuantity(int $articleId): void {
    $qty = stockQuantity($articleId);
    $st = getDB()->prepare("UPDATE stock_articles SET quantite_stock=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
    $st->execute([$qty, $articleId]);
}

/**
 * Ajoute un mouvement au journal.
 *
 * quantite est toujours positive ; le type détermine le sens.
 * Pour un transfert, renseigner source + destination.
 */
function stockMovement(
    int $articleId,
    string $type,
    float $quantity,
    ?int $sourceLocationId = null,
    ?int $destinationLocationId = null,
    ?int $projetId = null,
    ?int $fournisseurId = null,
    ?int $lotId = null,
    ?string $reference = null,
    ?string $note = null,
    ?int $userId = null
): int {
    if ($quantity <= 0) {
        throw new InvalidArgumentException('La quantité doit être supérieure à zéro.');
    }
    $allowed = ['reception','consommation','affectation_projet','retour_projet','transfert','correction_inventaire','rebut','demontage','recuperation','reservation','liberation_reservation'];
    if (!in_array($type, $allowed, true)) {
        throw new InvalidArgumentException('Type de mouvement invalide.');
    }
    if ($type === 'transfert' && (!$sourceLocationId || !$destinationLocationId)) {
        throw new InvalidArgumentException('Un transfert nécessite un emplacement source et destination.');
    }
    $db = getDB();
    $st = $db->prepare("INSERT INTO stock_mouvements
        (article_id,type,quantite,emplacement_source_id,emplacement_destination_id,projet_id,fournisseur_id,lot_id,reference_externe,note,user_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$articleId,$type,$quantity,$sourceLocationId,$destinationLocationId,$projetId,$fournisseurId,$lotId,$reference,$note,$userId]);
    $id = (int)$db->lastInsertId();
    stockSyncLegacyQuantity($articleId);
    return $id;
}

/** Retourne les quantités par emplacement pour une référence. */
function stockByLocation(int $articleId): array {
    $db = getDB();
    $sql = "SELECT e.id,e.nom,e.code,e.chemin,
            COALESCE((SELECT SUM(m.quantite) FROM stock_mouvements m WHERE m.article_id=? AND m.emplacement_destination_id=e.id),0)
          - COALESCE((SELECT SUM(m.quantite) FROM stock_mouvements m WHERE m.article_id=? AND m.emplacement_source_id=e.id),0) AS quantite
            FROM stock_emplacements e
            WHERE e.actif=1
            ORDER BY e.chemin";
    $st = $db->prepare($sql);
    $st->execute([$articleId,$articleId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
