<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/cdc_validation.php';
requerirConnexion();
$id=(int)($_GET['id']??0);
cdcValidationEnsureSchema(getDB());
$q=getDB()->prepare('SELECT v.*,p.nom FROM cdc_validations v JOIN projets p ON p.id=v.projet_id WHERE v.id=?');
$q->execute([$id]);$row=$q->fetch();
if(!$row){http_response_code(404);exit('Version du CDC introuvable.');}
requerirAccesProjet((int)$row['projet_id'],false);
$filename=basename((string)$row['pdf_remote_path']);
if($filename===''||!str_ends_with(strtolower($filename),'.pdf')){http_response_code(404);exit('PDF archivé introuvable.');}
$nc=new NextcloudClient();
if(!$nc->isConfigured()){http_response_code(503);exit('Nextcloud n’est pas configuré.');}
$nc->download((string)$row['pdf_remote_path'],$filename);
