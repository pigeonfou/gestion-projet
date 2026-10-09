<?php
require __DIR__.'/../includes/project_trl.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA foreign_keys=ON');
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT);CREATE TABLE projets(id INTEGER PRIMARY KEY,createur_id INTEGER,current_step INTEGER,status TEXT);INSERT INTO utilisateurs VALUES(1,'pilot'),(2,'reader'),(3,'admin');INSERT INTO projets VALUES(1,1,3,'actif'),(2,2,6,'actif')");
$owner=['id'=>1,'role'=>'utilisateur'];$admin=['id'=>3,'role'=>'admin'];
function trlCheck(bool $ok,string $msg):void {if(!$ok)throw new LogicException($msg);}
function trlReject(callable $fn):void {$rejected=false;try{$fn();}catch(InvalidArgumentException $e){$rejected=true;}trlCheck($rejected,'Invalid or forbidden evaluation accepted');}
trlCheck(count(trlLevels())===9 && trlLoad($db,1)['actuel']===null,'Complete scale and no fabricated maturity');
foreach(['0','10','3.5','03',[],true] as $bad)trlReject(fn()=>trlLevel($bad));
trlReject(fn()=>trlSave($db,1,['id'=>2,'role'=>'utilisateur'],['revision'=>0]));
trlReject(fn()=>trlSave($db,1,$owner,['revision'=>0,'actuel'=>6,'preuves'=>'']));
trlSave($db,1,$owner,['revision'=>'0','entree'=>'3','actuel'=>'4','cible'=>'7','preuves'=>'Prototype essayé en laboratoire']);
$first=trlLoad($db,1);trlCheck($first['actuel']===4 && $first['revision']===1 && $first['cible']===7,'Stored levels and revision');
trlCheck(trlLoad($db,2)['actuel']===null,'Project isolation');
trlCheck((int)$db->query('SELECT current_step FROM projets WHERE id=1')->fetchColumn()===3,'TRL does not validate process step');
trlReject(fn()=>trlSave($db,1,$owner,['revision'=>0,'actuel'=>5,'preuves'=>'stale']));
trlSave($db,1,$admin,['revision'=>1,'entree'=>3,'actuel'=>3,'cible'=>6,'preuves'=>'Réévaluation après essai']);
trlCheck(trlLoad($db,1)['actuel']===3 && (int)$db->query('SELECT COUNT(*) FROM projet_trl_historique')->fetchColumn()===2,'Downward reassessment and audit');
$prior=trlLoad($db,1);$db->exec("CREATE TRIGGER reject_trl_history BEFORE INSERT ON projet_trl_historique BEGIN SELECT RAISE(ABORT,'test'); END");
try{trlSave($db,1,$owner,['revision'=>2,'actuel'=>5,'preuves'=>'test']);}catch(PDOException $expected){}
trlCheck(trlLoad($db,1)===$prior,'Atomic rollback');
echo "TRL: complete scale, unknown initial state, permissions, evidence, persistence, isolation, revision and audit OK\n";
