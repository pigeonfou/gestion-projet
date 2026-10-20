<?php
$pageTitle = 'Projet';
$activePage = 'projets';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/estimation_fields.php';
require_once __DIR__ . '/includes/task_workflow.php';
require_once __DIR__ . '/includes/task_filters.php';
require_once __DIR__ . '/includes/ux_objects.php';
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

$projet = pfFindProject($db,$id);
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}

requerirAccesProjet($id, false);
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

require __DIR__.'/includes/actions/project.php';

// Reload after possible updates
$projet = pfFindProject($db,$id);
$progressStep = r1bClampStep((int)($projet['current_step'] ?? 1));
$validatedSteps = json_decode((string)($projet['validated_steps'] ?? '[]'), true);
if (!is_array($validatedSteps)) $validatedSteps = [];
$validatedSteps = array_values(array_unique(array_map('intval', $validatedSteps)));
if (empty($validatedSteps) && $progressStep > 1) $validatedSteps = range(1, $progressStep - 1);
// Display archival completion without changing the business validation state.
$displayValidatedSteps = $validatedSteps;
if (($projet['status'] ?? '') === 'termine') $displayValidatedSteps = range(r1bMinStep(), r1bMaxStep());
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

$needTasks = in_array($view, ['dashboard', 'taches', 'processus'], true) && !($view === 'processus' && $currentStep === 3);
$needDocs = in_array($view, ['dashboard', 'documents'], true);
$needJalons = $view === 'dashboard';
$needUsers = $view === 'processus' && in_array($currentStep, [4, 5], true);

if ($needTasks) {
    ensureTachesExtendedColumns();
    $tachesAll = $db->prepare('SELECT * FROM taches WHERE projet_id = ? ORDER BY id DESC');
    $tachesAll->execute([$id]);
    $tachesAll = $tachesAll->fetchAll();
    $taches = $tachesAll;
    if (!projectCanManage($db,$id,$user)) {
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

$pct = ($projet['status'] ?? '') === 'termine' ? 100 : (int)round((count($validatedSteps) / r1bMaxStep()) * 100);
$nbDocs = count($documents);
$validationsPending = (($projet['status']??'')==='termine' || in_array($progressStep,$validatedSteps,true)) ? 0 : ((($progressStep===3 && empty($projet['go_decision'])) || in_array($progressStep,[7,8],true)) ? 1 : 0);

$partial=$_SERVER['HTTP_X_PROJECTFLOW_PARTIAL'] ?? '';
if (($partial==='tasks' && $view==='taches') || ($partial==='history' && $view==='historique')) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: X-ProjectFlow-Partial');
    if ($partial==='history') require __DIR__.'/includes/views/decision_history.php';
    else { $statusFormAction=url('projet.php?id='.$id.'&view=taches');$showProjectLink=false;require __DIR__.'/includes/views/kanban.php'; }
    exit;
}

// Embedded history stays inside its own browsing context so filtering never
// reloads or discards an unfinished step form. Authentication above still applies.
$historyEmbedded = $view === 'historique' && ($_GET['embedded'] ?? '') === '1';
if ($historyEmbedded) {
    ?>
    <!doctype html><html lang="fr" data-theme="<?=e(interfaceTheme())?>"><head><meta charset="utf-8"><script src="<?=url('assets/js/appearance.js?v=1')?>"></script><meta name="viewport" content="width=device-width, initial-scale=1"><title>Historique des décisions</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css?v=recode-1') ?>"><link rel="stylesheet" href="<?=url('assets/css/components/dashboard.css')?>"><link rel="stylesheet" href="<?= url('assets/css/projet-r1b.css?v=recode-1') ?>"><link rel="stylesheet" href="<?= url('assets/css/decision-history.css?v=night-4') ?>"><link rel="stylesheet" href="<?= url('assets/css/projectflow-ui.css?v=night-4') ?>"><link rel="stylesheet" href="<?= url('assets/css/oddworks-theme.css?v=night-4') ?>"></head><body class="pf-app history-embedded"><main>
    <?php require __DIR__ . '/includes/views/decision_history.php'; ?>
    </main><script src="<?= url('assets/js/st-index.js') ?>"></script>
<script src="<?= url('assets/js/st-references.js?v=recode-1') ?>"></script>
<script src="<?=url('assets/js/projectflow-ui.js?v=recode-1')?>" defer></script>
</body></html>
    <?php
    exit;
}
$pageTitle = $projet['nom'];
$useAppShell = true;
require __DIR__ . '/includes/header.php';

function stepClass(int $n, int $displayed, array $validated): string
{
    $classes = ['r1b-step'];
    if (in_array($n, $validated, true)) $classes[] = 'done';
    if ($n === $displayed) $classes[] = 'active';
    return implode(' ', $classes);
}

?>

<link rel="stylesheet" href="<?= url('assets/css/decision-history.css?v=night-4') ?>">
<link rel="stylesheet" href="<?= url('assets/css/decision-dashboard.css?v=night-4') ?>">
<div class="r1b-layout">
  <!-- Sidebar projet -->
  <aside class="r1b-sidebar">
    <div class="r1b-sidebar-title"><?= e($projet['nom']) ?></div>
    <nav class="r1b-nav" aria-label="Navigation du projet">
      <a href="<?= url('projet.php?id=' . $id . '&view=dashboard') ?>" class="<?= $view === 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-th-large"></i> Synthèse du projet
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
    <nav class="r1b-nav" aria-label="Objets du projet">
      <a href="<?=url('specifications.php?projet_id='.$id)?>"><i class="fas fa-diagram-project"></i> S.F. / S.T.</a>
      <a href="<?=url('planning.php?projet_id='.$id)?>"><i class="fas fa-calendar"></i> Planning & Gantt</a>
      <a href="<?=url('production.php?projet_id='.$id)?>"><i class="fas fa-industry"></i> Production & stock</a>
    </nav>
    <div class="r1b-sidebar-foot">
      <?php
      $activeProjectChoices = $db->query("SELECT id, nom FROM projets WHERE status = 'actif' ORDER BY nom COLLATE NOCASE")->fetchAll();
      $activeProjectChoices = array_filter($activeProjectChoices, static fn(array $choice): bool => projectCanContribute($db, (int)$choice['id'], $user));
      ?>
      <label for="active-project-switch">Projets en cours</label>
      <select id="active-project-switch" class="form-control" onchange="if(this.value) window.location.assign(this.value)">
        <option value="" <?= ($projet['status'] ?? '') !== 'actif' ? 'selected' : '' ?>>Sélectionner un projet</option>
        <?php foreach ($activeProjectChoices as $choice): ?>
        <option value="<?= e(url('projet.php?id=' . (int)$choice['id'])) ?>" <?= (int)$choice['id'] === $id ? 'selected' : '' ?>><?= e($choice['nom']) ?></option>
        <?php endforeach; ?>
      </select>
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
        <a class="dash-kpi" href="<?=url('projet.php?id='.$id.'&view=processus')?>">
          <p class="dash-kpi-label">Progression</p>
          <p class="dash-kpi-value"><?= $pct ?>%</p>
        </a>
        <a class="dash-kpi" href="<?=url('projet.php?id='.$id.'&view=taches')?>">
          <p class="dash-kpi-label">Tâches en cours</p>
          <p class="dash-kpi-value"><?= $tachesOuvertes ?></p>
        </a>
        <a class="dash-kpi" href="<?=url('projet.php?id='.$id.'&view=processus&step='.$progressStep)?>">
          <p class="dash-kpi-label">Validations en attente</p>
          <p class="dash-kpi-value"><?= $validationsPending ?></p>
        </a>
        <a class="dash-kpi" href="<?=url('planning.php?projet_id='.$id)?>">
          <p class="dash-kpi-label">Jalons</p>
          <p class="dash-kpi-value"><?= $nbJalons ?></p>
        </a>
      </div>

      <div class="dash-grid">
        <!-- Colonne principale : étapes + tâches -->
        <div class="dash-col-main">
          <div class="r1b-card">
            <h3>Processus R1b</h3>
            <div class="dash-steps">
              <?php foreach ($steps as $n => $s): ?>
                <?php
                $done = in_array((int)$n, $displayValidatedSteps, true);
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
          <div class="r1b-card"><h3>Décisions & notes de pilotage</h3><p class="text-muted text-sm">Consultez les décisions enregistrées et ajoutez vos notes dans l’étape concernée.</p><div class="pf-links"><a href="<?=url('projet.php?id='.$id.'&view=historique')?>">Historique des décisions</a><a href="<?=url('projet.php?id='.$id.'&view=processus&step='.$progressStep)?>">Revue de l’étape actuelle</a></div></div>


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
                      <p class="dash-doc-name"><a href="<?=url('documents_externes.php?projet_id='.$id)?>"><?= e($d['nom_fichier'] ?? $d['nom_original'] ?? $d['filename'] ?? 'Fichier') ?></a></p>
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
            <?php if (!$firstStep): ?><div class="r1b-step-line <?= in_array((int)($n - 1), $displayValidatedSteps, true) ? 'on' : '' ?>"></div><?php endif; $firstStep = false; ?>
            <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=' . $n) ?>" class="<?= stepClass($n, $currentStep, $displayValidatedSteps) ?>" <?= $n === $currentStep ? 'aria-current="step"' : '' ?>>
              <div class="r1b-step-circle"><?= in_array((int)$n, $displayValidatedSteps, true) ? '✓' : $n ?></div>
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

        <?php if($currentStep>=6):?><div class="pf-links"><a href="<?=url('planning.php?projet_id='.$id)?>">Planning des tâches</a><a href="<?=url('documents_externes.php?projet_id='.$id)?>">Dossier & preuves</a><a href="<?=url('qualite_projet.php?projet_id='.$id)?>">Conformité fournisseurs</a><a href="<?=url('production.php?projet_id='.$id)?>">Prototype & production</a></div><?php endif;?>
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
          <?php require __DIR__.'/views/projects/specifications.php'; ?>
        <?php elseif ($currentStep === 3): ?>
          <?php require __DIR__ . '/includes/views/decision_dashboard.php'; ?>

        <?php elseif ($currentStep === 4): ?>
          <?php require __DIR__.'/views/projects/components.php'; ?>
        <?php elseif ($currentStep === 5): ?>
          <?php require __DIR__.'/views/projects/purchases.php'; ?>
        <?php elseif ($currentStep === 6): ?>
          <p class="text-sm text-muted mb-2">Fabrication et prototypage.</p>
          <?php foreach (($tachesAll ?? $taches) as $t): ?>
            <?php $st = $t['statut'] ?? 'a_faire'; ?>
            <div class="r1b-task-line">
              <a href="<?=url('tache.php?id='.(int)$t['id'])?>"><?= e($t['titre'] ?? '') ?></a>
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
          <div class="r1b-info-box" style="border-color:var(--state-success);background:var(--state-success-bg);color:var(--state-success);">
            <strong>Archivage validé / vente</strong> (étape 9) — suite à une conformité / livraison DG.
            <br><span class="text-sm">Distinct de l’abandon NO GO resté à l’étape 3.</span>
          </div>
        <?php endif; ?>

      </div>
      <aside class="r1b-card r1b-step-tools" aria-label="Actions et revue de l’étape">
        <h3>Actions et revue</h3>
        <div class="r1b-tools-resize-handle" role="separator" tabindex="0" aria-label="Redimensionner Actions et revue" aria-orientation="vertical" aria-valuemin="240" aria-valuemax="480" aria-valuenow="300" title="Maintenir et tirer pour ajuster la largeur ; flèches gauche/droite au clavier"></div>
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
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="GO">
              <label>Justification / réserves du GO <textarea name="motif" class="form-control" maxlength="5000" placeholder="Justification de la décision et réserves éventuelles"></textarea></label>
              <button type="submit" class="btn btn-success">GO</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="NO_GO">
              <label>Motif du NO GO <textarea name="motif" class="form-control" required maxlength="5000"></textarea></label>
              <button type="submit" class="btn btn-danger">NO GO → Abandon</button>
            </form>
          <?php elseif ($currentStep === 7): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="CONFORME">
              <button type="submit" class="btn btn-success">✓ Conforme → Livraison DG</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="NON_CONFORME">
              <label>Motif de non-conformité <textarea name="motif" class="form-control" required maxlength="5000"></textarea></label>
              <button type="submit" class="btn btn-danger">Non conforme</button>
            </form>
          <?php elseif ($currentStep === 8): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="CONFORME">
              <button type="submit" class="btn btn-success">✓ Conforme → Archivage validé/vente</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="NON_CONFORME">
              <label>Motif de non-conformité <textarea name="motif" class="form-control" required maxlength="5000"></textarea></label>
              <button type="submit" class="btn btn-danger">Non conforme</button>
            </form>
          <?php elseif ($currentStep > 0 && $currentStep < 9 && $goDecision !== 'NO_GO'): ?>
            <form method="POST" action="<?= $decideAction ?>" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
              <input type="hidden" name="decision" value="DONE">
              <button type="submit" class="btn btn-primary">Valider l'étape</button>
            </form>
            <form method="POST" action="<?= $decideAction ?>" class="mt-2">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="action" value="decide"><input type="hidden" name="project_revision" value="<?=e(pfProjectRevision($projet))?>">
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
            <?= csrfField() ?>
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

<link rel="stylesheet" href="<?= url('assets/css/estimate-layout.css?v=9') ?>">
<link rel="stylesheet" href="<?= url('assets/css/st-hierarchy.css?v=2') ?>">
<script>window.PROJECTFLOW_USERS = <?= json_encode(array_map(static fn($u) => $u['identifiant'], $utilisateursListe ?? []), JSON_UNESCAPED_UNICODE) ?>;</script>
<template id="estimation-st-cells"><?php renderEstimationCostCells('st'); ?><td><?php renderEstimationVariation('st'); ?></td></template>
<template id="estimation-cp-cells"><?php renderEstimationCostCells('cp'); ?><td><?php renderEstimationVariation('cp'); ?></td></template>
<template id="estimation-cp-variation"><?php renderEstimationVariation('cp'); ?></template>
<script>window.ProjectFlow=window.ProjectFlow||{};window.ProjectFlow.costTypes=<?=json_encode(array_values(array_filter(['Matériel','Composant','Prestataire','Logiciel','3D','PCB'],'stTypeHasCost')))?>;</script>
<script src="<?=url('assets/js/estimation.js')?>"></script>
<script src="<?= url('assets/js/projet-r1b.js?v=recode-1') ?>"></script>
<script src="<?= url('assets/js/app.js?v=recode-1') ?>"></script>
</main>
<footer class="footer">
  <div class="footer-container"><p>&copy; <?= date('Y') ?> OddWorks — Processus R1b</p></div>
</footer>
<script src="<?= url('assets/js/st-index.js') ?>"></script>
<script src="<?= url('assets/js/st-references.js?v=recode-1') ?>"></script>
<script src="<?= url('assets/js/st-hierarchy.js?v=recode-1') ?>"></script>
<script src="<?=url('assets/js/projectflow-ui.js?v=recode-1')?>" defer></script>
</body>
</html>
