<?php
require_once __DIR__ . '/../includes/cahier_specs.php';
function refsCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$a = str_repeat('a',32); $b = str_repeat('b',32); $c = str_repeat('c',32);
$post = ['st_sf'=>['S.F.1','S.F.2'], 'st_description'=>['Cible','Lien'],
    'st_type'=>['Matériel','S.T.x.x'], 'st_uid'=>[$a,$b], 'st_reference_uid'=>['',$a],
    'st_quantite'=>[2,99], 'st_cout_unitaire'=>[12.5,999], 'st_delai_jours'=>[3,999]];
$rows=parseSpecsTechniquesFromPost($post);
refsCheck($rows[1]['uid']===$b && $rows[1]['reference_uid']===$a,'Identité et relation conservées');
refsCheck($rows[1]['quantite']===null && $rows[1]['cout_estime']===0 && $rows[1]['delai_jours']===null,'Aucun double comptage');
validateStReferences($rows,$rows);
$renumbered=$rows; $renumbered[0]['id']='S.T.1.9';
validateStReferences($renumbered,$rows);
refsCheck($renumbered[1]['reference_uid']===$renumbered[0]['uid'],'Relation stable malgré renumérotation');
$missing=[$rows[1]]; validateStReferences($missing,$rows);
refsCheck($missing[0]['reference_uid']===$a,'Référence supprimée conservée pour signalement');
foreach (['self','cycle','foreign','duplicate'] as $case) {
    $bad=$rows;
    if($case==='self') $bad[1]['reference_uid']=$b;
    if($case==='cycle') {$bad[0]['type']='S.T.x.x';$bad[0]['reference_uid']=$b;}
    if($case==='foreign') $bad[1]['reference_uid']=$c;
    if($case==='duplicate') $bad[1]['uid']=$a;
    $rejected=false;
    try {validateStReferences($bad,$rows);} catch(InvalidArgumentException $e) {$rejected=true;}
    refsCheck($rejected,'Rejet serveur : '.$case);
}
$post['st_type'][1]='Matériel';$normal=parseSpecsTechniquesFromPost($post);
refsCheck($normal[1]['reference_uid']===null && $normal[1]['cout_estime']===98901.0,'Retour au type classique');
echo "Références S.T. : OK\n";
