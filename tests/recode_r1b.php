<?php
$file=tempnam(sys_get_temp_dir(),'pf-r1b-');putenv('PROJECTFLOW_DB_PATH='.$file);register_shutdown_function(static function () use ($file){@unlink($file);});
require_once __DIR__.'/../includes/cahier_specs.php';require_once __DIR__.'/../src/Services/R1bDecisionService.php';
$db=getDB();$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT);INSERT INTO utilisateurs VALUES(1,'admin');CREATE TABLE projets(id INTEGER PRIMARY KEY,nom TEXT,createur_id INTEGER);INSERT INTO projets VALUES(1,'Decision',1);CREATE TABLE cahiers(id INTEGER PRIMARY KEY,projet_id INTEGER,specs_json TEXT);CREATE TABLE taches(id INTEGER PRIMARY KEY,projet_id INTEGER,titre TEXT,description TEXT,priorite TEXT,statut TEXT)");runSchemaMigrations();initProjectNotes($db,1);
$actor=['id'=>1,'role'=>'admin'];
function r1bCurrent(PDO $db):array{return $db->query('SELECT * FROM projets WHERE id=1')->fetch(PDO::FETCH_ASSOC);}
$revision=pfProjectRevision(r1bCurrent($db));
if(pfApplyR1bDecision($db,1,1,'DONE','',$actor,$revision)!==2)throw new RuntimeException('Step1 not advanced');
try{pfApplyR1bDecision($db,1,1,'DONE','',$actor,$revision);throw new LogicException('Stale decision accepted');}catch(InvalidArgumentException $e){}
pfApplyR1bDecision($db,1,2,'DONE','',$actor,pfProjectRevision(r1bCurrent($db)));
if(pfApplyR1bDecision($db,1,3,'GO','Approved',$actor,pfProjectRevision(r1bCurrent($db)))!==4)throw new RuntimeException('GO failed');
$snapshot=$db->query('SELECT snapshot_json FROM projet_decisions WHERE etape=3')->fetchColumn();
if(!$snapshot||json_decode($snapshot,true,512,JSON_THROW_ON_ERROR)['version']!==1)throw new RuntimeException('Decision snapshot missing');
if(pfApplyR1bDecision($db,1,4,'DONE','',$actor,pfProjectRevision(r1bCurrent($db)))!==5)throw new RuntimeException('Purchase step skipped');
pfApplyR1bDecision($db,1,5,'REFUSE','Correction',$actor,pfProjectRevision(r1bCurrent($db)));
if($db->query('SELECT snapshot_json FROM projet_decisions WHERE etape=3')->fetchColumn()!==$snapshot)throw new RuntimeException('Historical snapshot mutated');
if(count($db->query('SELECT * FROM projet_decisions')->fetchAll())!==5)throw new RuntimeException('Non-atomic history');
echo "Recode R1b: complete transitions, refusal, snapshots and stale decision rejection OK\n";
