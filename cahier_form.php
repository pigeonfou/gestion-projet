<?php
$pageTitle = 'Cahier des charges';
require_once __DIR__ . '/includes/decision_dashboard.php';
$activePage = 'projets';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cahier_specs.php';
require_once __DIR__ . '/includes/cdc_pdf.php';
require_once __DIR__ . '/includes/r1b_steps.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$projet_id = (int)($_GET['projet_id'] ?? $_POST['projet_id'] ?? 0);
$cdcEmbedded = (($_GET['embedded'] ?? $_POST['embedded'] ?? '') === '1');
if ($projet_id <= 0) redirect('projets.php');

// Conserver le contexte d'origine pour que « Retour au projet »
// ramène à la vue/étape qui a ouvert le CDC.
$returnView = $_GET['return_view'] ?? $_POST['return_view'] ?? 'processus';
$returnStep = (int)($_GET['return_step'] ?? $_POST['return_step'] ?? 1);
if (!in_array($returnView, ['dashboard', 'processus', 'taches', 'documents'], true)) {
    $returnView = 'processus';
}
if ($returnView === 'processus') {
    $returnStep = r1bClampStep($returnStep);
}
$returnUrl = 'projet.php?id=' . $projet_id . '&view=' . rawurlencode($returnView);
if ($returnView === 'processus') {
    $returnUrl .= '&step=' . $returnStep;
}

$stmt = $db->prepare('SELECT * FROM projets WHERE id = ?');
$stmt->execute([$projet_id]);
$projet = $stmt->fetch();
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}
requerirAccesProjet($projet_id);

$stmt = $db->prepare('SELECT * FROM cahiers WHERE projet_id = ?');
$stmt->execute([$projet_id]);
$cahier = $stmt->fetch();
if (!$cahier) {
    $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$projet_id]);
    $stmt->execute([$projet_id]);
    $cahier = $stmt->fetch();
}
$cahier_id = (int)$cahier['id'];

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['form_action'] ?? 'save';
    $specs = loadSpecs($cahier_id);

    // Text fields
    $textKeys = [
        'objectifs','resultats_attendus','cas_usage','profils_utilisateurs',
        'delais','livrables_attendus',
    ];
    foreach ($textKeys as $k) {
        $specs[$k] = trim($_POST[$k] ?? '');
    }
    // Spécifications fonctionnelles (liste dynamique)
    $specs['fonctions'] = parseFonctionsFromPost($_POST);

    // Conserver les S.T. et composants déjà enregistrés dans le CDC :
    // l'édition des S.F. ne doit pas effacer les données de l'étape 2/4.
    $previousSpecs = loadSpecs($cahier_id);
    $specs['specs_techniques'] = is_array($previousSpecs['specs_techniques'] ?? null)
        ? $previousSpecs['specs_techniques']
        : [];
    $specs['composants_st'] = is_array($previousSpecs['composants_st'] ?? null)
        ? $previousSpecs['composants_st']
        : [];

    // Required validation on "generate"
    $errors = [];
    try { $specs['decision_cibles'] = ddTargets($_POST); }
    catch (InvalidArgumentException $e) { $errors[]=$e->getMessage(); }
    if (in_array($action, ['generate', 'validate'], true)) {
        if ($specs['objectifs'] === '') $errors[] = 'Les objectifs et le contexte sont obligatoires.';
    }

    $isCdcAjax=(($_POST['cdc_ajax']??'')==='1');
    $revision=(string)($_POST['cdc_revision']??'');
    if ($revision!=='' && !hash_equals(cdcEditorRevision($previousSpecs,loadJalons($cahier_id)),$revision)) {
        $errors[]='Le CDC a été modifié ailleurs. Rechargez la page avant de réenregistrer vos modifications.';
    }
    if ($errors && $isCdcAjax) {
        http_response_code(422);header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>implode(' ',$errors)],JSON_UNESCAPED_UNICODE);exit;
    }
    if ($errors) {
        setFlash('error', implode(' ', $errors));
    } else {
        saveSpecs($cahier_id, $specs);

        // Les S.F. ne sont pas des tâches.
        // Les S.T. existantes restent synchronisées comme tâches.
        $existingTechniques = $specs['specs_techniques'] ?? [];
        syncTasksFromStructuredSpecs(
            $projet_id,
            [],
            is_array($existingTechniques) ? $existingTechniques : []
        );

        saveJalonsFromPost($cahier_id, $_POST);
        // Mirror some fields into classic cahier columns
        $db->prepare('UPDATE cahiers SET objectifs=?, contexte=?, contraintes=?, date_maj=CURRENT_TIMESTAMP WHERE id=?')
           ->execute([$specs['objectifs'], $specs['cas_usage'], $specs['cas_usage'], $cahier_id]);

        if ($isCdcAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'=>true,'message'=>'CDC enregistré.',
                'revision'=>cdcEditorRevision(loadSpecs($cahier_id),loadJalons($cahier_id)),
                'download_url'=>url('cdc_pdf_download.php?projet_id='.$projet_id),
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
        }
        if ($action === 'generate') {
            // L’endpoint réutilise le PDF correspondant au contenu actuel ou le crée à la demande.
            $downloadPath = 'cdc_pdf_download.php?projet_id=' . $projet_id;
            if ($cdcEmbedded) $downloadPath .= '&embedded=1';
            redirect($downloadPath);
        }
        if ($action === 'validate') {
            redirect('cdc_validation.php?projet_id=' . $projet_id . ($cdcEmbedded ? '&embedded=1' : ''));
        }
        setFlash('success', 'Brouillon enregistré.');
        $returnPath = 'cahier_form.php?projet_id=' . $projet_id . '&return_view=' . rawurlencode($returnView) . '&return_step=' . $returnStep;
        if ($cdcEmbedded) $returnPath .= '&embedded=1';
        redirect($returnPath);
    }
}

$s = loadSpecs($cahier_id);
$jalonsList = loadJalons($cahier_id);
$pdfAlreadyGenerated = cdcGeneratedPdfExists($projet_id, $projet['nom'], $s, $jalonsList);

$pageTitle = 'CDC — ' . $projet['nom'];
require __DIR__ . '/includes/header.php';


?>
<div class="page-header"><div><h1>Cahier des charges</h1><p><?=e($projet['nom'])?></p></div><?php if(!$cdcEmbedded):?><a class="btn btn-secondary" href="<?=url($returnUrl)?>">Retour au projet</a><?php endif;?></div>
<?php $cdcInline=false; require __DIR__.'/includes/views/cdc_editor.php'; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
