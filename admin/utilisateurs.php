<?php
$pageTitle = 'Gestion des utilisateurs';
$activePage = 'utilisateurs';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__.'/../src/Services/AccountService.php';
requerirAdmin();

$db = getDB();
$action = $_GET['action'] ?? 'liste';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = utilisateurCourant();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    if (($_POST['form_action'] ?? '') === 'project_contribution') {
        $accountId = (int)($_POST['utilisateur_id'] ?? 0);
        try {
            projectSetContributor($db, (int)($_POST['projet_id'] ?? 0), $accountId, !empty($_POST['autorise']), $user);
            setFlash('success', 'Contribution au projet mise à jour.');
        } catch (InvalidArgumentException $e) { setFlash('error', $e->getMessage()); }
        redirect('admin/utilisateurs.php?action=modifier&id=' . $accountId);
    }
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $role = $_POST['role'] ?? 'utilisateur';
    $editId = (int)($_POST['id'] ?? 0);
    $nomAffiche = trim((string)($_POST['nom_affiche'] ?? ''));
    $fonction = trim((string)($_POST['fonction'] ?? ''));
    $competences = trim((string)($_POST['competences'] ?? ''));

    if (strlen($nomAffiche) > 200 || strlen($fonction) > 300 || strlen($competences) > 2000) {
        setFlash('error', 'Profil trop long : identité 200, fonction 300, compétences 2000 octets maximum.');
        redirect('admin/utilisateurs.php?action=' . ($editId ? 'modifier&id=' . $editId : 'creer'));
    }

    if ($identifiant === '' || !in_array($role, ['admin', 'utilisateur'])) {
        setFlash('error', 'Identifiant et rôle obligatoires.');
        redirect('admin/utilisateurs.php?action=' . ($editId ? 'modifier&id=' . $editId : 'creer'));
    }

    try {
        pfSaveAccount($db,$editId,['identifiant'=>$identifiant,'mot_de_passe'=>$mot_de_passe,'role'=>$role,'nom_affiche'=>$nomAffiche,'fonction'=>$fonction,'competences'=>$competences]);
        setFlash('success',$editId?'Utilisateur mis à jour.':'Utilisateur créé.');
        redirect('admin/utilisateurs.php');
    } catch (Throwable $e) {
        setFlash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Erreur lors de l\'enregistrement.');
        redirect('admin/utilisateurs.php');
    }
}

if ($action === 'creer' || $action === 'modifier') {
    $u = null;
    if ($action === 'modifier' && $id > 0) {
        $stmt = $db->prepare('SELECT id, identifiant, role, nom_affiche, fonction, competences FROM utilisateurs WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) {
            setFlash('error', 'Utilisateur introuvable.');
            redirect('admin/utilisateurs.php');
        }
    }
    $contributionProjects = [];
    if ($u) {
        projectMembersSchema($db);
        $q = $db->prepare('SELECT p.id,p.nom,COALESCE(m.actif,0) AS autorise FROM projets p LEFT JOIN projet_contributeurs m ON m.projet_id=p.id AND m.utilisateur_id=? ORDER BY p.nom');
        $q->execute([(int)$u['id']]);
        $contributionProjects = $q->fetchAll();
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
                <?= csrfField() ?>
                <?php if ($u): ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="identifiant">Identifiant *</label>
                    <input type="text" id="identifiant" name="identifiant" class="form-control" required value="<?= e($u['identifiant'] ?? '') ?>" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="nom_affiche">Identité / nom affiché</label>
                    <input type="text" id="nom_affiche" name="nom_affiche" class="form-control" value="<?= e($u['nom_affiche'] ?? '') ?>" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="fonction">Fonction</label>
                    <input type="text" id="fonction" name="fonction" class="form-control" value="<?= e($u['fonction'] ?? '') ?>" maxlength="150">
                </div>
                <div class="form-group">
                    <label for="competences">Compétences</label>
                    <textarea id="competences" name="competences" class="form-control" maxlength="1000" rows="3"><?= e($u['competences'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe <?= $u ? '(laisser vide pour ne pas changer)' : '*' ?></label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" <?= $u ? '' : 'required' ?> minlength="12" maxlength="72">
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
    <?php if ($u): ?>
    <div class="card" style="margin-top:1rem"><div class="card-body">
        <h2>Contributions par projet — <?= e($u['identifiant']) ?></h2>
        <p>Autorisez ce compte à modifier le cahier des charges, les documents Nextcloud et la qualité fournisseurs du projet choisi. Les décisions R1b et les autorisations restent réservées au créateur et aux administrateurs. Cette modification ne change ni le rôle ni le mot de passe.</p>
        <?php if ($u['role']==='admin'): ?><p>Ce compte est administrateur : il dispose déjà de ces accès. Retirer une contribution ne retire pas ses droits administrateur.</p><?php endif; ?>
        <table class="table"><thead><tr><th>Projet</th><th>Contribution</th></tr></thead><tbody>
        <?php foreach ($contributionProjects as $cp): ?><tr><td><?=e($cp['nom'])?></td><td>
            <form method="post"><?=csrfField()?><input type="hidden" name="form_action" value="project_contribution"><input type="hidden" name="utilisateur_id" value="<?=(int)$u['id']?>"><input type="hidden" name="projet_id" value="<?=(int)$cp['id']?>">
            <label for="contribution_<?=(int)$cp['id']?>"><input type="checkbox" id="contribution_<?=(int)$cp['id']?>" name="autorise" value="1" <?=$cp['autorise']?'checked':''?>> Autoriser la contribution</label>
            <button type="submit" class="btn btn-secondary">Enregistrer la contribution au projet <?=(int)$cp['id']?></button>
            </form>
        </td></tr><?php endforeach; ?></tbody></table>
    </div></div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$users = $db->query('SELECT id, identifiant, role, date_creation, nom_affiche, fonction, competences FROM utilisateurs ORDER BY identifiant')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-users"></i> Utilisateurs</h1>
    <a href="<?= url('admin/equipe.php') ?>" class="btn btn-secondary">Créer une équipe</a>
    <a href="<?= url('admin/utilisateurs.php?action=creer') ?>" class="btn btn-primary"><i class="fas fa-user-plus"></i> Nouvel utilisateur</a>
</div>
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Identifiant / identité</th><th>Fonction / compétences</th><th>Rôle</th><th>Date création</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['identifiant']) ?></strong><br><?= e($u['nom_affiche']) ?></td>
                    <td><?= e($u['fonction']) ?><br><span class="text-muted"><?= e($u['competences']) ?></span></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($u['date_creation'])) ?></td>
                    <td>
                        <a href="<?= url('admin/utilisateurs.php?action=modifier&id=' . (int)$u['id']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>

                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

