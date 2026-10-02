<?php
require_once __DIR__.'/production_workflow.php';
function reservationSchema(PDO $db):void {
    $db->exec('CREATE TABLE IF NOT EXISTS stock_reservation_historique(id INTEGER PRIMARY KEY AUTOINCREMENT,reservation_id INTEGER NOT NULL REFERENCES stock_reservations(id),utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id),action TEXT NOT NULL,details TEXT NOT NULL,date_action TEXT DEFAULT CURRENT_TIMESTAMP)');
}
function reservationHistory(PDO $db,int $id,int $user,string $action,array $details):void {
    $db->prepare('INSERT INTO stock_reservation_historique(reservation_id,utilisateur_id,action,details) VALUES(?,?,?,?)')->execute([$id,$user,$action,json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
}
function reservationCreate(PDO $db,array $p,int $user):int {
    $article=(int)($p['article_id']??0);$project=(int)($p['projet_id']??0);$location=(int)($p['emplacement_id']??0);$qty=filter_var($p['quantite']??null,FILTER_VALIDATE_FLOAT);$note=trim((string)($p['note']??''));
    if($qty===false||!is_finite($qty)||$qty<=0||!stockLotExists($db,'stock_articles',$article)||!stockLotExists($db,'projets',$project)||!stockLotExists($db,'stock_emplacements',$location))throw new InvalidArgumentException('Article, projet, emplacement et quantité positive requis.');
    $db->beginTransaction();try{
        $q=$db->prepare("SELECT COALESCE(SUM(quantite),0) FROM stock_reservations WHERE article_id=? AND statut='active' AND (emplacement_id=? OR emplacement_id IS NULL)");$q->execute([$article,$location]);
        if(productionQuantity($db,$article,$location)-(float)$q->fetchColumn()+0.000001<$qty)throw new InvalidArgumentException('Stock disponible insuffisant dans cet emplacement après réservations actives.');
        $db->prepare('INSERT INTO stock_reservations(article_id,projet_id,quantite,emplacement_id,note) VALUES(?,?,?,?,?)')->execute([$article,$project,$qty,$location,$note]);$id=(int)$db->lastInsertId();
        reservationHistory($db,$id,$user,'creation',['article'=>$article,'projet'=>$project,'emplacement'=>$location,'quantite'=>$qty,'note'=>$note]);$db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function reservationClose(PDO $db,int $id,string $status,int $user,string $reason):void {
    if(!in_array($status,['liberee','annulee'],true)||trim($reason)==='')throw new InvalidArgumentException('Motif et statut de clôture valides requis.');
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT * FROM stock_reservations WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Réservation introuvable.');
        if($r['statut']===$status){$db->commit();return;}if($r['statut']!=='active')throw new InvalidArgumentException('Réservation déjà fermée.');
        $db->prepare('UPDATE stock_reservations SET statut=?,date_fin=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$id]);reservationHistory($db,$id,$user,$status,['motif'=>$reason,'quantite'=>$r['quantite']]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/** Called within the same transaction as the physical consumption. */
function reservationConsume(PDO $db,int $article,int $project,int $location,float $quantity,int $user,string $reference):void {
    if(!$db->inTransaction())throw new LogicException('Consommation de réservation hors transaction.');
    $q=$db->prepare("SELECT * FROM stock_reservations WHERE article_id=? AND projet_id=? AND statut='active' AND (emplacement_id=? OR emplacement_id IS NULL) ORDER BY id");$q->execute([$article,$project,$location]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){if($quantity<=0.000001)break;$used=min($quantity,(float)$r['quantite']);$left=(float)$r['quantite']-$used;
        if($left<0.000001)$db->prepare("UPDATE stock_reservations SET statut='consommee',date_fin=CURRENT_TIMESTAMP WHERE id=?")->execute([$r['id']]);
        else {
            $db->prepare('UPDATE stock_reservations SET quantite=? WHERE id=?')->execute([$left,$r['id']]);
            $db->prepare("INSERT INTO stock_reservations(article_id,projet_id,quantite,emplacement_id,statut,note,date_fin) VALUES(?,?,?,?,'consommee',?,CURRENT_TIMESTAMP)")->execute([$article,$project,$used,$location,'Fraction réservation #'.$r['id'].' — '.$reference]);
            $child=(int)$db->lastInsertId();reservationHistory($db,$child,$user,'consommee',['origine'=>$r['id'],'quantite'=>$used,'reference'=>$reference]);
        }
        reservationHistory($db,(int)$r['id'],$user,'consommation',['quantite'=>$used,'reste'=>$left,'reference'=>$reference]);$quantity-=$used;
    }
}
