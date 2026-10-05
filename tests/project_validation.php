<?php
require __DIR__.'/../includes/project_validation.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE projets(id INTEGER PRIMARY KEY,current_step INTEGER,status TEXT,go_decision TEXT,validated_steps TEXT);
CREATE TABLE projet_decisions(projet_id INTEGER,etape INTEGER,decision TEXT,motif TEXT,utilisateur_id INTEGER,snapshot_json TEXT);
INSERT INTO projets VALUES(1,3,'actif',NULL,'[1,2]'),(2,3,'actif',NULL,'[]'),(3,2,'actif',NULL,'[1]')");
function checkValidation(bool $ok, string $message): void { if (!$ok) throw new LogicException($message); }
recordProjectValidation($db,1,7,'GO','Lancement motivé','{"cost":300}');
$row=$db->query('SELECT * FROM projets WHERE id=1')->fetch(PDO::FETCH_ASSOC);
checkValidation($row['current_step']===4 && $row['go_decision']==='GO' && $row['validated_steps']==='[1,2,3]','GO advances and validates step 3');
checkValidation($db->query('SELECT motif FROM projet_decisions WHERE projet_id=1')->fetchColumn()==='Lancement motivé','Motivation recorded');
foreach([[1,'NO_GO','Ancien lien'],[2,'NO_GO',' '],[3,'GO',''],[2,'INVALID',''],[2,'GO',str_repeat('x',10001)]] as [$id,$decision,$motif]) {
    $rejected=false;
    try { recordProjectValidation($db,$id,7,$decision,$motif,'{}'); } catch (RuntimeException|InvalidArgumentException $error) { $rejected=true; }
    checkValidation($rejected,'Reject stale links, premature decisions and invalid input');
}
recordProjectValidation($db,2,7,'NO_GO','Budget insuffisant','{"cost":300}');
$row=$db->query('SELECT * FROM projets WHERE id=2')->fetch(PDO::FETCH_ASSOC);
checkValidation($row['current_step']===3 && $row['status']==='archive' && $row['go_decision']==='NO_GO','Abandon archives at step 3');
checkValidation((int)$db->query('SELECT COUNT(*) FROM projet_decisions')->fetchColumn()===2,'One history entry per accepted response');
checkValidation($db->query('SELECT snapshot_json FROM projet_decisions WHERE projet_id=2')->fetchColumn()==='{"cost":300}','Snapshot retained');
$db->exec("INSERT INTO projets VALUES(4,3,'actif',NULL,'[]'); CREATE TRIGGER fail_history BEFORE INSERT ON projet_decisions BEGIN SELECT RAISE(ABORT,'test'); END");
try { recordProjectValidation($db,4,7,'GO','','{}'); } catch (PDOException $expected) {}
checkValidation(projectAwaitingValidation($db->query('SELECT * FROM projets WHERE id=4')->fetch(PDO::FETCH_ASSOC)),'Rollback if history fails');
echo "Project validation: launch, archive, motivations, stale links and rollback OK\n";
