<?php
require_once __DIR__.'/../src/Services/StockMovementService.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY);INSERT INTO utilisateurs VALUES(1);CREATE TABLE projets(id INTEGER PRIMARY KEY);INSERT INTO projets VALUES(1),(2);CREATE TABLE stock_articles(id INTEGER PRIMARY KEY);INSERT INTO stock_articles VALUES(1);CREATE TABLE stock_emplacements(id INTEGER PRIMARY KEY,actif INTEGER);INSERT INTO stock_emplacements VALUES(1,1),(2,1);CREATE TABLE stock_mouvements(id INTEGER PRIMARY KEY,article_id INTEGER,type TEXT,quantite REAL,emplacement_source_id INTEGER,emplacement_destination_id INTEGER,projet_id INTEGER,fournisseur_id INTEGER,lot_id INTEGER,reference_externe TEXT,note TEXT,user_id INTEGER);CREATE TABLE stock_reservations(id INTEGER PRIMARY KEY,article_id INTEGER,projet_id INTEGER,quantite REAL,emplacement_id INTEGER,note TEXT,statut TEXT DEFAULT 'active',date_fin TEXT);INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_destination_id) VALUES(1,'reception',10,2);");
function stockRefused(callable $f):void {try {$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid movement accepted');}
stockRefused(fn()=>pfRecordStockMovement($db,1,'consommation',5,1,null));
stockRefused(fn()=>pfRecordStockMovement($db,1,'consommation',1,2,1));
stockRefused(fn()=>pfRecordStockMovement($db,1,'transfert',1,2,2));
stockRefused(fn()=>pfRecordStockMovement($db,1,'reception',INF,null,1));
$db->exec("INSERT INTO stock_reservations(article_id,projet_id,quantite,emplacement_id) VALUES(1,2,8,2)");
stockRefused(fn()=>pfRecordStockMovement($db,1,'consommation',3,2,null,1,null,null,'TEST',null,1));
pfRecordStockMovement($db,1,'consommation',2,2,null,1,null,null,'TEST',null,1);
if(productionQuantity($db,1,2)!==8.0 || productionQuantity($db,1,1)!==0.0)throw new RuntimeException('Wrong source quantity');
echo "Recode stock: location, reservation, direction and finite quantities OK\n";
