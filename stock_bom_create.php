<?php
$pageTitle='Nouvelle nomenclature';$activePage='stock_pilotage';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/stock/catalogue.php';
require_once __DIR__.'/includes/stock/production_workflow.php';
requerirConnexion();requerirAdmin();$db=getDB();inventorySchema($db);productionSchema($db);
$pid=(int)($_POST['projet_id']??$_GET['projet_id']??0);$article=(int)($_POST['article_id']??0);
$error='';$reference=trim((string)($_POST['reference']??''));$version=trim((string)($_POST['version']??'A'));
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrfRequire();
 try{
  $parent=invArticle($db,$article);$project=invOne($db,'SELECT id FROM projets WHERE id=?',[$pid]);
  if(!$project||!projectCanManage($db,$pid,utilisateurCourant()))throw new InvalidArgumentException('Projet accessible requis.');
  $flags=invFlags($parent);
  if(!$flags['assembled']||$flags['virtual']||$flags['template'])throw new InvalidArgumentException('Sélectionnez un composant physique marqué assemblé.');
  if($reference===''||strlen($reference)>200||$version===''||strlen($version)>40)throw new InvalidArgumentException('Référence et indice requis (200 et 40 caractères maximum).');
  $db->exec('BEGIN IMMEDIATE');
  try{
   if(invOne($db,'SELECT id FROM stock_boms WHERE reference=? AND version=?',[$reference,$version]))throw new InvalidArgumentException('Cette référence et cet indice existent déjà.');
   $db->prepare("INSERT INTO stock_boms(article_parent_id,projet_id,reference,designation,version,statut) VALUES(?,?,?,?,?,'brouillon')")->execute([$article,$pid,$reference,$parent['designation'],$version]);
   $bid=(int)$db->lastInsertId();$db->exec('COMMIT');
  }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
  setFlash('success','Nomenclature créée. Renseignez sa composition puis validez-la avant de planifier un assemblage.');
  redirect('stock_production.php?bom_id='.$bid);
 }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Création impossible. Consultez le journal serveur.';if(!($e instanceof InvalidArgumentException))error_log('OddWorks BOM create: '.get_class($e));}
}
$projects=invRows($db,'SELECT id,nom FROM projets ORDER BY nom');
$articles=array_values(array_filter(invRows($db,'SELECT * FROM stock_articles ORDER BY reference'),static function($a){$f=invFlags($a);return $f['assembled']&&!$f['virtual']&&!$f['template'];}));
require __DIR__.'/includes/header.php';?>
<div class="page-header"><div><h1>Nouvelle nomenclature</h1><p class="text-muted">Définissez le projet et l’ensemble assemblé. Sa composition sera saisie à l’étape suivante.</p></div><a class="btn btn-secondary" href="<?=url($pid?'production.php?projet_id='.$pid:'inventaire.php')?>">Retour</a></div>
<?php if($error!==''):?><div class="alert alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<div class="card card-body"><form method="post"><?=csrfField()?>
<div class="form-row"><label>Projet<select class="form-control" name="projet_id" required><option value="">Choisir</option><?php foreach($projects as $p):?><option value="<?=(int)$p['id']?>" <?=$pid===(int)$p['id']?'selected':''?>><?=e($p['nom'])?></option><?php endforeach;?></select></label>
<label>Ensemble assemblé<select class="form-control" name="article_id" required><option value="">Choisir</option><?php foreach($articles as $a):?><option value="<?=(int)$a['id']?>" <?=$article===(int)$a['id']?'selected':''?>><?=e($a['reference'].' - '.$a['designation'])?></option><?php endforeach;?></select></label></div>
<div class="form-row"><label>Référence de nomenclature<input class="form-control" name="reference" maxlength="200" value="<?=e($reference)?>" required></label><label>Indice<input class="form-control" name="version" maxlength="40" value="<?=e($version)?>" required></label></div>
<p class="text-muted">La création conserve un brouillon. La validation de la composition et la planification d’un ordre restent des actions distinctes.</p>
<button class="btn btn-primary" type="submit">Créer la nomenclature</button></form></div>
<?php require __DIR__.'/includes/footer.php';?>
