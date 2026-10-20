<?php
$pageTitle = 'Documents externes';
$activePage = 'projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/NextcloudClient.php';
require_once __DIR__.'/includes/ux_objects.php';
require_once __DIR__.'/includes/document_relations.php';
require_once __DIR__.'/src/Services/DocumentService.php';
requerirConnexion();
$db = getDB();
$pid = (int)($_GET['projet_id'] ?? $_POST['projet_id'] ?? 0);
if ($pid > 0) requerirAccesProjet($pid, false);
else requerirAdmin();
$nc = new NextcloudClient();
pfDocumentLinksSchema($db);
$specs=$pid?pfSpecs($pid):emptySpecs();
$stFilter=(string)($_GET['st_uid']??'');

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrfVerify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Session expirée. Rechargez la page.']); exit;
    }
    requerirAccesProjet($pid);
    $name = (string)($_GET['nom'] ?? '');
    $folder = (string)($_GET['dossier'] ?? '01_Besoin');
    if ($name === '' || in_array($name,['.','..'],true) || str_contains($name,'/') || str_contains($name,chr(92)) || preg_match('/[\x00-\x1f\x7f]/u',$name) ||
        !preg_match('/^[A-Za-z0-9_-]+$/D',$folder) || !preg_match('/^\\d+$/D',$_SERVER['CONTENT_LENGTH'] ?? '')) {
        http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Nom, dossier ou taille du fichier invalide.']); exit;
    }
    $size = (int)$_SERVER['CONTENT_LENGTH'];
    $actor = utilisateurCourant()['id'];
    session_write_close();
    set_time_limit(0);
    $stream = fopen('php://input','rb');
    try {
        $remote = pfDocumentPath($pid,$folder,$name);
        $operation=pfDocumentIntent($db,['projet_id'=>$pid,'phase'=>$folder,'nom_fichier'=>$name,'chemin_nextcloud'=>$remote,'taille'=>$size,'mime'=>'application/octet-stream','uploader_id'=>$actor]);
        $result = $nc->uploadStream($remote,$stream,$size);
        if ($result['ok']) {
            pfDocumentUploaded($db,$operation);
            pfDocumentComplete($db,$operation);
            $result['message'] = 'Document « '.$name.' » envoyé sur Nextcloud.';
        } else { http_response_code(502); }
    } catch (Throwable $e) {
        error_log('ProjectFlow upload documentaire: '.get_class($e));
        http_response_code(500);
        $result = ['ok'=>false,'message'=>'Enregistrement non confirmé. Consultez Nextcloud avant de réessayer.'];
    } finally { if (is_resource($stream)) fclose($stream); }
    echo json_encode($result,JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}

$result = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfRequire();
    try {
    if(($_POST['action']??'')==='link_st'){requerirAccesProjet($pid);pfDocumentLink($db,$pid,(int)($_POST['document_id']??0),(string)($_POST['st_uid']??''),$specs,(int)utilisateurCourant()['id']);setFlash('success','Document associé à la S.T.');redirect('documents_externes.php?projet_id='.$pid);} elseif (($_POST['action'] ?? '')==='diagnostic') {
        requerirAdmin();
        $result=$nc->testConnection();
    } else {
        requerirAccesProjet($pid);
        $name=trim((string)($_POST['nom']??''));
        $folder=trim((string)($_POST['dossier']??'01_Besoin'));
        $content=(string)($_POST['contenu']??'');
        if (!preg_match('/^[A-Za-z0-9_.-]+\.md$/D',$name) || !preg_match('/^[A-Za-z0-9_-]+$/D',$folder) || $content==='') {
            $result=['ok'=>false,'message'=>'Nom .md, dossier et contenu obligatoires.'];
        } else {
            $remote=pfDocumentPath($pid,$folder,$name);
            $result=$nc->uploadContent($remote,$content);
            if ($result['ok']) {
                $read=$nc->readContent($remote);
                $result=['ok'=>$read['ok'] && hash_equals(hash('sha256',$content),hash('sha256',$read['content'])),'message'=>'Envoi puis récupération et comparaison SHA-256 : '.($read['ok'] && $read['content']===$content?'réussis':'échec')];
                if ($result['ok']) {
                    $q=$db->prepare('SELECT id FROM documents WHERE projet_id=? AND chemin_nextcloud=?');$q->execute([$pid,$remote]);$doc=$q->fetchColumn();
                    if ($doc) $db->prepare('UPDATE documents SET taille=?, date_upload=CURRENT_TIMESTAMP WHERE id=?')->execute([strlen($content),$doc]);
                    else $db->prepare('INSERT INTO documents(projet_id,phase,nom_fichier,chemin_nextcloud,taille,mime,uploader_id) VALUES(?,?,?,?,?,?,?)')->execute([$pid,$folder,$name,$remote,strlen($content),'text/markdown',utilisateurCourant()['id']]);
                }
            }
        }
    }
    } catch (Throwable $e) {
        error_log('ProjectFlow documents externes: '.get_class($e));
        $result=['ok'=>false, 'message'=>'Erreur interne documentaire ('.get_class($e).'). Aucun document confirmé comme enregistré. Consultez le journal PHP du serveur.'];
    }
}
if (isset($_GET['document'])) {
    $q=$db->prepare('SELECT * FROM documents WHERE id=? AND projet_id=?');$q->execute([(int)$_GET['document'],$pid]);$doc=$q->fetch();
    if (!$doc) {http_response_code(404);exit('Document introuvable.');}
    session_write_close();
    set_time_limit(0);
    $nc->download($doc['chemin_nextcloud'],$doc['nom_fichier'], ($doc['mime'] ?? '') === 'text/markdown');exit;
}
$q=$db->prepare('SELECT * FROM documents WHERE projet_id=? ORDER BY phase,nom_fichier');$q->execute([$pid]);$docs=$q->fetchAll();
$linkQuery=$db->prepare('SELECT * FROM document_st_links WHERE projet_id=?');$linkQuery->execute([$pid]);$linksByDoc=[];foreach($linkQuery->fetchAll() as $link)$linksByDoc[(int)$link['document_id']][]=$link['st_uid'];
$stByUid=array_column($specs['specs_techniques'],null,'uid');
if($stFilter!=='')$docs=array_values(array_filter($docs,fn($d)=>in_array($stFilter,$linksByDoc[(int)$d['id']]??[],true)));
require __DIR__.'/includes/header.php';
require __DIR__.'/views/documents/index.php';
