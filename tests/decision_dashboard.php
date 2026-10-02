<?php
require_once __DIR__.'/../includes/decision_dashboard.php';
function expectDD(bool $ok,string $name): void { if(!$ok) throw new RuntimeException($name); }
$specs=['decision_cibles'=>['budget'=>2500,'quantite'=>2,'tva'=>20,'taxe'=>'HT','delai'=>56], 'fonctions'=>[['id'=>'S.F.1'],['id'=>'S.F.2']], 'specs_techniques'=>[
 ['id'=>'S.T.1.1','sf'=>'S.F.1','description'=>'Achat','type'=>'Matériel','cout_estime'=>270,'cout_taxe'=>'HT','delai_jours'=>7],
 ['id'=>'S.T.1.2','sf'=>'S.F.1','description'=>'Interne','type'=>'Logiciel','cout_estime'=>999,'delai_jours'=>8],
 ['id'=>'S.T.2.1','sf'=>'S.F.2','description'=>'Kit','type'=>'Composant','cout_estime'=>35.75,'cout_taxe'=>'TTC','delai_jours'=>10],
 ['id'=>'S.T.2.2','sf'=>'S.F.2','description'=>'Carte','type'=>'PCB','cout_estime'=>85,'delai_jours'=>15],
 ['id'=>'S.T.2.3','sf'=>'S.F.2','description'=>'CAO','type'=>'3D','cout_estime'=>1000,'delai_jours'=>6],
 ['id'=>'S.T.2.4','sf'=>'S.F.2','description'=>'Externe','type'=>'Prestataire','cout_estime'=>220,'delai_jours'=>20]
 ],'composants_st'=>['S.T.1.1'=>[['quantite'=>2,'cout_unitaire'=>270]]]];
$tasks=[['id'=>10,'source_key'=>'st:3:S.T.1.1','assigne_a'=>'alice'],['id'=>11,'source_key'=>'st:3:S.T.2.1','assigne_a'=>null,'dependance_id'=>10]];
$d=ddConsolidate($specs,$tasks,[['identifiant'=>'alice','competences'=>'Réseau']]);
expectDD($d['totals']['HT']===575.0 && $d['totals']['TTC']===35.75,'No mixed-tax sum or internal double count');
expectDD($d['lotHT']===1209.58 && $d['margin']===1290.42,'Quantity + declared VAT consolidation');
expectDD($d['maxDelay']===20.0,'Longest element, not sum');
expectDD(count($d['unassigned'])===1 && count($d['dependencies'])===1,'Assignments and dependencies');
expectDD($d['current']['ht']===540.0,'Current sourcing kept separate');
$specs['decision_cibles']['quantite']=3;
$d=ddConsolidate($specs,$tasks,[]); expectDD($d['lotHT']===1814.38,'Quantity change recalculated without stale total');
$specs['specs_techniques'][0]['cout_estime']=300;
$specs['specs_techniques'][0]['delai_jours']=60;
$d=ddConsolidate($specs,$tasks,[]); expectDD($d['maxDelay']===60.0 && count(array_filter($d['risks'],fn($r)=>$r['level']==='Critique'))===1,'Delay change vs actual target');
$specs['specs_techniques'][0]['type']='Logiciel';
$d=ddConsolidate($specs,$tasks,[]); expectDD($d['totals']['HT']===305.0,'Type change removes purchase cost');
array_pop($specs['specs_techniques']);
$d=ddConsolidate($specs,$tasks,[]); expectDD($d['totals']['HT']===85.0 && count($d['lines'])===5,'Deletion updates aggregation');
$specs['specs_techniques'][]=['id'=>'S.T.2.5','sf'=>'S.F.2','description'=>'Ajout','type'=>'PCB','cout_estime'=>90,'delai_jours'=>12];
$tasks[0]['assigne_a']='bob';
$d=ddConsolidate($specs,$tasks,[]); expectDD($d['totals']['HT']===175.0 && isset($d['assigned']['bob']) && !isset($d['assigned']['alice']),'Addition and reassignment');
unset($specs['decision_cibles']['tva']);$d=ddConsolidate($specs,$tasks,[]);expectDD($d['unitHT']===null && $d['margin']===null,'No invented VAT');
$empty=ddConsolidate([],[],[]);expectDD($empty['budget']===null && $empty['maxDelay']===null && count($empty['missing'])>=5,'Unknowns remain unknown');
expectDD(ddTargets([])['budget']===null,'Blank targets allowed');
foreach(['NaN','-1','<script>'] as $bad){try{ddTargets(['cible_budget'=>$bad]);throw new RuntimeException('Invalid numeric accepted');}catch(InvalidArgumentException $expected){}}
echo "Decision dashboard: OK\n";
