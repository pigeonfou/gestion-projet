<?php
$pageTitle = 'Tâches';
$activePage = 'taches';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cahier_specs.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
ensureTachesExtendedColumns();

// Mise à jour statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_task_status') {
    $tid = (int)($_POST['task_id'] ?? 0);
    $st = $_POST['status'] ?? 'a_faire';
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
        $chk = $db->prepare('SELECT t.assigne_a, t.projet_id FROM taches t WHERE t.id = ?');
        $chk->execute([$tid]);
        $row = $chk->fetch();
        if (!$row) {
            setFlash('error', 'Tâche introuvable.');
        } elseif (!estAdmin() && (string)($row['assigne_a'] ?? '') !== (string)($user['identifiant'] ?? '')) {
            setFlash('error', 'Vous ne pouvez modifier que les tâches qui vous sont affectées.');
        } else {
            $db->prepare('UPDATE taches SET kanban_status = ?, statut = ? WHERE id = ?')
               ->execute([$kanban, $statutDb, $tid]);
            setFlash('success', 'Statut de la tâche mis à jour.');
        }
    }
    redirect('taches.php');
}

// Chargement : toutes les tâches (tous projets), filtrées par affectation si non-admin
$sql = 'SELECT t.*, p.nom AS projet_nom
        FROM taches t
        JOIN projets p ON p.id = t.projet_id';
$params = [];
if (!estAdmin()) {
    $sql .= ' WHERE t.assigne_a = ?';
    $params[] = $user['identifiant'] ?? '';
}
$sql .= ' ORDER BY t.id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$taches = $stmt->fetchAll();

$colsK = [
    'a_faire' => ['title' => 'À faire', 'bg' => 'kanban-col-todo', 'items' => []],
    'en_cours' => ['title' => 'En cours', 'bg' => 'kanban-col-progress', 'items' => []],
    'validation' => ['title' => 'En validation', 'bg' => 'kanban-col-validation', 'items' => []],
    'terminee' => ['title' => 'Terminé', 'bg' => 'kanban-col-done', 'items' => []],
];
foreach ($taches as $t) {
    $ks = taskKanbanStatus($t);
    if (!isset($colsK[$ks])) {
        $ks = 'a_faire';
    }
    $colsK[$ks]['items'][] = $t;
}

require __DIR__ . '/includes/header.php';

?>

<div class="taches-page-wrap">
  <div class="page-header">
      <div>
          <h1><i class="fas fa-tasks"></i> Gestion des tâches</h1>
          <p class="text-muted text-sm mt-1">
              <?php if (estAdmin()): ?>
                  Toutes les tâches de tous les projets
              <?php else: ?>
                  Vos tâches affectées — tous projets confondus
              <?php endif; ?>
          </p>
      </div>
  </div>

  <div class="r1b-kanban r1b-kanban-4">
      <?php foreach ($colsK as $key => $col): ?>
        <div class="r1b-kanban-col <?= e($col['bg']) ?>">
          <div class="r1b-kanban-head">
            <span class="kanban-col-title"><?= e($col['title']) ?></span>
            <span class="kanban-col-count"><?= count($col['items']) ?></span>
          </div>
          <?php if (empty($col['items'])): ?>
            <p class="text-muted text-sm" style="padding:.35rem .15rem;">Aucune tâche</p>
          <?php endif; ?>
          <?php foreach ($col['items'] as $t): ?>
            <div class="r1b-kanban-card">
              <p class="font-medium"><?= e($t['titre'] ?? '') ?></p>
              <?php if (!empty($t['description'])): ?>
                <p class="text-xs text-muted mt-1" style="white-space:pre-wrap;line-height:1.4;"><?= e(mb_strimwidth($t['description'], 0, 140, '…')) ?></p>
              <?php endif; ?>
              <div class="kanban-card-foot">
                <div>
                  <div class="kanban-assignee"><?= e($t['assigne_a'] ?? 'Non assigné') ?></div>
                  <?php if (!empty($t['projet_nom'])): ?>
                    <a class="kanban-project-link" href="<?= url('projet.php?id=' . (int)$t['projet_id'] . '&view=taches') ?>">
                      <i class="fas fa-folder-open"></i> <?= e($t['projet_nom']) ?>
                    </a>
                  <?php endif; ?>
                </div>
                <form method="POST" action="<?= url('taches.php') ?>" class="kanban-status-form">
                  <input type="hidden" name="action" value="update_task_status">
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
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
