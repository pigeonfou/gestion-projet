<?php
$pageTitle = 'Projet';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings_helper.php';
require_once __DIR__ . '/includes/cahier_specs.php';
require_once __DIR__ . '/includes/r1b_steps.php';
requerirConnexion();
seedSettingsIfEmpty();
ensureProjectProcessColumns();

$db = getDB();
$user = utilisateurCourant();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? $_POST['projet_id'] ?? 0);
$view = $_GET['view'] ?? 'dashboard'; // dashboard | processus | taches | documents
$stepGet = isset($_GET['step']) ? (int)$_GET['step'] : 0;

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

$currentStep = max(1, min(8, (int)($projet['current_step'] ?? 1)));
if ($stepGet >= 1 && $stepGet <= 8) {
    $currentStep = $stepGet;
}
$phase = r1bPhaseFromStep($currentStep);
$steps = r1bSteps();

// ——— Actions POST ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_notes') {
        $notes = trim($_POST['step_notes'] ?? '');
        $db->prepare('UPDATE projets SET step_notes = ? WHERE id = ?')->execute([$notes, $id]);
        setFlash('success', 'Notes enregistrées.');
        $redirView = $_POST['redir_view'] ?? 'processus';
        redirect('projet.php?id=' . $id . '&view=' . $redirView . ($redirView === 'processus' ? '&step=' . $currentStep : ''));
    }
    if ($action === 'set_step') {
        $n = (int)($_POST['step'] ?? 1);
        $n = max(1, min(8, $n));
        $db->prepare('UPDATE projets SET current_step = ? WHERE id = ?')->execute([$n, $id]);
        setFlash('success', 'Étape mise à jour.');
        redirect('projet.php?id=' . $id . '&view=processus&step=' . $n);
    }
    if ($action === 'save_specs_techniques') {
        try {
            ensureCahierSpecsColumn();
            $stmtC = $db->prepare('SELECT id FROM cahiers WHERE projet_id = ?');
            $stmtC->execute([$id]);
            $cid = (int)$stmtC->fetchColumn();
            if ($cid <= 0) {
                $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$id]);
                $cid = (int)$db->lastInsertId();
            }
            $techniques = parseSpecsTechniquesFromPost($_POST);
            saveSpecsTechniques($cid, $techniques);
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
            ensureCahierSpecsColumn();
            $stmtC = $db->prepare('SELECT id FROM cahiers WHERE projet_id = ?');
            $stmtC->execute([$id]);
            $cid = (int)$stmtC->fetchColumn();
            if ($cid <= 0) {
                $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$id]);
                $cid = (int)$db->lastInsertId();
            }
            $composants = parseComposantsStFromPost($_POST);
            saveComposantsSt($cid, $composants);
            $techs = loadSpecs($cid)['specs_techniques'] ?? [];
            syncTasksFromComposants($id, $composants, is_array($techs) ? $techs : []);
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
    if ($action === 'update_task_status') {
        ensureTachesExtendedColumns();
        $tid = (int)($_POST['task_id'] ?? 0);
        $st = $_POST['status'] ?? 'a_faire';
        $allowed = ['a_faire', 'en_cours', 'validation', 'terminee', 'done', 'todo', 'in_progress'];
        $map = [
            'todo' => 'a_faire',
            'in_progress' => 'en_cours',
            'done' => 'terminee',
            'validation' => 'validation',
            'a_faire' => 'a_faire',
            'en_cours' => 'en_cours',
            'terminee' => 'terminee',
        ];
        $kanban = $map[$st] ?? 'a_faire';
        $statutDb = $kanban === 'validation' ? 'en_cours' : ($kanban === 'terminee' ? 'terminee' : ($kanban === 'en_cours' ? 'en_cours' : 'a_faire'));
        if ($tid > 0) {
            $db->prepare('UPDATE taches SET kanban_status = ?, statut = ? WHERE id = ? AND projet_id = ?')
               ->execute([$kanban, $statutDb, $tid, $id]);
            setFlash('success', 'Statut de la tâche mis à jour.');
        }
        redirect('projet.php?id=' . $id . '&view=taches');
    }
    if ($action === 'decide') {


        $decision = $_POST['decision'] ?? '';
        if (in_array($decision, ['GO', 'NO_GO', 'CONFORME', 'NON_CONFORME', 'DONE'], true)) {
            if ($decision === 'GO' || $decision === 'NO_GO') {
                $db->prepare('UPDATE projets SET go_decision = ?, current_step = ? WHERE id = ?')
                   ->execute([$decision, $decision === 'NO_GO' ? 8 : min(8, $currentStep + 1), $id]);
            } elseif ($decision === 'DONE' || $decision === 'CONFORME') {
                $next = min(8, $currentStep + 1);
                $db->prepare('UPDATE projets SET current_step = ? WHERE id = ?')->execute([$next, $id]);
            } elseif ($decision === 'NON_CONFORME') {
                $db->prepare('UPDATE projets SET current_step = ? WHERE id = ?')->execute([max(1, $currentStep - 1), $id]);
            }
            setFlash('success', 'Décision enregistrée : ' . $decision);
        }
        redirect('projet.php?id=' . $id . '&view=processus');
    }
}

// Reload after possible updates
$stmt->execute([$id]);
$projet = $stmt->fetch();
$currentStep = max(1, min(8, (int)($projet['current_step'] ?? 1)));
if ($stepGet >= 1 && $stepGet <= 8) {
    $currentStep = $stepGet;
}
$phase = r1bPhaseFromStep($currentStep);

// Cahier
$stmt = $db->prepare('SELECT * FROM cahiers WHERE projet_id = ?');
$stmt->execute([$id]);
$cahier = $stmt->fetch();
if (!$cahier) {
    $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$id]);
    $stmt->execute([$id]);
    $cahier = $stmt->fetch();
}
$cahier_id = (int)$cahier['id'];
$specs = loadSpecs($cahier_id);

ensureTachesExtendedColumns();
$taches = $db->prepare('SELECT * FROM taches WHERE projet_id = ? ORDER BY id DESC');
$taches->execute([$id]);
$taches = $taches->fetchAll();

ensureSettingsTable();
$documents = $db->prepare('SELECT * FROM documents WHERE projet_id = ? ORDER BY date_upload DESC');
$documents->execute([$id]);
$documents = $documents->fetchAll();
$ncEnabled = getSetting('nextcloud_enabled', '0') === '1';

$jalonsProjet = loadJalons($cahier_id);
$nbJalons = count($jalonsProjet);
$utilisateursListe = $db->query('SELECT id, identifiant FROM utilisateurs ORDER BY identifiant')->fetchAll(PDO::FETCH_ASSOC);


// Stats pour le tableau de bord projet
$tachesOuvertes = 0;
$tachesTerminees = 0;
foreach ($taches as $t) {
    $st = $t['statut'] ?? $t['status'] ?? 'a_faire';
    if (in_array($st, ['terminee', 'done', 'terminé'], true)) {
        $tachesTerminees++;
    } else {
        $tachesOuvertes++;
    }
}
$pct = (int)round(($currentStep / 8) * 100);
$nbDocs = count($documents);
$validationsPending = ($currentStep === 3 && empty($projet['go_decision'])) || in_array($currentStep, [6, 7], true) ? 1 : 0;

$pageTitle = $projet['nom'];
$useAppShell = true;
require __DIR__ . '/includes/header.php';

function stepClass(int $n, int $current): string
{
    if ($n < $current) {
        return 'r1b-step done';
    }
    if ($n === $current) {
        return 'r1b-step active';
    }
    return 'r1b-step';
}

function statutLabel(string $s): string
{
    return match ($s) {
        'a_faire', 'todo' => 'À faire',
        'en_cours', 'in_progress' => 'En cours',
        'terminee', 'done', 'terminé' => 'Terminé',
        'validation' => 'En validation',
        default => $s,
    };
}

function taskKanbanStatus(array $t): string
{
    $k = $t['kanban_status'] ?? '';
    if (in_array($k, ['a_faire', 'en_cours', 'validation', 'terminee'], true)) {
        return $k;
    }
    $s = $t['statut'] ?? 'a_faire';
    return match ($s) {
        'terminee', 'done', 'terminé' => 'terminee',
        'en_cours', 'in_progress' => 'en_cours',
        default => 'a_faire',
    };
}
?>

<div class="r1b-layout">
  <!-- Sidebar projet -->
  <aside class="r1b-sidebar">
    <div class="r1b-sidebar-title"><?= e($projet['nom']) ?></div>
    <nav class="r1b-nav">
      <a href="<?= url('projet.php?id=' . $id . '&view=dashboard') ?>" class="<?= $view === 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-th-large"></i> Tableau de bord
      </a>
      <a href="<?= url('projet.php?id=' . $id . '&view=processus') ?>" class="<?= $view === 'processus' ? 'active' : '' ?>">
        <i class="fas fa-route"></i> Processus R1b
      </a>
      <a href="<?= url('projet.php?id=' . $id . '&view=taches') ?>" class="<?= $view === 'taches' ? 'active' : '' ?>">
        <i class="fas fa-tasks"></i> Tâches
      </a>
      <a href="<?= url('projet.php?id=' . $id . '&view=documents') ?>" class="<?= $view === 'documents' ? 'active' : '' ?>">
        <i class="fas fa-folder-open"></i> Documents
      </a>
      <a href="<?= url('cahier_form.php?projet_id=' . $id) ?>">
        <i class="fas fa-file-alt"></i> Cahier des charges
      </a>
    </nav>
    <div class="r1b-sidebar-foot">
      <a href="<?= url('projets.php') ?>"><i class="fas fa-arrow-left"></i> Tous les projets</a>
    </div>
  </aside>

  <div class="r1b-main">

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
                $done = $n < $currentStep;
                $active = $n === $currentStep;
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
                  <div class="dash-step-pct"><?= $done || $active ? (int)round(($n / 8) * 100) : 0 ?>%</div>
                </a>
              <?php endforeach; ?>
            </div>
            <div class="dash-progress-bar">
              <div class="dash-progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <p class="text-muted text-sm mt-2">
              Étape actuelle : <strong><?= e($steps[$currentStep]['title'] ?? '') ?></strong>
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
              <a href="<?= url('cahier_form.php?projet_id=' . $id) ?>" class="btn btn-secondary btn-sm">Éditer</a>
            </div>
            <?php if (empty($jalonsProjet)): ?>
              <p class="text-muted text-sm">Aucun jalon défini.</p>
              <a href="<?= url('cahier_form.php?projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Ajouter</a>
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
              <a href="<?= url('projet.php?id=' . $id . '&view=documents') ?>" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($documents)): ?>
              <p class="text-muted text-sm">Aucun document.</p>
            <?php else: ?>
              <div class="dash-doc-list">
                <?php foreach (array_slice($documents, 0, 5) as $d): ?>
                  <div class="dash-doc-row">
                    <i class="fas fa-file"></i>
                    <div>
                      <p class="dash-doc-name"><?= e($d['nom_original'] ?? $d['filename'] ?? 'Fichier') ?></p>
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
              <a href="<?= url('cahier_form.php?projet_id=' . $id) ?>">Ouvrir le cahier des charges</a>
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($view === 'processus'): ?>
      <!-- ========== PROCESSUS R1b ========== -->
      <div class="r1b-page-head">
        <div>
          <h2>Processus R1b – Conception</h2>
          <p class="text-muted text-sm">Projet : <strong><?= e($projet['nom']) ?></strong></p>
        </div>
      </div>

      <div class="r1b-stepper-wrap">
        <div class="r1b-stepper">
          <?php foreach ($steps as $n => $s): ?>
            <?php if ($n > 1): ?><div class="r1b-step-line <?= $n <= $currentStep ? 'on' : '' ?>"></div><?php endif; ?>
            <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=' . $n) ?>" class="<?= stepClass($n, $currentStep) ?>">
              <div class="r1b-step-circle"><?= $n < $currentStep ? '✓' : $n ?></div>
              <div class="r1b-step-label"><?= e($s['title']) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="r1b-card">
        <h3>Étape <?= $currentStep ?> – <?= e($steps[$currentStep]['title'] ?? '') ?></h3>

        <?php if ($currentStep === 1): ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">1. Contexte, objectifs et besoins utilisateurs</h4>
          <?php
            $hasContexte = trim($specs['objectifs'] ?? '') !== ''
                || trim($specs['resultats_attendus'] ?? '') !== ''
                || trim($specs['cas_usage'] ?? '') !== ''
                || trim($specs['profils_utilisateurs'] ?? '') !== '';
          ?>
          <?php if ($hasContexte): ?>
            <div class="r1b-info-box">
              <p><strong>Objectifs et contexte</strong></p>
              <p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['objectifs'] ?: '—') ?></p>
            </div>
            <div class="r1b-info-box">
              <p><strong>Hors périmètre du projet</strong></p>
              <p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['resultats_attendus'] ?: '—') ?></p>
            </div>
            <div class="r1b-info-box">
              <p><strong>Contraintes</strong></p>
              <p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['cas_usage'] ?: '—') ?></p>
            </div>
            <div class="r1b-info-box">
              <p><strong>Profils des utilisateurs finaux</strong></p>
              <p class="mt-1" style="white-space:pre-wrap;"><?= e($specs['profils_utilisateurs'] ?: '—') ?></p>
            </div>
          <?php else: ?>
            <p class="text-muted text-sm">Aucun contenu renseigné dans la section Contexte du CDC structuré.</p>
          <?php endif; ?>
          <a href="<?= url('cahier_form.php?projet_id=' . $id . '&step=1') ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-edit"></i> Éditer le contexte (CDC)</a>

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
              <a href="<?= url('cahier_form.php?projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-edit"></i> Ouvrir le CDC</a>
            </div>
          <?php else: ?>
            <form method="POST" id="formSpecsTech" action="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>">
              <input type="hidden" name="action" value="save_specs_techniques">
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
              <?php foreach ($fonctionsSF as $sf): ?>
                <?php
                  $sfId = $sf['id'] ?? '';
                  if (!preg_match('/^S\.F\.(\d+)$/', $sfId, $mSf)) continue;
                  $sfNum = (int)$mSf[1];
                  $rows = $techBySf[$sfId] ?? [];
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
                  </div>
                  <div class="sf-table-wrap">
                    <table class="sf-table st-table">
                      <thead>
                        <tr>
                          <th style="width:5.5rem">ID</th>
                          <th>Description</th>
                          <th style="width:8.5rem">Type</th>
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
                              <option value="Logiciel" <?= $ty === 'Logiciel' ? 'selected' : '' ?>>Logiciel</option>
                              <option value="3D" <?= $ty === '3D' ? 'selected' : '' ?>>3D</option>
                              <option value="PCB" <?= $ty === 'PCB' ? 'selected' : '' ?>>PCB</option>
                            </select>
                          </td>
                          <td><button type="button" class="btn-sf-del btn-st-del" title="Supprimer">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm mt-1 btn-st-add"><i class="fas fa-plus"></i> Ajouter une S.T.</button>
                </div>
              <?php endforeach; ?>
              <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les spécifications techniques</button>
                <a href="<?= url('cahier_form.php?projet_id=' . $id . '&step=2') ?>" class="btn btn-secondary btn-sm">Éditer les S.F. (CDC)</a>
              </div>
            </form>
          <?php endif; ?>

        <?php elseif ($currentStep === 3): ?>
          <p class="text-sm text-muted mb-2">Décision d'engagement du projet.</p>
          <?php if (!empty($projet['go_decision'])): ?>
            <div class="r1b-info-box">Décision actuelle : <strong><?= e($projet['go_decision']) ?></strong></div>
          <?php endif; ?>

        <?php elseif ($currentStep === 4): ?>
          <?php
            $techniquesAll = $specs['specs_techniques'] ?? [];
            $composantsSt = $specs['composants_st'] ?? [];
            if (!is_array($composantsSt)) $composantsSt = [];
            $users = $utilisateursListe ?? [];
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Composants & affectations</h4>
          <p class="text-sm text-muted mb-3">Pour chaque spécification technique (S.T.), ajoutez les lignes selon son type (Matériel, Logiciel, 3D, PCB).</p>
          <?php if (empty($techniquesAll)): ?>
            <div class="r1b-info-box">
              <p>Aucune spécification technique définie.</p>
              <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>" class="btn btn-primary btn-sm mt-2">Aller à l'étape 2</a>
            </div>
          <?php else: ?>
            <form method="POST" id="formComposantsSt" action="<?= url('projet.php?id=' . $id . '&view=processus&step=4') ?>">
              <input type="hidden" name="action" value="save_composants_st">
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
              <?php foreach ($techniquesAll as $stRow): ?>
                <?php
                  $stId = $stRow['id'] ?? '';
                  $stType = $stRow['type'] ?? 'Matériel';
                  $stDesc = $stRow['description'] ?? '';
                  if ($stId === '') continue;
                  $items = $composantsSt[$stId] ?? [];
                  if (empty($items)) {
                      $items = [[]]; // ligne vide
                  }
                  $prefix = match ($stType) {
                      'Matériel' => 'M',
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
                  </div>
                  <div class="sf-table-wrap">
                    <?php if ($stType === 'Matériel'): ?>
                      <table class="sf-table cp-table">
                        <thead>
                          <tr>
                            <th style="width:3.5rem">ID</th>
                            <th>Désignation</th>
                            <th>Référence</th>
                            <th>Fournisseur</th>
                            <th style="width:5rem">Qté</th>
                            <th style="width:9rem">Coût unitaire</th>
                            <th style="width:9rem">Coût total</th>
                            <th style="width:2.2rem"></th>
                          </tr>
                        </thead>
                        <tbody class="cp-body">
                          <?php foreach ($items as $ii => $it): ?>
                          <tr class="cp-row">
                            <td>
                              <span class="cp-id"><?= e($it['id'] ?? ($prefix . '.' . ($ii + 1))) ?></span>
                              <input type="hidden" name="cp_st_id[]" value="<?= e($stId) ?>">
                              <input type="hidden" name="cp_type[]" value="Matériel">
                            </td>
                            <td><input type="text" name="cp_designation[]" class="form-control" value="<?= e($it['designation'] ?? '') ?>"></td>
                            <td><input type="text" name="cp_reference[]" class="form-control" value="<?= e($it['reference'] ?? '') ?>"></td>
                            <td><input type="text" name="cp_fournisseur[]" class="form-control" value="<?= e($it['fournisseur'] ?? '') ?>"></td>
                            <td><input type="number" step="any" min="0" name="cp_quantite[]" class="form-control cp-qty" value="<?= e((string)($it['quantite'] ?? '')) ?>"></td>
                            <td>
                              <div class="cp-cost-cell">
                                <input type="number" step="any" min="0" name="cp_cout_unitaire[]" class="form-control cp-unit" value="<?= e((string)($it['cout_unitaire'] ?? '')) ?>">
                                <select name="cp_cout_unitaire_taxe[]" class="form-control cp-taxe">
                                  <option value="HT" <?= (($it['cout_unitaire_taxe'] ?? 'HT') === 'HT') ? 'selected' : '' ?>>HT</option>
                                  <option value="TTC" <?= (($it['cout_unitaire_taxe'] ?? '') === 'TTC') ? 'selected' : '' ?>>TTC</option>
                                </select>
                              </div>
                            </td>
                            <td>
                              <div class="cp-cost-cell">
                                <input type="text" class="form-control cp-total" value="<?= e((string)($it['cout_total'] ?? '')) ?>" readonly tabindex="-1">
                                <select name="cp_cout_total_taxe[]" class="form-control cp-taxe">
                                  <option value="HT" <?= (($it['cout_total_taxe'] ?? 'HT') === 'HT') ? 'selected' : '' ?>>HT</option>
                                  <option value="TTC" <?= (($it['cout_total_taxe'] ?? '') === 'TTC') ? 'selected' : '' ?>>TTC</option>
                                </select>
                              </div>
                              <!-- champs fantômes pour aligner les index des tableaux non-matériel -->
                              <input type="hidden" name="cp_affectation[]" value="">
                              <input type="hidden" name="cp_duree[]" value="">
                              <input type="hidden" name="cp_variation[]" value="">
                            </td>
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
                            <th style="width:9rem">Durée de réalisation</th>
                            <th style="width:9rem">Variation possible</th>
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
                            <td><input type="text" name="cp_duree[]" class="form-control" value="<?= e($it['duree'] ?? '') ?>" placeholder="ex. 3 j"></td>
                            <td>
                              <?php $vv = $it['variation'] ?? 'Moyenne'; ?>
                              <select name="cp_variation[]" class="form-control">
                                <option value="Forte" <?= $vv === 'Forte' ? 'selected' : '' ?>>Forte</option>
                                <option value="Moyenne" <?= $vv === 'Moyenne' ? 'selected' : '' ?>>Moyenne</option>
                                <option value="Faible" <?= $vv === 'Faible' ? 'selected' : '' ?>>Faible</option>
                              </select>
                            </td>
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
              <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les composants</button>
                <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>" class="btn btn-secondary btn-sm">Éditer les S.T. (étape 2)</a>
              </div>
            </form>
          <?php endif; ?>

        <?php elseif ($currentStep === 5): ?>
          <p class="text-sm text-muted mb-2">Fabrication et prototypage.</p>
          <?php foreach ($taches as $t): ?>
            <?php $st = $t['statut'] ?? 'a_faire'; ?>
            <div class="r1b-task-line">
              <span><?= e($t['titre'] ?? '') ?></span>
              <span class="text-muted"><?= e(statutLabel($st)) ?></span>
            </div>
          <?php endforeach; ?>

        <?php elseif ($currentStep === 6): ?>
          <p class="text-sm text-muted mb-2">Tests de conformité.</p>
          <div class="r1b-info-box">Validez la conformité ou signalez une non-conformité pour reboucler.</div>

        <?php elseif ($currentStep === 7): ?>
          <p class="text-sm text-muted mb-2">Livraison à la direction générale.</p>
          <ul class="r1b-list">
            <li>Dossier technique complet</li>
            <li>Rapport de tests</li>
            <li>CDC validé</li>
          </ul>

        <?php else: ?>
          <div class="r1b-info-box">Projet en phase d'archivage<?= (!empty($projet['go_decision']) && $projet['go_decision'] === 'NO_GO') ? ' (NO GO)' : '' ?>.</div>
        <?php endif; ?>

        <form method="POST" class="mt-3">
          <input type="hidden" name="action" value="save_notes">
          <input type="hidden" name="redir_view" value="processus">
          <label class="text-sm font-medium">Notes / résultats</label>
          <textarea name="step_notes" class="form-control" rows="3" placeholder="Notes, résultats, décisions…"><?= e($projet['step_notes'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-secondary btn-sm mt-1"><i class="fas fa-save"></i> Enregistrer les notes</button>
        </form>

        <div class="r1b-actions">
          <?php if ($currentStep === 3): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="GO">
              <button class="btn btn-success">GO</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="NO_GO">
              <button class="btn btn-danger">NO GO → Archivage</button></form>
          <?php elseif ($currentStep === 6 || $currentStep === 7): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="CONFORME">
              <button class="btn btn-success">✓ Conforme</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="NON_CONFORME">
              <button class="btn btn-danger">Non conforme</button></form>
          <?php elseif ($currentStep < 8): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="DONE">
              <button class="btn btn-primary">Valider l'étape</button></form>
          <?php endif; ?>
          <?php if ($currentStep > 1): ?>
            <a class="btn btn-secondary" href="<?= url('projet.php?id=' . $id . '&view=processus&step=' . ($currentStep - 1)) ?>">Étape précédente</a>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif ($view === 'taches'): ?>
      <!-- ========== TÂCHES (Kanban style Processus-R1b) ========== -->
      <?php
        ensureTachesExtendedColumns();
        $colsK = [
          'a_faire' => ['title' => 'À faire', 'bg' => 'kanban-col-todo', 'items' => []],
          'en_cours' => ['title' => 'En cours', 'bg' => 'kanban-col-progress', 'items' => []],
          'validation' => ['title' => 'En validation', 'bg' => 'kanban-col-validation', 'items' => []],
          'terminee' => ['title' => 'Terminé', 'bg' => 'kanban-col-done', 'items' => []],
        ];
        foreach ($taches as $t) {
            $ks = taskKanbanStatus($t);
            if (!isset($colsK[$ks])) $ks = 'a_faire';
            $colsK[$ks]['items'][] = $t;
        }
      ?>
      <div class="r1b-page-head">
        <div>
          <h2>Gestion des tâches</h2>
          <p class="text-muted text-sm">Tâches individuelles et de groupe — projet <?= e($projet['nom']) ?></p>
        </div>
        <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouvelle tâche</a>
      </div>
      <div class="r1b-kanban r1b-kanban-4">
        <?php foreach ($colsK as $key => $col): ?>
          <div class="r1b-kanban-col <?= e($col['bg']) ?>">
            <div class="r1b-kanban-head"><?= e($col['title']) ?> <span><?= count($col['items']) ?></span></div>
            <?php foreach ($col['items'] as $t): ?>
              <div class="r1b-kanban-card">
                <p class="font-medium"><?= e($t['titre'] ?? '') ?></p>
                <?php if (!empty($t['description'])): ?>
                  <p class="text-xs text-muted mt-1" style="white-space:pre-wrap;"><?= e(mb_strimwidth($t['description'], 0, 120, '…')) ?></p>
                <?php endif; ?>
                <div class="kanban-card-foot">
                  <span class="text-xs text-muted"><?= e($t['assigne_a'] ?? 'Non assigné') ?></span>
                  <form method="POST" action="<?= url('projet.php?id=' . $id . '&view=taches') ?>" class="kanban-status-form">
                    <input type="hidden" name="action" value="update_task_status">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                    <select name="status" class="kanban-status-select" onchange="this.form.submit()">
                      <option value="a_faire" <?= taskKanbanStatus($t) === 'a_faire' ? 'selected' : '' ?>>À faire</option>
                      <option value="en_cours" <?= taskKanbanStatus($t) === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                      <option value="validation" <?= taskKanbanStatus($t) === 'validation' ? 'selected' : '' ?>>En validation</option>
                      <option value="terminee" <?= taskKanbanStatus($t) === 'terminee' ? 'selected' : '' ?>>Terminé</option>
                    </select>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

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
              <h4><?= e($d['nom_original'] ?? $d['filename'] ?? 'Fichier') ?></h4>
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

<style>
.r1b-layout { display: grid; grid-template-columns: 220px 1fr; min-height: calc(100vh - 120px); gap: 0; }
.r1b-sidebar { background: #fff; border-right: 1px solid #e2e8f0; padding: 1rem 0; position: sticky; top: 60px; align-self: start; min-height: calc(100vh - 120px); }
.r1b-sidebar-title { font-weight: 700; font-size: .95rem; padding: 0 1rem 1rem; border-bottom: 1px solid #f1f5f9; margin-bottom: .5rem; color: #1e293b; word-break: break-word; }
.r1b-nav a { display: flex; align-items: center; gap: .6rem; padding: .65rem 1rem; font-size: .875rem; color: #64748b; text-decoration: none; border-right: 3px solid transparent; }
.r1b-nav a:hover { background: #f8fafc; color: #334155; }
.r1b-nav a.active { background: #ede9fe; color: #5b21b6; border-right-color: #7c3aed; font-weight: 600; }
.r1b-sidebar-foot { padding: 1rem; margin-top: 1rem; border-top: 1px solid #f1f5f9; }
.r1b-sidebar-foot a { font-size: .8rem; color: #64748b; text-decoration: none; }
.r1b-main { padding: 1.5rem; background: #f8fafc; }
.r1b-page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: .75rem; }
.r1b-page-head h2 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; }
.text-muted { color: #64748b; }
.text-sm { font-size: .875rem; }
.text-xs { font-size: .75rem; }
.mt-1 { margin-top: .25rem; }
.mt-2 { margin-top: .5rem; }
.mt-3 { margin-top: .75rem; }
.mb-2 { margin-bottom: .5rem; }
.mb-3 { margin-bottom: .75rem; }
.flex-between { display: flex; justify-content: space-between; align-items: center; }
.font-medium { font-weight: 500; }

/* KPI – disposition Tableau de bord R1b */
.dash-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.dash-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; }
.dash-kpi-label { font-size: .875rem; color: #64748b; margin: 0; }
.dash-kpi-value { font-size: 1.875rem; font-weight: 700; margin-top: .5rem; color: #0f172a; }

.dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
.dash-col-main, .dash-col-side { display: flex; flex-direction: column; gap: 0; }

.dash-steps { display: flex; flex-direction: column; gap: .5rem; }
.dash-step { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border: 1px solid #f1f5f9; border-radius: 10px; text-decoration: none; color: inherit; transition: border-color .15s; }
.dash-step:hover { border-color: #c4b5fd; background: #faf5ff; }
.dash-step.active { border-color: #a78bfa; background: #f5f3ff; }
.dash-step.done { border-color: #a7f3d0; }
.dash-step-num { width: 2rem; height: 2rem; border-radius: 50%; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem; flex-shrink: 0; }
.dash-step.active .dash-step-num { background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff; }
.dash-step.done .dash-step-num { background: #10b981; color: #fff; }
.dash-step-body { flex: 1; display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.dash-step-title { font-weight: 500; font-size: .875rem; }
.dash-step-badge { font-size: .7rem; padding: .15rem .5rem; border-radius: 999px; background: #ede9fe; color: #5b21b6; }
.dash-step-badge.done { background: #d1fae5; color: #047857; }
.dash-step-pct { font-size: .8rem; color: #64748b; font-weight: 500; }
.dash-progress-bar { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: 1rem; }
.dash-progress-fill { height: 100%; background: #7c3aed; border-radius: 999px; transition: width .3s; }

.dash-task-list { display: flex; flex-direction: column; }
.dash-task-row { display: flex; justify-content: space-between; align-items: center; padding: .6rem 0; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: inherit; font-size: .875rem; }
.dash-task-row:hover { color: #5b21b6; }
.dash-task-status { font-size: .7rem; padding: .15rem .45rem; border-radius: 999px; background: #f1f5f9; color: #475569; }
.dash-task-status.status-en_cours, .dash-task-status.status-in_progress { background: #dbeafe; color: #1d4ed8; }
.dash-task-status.status-terminee, .dash-task-status.status-done { background: #d1fae5; color: #047857; }

.dash-doc-list { display: flex; flex-direction: column; gap: .5rem; }
.dash-doc-row { display: flex; gap: .6rem; align-items: flex-start; font-size: .875rem; }
.dash-doc-row i { color: #94a3b8; margin-top: .15rem; }
.dash-doc-name { font-weight: 500; margin: 0; }

/* Stepper horizontal */
.r1b-stepper-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem; overflow-x: auto; }
.r1b-stepper { display: flex; align-items: flex-start; min-width: 860px; }
.r1b-step { display: flex; flex-direction: column; align-items: center; flex: 1; text-decoration: none; color: inherit; }
.r1b-step-circle { width: 2.25rem; height: 2.25rem; border-radius: 50%; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }
.r1b-step.active .r1b-step-circle { background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff; box-shadow: 0 4px 12px rgba(124,58,237,.35); }
.r1b-step.done .r1b-step-circle { background: #10b981; color: #fff; }
.r1b-step-label { font-size: .68rem; font-weight: 500; text-align: center; margin-top: .4rem; max-width: 100px; line-height: 1.2; }
.r1b-step.active .r1b-step-label { color: #5b21b6; font-weight: 700; }
.r1b-step-line { flex: 0.4; height: 4px; background: #e2e8f0; margin-top: 18px; border-radius: 2px; }
.r1b-step-line.on { background: #10b981; }

.r1b-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
.r1b-card h3 { font-size: 1.05rem; font-weight: 600; margin: 0 0 .75rem; }
.r1b-card h4 { font-size: .95rem; font-weight: 600; margin: 0; }
.r1b-info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: .75rem; font-size: .85rem; margin: .5rem 0; }
.r1b-list { padding-left: 1.1rem; font-size: .85rem; margin: .5rem 0; }
.r1b-task-line { display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid #f1f5f9; font-size: .85rem; }
.r1b-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; padding-top: .75rem; border-top: 1px solid #f1f5f9; }
.r1b-kanban { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.r1b-kanban-col { background: #f1f5f9; border-radius: 12px; padding: .75rem; min-height: 280px; }
.r1b-kanban-head { font-weight: 600; font-size: .85rem; margin-bottom: .6rem; display: flex; justify-content: space-between; }
.r1b-kanban-head span { background: #fff; border-radius: 999px; padding: 0 .45rem; font-size: .75rem; }
.r1b-kanban-card { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: .65rem; margin-bottom: .5rem; font-size: .85rem; text-decoration: none; color: inherit; }
.r1b-kanban-card:hover { border-color: #a78bfa; }
.r1b-docs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }

.form-control { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; }
.btn { display: inline-flex; align-items: center; gap: .35rem; padding: .5rem 1rem; border-radius: 8px; font-size: .875rem; font-weight: 500; border: none; cursor: pointer; text-decoration: none; }
.btn-sm { padding: .4rem .75rem; font-size: .8rem; }
.btn-primary { background: #7c3aed; color: #fff; }
.btn-primary:hover { background: #6d28d9; }
.btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.btn-success { background: #059669; color: #fff; }
.btn-danger { background: #dc2626; color: #fff; }

@media (max-width: 1100px) {
  .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .dash-grid { grid-template-columns: 1fr; }
}
@media (max-width: 900px) {
  .r1b-layout { grid-template-columns: 1fr; }
  .r1b-sidebar { border-right: none; border-bottom: 1px solid #e2e8f0; position: static; min-height: auto; }
  .r1b-kanban { grid-template-columns: 1fr; }
  .dash-kpi-grid { grid-template-columns: 1fr 1fr; }
}

.dash-jalon-list { display: flex; flex-direction: column; gap: .35rem; }
.dash-jalon-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
.dash-jalon-name { font-weight: 500; }
.dash-jalon-date { color: #64748b; font-size: .8rem; white-space: nowrap; }
.dash-jalon-row.soon .dash-jalon-date { color: #d97706; font-weight: 600; }
.dash-jalon-row.past .dash-jalon-date { color: #dc2626; }
.dash-jalon-row.past .dash-jalon-name { color: #94a3b8; text-decoration: line-through; }


.st-sf-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: .9rem 1rem; margin-bottom: 1rem; }
.st-sf-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .65rem; }
.st-sf-id { font-weight: 700; color: #5b21b6; font-family: ui-monospace, monospace; }
.st-sf-desc { flex: 1; font-size: .875rem; color: #334155; }
.st-sf-badge { font-size: .7rem; padding: .15rem .45rem; border-radius: 999px; background: #ede9fe; color: #5b21b6; }
.st-id { font-weight: 700; color: #0f766e; font-family: ui-monospace, monospace; font-size: .8rem; }
.sf-table { width: 100%; border-collapse: collapse; font-size: .85rem; background: #fff; }
.sf-table th, .sf-table td { border: 1px solid #e2e8f0; padding: .4rem .5rem; vertical-align: middle; }
.sf-table th { background: #f1f5f9; font-weight: 600; text-align: left; }
.btn-sf-del { background: transparent; border: none; color: #dc2626; font-size: 1.25rem; cursor: pointer; line-height: 1; padding: .15rem .35rem; border-radius: 4px; }
.btn-sf-del:hover { background: #fef2f2; }
.sf-table-wrap { overflow-x: auto; }


.cp-cost-cell { display: flex; gap: .25rem; align-items: center; }
.cp-cost-cell input { flex: 1; min-width: 0; }
.cp-cost-cell select.cp-taxe { width: 4.2rem; flex-shrink: 0; font-size: .75rem; padding: .25rem; }
.cp-id { font-weight: 700; color: #0f766e; font-family: ui-monospace, monospace; font-size: .8rem; }
.cp-table input.form-control, .cp-table select.form-control { font-size: .8rem; padding: .3rem .4rem; }

</style>

</main>
<footer class="footer">
  <div class="footer-container"><p>&copy; <?= date('Y') ?> ProjectFlow — Processus R1b</p></div>
</footer>

<script>
(function() {
  function renumberBlock(block) {
    const sfNum = block.getAttribute('data-sf-num');
    const sfId = block.getAttribute('data-sf');
    block.querySelectorAll('.st-row').forEach((row, i) => {
      const idSpan = row.querySelector('.st-id');
      if (idSpan) idSpan.textContent = 'S.T.' + sfNum + '.' + (i + 1);
      const hid = row.querySelector('input[name="st_sf[]"]');
      if (hid) hid.value = sfId;
    });
  }

  document.querySelectorAll('.st-sf-block').forEach(block => {
    const body = block.querySelector('.st-body');
    const sfNum = block.getAttribute('data-sf-num');
    const sfId = block.getAttribute('data-sf');

    function bindDel(btn) {
      btn.addEventListener('click', () => {
        const rows = body.querySelectorAll('.st-row');
        if (rows.length <= 1) {
          const row = rows[0];
          row.querySelector('input[type="text"]').value = '';
          row.querySelector('select').value = 'Matériel';
          renumberBlock(block);
          return;
        }
        btn.closest('.st-row').remove();
        renumberBlock(block);
      });
    }
    body.querySelectorAll('.btn-st-del').forEach(bindDel);

    const addBtn = block.querySelector('.btn-st-add');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const i = body.querySelectorAll('.st-row').length;
        const tr = document.createElement('tr');
        tr.className = 'st-row';
        tr.innerHTML =
          '<td><span class="st-id">S.T.' + sfNum + '.' + (i + 1) + '</span>' +
          '<input type="hidden" name="st_sf[]" value="' + sfId + '"></td>' +
          '<td><input type="text" name="st_description[]" class="form-control" value="" placeholder="Description technique…"></td>' +
          '<td><select name="st_type[]" class="form-control">' +
            '<option value="Matériel" selected>Matériel</option>' +
            '<option value="Logiciel">Logiciel</option>' +
            '<option value="3D">3D</option>' +
            '<option value="PCB">PCB</option>' +
          '</select></td>' +
          '<td><button type="button" class="btn-sf-del btn-st-del" title="Supprimer">&times;</button></td>';
        body.appendChild(tr);
        bindDel(tr.querySelector('.btn-st-del'));
        renumberBlock(block);
      });
    }
  });
})();
</script>


<script>
(function() {
  function renumber(block) {
    const prefix = block.getAttribute('data-prefix') || 'X';
    block.querySelectorAll('.cp-row').forEach((row, i) => {
      const idSpan = row.querySelector('.cp-id');
      if (idSpan) idSpan.textContent = prefix + '.' + (i + 1);
    });
  }

  function recalc(row) {
    const qty = parseFloat((row.querySelector('.cp-qty') || {}).value) || 0;
    const unit = parseFloat((row.querySelector('.cp-unit') || {}).value) || 0;
    const tot = row.querySelector('.cp-total');
    if (tot) tot.value = (qty * unit).toFixed(2);
  }

  document.querySelectorAll('.cp-st-block').forEach(block => {
    const body = block.querySelector('.cp-body');
    const type = block.getAttribute('data-st-type');
    const stId = block.getAttribute('data-st-id');
    const prefix = block.getAttribute('data-prefix');

    function bindRow(row) {
      const del = row.querySelector('.btn-cp-del');
      if (del) {
        del.addEventListener('click', () => {
          const rows = body.querySelectorAll('.cp-row');
          if (rows.length <= 1) {
            row.querySelectorAll('input:not([type="hidden"]), select').forEach(el => {
              if (el.tagName === 'SELECT') {
                if (el.options.length) el.selectedIndex = 0;
              } else if (!el.readOnly) {
                el.value = '';
              }
            });
            const tot = row.querySelector('.cp-total');
            if (tot) tot.value = '';
            renumber(block);
            return;
          }
          row.remove();
          renumber(block);
        });
      }
      const qty = row.querySelector('.cp-qty');
      const unit = row.querySelector('.cp-unit');
      if (qty) qty.addEventListener('input', () => recalc(row));
      if (unit) unit.addEventListener('input', () => recalc(row));
    }

    body.querySelectorAll('.cp-row').forEach(bindRow);

    const addBtn = block.querySelector('.btn-cp-add');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const i = body.querySelectorAll('.cp-row').length;
        const tr = document.createElement('tr');
        tr.className = 'cp-row';
        if (type === 'Matériel') {
          tr.innerHTML =
            '<td><span class="cp-id">' + prefix + '.' + (i+1) + '</span>' +
            '<input type="hidden" name="cp_st_id[]" value="' + stId + '">' +
            '<input type="hidden" name="cp_type[]" value="Matériel"></td>' +
            '<td><input type="text" name="cp_designation[]" class="form-control" value=""></td>' +
            '<td><input type="text" name="cp_reference[]" class="form-control" value=""></td>' +
            '<td><input type="text" name="cp_fournisseur[]" class="form-control" value=""></td>' +
            '<td><input type="number" step="any" min="0" name="cp_quantite[]" class="form-control cp-qty" value=""></td>' +
            '<td><div class="cp-cost-cell"><input type="number" step="any" min="0" name="cp_cout_unitaire[]" class="form-control cp-unit" value="">' +
            '<select name="cp_cout_unitaire_taxe[]" class="form-control cp-taxe"><option value="HT" selected>HT</option><option value="TTC">TTC</option></select></div></td>' +
            '<td><div class="cp-cost-cell"><input type="text" class="form-control cp-total" value="" readonly tabindex="-1">' +
            '<select name="cp_cout_total_taxe[]" class="form-control cp-taxe"><option value="HT" selected>HT</option><option value="TTC">TTC</option></select></div>' +
            '<input type="hidden" name="cp_affectation[]" value=""><input type="hidden" name="cp_duree[]" value=""><input type="hidden" name="cp_variation[]" value=""></td>' +
            '<td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>';
        } else {
          const userOpts = <?= json_encode(array_map(fn($u) => $u['identifiant'], $utilisateursListe ?? []), JSON_UNESCAPED_UNICODE) ?>;
          let opts = '<option value="">—</option>';
          (userOpts || []).forEach(u => { opts += '<option value="' + u.replace(/"/g, '&quot;') + '">' + u + '</option>'; });
          tr.innerHTML =
            '<td><span class="cp-id">' + prefix + '.' + (i+1) + '</span>' +
            '<input type="hidden" name="cp_st_id[]" value="' + stId + '">' +
            '<input type="hidden" name="cp_type[]" value="' + type + '">' +
            '<input type="hidden" name="cp_designation[]" value=""><input type="hidden" name="cp_reference[]" value="">' +
            '<input type="hidden" name="cp_fournisseur[]" value=""><input type="hidden" name="cp_quantite[]" value="">' +
            '<input type="hidden" name="cp_cout_unitaire[]" value=""><input type="hidden" name="cp_cout_unitaire_taxe[]" value="HT">' +
            '<input type="hidden" name="cp_cout_total_taxe[]" value="HT"></td>' +
            '<td><select name="cp_affectation[]" class="form-control">' + opts + '</select></td>' +
            '<td><input type="text" name="cp_duree[]" class="form-control" value="" placeholder="ex. 3 j"></td>' +
            '<td><select name="cp_variation[]" class="form-control"><option value="Forte">Forte</option><option value="Moyenne" selected>Moyenne</option><option value="Faible">Faible</option></select></td>' +
            '<td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>';
        }
        body.appendChild(tr);
        bindRow(tr);
        renumber(block);
      });
    }
  });
})();
</script>

<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
