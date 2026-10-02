<?php
require_once __DIR__.'/production_workflow.php';

function inventoryOpen(PDO $db,string $code,?int $location,string $notes): int {
    if(trim($code)===''||strlen($code)>200||($location&&!stockLotExists($db,'stock_emplacements',$location)))throw new InvalidArgumentException('Code et emplacement valides requis.');
    $db->beginTransaction();try{
        $db->prepare("INSERT INTO stock_inventaires(code,emplacement_id,statut,notes) VALUES(?,?,'ouvert',?)")->execute([trim($code),$location,$notes]);$id=(int)$db->lastInsertId();
        $sql='SELECT id FROM stock_emplacements WHERE actif=1'.($location?' AND id='.(int)$location:'');$places=$db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $articles=$db->query('SELECT id FROM stock_articles')->fetchAll(PDO::FETCH_COLUMN);
        $insert=$db->prepare('INSERT INTO stock_inventaire_lignes(inventaire_id,article_id,quantite_theorique,emplacement_id) VALUES(?,?,?,?)');
        foreach($places as $place)foreach($articles as $article){$qty=productionQuantity($db,(int)$article,(int)$place);$insert->execute([$id,$article,$qty,$place]);}
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Counts are retained even when the validation rejects a stock change during counting. */
function inventoryCount(PDO $db,int $id,array $counts,array $notes): void {
    $q=$db->prepare("SELECT id FROM stock_inventaires WHERE id=? AND statut='ouvert'");$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Inventaire fermé ou introuvable.');
    $q=$db->prepare('SELECT id FROM stock_inventaire_lignes WHERE inventaire_id=?');$q->execute([$id]);$rows=$q->fetchAll(PDO::FETCH_COLUMN);$clean=[];
    foreach($rows as $line){$qty=filter_var($counts[$line]??null,FILTER_VALIDATE_FLOAT);if($qty===false||!is_finite($qty)||$qty<0)throw new InvalidArgumentException('Toutes les lignes doivent être comptées avec une quantité positive ou nulle.');$clean[]=[$qty,$qty,$notes[$line]??'',$line,$id];}
    $db->beginTransaction();try{
        $q=$db->prepare('UPDATE stock_inventaire_lignes SET quantite_comptee=?,ecart=?-quantite_theorique,note=? WHERE id=? AND inventaire_id=?');foreach($clean as $row)$q->execute($row);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function inventoryValidate(PDO $db,int $id,int $userId):void {
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT * FROM stock_inventaires WHERE id=?');$q->execute([$id]);$inv=$q->fetch(PDO::FETCH_ASSOC);if(!$inv)throw new InvalidArgumentException('Inventaire introuvable.');
        if($inv['statut']==='valide'){$db->commit();return;}
        $q=$db->prepare('SELECT * FROM stock_inventaire_lignes WHERE inventaire_id=?');$q->execute([$id]);$lines=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($lines as $line){
            if($line['quantite_comptee']===null)throw new InvalidArgumentException('Comptages incomplets.');
            if(abs(productionQuantity($db,(int)$line['article_id'],(int)$line['emplacement_id'])-(float)$line['quantite_theorique'])>0.000001)throw new InvalidArgumentException('Stock modifié depuis ouverture : ouvrez un nouvel inventaire avec un comptage actualisé.');
            if(abs((float)$line['ecart'])>0.000001){
                $q=$db->prepare('SELECT COUNT(*) FROM stock_unites WHERE article_id=?');$q->execute([$line['article_id']]);
                if($q->fetchColumn())throw new InvalidArgumentException('Écart sur article sérialisé : rapprochez les séries et recomptez avant validation. Aucun ajustement automatique.');
            }
        }
        foreach($lines as $line){$delta=(float)$line['ecart'];if(abs($delta)<0.000001)continue;
            $source=$delta<0?(int)$line['emplacement_id']:null;$dest=$delta>0?(int)$line['emplacement_id']:null;
            $db->prepare("INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_source_id,emplacement_destination_id,reference_externe,note,user_id) VALUES(?,'correction_inventaire',?,?,?,?,?,?)")->execute([$line['article_id'],abs($delta),$source,$dest,'INV:'.$id,'Écart inventaire '.$inv['code'].' — '.($line['note']??''),$userId]);
            $q=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='correction_inventaire' AND emplacement_source_id IS NOT NULL AND emplacement_destination_id IS NULL THEN -quantite WHEN type IN ('reception','retour_projet','recuperation','correction_inventaire') THEN quantite WHEN type IN ('consommation','affectation_projet','rebut','demontage') THEN -quantite ELSE 0 END),0) FROM stock_mouvements WHERE article_id=?");$q->execute([$line['article_id']]);$db->prepare('UPDATE stock_articles SET quantite_stock=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(float)$q->fetchColumn(),$line['article_id']]);
        }
        $db->prepare("UPDATE stock_inventaires SET statut='valide',valide_par=?,date_validation=CURRENT_TIMESTAMP WHERE id=?")->execute([$userId,$id]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
