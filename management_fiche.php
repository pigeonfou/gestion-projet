<?php
$pageTitle='Suivi management';$activePage='management';
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/management_workflow.php';
requerirConnexion();runSchemaMigrations();$db=getDB();managementSchema($db);
$type=(string)($_GET['type']??'');$id=(int)($_GET['id']??0);
try {$definition=managementDefinition($type);$row=managementRead($db,$type,$id);}catch(Throwable $e){http_response_code(404);exit('Fiche introuvable.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfRequire();requerirAdmin();
    try {managementUpdate($db,$type,$id,$_POST,(int)utilisateurCourant()['id']);setFlash('success','Suivi enregistré avec preuve et historique.');redirect('management_fiche.php?type='.$type.'&id='.$id);}catch(Throwable $e){setFlash('error',$e->getMessage());}
    $row=managementRead($db,$type,$id);
}
$stmt=$db->prepare('SELECT h.*,d.nom_fichier FROM management_historique h LEFT JOIN documents d ON d.id=h.document_externe_id WHERE h.type=? AND h.fiche_id=? ORDER BY h.id DESC');$stmt->execute([$type,$id]);$history=$stmt->fetchAll();$last=$history[0]??[];
$stmt=$db->prepare('SELECT id,nom_fichier FROM documents WHERE projet_id=? ORDER BY id');$stmt->execute([(int)($row['projet_id']??0)]);$documents=$stmt->fetchAll();
$back=['nc'=>'nc','action'=>'actions','risque'=>'risques','document'=>'documents'][$type];
require __DIR__.'/includes/header.php';?>
<div class="page-header"><div><h1><?=e($definition['title'])?> #<?=$id?></h1><p><?=e($row[$definition['label']])?></p></div><a class="btn btn-secondary" href="<?=url('management.php?tab='.$back)?>">Retour au management</a></div>
<div class="card" style="padding:1rem"><p><strong>Statut :</strong> <?=e($row['statut'])?></p><p style="white-space:pre-wrap"><?=e($row['description']??'')?></p><p><?=e($row['disposition']??'')?></p><?php if(!empty($row['projet_id'])):?><a href="<?=url('projet.php?id='.(int)$row['projet_id'])?>">Projet #<?=(int)$row['projet_id']?></a><?php endif;?></div>
<?php if(estAdmin()):?><div class="card" style="padding:1rem;margin-top:1rem"><form method="post"><?=csrfField()?>
<label>Statut<select name="statut" class="form-control"><?php foreach($definition['statuses'] as $status):?><option <?=$status===$row['statut']?'selected':''?>><?=e($status)?></option><?php endforeach;?></select></label>
<label>Date métier<input name="date_metier" type="date" class="form-control" required value="<?=e($last['date_metier']??date('Y-m-d'))?>"></label>
<label>Preuve Nextcloud du projet<select name="document_externe_id" class="form-control"><option value="">Sans preuve</option><?php foreach($documents as $doc):?><option value="<?=(int)$doc['id']?>" <?=(int)($last['document_externe_id']??0)===(int)$doc['id']?'selected':''?>><?=e($doc['nom_fichier'])?></option><?php endforeach;?></select></label>
<?php if($type==='nc'):?><label>Cause<textarea name="cause" class="form-control"><?=e($row['cause']??'')?></textarea></label><label>Action corrective<textarea name="action_corrective" class="form-control"><?=e($row['action_corrective']??'')?></textarea></label><?php endif;?>
<?php if($type==='document'):?><label>Approbateur<input name="approbateur" class="form-control" value="<?=e($row['approbateur']??'')?>"></label><?php endif;?>
<label>Résultat / vérification d’efficacité<textarea name="resultat" class="form-control" required><?=e($row[$definition['result']]??'')?></textarea></label>
<p>Les dates métier simulées sont distinctes des dates d’enregistrement. Une clôture exige un résultat et une preuve du même projet.</p><button class="btn btn-primary">Enregistrer le suivi</button></form></div><?php endif;?>
<div class="card" style="padding:1rem;margin-top:1rem"><h2>Historique</h2><?php foreach($history as $event):$before=json_decode($event['avant'],true);$after=json_decode($event['apres'],true);?><div style="border-bottom:1px solid #ddd;padding:.5rem"><p><?=e($event['date_metier'])?> — <?=e($before['statut'].' → '.$after['statut'])?> — acteur #<?=(int)$event['utilisateur_id']?> — enregistré <?=e($event['date_action'])?></p><p style="white-space:pre-wrap"><?=e($after[$definition['result']]??'')?></p><?php if($event['document_externe_id']):?><p>Preuve : <?=e($event['nom_fichier'])?> — <a href="<?=url('documents_externes.php?projet_id='.(int)$row['projet_id'])?>">Documents Nextcloud du projet</a></p><?php endif;?></div><?php endforeach;?><?php if(!$history):?><p>Aucun suivi enregistré.</p><?php endif;?></div>
<?php require __DIR__.'/includes/footer.php';?>
