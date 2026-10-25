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
$cdcMode=(string)($_GET['mode']??'overview');
if(!in_array($cdcMode,['overview','request','import','history'],true))$cdcMode='overview';

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
            if($prepared && $prepared['status']!=='pending') throw new RuntimeException('Cette validation est déjà clôturée. Modifiez le CDC ou traitez un refus pour ouvrir un nouveau cycle.');
            if($prepared){
                $pdfPath=cdcGeneratedPdfPath($pid,(string)$project['nom'],$specs,$milestones);
                if(!is_file($pdfPath)||filesize($pdfPath)<=0) throw new RuntimeException('Le PDF archivé de cette validation n’est plus disponible sur le serveur. Aucun nouvel archivage n’a été effectué.');
                $generated=['path'=>$pdfPath,'hash'=>$currentHash,'created'=>false];
            }else{
                $generated=cdcEnsureGeneratedPdf($pid,(string)$project['nom'],$specs,$milestones);
            }
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
            if($prepared['status']!=='pending') throw new RuntimeException('Cette validation est déjà clôturée. Un nouveau cycle peut être ouvert depuis le CDC après modification ou refus.');
            $msgPath=cdcValidationBuildMsg(
                $recipient,
                cdcValidationSubject((string)$project['nom'],(string)$prepared['reference']),
                (string)$project['nom'],
                (string)$prepared['reference'],
                (string)$generated['path'],
                basename((string)$prepared['pdf_remote_path'])
            );
            $downloadName='Demande_validation_CDC_'.preg_replace('/[^A-Za-z0-9_-]+/','_', (string)$prepared['reference']).'.msg';
            header('Content-Type: application/vnd.ms-outlook');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: '.(string)filesize($msgPath));
            header('Cache-Control: private, no-store, max-age=0');
            header("Content-Disposition: attachment; filename=\"{$downloadName}\"");
            readfile($msgPath);
            @unlink($msgPath);
            exit;
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
            redirect('cdc_validation.php?projet_id='.$pid.'&mode=import&complete=1'.($cdcEmbedded?'&embedded=1':''));
        }else throw new RuntimeException('Action de validation inconnue.');
    }catch(Throwable $e){
        error_log('OddWorks CDC validation project '.$pid.': '.get_class($e).' '.$e->getMessage());
        $error=$e instanceof RuntimeException?$e->getMessage():'La validation du CDC n’a pas pu être enregistrée.';
    }
}

$q=$db->prepare('SELECT * FROM cdc_validations WHERE projet_id=? ORDER BY id DESC');$q->execute([$pid]);$history=$q->fetchAll();
$latestPending=null;foreach($history as $row){if($row['status']==='pending'){$latestPending=$row;break;}}

$cdcHistory=$history;$cdcHistoryProject=$pid;
if($cdcMode==='history' && ($_GET['fragment']??'')==='1'){
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__.'/includes/views/cdc_validation_history.php';exit;
}
$pageTitle='Validation du CDC — '.(string)$project['nom'];
require __DIR__.'/includes/header.php';
?>
<?php if($cdcEmbedded):?><style>.cdc-embedded .header,.cdc-embedded .footer{display:none!important}.cdc-embedded{display:block;min-height:0;height:auto}.cdc-embedded .main-content{display:block;flex:none;min-height:0;width:100%;max-width:none;margin:0;padding:1rem}.cdc-embedded .card.card-body{margin:0!important;padding:0;border:0;box-shadow:none;background:transparent}.cdc-embedded .card-body>h2{display:none}.cdc-embedded .form-row{display:grid;grid-template-columns:minmax(0,1fr);align-items:stretch!important}.cdc-embedded .form-group{min-width:0!important;margin-bottom:0}.cdc-embedded .form-control{width:100%;box-sizing:border-box}.cdc-embedded form .btn{justify-self:end;max-width:100%;white-space:normal}.cdc-embedded .card-body>p{margin:0 0 1rem;line-height:1.6}.cdc-embedded form:not(.form-row){display:grid;gap:1rem}.cdc-embedded input[type=file]{max-width:100%}</style><?php endif;?>
<?php if(!$cdcEmbedded):?><div class="page-header"><h1>Validation du cahier des charges</h1><a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid.'&view=processus&step=1&onglet=4')?>">Retour au projet</a></div><?php endif;?>
<?php if($error!==''):?><div class="alert alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if($cdcMode==='overview'): ?>
 <div class="pf-links"><button type="button" class="btn btn-primary" data-open-validation="validationRequest">Préparer une demande</button><button type="button" class="btn btn-secondary" data-open-validation="validationImport">Importer la réponse</button></div>
<?php endif;?>
<?php if($cdcMode==='overview'):?><dialog id="validationRequest" class="cdc-popup" aria-label="Préparer une demande"><button type="button" class="btn btn-secondary" data-close-validation>Fermer</button><?php endif;?>
<?php if(in_array($cdcMode,['overview','request'],true)):?>
<section class="card card-body" style="margin-bottom:1rem">
 <h2>Préparer une demande</h2>
 <p>OddWorks archive la version PDF du CDC lors de la première demande, puis génère un brouillon Outlook <code>.msg</code> avec le PDF joint. Pour le même CDC en attente, un nouveau clic régénère le brouillon avec la même référence sans créer une nouvelle archive ni une entrée d’historique.</p>
 <form method="post" class="form-row" style="align-items:end;gap:1rem"><?=csrfField()?>
  <input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="start"><input type="hidden" name="embedded" value="<?=$cdcEmbedded?'1':'0'?>">
  <div class="form-group" style="flex:1;min-width:260px"><label for="recipient">Destinataire</label><input class="form-control" id="recipient" type="email" name="recipient" required value="<?=e((string)($prepared['recipient']??($latestPending['recipient']??'francois.couraud@procomm-mmc.com')))?>"></div>
  <button type="submit" class="btn btn-primary"><i class="fas fa-envelope"></i> Générer le brouillon Outlook (.msg)</button>
 </form>
</section>
<?php endif;?>
<?php if($cdcMode==='overview'):?></dialog><dialog id="validationImport" class="cdc-popup" aria-label="Importer la réponse"><button type="button" class="btn btn-secondary" data-close-validation>Fermer</button><?php endif;?>
<?php if(in_array($cdcMode,['overview','import'],true)):?>
 <?php if(($_GET['complete']??'')==='1'):?><p class="alert alert-success">Réponse importée et archivée dans les fichiers du projet.</p><script>if(window.parent!==window)window.parent.postMessage({type:'cdc-response-imported'},window.location.origin);</script>
 <?php elseif($latestPending):?>
<section class="card card-body" style="margin-bottom:1rem">
 <h2>Importer la réponse</h2><p>Exportez le message reçu depuis Outlook en <code>.eml</code> ou <code>.msg</code>, puis importez-le ici. Le courriel doit contenir la référence <strong><?=e((string)$latestPending['reference'])?></strong>, une décision explicite « CDC Accepté » ou « CDC Refusé », et le champ « Commentaire : ».</p>
 <form method="post" enctype="multipart/form-data"><?=csrfField()?>
  <input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="import"><input type="hidden" name="validation_id" value="<?=(int)$latestPending['id']?>"><input type="hidden" name="embedded" value="<?=$cdcEmbedded?'1':'0'?>">
  <div class="form-group"><label for="replyFile">Courriel exporté (.eml ou .msg)</label><input class="form-control" id="replyFile" type="file" name="reply_file" accept=".eml,.msg,message/rfc822,application/vnd.ms-outlook" required></div>
  <button class="btn btn-primary" type="submit"><i class="fas fa-file-import"></i> Importer et archiver la réponse</button>
 </form>
</section>
 <?php else:?><p>Aucune demande de validation en attente. Préparez d’abord une demande pour ce CDC.</p><?php endif;?>
<?php endif;?>
<?php if($cdcMode==='overview'):?></dialog><?php endif;?>
<?php if(in_array($cdcMode,['overview','history'],true))require __DIR__.'/includes/views/cdc_validation_history.php';?>
<style>.cdc-popup{position:fixed;inset:0;margin:auto;width:min(92vw,850px);max-height:90vh;overflow:auto;padding:1rem;border:1px solid var(--border-default,#94a3b8);border-radius:12px;background:var(--surface-raised,#fff);color:var(--text-primary,#132839)}.cdc-popup::backdrop{background:rgba(3,13,25,.65)}</style>
<script>document.querySelectorAll('[data-open-validation]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.openValidation).showModal()));document.querySelectorAll('[data-close-validation]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));</script>
<?php if($cdcEmbedded):?><script>
(() => {
 const content=document.querySelector('.main-content');if(!content||window.parent===window)return;
 const report=()=>window.parent.postMessage({type:'cdc-dialog-size',height:Math.ceil(content.getBoundingClientRect().height)},window.location.origin);
 new ResizeObserver(report).observe(content);window.addEventListener('load',report);report();
})();
</script><?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
