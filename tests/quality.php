<?php
require_once __DIR__.'/../includes/quality.php';
$denied=0;
foreach([fn()=>qualityCoverage('partiel',100),fn()=>qualityCoverage('conforme',80),fn()=>qualityCoverage('non_conforme',101),fn()=>qualityCoverage('conforme',''),fn()=>qualityCertificate(['iso9001'=>'oui'],'9001')] as $f)try{$f();}catch(InvalidArgumentException $e){$denied++;}
if($denied!==5 || qualityCoverage('inconnu',100)!==null || qualityCoverage('partiel',80)!==80.0)throw new RuntimeException('Validation qualité incorrecte');
$rows=[['iso9001'=>'oui','validite9001'=>'2099-01-01','rohs'=>'conforme','reach'=>'partiel','fournisseur_id'=>1,'document_id'=>1],['iso9001'=>'oui','validite9001'=>'2000-01-01','rohs'=>'non_conforme','reach'=>'inconnu']];
$r=qualityIndicators($rows);
if($r['references']!==2 || $r['iso9001']!==1 || $r['rohs']!==1 || $r['reach']!==0 || $r['inconnues']!==1 || $r['non_conformes']!==1 || $r['alertes']!==2)throw new RuntimeException('Dénominateur, certificats expirés ou alertes incorrects');
$db=new PDO('sqlite::memory:');$db->exec('PRAGMA foreign_keys=ON; CREATE TABLE projets(id INTEGER PRIMARY KEY); CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY); CREATE TABLE stock_fournisseurs(id INTEGER PRIMARY KEY); CREATE TABLE documents(id INTEGER PRIMARY KEY); CREATE TABLE taches(id INTEGER PRIMARY KEY); INSERT INTO projets VALUES(1);');
qualitySchema($db);qualitySchema($db);
$db->exec("INSERT INTO qualite_references(projet_id,reference) VALUES(1,'R1')");
try{$db->exec("INSERT INTO qualite_references(projet_id,reference) VALUES(1,'R1')");throw new RuntimeException('Doublon accepté');}catch(PDOException $e){}
echo "OK : couvertures, certificats, inconnues, alertes et unicité qualité\n";
