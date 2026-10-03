<?php
require_once __DIR__ . '/../includes/cahier_specs.php';
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$types = ['Matériel', 'Composant', 'Prestataire', 'Logiciel', '3D', 'PCB'];
$post = ['st_sf' => array_fill(0, 6, 'S.F.1'), 'st_description' => $types,
    'st_type' => $types, 'st_cout_estime' => array_fill(0, 6, '12,50'),
    'st_cout_taxe' => array_fill(0, 6, 'TTC'), 'st_delai_jours' => ['2', '3,5', '0', '4', '5', '6']];
$rows = parseSpecsTechniquesFromPost($post);
check(count($rows) === 6, 'Toutes les S.T. doivent être conservées');
foreach ($rows as $i => $row) {
    check($row['cout_estime'] === (in_array($i, [3, 4], true) ? 0 : 12.5), 'Coût selon le type ' . $types[$i]);
    check($row['delai_jours'] === [2.0, 3.5, 0.0, 4.0, 5.0, 6.0][$i], 'Délai ' . $types[$i]);
}
$post = ['cp_st_id' => array_map(fn($i) => 'S.T.1.' . ($i + 1), range(0, 5)),
    'cp_type' => $types, 'cp_designation' => $types,
    'cp_quantite' => array_fill(0, 6, '2'), 'cp_cout_unitaire' => array_fill(0, 6, '12,50'),
    'cp_cout_unitaire_taxe' => array_fill(0, 6, 'TTC'),
    'cp_affectation' => array_fill(0, 6, 'admin'),
    'cp_duree' => array_fill(0, 6, 'ancienne durée'),
    'cp_delai_jours' => ['2', '3,5', '0', '4', '5', '6']];
$items = parseComposantsStFromPost($post);
foreach ($types as $i => $type) {
    $item = $items['S.T.1.' . ($i + 1)][0];
    check($item['delai_jours'] === [2.0, 3.5, 0.0, 4.0, 5.0, 6.0][$i], 'Index des délais après mélange des types');
    check(isset($item['cout_total']) === !in_array($i, [3, 4], true), 'Coûts exclus pour les réalisations internes');
    if (isset($item['cout_total'])) check($item['cout_total'] === 25.0 && $item['cout_total_taxe'] === 'TTC', 'Calcul du coût et taxe');
    check($item['affectation'] === 'admin' && $item['duree'] === 'ancienne durée', 'Conservation des données existantes');
}
check($items['S.T.1.6'][0]['id'] === 'PCB.1', 'Identifiant PCB stable');
check(parseDelaiJours('') === null && parseDelaiJours('-2') === null && parseDelaiJours('abc') === null, 'Délai vide ou invalide');
check(parseComposantsStFromPost(['cp_st_id' => ['S.T.1.1'], 'cp_type' => ['PCB']]) === [], 'Ligne vide ignorée');
check(count(parseComposantsStFromPost(['cp_st_id' => ['S.T.1.1'], 'cp_type' => ['Composant'], 'cp_delai_jours' => ['0']])) === 1, 'Délai nul conservé');
echo "OK : six types, coûts HT/TTC, délais, index mixtes et données anciennes\n";

// Initial estimates use quantity × unit, independently of refined supplier data.
foreach (['Faible', 'Moyen', 'Fort'] as $variation) {
    $initial = parseSpecsTechniquesFromPost([
        'st_sf'=>array_fill(0,6,'S.F.2'), 'st_description'=>$types, 'st_type'=>$types,
        'st_quantite'=>array_fill(0,6,'2,5'), 'st_cout_unitaire'=>array_fill(0,6,'12,34'),
        'st_cout_estime'=>array_fill(0,6,'99999'), 'st_cout_taxe'=>array_fill(0,6,'TTC'),
        'st_variation'=>array_fill(0,6,$variation), 'st_delai_jours'=>array_fill(0,6,'3,75')]);
    foreach ($initial as $i=>$row) {
        check($row['variation']===$variation && $row['delai_jours']===3.75, 'Variation/délai des six types');
        check($row['cout_estime']===(stTypeHasCost($types[$i])?30.85:0), 'Total serveur indépendant du total envoyé');
        check($row['id']==='S.T.2.'.($i+1), 'Association S.T.');
    }
    $refined = parseComposantsStFromPost(['cp_st_id'=>['S.T.2.1'], 'cp_type'=>['Matériel'],
        'cp_quantite'=>['3'], 'cp_cout_unitaire'=>['10,125'], 'cp_cout_unitaire_taxe'=>['HT'],
        'cp_variation'=>[$variation], 'cp_delai_jours'=>['7,5']]);
    check($refined['S.T.2.1'][0]['cout_total']===30.38 && $refined['S.T.2.1'][0]['variation']===$variation, 'Arrondi et variation affinée');
    check($initial[0]['cout_estime']===30.85 && $initial[0]['delai_jours']===3.75, 'Estimation initiale non écrasée');
    $db=new PDO('sqlite::memory:');$db->exec('CREATE TABLE cahiers(specs_json TEXT)');
    $db->prepare('INSERT INTO cahiers VALUES (?)')->execute([json_encode(['specs_techniques'=>$initial,'composants_st'=>$refined])]);
    $reloaded=json_decode($db->query('SELECT specs_json FROM cahiers')->fetchColumn(),true);
    check($reloaded['specs_techniques'][0]['variation']===$variation && $reloaded['composants_st']['S.T.2.1'][0]['variation']===$variation, 'Persistance SQLite JSON distincte');
}
check(normalizeEstimationVariation('Forte')==='Fort' && normalizeEstimationVariation('Moyenne')==='Moyen', 'Compatibilité anciennes variations');
check(estimationDecimal('-1')===0.0 && estimationDecimal('INF')===0.0, 'Quantités/coûts invalides');
echo "OK : quantité décimale, totaux recalculés, trois variations, compatibilité, stockage SQLite distinct
";

check(normalizeEstimationVariation('') === '', 'Risque absent reste inconnu');
