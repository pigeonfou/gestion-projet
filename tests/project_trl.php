<?php
require __DIR__.'/../includes/project_trl.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA foreign_keys=ON');
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT);CREATE TABLE projets(id INTEGER PRIMARY KEY,createur_id INTEGER,current_step INTEGER,status TEXT);INSERT INTO utilisateurs VALUES(1,'pilot'),(2,'reader'),(3,'admin');INSERT INTO projets VALUES(1,1,3,'actif'),(2,2,6,'actif')");
$owner=['id'=>1,'role'=>'utilisateur'];$admin=['id'=>3,'role'=>'admin'];
$db->exec("CREATE TABLE documents(id INTEGER PRIMARY KEY,projet_id INTEGER,phase TEXT,nom_fichier TEXT,taille INTEGER,date_upload TEXT)");
function trlCheck(bool $ok,string $msg):void {if(!$ok)throw new LogicException($msg);}
function trlReject(callable $fn):void {$rejected=false;try{$fn();}catch(InvalidArgumentException $e){$rejected=true;}trlCheck($rejected,'Invalid or forbidden evaluation accepted');}
trlCheck(array_keys(trlLevels())===[2,3,4,5,6,7] && trlLoad($db,1)['actuel']===null,'AID/DGA subset and no fabricated maturity');
foreach(['0','1','8','9',1,8,9,'10','3.5','03',[],true] as $bad)trlReject(fn()=>trlLevel($bad));
trlCheck(trlLoad($db,1)['entree']===2 && trlLoad($db,1)['actuel']===null,'Default entry is concept, current remains unevaluated');
trlCheck(trlGroups()['concept']['levels']===[2] && trlGroups()['ri']['levels']===[3,4,5],'Concept separate from RI');
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
$db->exec("INSERT INTO projet_trl(projet_id,entree,actuel,cible,preuves,revision) VALUES(2,2,8,9,'Évaluation ancienne',1)");
$legacy=trlLoad($db,2);
trlCheck($legacy['entree']===2 && $legacy['actuel']===null && $legacy['cible']===null,'Excluded levels not usable in UI');
trlCheck($legacy['_excluded_levels']===['actuel'=>8,'cible'=>9],'Original levels retained for reassessment audit');
trlCheck((int)$db->query('SELECT actuel FROM projet_trl WHERE projet_id=2')->fetchColumn()===8,'Reading does not rewrite project data');
trlCheck(trlDisplayLabel(8)==='À réévaluer' && trlDisplayLabel(4)==='TRL 4','History labels respect usable range');
$db->exec('DROP TRIGGER reject_trl_history');
trlReject(fn()=>trlSave($db,2,$admin,['revision'=>1,'actuel'=>8,'preuves'=>'excluded']));
trlSave($db,2,$admin,['revision'=>1,'entree'=>3,'actuel'=>5,'cible'=>7,'preuves'=>'Réévaluation du périmètre']);
$audit=json_decode($db->query('SELECT avant_json FROM projet_trl_historique WHERE projet_id=2')->fetchColumn(),true);
trlCheck($audit['_excluded_levels']['actuel']===8 && trlLoad($db,2)['actuel']===5,'Explicit reassessment preserves previous audit');

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url($path): string { return '/oddworks/' . $path; }
function csrfField(): string { return '<input type="hidden" name="csrf" value="test">'; }
$id=2; $user=$admin; $view='trl';
ob_start(); require __DIR__.'/../includes/views/project_trl.php'; $html=ob_get_clean();
preg_match_all('/<option value="([0-9]+)"/', $html, $options);
trlCheck($options[1]===['2','3','4','5','6','7','2','3','4','5','6','7','2','3','4','5','6','7'],'Only usable levels in every selector');
trlCheck(str_contains($html,'Recherche industrielle') && str_contains($html,'Développement expérimental'),'Two AID/DGA groups rendered');
trlCheck(str_contains($html,'name="revision" value="2"') && str_contains($html,'name="csrf"'),'Revision and CSRF controls retained');
$user=['id'=>1,'role'=>'utilisateur'];
ob_start(); require __DIR__.'/../includes/views/project_trl.php'; $readonly=ob_get_clean();
trlCheck(!str_contains($readonly,'<form') && str_contains($readonly,'Réévaluation du périmètre'),'Reader sees evaluation without edit controls');
$view='dashboard';
ob_start(); require __DIR__.'/../includes/views/project_trl.php'; $compact=ob_get_clean();
trlCheck(str_contains($compact,'trl-overview') && !str_contains($compact,'trl-domains'),'Other project views retain compact summary');

$db->exec("INSERT INTO documents VALUES(1,1,'09_TRL','essai.bin',42,CURRENT_TIMESTAMP),(2,2,'01_Besoin','hors-tr l.pdf',100,CURRENT_TIMESTAMP)");
trlCheck(trlEvidence($db,2)===[], 'Proofs isolated by project and folder');
trlSave($db,1,$owner,['revision'=>2,'entree'=>2,'actuel'=>3,'cible'=>5,'preuves'=>'']);
$snapshot=json_decode($db->query('SELECT apres_json FROM projet_trl_historique WHERE projet_id=1 ORDER BY id DESC LIMIT 1')->fetchColumn(),true);
trlCheck($snapshot['documents_justificatifs'][0]['id']===1,'File-only proof accepted and included in audit');
trlCheck(str_contains($html,'type="file"') && !str_contains($html,'accept=') && str_contains($html,'usage=trl'),'All file types upload available to managers');
trlCheck(!str_contains($readonly,'type="file"'),'Read-only user cannot upload proof');
trlCheck(!str_contains($html,'Deux natures de projet, cinq niveaux de maturité'),'Obsolete subtitle removed');
echo "TRL: AID/DGA levels 2–7, unknown initial state, permissions, evidence, persistence, isolation, revision and audit OK\n";
