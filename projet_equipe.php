<?php
$pageTitle='Contributeurs du projet';$activePage='projets';
require __DIR__.'/includes/bootstrap.php';
requerirConnexion();$db=getDB();$user=utilisateurCourant();$pid=(int)($_GET['projet_id']??$_POST['projet_id']??0);
requerirGestionProjet($pid);projectMembersSchema($db);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfRequire();
    try {
        if (!in_array($_POST['action']??'', ['autoriser','retirer'],true)) throw new InvalidArgumentException('Action invalide.');
        projectSetContributor($db,$pid,(int)($_POST['utilisateur_id']??0),$_POST['action']==='autoriser',$user);
        setFlash('success','Autorisation de contribution enregistrée.');
    } catch(InvalidArgumentException $e) {setFlash('error',$e->getMessage());}
    redirect('projet_equipe.php?projet_id='.$pid);
}
$q=$db->prepare('SELECT nom FROM projets WHERE id=?');$q->execute([$pid]);$nom=$q->fetchColumn();
$users=$db->query('SELECT id,identifiant,nom_affiche FROM utilisateurs ORDER BY identifiant')->fetchAll();
$q=$db->prepare('SELECT m.*,u.identifiant FROM projet_contributeurs m JOIN utilisateurs u ON u.id=m.utilisateur_id WHERE projet_id=? ORDER BY u.identifiant');$q->execute([$pid]);$members=$q->fetchAll();
$q=$db->prepare('SELECT h.*,u.identifiant,a.identifiant AS acteur FROM projet_contributeurs_historique h JOIN utilisateurs u ON u.id=h.utilisateur_id JOIN utilisateurs a ON a.id=h.acteur_id WHERE h.projet_id=? ORDER BY h.id DESC');$q->execute([$pid]);$history=$q->fetchAll();
require __DIR__.'/includes/header.php';
?>
<div class="page-header"><h1>Contributeurs — <?=e($nom)?></h1><a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid)?>">Retour au projet</a></div>
<p>Les contributeurs peuvent enregistrer le cahier des charges, les documents Nextcloud et la qualité fournisseurs de ce projet. Les décisions R1b, les notes de pilotage, la gestion des tâches et des autorisations restent réservées au créateur et aux administrateurs. Les droits sur leurs propres tâches restent inchangés.</p>
<form method="post" class="card"><?=csrfField()?><input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="autoriser"><label for="contributor">Utilisateur</label><select id="contributor" name="utilisateur_id" class="form-control"><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=e($u['identifiant'].' — '.$u['nom_affiche'])?></option><?php endforeach;?></select><button class="btn btn-primary">Autoriser la contribution à ce projet</button></form>
<table class="table"><thead><tr><th>Utilisateur</th><th>Autorisation</th><th>Action</th></tr></thead><tbody><?php foreach($members as $m):?><tr><td><?=e($m['identifiant'])?></td><td><?=$m['actif']?'Active':'Retirée'?></td><td><?php if($m['actif']):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="retirer"><input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="utilisateur_id" value="<?=(int)$m['utilisateur_id']?>"><button class="btn btn-secondary">Retirer la contribution de <?=e($m['identifiant'])?></button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table>
<h2>Historique des autorisations</h2><table class="table"><thead><tr><th>Date serveur</th><th>Utilisateur</th><th>État</th><th>Acteur</th></tr></thead><tbody><?php foreach($history as $h):?><tr><td><?=e($h['date_action'])?></td><td><?=e($h['identifiant'])?></td><td><?=$h['actif']?'Autorisée':'Retirée'?></td><td><?=e($h['acteur'])?></td></tr><?php endforeach;?></tbody></table>
<?php require __DIR__.'/includes/footer.php';?>
