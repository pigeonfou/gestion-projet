<?php
require_once __DIR__.'/../includes/stock/reservation_workflow.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY);INSERT INTO utilisateurs VALUES(1);CREATE TABLE projets(id INTEGER PRIMARY KEY);INSERT INTO projets VALUES(1);
CREATE TABLE stock_articles(id INTEGER PRIMARY KEY);INSERT INTO stock_articles VALUES(1);CREATE TABLE stock_emplacements(id INTEGER PRIMARY KEY,actif INTEGER);INSERT INTO stock_emplacements VALUES(1,1),(2,1);
CREATE TABLE stock_mouvements(id INTEGER PRIMARY KEY,article_id INTEGER,quantite REAL,emplacement_source_id INTEGER,emplacement_destination_id INTEGER);INSERT INTO stock_mouvements VALUES(1,1,5,NULL,1);
CREATE TABLE stock_reservations(id INTEGER PRIMARY KEY,article_id INTEGER,projet_id INTEGER,quantite REAL,emplacement_id INTEGER,note TEXT,statut TEXT DEFAULT 'active',date_fin TEXT);");reservationSchema($db);
function deniedReservation(callable $f):void {try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Réservation invalide acceptée');}
$p=['article_id'=>1,'projet_id'=>1,'emplacement_id'=>1,'quantite'=>4];$id=reservationCreate($db,$p,1);
deniedReservation(fn()=>reservationCreate($db,array_merge($p,['quantite'=>2]),1));deniedReservation(fn()=>reservationCreate($db,array_merge($p,['quantite'=>-1]),1));deniedReservation(fn()=>reservationCreate($db,array_merge($p,['emplacement_id'=>2]),1));
reservationClose($db,$id,'liberee',1,'Essai');reservationClose($db,$id,'liberee',1,'Idempotent');
if($db->query('SELECT COUNT(*) FROM stock_reservation_historique')->fetchColumn()!=2)throw new RuntimeException('Clôture non idempotente');
$p['quantite']=3;$id2=reservationCreate($db,$p,1);$db->beginTransaction();reservationConsume($db,1,1,1,2,1,'OF:TEST');$db->commit();
if($db->query("SELECT quantite FROM stock_reservations WHERE id=$id2")->fetchColumn()!=1||$db->query("SELECT SUM(quantite) FROM stock_reservations WHERE statut='consommee'")->fetchColumn()!=2)throw new RuntimeException('Fractionnement consommation incorrect');
reservationClose($db,$id2,'annulee',1,'Clôture restant');if(productionQuantity($db,1,1)!==5.0)throw new RuntimeException('Réservation modifie le stock physique');
echo "OK : disponibilité, réservations, libération, annulation, consommation partielle et journal\n";
