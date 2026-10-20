<?php
require_once __DIR__.'/../src/Services/DocumentService.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE document_operations(id INTEGER PRIMARY KEY,projet_id INTEGER,remote_path TEXT,metadata_json TEXT,statut TEXT DEFAULT 'pending',updated_at TEXT);CREATE TABLE documents(id INTEGER PRIMARY KEY,projet_id INTEGER,phase TEXT,nom_fichier TEXT,chemin_nextcloud TEXT,taille INTEGER,mime TEXT,uploader_id INTEGER)");
$id=pfDocumentIntent($db,['projet_id'=>1,'phase'=>'01_Besoin','nom_fichier'=>'binary.bin','chemin_nextcloud'=>'Projet_1/binary.bin','taille'=>0,'mime'=>'application/octet-stream','uploader_id'=>1]);
pfDocumentUploaded($db,$id);
$db->exec("CREATE TRIGGER fail_insert BEFORE INSERT ON documents BEGIN SELECT RAISE(ABORT,'failure'); END");
try{pfDocumentComplete($db,$id);throw new LogicException('Failure missing');}catch(PDOException $e){}
if($db->query('SELECT statut FROM document_operations')->fetchColumn()!=='uploaded')throw new RuntimeException('Recovery intent lost');
$db->exec('DROP TRIGGER fail_insert');$doc=pfDocumentComplete($db,$id);
if(pfDocumentComplete($db,$id)!==$doc||$db->query('SELECT COUNT(*) FROM documents')->fetchColumn()!=1)throw new RuntimeException('Recovery not idempotent');
echo "Recode documents: durable intent, rollback and idempotent reconciliation OK\n";
