<?php
/** Partial kanban 4 colonnes. Vars: $taches, $statusFormAction, $showProjectLink, $id (optionnel) */
$taches = $taches ?? [];
$statusFormAction = $statusFormAction ?? url('taches.php');
$showProjectLink = $showProjectLink ?? false;
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
?>
<div class="r1b-kanban r1b-kanban-4">
  <?php foreach ($colsK as $col): ?>
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
              <?php if ($showProjectLink && !empty($t['projet_nom'])): ?>
                <a class="kanban-project-link" href="<?= url('projet.php?id=' . (int)$t['projet_id'] . '&view=taches') ?>">
                  <i class="fas fa-folder-open"></i> <?= e($t['projet_nom']) ?>
                </a>
              <?php endif; ?>
            </div>
            <form method="POST" action="<?= e($statusFormAction) ?>" class="kanban-status-form">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_task_status">
              <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
              <?php if (!empty($id)): ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">
              <?php elseif (!empty($t['projet_id'])): ?>
                <input type="hidden" name="id" value="<?= (int)$t['projet_id'] ?>">
              <?php endif; ?>
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
