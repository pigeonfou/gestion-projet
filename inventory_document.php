<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/stock/catalogue.php';
require_once __DIR__.'/includes/NextcloudClient.php';
requerirConnexion();$db=getDB();inventorySchema($db);$client=new NextcloudClient();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $doc=invOne($db,'SELECT * FROM inventory_documents WHERE id=?',[(int)($_GET['id']??0)]);
    if(!$doc){http_response_code(404);exit('Document introuvable.');}
    header('Cache-Control: private, no-store');$client->download($doc['chemin'],$doc['nom']);exit;
}
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if($_SERVER['REQUEST_METHOD']!=='POST'||!csrfVerify())throw new InvalidArgumentException('Session ou jeton invalide.');
    $article=(int)($_POST['article_id']??0);invArticle($db,$article,true);
    $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']<1||$file['size']>20*1024*1024||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Fichier requis, 20 Mo maximum (selon la limite PHP du serveur).');
    $name=basename(str_replace('\\','/',$file['name']));$name=preg_replace('/[\x00-\x1F\x7F]/','',$name);if(!$name||strlen($name)>200)throw new InvalidArgumentException('Nom de fichier invalide.');
    $path='OddWorks-Stock/composants/'.$article.'/'.bin2hex(random_bytes(12)).'/'.$name;
    $upload=$client->upload($path,$file['tmp_name']);if(!$upload['ok'])throw new InvalidArgumentException('Envoi Nextcloud impossible. Vérifiez sa configuration et réessayez.');
    $db->beginTransaction();try{invArticle($db,$article,true);$id=invRun($db,'INSERT INTO inventory_documents(article_id,nom,chemin,taille) VALUES(?,?,?,?)',[$article,$name,$path,$file['size']]);invHistory($db,$article,(int)utilisateurCourant()['id'],'document_add',['nom'=>$name,'document_id'=>$id]);invRun($db,'UPDATE inventory_meta SET revision=revision+1 WHERE id=1');$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){http_response_code(422);echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Enregistrement documentaire impossible.']);}
