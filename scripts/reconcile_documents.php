<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../includes/schema.php';runSchemaMigrations();
require_once __DIR__.'/../includes/NextcloudClient.php';
require_once __DIR__.'/../src/Services/DocumentService.php';
$db=getDB();$nc=new NextcloudClient();
foreach($db->query("SELECT id,remote_path,statut FROM document_operations WHERE statut IN ('pending','uploaded') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $row){
    if($row['statut']==='pending'){echo 'Manual verification required for operation #'.$row['id'].PHP_EOL;continue;}
    $remote=$nc->statFile($row['remote_path']);
    if($remote['ok']){pfDocumentComplete($db,(int)$row['id']);echo 'Confirmed operation #'.$row['id'].PHP_EOL;}
    else echo 'Unconfirmed operation #'.$row['id'].' (HTTP '.$remote['status'].')'.PHP_EOL;
}
