<?php
$pageTitle='Pilotage stock avancé'; $activePage='stock_pilotage';
require_once __DIR__.'/includes/bootstrap.php'; require_once __DIR__.'/includes/stock/stock_helpers.php'; require_once __DIR__.'/includes/stock/lot_workflow.php'; require_once __DIR__.'/includes/stock/inventory_workflow.php'; require_once __DIR__.'/includes/stock/reservation_workflow.php';
requerirConnexion(); runSchemaMigrations(); $db=getDB(); stockLotSchema($db); reservationSchema($db);
$tabs=['reservations'=>'Réservations','lots'=>'Lots & réception','unites'=>'Unités / séries','bom'=>'BOM / nomenclatures','inventaires'=>'Inventaires','environnement'=>'Environnement'];
$tab=$_GET['tab']??'reservations'; if(!isset($tabs[$tab]))$tab='reservations';
$pid=(int)($_GET['projet_id']??0);$articleFilter=(int)($_GET['article_id']??0);
function pfStockRows(array $rows): array {global $pid,$articleFilter;return array_values(array_filter($rows,fn($r)=>(!$pid||((int)($r['projet_id']??0)===$pid))&&(!$articleFilter||((int)($r['article_id']??$r['article_parent_id']??0)===$articleFilter))));}
$stockContextQuery=($pid?'&projet_id='.$pid:'').($articleFilter?'&article_id='.$articleFilter:'');
$articles=$db->query("SELECT id,reference,designation FROM stock_articles ORDER BY reference")->fetchAll();
$projets=$db->query("SELECT id,nom FROM projets ORDER BY nom")->fetchAll();
$empl=$db->query("SELECT id,chemin FROM stock_emplacements WHERE actif=1 ORDER BY chemin")->fetchAll();
$fourn=$db->query("SELECT id,nom FROM stock_fournisseurs ORDER BY nom")->fetchAll();
function so($rows,$label){foreach($rows as $r)echo '<option value="'.(int)$r['id'].'">'.e($r[$label]).'</option>';}
if($_SERVER['REQUEST_METHOD']==='POST'){csrfRequire();$a=$_POST['action']??'';
 try{
  requerirAdmin();
  if($a==='reservation')reservationCreate($db,$_POST,(int)utilisateurCourant()['id']);
  elseif($a==='close_reservation')reservationClose($db,(int)($_POST['reservation_id']??0),(string)($_POST['statut']??''),(int)utilisateurCourant()['id'],trim((string)($_POST['motif']??'')));
  elseif($a==='lot')stockLotCreate($db,$_POST,(int)utilisateurCourant()['id']);
  elseif($a==='receive_lot')stockLotReceive($db,(int)($_POST['lot_id']??0),(int)($_POST['emplacement_id']??0),(int)($_POST['projet_id']??0)?:null,(int)utilisateurCourant()['id']);
  elseif($a==='unite')$db->prepare("INSERT INTO stock_unites(article_id,numero_serie,code_barres,uid_rfid,statut,emplacement_id,projet_id,date_acquisition,notes) VALUES(?,?,?,?,?,?,?,?,?)")->execute([(int)$_POST['article_id'],trim($_POST['numero_serie'])?:null,trim($_POST['code_barres'])?:null,trim($_POST['uid_rfid'])?:null,$_POST['statut'],($_POST['emplacement_id']?:null),($_POST['projet_id']?:null),($_POST['date_acquisition']?:null),trim($_POST['notes'])]);
  elseif($a==='bom')$db->prepare("INSERT INTO stock_boms(article_parent_id,projet_id,reference,designation,version,statut,notes) VALUES(?,?,?,?,?,?,?)")->execute([($_POST['article_parent_id']?:null),($_POST['projet_id']?:null),trim($_POST['reference']),trim($_POST['designation']),trim($_POST['version']),$_POST['statut'],trim($_POST['notes'])]);
  elseif($a==='inventaire'){requerirAdmin();inventoryOpen($db,trim($_POST['code']),(int)($_POST['emplacement_id']??0)?:null,trim($_POST['notes']));}
  elseif($a==='env') {
   $articleId=(int)($_POST['article_id']??0);
   $exists=$db->prepare('SELECT 1 FROM stock_articles WHERE id=?');$exists->execute([$articleId]);
   if(!$exists->fetchColumn())throw new InvalidArgumentException('Article introuvable.');
   $mass=trim((string)($_POST['masse_g']??''));
   if($mass!=='' && (!is_numeric($mass) || !is_finite((float)$mass) || (float)$mass<0))throw new InvalidArgumentException('La masse doit être positive ou nulle.');
   $db->prepare("INSERT INTO stock_environnement(article_id,masse_g,matiere,substance_reglementee,dangereux,filiere_dechet,recyclage_possible,emballage,consignes) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(article_id) DO UPDATE SET masse_g=excluded.masse_g,matiere=excluded.matiere,substance_reglementee=excluded.substance_reglementee,dangereux=excluded.dangereux,filiere_dechet=excluded.filiere_dechet,recyclage_possible=excluded.recyclage_possible,emballage=excluded.emballage,consignes=excluded.consignes")->execute([$articleId,$mass===''?null:(float)$mass,trim($_POST['matiere']),trim($_POST['substance']),isset($_POST['dangereux'])?1:0,trim($_POST['filiere']),isset($_POST['recyclable'])?1:0,trim($_POST['emballage']),trim($_POST['consignes'])]);
  }
  setFlash('success','Enregistrement stock créé.');redirect('stock_pilotage.php?tab='.$tab);
 }catch(Throwable $e){setFlash('error','Erreur : '.$e->getMessage());}
}
require __DIR__.'/includes/header.php';
require __DIR__.'/views/stock/pilotage.php';
