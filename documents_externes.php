<?php
$pageTitle = 'Documents externes';
$activePage = 'projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/NextcloudClient.php';
require_once __DIR__.'/includes/ux_objects.php';
require_once __DIR__.'/includes/document_relations.php';
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
    $nc->download($doc['chemin_nextcloud'],$doc['nom_fichier'], ($doc['mime'] ?? '') === 'text/markdown');exit;
}
$q=$db->prepare('SELECT * FROM documents WHERE projet_id=? ORDER BY phase,nom_fichier');$q->execute([$pid]);$docs=$q->fetchAll();
$linkQuery=$db->prepare('SELECT * FROM document_st_links WHERE projet_id=?');$linkQuery->execute([$pid]);$linksByDoc=[];foreach($linkQuery->fetchAll() as $link)$linksByDoc[(int)$link['document_id']][]=$link['st_uid'];
$stByUid=array_column($specs['specs_techniques'],null,'uid');
if($stFilter!=='')$docs=array_values(array_filter($docs,fn($d)=>in_array($stFilter,$linksByDoc[(int)$d['id']]??[],true)));
require __DIR__.'/includes/header.php';
?>
<div class="page-header"><h1>Documents externes</h1><?php if($pid):?>

<a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid)?>">Retour au projet</a><?php endif;?></div>
<p>Les documents sont transférés vers Nextcloud. ProjectFlow conserve uniquement leurs références et métadonnées. Aucun document généré par cet écran n’est écrit sur le disque du serveur.</p>
<?php if($result):?><div class="alert alert-<?=$result['ok']?'success':'error'?>"><?=e($result['message'])?></div><?php endif;?>
<section class="card card-body" style="margin-top:20px"><div class="pf-section-head"><h2>Bibliothèque documentaire · <?=count($docs)?></h2><?php if($stFilter!==''):?><a href="<?=url('documents_externes.php?projet_id='.$pid)?>">Voir tous les documents du projet</a><?php endif;?></div>
<label for="docSearch">Rechercher un document</label><input id="docSearch" type="search" class="form-control" data-pf-filter="pf-doc-table" placeholder="Nom, dossier, S.T.…" style="max-width:400px;margin:8px 0 16px">
<table class="table" id="pf-doc-table"><thead><tr><th>Dossier</th><th>Document Nextcloud</th><th>Relations</th><th>Taille</th><th>Dernier envoi</th></tr></thead><tbody>
<?php foreach($docs as $doc):?><tr id="document-<?=(int)$doc['id']?>"><td><span class="pf-tag"><?=e($doc['phase'])?></span></td><td><a href="<?=url('documents_externes.php?projet_id='.$pid.'&document='.$doc['id'])?>"><?=e($doc['nom_fichier'])?></a></td><td><?php foreach($linksByDoc[(int)$doc['id']]??[] as $linkUid):?><p><?php if(isset($stByUid[$linkUid])):?><a class="pf-object-id" href="<?=pfStUrl($pid,$stByUid[$linkUid])?>"><?=e($stByUid[$linkUid]['id'])?></a><?php else:?><span class="pf-tag">S.T. supprimée</span><?php endif;?></p><?php endforeach;?><a href="<?=url('projet.php?id='.$pid)?>">Projet</a></td><td><?=number_format((int)$doc['taille']/1024,1,',',' ')?> Ko</td><td><?=e($doc['date_upload'])?></td></tr><?php endforeach;?></tbody></table>
<?php if(!$docs):?><p class="pf-empty"><?=$stFilter!==''?'Aucun document explicitement associé à cette S.T. Les autres documents du projet restent disponibles.':'Aucun document enregistré.'?></p><?php endif;?></section>
<?php if($pid):?><details class="pf-create card card-body" style="margin-top:16px"><summary>Associer un document existant à une S.T.</summary><form method="post"><?=csrfField()?><input type="hidden" name="action" value="link_st"><input type="hidden" name="projet_id" value="<?=$pid?>"><div class="form-row"><label>Document<select name="document_id" class="form-control" required><option value="">Choisir</option><?php $dq=$db->prepare('SELECT id,nom_fichier FROM documents WHERE projet_id=? ORDER BY nom_fichier');$dq->execute([$pid]);foreach($dq->fetchAll() as $d):?><option value="<?=(int)$d['id']?>"><?=e($d['nom_fichier'])?></option><?php endforeach;?></select></label><label>S.T.<select name="st_uid" class="form-control" required><option value="">Choisir</option><?php foreach($specs['specs_techniques'] as $st):?><option value="<?=e($st['uid'])?>" <?=$stFilter===$st['uid']?'selected':''?>><?=e($st['id'].' — '.$st['description'])?></option><?php endforeach;?></select></label></div><button class="btn btn-primary">Associer le document</button></form></details><?php endif;?>
<?php if(estAdmin()):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="diagnostic"><button class="btn btn-secondary">Tester la connexion Nextcloud</button></form><?php endif;?>
<?php if($pid):?>
<details class="pf-create" style="margin-top:16px"><summary>Importer un fichier sur Nextcloud</summary><form id="fileUploadForm" class="card" style="padding:1rem;margin-top:1rem" data-endpoint="<?=e(url('documents_externes.php?projet_id='.$pid))?>">
<?=csrfField()?>
<h2>Importer un document sur Nextcloud</h2>
<label for="uploadFolder">Dossier</label><input id="uploadFolder" class="form-control" value="01_Besoin" pattern="[A-Za-z0-9_-]+" required>
<label for="uploadFile">Fichier</label><input id="uploadFile" type="file" class="form-control" required>
<p>Tous les types de fichiers sont acceptés. Aucune limite de taille n’est imposée par ProjectFlow.</p>
<button class="btn btn-primary" type="submit">Envoyer sur Nextcloud</button>
<progress id="uploadProgress" max="100" value="0" hidden style="width:100%"></progress>
<p id="uploadStatus" role="status" aria-live="polite"></p>
</form></details>
<script src="<?=url('assets/js/document-upload.js')?>" defer></script>
<details class="pf-create" style="margin-top:16px"><summary>Rédiger un document sur Nextcloud</summary><form method="post" class="card" style="padding:1rem;margin-top:1rem"><?=csrfField()?>
<input type="hidden" name="projet_id" value="<?=$pid?>">
<label for="docFolder">Dossier</label><input id="docFolder" class="form-control" name="dossier" value="01_Besoin" required>
<label for="docName">Nom du document (.md)</label><input id="docName" class="form-control" name="nom" placeholder="expression_besoin.md" required>
<label for="docContent">Contenu du document</label><textarea id="docContent" class="form-control" name="contenu" rows="12" required></textarea>
<button class="btn btn-primary">Enregistrer sur Nextcloud et vérifier la récupération</button></form></details>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
