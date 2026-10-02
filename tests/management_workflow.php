<?php
require __DIR__.'/../includes/management_workflow.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function refused(callable $fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Refus attendu.');}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE documents(id INTEGER PRIMARY KEY,projet_id INTEGER);INSERT INTO documents VALUES(10,3),(20,4)');
$db->exec("CREATE TABLE non_conformites(id INTEGER PRIMARY KEY,projet_id INTEGER,reference TEXT,description TEXT,statut TEXT,cause TEXT,action_corrective TEXT,verification_efficacite TEXT,date_cloture TEXT);INSERT INTO non_conformites VALUES(1,3,'NC-PS01','LED','ouverte',NULL,NULL,NULL,NULL)");
managementSchema($db);
$data=['statut'=>'cloturee','date_metier'=>'2027-01-15','resultat'=>'LED conforme après30cycles','cause'=>'connecteur','action_corrective'=>'verrouillage','document_externe_id'=>10];
refused(fn()=>managementUpdate($db,'nc',1,array_replace($data,['document_externe_id'=>20]),3));
refused(fn()=>managementUpdate($db,'nc',1,array_replace($data,['document_externe_id'=>null]),3));
refused(fn()=>managementUpdate($db,'nc',1,array_replace($data,['cause'=>'']),3));
refused(fn()=>managementUpdate($db,'nc',1,array_replace($data,['resultat'=>'']),3));
refused(fn()=>managementUpdate($db,'nc',1,array_replace($data,['date_metier'=>'2027-02-30']),3));
refused(fn()=>managementUpdate($db,'nc',1,array_replace($data,['statut'=>'supprimee']),3));
check($db->query('SELECT COUNT(*) FROM management_historique')->fetchColumn()==0,'aucun historique sur refus');
check(managementRead($db,'nc',1)['statut']==='ouverte','refus atomiques');
managementUpdate($db,'nc',1,$data,3);
$r=managementRead($db,'nc',1);check($r['statut']==='cloturee'&&$r['date_cloture']==='2027-01-15','clôture datée');
$h=$db->query('SELECT * FROM management_historique')->fetch(PDO::FETCH_ASSOC);check($h['utilisateur_id']==3&&$h['document_externe_id']==10,'acteur etpreuve');
check(json_decode($h['avant'],true)['statut']==='ouverte'&&json_decode($h['apres'],true)['statut']==='cloturee','historiqueavantaprès');
$db->exec("CREATE TABLE risques_opportunites(id INTEGER PRIMARY KEY,projet_id INTEGER,description TEXT,statut TEXT,efficacite TEXT,updated_at TEXT);INSERT INTO risques_opportunites VALUES(1,3,'ISOinconnu','ouvert',NULL,NULL)");
managementUpdate($db,'risque',1,['statut'=>'maitrise','date_metier'=>'2027-01-15','resultat'=>'contrôle100%, revuepériodique conservée','document_externe_id'=>10],3);
check(managementRead($db,'risque',1)['statut']==='maitrise','risquemaîtrisé');
refused(fn()=>managementRead($db,'nc;DELETE FROM documents',1));
echo "Suivi management, preuves projet, clôture et historique : OK\n";
