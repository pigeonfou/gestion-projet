<?php
$pageTitle = 'Projet';
$activePage = 'projets';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/estimation_fields.php';
require_once __DIR__ . '/includes/task_workflow.php';
require_once __DIR__ . '/includes/task_filters.php';
require_once __DIR__ . '/includes/cahier_specs.php';
require_once __DIR__ . '/includes/stock_link.php';
require_once __DIR__ . '/includes/r1b_steps.php';
require_once __DIR__ . '/includes/r1b_decisions.php';
require_once __DIR__ . '/includes/decision_history.php';
require_once __DIR__ . '/includes/project_notes.php';
require_once __DIR__ . '/includes/decision_dashboard.php';
requerirConnexion();
seedSettingsIfEmpty();
runSchemaMigrations();

$db = getDB();
$user = utilisateurCourant();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? $_POST['projet_id'] ?? 0);
$view = $_GET['view'] ?? 'dashboard'; // dashboard | processus | historique | taches | documents
$stepGet = array_key_exists('step', $_GET) ? (int)$_GET['step'] : null;

if ($id <= 0) {
    redirect('projets.php');
}

$stmt = $db->prepare("SELECT p.*, u.identifiant AS createur FROM projets p JOIN utilisateurs u ON u.id = p.createur_id WHERE p.id = ?");
$stmt->execute([$id]);
$projet = $stmt->fetch();
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}

initProjectNotes($db,$id);

$progressStep = r1bClampStep((int)($projet['current_step'] ?? 1));
$validatedSteps = json_decode((string)($projet['validated_steps'] ?? '[]'), true);
if (!is_array($validatedSteps)) $validatedSteps = [];
$validatedSteps = array_values(array_unique(array_map('intval', $validatedSteps)));
// Compatibilité des projets existants : les étapes déjà franchies restent validées.
if (empty($validatedSteps) && $progressStep > 1) {
    $validatedSteps = range(1, $progressStep - 1);
}
$currentStep = $progressStep;
if ($stepGet !== null && $stepGet >= r1bMinStep() && $stepGet <= r1bMaxStep()) {
    $currentStep = $stepGet;
}
$phase = r1bPhaseFromStep($currentStep);
$steps = r1bSteps();

// Contexte de retour utilisé par le CDC : revenir exactement à la vue/étape qui l'a ouvert.
$cdcReturnQuery = '&return_view=' . rawurlencode($view);
if ($view === 'processus') {
    $cdcReturnQuery .= '&return_step=' . $currentStep;
}

// ——— Actions POST ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? '';
    if ($action !== 'update_task_status') {
        requerirGestionProjet($id);
    }
    if ($action === 'manage_note' || $action === 'save_notes') {
        try {
            if ($action === 'manage_note') {
                changeProjectNote($db,$id,$currentStep,(string)($_POST['note_action']??''),(string)($_POST['note_content']??''),(int)($_POST['note_id']??0),(int)($_POST['note_revision']??0));
            } elseif (trim((string)($_POST['step_notes']??''))!=='') {
                changeProjectNote($db,$id,$currentStep,'add',(string)$_POST['step_notes']);
            }
            if ($currentStep === 1 && !empty($_POST['save_cadrage_with_notes'])) {
                $dest=$_POST['cadrage_destination']??null;
                if(!in_array($dest,['interne','externe'],true))$dest=null;
                $db->prepare('UPDATE projets SET cadrage_commerciale=?,cadrage_technique=?,cadrage_destination=? WHERE id=?')->execute([!empty($_POST['cadrage_commerciale'])?1:0,!empty($_POST['cadrage_technique'])?1:0,$dest,$id]);
            }
            setFlash('success','Notes et informations enregistrées.');
        } catch(Throwable $e) { setFlash('error',$e->getMessage()); }
        redirect('projet.php?id='.$id.'&view=processus&step='.$currentStep);
    }
    if ($action === 'set_step') {
        // La navigation ne valide ni ne modifie l'avancement du projet.
        $n = r1bClampStep((int)($_POST['step'] ?? $progressStep));
        redirect('projet.php?id=' . $id . '&view=processus&step=' . $n);
    }
    if ($action === 'save_cadrage') {
        $comm = !empty($_POST['cadrage_commerciale']) ? 1 : 0;
        $tech = !empty($_POST['cadrage_technique']) ? 1 : 0;
        $dest = $_POST['cadrage_destination'] ?? '';
        if (!in_array($dest, ['interne', 'externe'], true)) $dest = null;
        $db->prepare('UPDATE projets SET cadrage_commerciale = ?, cadrage_technique = ?, cadrage_destination = ? WHERE id = ?')
           ->execute([$comm, $tech, $dest, $id]);
        setFlash('success', 'Note de cadrage enregistrée.');
        redirect('projet.php?id=' . $id . '&view=processus&step=1');
    }
    if ($action === 'save_specs_techniques') {
        try {
            $cid = getOrCreateCahierId($id);
            $techniques = parseSpecsTechniquesFromPost($_POST);
            saveSpecsTechniques($cid, $techniques);

            // À l'étape 2, toutes les S.T. (Matériel, Composant, Prestataire, Logiciel, 3D, PCB)
            // sont synchronisées comme tâches.
            $fonctions = loadSpecs($cid)['fonctions'] ?? [];
            syncTasksFromStructuredSpecs(
                $id,
                is_array($fonctions) ? $fonctions : [],
                $techniques
            );

            $n = count($techniques);
            setFlash('success', $n > 0
                ? ("Spécifications techniques enregistrées ($n ligne(s)).")
                : 'Aucune ligne avec description : rien à enregistrer. Saisissez une description pour chaque S.T.');
        } catch (Throwable $e) {
            setFlash('error', 'Erreur d\'enregistrement des S.T. : ' . $e->getMessage());
        }
        redirect('projet.php?id=' . $id . '&view=processus&step=2');
    }
    if ($action === 'save_composants_st') {
        try {
            $cid = getOrCreateCahierId($id);
            $composants = parseComposantsStFromPost($_POST);
            saveComposantsSt($cid, $composants);
            $techs = loadSpecs($cid)['specs_techniques'] ?? [];
            syncTasksFromComposants($id, $composants, is_array($techs) ? $techs : []);
            syncStockUsagesFromComposants($id, $composants);
            $n = 0;
            foreach ($composants as $items) { $n += count($items); }
            setFlash('success', $n > 0
                ? ("Composants / affectations enregistrés ($n ligne(s)). Tâches Logiciel/3D/PCB synchronisées.")
                : 'Aucune ligne renseignée à enregistrer.');
        } catch (Throwable $e) {
            setFlash('error', 'Erreur d\'enregistrement : ' . $e->getMessage());
        }
        redirect('projet.php?id=' . $id . '&view=processus&step=4');
    }
    if ($action === 'create_purchase_tasks') {
        try {
            ensureTachesExtendedColumns();
            $selected = $_POST['purchase_create'] ?? [];
            $assignees = $_POST['purchase_assignee'] ?? [];
            $payloads = $_POST['purchase_payload'] ?? [];
            if (!is_array($selected)) $selected = [];
            if (!is_array($assignees)) $assignees = [];
            if (!is_array($payloads)) $payloads = [];

            $validUsers = $db->query('SELECT identifiant FROM utilisateurs')->fetchAll(PDO::FETCH_COLUMN);
            $validUsers = array_flip(array_map('strval', $validUsers));
            $ins = $db->prepare('INSERT INTO taches (projet_id, titre, description, priorite, statut, assigne_a, source_key, kanban_status) VALUES (?,?,?,?,?,?,?,?)');
            $upd = $db->prepare('UPDATE taches SET titre=?, description=?, assigne_a=? WHERE projet_id=? AND source_key=?');
            $find = $db->prepare('SELECT id FROM taches WHERE projet_id=? AND source_key=? LIMIT 1');
            $created = 0; $updated = 0;

            foreach ($selected as $key) {
                $key = (string)$key;
                if (!isset($payloads[$key])) continue;
                $p = json_decode(base64_decode((string)$payloads[$key], true) ?: '', true);
                if (!is_array($p)) continue;
                $stId = trim((string)($p['st_id'] ?? ''));
                $itemId = trim((string)($p['item_id'] ?? ''));
                if ($stId === '' || $itemId === '') continue;
                $sourceKey = 'achat:' . $id . ':' . $stId . ':' . $itemId;
                $assignee = trim((string)($assignees[$key] ?? ''));
                if ($assignee !== '' && !isset($validUsers[$assignee])) $assignee = '';

                $designation = trim((string)($p['designation'] ?? ''));
                $reference = trim((string)($p['reference'] ?? ''));
                $fournisseur = trim((string)($p['fournisseur'] ?? ''));
                $qty = (float)($p['quantite'] ?? 0);
                $cu = (float)($p['cout_unitaire'] ?? 0);
                $tax = (($p['cout_unitaire_taxe'] ?? 'HT') === 'TTC') ? 'TTC' : 'HT';
                $total = round($qty * $cu, 2);
                $titre = '[ACHAT ' . $stId . '/' . $itemId . '] ' . ($designation !== '' ? $designation : ($reference !== '' ? $reference : 'Composant / matériel'));
                $description = implode("\n", array_filter([
                    'S.T. : ' . $stId,
                    'Ligne : ' . $itemId,
                    $reference !== '' ? 'Référence : ' . $reference : '',
                    $fournisseur !== '' ? 'Fournisseur : ' . $fournisseur : '',
                    'Quantité : ' . rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'),
                    'Coût unitaire : ' . number_format($cu, 2, ',', ' ') . ' € ' . $tax,
                    'Coût total estimé : ' . number_format($total, 2, ',', ' ') . ' € ' . $tax,
                    isset($p['delai_jours']) ? 'Délai : ' . $p['delai_jours'] . ' j' : '',
                ]));

                $find->execute([$id, $sourceKey]);
                if ($find->fetchColumn()) {
                    $upd->execute([$titre, $description, $assignee !== '' ? $assignee : null, $id, $sourceKey]);
                    $updated++;
                } else {
                    $ins->execute([$id, $titre, $description, 'moyenne', 'a_faire', $assignee !== '' ? $assignee : null, $sourceKey, 'a_faire']);
                    $created++;
                }
            }
            setFlash('success', "Tâches d'achat : $created créée(s), $updated mise(s) à jour.");
        } catch (Throwable $e) {
            setFlash('error', "Erreur lors de la génération des tâches d'achat : " . $e->getMessage());
        }
        redirect('projet.php?id=' . $id . '&view=processus&step=5');
    }
    if (($_POST['action'] ?? '') === 'update_task_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfRequire();
        $aliases = ['todo'=>'a_faire','in_progress'=>'en_cours','done'=>'terminee'];
        $status = (string)($_POST['status'] ?? 'a_faire');
        $status = $aliases[$status] ?? $status;
        try {
            taskSetStatus($db, (int)($_POST['task_id'] ?? 0), $status, $user, $id);
            setFlash('success', 'Statut de la tâche mis à jour.');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
        } catch (Throwable $e) {
            setFlash('error', 'Statut non enregistré.');
        }
        redirect('projet.php?id=' . $id . '&view=taches&'.taskFilterQuery(taskFilterInput($_GET)));
    }
    if ($action === 'decide') {
        $decision = $_POST['decision'] ?? '';
        $motif = trim((string)($_POST['motif'] ?? $projet['step_notes'] ?? ''));
        if (!in_array($decision, r1bAllowedDecisions($currentStep), true) || $currentStep > $progressStep) {
            setFlash('error', 'Décision invalide pour cette étape ou étape non encore atteinte.');
            redirect('projet.php?id=' . $id . '&view=processus&step=' . $progressStep);
        }
        if (strlen($motif) > 10000 || (in_array($decision, ['NO_GO', 'NON_CONFORME', 'REFUSE'], true) && $motif === '')) {
            setFlash('error', 'Un motif de refus est obligatoire (10 000 octets maximum).');
            redirect('projet.php?id=' . $id . '&view=processus&step=' . $currentStep);
        }
        try {
        $db->beginTransaction();
        if (in_array($decision, ['GO', 'NO_GO', 'CONFORME', 'NON_CONFORME', 'DONE', 'REFUSE'], true)) {
            if ($decision === 'NO_GO') {
                // Archivage étape 3 (abandon) — reste à l'étape 3, ne passe PAS à l'étape 8
                $db->prepare('UPDATE projets SET go_decision = ?, current_step = 3, status = ? WHERE id = ?')
                   ->execute(['NO_GO', 'archive', $id]);
                setFlash('success', 'NO GO enregistré — projet archivé comme abandonné (étape 3).');
            } elseif ($decision === 'GO') {
                $validated = $validatedSteps; $validated[] = 3;
                $validated = array_values(array_unique(array_map('intval', $validated))); sort($validated);
                $db->prepare('UPDATE projets SET go_decision = ?, current_step = ?, status = ?, validated_steps = ? WHERE id = ?')
                   ->execute(['GO', max($progressStep, 4), 'actif', json_encode($validated), $id]);
                setFlash('success', 'Décision enregistrée : GO');
            } elseif ($decision === 'CONFORME') {
                // Le bouton Conforme est l'acte explicite de validation des étapes 7/8.
                $validated = $validatedSteps; $validated[] = $currentStep;
                $validated = array_values(array_unique(array_map('intval', $validated))); sort($validated);
                $validatedJson = json_encode($validated);
                $next = max($progressStep, r1bClampStep($currentStep + 1));
                if ($currentStep >= 8 || $next === 9) {
                    $db->prepare('UPDATE projets SET current_step = 9, status = ?, validated_steps = ? WHERE id = ?')
                       ->execute(['termine', $validatedJson, $id]);
                    setFlash('success', 'Conforme — projet archivé en validé/vente (étape 9).');
                } else {
                    $db->prepare('UPDATE projets SET current_step = ?, status = ?, validated_steps = ? WHERE id = ?')
                       ->execute([$next, 'actif', $validatedJson, $id]);
                    setFlash('success', 'Décision enregistrée : CONFORME');
                }
            } elseif ($decision === 'DONE') {
                $validated = $validatedSteps;
                $validated[] = $currentStep;
                $validated = array_values(array_unique(array_map('intval', $validated)));
                sort($validated);
                $validatedJson = json_encode($validated);
                // L'étape 5 (Achats) a été insérée après l'étape 4.
                // Elle doit être réellement parcourue, y compris pour un projet ancien
                // dont current_step avait déjà été décalé vers 6+ par la migration.
                $next = ($currentStep === 4)
                    ? 5
                    : max($progressStep, r1bClampStep($currentStep + 1));
                if ($next === 9) {
                    $db->prepare('UPDATE projets SET current_step = 9, status = ?, validated_steps = ? WHERE id = ?')
                       ->execute(['termine', $validatedJson, $id]);
                    setFlash('success', 'Étape validée — projet archivé en validé/vente (étape 9).');
                } else {
                    $db->prepare('UPDATE projets SET current_step = ?, validated_steps = ? WHERE id = ?')->execute([$next, $validatedJson, $id]);
                    setFlash('success', 'Étape validée.');
                }
            } elseif ($decision === 'NON_CONFORME' || $decision === 'REFUSE') {
                $refus = r1bRefusalState($currentStep, $validatedSteps);
                $db->prepare("UPDATE projets SET current_step = ?, validated_steps = ?, status = 'actif' WHERE id = ?")
                    ->execute([$refus['step'], json_encode($refus['validated']), $id]);
                setFlash('success', 'Refus enregistré. Retour à l’étape ' . $refus['step'] . ' ; les validations à partir de cette étape sont à revoir.');
            }
        }
        $db->prepare('INSERT INTO projet_decisions(projet_id,etape,decision,motif,utilisateur_id) VALUES(?,?,?,?,?)')
            ->execute([$id,$currentStep,$decision,$motif,$user['id']]);
        $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('ProjectFlow décision R1b : ' . get_class($e));
            setFlash('error', 'Décision non enregistrée. Réessayez après vérification du journal serveur.');
            redirect('projet.php?id=' . $id . '&view=processus&step=' . $currentStep);
        }
        // Après une décision/validation, afficher l'étape réellement atteinte.
        // Sans cela, le paramètre GET "step" du formulaire (ex. step=4)
        // reste actif pendant cette requête et réaffiche l'ancienne étape.
        if (in_array($decision, ['GO', 'CONFORME', 'DONE', 'NON_CONFORME', 'REFUSE'], true)) {
            $stmtNext = $db->prepare('SELECT current_step FROM projets WHERE id = ?');
            $stmtNext->execute([$id]);
            $redirectStep = r1bClampStep((int)$stmtNext->fetchColumn());
            redirect('projet.php?id=' . $id . '&view=processus&step=' . $redirectStep);
        }
        redirect('projet.php?id=' . $id . '&view=processus');
    }
}

// Reload after possible updates
$stmt->execute([$id]);
$projet = $stmt->fetch();
$progressStep = r1bClampStep((int)($projet['current_step'] ?? 1));
$validatedSteps = json_decode((string)($projet['validated_steps'] ?? '[]'), true);
if (!is_array($validatedSteps)) $validatedSteps = [];
$validatedSteps = array_values(array_unique(array_map('intval', $validatedSteps)));
if (empty($validatedSteps) && $progressStep > 1) $validatedSteps = range(1, $progressStep - 1);
$currentStep = $progressStep;
if ($stepGet !== null && $stepGet >= r1bMinStep() && $stepGet <= r1bMaxStep()) {
    $currentStep = $stepGet;
}
$phase = r1bPhaseFromStep($currentStep);

// Données selon la vue
$cahier_id = getOrCreateCahierId($id);
$stmt = $db->prepare('SELECT * FROM cahiers WHERE id = ?');
$stmt->execute([$cahier_id]);
$cahier = $stmt->fetch() ?: ['id' => $cahier_id];
$specs = loadSpecs($cahier_id);

$taches = [];
$tachesAll = [];
$documents = [];
$jalonsProjet = [];
$utilisateursListe = [];
$nbJalons = 0;
$nbDocs = 0;
$tachesOuvertes = 0;
$tachesTerminees = 0;
$ncEnabled = false;

$needTasks = in_array($view, ['dashboard', 'taches', 'processus'], true);
$needDocs = in_array($view, ['dashboard', 'documents'], true);
$needJalons = $view === 'dashboard';
$needUsers = $view === 'processus' && in_array($currentStep, [3, 4, 5], true);

if ($needTasks) {
    ensureTachesExtendedColumns();
    $tachesAll = $db->prepare('SELECT * FROM taches WHERE projet_id = ? ORDER BY id DESC');
    $tachesAll->execute([$id]);
    $tachesAll = $tachesAll->fetchAll();
    $taches = $tachesAll;
    if (!estAdmin()) {
        $ident = $user['identifiant'] ?? '';
        $taches = array_values(array_filter($taches, static function ($t) use ($ident) {
            return isset($t['assigne_a']) && (string)$t['assigne_a'] === (string)$ident;
        }));
    }
    foreach ($tachesAll as $t) {
        $st = $t['statut'] ?? 'a_faire';
        if (in_array($st, ['terminee', 'done', 'terminé'], true)) {
            $tachesTerminees++;
        } else {
            $tachesOuvertes++;
        }
    }
}
if ($needDocs) {
    $documents = $db->prepare('SELECT * FROM documents WHERE projet_id = ? ORDER BY date_upload DESC');
    $documents->execute([$id]);
    $documents = $documents->fetchAll();
    $nbDocs = count($documents);
    $ncEnabled = getSetting('nextcloud_enabled', '0') === '1';
}
if ($needJalons) {
    $jalonsProjet = loadJalons($cahier_id);
    $nbJalons = count($jalonsProjet);
}
if ($needUsers) {
    $utilisateursListe = $db->query('SELECT id, identifiant, nom_affiche, fonction, competences FROM utilisateurs ORDER BY identifiant')->fetchAll(PDO::FETCH_ASSOC);
}

$decisionUsers = $utilisateursListe;
$lastDecision = null;
if ($view === 'processus' && $currentStep === 3) {
    $dq=$db->prepare('SELECT d.*, u.identifiant FROM projet_decisions d JOIN utilisateurs u ON u.id=d.utilisateur_id WHERE d.projet_id=? AND d.etape=3 ORDER BY d.id DESC LIMIT 1');
    $dq->execute([$id]); $lastDecision=$dq->fetch() ?: null;
}

$pct = (int)round((count($validatedSteps) / r1bMaxStep()) * 100);
$nbDocs = count($documents);
$validationsPending = ($currentStep === 3 && empty($projet['go_decision'])) || in_array($currentStep, [7, 8], true) ? 1 : 0;

// Embedded history stays inside its own browsing context so filtering never
// reloads or discards an unfinished step form. Authentication above still applies.
$historyEmbedded = $view === 'historique' && ($_GET['embedded'] ?? '') === '1';
if ($historyEmbedded) {
    ?>
    <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Historique des décisions</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"><link rel="stylesheet" href="<?= url('assets/css/projet-r1b.css?v=dimensions-3') ?>"><link rel="stylesheet" href="<?= url('assets/css/decision-history.css?v=history-9') ?>"></head><body class="history-embedded"><main>
    <?php require __DIR__ . '/includes/views/decision_history.php'; ?>
    </main></body></html>
    <?php
    exit;
}
$pageTitle = $projet['nom'];
$useAppShell = true;
require __DIR__ . '/includes/header.php';

function stepClass(int $n, int $displayed, array $validated): string
{
    if (in_array($n, $validated, true)) return 'r1b-step done';
    if ($n === $displayed) return 'r1b-step active';
    return 'r1b-step';
}

?>

<link rel="stylesheet" href="<?= url('assets/css/decision-history.css?v=history-9') ?>">
<link rel="stylesheet" href="<?= url('assets/css/decision-dashboard.css?v=4') ?>">
<div class="r1b-layout">
  <!-- Sidebar projet -->
  <aside class="r1b-sidebar">
    <nav class="r1b-nav">
      <a href="<?= url('projet.php?id=' . $id . '&view=dashboard') ?>" class="<?= $view === 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-th-large"></i> <?= e($projet['nom']) ?>
      </a>
      <a href="<?= url('projet.php?id=' . $id . '&view=processus') ?>" class="<?= $view === 'processus' ? 'active' : '' ?>">
        <i class="fas fa-route"></i> Processus R1b
      </a>
      <a href="<?= url('projet.php?id=' . $id . '&view=historique&step=' . $currentStep) ?>" class="<?= $view === 'historique' ? 'active' : '' ?>"><i class="fas fa-history"></i> Historique des décisions</a>
      <a href="<?= url('projet.php?id=' . $id . '&view=taches') ?>" class="<?= $view === 'taches' ? 'active' : '' ?>">
        <i class="fas fa-tasks"></i> Tâches
      </a>
      <a href="<?= url('documents_externes.php?projet_id=' . $id) ?>" class="<?= $view === 'documents' ? 'active' : '' ?>">
        <i class="fas fa-folder-open"></i> Documents
      </a>
      <a href="<?= url('qualite_projet.php?projet_id=' . $id) ?>"><i class="fas fa-check-circle"></i> Qualité fournisseurs</a>
      <a href="<?= url('cahier_form.php?projet_id=' . $id . $cdcReturnQuery) ?>">
        <i class="fas fa-file-alt"></i> Cahier des charges
      </a>
      <a href="<?= url('cdc_test_form.php?projet_id=' . $id) ?>"><i class="fas fa-flask"></i> CDC-Test-Form</a>
      <?php if(projectCanManage($db,$id,$user)):?><a href="<?=url('projet_equipe.php?projet_id='.$id)?>">Contributeurs du projet</a><?php endif;?>
    </nav>
    <div class="r1b-sidebar-foot">
      <a href="<?= url('projets.php') ?>"><i class="fas fa-arrow-left"></i> Tous les projets</a>
    </div>
  </aside>

  <div class="r1b-main <?= $view === 'processus' ? 'r1b-process-main' : '' ?>">

    <?php if ($view === 'dashboard'): ?>
      <!-- ========== TABLEAU DE BORD (aligné Processus-R1b) ========== -->
      <div class="r1b-page-head">
        <div>
          <h2>Tableau de bord</h2>
          <p class="text-muted text-sm mt-1">Vue d'ensemble du projet de conception</p>
        </div>
        <a href="<?= url('projet.php?id=' . $id . '&view=processus') ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-route"></i> Ouvrir le processus
        </a>
      </div>

      <!-- KPI cards -->
      <div class="dash-kpi-grid">
        <div class="dash-kpi">
          <p class="dash-kpi-label">Progression</p>
          <p class="dash-kpi-value"><?= $pct ?>%</p>
        </div>
        <div class="dash-kpi">
          <p class="dash-kpi-label">Tâches en cours</p>
          <p class="dash-kpi-value"><?= $tachesOuvertes ?></p>
        </div>
        <div class="dash-kpi">
          <p class="dash-kpi-label">Validations en attente</p>
          <p class="dash-kpi-value"><?= $validationsPending ?></p>
        </div>
        <div class="dash-kpi">
          <p class="dash-kpi-label">Jalons</p>
          <p class="dash-kpi-value"><?= $nbJalons ?></p>
        </div>
      </div>

      <div class="dash-grid">
        <!-- Colonne principale : étapes + tâches -->
        <div class="dash-col-main">
          <div class="r1b-card">
            <h3>Processus R1b</h3>
            <div class="dash-steps">
              <?php foreach ($steps as $n => $s): ?>
                <?php
                $done = in_array((int)$n, $validatedSteps, true);
                $active = !$done && $n === $progressStep;
                $cls = $done ? 'done' : ($active ? 'active' : '');
                ?>
                <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=' . $n) ?>" class="dash-step <?= $cls ?>">
                  <div class="dash-step-num"><?= $done ? '✓' : $n ?></div>
                  <div class="dash-step-body">
                    <span class="dash-step-title"><?= e($s['title']) ?></span>
                    <?php if ($active): ?>
                      <span class="dash-step-badge">En cours</span>
                    <?php elseif ($done): ?>
                      <span class="dash-step-badge done">Terminé</span>
                    <?php endif; ?>
                  </div>
                  <div class="dash-step-pct"><?= $done || $active ? (int)round(($n / r1bMaxStep()) * 100) : 0 ?>%</div>
                </a>
              <?php endforeach; ?>
            </div>
            <div class="dash-progress-bar">
              <div class="dash-progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <p class="text-muted text-sm mt-2">
              Étape actuelle : <strong><?= e($steps[$progressStep]['title'] ?? '') ?></strong>
              <?php if (!empty($projet['go_decision'])): ?>
                · Décision GO/NO GO : <strong><?= e($projet['go_decision']) ?></strong>
              <?php endif; ?>
            </p>
          </div>

          <div class="r1b-card">
            <div class="flex-between mb-3">
              <h3>Tâches récentes</h3>
              <a href="<?= url('projet.php?id=' . $id . '&view=taches') ?>" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($taches)): ?>
              <p class="text-muted text-sm">Aucune tâche pour ce projet.</p>
              <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Nouvelle tâche</a>
            <?php else: ?>
              <div class="dash-task-list">
                <?php foreach (array_slice($taches, 0, 6) as $t): ?>
                  <?php $st = $t['statut'] ?? $t['status'] ?? 'a_faire'; ?>
                  <a href="<?= url('tache.php?id=' . (int)$t['id']) ?>" class="dash-task-row">
                    <span class="dash-task-title"><?= e($t['titre'] ?? $t['title'] ?? 'Sans titre') ?></span>
                    <span class="dash-task-status status-<?= e($st) ?>"><?= e(statutLabel($st)) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Colonne droite : notes + docs -->
        <div class="dash-col-side">
          <div class="r1b-card">
            <h3>Notes / décisions</h3>
            <form method="POST">
              <input type="hidden" name="action" value="save_notes">
              <?= csrfField() ?>
              <input type="hidden" name="redir_view" value="dashboard">
              <textarea name="step_notes" class="form-control" rows="5" placeholder="Notes, résultats, décisions…"><?= e($projet['step_notes'] ?? '') ?></textarea>
              <button type="submit" class="btn btn-secondary btn-sm mt-2"><i class="fas fa-save"></i> Enregistrer</button>
            </form>
            <?php if (!empty($projet['go_decision'])): ?>
              <div class="r1b-info-box mt-3">
                Décision GO/NO GO : <strong><?= e($projet['go_decision']) ?></strong>
              </div>
            <?php endif; ?>
          </div>


          <div class="r1b-card">
            <div class="flex-between mb-3">
              <h3>Jalons</h3>
              <a href="<?= url('cahier_form.php?projet_id=' . $id . $cdcReturnQuery) ?>" class="btn btn-secondary btn-sm">Éditer</a>
            </div>
            <?php if (empty($jalonsProjet)): ?>
              <p class="text-muted text-sm">Aucun jalon défini.</p>
              <a href="<?= url('cahier_form.php?projet_id=' . $id . $cdcReturnQuery) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Ajouter</a>
            <?php else: ?>
              <div class="dash-jalon-list">
                <?php foreach ($jalonsProjet as $j): ?>
                  <?php
                    $jd = $j['date_prevue'] ?? '';
                    $past = $jd && strtotime($jd) < strtotime('today');
                    $soon = $jd && !$past && strtotime($jd) <= strtotime('+14 days');
                  ?>
                  <div class="dash-jalon-row <?= $past ? 'past' : ($soon ? 'soon' : '') ?>">
                    <span class="dash-jalon-name"><?= e($j['nom']) ?></span>
                    <span class="dash-jalon-date"><?= $jd ? date('d/m/Y', strtotime($jd)) : '—' ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="r1b-card">
            <div class="flex-between mb-3">
              <h3>Documents récents</h3>
              <a href="<?= url('documents_externes.php?projet_id=' . $id) ?>" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($documents)): ?>
              <p class="text-muted text-sm">Aucun document.</p>
            <?php else: ?>
              <div class="dash-doc-list">
                <?php foreach (array_slice($documents, 0, 5) as $d): ?>
                  <div class="dash-doc-row">
                    <i class="fas fa-file"></i>
                    <div>
                      <p class="dash-doc-name"><?= e($d['nom_fichier'] ?? $d['nom_original'] ?? $d['filename'] ?? 'Fichier') ?></p>
                      <p class="text-muted text-xs"><?= e($d['date_upload'] ?? '') ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="r1b-card">
            <h3>Infos projet</h3>
            <p class="text-sm"><strong>Créateur :</strong> <?= e($projet['createur'] ?? '—') ?></p>
            <p class="text-sm mt-1"><strong>Statut :</strong> <?= e($projet['status'] ?? 'actif') ?></p>
            <p class="text-sm mt-1"><strong>CDC :</strong>
              <a href="<?= url('cahier_form.php?projet_id=' . $id . $cdcReturnQuery) ?>">Ouvrir le cahier des charges</a>
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($view === 'processus'): ?>
      <!-- ========== PROCESSUS R1b ========== -->
      <header class="r1b-process-header">
      <div class="r1b-page-head">
        <div>
          <h2>Processus R1b – Conception</h2>
          <p class="text-muted text-sm">Projet : <strong><?= e($projet['nom']) ?></strong></p>
      </div>
      </div>

      <div class="r1b-stepper-wrap">
        <div class="r1b-stepper">
          <?php $firstStep = true; foreach ($steps as $n => $s): ?>
            <?php if (!$firstStep): ?><div class="r1b-step-line <?= in_array((int)($n - 1), $validatedSteps, true) ? 'on' : '' ?>"></div><?php endif; $firstStep = false; ?>
            <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=' . $n) ?>" class="<?= stepClass($n, $currentStep, $validatedSteps) ?>">
              <div class="r1b-step-circle"><?= in_array((int)$n, $validatedSteps, true) ? '✓' : $n ?></div>
              <div class="r1b-step-label"><?= e($s['title']) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      </header>
      <div class="r1b-process-scroll">
      <?php $stepEditorActions = ''; ?>
      <div class="r1b-step-workspace">
      <div class="r1b-card r1b-step-content">
        <h3>Étape <?= $currentStep ?> – <?= e($steps[$currentStep]['title'] ?? '') ?></h3>

        <?php if ($currentStep === 1): ?>
          <hr class="my-4">
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">1. Contexte, objectifs et besoins utilisateurs</h4>
          <?php
            $hasContexte = trim($specs['objectifs'] ?? '') !== ''
                || trim($specs['resultats_attendus'] ?? '') !== ''
                || trim($specs['cas_usage'] ?? '') !== ''
                || trim($specs['profils_utilisateurs'] ?? '') !== '';
          ?>
          <?php if ($hasContexte): ?>
            <div class="r1b-info-box"><p><strong>Objectifs et contexte</strong></p><p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['objectifs'] ?: '—') ?></p></div>
            <div class="r1b-info-box"><p><strong>Hors périmètre du projet</strong></p><p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['resultats_attendus'] ?: '—') ?></p></div>
            <div class="r1b-info-box"><p><strong>Contraintes</strong></p><p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['cas_usage'] ?: '—') ?></p></div>
            <div class="r1b-info-box"><p><strong>Profils des utilisateurs finaux</strong></p><p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['profils_utilisateurs'] ?: '—') ?></p></div>
          <?php else: ?>
            <p class="text-muted text-sm">Aucun contenu renseigné dans la section Contexte du CDC structuré.</p>
          <?php endif; ?>
          <?php ob_start(); ?>
          <a href="<?= url('cahier_form.php?projet_id=' . $id . '&step=1&return_view=processus&return_step=1') ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-edit"></i> Éditer le contexte (CDC)</a>
          <?php $stepEditorActions = ob_get_clean(); ?>

        <?php elseif ($currentStep === 2): ?>
          <?php
            $fonctionsSF = $specs['fonctions'] ?? [];
            $techniquesAll = $specs['specs_techniques'] ?? [];
            $techBySf = [];
            foreach ($techniquesAll as $trow) {
                $sfKey = $trow['sf'] ?? '';
                if ($sfKey === '') continue;
                $techBySf[$sfKey][] = $trow;
            }
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Spécifications techniques</h4>
          <p class="text-sm text-muted mb-3">Pour chaque spécification fonctionnelle (S.F.) du CDC, ajoutez les spécifications techniques associées (S.T.n.m).</p>
          <?php if (empty($fonctionsSF)): ?>
            <div class="r1b-info-box">
              <p>Aucune spécification fonctionnelle dans le cahier des charges.</p>
              <a href="<?= url('cahier_form.php?projet_id=' . $id . $cdcReturnQuery) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-edit"></i> Ouvrir le CDC</a>
            </div>
          <?php else: ?>
            <form method="POST" id="formSpecsTech" action="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>">
              <input type="hidden" name="action" value="save_specs_techniques">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
              <?php foreach ($fonctionsSF as $sf): ?>
                <?php
                  $sfId = $sf['id'] ?? '';
                  if (!preg_match('/^S\.F\.(\d+)$/', $sfId, $mSf)) continue;
                  $sfNum = (int)$mSf[1];
                  $rows = $techBySf[$sfId] ?? [];
                  $sfCostHT = 0.0; $sfCostTTC = 0.0;
                  foreach ($rows as $costRow) {
                      $v = stTypeHasCost($costRow['type'] ?? 'Matériel') ? (float)($costRow['cout_estime'] ?? 0) : 0;
                      if (($costRow['cout_taxe'] ?? 'HT') === 'TTC') $sfCostTTC += $v; else $sfCostHT += $v;
                  }
                  if (empty($rows)) {
                      $rows = [['id' => 'S.T.' . $sfNum . '.1', 'description' => '', 'type' => 'Matériel']];
                  }
                ?>
                <div class="st-sf-block" data-sf="<?= e($sfId) ?>" data-sf-num="<?= $sfNum ?>">
                  <div class="st-sf-head">
                    <span class="st-sf-id"><?= e($sfId) ?></span>
                    <span class="st-sf-desc"><?= e($sf['description'] ?: '(sans description)') ?></span>
                    <?php if (!empty($sf['indicateur'])): ?>
                      <span class="st-sf-badge"><?= e($sf['indicateur']) ?></span>
                    <?php endif; ?>
                    <span class="st-sf-badge sf-cost-badge">Estimation coût <?= e($sfId) ?> :
                      <span class="sf-cost-value"><?= number_format($sfCostHT, 2, ',', ' ') ?> € HT<?php if ($sfCostTTC > 0): ?> + <?= number_format($sfCostTTC, 2, ',', ' ') ?> € TTC<?php endif; ?></span>
                    </span>
                  </div>
                  <div class="sf-table-wrap">
                    <table class="sf-table st-table">
                      <thead>
                        <tr>
                          <th style="width:5.5rem">ID</th>
                          <th>Description</th>
                          <th style="width:8.5rem">Type</th>
                          <th class="estimation-qty-head">Qté</th><th class="estimation-unit-head">Coût unitaire</th><th class="estimation-total-head">Coût total</th><th class="estimation-variation-head">Variation</th>
                          <th class="estimation-delay-head">Délai estimé (jours)</th>
                          <th style="width:2.5rem"></th>
                        </tr>
                      </thead>
                      <tbody class="st-body">
                        <?php foreach ($rows as $ri => $tr): ?>
                        <tr class="st-row">
                          <td><span class="st-id"><?= e($tr['id'] ?? ('S.T.' . $sfNum . '.' . ($ri + 1))) ?></span>
                            <input type="hidden" name="st_sf[]" value="<?= e($sfId) ?>">
                          </td>
                          <td><input type="text" name="st_description[]" class="form-control" value="<?= e($tr['description'] ?? '') ?>" placeholder="Description technique…"></td>
                          <td>
                            <?php $ty = $tr['type'] ?? 'Matériel'; ?>
                            <select name="st_type[]" class="form-control">
                              <option value="Matériel" <?= $ty === 'Matériel' ? 'selected' : '' ?>>Matériel</option>
                              <option value="Composant" <?= $ty === 'Composant' ? 'selected' : '' ?>>Composant</option>
                              <option value="Prestataire" <?= $ty === 'Prestataire' ? 'selected' : '' ?>>Prestataire</option>
                              <option value="Logiciel" <?= $ty === 'Logiciel' ? 'selected' : '' ?>>Logiciel</option>
                              <option value="3D" <?= $ty === '3D' ? 'selected' : '' ?>>3D</option>
                              <option value="PCB" <?= $ty === 'PCB' ? 'selected' : '' ?>>PCB</option>
                            </select>
                          </td>
                          <?php renderEstimationCostCells('st', $tr, stTypeHasCost($ty)); ?>
                          <td><?php renderEstimationVariation('st', $tr); ?></td>
                          <td><input type="number" min="0" step="0.01" name="st_delai_jours[]" class="form-control st-delay" value="<?= e((string)($tr['delai_jours'] ?? '')) ?>" aria-label="Délai estimé en jours"></td>
                          <td><button type="button" class="btn-sf-del btn-st-del" title="Supprimer">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm mt-1 btn-st-add"><i class="fas fa-plus"></i> Ajouter une S.T.</button>
                </div>
              <?php endforeach; ?>
              <?php ob_start(); ?>
              <div class="mt-3">
                <button type="submit" form="formSpecsTech" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les spécifications techniques</button>
                <a href="<?= url('cahier_form.php?projet_id=' . $id . '&step=2&return_view=processus&return_step=2') ?>" class="btn btn-secondary btn-sm">Éditer les S.F. (CDC)</a>
              </div>
              <?php $stepEditorActions = ob_get_clean(); ?>
            </form>
          <?php endif; ?>

        <?php elseif ($currentStep === 3): ?>
          <?php require __DIR__ . '/includes/views/decision_dashboard.php'; ?>

        <?php elseif ($currentStep === 4): ?>
          <?php
            $techniquesAll = $specs['specs_techniques'] ?? [];
            $composantsSt = $specs['composants_st'] ?? [];
            if (!is_array($composantsSt)) $composantsSt = [];
            $users = $utilisateursListe ?? [];
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Composants & affectations</h4>
          <p class="text-sm text-muted mb-3">Matériel, Composant, Prestataire et PCB : coûts et délais. Logiciel et 3D : délais de réalisation uniquement.</p>
          <?php if (empty($techniquesAll)): ?>
            <div class="r1b-info-box">
              <p>Aucune spécification technique définie.</p>
              <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>" class="btn btn-primary btn-sm mt-2">Aller à l'étape 2</a>
            </div>
          <?php else: ?>
            <form method="POST" id="formComposantsSt" action="<?= url('projet.php?id=' . $id . '&view=processus&step=4') ?>">
              <input type="hidden" name="action" value="save_composants_st">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
              <?php foreach ($techniquesAll as $stRow): ?>
                <?php
                  $stId = $stRow['id'] ?? '';
                  $stType = $stRow['type'] ?? 'Matériel';
                  $stDesc = $stRow['description'] ?? '';
                  if ($stId === '') continue;
                  $items = $composantsSt[$stId] ?? [];
                  $stCostHT = 0.0; $stCostTTC = 0.0;
                  foreach ($items as $costItem) {
                      $costValue = (float)($costItem['cout_total'] ?? 0);
                      if (($costItem['cout_unitaire_taxe'] ?? $costItem['cout_total_taxe'] ?? 'HT') === 'TTC') $stCostTTC += $costValue;
                      else $stCostHT += $costValue;
                  }
                  if (empty($items)) {
                      $items = [[]]; // ligne vide
                  }
                  $prefix = match ($stType) {
                      'Matériel' => 'M',
                      'Composant' => 'C',
                      'Prestataire' => 'P',
                      'Logiciel' => 'L',
                      '3D' => '3D',
                      'PCB' => 'PCB',
                      default => 'X',
                  };
                ?>
                <div class="st-sf-block cp-st-block" data-st-id="<?= e($stId) ?>" data-st-type="<?= e($stType) ?>" data-prefix="<?= e($prefix) ?>">
                  <div class="st-sf-head">
                    <span class="st-id"><?= e($stId) ?></span>
                    <span class="st-sf-badge"><?= e($stType) ?></span>
                    <span class="st-sf-desc"><?= e($stDesc ?: '(sans description)') ?></span>
                    <?php if (stTypeHasCost($stType)): ?>
                      <span class="st-sf-badge cp-st-cost">Coût <?= e($stId) ?> :
                        <span class="cp-st-cost-value"><?= number_format($stCostHT, 2, ',', ' ') ?> € HT<?= $stCostTTC > 0 ? ' + ' . number_format($stCostTTC, 2, ',', ' ') . ' € TTC' : '' ?></span>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="sf-table-wrap">
                    <?php if (stTypeHasCost($stType)): ?>
                      <table class="sf-table cp-table">
                        <thead>
                          <tr>
                            <th style="width:3.5rem">ID</th>
                            <th>Désignation</th>
                            <th>Référence</th>
                            <th>Fournisseur</th>
                            <th class="estimation-qty-head">Qté</th>
                            <th class="estimation-unit-head">Coût unitaire</th>
                            <th class="estimation-total-head">Coût total</th>
                            <th class="estimation-variation-head">Variation</th><th class="estimation-delay-head">Délai estimé (jours)</th>
                            <th style="width:2.2rem"></th>
                          </tr>
                        </thead>
                        <tbody class="cp-body">
                          <?php foreach ($items as $ii => $it): ?>
                          <tr class="cp-row">
                            <td>
                              <span class="cp-id"><?= e($it['id'] ?? ($prefix . '.' . ($ii + 1))) ?></span>
                              <input type="hidden" name="cp_st_id[]" value="<?= e($stId) ?>">
                              <input type="hidden" name="cp_type[]" value="<?= e($stType) ?>">
                            </td>
                            <td><input type="text" name="cp_designation[]" class="form-control" value="<?= e($it['designation'] ?? '') ?>"></td>
                            <td><input type="text" name="cp_reference[]" class="form-control" value="<?= e($it['reference'] ?? '') ?>"></td>
                            <td><input type="text" name="cp_fournisseur[]" class="form-control" value="<?= e($it['fournisseur'] ?? '') ?>"></td>
                            <?php ob_start(); ?>
                              <?php if ($stType === 'PCB'): ?>
                              <label>Affectation PCB</label><select name="cp_affectation[]" class="form-control"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= e($u['identifiant']) ?>" <?= (($it['affectation'] ?? '') === $u['identifiant']) ? 'selected' : '' ?>><?= e($u['identifiant']) ?></option><?php endforeach; ?></select>
                              <?php else: ?><input type="hidden" name="cp_affectation[]" value="<?= e($it['affectation'] ?? '') ?>"><?php endif; ?>
                              <input type="hidden" name="cp_duree[]" value="<?= e($it['duree'] ?? '') ?>">
                            <?php $extraTotal = ob_get_clean(); renderEstimationCostCells('cp', $it, true, $extraTotal); ?>
                            <td><?php renderEstimationVariation('cp', $it); ?></td>
                            <td><input type="number" min="0" step="0.01" name="cp_delai_jours[]" class="form-control" value="<?= e((string)($it['delai_jours'] ?? '')) ?>" aria-label="Délai en jours"><?php if (!isset($it['delai_jours']) && !empty($it['duree'])): ?><span class="text-xs text-muted">Durée précédente : <?= e($it['duree']) ?></span><?php endif; ?></td>
                            <td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php else: ?>
                      <table class="sf-table cp-table">
                        <thead>
                          <tr>
                            <th style="width:4rem">ID</th>
                            <th>Affectation</th>

                            <th class="estimation-variation-head">Variation</th>
                            <th class="estimation-delay-head">Délai estimé (jours)</th>
                            <th style="width:2.2rem"></th>
                          </tr>
                        </thead>
                        <tbody class="cp-body">
                          <?php foreach ($items as $ii => $it): ?>
                          <tr class="cp-row">
                            <td>
                              <span class="cp-id"><?= e($it['id'] ?? ($prefix . '.' . ($ii + 1))) ?></span>
                              <input type="hidden" name="cp_st_id[]" value="<?= e($stId) ?>">
                              <input type="hidden" name="cp_type[]" value="<?= e($stType) ?>">
                              <input type="hidden" name="cp_designation[]" value="">
                              <input type="hidden" name="cp_reference[]" value="">
                              <input type="hidden" name="cp_fournisseur[]" value="">
                              <input type="hidden" name="cp_quantite[]" value="">
                              <input type="hidden" name="cp_cout_unitaire[]" value="">
                              <input type="hidden" name="cp_cout_unitaire_taxe[]" value="HT">
                              <input type="hidden" name="cp_cout_total_taxe[]" value="HT">
                            </td>
                            <td>
                              <select name="cp_affectation[]" class="form-control">
                                <option value="">—</option>
                                <?php foreach ($users as $u): ?>
                                  <option value="<?= e($u['identifiant']) ?>" <?= (($it['affectation'] ?? '') === $u['identifiant']) ? 'selected' : '' ?>><?= e($u['identifiant']) ?></option>
                                <?php endforeach; ?>
                              </select>
                            </td>
                            <td>
                              <input type="hidden" name="cp_duree[]" value="<?= e($it['duree'] ?? '') ?>">
                              <?php renderEstimationVariation('cp', $it); ?>
                            </td>
                            <td><input type="number" min="0" step="0.01" name="cp_delai_jours[]" class="form-control" value="<?= e((string)($it['delai_jours'] ?? '')) ?>" aria-label="Délai en jours"><?php if (!isset($it['delai_jours']) && !empty($it['duree'])): ?><span class="text-xs text-muted">Durée précédente : <?= e($it['duree']) ?></span><?php endif; ?></td>
                            <td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm mt-1 btn-cp-add"><i class="fas fa-plus"></i> Ajouter une ligne</button>
                </div>
              <?php endforeach; ?>
              <?php ob_start(); ?>
              <div class="mt-3">
                <button type="submit" form="formComposantsSt" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les composants</button>
                <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>" class="btn btn-secondary btn-sm">Éditer les S.T. (étape 2)</a>
              </div>
              <?php $stepEditorActions = ob_get_clean(); ?>
            </form>
          <?php endif; ?>

        <?php elseif ($currentStep === 5): ?>
          <?php
            $purchaseRows = [];
            $composantsAchat = $specs['composants_st'] ?? [];
            $techAchat = $specs['specs_techniques'] ?? [];
            $techAchatById = [];
            foreach ($techAchat as $ta) if (!empty($ta['id'])) $techAchatById[$ta['id']] = $ta;
            foreach ($composantsAchat as $stId => $items) {
                if (!stTypeHasCost($techAchatById[$stId]['type'] ?? '') || !is_array($items)) continue;
                foreach ($items as $it) {
                    if (!is_array($it)) continue;
                    $itemId = trim((string)($it['id'] ?? ''));
                    if ($itemId === '') continue;
                    $purchaseRows[] = ['st_id' => $stId, 'st_description' => $techAchatById[$stId]['description'] ?? '', 'item' => $it];
                }
            }
            $purchaseTasks = [];
            foreach ($tachesAll as $pt) {
                $sk = (string)($pt['source_key'] ?? '');
                if (str_starts_with($sk, 'achat:' . $id . ':')) $purchaseTasks[$sk] = $pt;
            }
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Liste des achats issus de l'étape 4</h4>
          <p class="text-sm text-muted mb-3">Sélectionnez les composants ou matériels à commander, affectez un utilisateur puis générez les tâches d'achat. Une tâche existante est mise à jour plutôt que dupliquée.</p>
          <?php if (empty($purchaseRows)): ?>
            <div class="r1b-info-box">Aucune ligne Matériel, Composant, Prestataire ou PCB renseignée à l'étape 4. Enregistrez d'abord les composants et matériels à acheter.</div>
          <?php else: ?>
            <form method="POST" action="<?= url('projet.php?id=' . $id . '&view=processus&step=5') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="create_purchase_tasks">
              <div class="sf-table-wrap">
                <table class="sf-table r1b-purchase-table">
                  <thead><tr>
                    <th style="width:2.5rem">Créer</th><th>S.T.</th><th>ID</th><th>Désignation</th><th>Référence</th><th>Fournisseur</th><th>Qté</th><th>Coût estimé</th><th class="estimation-delay-head">Délai estimé (jours)</th><th>Affecter à</th><th>État tâche</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($purchaseRows as $pr): ?>
                    <?php
                      $it = $pr['item']; $stId = $pr['st_id']; $itemId = (string)$it['id'];
                      $rowKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $stId . '__' . $itemId);
                      $sourceKey = 'achat:' . $id . ':' . $stId . ':' . $itemId;
                      $existingTask = $purchaseTasks[$sourceKey] ?? null;
                      $tax = (($it['cout_unitaire_taxe'] ?? 'HT') === 'TTC') ? 'TTC' : 'HT';
                      $total = (float)($it['quantite'] ?? 0) * (float)($it['cout_unitaire'] ?? 0);
                      $payload = base64_encode(json_encode([
                        'st_id'=>$stId,'item_id'=>$itemId,'designation'=>$it['designation'] ?? '',
                        'reference'=>$it['reference'] ?? '','fournisseur'=>$it['fournisseur'] ?? '',
                        'quantite'=>$it['quantite'] ?? 0,'cout_unitaire'=>$it['cout_unitaire'] ?? 0,
                        'cout_unitaire_taxe'=>$tax,'delai_jours'=>$it['delai_jours'] ?? null
                      ], JSON_UNESCAPED_UNICODE));
                    ?>
                    <tr>
                      <td><input type="checkbox" name="purchase_create[]" value="<?= e($rowKey) ?>" <?= $existingTask ? 'checked' : '' ?>></td>
                      <td><strong><?= e($stId) ?></strong><div class="text-xs text-muted"><?= e($pr['st_description']) ?></div></td>
                      <td><?= e($itemId) ?></td>
                      <td><?= e($it['designation'] ?? '—') ?></td>
                      <td><?= e($it['reference'] ?? '—') ?></td>
                      <td><?= e($it['fournisseur'] ?? '—') ?></td>
                      <td class="estimate-quantity"><?= e((string)($it['quantite'] ?? '0')) ?></td>
                      <td class="estimate-money"><?= number_format($total, 2, ',', ' ') ?> € <?= e($tax) ?></td>
                      <td class="estimate-delay"><?= e(isset($it['delai_jours']) ? (string)$it['delai_jours'] : '—') ?></td>
                      <td>
                        <select name="purchase_assignee[<?= e($rowKey) ?>]" class="form-control">
                          <option value="">— Non affectée —</option>
                          <?php foreach ($utilisateursListe as $u): ?>
                            <option value="<?= e($u['identifiant']) ?>" <?= (($existingTask['assigne_a'] ?? '') === $u['identifiant']) ? 'selected' : '' ?>><?= e($u['identifiant']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="purchase_payload[<?= e($rowKey) ?>]" value="<?= e($payload) ?>">
                      </td>
                      <td><?= $existingTask ? e(statutLabel($existingTask['statut'] ?? 'a_faire')) : '<span class="text-muted">Non créée</span>' ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-shopping-cart"></i> Générer / mettre à jour les tâches d'achat</button>
            </form>
          <?php endif; ?>

        <?php elseif ($currentStep === 6): ?>
          <p class="text-sm text-muted mb-2">Fabrication et prototypage.</p>
          <?php foreach (($tachesAll ?? $taches) as $t): ?>
            <?php $st = $t['statut'] ?? 'a_faire'; ?>
            <div class="r1b-task-line">
              <span><?= e($t['titre'] ?? '') ?></span>
              <span class="text-muted"><?= e(statutLabel($st)) ?></span>
            </div>
          <?php endforeach; ?>

        <?php elseif ($currentStep === 7): ?>
          <p class="text-sm text-muted mb-2">Tests de conformité.</p>
          <div class="r1b-info-box">Validez la conformité ou signalez une non-conformité pour reboucler.</div>

        <?php elseif ($currentStep === 8): ?>
          <p class="text-sm text-muted mb-2">Livraison à la direction générale.</p>
          <ul class="r1b-list">
            <li>Dossier technique complet</li>
            <li>Rapport de tests</li>
            <li>CDC validé</li>
          </ul>
          <div class="r1b-info-box mt-2">Un état <strong>Conforme</strong> archive le projet en <strong>validé/vente</strong> (étape 9).</div>

        <?php else: ?>
          <div class="r1b-info-box" style="border-color:#a7f3d0;background:#ecfdf5;color:#065f46;">
            <strong>Archivage validé / vente</strong> (étape 9) — suite à une conformité / livraison DG.
            <br><span class="text-sm">Distinct de l’abandon NO GO resté à l’étape 3.</span>
          </div>
        <?php endif; ?>

      </div>
      <aside class="r1b-card r1b-step-tools" aria-label="Actions et revue de l’étape">
        <h3>Actions et revue</h3>
        <?= $stepEditorActions ?>
        <?php require __DIR__ . '/includes/views/project_notes.php'; ?>

        <div class="r1b-actions">
          <?php
            $decideAction = url('projet.php?id=' . $id . '&view=processus&step=' . $currentStep);
            $goDecision = trim((string)($projet['go_decision'] ?? ''));
          ?>
          <?php if ($currentStep === 3): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="GO">
              <label>Justification / réserves du GO <textarea name="motif" class="form-control" maxlength="5000" placeholder="Justification de la décision et réserves éventuelles"></textarea></label>
              <button type="submit" class="btn btn-success">GO</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="NO_GO">
              <label>Motif du NO GO <textarea name="motif" class="form-control" required maxlength="5000"></textarea></label>
              <button type="submit" class="btn btn-danger">NO GO → Abandon</button>
            </form>
          <?php elseif ($currentStep === 7): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="CONFORME">
              <button type="submit" class="btn btn-success">✓ Conforme → Livraison DG</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="NON_CONFORME">
              <label>Motif de non-conformité <textarea name="motif" class="form-control" required maxlength="5000"></textarea></label>
              <button type="submit" class="btn btn-danger">Non conforme</button>
            </form>
          <?php elseif ($currentStep === 8): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="CONFORME">
              <button type="submit" class="btn btn-success">✓ Conforme → Archivage validé/vente</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="NON_CONFORME">
              <label>Motif de non-conformité <textarea name="motif" class="form-control" required maxlength="5000"></textarea></label>
              <button type="submit" class="btn btn-danger">Non conforme</button>
            </form>
          <?php elseif ($currentStep > 0 && $currentStep < 9 && $goDecision !== 'NO_GO'): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="DONE">
              <button type="submit" class="btn btn-primary">Valider l'étape</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" class="mt-2">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="decision" value="REFUSE">
              <label for="motif-refus">Motif du refus / corrections attendues</label>
              <textarea id="motif-refus" name="motif" class="form-control" required maxlength="5000"></textarea>
              <button type="submit" class="btn btn-danger">Refuser l'étape et revenir en correction</button>
            </form>
          <?php endif; ?>
          <?php if ($currentStep > r1bMinStep()): ?>
            <a class="btn btn-secondary" href="<?= url('projet.php?id=' . $id . '&view=processus&step=' . ($currentStep - 1)) ?>">Étape précédente</a>
          <?php endif; ?>
        </div>
      </aside>
      </div>

      <section class="r1b-card history-step-panel" aria-label="Historique séparé de l’étape">
        <div class="r1b-page-head"><div><h3>Historique des décisions</h3><p class="text-sm text-muted">Consultez, filtrez et triez sans perdre la saisie en cours dans cette étape.</p></div><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= url('projet.php?id='.$id.'&view=historique&step='.$currentStep) ?>">Ouvrir l’historique complet</a></div>
        <iframe class="history-step-frame" title="Historique des décisions de l’étape <?= $currentStep ?>" src="<?= url('projet.php?id='.$id.'&view=historique&embedded=1&step='.$currentStep) ?>" loading="lazy"></iframe>
      </section>

      </div><!-- Défilement du contenu et de l’historique sous l’en-tête -->
    <?php elseif ($view === 'historique'): ?>
      <?php require __DIR__ . '/includes/views/decision_history.php'; ?>

    <?php elseif ($view === 'taches'): ?>
      <!-- ========== TÂCHES (Kanban style Processus-R1b) ========== -->
      <div class="r1b-page-head">
        <div>
          <h2>Gestion des tâches</h2>
          <p class="text-muted text-sm">
            <?php if (estAdmin()): ?>
              Toutes les tâches du projet <?= e($projet['nom']) ?>
            <?php else: ?>
              Vos tâches affectées — projet <?= e($projet['nom']) ?>
            <?php endif; ?>
          </p>
        </div>
        <?php if (estAdmin()): ?>
          <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouvelle tâche</a>
        <?php endif; ?>
      </div>
      <?php
        $statusFormAction=url('projet.php?id='.$id.'&view=taches');
        $showProjectLink=false;
        require __DIR__.'/includes/views/kanban.php';
      ?>

    <?php elseif ($view === 'documents'): ?>

      <!-- ========== DOCUMENTS ========== -->
      <div class="r1b-page-head">
        <div>
          <h2>Espace documentaire</h2>
          <p class="text-muted text-sm">Documents du projet <?= e($projet['nom']) ?></p>
        </div>
        <?php if ($ncEnabled): ?>
          <form action="<?= url('actions/upload.php') ?>" method="POST" enctype="multipart/form-data" class="flex gap-2 items-center">
            <input type="hidden" name="projet_id" value="<?= $id ?>">
            <input type="hidden" name="phase" value="<?= e($phase) ?>">
            <input type="file" name="fichier" required class="form-control" style="max-width:220px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Importer</button>
          </form>
        <?php else: ?>
          <span class="text-muted text-sm">Nextcloud non configuré (Admin → Paramètres)</span>
        <?php endif; ?>
      </div>
      <div class="r1b-docs-grid">
        <?php if (empty($documents)): ?>
          <p class="text-muted">Aucun document</p>
        <?php else: ?>
          <?php foreach ($documents as $d): ?>
            <div class="r1b-card">
              <h4><?= e($d['nom_fichier'] ?? $d['nom_original'] ?? $d['filename'] ?? 'Fichier') ?></h4>
              <p class="text-xs text-muted mt-1"><?= e($d['date_upload'] ?? '') ?> · <?= e($d['phase'] ?? '') ?></p>
              <?php if (!empty($d['chemin_nextcloud'])): ?>
                <p class="text-xs text-muted">Nextcloud : <?= e($d['chemin_nextcloud']) ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div><!-- /.r1b-main -->
</div><!-- /.r1b-layout -->

<script>window.PROJECTFLOW_USERS = <?= json_encode(array_map(static fn($u) => $u['identifiant'], $utilisateursListe ?? []), JSON_UNESCAPED_UNICODE) ?>;</script>
<template id="estimation-st-cells"><?php renderEstimationCostCells('st'); ?><td><?php renderEstimationVariation('st'); ?></td></template>
<template id="estimation-cp-cells"><?php renderEstimationCostCells('cp'); ?><td><?php renderEstimationVariation('cp'); ?></td></template>
<template id="estimation-cp-variation"><?php renderEstimationVariation('cp'); ?></template>
<script src="<?= url('assets/js/projet-r1b.js?v=review-2') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</main>
<footer class="footer">
  <div class="footer-container"><p>&copy; <?= date('Y') ?> ProjectFlow — Processus R1b</p></div>
</footer>
</body>
</html>

