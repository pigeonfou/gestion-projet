<?php
$pageTitle = 'Processus R1b';
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
$view = $_GET['view'] ?? 'processus'; // processus | taches | documents
$stepGet = isset($_GET['step']) ? (int)$_GET['step'] : 0;

if ($id <= 0) redirect('projets.php');

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

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_notes') {
        $notes = trim($_POST['step_notes'] ?? '');
        $db->prepare('UPDATE projets SET step_notes = ? WHERE id = ?')->execute([$notes, $id]);
        setFlash('success', 'Notes enregistrées.');
        redirect('projet.php?id=' . $id . '&view=processus&step=' . $currentStep);
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
                // rester sur tests
                $db->prepare('UPDATE projets SET current_step = 6 WHERE id = ?')->execute([$id]);
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
if ($stepGet >= 1 && $stepGet <= 8) $currentStep = $stepGet;
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

$pageTitle = $projet['nom'];
$useAppShell = true;
require __DIR__ . '/includes/header.php';

function stepClass(int $n, int $current): string {
    if ($n < $current) return 'r1b-step done';
    if ($n === $current) return 'r1b-step active';
    return 'r1b-step';
}
?>

<div class="r1b-layout">
  <!-- Sidebar projet -->
  <aside class="r1b-sidebar">
    <div class="r1b-sidebar-title"><?= e($projet['nom']) ?></div>
    <nav class="r1b-nav">
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
    <?php if ($view === 'processus'): ?>
      <div class="r1b-page-head">
        <div>
          <h2>Processus R1b – Conception</h2>
          <p class="text-muted text-sm">Projet : <strong><?= e($projet['nom']) ?></strong>
            <?php if ($projet['go_decision']): ?>
              · Décision : <strong><?= e($projet['go_decision']) ?></strong>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <!-- Stepper horizontal -->
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

      <!-- Contenu étape -->
      <div class="r1b-card">
        <h3>Étape <?= $currentStep ?> – <?= e($steps[$currentStep]['title']) ?></h3>

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
            <p><strong>Budget (cahier) :</strong> <?= e($cahier['budget'] ?: '—') ?></p>
          </div>
          <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Ajouter une charge</a>

        <?php elseif ($currentStep === 3): ?>
          <p class="text-sm text-muted mb-2">Décision d'engagement du projet.</p>
          <?php if ($projet['go_decision']): ?>
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
              <li><?= e($m['description']) ?></li>
            <?php endforeach; ?>
            <?php if (!$materiels): ?><li class="text-muted">Aucun matériel listé — renseignez le CDC ou les tâches.</li><?php endif; ?>
          </ul>

        <?php elseif ($currentStep === 5): ?>
          <p class="text-sm text-muted mb-2">Fabrication et prototypage.</p>
          <?php foreach ($taches as $t): ?>
            <div class="r1b-task-line">
              <span><?= e($t['titre']) ?></span>
              <span class="badge badge-<?= e($t['statut']) ?>"><?= e($t['statut']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$taches): ?><p class="text-muted text-sm">Aucune tâche.</p><?php endif; ?>

        <?php elseif ($currentStep === 6): ?>
          <p class="text-sm text-muted mb-2">Tests de conformité du prototype.</p>
          <div class="r1b-info-box">Validez la conformité ou signalez une non-conformité pour reboucler.</div>

        <?php elseif ($currentStep === 7): ?>
          <p class="text-sm text-muted mb-2">Livraison à la direction générale.</p>
          <?php
          $livrables = $db->prepare('SELECT * FROM livrables WHERE cahier_id = ?');
          $livrables->execute([$cahier_id]);
          $livrables = $livrables->fetchAll();
          ?>
          <ul class="r1b-list">
            <?php foreach ($livrables as $l): ?>
              <li><?= e($l['description']) ?><?= $l['date_livraison'] ? ' — ' . date('d/m/Y', strtotime($l['date_livraison'])) : '' ?></li>
            <?php endforeach; ?>
            <?php if (!$livrables): ?><li class="text-muted">Aucun livrable défini.</li><?php endif; ?>
          </ul>

        <?php else: ?>
          <p class="text-sm text-muted mb-2">Archivage du dossier et passage vers R2 Vente.</p>
          <div class="r1b-info-box">Projet en phase d'archivage<?= $projet['go_decision'] === 'NO_GO' ? ' (NO GO)' : '' ?>.</div>
        <?php endif; ?>

        <form method="POST" class="mt-2">
          <input type="hidden" name="action" value="save_notes">
          <label class="text-sm font-medium">Notes / résultats de l'étape</label>
          <textarea name="step_notes" class="form-control" rows="3" placeholder="Notes, résultats, décisions…"><?= e($projet['step_notes'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-secondary btn-sm mt-1"><i class="fas fa-save"></i> Enregistrer les notes</button>
        </form>

        <div class="r1b-actions">
          <?php if ($currentStep === 3): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="GO">
              <button class="btn btn-success">GO</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="decide"><input type="hidden" name="decision" value="NO_GO">
              <button class="btn btn-danger">NO GO → Archivage</button></form>
          <?php elseif ($currentStep === 6): ?>
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
        $st = $t['statut'] ?? 'a_faire';
        if (!isset($cols[$st])) $st = 'a_faire';
        $cols[$st]['items'][] = $t;
      }
      ?>
      <div class="r1b-kanban">
        <?php foreach ($cols as $key => $col): ?>
        <div class="r1b-kanban-col">
          <div class="r1b-kanban-head"><?= e($col['title']) ?> <span><?= count($col['items']) ?></span></div>
          <?php foreach ($col['items'] as $t): ?>
          <div class="r1b-kanban-card">
            <strong><?= e($t['titre']) ?></strong>
            <div class="text-muted text-sm mt-1">
              <span class="badge badge-<?= e($t['priorite']) ?>"><?= e($t['priorite']) ?></span>
              <a href="<?= url('tache.php?action=modifier&id=' . (int)$t['id']) ?>">Modifier</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>

    <?php else: /* documents */ ?>
      <div class="r1b-page-head">
        <div>
          <h2>Espace documentaire</h2>
          <p class="text-muted text-sm">Documents du projet</p>
        </div>
      </div>
      <?php if ($ncEnabled): ?>
      <form class="r1b-card" action="<?= url('actions/upload.php') ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="projet_id" value="<?= $id ?>">
        <input type="hidden" name="phase" value="<?= e($phase) ?>">
        <label class="btn btn-primary btn-sm" style="cursor:pointer;">
          Importer un fichier
          <input type="file" name="fichier" required style="display:none" onchange="this.form.submit()">
        </label>
      </form>
      <?php endif; ?>
      <div class="r1b-docs-grid">
        <?php foreach ($documents as $doc): ?>
        <div class="r1b-card">
          <h4><?= e($doc['nom_fichier']) ?></h4>
          <p class="text-muted text-sm"><?= date('d/m/Y H:i', strtotime($doc['date_upload'])) ?>
            · phase <?= e($doc['phase']) ?>
            <?php if ($doc['taille']): ?> · <?= number_format($doc['taille']/1024, 0) ?> Ko<?php endif; ?>
          </p>
        </div>
        <?php endforeach; ?>
        <?php if (!$documents): ?><p class="text-muted">Aucun document</p><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
.r1b-layout { display: grid; grid-template-columns: 220px 1fr; min-height: calc(100vh - var(--header-h)); background: #f8fafc; }
.r1b-sidebar { background: #fff; border-right: 1px solid #e2e8f0; padding: .75rem 0; }
.r1b-sidebar-title { padding: .75rem 1rem; font-weight: 700; font-size: .95rem; border-bottom: 1px solid #f1f5f9; word-break: break-word; }
.r1b-nav a { display: flex; align-items: center; gap: .5rem; padding: .65rem 1rem; color: #64748b; text-decoration: none; font-size: .85rem; font-weight: 500; border-right: 3px solid transparent; }
.r1b-nav a:hover { background: #f8fafc; color: #5b21b6; }
.r1b-nav a.active { background: #ede9fe; color: #5b21b6; border-right-color: #7c3aed; }
.r1b-sidebar-foot { padding: 1rem; border-top: 1px solid #f1f5f9; margin-top: 1rem; }
.r1b-sidebar-foot a { color: #64748b; font-size: .8rem; text-decoration: none; }
.r1b-main { padding: 1.25rem 1.5rem 2rem; max-width: 1100px; }
.r1b-page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.r1b-page-head h2 { font-size: 1.4rem; font-weight: 700; }
.r1b-stepper-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem; overflow-x: auto; }
.r1b-stepper { display: flex; align-items: flex-start; min-width: 860px; }
.r1b-step { flex: 1; display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #64748b; }
.r1b-step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }
.r1b-step.active .r1b-step-circle { background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff; box-shadow: 0 4px 12px rgba(124,58,237,.35); }
.r1b-step.done .r1b-step-circle { background: #10b981; color: #fff; }
.r1b-step-label { font-size: .68rem; font-weight: 500; text-align: center; margin-top: .4rem; max-width: 100px; line-height: 1.2; }
.r1b-step.active .r1b-step-label { color: #5b21b6; font-weight: 700; }
.r1b-step-line { flex: 0.4; height: 4px; background: #e2e8f0; margin-top: 18px; border-radius: 2px; }
.r1b-step-line.on { background: #10b981; }
.r1b-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
.r1b-card h3 { font-size: 1.05rem; font-weight: 600; margin-bottom: .75rem; }
.r1b-card h4 { font-size: .95rem; font-weight: 600; }
.r1b-info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: .75rem; font-size: .85rem; margin: .5rem 0; }
.r1b-list { padding-left: 1.1rem; font-size: .85rem; }
.r1b-task-line { display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid #f1f5f9; font-size: .85rem; }
.r1b-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; padding-top: .75rem; border-top: 1px solid #f1f5f9; }
.r1b-kanban { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.r1b-kanban-col { background: #f1f5f9; border-radius: 12px; padding: .75rem; min-height: 280px; }
.r1b-kanban-head { font-weight: 600; font-size: .85rem; margin-bottom: .6rem; display: flex; justify-content: space-between; }
.r1b-kanban-head span { background: #fff; border-radius: 999px; padding: 0 .45rem; font-size: .75rem; }
.r1b-kanban-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: .65rem; margin-bottom: .5rem; font-size: .85rem; }
.r1b-docs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
@media (max-width: 900px) {
  .r1b-layout { grid-template-columns: 1fr; }
  .r1b-sidebar { border-right: none; border-bottom: 1px solid #e2e8f0; }
  .r1b-kanban { grid-template-columns: 1fr; }
}
</style>

</main>
<footer class="footer">
  <div class="footer-container"><p>&copy; <?= date('Y') ?> ProjectFlow — Processus R1b</p></div>
</footer>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
