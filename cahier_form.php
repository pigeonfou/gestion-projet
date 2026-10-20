<?php
$pageTitle = 'Cahier des charges';
require_once __DIR__ . '/includes/decision_dashboard.php';
$activePage = 'projets';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cahier_specs.php';
require_once __DIR__ . '/includes/r1b_steps.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$projet_id = (int)($_GET['projet_id'] ?? $_POST['projet_id'] ?? 0);
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
    $previousSpecs = loadSpecs($cahier_id);
    $specs = array_merge(emptySpecs(), $previousSpecs);

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
    if ($action === 'generate') {
        if ($specs['objectifs'] === '') $errors[] = 'Les objectifs et le contexte sont obligatoires.';
    }

    if ($errors) {
        setFlash('error', implode(' ', $errors));
    } else {
        try {
        pfTransaction($db, function () use ($db, $cahier_id, $projet_id, &$specs, $previousSpecs) {
        pfAssertSpecsRevision($cahier_id, $_POST['specs_revision'] ?? null);
        $specs = pfRemapFunctions($db, $cahier_id, $previousSpecs, $specs);
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

        });
        } catch (Throwable $e) {
            setFlash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Cahier non enregistré. Consultez le journal serveur.');
            error_log('ProjectFlow cahier: '.$e->getMessage());
            redirect('cahier_form.php?projet_id='.$projet_id);
        }
        if ($action === 'generate') {
            $text = generateCahierText($specs, $projet['nom'], loadJalons($cahier_id));
            $_SESSION['cdc_generated'] = $text;
            setFlash('success', 'Cahier des charges généré et enregistré.');
            redirect('cahier_form.php?projet_id=' . $projet_id . '&return_view=' . rawurlencode($returnView) . '&return_step=' . $returnStep . '&generated=1');
        }
        setFlash('success', 'Brouillon enregistré.');
        redirect('cahier_form.php?projet_id=' . $projet_id . '&return_view=' . rawurlencode($returnView) . '&return_step=' . $returnStep);
    }
}

$s = loadSpecs($cahier_id);
$jalonsList = loadJalons($cahier_id);
$generated = $_SESSION['cdc_generated'] ?? null;
if (isset($_GET['generated'])) {
    // keep flash; text in session
} else {
    unset($_SESSION['cdc_generated']);
    $generated = null;
}
if (isset($_GET['generated']) && !empty($_SESSION['cdc_generated'])) {
    $generated = $_SESSION['cdc_generated'];
}

$pageTitle = 'CDC — ' . $projet['nom'];
require __DIR__ . '/includes/header.php';

?>

<?php require __DIR__.'/views/specifications/form.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

