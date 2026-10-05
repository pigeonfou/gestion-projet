<?php
// Always use a disposable database, including when this suite runs on the host.
$hierarchyDb = tempnam(sys_get_temp_dir(), 'oddworks-st-');
putenv('PROJECTFLOW_DB_PATH=' . $hierarchyDb);
register_shutdown_function(static function () use ($hierarchyDb) { @unlink($hierarchyDb); });
require_once __DIR__ . '/../includes/cahier_specs.php';
function hierarchyCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function hierarchyRefused(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException $e) {
        hierarchyCheck(strpos($e->getMessage(), $message) !== false, 'Message de refus explicite');
        return;
    }
    throw new RuntimeException('Refus attendu : ' . $message);
}
$db = getDB();
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY, identifiant TEXT);
CREATE TABLE projets(id INTEGER PRIMARY KEY, nom TEXT, createur_id INTEGER);
CREATE TABLE cahiers(id INTEGER PRIMARY KEY, projet_id INTEGER, specs_json TEXT);
CREATE TABLE taches(id INTEGER PRIMARY KEY, projet_id INTEGER, titre TEXT, description TEXT, priorite TEXT, statut TEXT);
INSERT INTO utilisateurs VALUES(1,'recette'); INSERT INTO projets VALUES(1,'Test',1),(2,'Autre',1);
INSERT INTO cahiers VALUES(1,1,'{}'),(2,2,'{}');");
$a = str_repeat('a', 32); $b = str_repeat('b', 32); $c = str_repeat('c', 32); $d = str_repeat('d', 32); $foreign = str_repeat('f', 32);
$post = ['st_sf'=>['S.F.1','S.F.1','S.F.2','S.F.1'],
    'st_description'=>['Carte','Alimentation','Firmware','Communication'],
    'st_type'=>['Matériel','PCB','Logiciel','Composant'], 'st_uid'=>[$a,$b,$c,$d],
    'st_parent_uid'=>['',$a,$b,$a], 'st_quantite'=>[2,3,4,5], 'st_cout_unitaire'=>[10,20,30,40],
    'st_cout_taxe'=>['HT','TTC','HT','HT'], 'st_variation'=>['Faible','Fort','Moyen',''], 'st_delai_jours'=>[1,2,3,4]];
$rows = parseSpecsTechniquesFromPost($post);
$by = array_column($rows, null, 'uid');
hierarchyCheck($by[$c]['parent_uid'] === $b && $by[$c]['sf'] === 'S.F.2', 'Relation transversale aux S.F.');
$flat = $rows; foreach ($flat as &$st) $st['parent_uid'] = null; unset($st);
$graph = validateStHierarchy($rows);
foreach ($graph as $i=>$st) { unset($st['parent_uid']); $baseline=$flat[$i]; unset($baseline['parent_uid']); hierarchyCheck($st === $baseline, 'Coûts, quantités, HT/TTC, risques et délais inchangés'); }
saveSpecsTechniques(1, $rows);
$loaded = loadSpecs(1)['specs_techniques'];
hierarchyCheck($loaded == $rows, 'Sauvegarde et rechargement exacts');
$reopened = new PDO('sqlite:' . $hierarchyDb);
hierarchyCheck(json_decode($reopened->query('SELECT specs_json FROM cahiers WHERE id=1')->fetchColumn(), true)['specs_techniques'] == $rows, 'Persisté sur une nouvelle connexion');
syncTasksFromStructuredSpecs(1, [], $flat);
$tasks = $db->query('SELECT id,source_key FROM taches ORDER BY id')->fetchAll();
syncTasksFromStructuredSpecs(1, [], $rows);
hierarchyCheck($db->query('SELECT id,source_key FROM taches ORDER BY id')->fetchAll() === $tasks, 'Aucune tâche supplémentaire ou dupliquée');
foreach (['self','direct','indirect','foreign'] as $case) {
    $bad = $by;
    if ($case === 'self') $bad[$a]['parent_uid'] = $a;
    if ($case === 'direct') $bad[$a]['parent_uid'] = $b;
    if ($case === 'indirect') $bad[$a]['parent_uid'] = $c;
    if ($case === 'foreign') $bad[$a]['parent_uid'] = $foreign;
    hierarchyRefused(fn()=>saveSpecsTechniques(1, array_values($bad)), $case === 'foreign' ? 'projet courant' : 'boucle');
    hierarchyCheck(loadSpecs(1)['specs_techniques'] == $rows, 'Refus atomique : ' . $case);
}
$foreignRows = [['uid'=>$foreign,'parent_uid'=>null,'id'=>'S.T.1.1','sf'=>'S.F.1','description'=>'Autre projet','type'=>'Matériel']];
saveSpecsTechniques(2, $foreignRows);
$bad=$rows; $bad[0]['parent_uid']=$foreign;
hierarchyRefused(fn()=>saveSpecsTechniques(1,$bad), 'projet courant');
// Rename and renumber display references without changing the structural IDs.
$changed=$rows; $changed[0]['id']='S.T.1.8'; $changed[0]['description']='Carte renommée';
saveSpecsTechniques(1,$changed);
hierarchyCheck(array_column(loadSpecs(1)['specs_techniques'],null,'uid')[$b]['parent_uid']===$a,'Relation stable après renommage/renumérotation');
$changed[1]['parent_uid']=null; saveSpecsTechniques(1,$changed);
hierarchyCheck(loadSpecs(1)['specs_techniques'][1]['parent_uid']===null,'Détachement');
saveSpecsTechniques(1,$rows);
$remaining=array_values(array_filter($rows,fn($st)=>$st['uid']!==$a));
hierarchyRefused(fn()=>saveSpecsTechniques(1,$remaining),'Confirmez');
saveSpecsTechniques(1,$remaining,[$a]);
$survivors=array_column(loadSpecs(1)['specs_techniques'],null,'uid');
hierarchyCheck(count($survivors)===3 && $survivors[$b]['parent_uid']===null && $survivors[$d]['parent_uid']===null && $survivors[$c]['parent_uid']===$b,'Suppression sans cascade : enfants racines et petits-enfants conservés');
// Legacy migration is persisted and idempotent, without touching other JSON data.
$legacy=['objectifs'=>'Ancien','composants_st'=>['S.T.1.1'=>[['id'=>'PCB.1']]],'specs_techniques'=>[['id'=>'S.T.1.1','sf'=>'S.F.1','description'=>'Ancienne','type'=>'Matériel']]];
$db->prepare('UPDATE cahiers SET specs_json=? WHERE id=1')->execute([json_encode($legacy)]);
$migrated=loadSpecs(1);
$stored=json_decode($db->query('SELECT specs_json FROM cahiers WHERE id=1')->fetchColumn(),true);
hierarchyCheck(isset($stored['specs_techniques'][0]['uid']) && array_key_exists('parent_uid',$stored['specs_techniques'][0]) && $stored['specs_techniques'][0]['parent_uid']===null,'Migration persistée avec parent NULL');
hierarchyCheck($stored['objectifs']===$legacy['objectifs'] && $stored['composants_st']===$legacy['composants_st'],'Données étapes 1 et 4 conservées');
hierarchyCheck(migrateStHierarchy($stored,1)===$stored,'Migration idempotente');
// All seven types, including an independent reference relation.
foreach (['Matériel','Composant','Prestataire','Logiciel','3D','PCB','S.T.x.x'] as $type) {
    $candidate=$by; $candidate[$d]['type']=$type;
    $candidate[$d]['reference_uid']=$type==='S.T.x.x' ? $c : null;
    validateStReferences(array_values($candidate),array_values($candidate));
    $result=array_column(validateStHierarchy(array_values($candidate)),null,'uid');
    hierarchyCheck($result[$d]['parent_uid']===$a && $result[$d]['reference_uid']===$candidate[$d]['reference_uid'],'Parent et référence indépendants pour '.$type);
}
$deep=[];for($i=1;$i<=1000;$i++)$deep[]=['uid'=>sprintf('%032x',$i),'parent_uid'=>$i>1?sprintf('%032x',$i-1):null];
hierarchyCheck(count(validateStHierarchy($deep))===1000,'Aucune limite arbitraire de profondeur');
echo "Hiérarchie S.T. : OK (migration, persistance, cycles, périmètre, suppression sans cascade, types, références, coûts et tâches)\n";
