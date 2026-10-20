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
require __DIR__.'/includes/header.php';
require __DIR__.'/views/stock/production.php';
