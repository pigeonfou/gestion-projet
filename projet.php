<?php
$pageTitle = 'Détail projet';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) redirect('projets.php');

$stmt = $db->prepare("SELECT p.*, u.identifiant AS createur FROM projets p JOIN utilisateurs u ON u.id = p.createur_id WHERE p.id = ?");
$stmt->execute([$id]);
$projet = $stmt->fetch();
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}

$stmt = $db->prepare('SELECT * FROM taches WHERE projet_id = ? ORDER BY CASE priorite WHEN "urgente" THEN 1 WHEN "haute" THEN 2 WHEN "moyenne" THEN 3 ELSE 4 END, date_creation DESC');
$stmt->execute([$id]);
$taches = $stmt->fetchAll();

$pageTitle = $projet['nom'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-folder-open"></i> <?= e($projet['nom']) ?></h1>
        <p class="text-muted text-sm mt-1">Créé par <?= e($projet['createur']) ?> le <?= date('d/m/Y', strtotime($projet['date_creation'])) ?></p>
    </div>
    <div class="flex gap-1">
        <a href="<?= url('cahier.php?projet_id=' . $id) ?>" class="btn btn-primary"><i class="fas fa-book"></i> Cahier des charges</a>
        <?php if ($projet['createur_id'] == $user['id'] || estAdmin()): ?>
        <a href="<?= url('projets.php?action=modifier&id=' . $id) ?>" class="btn btn-secondary"><i class="fas fa-edit"></i> Modifier</a>
        <?php endif; ?>
        <a href="<?= url('projets.php') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if ($projet['description']): ?>
<div class="card mb-2">
    <div class="card-body">
        <h3 style="margin-bottom:.5rem;font-size:1rem;">Description</h3>
        <p><?= nl2br(e($projet['description'])) ?></p>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-tasks"></i> Tâches (<?= count($taches) ?>)</h2>
        <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouvelle tâche</a>
    </div>
    <div class="card-body">
        <?php if (empty($taches)): ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>Aucune tâche pour ce projet.</p>
                <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary mt-2">Ajouter une tâche</a>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($taches as $t): ?>
                <div class="task-card">
                    <div class="flex justify-between items-center">
                        <h3 style="font-size:1rem;"><?= e($t['titre']) ?></h3>
                        <div class="flex gap-1">
                            <span class="badge badge-<?= e($t['priorite']) ?>"><?= e($t['priorite']) ?></span>
                            <span class="badge badge-<?= e($t['statut']) ?>">
                                <?= match($t['statut']) { 'a_faire' => 'À faire', 'en_cours' => 'En cours', 'terminee' => 'Terminée', default => $t['statut'] } ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($t['description']): ?>
                    <p class="text-muted text-sm"><?= e(mb_strimwidth($t['description'], 0, 100, '…')) ?></p>
                    <?php endif; ?>
                    <div class="project-meta">
                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($t['date_creation'])) ?></span>
                        <?php if ($t['date_echeance']): ?>
                        <span><i class="fas fa-flag"></i> Échéance : <?= date('d/m/Y', strtotime($t['date_echeance'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="project-actions">
                        <a href="<?= url('tache.php?action=modifier&id=' . (int)$t['id']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Modifier</a>
                        <a href="<?= url('tache.php?action=supprimer&id=' . (int)$t['id'] . '&projet_id=' . $id) ?>" class="btn btn-danger btn-sm" data-confirm="Supprimer cette tâche ?"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
