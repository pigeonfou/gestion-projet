<?php
require_once __DIR__.'/../includes/task_workflow.php';
$db=new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE projets(id INTEGER,createur_id INTEGER); INSERT INTO projets VALUES(1,1),(2,1);
CREATE TABLE taches(id INTEGER PRIMARY KEY,projet_id INTEGER,assigne_a TEXT,statut TEXT,kanban_status TEXT,dependance_id INTEGER);
INSERT INTO taches VALUES(1,1,"alice","a_faire","a_faire",NULL),(2,1,"bob","a_faire","a_faire",1),(3,2,"bob","a_faire","a_faire",NULL);
CREATE TABLE tache_historique(id INTEGER PRIMARY KEY,tache_id INTEGER,utilisateur_id INTEGER,action TEXT,details TEXT);');
$alice=['id'=>2,'identifiant'=>'alice','role'=>'utilisateur'];
$bob=['id'=>3,'identifiant'=>'bob','role'=>'utilisateur'];
$denied=0;
foreach ([fn()=>taskSetStatus($db,1,'terminee',$bob),fn()=>taskSetStatus($db,2,'en_cours',$bob),fn()=>taskDependencyCheck($db,1,1,3,'a_faire'),fn()=>taskDependencyCheck($db,1,1,2,'a_faire'),fn()=>taskSetStatus($db,1,'terminee',$alice,2)] as $f) {
    try { $f(); } catch (InvalidArgumentException $e) { $denied++; }
}
if($denied!==5 || $db->query('SELECT COUNT(*) FROM tache_historique')->fetchColumn()!=0) throw new RuntimeException('Refus ou atomicité incorrects');
taskSetStatus($db,1,'terminee',$alice,1); taskSetStatus($db,2,'en_cours',$bob,1);
if($db->query('SELECT statut FROM taches WHERE id=2')->fetchColumn()!=='en_cours' || $db->query('SELECT COUNT(*) FROM tache_historique')->fetchColumn()!=2) throw new RuntimeException('Transition ou journal incorrects');
echo "OK : droits tâches, dépendances, cycles, périmètre projet et journal atomique\n";
