<?php
// CI fixture only: refuse to touch any normal OddWorks database.
if (getenv('PROJECTFLOW_DB_PATH') !== '/tmp/oddworks-etudes-browser.sqlite') {
    throw new RuntimeException('Base de recette dédiée obligatoire');
}
require __DIR__.'/../../includes/cahier_specs.php';
runSchemaMigrations();
$db=getDB();
$db->exec("INSERT INTO projets(id,nom,createur_id) VALUES(1,'Carte de commande — recette',1); INSERT INTO cahiers(id,projet_id) VALUES(1,1)");
$s=emptySpecs();
$s['fonctions']=[['id'=>'S.F.1','description'=>'Piloter les équipements','indicateur'=>'Obligatoire'],['id'=>'S.F.2','description'=>'Assurer la protection du boîtier','indicateur'=>'']];
$s['specs_techniques']=parseSpecsTechniquesFromPost([
 'st_sf'=>['S.F.1','S.F.1','S.F.1','S.F.2','S.F.2'],
 'st_uid'=>[str_repeat('a',32),str_repeat('b',32),str_repeat('c',32),str_repeat('d',32),str_repeat('e',32)],
 'st_parent_uid'=>['','',str_repeat('b',32),'',''],
 'st_description'=>['Carte de commande principale','Module radio longue portée','Antenne externe','Boîtier étanche','Firmware de commande'],
 'st_type'=>['PCB','Composant','Composant','Matériel','Logiciel'],
 'st_quantite'=>[2,2,2,1,1], 'st_cout_unitaire'=>[85,45,'inconnue',120,0],
 'st_cout_taxe'=>['HT','HT','TTC','TTC','HT'], 'st_delai_jours'=>[15,10,'inconnue',20,7],
 'st_variation'=>['Faible','Moyen','','Fort','']
]);
saveSpecs(1,$s);
echo "Fixture étude initialisée sur la base temporaire\n";
