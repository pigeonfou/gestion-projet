<?php
$pageTitle = 'Tâche';
$activePage = 'projets';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/task_workflow.php';
require_once __DIR__ . '/includes/task_assignment.php';
require_once __DIR__ . '/includes/ux_objects.php';
requerirConnexion();
$db = getDB();
$user = utilisateurCourant();
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$tache = null;
if ($id > 0) {
    $q = $db->prepare('SELECT * FROM taches WHERE id=?');
    $q->execute([$id]); $tache = $q->fetch();
    if (!$tache) { setFlash('error','Tâche introuvable.'); redirect('projets.php'); }
}
$projetId = $tache ? (int)$tache['projet_id'] : (int)($_POST['projet_id'] ?? $_GET['projet_id'] ?? 0);
$q = $db->prepare('SELECT p.*,u.identifiant createur FROM projets p JOIN utilisateurs u ON u.id=p.createur_id WHERE p.id=?');
$q->execute([$projetId]); $projet = $q->fetch();
if (!$projet) { setFlash('error','Projet introuvable.'); redirect('projets.php'); }
$manager = estAdmin() || (int)$projet['createur_id'] === (int)$user['id'];
$assignee = $tache && (string)($tache['assigne_a'] ?? '') === (string)$user['identifiant'];
if (!$manager && !$assignee) { setFlash('error','Accès à cette tâche refusé.'); redirect('taches.php'); }
$retour = $manager ? 'projet.php?id='.$projetId.'&view=taches' : 'taches.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    try {
        if (($_POST['action'] ?? '') === 'supprimer') {
            if (!$manager || !$tache) throw new InvalidArgumentException('Suppression non autorisée.');
            $db->prepare('DELETE FROM taches WHERE id=?')->execute([$id]);
            setFlash('success','Tâche supprimée.'); redirect($retour);
        }
        $titre = $manager ? trim((string)($_POST['titre'] ?? '')) : $tache['titre'];
        $description = $manager ? trim((string)($_POST['description'] ?? '')) : $tache['description'];
        $priorite = $manager ? (string)($_POST['priorite'] ?? 'moyenne') : $tache['priorite'];
        $assign = $manager ? trim((string)($_POST['assigne_a'] ?? '')) : (string)$tache['assigne_a'];
        $dependency = $manager ? (int)($_POST['dependance_id'] ?? 0) : (int)($tache['dependance_id'] ?? 0);
        $deadline = $manager ? (string)($_POST['date_echeance'] ?? '') : (string)($tache['date_echeance'] ?? '');
        $start = $manager ? trim((string)($_POST['date_debut'] ?? '')) : (string)($tache['date_debut'] ?? '');
        $status = (string)($_POST['statut'] ?? 'a_faire');
        $resultats = trim((string)($_POST['resultats'] ?? ''));
        $dateMetier = trim((string)($_POST['date_metier'] ?? ''));
        if ($titre === '' || strlen($titre)>2000 || strlen($description)>20000 || strlen($resultats)>20000 || !in_array($priorite,['basse','moyenne','haute','urgente'],true) || !in_array($status,['a_faire','en_cours','validation','terminee'],true)) throw new InvalidArgumentException('Données invalides ou trop longues.');
        foreach ([$deadline,$dateMetier,$start] as $date) {
            if ($date !== '') {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$d || $d->format('Y-m-d') !== $date) throw new InvalidArgumentException('Date invalide.');
            }
        }
        if ($start !== '' && $deadline !== '' && $start > $deadline) throw new InvalidArgumentException('Le début doit précéder ou être égal à l’échéance.');
        if ($assign !== '') {
            $q = $db->prepare('SELECT id FROM utilisateurs WHERE identifiant=?'); $q->execute([$assign]);
            if (!$q->fetchColumn()) throw new InvalidArgumentException('Utilisateur affecté introuvable.');
        }
        $db->beginTransaction();
        taskDependencyCheck($db,$id,$projetId,$dependency,$status);
        $values = [$titre,$description,$priorite,$status==='validation'?'en_cours':$status,$status,$assign!==''?$assign:null,$deadline?:null,$resultats,$dateMetier?:null,$dependency?:null,$start?:null];
        if ($tache) {
            $db->prepare('UPDATE taches SET titre=?,description=?,priorite=?,statut=?,kanban_status=?,assigne_a=?,date_echeance=?,resultats=?,date_metier=?,dependance_id=?,date_debut=? WHERE id=?')->execute([...$values,$id]);
        } else {
            $db->prepare('INSERT INTO taches(titre,description,priorite,statut,kanban_status,assigne_a,date_echeance,resultats,date_metier,dependance_id,date_debut,projet_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$values,$projetId]);
            $id = (int)$db->lastInsertId();
        }
        if ($manager && $tache) taskSyncAssignmentSource($db,$tache,$assign);
        $details = json_encode(['affectation'=>$assign,'statut'=>$status,'resultats'=>$resultats,'date_metier'=>$dateMetier,'dependance'=>$dependency,'date_debut'=>$start,'date_echeance'=>$deadline],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $db->prepare('INSERT INTO tache_historique(tache_id,utilisateur_id,action,details) VALUES(?,?,?,?)')->execute([$id,$user['id'],$tache?'modification':'création',$details]);
        $db->commit();
        setFlash('success','Tâche enregistrée avec affectation, résultats et historique.'); redirect($retour);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error',$e instanceof InvalidArgumentException?$e->getMessage():'Enregistrement impossible.');
        redirect('tache.php?action='.($tache?'modifier&id='.$id:'creer&projet_id='.$projetId));
    }
}
$users = $db->query('SELECT identifiant,nom_affiche,fonction FROM utilisateurs ORDER BY identifiant')->fetchAll();
$q = $db->prepare('SELECT id,titre FROM taches WHERE projet_id=? AND id<>? ORDER BY id'); $q->execute([$projetId,$id]); $deps=$q->fetchAll();
$pageTitle = $tache ? 'Modifier la tâche' : 'Nouvelle tâche';
require __DIR__.'/includes/header.php';
?>
<?php if($tache) pfTaskLinks($tache); ?>
<div class="page-header"><h1><?= e($pageTitle) ?></h1><a class="btn btn-secondary" href="<?= url($retour) ?>">Retour aux tâches</a></div>
<div class="card"><div class="card-body">
<p>Projet : <strong><?= e($projet['nom']) ?></strong></p>
<form method="POST" action="<?= url('tache.php') ?>">
<?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="projet_id" value="<?= $projetId ?>">
<?php $locked=$manager?'':'disabled'; ?>
<div class="form-group"><label for="titre">Titre</label><input id="titre" name="titre" class="form-control" value="<?= e($tache['titre']??'') ?>" required maxlength="1000" <?= $locked ?>></div>
<div class="form-group"><label for="description">Contexte et travail attendu</label><textarea id="description" name="description" class="form-control" rows="4" maxlength="10000" <?= $locked ?>><?= e($tache['description']??'') ?></textarea></div>
<div class="form-group"><label for="assigne">Affectation</label><select id="assigne" name="assigne_a" class="form-control" <?= $locked ?>><option value="">Non assigné</option><?php foreach($users as $u): ?><option value="<?= e($u['identifiant']) ?>" <?= ($tache['assigne_a']??'')===$u['identifiant']?'selected':'' ?>><?= e($u['identifiant'].' — '.$u['nom_affiche'].' — '.$u['fonction']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="priorite">Priorité</label><select id="priorite" name="priorite" class="form-control" <?= $locked ?>><?php foreach(['basse','moyenne','haute','urgente'] as $p): ?><option value="<?= $p ?>" <?= ($tache['priorite']??'moyenne')===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="dep">Tâche précédente requise</label><select id="dep" name="dependance_id" class="form-control" <?= $locked ?>><option value="">Aucune</option><?php foreach($deps as $dep): ?><option value="<?= (int)$dep['id'] ?>" <?= (int)($tache['dependance_id']??0)===(int)$dep['id']?'selected':'' ?>><?= e('#'.$dep['id'].' '.$dep['titre']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="statut">Statut</label><select id="statut" name="statut" class="form-control"><?php foreach(['a_faire'=>'À faire','en_cours'=>'En cours','validation'=>'En validation','terminee'=>'Terminée'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($tache['kanban_status']??$tache['statut']??'a_faire')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="date_debut">Début prévu (Gantt)</label><input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= e($tache['date_debut']??'') ?>" <?= $locked ?>></div>
<div class="form-group"><label for="echeance">Date d’échéance</label><input type="date" id="echeance" name="date_echeance" class="form-control" value="<?= e($tache['date_echeance']??'') ?>" <?= $locked ?>></div>
<div class="form-group"><label for="date_metier">Date métier du résultat (simulée pour la recette)</label><input type="date" id="date_metier" name="date_metier" class="form-control" value="<?= e($tache['date_metier']??'') ?>"></div>
<div class="form-group"><label for="resultats">Résultats / preuves / corrections</label><textarea id="resultats" name="resultats" class="form-control" rows="5" maxlength="10000"><?= e($tache['resultats']??'') ?></textarea></div>
<button type="submit" class="btn btn-primary">Enregistrer la tâche</button>
</form>
<?php if($tache): $q=$db->prepare('SELECT h.*,u.identifiant FROM tache_historique h JOIN utilisateurs u ON u.id=h.utilisateur_id WHERE tache_id=? ORDER BY h.id DESC');$q->execute([$id]); ?>
<h2>Historique de la tâche</h2><p>Les horodatages serveur et l’acteur connecté sont conservés séparément des dates métier simulées.</p>
<?php foreach($q->fetchAll() as $h): $historyData=json_decode($h['details'],true); ?>
<details class="pf-create"><summary><?= e($h['date_action'].' — '.$h['identifiant'].' — '.$h['action']) ?></summary>
<?php if(is_array($historyData)): ?><dl>
<?php foreach(['affectation'=>'Responsable','statut'=>'Statut','date_debut'=>'Début prévu','date_echeance'=>'Échéance','date_metier'=>'Date métier','dependance'=>'Tâche précédente','resultats'=>'Résultats / preuves / corrections'] as $key=>$label): if(!array_key_exists($key,$historyData))continue; $value=$historyData[$key]; if(!is_scalar($value)&&$value!==null)continue;
if($key==='statut')$value=['a_faire'=>'À faire','en_cours'=>'En cours','validation'=>'En validation','terminee'=>'Terminée'][$value]??$value;
if($key==='dependance')$value=$value?'#'.(int)$value:'Aucune'; ?>
<dt><strong><?=e($label)?></strong></dt><dd class="pf-technical-summary"><?=e((string)($value??''))?:'Non renseigné'?></dd>
<?php endforeach; ?></dl><?php else: ?><p class="pf-technical-summary"><?=e($h['details'])?></p><?php endif; ?>
</details><?php endforeach; ?>
<?php endif; ?>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
