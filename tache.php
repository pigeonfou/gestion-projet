<?php
$pageTitle = 'Tâche';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$projet_id = isset($_GET['projet_id']) ? (int)$_GET['projet_id'] : 0;

if ($action === 'supprimer' && $id > 0) {
    $stmt = $db->prepare('SELECT projet_id FROM taches WHERE id = ?');
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if ($t) {
        $db->prepare('DELETE FROM taches WHERE id = ?')->execute([$id]);
        setFlash('success', 'Tâche supprimée.');
        redirect('projet.php?id=' . (int)$t['projet_id']);
    }
    setFlash('error', 'Tâche introuvable.');
    redirect('projets.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priorite = $_POST['priorite'] ?? 'moyenne';
    $statut = $_POST['statut'] ?? 'a_faire';
    $date_echeance = $_POST['date_echeance'] ?? null;
    if ($date_echeance === '') $date_echeance = null;
    $editId = (int)($_POST['id'] ?? 0);
    $projet_id = (int)($_POST['projet_id'] ?? 0);

    $priorites = ['basse', 'moyenne', 'haute', 'urgente'];
    $statuts = ['a_faire', 'en_cours', 'terminee'];

    if ($titre === '' || $projet_id <= 0 || !in_array($priorite, $priorites) || !in_array($statut, $statuts)) {
        setFlash('error', 'Données invalides.');
        redirect('tache.php?action=' . ($editId ? 'modifier&id=' . $editId : 'creer&projet_id=' . $projet_id));
    }

    try {
        if ($editId > 0) {
            $stmt = $db->prepare('UPDATE taches SET titre=?, description=?, priorite=?, statut=?, date_echeance=? WHERE id=?');
            $stmt->execute([$titre, $description, $priorite, $statut, $date_echeance, $editId]);
            setFlash('success', 'Tâche mise à jour.');
            $stmt = $db->prepare('SELECT projet_id FROM taches WHERE id = ?');
            $stmt->execute([$editId]);
            $projet_id = (int)$stmt->fetchColumn();
        } else {
            $stmt = $db->prepare('INSERT INTO taches (projet_id, titre, description, priorite, statut, date_echeance) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$projet_id, $titre, $description, $priorite, $statut, $date_echeance]);
            setFlash('success', 'Tâche créée.');
        }
        redirect('projet.php?id=' . $projet_id);
    } catch (PDOException $e) {
        setFlash('error', 'Erreur lors de l\'enregistrement.');
        redirect('projets.php');
    }
}

$tache = null;
if ($action === 'modifier' && $id > 0) {
    $stmt = $db->prepare('SELECT * FROM taches WHERE id = ?');
    $stmt->execute([$id]);
    $tache = $stmt->fetch();
    if (!$tache) {
        setFlash('error', 'Tâche introuvable.');
        redirect('projets.php');
    }
    $projet_id = (int)$tache['projet_id'];
}

if ($action !== 'creer' && $action !== 'modifier') redirect('projets.php');
if ($projet_id <= 0) {
    setFlash('error', 'Projet non spécifié.');
    redirect('projets.php');
}

$stmt = $db->prepare('SELECT nom FROM projets WHERE id = ?');
$stmt->execute([$projet_id]);
$projetNom = $stmt->fetchColumn();
if (!$projetNom) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}

$pageTitle = $tache ? 'Modifier la tâche' : 'Nouvelle tâche';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-<?= $tache ? 'edit' : 'plus' ?>"></i> <?= $tache ? 'Modifier la tâche' : 'Nouvelle tâche' ?></h1>
    <a href="<?= url('projet.php?id=' . $projet_id) ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>
<div class="card" style="max-width:640px;">
    <div class="card-body">
        <p class="text-muted mb-2">Projet : <strong><?= e($projetNom) ?></strong></p>
        <form method="POST" action="<?= url('tache.php') ?>">
            <input type="hidden" name="projet_id" value="<?= $projet_id ?>">
            <?php if ($tache): ?><input type="hidden" name="id" value="<?= (int)$tache['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label for="titre">Titre *</label>
                <input type="text" id="titre" name="titre" class="form-control" required value="<?= e($tache['titre'] ?? '') ?>" maxlength="200">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= e($tache['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="priorite">Priorité</label>
                    <select id="priorite" name="priorite" class="form-control">
                        <?php foreach (['basse','moyenne','haute','urgente'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($tache['priorite'] ?? 'moyenne') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="statut">Statut</label>
                    <select id="statut" name="statut" class="form-control">
                        <option value="a_faire" <?= ($tache['statut'] ?? '') === 'a_faire' ? 'selected' : '' ?>>À faire</option>
                        <option value="en_cours" <?= ($tache['statut'] ?? '') === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="terminee" <?= ($tache['statut'] ?? '') === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="date_echeance">Date d'échéance</label>
                <input type="date" id="date_echeance" name="date_echeance" class="form-control" value="<?= e($tache['date_echeance'] ?? '') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="<?= url('projet.php?id=' . $projet_id) ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
