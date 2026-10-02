<?php
function stockLotSchema(PDO $db): void {
    $cols=$db->query('PRAGMA table_info(stock_lots)')->fetchAll(PDO::FETCH_COLUMN,1);
    foreach(['emplacement_id'=>'INTEGER REFERENCES stock_emplacements(id)','projet_id'=>'INTEGER REFERENCES projets(id)'] as $k=>$v) if(!in_array($k,$cols,true))$db->exec("ALTER TABLE stock_lots ADD COLUMN $k $v");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_lot_auto_reception ON stock_mouvements(lot_id) WHERE type='reception' AND reference_externe LIKE 'LOT:%'");
}

function stockLotExists(PDO $db,string $table,int $id): bool {
    if(!in_array($table,['stock_articles','stock_fournisseurs','stock_emplacements','projets'],true))throw new InvalidArgumentException('Relation invalide.');
    $q=$db->prepare("SELECT id FROM $table WHERE id=?".($table==='stock_emplacements'?' AND actif=1':''));$q->execute([$id]);return (bool)$q->fetchColumn();
}

/** A quarantined lot is metadata only; release inserts one physical receipt. */
function stockLotReceive(PDO $db,int $lotId,int $locationId,?int $projectId,int $userId): int {
    $own=!$db->inTransaction();if($own)$db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM stock_lots WHERE id=?');$q->execute([$lotId]);$lot=$q->fetch(PDO::FETCH_ASSOC);
        if(!$lot || !in_array($lot['statut'],['quarantaine','libere'],true))throw new InvalidArgumentException('Lot introuvable ou rejeté.');
        $qty=(float)$lot['quantite_initiale'];
        if(!is_finite($qty)||$qty<=0)throw new InvalidArgumentException('Quantité du lot invalide.');
        if(!stockLotExists($db,'stock_emplacements',$locationId)||($projectId&&!stockLotExists($db,'projets',$projectId)))throw new InvalidArgumentException('Emplacement actif et projet valides requis.');
        $q=$db->prepare("SELECT id,quantite,article_id,emplacement_destination_id,projet_id FROM stock_mouvements WHERE lot_id=? AND type='reception'");$q->execute([$lotId]);$existing=$q->fetchAll(PDO::FETCH_ASSOC);
        if($existing) {
            $sum=array_sum(array_column($existing,'quantite'));
            foreach($existing as $entry)if((int)$entry['article_id']!==(int)$lot['article_id'])throw new InvalidArgumentException('Mouvement existant incohérent avec le lot.');
            foreach($existing as $entry)if((int)$entry['emplacement_destination_id']!==$locationId||(int)$entry['projet_id']!==(int)$projectId)throw new InvalidArgumentException('Lot déjà reçu ailleurs : utilisez un mouvement de transfert, pas une nouvelle réception.');
            if(abs($sum-$qty)>0.000001)throw new InvalidArgumentException('Réceptions existantes partielles : rapprochez le lot avant libération, aucune entrée ajoutée.');
            $movement=(int)$existing[0]['id'];
        } else {
            $note='Réception lot '.$lot['reference_lot'].' — date métier '.$lot['date_reception'].' — '.($lot['notes']??'');
            $db->prepare("INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_destination_id,projet_id,fournisseur_id,lot_id,reference_externe,note,user_id) VALUES(?,'reception',?,?,?,?,?,?,?,?)")->execute([(int)$lot['article_id'],$qty,$locationId,$projectId,$lot['fournisseur_id'],$lotId,'LOT:'.$lotId,$note,$userId]);
            $movement=(int)$db->lastInsertId();
        }
        $db->prepare("UPDATE stock_lots SET statut='libere',emplacement_id=?,projet_id=? WHERE id=?")->execute([$locationId,$projectId,$lotId]);
        $q=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='correction_inventaire' AND emplacement_source_id IS NOT NULL AND emplacement_destination_id IS NULL THEN -quantite WHEN type IN ('reception','retour_projet','recuperation','correction_inventaire') THEN quantite WHEN type IN ('consommation','affectation_projet','rebut','demontage') THEN -quantite ELSE 0 END),0) FROM stock_mouvements WHERE article_id=?");$q->execute([$lot['article_id']]);
        $db->prepare('UPDATE stock_articles SET quantite_stock=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(float)$q->fetchColumn(),$lot['article_id']]);
        if($own)$db->commit();return $movement;
    } catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();throw $e;}
}

function stockLotCreate(PDO $db,array $p,int $userId): int {
    $article=(int)($p['article_id']??0);$supplier=(int)($p['fournisseur_id']??0);$location=(int)($p['emplacement_id']??0);$project=(int)($p['projet_id']??0);
    $ref=trim((string)($p['reference_lot']??''));$date=trim((string)($p['date_reception']??''));$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    $qty=filter_var($p['quantite']??null,FILTER_VALIDATE_FLOAT);$cost=filter_var($p['cout']??0,FILTER_VALIDATE_FLOAT);$tax=filter_var($p['tva']??0,FILTER_VALIDATE_FLOAT);$status=(string)($p['statut']??'quarantaine');$notes=trim((string)($p['notes']??''));
    if($ref===''||strlen($ref)>200||strlen($notes)>10000||!$d||$d->format('Y-m-d')!==$date||$qty===false||!is_finite($qty)||$qty<=0||$cost===false||!is_finite($cost)||$cost<0||$tax===false||!is_finite($tax)||$tax<0||$tax>100||!in_array($status,['quarantaine','libere','rejete'],true))throw new InvalidArgumentException('Lot : référence, date réelle, quantité positive et coût/TVA valides requis.');
    if(!stockLotExists($db,'stock_articles',$article)||($supplier&&!stockLotExists($db,'stock_fournisseurs',$supplier))||!stockLotExists($db,'stock_emplacements',$location)||($project&&!stockLotExists($db,'projets',$project)))throw new InvalidArgumentException('Relations article, fournisseur, emplacement ou projet invalides.');
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT id FROM stock_lots WHERE article_id=? AND reference_lot=?');$q->execute([$article,$ref]);if($q->fetchColumn())throw new InvalidArgumentException('Ce lot existe déjà pour cet article.');
        $db->prepare('INSERT INTO stock_lots(article_id,fournisseur_id,reference_lot,date_reception,quantite_initiale,cout_unitaire_ht,tva,statut,notes,emplacement_id,projet_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$article,$supplier?:null,$ref,$date,$qty,$cost,$tax,$status,$notes,$location,$project?:null]);
        $id=(int)$db->lastInsertId();if($status==='libere')stockLotReceive($db,$id,$location,$project?:null,$userId);
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
