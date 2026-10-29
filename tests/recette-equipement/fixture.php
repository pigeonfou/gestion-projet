<?php
// Guard the complete fixture, including all read helpers, against real application databases.
if (getenv('PROJECTFLOW_DB_PATH') !== '/tmp/oddworks-atlas12.sqlite') throw new RuntimeException('Base jetable obligatoire. Aucune exécution DEV/PROD directe.');
require_once __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../includes/stock/catalogue.php';
require_once __DIR__.'/../../includes/stock/production_workflow.php';
require_once __DIR__.'/../../includes/cdc_validation.php';
require_once __DIR__.'/../../includes/cahier_specs.php';
runSchemaMigrations();$db=getDB();inventorySchema($db);productionSchema($db);cdcValidationEnsureSchema($db);
$mode=$argv[1]??'state';
if($mode==='init'){
 foreach(['nextcloud_enabled'=>'1','nextcloud_webdav'=>'http://127.0.0.1:8092','nextcloud_user'=>'fixture','nextcloud_password'=>'fixture-only','nextcloud_root'=>'RECETTE-ATLAS12'] as $k=>$v)setSetting($k,$v);
 echo "Configuration WebDAV locale isolée prête\n";exit;
}
$p=invOne($db,"SELECT * FROM projets WHERE nom='[RECETTE DEV FICTIVE] ATLAS-12 - Banc technique modulaire'");
if(!$p)throw new RuntimeException('Projet de recette absent');$pid=(int)$p['id'];
if($mode==='boms'){
 $scenario=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
 foreach($scenario['assemblies'] as $a){
  $article=invOne($db,'SELECT id FROM stock_articles WHERE reference_fabricant=?',['AT12-'.$a['code']]);
  if(!invOne($db,'SELECT id FROM stock_boms WHERE reference=?',['REC-ATLAS12-BOM-'.$a['code']])){
   $db->prepare("INSERT INTO stock_boms(reference,version,projet_id,article_parent_id,statut) VALUES(?,'A',?,?,'brouillon')")->execute(['REC-ATLAS12-BOM-'.$a['code'],$pid,$article['id']]);
  }
 }
 echo "Quatre en-têtes de BOM initialisés par fixture : l’interface native de création est redirigée vers l’inventaire. Les compositions/OF passent par les contrôleurs HTTP.\n";exit;
}
$c=invOne($db,'SELECT id FROM cahiers WHERE projet_id=?',[$pid]);
$data=['project'=>$p,'specs'=>$c?loadSpecs((int)$c['id']):null,'validations'=>invRows($db,'SELECT * FROM cdc_validations WHERE projet_id=?',[$pid]),'documents'=>invRows($db,'SELECT * FROM documents WHERE projet_id=?',[$pid]),'decisions'=>invRows($db,'SELECT * FROM projet_decisions WHERE projet_id=? ORDER BY id',[$pid]),'tasks'=>invRows($db,'SELECT * FROM taches WHERE projet_id=? ORDER BY id',[$pid]),'boms'=>invRows($db,'SELECT * FROM stock_boms WHERE projet_id=?',[$pid]),'bom_items'=>invRows($db,'SELECT i.* FROM stock_bom_items i JOIN stock_boms b ON b.id=i.bom_id WHERE b.projet_id=?',[$pid]),'orders'=>invRows($db,'SELECT * FROM stock_production WHERE projet_id=?',[$pid]),'units'=>invRows($db,'SELECT * FROM stock_unites WHERE projet_id=?',[$pid]),'genealogy'=>invRows($db,'SELECT c.* FROM stock_production_composants c JOIN stock_production p ON p.id=c.production_id WHERE p.projet_id=?',[$pid]),'reservations'=>invRows($db,'SELECT * FROM stock_reservations WHERE projet_id=?',[$pid]),'stock'=>invState($db)];
echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
