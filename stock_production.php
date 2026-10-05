<?php
$pageTitle='Nomenclature et production';$activePage='stock_pilotage';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/stock/production_workflow.php';
requerirConnexion();requerirAdmin();runSchemaMigrations();$db=getDB();productionSchema($db);
$bid=(int)($_GET['bom_id']??0);$q=$db->prepare('SELECT b.*,a.reference parent,p.nom projet FROM stock_boms b LEFT JOIN stock_articles a ON a.id=b.article_parent_id LEFT JOIN projets p ON p.id=b.projet_id WHERE b.id=?');$q->execute([$bid]);$bom=$q->fetch();
if(!$bom){setFlash('error','BOM introuvable.');redirect('stock_pilotage.php?tab=bom');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfRequire();try{
        $action=$_POST['action']??'';
        if($action==='composition'){
            $rows=[];foreach(($_POST['article_id']??[]) as $i=>$article)$rows[]=['article_id'=>$article,'quantite'=>$_POST['quantite'][$i]??null,'reference_position'=>$_POST['position'][$i]??'','notes'=>$_POST['notes'][$i]??''];
            productionBomSave($db,$bid,$rows,(string)($_POST['statut']??''));
        }elseif($action==='planifier')productionPlan($db,$bid,$_POST,(int)utilisateurCourant()['id']);
        elseif($action==='terminer'){
            $oid=(int)($_POST['order_id']??0);$q=$db->prepare('SELECT id FROM stock_production WHERE id=? AND bom_id=?');$q->execute([$oid,$bid]);if(!$q->fetchColumn())throw new InvalidArgumentException('Ordre hors nomenclature.');
            productionComplete($db,$oid,$_POST,(int)utilisateurCourant()['id']);
        }else throw new InvalidArgumentException('Action invalide.');
        setFlash('success','Nomenclature / production enregistrée.');redirect('stock_production.php?bom_id='.$bid);
    }catch(Throwable $e){setFlash('error',$e instanceof PDOException?'Enregistrement refusé : référence ou série déjà utilisée, ou relation invalide.':$e->getMessage());}
}
$items=productionBomItems($db,$bid);
$articles=$db->query('SELECT id,reference FROM stock_articles ORDER BY reference')->fetchAll();
$locations=$db->query('SELECT id,chemin FROM stock_emplacements WHERE actif=1 ORDER BY chemin')->fetchAll();
$users=$db->query('SELECT id,identifiant FROM utilisateurs ORDER BY identifiant')->fetchAll();
$q=$db->prepare('SELECT id,nom_fichier FROM documents WHERE projet_id=? ORDER BY id');$q->execute([$bom['projet_id']]);$documents=$q->fetchAll();
$q=$db->prepare('SELECT o.*,u.identifiant responsable,d.nom_fichier document FROM stock_production o JOIN utilisateurs u ON u.id=o.responsable_id JOIN documents d ON d.id=o.document_id WHERE o.bom_id=? ORDER BY o.id DESC');$q->execute([$bid]);$orders=$q->fetchAll();
$locked=(bool)$orders;
function productionOptions(array $rows,string $label,$selected=0):void{foreach($rows as $r)echo '<option value="'.(int)$r['id'].'" '.((int)$selected===(int)$r['id']?'selected':'').'>'.e($r[$label]).'</option>';}
require __DIR__.'/includes/header.php';?>
<div class="page-header"><div><h1>Nomenclature et production</h1><p><?=e($bom['reference'].' — indice '.$bom['version'].' — '.$bom['parent'])?></p><p><a href="<?=url('production.php?projet_id='.(int)$bom['projet_id'])?>"><?=e($bom['projet'])?></a></p></div><a class="btn btn-secondary" href="<?=url('stock_pilotage.php?tab=bom')?>">Retour aux BOM</a></div>
<div class="card" style="padding:1.25rem;margin-bottom:1rem"><h2>Composition par produit</h2>
<p>La quantité de chaque ligne est multipliée par le nombre de produits. Une BOM utilisée par un ordre conserve son indice et sa composition.</p>
<form method="post" id="compositionForm"><?=csrfField()?><input type="hidden" name="action" value="composition">
<table class="table"><thead><tr><th>Position</th><th>Article</th><th>Quantité par produit</th><th>Notes</th></tr></thead><tbody>
<?php $editRows=$items;if(!$locked)for($i=0;$i<6;$i++)$editRows[]=[];foreach($editRows as $i=>$item):?>
<tr><td><input aria-label="Position <?=($i+1)?>" class="form-control" name="position[]" value="<?=e($item['reference_position']??'')?>" <?=$locked?'disabled':''?>></td><td><select aria-label="Article <?=($i+1)?>" class="form-control" name="article_id[]" <?=$locked?'disabled':''?>><option value="">Ligne vide</option><?php productionOptions($articles,'reference',$item['article_id']??0);?></select></td><td><input aria-label="Quantité <?=($i+1)?>" class="form-control" type="number" step="any" min="0.000001" name="quantite[]" value="<?=e((string)($item['quantite']??1))?>" <?=$locked?'disabled':''?>></td><td><input aria-label="Note <?=($i+1)?>" class="form-control" name="notes[]" value="<?=e($item['notes']??'')?>" <?=$locked?'disabled':''?>></td></tr><?php endforeach;?></tbody></table>
<?php if(!$locked):?><label>Statut<select name="statut" class="form-control"><?php foreach(['brouillon','validee','obsolete'] as $s):?><option <?=$bom['statut']===$s?'selected':''?>><?=e($s)?></option><?php endforeach;?></select></label><button class="btn btn-primary">Enregistrer la composition</button><?php endif;?></form>
<?php $costs=['HT'=>0.0,'TTC'=>0.0];foreach($items as $item)$costs[$item['taxe']==='TTC'?'TTC':'HT']+=(float)$item['quantite']*(float)$item['valeur_unitaire'];?>
<p>Coût matières par produit : <strong><?=number_format($costs['HT'],2,',',' ')?> € HT</strong> + <strong><?=number_format($costs['TTC'],2,',',' ')?> € TTC</strong>. Main-d’œuvre, essais et transport additionnel à prévoir séparément.</p></div>
<?php if($bom['statut']==='validee'&&$items):?><div class="card" style="padding:1.25rem;margin-bottom:1rem"><h2>Planifier un ordre d’assemblage</h2>
<form method="post" id="orderForm"><?=csrfField()?><input type="hidden" name="action" value="planifier"><div class="form-row"><label>Référence OF<input class="form-control" name="reference" required maxlength="200"></label><label>Nombre de produits<input class="form-control" name="quantite" type="number" min="1" max="1000" value="1" required></label><label>Date métier d’assemblage<input class="form-control" name="date_metier" type="date" required></label><label>Responsable<select class="form-control" name="responsable_id" required><option value="">Choisir</option><?php productionOptions($users,'identifiant');?></select></label><label>Instruction Nextcloud du projet<select class="form-control" name="document_id" required><option value="">Choisir</option><?php productionOptions($documents,'nom_fichier');?></select></label></div><button class="btn btn-primary">Planifier l’ordre</button></form></div><?php endif;?>
<?php foreach($orders as $o):$snapshot=json_decode($o['snapshot_json'],true);?>
<div id="order-<?=(int)$o['id']?>" class="card" style="padding:1.25rem;margin-bottom:1rem"><h2><?=e($o['reference'])?> — <?=e($o['statut'])?></h2><p><?= (int)$o['quantite']?> produits — <?=e($o['date_metier'])?> — <?=e($o['responsable'])?> — instruction : <a href="<?=url('documents_externes.php?projet_id='.(int)$bom['projet_id'].'#document-'.(int)$o['document_id'])?>"><?=e($o['document'])?></a></p>
<?php if($o['statut']==='planifie'):?><p>Choisissez un lot libéré et son emplacement pour chaque composant. La validation enregistre consommation, produit fini, séries et généalogie dans une même transaction.</p>
<form method="post" class="completeOrderForm"><?=csrfField()?><input type="hidden" name="action" value="terminer"><input type="hidden" name="order_id" value="<?=(int)$o['id']?>">
<?php foreach($snapshot['items'] as $item):$aid=(int)$item['article_id'];$q=$db->prepare("SELECT id,reference_lot FROM stock_lots WHERE article_id=? AND statut='libere' ORDER BY date_reception,id");$q->execute([$aid]);$lots=$q->fetchAll();?>
<div class="form-row"><strong><?=e($item['reference'])?> : <?=e((string)($item['quantite']*$o['quantite']))?> u</strong><label>Lot <?=e($item['reference'])?><select class="form-control" name="lot[<?=$aid?>]" required><option value="">Choisir</option><?php productionOptions($lots,'reference_lot');?></select></label><label>Source <?=e($item['reference'])?><select class="form-control" name="source[<?=$aid?>]" required><option value="">Choisir</option><?php productionOptions($locations,'chemin');?></select></label></div><?php endforeach;?>
<label>Destination des produits finis<select class="form-control" name="destination_id" required><option value="">Choisir</option><?php productionOptions($locations,'chemin');?></select></label>
<label>Numéros de série, un par ligne<textarea class="form-control" name="series" required></textarea></label><label>Résultats du contrôle et preuve documentaire<textarea class="form-control" name="resultat" required maxlength="10000"></textarea></label><button class="btn btn-primary">Terminer l’assemblage et enregistrer le stock</button></form>
<?php else:?><p><?=nl2br(e($o['resultat']))?></p><p>Enregistré par acteur connecté #<?=(int)$o['user_id']?> le <?=e($o['completed_at'])?>.</p>
<?php $q=$db->prepare('SELECT u.numero_serie,a.reference,l.reference_lot,c.quantite FROM stock_production_composants c JOIN stock_unites u ON u.id=c.unite_id JOIN stock_articles a ON a.id=c.article_id JOIN stock_lots l ON l.id=c.lot_id WHERE c.production_id=? ORDER BY u.numero_serie,a.reference');$q->execute([$o['id']]);?>
<table class="table"><tr><th>Produit / série</th><th>Composant</th><th>Lot source</th><th>Quantité</th></tr><?php foreach($q->fetchAll() as $r):?><tr><td><?=e($r['numero_serie'])?></td><td><a href="<?=url('recherche.php?q='.rawurlencode($r['reference']))?>"><?=e($r['reference'])?></a></td><td><?=e($r['reference_lot'])?></td><td><?=e((string)$r['quantite'])?></td></tr><?php endforeach;?></table><?php endif;?></div><?php endforeach;?>
<?php require __DIR__.'/includes/footer.php';?>
