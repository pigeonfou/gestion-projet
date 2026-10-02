<?php
require_once __DIR__.'/../includes/stock/inventory_workflow.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE stock_articles(id INTEGER PRIMARY KEY,quantite_stock REAL,updated_at TEXT);INSERT INTO stock_articles VALUES(1,5,NULL),(2,2,NULL);
CREATE TABLE stock_emplacements(id INTEGER PRIMARY KEY,actif INTEGER);INSERT INTO stock_emplacements VALUES(1,1);
CREATE TABLE stock_mouvements(id INTEGER PRIMARY KEY,article_id INTEGER,type TEXT,quantite REAL,emplacement_source_id INTEGER,emplacement_destination_id INTEGER,reference_externe TEXT,note TEXT,user_id INTEGER);INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_destination_id) VALUES(1,'reception',5,1),(2,'recuperation',2,1);
CREATE TABLE stock_unites(id INTEGER PRIMARY KEY,article_id INTEGER);INSERT INTO stock_unites VALUES(1,2),(2,2);
CREATE TABLE stock_inventaires(id INTEGER PRIMARY KEY,code TEXT UNIQUE,emplacement_id INTEGER,statut TEXT,notes TEXT,valide_par INTEGER,date_validation TEXT);
CREATE TABLE stock_inventaire_lignes(id INTEGER PRIMARY KEY,inventaire_id INTEGER,article_id INTEGER,quantite_theorique REAL,quantite_comptee REAL,ecart REAL,emplacement_id INTEGER,note TEXT);");
function rejectInventory(callable $f):void{try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Inventaire invalide accepté');}
$id=inventoryOpen($db,'I1',1,'Fictif');$rows=$db->query("SELECT id,article_id FROM stock_inventaire_lignes WHERE inventaire_id=$id")->fetchAll(PDO::FETCH_KEY_PAIR);$counts=[];foreach($rows as $line=>$article)$counts[$line]=$article==1?4:1;
rejectInventory(fn()=>inventoryValidate($db,$id,1));inventoryCount($db,$id,$counts,[]);rejectInventory(fn()=>inventoryValidate($db,$id,1));
if($db->query('SELECT COUNT(*) FROM stock_mouvements')->fetchColumn()!=2)throw new RuntimeException('Ajustement partiel malgré écart série');
foreach($rows as $line=>$article)if($article==2)$counts[$line]=2;
inventoryCount($db,$id,$counts,[]);inventoryValidate($db,$id,1);inventoryValidate($db,$id,1);
if(productionQuantity($db,1,1)!==4.0||$db->query('SELECT quantite_stock FROM stock_articles WHERE id=1')->fetchColumn()!=4||$db->query('SELECT COUNT(*) FROM stock_mouvements')->fetchColumn()!=3)throw new RuntimeException('Correction négative ou idempotence invalide');
rejectInventory(fn()=>inventoryCount($db,$id,$counts,[]));
$id2=inventoryOpen($db,'I2',1,'Stock modifié');$counts2=[];foreach($db->query("SELECT id,article_id FROM stock_inventaire_lignes WHERE inventaire_id=$id2")->fetchAll(PDO::FETCH_KEY_PAIR) as $line=>$article)$counts2[$line]=$article==1?4:2;inventoryCount($db,$id2,$counts2,[]);
$db->exec("INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_destination_id) VALUES(1,'reception',1,1)");rejectInventory(fn()=>inventoryValidate($db,$id2,1));
if($db->query("SELECT statut FROM stock_inventaires WHERE id=$id2")->fetchColumn()!=='ouvert')throw new RuntimeException('Inventaire périmé validé');
echo "OK : inventaire, comptages, écarts signés, séries, stock modifié, atomicité et idempotence\n";
