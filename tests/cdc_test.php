<?php
require __DIR__.'/../includes/cdc_test.php';
function cdcCheck(bool $ok,string $message):void {if(!$ok)throw new LogicException($message);}
$d=cdcTestDefaults();cdcCheck(count(array_filter($d,static fn($v)=>$v!==''))===0,'Tous les champs sont vides');
cdcCheck(cdcTestValidate([])===$d,'Brouillon vide accepté');
cdcCheck(count(cdcTestSections())===5,'Sections');
$d['budget']='Environ 10 000 € HT, à confirmer';$clean=cdcTestValidate($d);cdcCheck($clean['budget']===$d['budget'],'Budget libre');
foreach([['date_redaction'=>'2026-02-30'],['besoin'=>['invalide']]] as $bad){try{cdcTestValidate(array_replace($d,$bad));throw new LogicException('Validation manquante');}catch(InvalidArgumentException $e){}}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);cdcTestEnsure($db);
$db->exec("CREATE TABLE cdc_test_forms(projet_id INTEGER,payload TEXT);INSERT INTO cdc_test_forms VALUES(1,'Ancien exemple conservé')");
cdcTestSave($db,1,3,0,$clean);$saved=cdcTestLoad($db,1);cdcCheck($saved['revision']==1,'Création');cdcCheck(cdcTestLoad($db,2)===null,'Isolation');
$clean['nom']='Version revue';cdcTestSave($db,1,3,1,$clean);$saved=cdcTestLoad($db,1);cdcCheck($saved['revision']==2&&json_decode($saved['payload'],true)['nom']==='Version revue','Rechargement');
try{cdcTestSave($db,1,3,1,$d);throw new LogicException('Concurrence absente');}catch(RuntimeException $e){}
try{cdcTestSave($db,1,3,0,$d);throw new LogicException('Écrasement initial');}catch(RuntimeException $e){}
cdcCheck($db->query('SELECT payload FROM cdc_test_forms WHERE projet_id=1')->fetchColumn()==='Ancien exemple conservé','Ancien formulaire préservé');
echo "CDC-Test-Form : champs vides, brouillon partiel, validation, stockage isolé, rechargement et concurrence OK\n";
