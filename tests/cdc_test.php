<?php
require __DIR__.'/../includes/cdc_test.php';
function cdcCheck(bool $ok,string $message):void {if(!$ok)throw new LogicException($message);}
$d=cdcTestDefaults();cdcCheck($d['nom']==='EcoFlask Connect'&&$d['quantite_validation']==='50','Exemple');
cdcCheck(count(cdcTestSections())===6,'Sections');
$d['cogs']='8,50';$clean=cdcTestValidate($d);cdcCheck($clean['cogs']==='8.50','Décimal');
foreach([['poids'=>'-1'],['nom'=>''],['date_redaction'=>'2026-02-30'],['volume_commande'=>'3.5'],['contexte'=>['invalide']]] as $bad){try{cdcTestValidate(array_replace($d,$bad));throw new LogicException('Validation manquante');}catch(InvalidArgumentException $e){}}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);cdcTestEnsure($db);
cdcTestSave($db,1,3,0,$clean);$saved=cdcTestLoad($db,1);cdcCheck($saved['revision']==1,'Création');cdcCheck(cdcTestLoad($db,2)===null,'Isolation');
$clean['nom']='Version revue';cdcTestSave($db,1,3,1,$clean);$saved=cdcTestLoad($db,1);cdcCheck($saved['revision']==2&&json_decode($saved['payload'],true)['nom']==='Version revue','Rechargement');
try{cdcTestSave($db,1,3,1,$d);throw new LogicException('Concurrence absente');}catch(RuntimeException $e){}
try{cdcTestSave($db,1,3,0,$d);throw new LogicException('Écrasement initial');}catch(RuntimeException $e){}
echo "CDC-Test-Form : exemple, validation, stockage isolé, rechargement et concurrence OK\n";
