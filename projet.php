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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
        'validation' => 'Validation',
        default => $s,
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
          <p class="text-sm text-muted mb-2">Formaliser le besoin et le cahier des charges.</p>
          <?php $hasSpecs = trim($specs['objectifs'] ?? '') !== ''; ?>
          <?php if ($hasSpecs): ?>
            <div class="r1b-info-box">
              <p><strong>Objectifs :</strong> <?= e(mb_strimwidth($specs['objectifs'], 0, 220, '…')) ?></p>
              <p class="mt-1"><strong>Contexte :</strong> <?= e(implode(', ', $specs['contexte_utilisation'] ?: ['—'])) ?></p>
              <p class="mt-1"><strong>Durcissement :</strong> <?= e($specs['niveau_durcissement'] ?: '—') ?> · IP <?= e($specs['ip'] ?: '—') ?></p>
            </div>
          <?php else: ?>
            <p class="text-muted text-sm">Aucun CDC structuré renseigné.</p>
          <?php endif; ?>
          <a href="<?= url('cahier_form.php?projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-edit"></i> Rédiger / éditer le CDC</a>

        <?php elseif ($currentStep === 2): ?>
          <p class="text-sm text-muted mb-2">Études de capacité et besoins d'investissement.</p>
          <div class="r1b-info-box">
            <p><strong>Tâches / ressources :</strong> <?= count($taches) ?></p>
            <p><strong>Budget (cahier) :</strong> <?= e($cahier['budget'] ?? '—') ?></p>
          </div>
          <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Ajouter une charge</a>

        <?php elseif ($currentStep === 3): ?>
          <p class="text-sm text-muted mb-2">Décision d'engagement du projet.</p>
          <?php if (!empty($projet['go_decision'])): ?>
            <div class="r1b-info-box">Décision actuelle : <strong><?= e($projet['go_decision']) ?></strong></div>
          <?php endif; ?>

        <?php elseif ($currentStep === 4): ?>
          <p class="text-sm text-muted mb-2">Recherche et sélection des composants / matériels.</p>
          <?php
          $materiels = $db->prepare('SELECT * FROM materiel WHERE cahier_id = ?');
          $materiels->execute([$cahier_id]);
          $materiels = $materiels->fetchAll();
          ?>
          <ul class="r1b-list">
            <?php foreach ($materiels as $m): ?>
              <li><?= e($m['description'] ?? '') ?></li>
            <?php endforeach; ?>
            <?php if (empty($materiels)): ?><li class="text-muted">Aucun matériel listé</li><?php endif; ?>
          </ul>

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
      <!-- ========== TÂCHES (Kanban) ========== -->
      <div class="r1b-page-head">
        <div>
          <h2>Gestion des tâches</h2>
          <p class="text-muted text-sm">Tâches du projet <?= e($projet['nom']) ?></p>
        </div>
        <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouvelle tâche</a>
      </div>
      <?php
      $cols = [
          'a_faire' => ['title' => 'À faire', 'items' => []],
          'en_cours' => ['title' => 'En cours', 'items' => []],
          'terminee' => ['title' => 'Terminé', 'items' => []],
      ];
      foreach ($taches as $t) {
          $st = $t['statut'] ?? $t['status'] ?? 'a_faire';
          if (in_array($st, ['done', 'terminé', 'terminee'], true)) {
              $cols['terminee']['items'][] = $t;
          } elseif (in_array($st, ['en_cours', 'in_progress', 'validation'], true)) {
              $cols['en_cours']['items'][] = $t;
          } else {
              $cols['a_faire']['items'][] = $t;
          }
      }
      ?>
      <div class="r1b-kanban">
        <?php foreach ($cols as $key => $col): ?>
          <div class="r1b-kanban-col">
            <div class="r1b-kanban-head"><?= e($col['title']) ?> <span><?= count($col['items']) ?></span></div>
            <?php foreach ($col['items'] as $t): ?>
              <a href="<?= url('tache.php?id=' . (int)$t['id']) ?>" class="r1b-kanban-card">
                <p class="font-medium"><?= e($t['titre'] ?? $t['title'] ?? '') ?></p>
                <?php if (!empty($t['assigne_a'])): ?>
                  <p class="text-xs text-muted mt-1"><?= e($t['assigne_a']) ?></p>
                <?php endif; ?>
              </a>
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

</style>

</main>
<footer class="footer">
  <div class="footer-container"><p>&copy; <?= date('Y') ?> ProjectFlow — Processus R1b</p></div>
</footer>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
