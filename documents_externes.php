<?php
$pageTitle = 'Documents externes';
$activePage = 'projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/NextcloudClient.php';
requerirConnexion();
$db = getDB();
$pid = (int)($_GET['projet_id'] ?? $_POST['projet_id'] ?? 0);
if ($pid > 0) requerirAccesProjet($pid, false);
else requerirAdmin();
$nc = new NextcloudClient();

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
        $remote = trim(getSetting('nextcloud_root','ProjectFlow'),'/').'/Projet_'.$pid.'/'.$folder.'/'.$name;
        $result = $nc->uploadStream($remote,$stream,$size);
        if ($result['ok']) {
            $db->prepare('INSERT INTO documents(projet_id,phase,nom_fichier,chemin_nextcloud,taille,mime,uploader_id) VALUES(?,?,?,?,?,?,?)')
                ->execute([$pid,$folder,$name,$remote,$size,'application/octet-stream',$actor]);
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
    if (($_POST['action'] ?? '')==='diagnostic') {
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
            $remote=trim(getSetting('nextcloud_root','ProjectFlow'),'/').'/Projet_'.$pid.'/'.$folder.'/'.$name;
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
    $nc->download($doc['chemin_nextcloud'],$doc['nom_fichier']);exit;
}
$q=$db->prepare('SELECT * FROM documents WHERE projet_id=? ORDER BY phase,nom_fichier');$q->execute([$pid]);$docs=$q->fetchAll();
require __DIR__.'/includes/header.php';
?>
<div class="page-header"><h1>Documents externes</h1><?php if($pid):?>

<a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid)?>">Retour au projet</a><?php endif;?></div>
<p>Les documents sont transférés vers Nextcloud. ProjectFlow conserve uniquement leurs références et métadonnées. Aucun document généré par cet écran n’est écrit sur le disque du serveur.</p>
<?php if($result):?><div class="alert alert-<?=$result['ok']?'success':'error'?>"><?=e($result['message'])?></div><?php endif;?>
<?php if(estAdmin()):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="diagnostic"><button class="btn btn-secondary">Tester la connexion Nextcloud</button></form><?php endif;?>
<?php if($pid):?>
<form id="fileUploadForm" class="card" style="padding:1rem;margin-top:1rem" data-endpoint="<?=e(url('documents_externes.php?projet_id='.$pid))?>">
<?=csrfField()?>
<h2>Importer un document sur Nextcloud</h2>
<label for="uploadFolder">Dossier</label><input id="uploadFolder" class="form-control" value="01_Besoin" pattern="[A-Za-z0-9_-]+" required>
<label for="uploadFile">Fichier</label><input id="uploadFile" type="file" class="form-control" required>
<p>Tous les types de fichiers sont acceptés. Aucune limite de taille n’est imposée par ProjectFlow.</p>
<button class="btn btn-primary" type="submit">Envoyer sur Nextcloud</button>
<progress id="uploadProgress" max="100" value="0" hidden style="width:100%"></progress>
<p id="uploadStatus" role="status" aria-live="polite"></p>
</form>
<script src="<?=url('assets/js/document-upload.js')?>" defer></script>
<form method="post" class="card" style="padding:1rem;margin-top:1rem"><?=csrfField()?>
<input type="hidden" name="projet_id" value="<?=$pid?>">
<label for="docFolder">Dossier</label><input id="docFolder" class="form-control" name="dossier" value="01_Besoin" required>
<label for="docName">Nom du document (.md)</label><input id="docName" class="form-control" name="nom" placeholder="expression_besoin.md" required>
<label for="docContent">Contenu du document</label><textarea id="docContent" class="form-control" name="contenu" rows="12" required></textarea>
<button class="btn btn-primary">Enregistrer sur Nextcloud et vérifier la récupération</button></form>
<?php endif;?>
<table class="table"><thead><tr><th>Dossier</th><th>Document</th><th>Taille</th><th>Dernier envoi</th></tr></thead><tbody>
<?php foreach($docs as $doc):?><tr><td><?=e($doc['phase'])?></td><td><a href="<?=url('documents_externes.php?projet_id='.$pid.'&document='.$doc['id'])?>"><?=e($doc['nom_fichier'])?></a></td><td><?=(int)$doc['taille']?> octets</td><td><?=e($doc['date_upload'])?></td></tr><?php endforeach;?></tbody></table>
<?php require __DIR__.'/includes/footer.php';?>
