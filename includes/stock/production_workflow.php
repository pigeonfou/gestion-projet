<?php
/** Production metadata and lot genealogy; documentary bytes stay on Nextcloud. */
require_once __DIR__.'/lot_workflow.php';

function productionSchema(PDO $db): void {
    stockLotSchema($db);
    $db->exec("CREATE TABLE IF NOT EXISTS stock_production (
        id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL UNIQUE,
        bom_id INTEGER NOT NULL REFERENCES stock_boms(id), projet_id INTEGER NOT NULL REFERENCES projets(id),
        quantite INTEGER NOT NULL CHECK(quantite>0), date_metier TEXT NOT NULL,
        statut TEXT NOT NULL DEFAULT 'planifie', document_id INTEGER NOT NULL REFERENCES documents(id),
        responsable_id INTEGER NOT NULL REFERENCES utilisateurs(id), user_id INTEGER NOT NULL REFERENCES utilisateurs(id),
        resultat TEXT NOT NULL DEFAULT '', snapshot_json TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, completed_at TEXT
    );
    CREATE TABLE IF NOT EXISTS stock_production_composants (
        id INTEGER PRIMARY KEY AUTOINCREMENT, production_id INTEGER NOT NULL REFERENCES stock_production(id),
        unite_id INTEGER NOT NULL REFERENCES stock_unites(id), article_id INTEGER NOT NULL REFERENCES stock_articles(id),
        lot_id INTEGER NOT NULL REFERENCES stock_lots(id), quantite REAL NOT NULL CHECK(quantite>0),
        emplacement_id INTEGER NOT NULL REFERENCES stock_emplacements(id)
    );
    CREATE TABLE IF NOT EXISTS stock_production_unites (
        production_id INTEGER NOT NULL REFERENCES stock_production(id),
        unite_id INTEGER NOT NULL UNIQUE REFERENCES stock_unites(id), PRIMARY KEY(production_id,unite_id)
    )");
}

function productionBomItems(PDO $db,int $bomId): array {
    $q=$db->prepare('SELECT i.*,a.reference,a.designation article_nom,a.valeur_unitaire,a.taxe FROM stock_bom_items i JOIN stock_articles a ON a.id=i.article_id WHERE i.bom_id=? ORDER BY i.reference_position,i.id');
    $q->execute([$bomId]);return $q->fetchAll(PDO::FETCH_ASSOC);
}

function productionBomSave(PDO $db,int $bomId,array $rows,string $status): void {
    if(!in_array($status,['brouillon','validee','obsolete'],true))throw new InvalidArgumentException('Statut BOM invalide.');
    $q=$db->prepare('SELECT * FROM stock_boms WHERE id=?');$q->execute([$bomId]);$bom=$q->fetch(PDO::FETCH_ASSOC);
    if(!$bom)throw new InvalidArgumentException('BOM introuvable.');
    $q=$db->prepare('SELECT id FROM stock_production WHERE bom_id=? LIMIT 1');$q->execute([$bomId]);
    if($q->fetchColumn())throw new InvalidArgumentException('BOM utilisée par un ordre : créez un nouvel indice pour modifier sa composition.');
    $clean=[];$seen=[];
    foreach($rows as $row){
        $article=(int)($row['article_id']??0);if(!$article)continue;
        $qty=filter_var($row['quantite']??null,FILTER_VALIDATE_FLOAT);
        if($article===(int)$bom['article_parent_id']||isset($seen[$article])||!stockLotExists($db,'stock_articles',$article)||$qty===false||!is_finite($qty)||$qty<=0)throw new InvalidArgumentException('Composants distincts, différents du parent, et quantités positives requis.');
        $seen[$article]=true;$clean[]=[$bomId,$article,$qty,trim((string)($row['reference_position']??'')),trim((string)($row['notes']??''))];
    }
    if($status==='validee'&&(!$clean||!$bom['article_parent_id']||!$bom['projet_id']))throw new InvalidArgumentException('Parent, projet et composition nécessaires avant validation.');
    $db->beginTransaction();try{
        $db->prepare('DELETE FROM stock_bom_items WHERE bom_id=?')->execute([$bomId]);
        $q=$db->prepare('INSERT INTO stock_bom_items(bom_id,article_id,quantite,reference_position,notes) VALUES(?,?,?,?,?)');foreach($clean as $row)$q->execute($row);
        $db->prepare('UPDATE stock_boms SET statut=? WHERE id=?')->execute([$status,$bomId]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function productionPlan(PDO $db,int $bomId,array $p,int $userId): int {
    $q=$db->prepare("SELECT * FROM stock_boms WHERE id=? AND statut='validee'");$q->execute([$bomId]);$bom=$q->fetch(PDO::FETCH_ASSOC);
    $items=productionBomItems($db,$bomId);$date=(string)($p['date_metier']??'');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    $qty=filter_var($p['quantite']??null,FILTER_VALIDATE_INT);$ref=trim((string)($p['reference']??''));$doc=(int)($p['document_id']??0);$responsable=(int)($p['responsable_id']??0);
    if(!$bom||!$items||!$bom['projet_id']||!$bom['article_parent_id']||$qty===false||$qty<1||$qty>1000||$ref===''||strlen($ref)>200||!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('BOM validée, quantité entière1–1000, référence et date valides requises.');
    $q=$db->prepare('SELECT id FROM documents WHERE id=? AND projet_id=?');$q->execute([$doc,$bom['projet_id']]);if(!$q->fetchColumn())throw new InvalidArgumentException('Instruction Nextcloud du même projet requise.');
    $q=$db->prepare('SELECT id FROM utilisateurs WHERE id=?');$q->execute([$responsable]);if(!$q->fetchColumn())throw new InvalidArgumentException('Responsable requis.');
    $snapshot=['parent'=>(int)$bom['article_parent_id'],'reference'=>$bom['reference'],'version'=>$bom['version'],'items'=>$items];
    $db->prepare('INSERT INTO stock_production(reference,bom_id,projet_id,quantite,date_metier,document_id,responsable_id,user_id,snapshot_json) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$ref,$bomId,$bom['projet_id'],$qty,$date,$doc,$responsable,$userId,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    return (int)$db->lastInsertId();
}

function productionQuantity(PDO $db,int $article,int $location,?int $lot=null): float {
    $sql='SELECT COALESCE(SUM(CASE WHEN emplacement_destination_id=? THEN quantite ELSE 0 END),0)-COALESCE(SUM(CASE WHEN emplacement_source_id=? THEN quantite ELSE 0 END),0) FROM stock_mouvements WHERE article_id=?';$params=[$location,$location,$article];
    if($lot!==null){$sql.=' AND lot_id=?';$params[]=$lot;}
    $q=$db->prepare($sql);$q->execute($params);return (float)$q->fetchColumn();
}

/** Consume explicit released lots and create finished serials in one atomic transaction. */
function productionComplete(PDO $db,int $id,array $p,int $userId): void {
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT * FROM stock_production WHERE id=?');$q->execute([$id]);$order=$q->fetch(PDO::FETCH_ASSOC);
        if(!$order)throw new InvalidArgumentException('Ordre introuvable.');
        if($order['statut']==='termine'){$db->commit();return;}
        $snapshot=json_decode($order['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$qty=(int)$order['quantite'];$destination=(int)($p['destination_id']??0);$result=trim((string)($p['resultat']??''));
        $serials=preg_split('/[\r\n]+/',trim((string)($p['series']??'')), -1,PREG_SPLIT_NO_EMPTY);$serials=array_map('trim',$serials);
        if(!stockLotExists($db,'stock_emplacements',$destination)||$result===''||strlen($result)>10000||count($serials)!==$qty||count(array_unique($serials))!==$qty)throw new InvalidArgumentException('Résultat de contrôle, destination et un numéro de série distinct par produit requis.');
        $q=$db->prepare('SELECT id FROM stock_unites WHERE numero_serie=?');foreach($serials as $serial){if(strlen($serial)>200)throw new InvalidArgumentException('Numéro de série trop long.');$q->execute([$serial]);if($q->fetchColumn())throw new InvalidArgumentException('Numéro de série déjà utilisé : '.$serial);}
        $allocations=[];
        foreach($snapshot['items'] as $item){
            $article=(int)$item['article_id'];$lot=(int)($p['lot'][$article]??0);$location=(int)($p['source'][$article]??0);$need=(float)$item['quantite']*$qty;
            $q=$db->prepare("SELECT * FROM stock_lots WHERE id=? AND article_id=? AND statut='libere'");$q->execute([$lot,$article]);$l=$q->fetch(PDO::FETCH_ASSOC);
            if(!$l||$l['date_reception']>$order['date_metier']||!stockLotExists($db,'stock_emplacements',$location)||productionQuantity($db,$article,$location,$lot)+0.000001<$need||productionQuantity($db,$article,$location)+0.000001<$need)throw new InvalidArgumentException('Stock de lot libéré insuffisant à la date prévue pour '.$item['reference']);
            $q=$db->prepare("SELECT COALESCE(SUM(quantite),0) FROM stock_reservations WHERE article_id=? AND statut='active' AND projet_id<>? AND (emplacement_id IS NULL OR emplacement_id=?)");$q->execute([$article,$order['projet_id'],$location]);
            if(productionQuantity($db,$article,$location)-(float)$q->fetchColumn()+0.000001<$need)throw new InvalidArgumentException('Stock réservé à un autre projet : '.$item['reference']);
            $allocations[]=[$article,$lot,$location,(float)$item['quantite'],$need];
        }
        $move=$db->prepare('INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_source_id,emplacement_destination_id,projet_id,lot_id,unite_id,reference_externe,note,user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $unit=$db->prepare("INSERT INTO stock_unites(article_id,numero_serie,statut,emplacement_id,projet_id,date_acquisition,notes) VALUES(?,?,'en_stock',?,?,?,?)");
        foreach($serials as $serial){
            $unit->execute([$snapshot['parent'],$serial,$destination,$order['projet_id'],$order['date_metier'],'OF '.$order['reference'].' — '.$result]);$uid=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO stock_production_unites VALUES(?,?)')->execute([$id,$uid]);
            foreach($allocations as [$article,$lot,$location,$perUnit,$need]){
                $move->execute([$article,'consommation',$perUnit,$location,null,$order['projet_id'],$lot,$uid,'OF:'.$id,'Intégré dans '.$serial.' — date métier '.$order['date_metier'],$userId]);
                $db->prepare('INSERT INTO stock_production_composants(production_id,unite_id,article_id,lot_id,quantite,emplacement_id) VALUES(?,?,?,?,?,?)')->execute([$id,$uid,$article,$lot,$perUnit,$location]);
            }
            $move->execute([$snapshot['parent'],'recuperation',1,null,$destination,$order['projet_id'],null,$uid,'OF:'.$id,'Produit assemblé '.$serial.' — date métier '.$order['date_metier'],$userId]);
        }
        $ids=array_column($allocations,0);$ids[]=$snapshot['parent'];
        $calc=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='correction_inventaire' AND emplacement_source_id IS NOT NULL AND emplacement_destination_id IS NULL THEN -quantite WHEN type IN ('reception','retour_projet','recuperation','correction_inventaire') THEN quantite WHEN type IN ('consommation','affectation_projet','rebut','demontage') THEN -quantite ELSE 0 END),0) FROM stock_mouvements WHERE article_id=?");
        foreach($ids as $article){$calc->execute([$article]);$db->prepare('UPDATE stock_articles SET quantite_stock=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(float)$calc->fetchColumn(),$article]);}
        $db->prepare("UPDATE stock_production SET statut='termine',resultat=?,user_id=?,completed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$result,$userId,$id]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
