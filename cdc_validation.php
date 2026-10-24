<?php
$pageTitle='Validation du cahier des charges';
$activePage='projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/cahier_specs.php';
require_once __DIR__.'/includes/cdc_pdf.php';
require_once __DIR__.'/includes/cdc_validation.php';
require_once __DIR__.'/includes/r1b_steps.php';
requerirConnexion();
$db=getDB();
$pid=(int)($_GET['projet_id']??$_POST['projet_id']??0);
$cdcEmbedded=(($_GET['embedded']??$_POST['embedded']??'')==='1');
if($pid<=0) redirect('projets.php');
requerirAccesProjet($pid);
cdcValidationEnsureSchema($db);
$q=$db->prepare('SELECT id,nom FROM projets WHERE id=?');$q->execute([$pid]);$project=$q->fetch();
if(!$project){setFlash('error','Projet introuvable.');redirect('projets.php');}
$q=$db->prepare('SELECT id FROM cahiers WHERE projet_id=?');$q->execute([$pid]);$cahierId=(int)$q->fetchColumn();
if(!$cahierId){setFlash('error','Enregistrez le cahier des charges avant de demander sa validation.');redirect('cahier_form.php?projet_id='.$pid);}
$specs=loadSpecs($cahierId);$milestones=loadJalons($cahierId);
$currentHash=cdcGeneratedPdfHash($pid,(string)$project['nom'],$specs,$milestones);
$error='';$prepared=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfRequire();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='start'){
            $recipient=trim((string)($_POST['recipient']??''));
            if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Saisissez une adresse de courriel destinataire valide.');
            if(trim((string)($specs['objectifs']??''))==='') throw new RuntimeException('Les objectifs et le contexte doivent être renseignés avant une demande de validation.');
            $nc=new NextcloudClient();
            if(!$nc->isConfigured()) throw new RuntimeException('Nextcloud n’est pas configuré. La demande ne peut pas être préparée car le CDC doit rester dans les fichiers du projet.');
            $q=$db->prepare("SELECT * FROM cdc_validations WHERE projet_id=? AND cdc_hash=? ORDER BY id DESC LIMIT 1");
            $q->execute([$pid,$currentHash]);$prepared=$q->fetch() ?: null;
            if($prepared && $prepared['status']==='refusé') $prepared=null;
            $generated=cdcEnsureGeneratedPdf($pid,(string)$project['nom'],$specs,$milestones);
            if(!$prepared){
                $stamp=date('Ymd-His');
                $reference='ODW-P'.$pid.'-'.substr($currentHash,0,10).'-'.$stamp.'-'.strtoupper(bin2hex(random_bytes(2)));
                $pdfName='CDC_P'.$pid.'_'.substr($currentHash,0,10).'_'.substr($reference,-4).'.pdf';
                $pdfRemote=cdcValidationNextcloudPath($pid,$pdfName);
                $up=$nc->upload($pdfRemote,$generated['path']);
                if(!$up['ok']) throw new RuntimeException('Le PDF archivé dans le projet n’a pas pu être envoyé sur Nextcloud : '.$up['message']);
                cdcValidationRegisterDocument($db,$pid,'validation_cdc',$pdfName,$pdfRemote,'application/pdf',(int)filesize($generated['path']),(int)utilisateurCourant()['id']);
                $db->prepare("INSERT INTO cdc_validations(projet_id,reference,cdc_hash,pdf_hash,pdf_path,pdf_remote_path,recipient,status,created_by) VALUES(?,?,?,?,?,?,?,'pending',?)")
                    ->execute([$pid,$reference,$currentHash,$generated['hash'],$generated['path'],$pdfRemote,$recipient,(int)utilisateurCourant()['id']]);
                $id=(int)$db->lastInsertId();
                $q=$db->prepare('SELECT * FROM cdc_validations WHERE id=?');$q->execute([$id]);$prepared=$q->fetch();
            }else{
                if(!hash_equals((string)$prepared['recipient'],$recipient)) $db->prepare('UPDATE cdc_validations SET recipient=? WHERE id=?')->execute([$recipient,(int)$prepared['id']]);
                $q=$db->prepare('SELECT * FROM cdc_validations WHERE id=?');$q->execute([(int)$prepared['id']]);$prepared=$q->fetch();
            }
            $prepared['outlook_url']=cdcValidationOutlookUrl($recipient,cdcValidationSubject((string)$project['nom'],(string)$prepared['reference']),cdcValidationBody((string)$project['nom'],(string)$prepared['reference']));
            $prepared['reused']=!empty($generated['created'])?false:true;
            if($prepared['status']!=='pending') throw new RuntimeException('Cette validation est déjà clôturée. Un nouveau cycle peut être ouvert depuis le CDC après modification ou refus.');
        }elseif($action==='import'){
            $validationId=(int)($_POST['validation_id']??0);
            $q=$db->prepare("SELECT * FROM cdc_validations WHERE id=? AND projet_id=? AND status='pending'");$q->execute([$validationId,$pid]);$validation=$q->fetch();
            if(!$validation) throw new RuntimeException('Aucune demande de validation en attente ne correspond à cette réponse.');
            if(empty($_FILES['reply_file'])||($_FILES['reply_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Choisissez le courriel exporté depuis Outlook au format EML ou MSG.');
            $file=$_FILES['reply_file'];
            if((int)$file['size']<=0||(int)$file['size']>30*1024*1024) throw new RuntimeException('Le courriel est vide ou dépasse la limite de 30 Mo.');
            $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,['eml','msg'],true)) throw new RuntimeException('Seuls les fichiers .eml et .msg exportés depuis Outlook sont acceptés.');
            $raw=file_get_contents((string)$file['tmp_name']);
            if(!is_string($raw)||$raw==='') throw new RuntimeException('Le fichier importé n’a pas pu être lu.');
            $parsed=$ext==='eml'?cdcValidationParseEml($raw):cdcValidationParseMsg($raw);
            if(stripos($parsed['subject'].' '.$parsed['body'],(string)$validation['reference'])===false) throw new RuntimeException('La référence '.$validation['reference'].' est absente du courriel. Importez la réponse à cette demande de validation.');
            $replyText=preg_replace('/^\s*>.*(?:\n|$)/m','',$parsed['body'])??$parsed['body'];
            $replyText=preg_split('/\n\s*(?:-{2,}\s*(?:Original Message|Message d’origine)|Le .{3,120} a écrit\s*:|From:\s|De\s*:\s)/iu',$replyText,2)[0]??$replyText;
            $result=cdcValidationParseDecision($replyText);
            $sender=trim((string)$parsed['sender_name']);
            $senderEmail=trim((string)$parsed['sender_email']);
            if($sender===''&&$senderEmail==='') throw new RuntimeException('L’expéditeur du courriel n’a pas pu être extrait.');
            $displaySender=$sender!==''?$sender:($senderEmail!==''?$senderEmail:'Expéditeur inconnu');
            $validatedAt=(new DateTimeImmutable((string)$parsed['date']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:sP');
            $info="Validation du cahier des charges\nRéférence : {$validation['reference']}\nProjet : {$project['nom']}\nObjet du courriel : {$parsed['subject']}\nRésultat : CDC ".($result['decision']==='accepté'?'Accepté':'Refusé')."\nCommentaire :\n".($result['comment']!==''?$result['comment']:'(Aucun commentaire)');
            $info.="\n\nDate du courriel : {$validatedAt}\nPersonne ayant réalisé la validation : {$displaySender}".($senderEmail!==''?" <{$senderEmail}>":'')."\nFormat du message original : .".$ext."\nVersion du CDC : {$validation['pdf_remote_path']}\n";
            $decisionSlug=$result['decision']==='accepté'?'accepte':'refuse';
            $zipName='Validation_CDC_'.$validation['reference'].'_'.$decisionSlug.'_'.date('Ymd-His').'.zip';
            $zipPath=tempnam(sys_get_temp_dir(),'oddworks-cdc-');
            if($zipPath===false) throw new RuntimeException('Le fichier temporaire de l’archive n’a pas pu être créé.');
            try{
                cdcValidationSafeZip($zipPath,['validation.txt'=>$info,'reponse-originale.'.$ext=>$raw]);
                $zipRemote=cdcValidationNextcloudPath($pid,$zipName);
                $nc=new NextcloudClient();
                if(!$nc->isConfigured()) throw new RuntimeException('Nextcloud n’est pas configuré. Le courriel ne peut pas être archivé hors de l’espace projet.');
                $up=$nc->upload($zipRemote,$zipPath);
                if(!$up['ok']) throw new RuntimeException('L’archive de validation n’a pas été enregistrée dans Nextcloud : '.$up['message']);
                cdcValidationRegisterDocument($db,$pid,'validation_cdc',$zipName,$zipRemote,'application/zip',(int)filesize($zipPath),(int)utilisateurCourant()['id']);
            }finally{if(is_file($zipPath))@unlink($zipPath);}
            $db->prepare("UPDATE cdc_validations SET status=?,decision=?,comment=?,validator_name=?,validator_email=?,validated_at=?,response_format=?,response_remote_path=?,archive_remote_path=? WHERE id=? AND status='pending'")
                ->execute([$result['decision'],$result['decision'],$result['comment'],$displaySender,$senderEmail,$validatedAt,$ext,$zipRemote,$zipRemote,$validationId]);
            setFlash('success','Réponse importée : CDC '.($result['decision']==='accepté'?'accepté':'refusé').'. Le message original et les informations extraites sont archivés dans les fichiers du projet.');
            redirect('cdc_validation.php?projet_id='.$pid.($cdcEmbedded?'&embedded=1':''));
        }else throw new RuntimeException('Action de validation inconnue.');
    }catch(Throwable $e){
        error_log('OddWorks CDC validation project '.$pid.': '.get_class($e).' '.$e->getMessage());
        $error=$e instanceof RuntimeException?$e->getMessage():'La validation du CDC n’a pas pu être enregistrée.';
    }
}

$q=$db->prepare('SELECT * FROM cdc_validations WHERE projet_id=? ORDER BY id DESC');$q->execute([$pid]);$history=$q->fetchAll();
$latestPending=null;foreach($history as $row){if($row['status']==='pending'){$latestPending=$row;break;}}
$pageTitle='Validation du CDC — '.(string)$project['nom'];
require __DIR__.'/includes/header.php';
?>
<?php if($cdcEmbedded):?><style>.cdc-embedded .header,.cdc-embedded .footer{display:none!important}.cdc-embedded .main-content{width:100%;max-width:none;margin:0;padding:1rem}.cdc-embedded .page-header>a{display:none}</style><?php endif;?>
<div class="page-header"><div><h1><i class="fas fa-clipboard-check"></i> Validation du cahier des charges</h1><p class="text-muted">Projet : <strong><?=e((string)$project['nom'])?></strong></p></div><a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid.'&view=documents')?>">Retour au projet</a></div>
<?php if($error!==''):?><div class="alert alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<section class="card card-body" style="margin-bottom:1rem">
 <h2>Préparer une demande</h2>
 <p>OddWorks archive le PDF du CDC dans les fichiers du projet, puis prépare un brouillon Outlook avec la référence de validation.</p>
 <form method="post" class="form-row" style="align-items:end;gap:1rem"><?=csrfField()?>
  <input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="start"><input type="hidden" name="embedded" value="<?=$cdcEmbedded?'1':'0'?>">
  <div class="form-group" style="flex:1;min-width:260px"><label for="recipient">Destinataire</label><input class="form-control" id="recipient" type="email" name="recipient" required value="<?=e((string)($prepared['recipient']??($latestPending['recipient']??'francois.couraud@procomm-mmc.com')))?>"></div>
  <button type="submit" class="btn btn-primary"><i class="fas fa-envelope"></i> Préparer la demande de validation</button>
 </form>
</section>
<?php if($prepared):?>
<section class="card card-body" style="margin-bottom:1rem;border-left:4px solid #0e7490">
 <h2>Brouillon prêt · <?=e((string)$prepared['reference'])?></h2>
 <p>La version du CDC est archivée dans les fichiers du projet. Dans Outlook, joignez le PDF archivé à ce brouillon avant de l’envoyer. Le deeplink Outlook ne permet pas de joindre automatiquement un fichier.</p>
 <div style="display:flex;flex-wrap:wrap;gap:.75rem"><a class="btn btn-primary" href="<?=e($prepared['outlook_url'])?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Ouvrir le brouillon dans Outlook</a><a class="btn btn-secondary" href="<?=url('cdc_validation_pdf.php?id='.(int)$prepared['id'])?>"><i class="fas fa-file-pdf"></i> Télécharger le PDF archivé</a></div>
</section>
<?php endif;?>
<?php if($latestPending):?>
<section class="card card-body" style="margin-bottom:1rem">
 <h2>Importer la réponse</h2><p>Exportez le message reçu depuis Outlook en <code>.eml</code> ou <code>.msg</code>, puis importez-le ici. Le courriel doit contenir la référence <strong><?=e((string)$latestPending['reference'])?></strong>, une décision explicite « CDC Accepté » ou « CDC Refusé », et le champ « Commentaire : ».</p>
 <form method="post" enctype="multipart/form-data"><?=csrfField()?>
  <input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="import"><input type="hidden" name="validation_id" value="<?=(int)$latestPending['id']?>"><input type="hidden" name="embedded" value="<?=$cdcEmbedded?'1':'0'?>">
  <div class="form-group"><label for="replyFile">Courriel exporté (.eml ou .msg)</label><input class="form-control" id="replyFile" type="file" name="reply_file" accept=".eml,.msg,message/rfc822,application/vnd.ms-outlook" required></div>
  <button class="btn btn-primary" type="submit"><i class="fas fa-file-import"></i> Importer et archiver la réponse</button>
 </form>
</section>
<?php endif;?>
<section class="card card-body"><h2>Historique des validations</h2>
<?php if(!$history):?><p>Aucune demande de validation enregistrée.</p><?php else:?><div class="table-responsive"><table class="table"><thead><tr><th>Référence</th><th>État</th><th>Décision</th><th>Validateur</th><th>Date du courriel</th><th>Commentaire</th><th>Archive</th></tr></thead><tbody>
<?php foreach($history as $item):?><tr><td><?=e((string)$item['reference'])?></td><td><?=e($item['status']==='pending'?'En attente':($item['status']==='accepté'?'Clôturée — acceptée':'Clôturée — refusée'))?></td><td><?=e($item['decision']?('CDC '.($item['decision']==='accepté'?'Accepté':'Refusé')):'—')?></td><td><?=e((string)($item['validator_name']??'—'))?><?=!empty($item['validator_email'])?'<br><small>'.e((string)$item['validator_email']).'</small>':''?></td><td><?=e((string)($item['validated_at']??'—'))?></td><td><?=nl2br(e((string)($item['comment']??'—')))?></td><td><?php if(!empty($item['archive_remote_path'])):?><a href="<?=url('documents_externes.php?projet_id='.$pid)?>">Voir les fichiers du projet</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<?php require __DIR__.'/includes/footer.php';?>
