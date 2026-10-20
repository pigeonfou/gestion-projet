<?php
require_once __DIR__.'/../src/Services/AccountService.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT,role TEXT,mot_de_passe TEXT,nom_affiche TEXT,fonction TEXT,competences TEXT);INSERT INTO utilisateurs VALUES(1,'admin','admin','hash','','','');CREATE TABLE taches(id INTEGER PRIMARY KEY,assigne_a TEXT);INSERT INTO taches VALUES(1,'admin');CREATE TABLE cahiers(id INTEGER PRIMARY KEY,specs_json TEXT)");
$p=['identifiant'=>'renamed','mot_de_passe'=>'','role'=>'admin','nom_affiche'=>'','fonction'=>'','competences'=>''];
pfSaveAccount($db,1,$p);if($db->query('SELECT assigne_a FROM taches')->fetchColumn()!=='renamed')throw new RuntimeException('Assignment broken on rename');
$p['role']='utilisateur';try{pfSaveAccount($db,1,$p);throw new LogicException('Last admin removed');}catch(InvalidArgumentException $e){}
$p['identifiant']='new';$p['mot_de_passe']='short';try{pfSaveAccount($db,0,$p);throw new LogicException('Weak password accepted');}catch(InvalidArgumentException $e){}
echo "Recode accounts: shared validation, last admin and stable assignment on rename OK\n";
