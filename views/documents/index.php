<?php

?>
<div class="page-header"><h1>Documents externes</h1><?php if($pid):?>

<a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid)?>">Retour au projet</a><?php endif;?></div>
<p>Les documents sont transférés vers Nextcloud. OddWorks conserve uniquement leurs références et métadonnées. Aucun document généré par cet écran n’est écrit sur le disque du serveur.</p>
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
<p>Tous les types de fichiers sont acceptés. Aucune limite de taille n’est imposée par OddWorks.</p>
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
<?php require PROJECTFLOW_ROOT . '/includes/footer.php';?>
