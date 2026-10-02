<?php
require __DIR__.'/../includes/decision_history.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY, identifiant TEXT); CREATE TABLE projet_decisions(id INTEGER PRIMARY KEY,projet_id INTEGER,etape INTEGER,decision TEXT,utilisateur_id INTEGER,date_decision TEXT,motif TEXT)');
$db->exec("INSERT INTO utilisateurs VALUES(1,'AgentGPT'),(2,'Gaëlle')");
$insert=$db->prepare('INSERT INTO projet_decisions VALUES(?,?,?,?,?,?,?)');
for($i=1;$i<=60;$i++)$insert->execute([$i,1,$i%2?7:6,$i%2?'NON_CONFORME':'DONE',$i%2?1:2,'2026-10-02 12:00:00',$i===1?'NC-P02 débit 100% seuil_800':'Résultat '.$i]);
$insert->execute([61,2,7,'CONFORME',1,'2026-10-02 12:00:00','Projet confidentiel étranger']);
$insert->execute([62,1,7,'CONFORME',1,'2026-10-03 00:00:00','Retest']);
function expect($condition,$message){if(!$condition)throw new RuntimeException($message);}
$r=loadDecisionHistory($db,1,[]);expect($r['count']===61 && count($r['rows'])===25 && $r['pages']===3,'Scope et pagination');
$r=loadDecisionHistory($db,1,['h_step'=>7,'h_decision'=>'NON_CONFORME','h_actor'=>1,'h_q'=>'NC-P02','h_from'=>'2026-10-02','h_to'=>'2026-10-02']);expect($r['count']===1 && $r['rows'][0]['id']==1,'Filtres combinés et recherche');
foreach(['100%','seuil_800'] as $q)expect(loadDecisionHistory($db,1,['h_q'=>$q])['count']===1,'Recherche littérale des caractères LIKE');
expect(loadDecisionHistory($db,1,['h_to'=>'2026-10-02'])['count']===60,'Borne haute inclusive sans lendemain');
expect(loadDecisionHistory($db,1,['h_q'=>"' OR 1=1 --"])['count']===0,'Recherche paramétrée');
$r=loadDecisionHistory($db,1,['h_sort'=>'date; DROP TABLE projet_decisions','h_order'=>'injection','h_page'=>999]);expect($r['filters']['h_sort']==='date'&&$r['filters']['h_page']===3,'Tri autorisé et page bornée');
$r=loadDecisionHistory($db,1,['h_sort'=>'step','h_order'=>'asc','h_size'=>100]);expect($r['rows'][0]['etape']==6 && end($r['rows'])['etape']==7,'Tri numérique');
$r=loadDecisionHistory($db,1,['h_q'=>'aucune correspondance']);expect($r['count']===0&&$r['pages']===1,'Résultat vide');
$r=decisionHistoryFilters(['h_step'=>[], 'h_q'=>[], 'h_from'=>'2026-02-31']);expect($r['h_step']===0&&$r['h_q']===''&&$r['h_from']==='','Entrées invalides');
$db->exec('DELETE FROM utilisateurs WHERE id=2');expect(loadDecisionHistory($db,1,[])['count']===61,'Historique conservé si acteur absent');
echo "OK : historique isolé par projet, filtres, recherche littérale, dates, tris sûrs et pagination\n";
