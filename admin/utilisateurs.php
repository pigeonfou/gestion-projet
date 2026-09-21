<?php
$pageTitle = 'Gestion des utilisateurs';
$activePage = 'utilisateurs';
require_once __DIR__ . '/../includes/auth.php';
requerirAdmin();

$db = getDB();
$action = $_GET['action'] ?? 'liste';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = utilisateurCourant();

if ($action === 'supprimer' && $id > 0) {
    if ($id === (int)$user['id']) {
        setFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    } else {
        $db->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$id]);
        setFlash('success', 'Utilisateur supprimé.');
    }
    redirect('admin/utilisateurs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $role = $_POST['role'] ?? 'utilisateur';
    $editId = (int)($_POST['id'] ?? 0);

    if ($identifiant === '' || !in_array($role, ['admin', 'utilisateur'])) {
        setFlash('error', 'Identifiant et rôle obligatoires.');
        redirect('admin/utilisateurs.php?action=' . ($editId ? 'modifier&id=' . $editId : 'creer'));
    }

    try {
        if ($editId > 0) {
            if ($mot_de_passe !== '') {
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE utilisateurs SET identifiant=?, mot_de_passe=?, role=? WHERE id=?');
                $stmt->execute([$identifiant, $hash, $role, $editId]);
            } else {
                $stmt = $db->prepare('UPDATE utilisateurs SET identifiant=?, role=? WHERE id=?');
                $stmt->execute([$identifiant, $role, $editId]);
            }
            setFlash('success', 'Utilisateur mis à jour.');
        } else {
            if ($mot_de_passe === '') {
                setFlash('error', 'Mot de passe obligatoire pour un nouvel utilisateur.');
                redirect('admin/utilisateurs.php?action=creer');
            }
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO utilisateurs (identifiant, mot_de_passe, role) VALUES (?,?,?)');
            $stmt->execute([$identifiant, $hash, $role]);
            setFlash('success', 'Utilisateur créé.');
        }
        redirect('admin/utilisateurs.php');
    } catch (PDOException $e) {
        setFlash('error', str_contains($e->getMessage(), 'UNIQUE') ? 'Cet identifiant existe déjà.' : 'Erreur lors de l\'enregistrement.');
        redirect('admin/utilisateurs.php');
    }
}

if ($action === 'creer' || $action === 'modifier') {
    $u = null;
    if ($action === 'modifier' && $id > 0) {
        $stmt = $db->prepare('SELECT id, identifiant, role FROM utilisateurs WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) {
            setFlash('error', 'Utilisateur introuvable.');
            redirect('admin/utilisateurs.php');
        }
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
        <h1><i class="fas fa-user-<?= $u ? 'edit' : 'plus' ?>"></i> <?= $u ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' ?></h1>
        <a href="<?= url('admin/utilisateurs.php') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <div class="card" style="max-width:500px;">
        <div class="card-body">
            <form method="POST">
                <?php if ($u): ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="identifiant">Identifiant *</label>
                    <input type="text" id="identifiant" name="identifiant" class="form-control" required value="<?= e($u['identifiant'] ?? '') ?>" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe <?= $u ? '(laisser vide pour ne pas changer)' : '*' ?></label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" <?= $u ? '' : 'required' ?> minlength="6">
                </div>
                <div class="form-group">
                    <label for="role">Rôle *</label>
                    <select id="role" name="role" class="form-control">
                        <option value="utilisateur" <?= ($u['role'] ?? '') === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                        <option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <a href="<?= url('admin/utilisateurs.php') ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$users = $db->query('SELECT id, identifiant, role, date_creation FROM utilisateurs ORDER BY identifiant')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-users"></i> Utilisateurs</h1>
    <a href="<?= url('admin/utilisateurs.php?action=creer') ?>" class="btn btn-primary"><i class="fas fa-user-plus"></i> Nouvel utilisateur</a>
</div>
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Identifiant</th><th>Rôle</th><th>Date création</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['identifiant']) ?></strong></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($u['date_creation'])) ?></td>
                    <td>
                        <a href="<?= url('admin/utilisateurs.php?action=modifier&id=' . (int)$u['id']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                        <a href="<?= url('admin/utilisateurs.php?action=supprimer&id=' . (int)$u['id']) ?>" class="btn btn-danger btn-sm" data-confirm="Supprimer cet utilisateur ?"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
