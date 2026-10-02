<?php
require __DIR__.'/../includes/project_members.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function refused(callable $fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Refus attendu');}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE projets(id INTEGER PRIMARY KEY,createur_id INTEGER);CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY);INSERT INTO projets VALUES(3,1),(4,1);INSERT INTO utilisateurs VALUES(1),(3),(10),(11)');
$owner=['id'=>1,'role'=>'utilisateur'];$admin=['id'=>3,'role'=>'admin'];$member=['id'=>10,'role'=>'utilisateur'];$other=['id'=>11,'role'=>'utilisateur'];
check(!projectCanContribute($db,3,$member),'aucune autorisation implicite');
refused(fn()=>projectSetContributor($db,3,10,true,$member));
check($db->query('SELECT COUNT(*) FROM projet_contributeurs')->fetchColumn()==0,'refus sans mutation');
projectSetContributor($db,3,10,true,$owner);
check(projectCanContribute($db,3,$member),'contribution autorisée');
check(!projectCanManage($db,3,$member),'pas de pilotage R1b');
check(!projectCanContribute($db,4,$member),'aucun accès à un autre projet');
check(!projectCanContribute($db,3,$other),'aucun accès autre utilisateur');
refused(fn()=>projectSetContributor($db,3,11,true,$member));
projectSetContributor($db,3,10,false,$admin);
check(!projectCanContribute($db,3,$member),'retrait effectif');
check($db->query('SELECT COUNT(*) FROM projet_contributeurs_historique')->fetchColumn()==2,'historique grant et retrait');
refused(fn()=>projectSetContributor($db,99,10,true,$admin));
refused(fn()=>projectSetContributor($db,3,99,true,$owner));
check(projectCanManage($db,3,$owner)&&projectCanManage($db,3,$admin),'droits gestion conservés');
echo "Contributeurs explicites, isolation projet, pilotage réservé et retrait : OK\n";
