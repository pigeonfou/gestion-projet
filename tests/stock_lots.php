<?php
require_once __DIR__.'/../includes/stock/lot_workflow.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE stock_articles(id INTEGER PRIMARY KEY,quantite_stock REAL DEFAULT 0,updated_at TEXT);CREATE TABLE stock_fournisseurs(id INTEGER PRIMARY KEY);CREATE TABLE stock_emplacements(id INTEGER PRIMARY KEY,actif INTEGER);CREATE TABLE projets(id INTEGER PRIMARY KEY);CREATE TABLE stock_lots(id INTEGER PRIMARY KEY,article_id INTEGER,fournisseur_id INTEGER,reference_lot TEXT,date_reception TEXT,quantite_initiale REAL,cout_unitaire_ht REAL,tva REAL,statut TEXT,notes TEXT);CREATE TABLE stock_mouvements(id INTEGER PRIMARY KEY,article_id INTEGER,type TEXT,quantite REAL,emplacement_destination_id INTEGER,projet_id INTEGER,fournisseur_id INTEGER,lot_id INTEGER,reference_externe TEXT,note TEXT,user_id INTEGER);INSERT INTO stock_articles(id) VALUES(1);INSERT INTO stock_emplacements VALUES(1,1);INSERT INTO projets VALUES(1);');
stockLotSchema($db);stockLotSchema($db);
$p=['article_id'=>1,'emplacement_id'=>1,'projet_id'=>1,'reference_lot'=>'TEST-Q','date_reception'=>'2026-10-28','quantite'=>2,'cout'=>180,'tva'=>20,'statut'=>'quarantaine'];
$id=stockLotCreate($db,$p,1);
if($db->query('SELECT COUNT(*) FROM stock_mouvements')->fetchColumn()!=0)throw new RuntimeException('Quarantaine entrée en stock');
stockLotReceive($db,$id,1,1,1);stockLotReceive($db,$id,1,1,1);
if($db->query('SELECT COUNT(*) FROM stock_mouvements')->fetchColumn()!=1||$db->query('SELECT quantite_stock FROM stock_articles')->fetchColumn()!=2)throw new RuntimeException('Libération ou idempotence incorrecte');
$p['reference_lot']='TEST-R';$p['statut']='rejete';$rid=stockLotCreate($db,$p,1);
$denied=0;foreach([fn()=>stockLotReceive($db,$rid,1,1,1),fn()=>stockLotCreate($db,array_merge($p,['quantite'=>-1]),1),fn()=>stockLotCreate($db,array_merge($p,['date_reception'=>'2026-02-30']),1),fn()=>stockLotCreate($db,array_merge($p,['emplacement_id'=>999]),1),fn()=>stockLotCreate($db,$p,1)] as $f)try{$f();}catch(InvalidArgumentException $e){$denied++;}
if($denied!==5||$db->query('SELECT COUNT(*) FROM stock_lots')->fetchColumn()!=2)throw new RuntimeException('Validation/atomicité incorrectes');
$p['reference_lot']='TEST-L';$p['statut']='libere';stockLotCreate($db,$p,1);
if($db->query('SELECT quantite_stock FROM stock_articles')->fetchColumn()!=4)throw new RuntimeException('Création libérée sans entrée');
echo "OK : lots, quarantaine, libération atomique, absence de doublon et validations\n";
