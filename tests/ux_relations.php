<?php
require_once __DIR__.'/../includes/ux_objects.php';require_once __DIR__.'/../includes/document_relations.php';
function uxCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach(['st:3:S.T.2.1','cp:3:S.T.2.1:PCB.1','achat:3:S.T.2.1:PCB.1'] as $key)uxCheck(pfTaskStId(['projet_id'=>3,'source_key'=>$key])==='S.T.2.1','Structured source relation');
foreach(['st:4:S.T.2.1','manual:3:S.T.2.1','st:3:S.T.2.1X',''] as $key)uxCheck(pfTaskStId(['projet_id'=>3,'source_key'=>$key,'titre'=>'S.T.2.1'])===null,'No inferred or foreign relation');
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('CREATE TABLE documents(id INTEGER PRIMARY KEY,projet_id INTEGER);CREATE TABLE projets(id INTEGER PRIMARY KEY);CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY);INSERT INTO documents VALUES(1,3),(2,4);INSERT INTO projets VALUES(3),(4);INSERT INTO utilisateurs VALUES(1)');pfDocumentLinksSchema($db);$uid=str_repeat('a',32);$specs=['specs_techniques'=>[['uid'=>$uid,'id'=>'S.T.1.1']]];
pfDocumentLink($db,3,1,$uid,$specs,1);pfDocumentLink($db,3,1,$uid,$specs,1);uxCheck((int)$db->query('SELECT COUNT(*) FROM document_st_links')->fetchColumn()===1,'Idempotent association');
foreach([[3,2,$uid,$specs],[3,1,str_repeat('b',32),$specs]] as [$pid,$did,$baduid,$ss]){$rejected=false;try{pfDocumentLink($db,$pid,$did,$baduid,$ss,1);}catch(InvalidArgumentException $e){$rejected=true;}uxCheck($rejected,'Cross-project and missing target rejected');}
pfDocumentLinksSchema($db);uxCheck((int)$db->query('SELECT COUNT(*) FROM document_st_links')->fetchColumn()===1,'Repeat migration preserves links');echo "UX relations : OK\n";
