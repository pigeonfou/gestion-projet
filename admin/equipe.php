<?php
$pageTitle = 'Création groupée d’une équipe';
$activePage = 'utilisateurs';
require_once __DIR__ . '/../includes/bootstrap.php';
requerirAdmin();
$db = getDB();
$erreur = '';
$rows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $rows = is_array($_POST['membres'] ?? null) ? $_POST['membres'] : [];
    $validated = [];
    $seen = [];
    try {
        if (count($rows) < 1 || count($rows) > 9) {
            throw new InvalidArgumentException('Une équipe doit contenir de 1 à 9 membres.');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Membre invalide.');
            $login = trim((string)($row['identifiant'] ?? ''));
            if ($login === '') continue;
            $nom = trim((string)($row['nom_affiche'] ?? ''));
            $fonction = trim((string)($row['fonction'] ?? ''));
            $competences = trim((string)($row['competences'] ?? ''));
            $password = (string)($row['mot_de_passe'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/D', $login)) {
                throw new InvalidArgumentException('Identifiant invalide : lettres ASCII, chiffres, point, tiret et soulignement uniquement.');
            }
            if (isset($seen[strtolower($login)])) throw new InvalidArgumentException('Identifiant répété dans l’équipe.');
            $seen[strtolower($login)] = true;
            if (strlen($nom) > 200 || strlen($fonction) > 300 || strlen($competences) > 2000) {
                throw new InvalidArgumentException('Profil trop long.');
            }
            if (strlen($password) < 12 || strlen($password) > 72) {
                throw new InvalidArgumentException('Chaque membre renseigné doit avoir un mot de passe de 12 à 72 octets.');
            }
            $validated[] = [$login, password_hash($password, PASSWORD_DEFAULT), $nom, $fonction, $competences];
        }
        if (!$validated) throw new InvalidArgumentException('Renseignez au moins un membre.');
        $db->beginTransaction();
        $exists = $db->prepare('SELECT id FROM utilisateurs WHERE lower(identifiant)=lower(?)');
        $insert = $db->prepare("INSERT INTO utilisateurs (identifiant, mot_de_passe, role, nom_affiche, fonction, competences) VALUES (?,?,'utilisateur',?,?,?)");
        foreach ($validated as $values) {
            $exists->execute([$values[0]]);
            if ($exists->fetchColumn() !== false) throw new InvalidArgumentException('Un identifiant existe déjà : aucun compte créé.');
            $insert->execute($values);
        }
        $db->commit();
        setFlash('success', count($validated) . ' membres créés avec le rôle utilisateur.');
        redirect('admin/utilisateurs.php');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $erreur = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Enregistrement impossible : aucun compte créé.';
    }
    // Les mots de passe ne sont jamais réaffichés, même après une erreur.
    foreach ($rows as &$row) {
        if (is_array($row)) unset($row['mot_de_passe']);
    }
    unset($row);
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1>Créer une équipe</h1>
    <a href="<?= url('admin/utilisateurs.php') ?>" class="btn btn-secondary">Retour aux utilisateurs</a>
</div>
<div class="card"><div class="card-body">
    <p>Jusqu’à neuf comptes, tous avec le rôle utilisateur. Laissez l’identifiant vide pour ignorer une ligne. Les comptes existants ne sont jamais modifiés. Toute erreur annule la création du groupe.</p>
    <?php if ($erreur): ?><div class="error-msg" role="alert"><?= e($erreur) ?></div><?php endif; ?>
    <form method="POST" action="<?= url('admin/equipe.php') ?>" autocomplete="off">
        <?= csrfField() ?>
        <?php for ($i = 0; $i < 9; $i++): $row = is_array($rows[$i] ?? null) ? $rows[$i] : []; ?>
        <fieldset class="card" style="margin-bottom:1rem;padding:1rem;">
            <legend>Membre <?= $i + 1 ?></legend>
            <div class="form-group"><label for="login_<?= $i ?>">Identifiant <?= $i + 1 ?></label><input class="form-control" id="login_<?= $i ?>" name="membres[<?= $i ?>][identifiant]" maxlength="50" value="<?= e($row['identifiant'] ?? '') ?>"></div>
            <div class="form-group"><label for="nom_<?= $i ?>">Identité <?= $i + 1 ?></label><input class="form-control" id="nom_<?= $i ?>" name="membres[<?= $i ?>][nom_affiche]" maxlength="100" value="<?= e($row['nom_affiche'] ?? '') ?>"></div>
            <div class="form-group"><label for="fonction_<?= $i ?>">Fonction <?= $i + 1 ?></label><input class="form-control" id="fonction_<?= $i ?>" name="membres[<?= $i ?>][fonction]" maxlength="150" value="<?= e($row['fonction'] ?? '') ?>"></div>
            <div class="form-group"><label for="skills_<?= $i ?>">Compétences <?= $i + 1 ?></label><textarea class="form-control" id="skills_<?= $i ?>" name="membres[<?= $i ?>][competences]" maxlength="1000"><?= e($row['competences'] ?? '') ?></textarea></div>
            <div class="form-group"><label for="password_<?= $i ?>">Mot de passe <?= $i + 1 ?></label><input class="form-control" type="password" id="password_<?= $i ?>" name="membres[<?= $i ?>][mot_de_passe]" autocomplete="new-password" minlength="12" maxlength="72"></div>
        </fieldset>
        <?php endfor; ?>
        <button type="submit" class="btn btn-primary">Créer les membres</button>
    </form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
