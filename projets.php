<?php
$pageTitle = 'Projets';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$action = $_GET['action'] ?? 'liste';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $editId = (int)($_POST['id'] ?? 0);

    if ($nom === '') {
        setFlash('error', 'Le nom du projet est obligatoire.');
        redirect('projets.php?action=' . ($editId ? 'modifier&id=' . $editId : 'creer'));
    }

    try {
        if ($editId > 0) {
            $stmt = $db->prepare('SELECT createur_id FROM projets WHERE id = ?');
            $stmt->execute([$editId]);
            $proj = $stmt->fetch();
            if (!$proj || ($proj['createur_id'] != $user['id'] && !estAdmin())) {
                setFlash('error', 'Vous n\'êtes pas autorisé à modifier ce projet.');
                redirect('projets.php');
            }
            $stmt = $db->prepare('UPDATE projets SET nom = ?, description = ? WHERE id = ?');
            $stmt->execute([$nom, $description, $editId]);
            setFlash('success', 'Projet mis à jour avec succès.');
            redirect('projet.php?id=' . $editId);
        } else {
            $stmt = $db->prepare('INSERT INTO projets (nom, description, createur_id) VALUES (?, ?, ?)');
            $stmt->execute([$nom, $description, $user['id']]);
            $newId = $db->lastInsertId();
            setFlash('success', 'Projet créé avec succès.');
            redirect('projet.php?id=' . $newId);
        }
    } catch (PDOException $e) {
        setFlash('error', 'Erreur lors de l\'enregistrement.');
        redirect('projets.php');
    }
}

if ($action === 'supprimer' && $id > 0) {
    $stmt = $db->prepare('SELECT createur_id FROM projets WHERE id = ?');
    $stmt->execute([$id]);
    $proj = $stmt->fetch();
    if (!$proj) {
        setFlash('error', 'Projet introuvable.');
        redirect('projets.php');
    }
    $nbTaches = $db->prepare('SELECT COUNT(*) FROM taches WHERE projet_id = ?');
    $nbTaches->execute([$id]);
    $count = (int)$nbTaches->fetchColumn();

    if ($proj['createur_id'] != $user['id'] && !estAdmin()) {
        setFlash('error', 'Seul le créateur peut supprimer ce projet.');
    } elseif ($count > 0) {
        setFlash('error', 'Impossible de supprimer : des tâches sont associées.');
    } else {
        $db->prepare('DELETE FROM projets WHERE id = ?')->execute([$id]);
        setFlash('success', 'Projet supprimé.');
    }
    redirect('projets.php');
}

if ($action === 'creer' || $action === 'modifier') {
    $projet = null;
    if ($action === 'modifier' && $id > 0) {
        $stmt = $db->prepare('SELECT * FROM projets WHERE id = ?');
        $stmt->execute([$id]);
        $projet = $stmt->fetch();
        if (!$projet) {
            setFlash('error', 'Projet introuvable.');
            redirect('projets.php');
        }
        if ($projet['createur_id'] != $user['id'] && !estAdmin()) {
            setFlash('error', 'Accès refusé.');
            redirect('projets.php');
        }
    }
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="page-header">
        <h1><i class="fas fa-<?= $projet ? 'edit' : 'plus' ?>"></i> <?= $projet ? 'Modifier le projet' : 'Nouveau projet' ?></h1>
        <a href="<?= url('projets.php') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <div class="card" style="max-width:600px;">
        <div class="card-body">
            <form method="POST" action="<?= url('projets.php') ?>">
                <?php if ($projet): ?><input type="hidden" name="id" value="<?= (int)$projet['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="nom">Nom du projet *</label>
                    <input type="text" id="nom" name="nom" class="form-control" required value="<?= e($projet['nom'] ?? '') ?>" maxlength="150">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?= e($projet['description'] ?? '') ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <a href="<?= url('projets.php') ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tri = $_GET['tri'] ?? 'date_desc';
$order = match($tri) {
    'nom_asc' => 'p.nom ASC',
    'nom_desc' => 'p.nom DESC',
    'date_asc' => 'p.date_creation ASC',
    default => 'p.date_creation DESC'
};

$stmt = $db->query("
    SELECT p.*, u.identifiant AS createur,
           (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id) AS nb_taches
    FROM projets p
    JOIN utilisateurs u ON u.id = p.createur_id
    ORDER BY $order
");
$projets = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-folder-open"></i> Projets</h1>
    <div class="flex gap-1 items-center">
        <form method="GET" style="display:flex;gap:.5rem;">
            <select name="tri" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <option value="date_desc" <?= $tri === 'date_desc' ? 'selected' : '' ?>>Plus récents</option>
                <option value="date_asc" <?= $tri === 'date_asc' ? 'selected' : '' ?>>Plus anciens</option>
                <option value="nom_asc" <?= $tri === 'nom_asc' ? 'selected' : '' ?>>Nom A→Z</option>
                <option value="nom_desc" <?= $tri === 'nom_desc' ? 'selected' : '' ?>>Nom Z→A</option>
            </select>
        </form>
        <a href="<?= url('projets.php?action=creer') ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouveau projet
        </a>
    </div>
</div>

<?php if (empty($projets)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>Aucun projet trouvé.</p>
            <a href="<?= url('projets.php?action=creer') ?>" class="btn btn-primary mt-2">Créer un projet</a>
        </div>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($projets as $p):
            $peutSupprimer = ($p['createur_id'] == $user['id'] || estAdmin()) && (int)$p['nb_taches'] === 0;
        ?>
        <div class="project-card">
            <h3><?= e($p['nom']) ?></h3>
            <p class="text-muted text-sm"><?= e(mb_strimwidth($p['description'] ?? 'Pas de description', 0, 120, '…')) ?></p>
            <div class="project-meta">
                <span><i class="fas fa-user"></i> <?= e($p['createur']) ?></span>
                <span><i class="fas fa-tasks"></i> <?= (int)$p['nb_taches'] ?> tâche(s)</span>
                <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($p['date_creation'])) ?></span>
            </div>
            <div class="project-actions">
                <a href="<?= url('projet.php?id=' . (int)$p['id']) ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Voir</a>
                <?php if ($p['createur_id'] == $user['id'] || estAdmin()): ?>
                <a href="<?= url('projets.php?action=modifier&id=' . (int)$p['id']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                <?php endif; ?>
                <?php if ($peutSupprimer): ?>
                <a href="<?= url('projets.php?action=supprimer&id=' . (int)$p['id']) ?>" class="btn btn-danger btn-sm" data-confirm="Supprimer définitivement ce projet ?"><i class="fas fa-trash"></i></a>
                <?php else: ?>
                <button class="btn btn-danger btn-sm disabled" title="<?= (int)$p['nb_taches'] > 0 ? 'Des tâches sont associées' : 'Non autorisé' ?>"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
