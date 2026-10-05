<?php
$pageTitle = 'Validation du projet';
$activePage = 'projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/project_validation.php';
require_once __DIR__.'/includes/cahier_specs.php';
require_once __DIR__.'/includes/project_notes.php';
require_once __DIR__.'/includes/decision_dashboard.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('projets.php');
if (!estConnecte()) $_SESSION['validation_project_return'] = $id;
requerirGestionProjet($id);
header('Cache-Control: no-store');
$db = getDB();
$stmt = $db->prepare('SELECT * FROM projets WHERE id=?');
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) { http_response_code(404); exit('Projet introuvable.'); }
$motif = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $motif = is_string($_POST['motif'] ?? null) ? $_POST['motif'] : '';
    $decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';
    try {
        $snapshot = ddSnapshot(loadSpecs(getOrCreateCahierId($id)), $project, projectNotes($db,$id));
        recordProjectValidation($db,$id,(int)utilisateurCourant()['id'],$decision,$motif,$snapshot);
        setFlash('success',$decision === 'GO' ? 'Lancement du projet enregistré. Le projet passe à l’étape 4.' : 'Abandon enregistré. Le projet est archivé à l’étape 3.');
        redirect('validation_projet.php?id='.$id);
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('ProjectFlow validation : '.get_class($exception));
        $error = 'Décision non enregistrée : le projet a peut-être déjà été validé. Rechargez la page avant de réessayer.';
    }
}
require __DIR__.'/includes/header.php';
?>
<section class="r1b-card" style="max-width:900px;margin:24px auto;padding:24px">
    <h1>Validation du projet : <?=e($project['nom'])?></h1>
    <p><a href="<?=url('projet.php?id='.$id.'&view=processus&step=3')?>">Consulter le rapport complet de l’étape 3</a></p>
    <?php if ($error): ?><p class="alert alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
    <?php if (projectAwaitingValidation($project)): ?>
        <p>Le lancement valide l’étape 3 et ouvre l’étape 4. L’abandon archive le projet. Votre décision et ses motivations seront conservées dans l’historique.</p>
        <form method="post" action="<?=url('validation_projet.php?id='.$id)?>">
            <?=csrfField()?>
            <div class="form-group">
                <label for="validation-motif">Motivations / remarques de la décision</label>
                <textarea id="validation-motif" class="form-control" name="motif" rows="7" maxlength="10000" aria-describedby="validation-help"><?=e($motif)?></textarea>
                <p id="validation-help">Un motif est obligatoire en cas d’abandon. Maximum : 10 000 octets.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button type="submit" name="decision" value="GO" class="btn btn-primary" style="white-space:normal">Lancement projet : <?=e($project['nom'])?></button>
                <button type="submit" name="decision" value="NO_GO" class="btn btn-danger">Abandon et Archivage</button>
            </div>
        </form>
    <?php else: ?>
        <p role="status">Ce projet ne sollicite plus de décision à l’étape 3. <?=($project['go_decision']??'')==='GO'?'Son lancement a été validé.':((($project['go_decision']??'')==='NO_GO')?'Il a été abandonné et archivé.':'Consultez son avancement actuel.')?></p>
    <?php endif; ?>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
