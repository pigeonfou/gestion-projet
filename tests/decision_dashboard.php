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
expectDD(!isset($d['assigned']) && !isset($d['dependencies']), 'No future task assignments or planning');
expectDD(!isset($d['current']), 'No future sourcing');
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
$d=ddConsolidate($specs,$tasks,[]); expectDD($d['totals']['HT']===175.0 && !isset($d['assigned']),'Addition without future reassignment');
unset($specs['decision_cibles']['tva']);$d=ddConsolidate($specs,$tasks,[]);expectDD($d['unitHT']===null && $d['margin']===null,'No invented VAT');
$empty=ddConsolidate([],[],[]);expectDD($empty['budget']===null && $empty['maxDelay']===null && count($empty['missing'])>=5,'Unknowns remain unknown');
expectDD(ddTargets([])['budget']===null,'Blank targets allowed');
foreach(['NaN','-1','<script>'] as $bad){try{ddTargets(['cible_budget'=>$bad]);throw new RuntimeException('Invalid numeric accepted');}catch(InvalidArgumentException $expected){}}
echo "Decision dashboard: OK\n";
require_once __DIR__.'/../includes/decision_gantt.php';
$g=ddGantt([
 ['id'=>1,'date_debut'=>'2026-12-30','date_echeance'=>'2027-01-02'],
 ['id'=>2,'date_echeance'=>'2027-01-04'],
 ['id'=>3,'date_debut'=>'2027-01-05','date_echeance'=>'2027-01-03'],
 ['id'=>4,'date_echeance'=>'2026-02-30'],
 ['id'=>5,'date_debut'=>'2027-01-01'],
]);
expectDD($g['days']===6 && count($g['rows'])===2 && count($g['undated'])===3,'Gantt dates across years, invalid and missing periods');
expectDD(!$g['rows'][0]['milestone'] && abs($g['rows'][0]['width']-100*4/6)<0.001,'Inclusive planned duration');
expectDD($g['rows'][1]['milestone'] && $g['rows'][1]['start']->format('Y-m-d')==='2027-01-04','Deadline is a milestone, no fabricated start');
expectDD(ddGantt([])['days']===0 && ddGanttDate('2026-02-30')===null,'Empty and impossible calendar dates');
echo "Gantt: OK\n";

expectDD(ddGanttTaskLabel(['id'=>28,'source_key'=>'st:3:S.T.2.1','titre'=>'Autre S.T.3.2'])==='#28 · S.T.2.1','Gantt source S.T. takes precedence');
expectDD(ddGanttTaskLabel(['id'=>42,'titre'=>'[ACHAT S.T.2.2/PCB.1] Carte'])==='#42 · S.T.2.2','Gantt legacy purchase S.T.');
expectDD(ddGanttTaskLabel(['id'=>66,'titre'=>'Libération'])==='#66','No invented S.T. for manual tasks');

// Temporal independence: compare the whole result and stored sources, not only one KPI.
$baseline=ddConsolidate($specs,$tasks,[]);
$frozen=ddSnapshot($specs,['nom'=>'ARV-8','step_notes'=>'future note','current_step'=>9]);
$specs['composants_st']=['S.T.1.1'=>[['cout_unitaire'=>999999,'delai_jours'=>999,'fournisseur'=>'Future','variation'=>'Fort']]];
$tasks=[['id'=>10,'assigne_a'=>'Future','date_echeance'=>'2099-01-01','cout_estime'=>999999]];
expectDD($baseline===ddConsolidate($specs,$tasks,[['identifiant'=>'Future']]),'Downstream changes cannot alter any indicator');
expectDD($frozen===ddSnapshot($specs,['nom'=>'ARV-8','step_notes'=>'other future note','current_step'=>4]),'Future data excluded from snapshots');
$specs['specs_techniques'][0]['variation']='Fort';
$updated=ddConsolidate($specs);
expectDD($updated!==$baseline && $updated['riskCounts']['Fort']===1,'Upstream risk changes update the synthesis');
$specs['specs_techniques'][0]['uid']='target';
$specs['specs_techniques'][]=['id'=>'S.T.1.9','sf'=>'S.F.1','uid'=>'ref','type'=>'S.T.x.x','reference_uid'=>'target','cout_estime'=>999999,'delai_jours'=>999,'variation'=>'Fort'];
$referenced=ddConsolidate($specs);
expectDD($referenced['totals']===$updated['totals'] && $referenced['maxDelay']===$updated['maxDelay'] && $referenced['riskCounts']===$updated['riskCounts'] && $referenced['groups']===$updated['groups'],'References do not duplicate cost, delay, risk or capacity');
$decoded=json_decode($frozen,true);
expectDD(!isset($decoded['sources']['composants_st']) && !isset($decoded['project']['step_notes']), 'Historical source boundary');
expectDD(ddConsolidate($decoded['sources'])===$baseline,'Historical estimates remain unchanged after upstream corrections');
$specs['specs_techniques'][0]['variation']='';
expectDD(ddConsolidate($specs)['riskCounts']['Non renseigné']>0,'Missing risk is unknown, never favorable');
echo "Temporal isolation and historical snapshots: OK\n";

expectDD($decoded['analysis'] === $baseline, 'Computed decision indicators are frozen alongside sources');
