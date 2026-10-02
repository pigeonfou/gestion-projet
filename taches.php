<?php
$pageTitle = 'Tâches';
$activePage = 'taches';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/task_workflow.php';
require_once __DIR__ . '/includes/cahier_specs.php';
requerirConnexion();
runSchemaMigrations();

$db = getDB();
$user = utilisateurCourant();

    if (($_POST['action'] ?? '') === 'update_task_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfRequire();
        $aliases = ['todo'=>'a_faire','in_progress'=>'en_cours','done'=>'terminee'];
        $status = (string)($_POST['status'] ?? 'a_faire');
        $status = $aliases[$status] ?? $status;
        try {
            taskSetStatus($db, (int)($_POST['task_id'] ?? 0), $status, $user, null);
            setFlash('success', 'Statut de la tâche mis à jour.');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
        } catch (Throwable $e) {
            setFlash('error', 'Statut non enregistré.');
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
