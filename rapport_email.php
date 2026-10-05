<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/cahier_specs.php';
require_once __DIR__.'/includes/project_notes.php';
require_once __DIR__.'/includes/report_email.php';
requerirConnexion();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
csrfRequire();
$id=(int)($_POST['projet_id']??0);
requerirAccesProjet($id,false);
$stmt=getDB()->prepare('SELECT * FROM projets WHERE id=?');$stmt->execute([$id]);$project=$stmt->fetch();
if(!$project){http_response_code(404);exit('Projet introuvable.');}
try {
    $raw=$_POST['recipients']??[];
    if(!is_array($raw)||array_filter($raw,static fn($v)=>!is_string($v)))throw new InvalidArgumentException('Destinataires invalides.');
    $recipients=reportRecipients(implode(',',$raw));
    $html=reportEmailHtml($project,loadSpecs(getOrCreateCahierId($id)),projectNotes(getDB(),$id));
    $draft=reportEmailDraft('OddWorks — '.$project['nom'].' — Étape 3 GO / NO GO',$recipients,$html);
} catch(InvalidArgumentException $error) {
    setFlash('error',$error->getMessage());redirect('projet.php?id='.$id.'&view=processus&step=3');
}
if(($_POST['format']??'')==='preview'){header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: no-store');echo $html;exit;}
header('Content-Type: message/rfc822');
header('Content-Disposition: attachment; filename="OddWorks-rapport-etape3-'.$id.'.eml"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $draft;
