<?php
$pageTitle = 'Tâches';
$activePage = 'taches';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cahier_specs.php';
requerirConnexion();
runSchemaMigrations();

$db = getDB();
$user = utilisateurCourant();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_task_status') {
    csrfRequire();
    $tid = (int)($_POST['task_id'] ?? 0);
    $st = $_POST['status'] ?? 'a_faire';
    $map = [
        'todo' => 'a_faire', 'in_progress' => 'en_cours', 'done' => 'terminee',
        'validation' => 'validation', 'a_faire' => 'a_faire', 'en_cours' => 'en_cours', 'terminee' => 'terminee',
    ];
    $kanban = $map[$st] ?? 'a_faire';
    $statutDb = $kanban === 'validation' ? 'en_cours' : ($kanban === 'terminee' ? 'terminee' : ($kanban === 'en_cours' ? 'en_cours' : 'a_faire'));
    if ($tid > 0) {
        $chk = $db->prepare('SELECT assigne_a FROM taches WHERE id = ?');
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

$sql = 'SELECT t.*, p.nom AS projet_nom FROM taches t JOIN projets p ON p.id = t.projet_id';
$params = [];
if (!estAdmin()) {
    $sql .= ' WHERE t.assigne_a = ?';
    $params[] = $user['identifiant'] ?? '';
}
$sql .= ' ORDER BY t.id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$taches = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="taches-page-wrap">
  <div class="page-header">
    <div>
      <h1><i class="fas fa-tasks"></i> Gestion des tâches</h1>
      <p class="text-muted text-sm mt-1">
        <?php if (estAdmin()): ?>Toutes les tâches de tous les projets
        <?php else: ?>Vos tâches affectées — tous projets confondus<?php endif; ?>
      </p>
    </div>
  </div>
  <?php
    $statusFormAction = url('taches.php');
    $showProjectLink = true;
    require __DIR__ . '/includes/views/kanban.php';
  ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
