<?php
$path=tempnam(sys_get_temp_dir(),'pf-integrity-');putenv('PROJECTFLOW_DB_PATH='.$path);
register_shutdown_function(static function () use ($path) { @unlink($path); });
require_once __DIR__.'/../includes/cahier_specs.php';
function integrityCheck(bool $ok,string $message): void {if (!$ok) throw new RuntimeException($message);}
$db=getDB();
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT);INSERT INTO utilisateurs VALUES(1,'test');CREATE TABLE projets(id INTEGER PRIMARY KEY,nom TEXT,createur_id INTEGER);INSERT INTO projets VALUES(1,'Test',1);CREATE TABLE cahiers(id INTEGER PRIMARY KEY,projet_id INTEGER,specs_json TEXT);INSERT INTO cahiers VALUES(1,1,'{}');CREATE TABLE taches(id INTEGER PRIMARY KEY,projet_id INTEGER,titre TEXT,description TEXT,priorite TEXT,statut TEXT);");
$a=str_repeat('a',32);$b=str_repeat('b',32);
$rows=[['uid'=>$a,'parent_uid'=>null,'sf'=>'S.F.1','id'=>'S.T.1.1','description'=>'A','type'=>'Matériel'],['uid'=>$b,'parent_uid'=>null,'sf'=>'S.F.1','id'=>'S.T.1.2','description'=>'B','type'=>'PCB']];
saveSpecsTechniques(1,$rows);syncTasksFromStructuredSpecs(1,[],$rows);
$task=(int)$db->query("SELECT id FROM taches WHERE source_key='st:1:S.T.1.2'")->fetchColumn();
$db->prepare("UPDATE taches SET statut='terminee',kanban_status='terminee' WHERE id=?")->execute([$task]);
$db->prepare("INSERT INTO tache_historique(tache_id,utilisateur_id,action,details) VALUES(?,1,'test','Historique B')")->execute([$task]);
saveComposantsSt(1,['S.T.1.2'=>[['id'=>'PCB.1','uid'=>str_repeat('c',32),'reference'=>'B','quantite'=>2]]]);
$remaining=[$rows[1]];$remaining[0]['id']='S.T.1.1';
saveSpecsTechniques(1,$remaining);syncTasksFromStructuredSpecs(1,[],$remaining);
$saved=$db->query("SELECT * FROM taches WHERE source_uid='st:$b'")->fetch(PDO::FETCH_ASSOC);
integrityCheck((int)$saved['id']===$task && $saved['statut']==='terminee' && $saved['source_key']==='st:1:S.T.1.1','Task identity/status follow survivor');
integrityCheck((int)$db->query("SELECT COUNT(*) FROM tache_historique WHERE tache_id=$task")->fetchColumn()===1,'History preserved');
integrityCheck(loadSpecs(1)['composants_st']['S.T.1.1'][0]['reference']==='B','Components follow immutable ST');
$componentUid=str_repeat('c',32);
$db->prepare("INSERT INTO taches(projet_id,titre,source_key,source_uid,statut) VALUES(1,'Purchase','achat:1:S.T.1.1:PCB.1',?,'terminee')")->execute(['achat:'.$b.':'.$componentUid]);
$purchaseId=(int)$db->lastInsertId();
saveComposantsSt(1,['S.T.1.1'=>[['id'=>'PCB.1','uid'=>str_repeat('d',32),'reference'=>'New'],['id'=>'PCB.2','uid'=>$componentUid,'reference'=>'B']]]);
$purchase=$db->query("SELECT * FROM taches WHERE id=$purchaseId")->fetch(PDO::FETCH_ASSOC);
integrityCheck($purchase['source_key']==='achat:1:S.T.1.1:PCB.2' && $purchase['statut']==='terminee','Purchase follows component reorder');
saveComposantsSt(1,['S.T.1.1'=>[['id'=>'PCB.1','uid'=>str_repeat('d',32),'reference'=>'New']]]);
integrityCheck($db->query("SELECT source_key FROM taches WHERE id=$purchaseId")->fetchColumn()==='removed:'.$purchaseId,'Deleted component task retained without claiming a different item');
$revision=specsRevision(1);pfAssertSpecsRevision(1,$revision);
$db->exec("UPDATE cahiers SET specs_json='{}' WHERE id=1");
try {pfAssertSpecsRevision(1,$revision);throw new LogicException('Stale edit accepted');} catch (InvalidArgumentException $e) {}
$before=$db->query('SELECT COUNT(*) FROM jalons')->fetchColumn();
try {pfTransaction($db,function () use ($db) {saveJalonsFromPost(1,['jalon_nom'=>['A']]);throw new RuntimeException('Injected failure');});}catch(RuntimeException $e){}
integrityCheck($db->query('SELECT COUNT(*) FROM jalons')->fetchColumn()===$before,'Nested operation rolled back');
$db->exec("UPDATE cahiers SET specs_json='broken' WHERE id=1");
try {loadSpecs(1);throw new LogicException('Invalid JSON treated as empty');}catch(JsonException $e){}
echo "Recode integrity: stable identity, history, components, concurrency, rollback and corrupt JSON OK\n";
