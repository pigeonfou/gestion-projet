<?php
require_once __DIR__.'/Transaction.php';
require_once __DIR__.'/../../includes/stock/reservation_workflow.php';

/** Validates and records a physical movement together with reservation consumption. */
function pfRecordStockMovement(PDO $db, int $article, string $type, float $quantity,
    ?int $source, ?int $destination, ?int $project = null, ?int $supplier = null,
    ?int $lot = null, ?string $reference = null, ?string $note = null, ?int $user = null): int {
    return pfTransaction($db, function () use ($db,$article,$type,$quantity,$source,$destination,$project,$supplier,$lot,$reference,$note,$user) {
        if (!is_finite($quantity) || $quantity<=0) throw new InvalidArgumentException('Quantité positive et finie requise.');
        $outgoing=['consommation','affectation_projet','rebut','demontage'];
        $incoming=['reception','retour_projet','recuperation'];
        if (in_array($type,$outgoing,true)) {
            if (!$source || $destination) throw new InvalidArgumentException('Ce mouvement exige une source et aucune destination.');
        } elseif (in_array($type,$incoming,true)) {
            if ($source || !$destination) throw new InvalidArgumentException('Ce mouvement exige une destination et aucune source.');
        } elseif ($type==='transfert') {
            if (!$source || !$destination || $source===$destination) throw new InvalidArgumentException('Deux emplacements distincts sont requis.');
        } elseif ($type==='correction_inventaire') {
            if ((bool)$source === (bool)$destination) throw new InvalidArgumentException('Une correction exige une source ou une destination.');
        } else throw new InvalidArgumentException('Utilisez le module de réservation pour réserver ou libérer du stock.');
        if (!stockLotExists($db,'stock_articles',$article)) throw new InvalidArgumentException('Article introuvable.');
        $check=$db->prepare('SELECT 1 FROM stock_emplacements WHERE id=? AND actif=1');
        foreach ([$source,$destination] as $location) if ($location) {
            $check->execute([$location]);if (!$check->fetchColumn()) throw new InvalidArgumentException('Emplacement actif introuvable.');
        }
        if ($lot) {
            $q=$db->prepare('SELECT statut FROM stock_lots WHERE id=? AND article_id=?');$q->execute([$lot,$article]);$status=$q->fetchColumn();
            if ($status===false || (in_array($type,$outgoing,true) && $status!=='libere')) throw new InvalidArgumentException('Lot du même article et libéré requis pour cette sortie.');
        }
        if ($source) {
            $physical=productionQuantity($db,$article,$source);
            if ($physical+0.000001<$quantity || ($lot && productionQuantity($db,$article,$source,$lot)+0.000001<$quantity)) throw new InvalidArgumentException('Stock insuffisant dans l’emplacement source.');
            $q=$db->prepare("SELECT COALESCE(SUM(quantite),0) FROM stock_reservations WHERE article_id=? AND statut='active' AND (emplacement_id=? OR emplacement_id IS NULL) AND (? IS NULL OR projet_id<>?)");
            // Only a project consumption may use its own reservations.
            $own=in_array($type,['consommation','affectation_projet'],true)?$project:null;
            $q->execute([$article,$source,$own,$own]);
            if ($physical-(float)$q->fetchColumn()+0.000001<$quantity) throw new InvalidArgumentException('Stock réservé indisponible dans cet emplacement.');
        }
        $q=$db->prepare('INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_source_id,emplacement_destination_id,projet_id,fournisseur_id,lot_id,reference_externe,note,user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$article,$type,$quantity,$source,$destination,$project,$supplier,$lot,$reference,$note,$user]);
        $id=(int)$db->lastInsertId();
        if ($project && in_array($type,['consommation','affectation_projet'],true)) {
            reservationSchema($db);
            reservationConsume($db,$article,$project,$source,$quantity,(int)$user,$reference ?? 'MANUEL');
        }
        return $id;
    });
}
