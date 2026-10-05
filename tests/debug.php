<?php
require __DIR__.'/../includes/debug.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE parametres(cle TEXT PRIMARY KEY,valeur TEXT); INSERT INTO parametres VALUES('debug_enabled','0');
CREATE TABLE projets(id INTEGER PRIMARY KEY,current_step INTEGER,status TEXT,go_decision TEXT,validated_steps TEXT);
INSERT INTO projets VALUES(1,9,'termine','GO','[1,2,3,4,5,6,7,8]');
CREATE TABLE projet_decisions(projet_id INTEGER,etape INTEGER,decision TEXT,motif TEXT,utilisateur_id INTEGER)");
$admin=['id'=>1,'role'=>'admin'];
function checkDebug(bool $ok,string $msg):void{if(!$ok)throw new LogicException($msg);}
function rejectDebug(callable $call):void{$reject=false;try{$call();}catch(RuntimeException|InvalidArgumentException $e){$reject=true;}checkDebug($reject,'Forbidden or invalid debug operation accepted');}
rejectDebug(fn()=>debugChangeStep($db,$admin,1,3,'en_cours','test'));
$db->exec("UPDATE parametres SET valeur='1'");
rejectDebug(fn()=>debugChangeStep($db,['id'=>2,'role'=>'utilisateur'],1,3,'en_cours','test'));
foreach([[0,'validee','test'],[10,'validee','test'],[2,'abandon','test'],[3,'validee',''],[3,'invalid','test']] as [$step,$state,$reason])rejectDebug(fn()=>debugChangeStep($db,$admin,1,$step,$state,$reason));
debugChangeStep($db,$admin,1,3,'en_cours','Rejouer la validation');
$p=$db->query('SELECT * FROM projets')->fetch(PDO::FETCH_ASSOC);
checkDebug($p['current_step']===3 && $p['status']==='actif' && $p['go_decision']===null && $p['validated_steps']==='[1,2]','Reopen step 3 clears GO and downstream validations');
debugChangeStep($db,$admin,1,3,'abandon','Tester abandon');
checkDebug($db->query('SELECT status FROM projets')->fetchColumn()==='archive','NO GO archive');
debugChangeStep($db,$admin,1,3,'validee','Tester GO');
$p=$db->query('SELECT * FROM projets')->fetch(PDO::FETCH_ASSOC);
checkDebug($p['current_step']===4 && $p['go_decision']==='GO' && $p['validated_steps']==='[1,2,3]','GO validation');
debugChangeStep($db,$admin,1,8,'validee','Tester clôture');
checkDebug($db->query('SELECT status FROM projets')->fetchColumn()==='termine','Step 8 finishes project');
debugChangeStep($db,$admin,1,1,'en_cours','Retour au besoin');
checkDebug($db->query('SELECT validated_steps FROM projets')->fetchColumn()==='[]','Reset step 1');
checkDebug((int)$db->query('SELECT COUNT(*) FROM projet_decisions')->fetchColumn()===5 && str_contains($db->query('SELECT motif FROM projet_decisions LIMIT 1')->fetchColumn(),'Avant :'),'Audited successful operations only');
$db->exec("CREATE TRIGGER fail_log BEFORE INSERT ON projet_decisions BEGIN SELECT RAISE(ABORT,'test'); END");
try{debugChangeStep($db,$admin,1,7,'validee','test');}catch(PDOException $expected){}
checkDebug((int)$db->query('SELECT current_step FROM projets')->fetchColumn()===1,'Atomic rollback');
echo "Debug: disabled mode, admin restriction, states, downstream reset, audit and rollback OK\n";
