<?php
$unknownDb = tempnam(sys_get_temp_dir(), 'oddworks-unknown-');
putenv('PROJECTFLOW_DB_PATH=' . $unknownDb);
register_shutdown_function(static function () use ($unknownDb) { @unlink($unknownDb); });
require_once __DIR__ . '/../includes/cahier_specs.php';
require_once __DIR__ . '/../includes/decision_dashboard.php';
function unknownCheck(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
$db=getDB();
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT);
CREATE TABLE projets(id INTEGER PRIMARY KEY,nom TEXT,createur_id INTEGER);
CREATE TABLE cahiers(id INTEGER PRIMARY KEY,projet_id INTEGER,specs_json TEXT);
CREATE TABLE taches(id INTEGER PRIMARY KEY,projet_id INTEGER,titre TEXT,description TEXT,priorite TEXT,statut TEXT);
INSERT INTO utilisateurs VALUES(1,'recette');INSERT INTO projets VALUES(1,'Recette',1);INSERT INTO cahiers VALUES(1,1,'{}');");
$a=str_repeat('a',32);$b=str_repeat('b',32);$c=str_repeat('c',32);$r=str_repeat('d',32);
$post=['st_uid'=>[$a,$b,$c,$r],'st_sf'=>array_fill(0,4,'S.F.1'),'st_description'=>['Coût inconnu','Achat connu','Logiciel','Référence'],
    'st_type'=>['Matériel','PCB','Logiciel','S.T.x.x'],'st_reference_uid'=>['','','',$a],'st_parent_uid'=>['',$a,$b,$a],
    'st_quantite'=>[2,3,999,1],'st_cout_unitaire'=>['-0,01','10','-20','-1'],
    'st_delai_jours'=>['-1','5','-2','-1'],'st_cout_taxe'=>['TTC','HT','HT','HT'],'st_variation'=>array_fill(0,4,'Faible')];
$rows=parseSpecsTechniquesFromPost($post);
unknownCheck($rows[0]['cout_unitaire']===null && $rows[0]['cout_estime']===null && $rows[0]['cout_unitaire_inconnu'],'Negative price is unknown, never zero');
unknownCheck($rows[0]['delai_jours']===null && $rows[0]['delai_inconnu'],'Negative duration is unknown');
unknownCheck(!$rows[2]['cout_unitaire_inconnu'] && $rows[2]['cout_estime']===0 && $rows[2]['delai_inconnu'],'Internal type: duration only');
unknownCheck(!$rows[3]['cout_unitaire_inconnu'] && !$rows[3]['delai_inconnu'],'References do not acquire independent estimates');
$base=['fonctions'=>[['id'=>'S.F.1','description'=>'Recette']], 'decision_cibles'=>['budget'=>1000,'quantite'=>2,'taxe'=>'HT','tva'=>20,'delai'=>20], 'composants_st'=>['S.T.1.1'=>[['id'=>'M.1','quantite'=>2,'cout_unitaire'=>40,'cout_total'=>80]]]];
saveSpecs(1,$base); // Reference target must exist before creating a technical reference.
saveSpecsTechniques(1,array_slice($rows,0,3));saveSpecsTechniques(1,$rows);
$specs=loadSpecs(1);$saved=$specs['specs_techniques'];
unknownCheck($saved[0]['cout_unitaire']===null && $saved[0]['cout_unitaire_inconnu'] && $saved[0]['delai_inconnu'],'Unknown flags and NULL persist');
unknownCheck($specs['composants_st']===$base['composants_st'],'Refined component estimates unchanged');
unknownCheck($saved[1]['parent_uid']===$a && $saved[3]['reference_uid']===$a,'Hierarchy and references retained');
$d=ddConsolidate($specs);
unknownCheck($d['totals']['HT']===30.0 && $d['totals']['TTC']===0.0 && $d['costIncompleteCount']===1,'Known subtotal without duplicate reference cost');
unknownCheck($d['unitHT']===null && $d['lotHT']===null && $d['margin']===null,'No invented complete cost or favorable budget margin');
unknownCheck($d['maxDelay']===5.0 && $d['delayIncompleteCount']===2,'Maximum only among known durations, reference excluded');
unknownCheck(ddDelaySummary($d)==='Inconnu' && ddCostSummary($d)===ddMoney(30) && strpos(ddCostPrecision($d),'1 coût(s) inconnu(s)')!==false,'Explicit report labels');
unknownCheck(!$d['capacityComplete'] && !$d['references'][0]['confirmed'],'Unknown fields and source reference cannot confirm capacity');
$frozen=ddSnapshot($specs,['nom'=>'Recette']);$history=json_decode($frozen,true);
unknownCheck($history['analysis']['margin']===null && $history['analysis']['costIncompleteCount']===1 && $history['sources']['specs_techniques'][0]['cout_unitaire_inconnu'],'Decision report freezes unknown state');
$post['st_cout_unitaire'][0]='inconnue';$post['st_delai_jours'][0]='inconnue';
$normalized=parseSpecsTechniquesFromPost($post);
unknownCheck($normalized[0]['cout_unitaire_inconnu'] && $normalized[0]['delai_inconnu'],'Displayed unknown can be resubmitted');
$post['st_cout_unitaire'][0]='0';$post['st_delai_jours'][0]='0';$post['st_delai_jours'][2]='4,5';
$known=parseSpecsTechniquesFromPost($post);saveSpecsTechniques(1,$known);
$d=ddConsolidate(loadSpecs(1));
unknownCheck(!$known[0]['cout_unitaire_inconnu'] && !$known[0]['delai_inconnu'] && $known[0]['cout_estime']===0.0,'Zero is known; recovery from unknown clears flags');
unknownCheck($d['costIncompleteCount']===0 && $d['delayIncompleteCount']===0 && $d['lotHT']===60.0 && $d['margin']===940.0,'Known values restore normal calculations');
unknownCheck(json_decode($frozen,true)['analysis']['margin']===null,'Historical report unchanged after correction');
echo "Estimations inconnues : OK (valeurs négatives, persistance, sous-totaux, délais, budget, références et rapports)\n";
