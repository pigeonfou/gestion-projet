<?php
$pageTitle='Système de management'; $activePage='management';
require_once __DIR__.'/includes/bootstrap.php'; requerirConnexion(); runSchemaMigrations();
$db=getDB();
$tabs=['processus'=>'Processus','documents'=>'Documents maîtrisés','risques'=>'Risques & opportunités','actions'=>'Actions','nc'=>'Non-conformités','enregistrements'=>'Enregistrements'];
$tab=$_GET['tab']??'processus'; if(!isset($tabs[$tab]))$tab='processus';
if($_SERVER['REQUEST_METHOD']==='POST'){ csrfRequire(); $a=$_POST['action']??'';
 try{
  requerirAdmin();
  if($a==='processus'){$db->prepare("INSERT INTO processus(code,nom,pilote,description) VALUES(?,?,?,?)")->execute([trim($_POST['code']),trim($_POST['nom']),trim($_POST['pilote']),trim($_POST['description'])]);}
  elseif($a==='document'){if(!in_array($_POST['statut']??'', ['brouillon','en_revue'],true))throw new InvalidArgumentException('Créer un brouillon puis approuver dans le suivi avec preuve.');$db->prepare("INSERT INTO documents_controles(reference,titre,type,processus_id,projet_id,statut,version,auteur,date_creation,description) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([trim($_POST['reference']),trim($_POST['titre']),trim($_POST['type']),($_POST['processus_id']?:null),($_POST['projet_id']?:null),trim($_POST['statut']),trim($_POST['version']),trim($_POST['auteur']),date('Y-m-d'),trim($_POST['description'])]);}
  elseif($a==='risque'){$p=(int)$_POST['probabilite'];$i=(int)$_POST['impact'];if($p<1||$p>5||$i<1||$i>5)throw new InvalidArgumentException('Probabilité et impact : valeurs de1 à5.');$db->prepare("INSERT INTO risques_opportunites(processus_id,projet_id,type,description,cause,consequence,probabilite,impact,criticite,action_prevue,responsable,echeance) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")->execute([($_POST['processus_id']?:null),($_POST['projet_id']?:null),$_POST['type'],trim($_POST['description']),trim($_POST['cause']),trim($_POST['consequence']),$p,$i,$p*$i,trim($_POST['action_prevue']),trim($_POST['responsable']),($_POST['echeance']?:null)]);}
  elseif($a==='actionq'){$db->prepare("INSERT INTO actions_qualite(processus_id,projet_id,type,origine,description,responsable,echeance) VALUES(?,?,?,?,?,?,?)")->execute([($_POST['processus_id']?:null),($_POST['projet_id']?:null),trim($_POST['type']),trim($_POST['origine']),trim($_POST['description']),trim($_POST['responsable']),($_POST['echeance']?:null)]);}
  elseif($a==='nc'){$ref='NC-'.date('Ymd-His');$db->prepare("INSERT INTO non_conformites(reference,processus_id,projet_id,description,detection,disposition,responsable,echeance) VALUES(?,?,?,?,?,?,?,?)")->execute([$ref,($_POST['processus_id']?:null),($_POST['projet_id']?:null),trim($_POST['description']),trim($_POST['detection']),trim($_POST['disposition']),trim($_POST['responsable']),($_POST['echeance']?:null)]);}
  elseif($a==='record'){
   $date=(string)($_POST['date_metier']??'');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
   if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Date métier invalide.');
   $proof=(int)($_POST['document_id']??0)?:null;$project=(int)($_POST['projet_id']??0)?:null;
   if($proof){$q=$db->prepare('SELECT projet_id FROM documents_controles WHERE id=?');$q->execute([$proof]);$d=$q->fetch();if(!$d||!$project||(int)$d['projet_id']!==$project)throw new InvalidArgumentException('Le document maîtrisé doit appartenir au projet de l’enregistrement.');}
   $notes=trim($_POST['notes'])."\nEnregistré par acteur #".(int)utilisateurCourant()['id'].' le '.date('Y-m-d H:i:s').'.';
   $db->prepare("INSERT INTO enregistrements(type,reference,processus_id,projet_id,document_id,date_evenement,resultat,statut,notes) VALUES(?,?,?,?,?,?,?,?,?)")->execute([trim($_POST['type']),trim($_POST['reference']),($_POST['processus_id']?:null),$project,$proof,$date.' 00:00:00',trim($_POST['resultat']),trim($_POST['statut']),$notes]);
  }
  setFlash('success','Enregistrement créé.'); redirect('management.php?tab='.$tab);
 }catch(Throwable $e){setFlash('error','Erreur : '.$e->getMessage());}
}
$processus=$db->query("SELECT * FROM processus ORDER BY code")->fetchAll();
$projets=$db->query("SELECT id,nom FROM projets ORDER BY nom")->fetchAll();
$controlled=$db->query("SELECT id,reference FROM documents_controles ORDER BY reference")->fetchAll();
$counts=[
 'processus'=>(int)$db->query("SELECT COUNT(*) FROM processus WHERE actif=1")->fetchColumn(),
 'documents'=>(int)$db->query("SELECT COUNT(*) FROM documents_controles")->fetchColumn(),
 'risques'=>(int)$db->query("SELECT COUNT(*) FROM risques_opportunites WHERE statut NOT IN ('clos','ferme')")->fetchColumn(),
 'actions'=>(int)$db->query("SELECT COUNT(*) FROM actions_qualite WHERE statut NOT IN ('close','cloturee')")->fetchColumn(),
 'nc'=>(int)$db->query("SELECT COUNT(*) FROM non_conformites WHERE statut!='cloturee'")->fetchColumn()
];
require __DIR__.'/includes/header.php';
require __DIR__.'/views/management/index.php';
