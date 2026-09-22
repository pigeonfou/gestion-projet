<?php
$pageTitle = 'Paramètres';
$activePage = 'settings';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/NextcloudClient.php';
requerirAdmin();
seedSettingsIfEmpty();

$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test_nextcloud') {
        // Sauver d'abord les champs Nextcloud du formulaire pour tester les valeurs saisies
        foreach (['nextcloud_url','nextcloud_user','nextcloud_password','nextcloud_webdav','nextcloud_root'] as $k) {
            if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
        }
        $nc = new NextcloudClient();
        $testResult = $nc->testConnection();
    } else {
        $fields = [
            'site_nom', 'site_tagline',
            'nextcloud_url', 'nextcloud_user', 'nextcloud_password', 'nextcloud_webdav', 'nextcloud_root',
            'nextcloud_enabled',
            'items_per_page', 'timezone', 'date_format',
            'allow_all_mime', 'max_upload_mb',
        ];
        foreach ($fields as $f) {
            if ($f === 'nextcloud_enabled' || $f === 'allow_all_mime') {
                setSetting($f, isset($_POST[$f]) ? '1' : '0');
            } elseif (isset($_POST[$f])) {
                setSetting($f, trim($_POST[$f]));
            }
        }
        // Recalculer webdav si URL + user fournis et webdav vide
        $url = rtrim(getSetting('nextcloud_url', ''), '/');
        $user = getSetting('nextcloud_user', '');
        if ($url && $user && !trim($_POST['nextcloud_webdav'] ?? '')) {
            setSetting('nextcloud_webdav', $url . '/remote.php/dav/files/' . rawurlencode($user));
        }
        setFlash('success', 'Paramètres enregistrés.');
        redirect('admin/settings.php');
    }
}

$s = array_merge(defaultSettings(), getAllSettings());
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Paramètres</h1>
</div>

<?php if ($testResult): ?>
<div class="alert alert-<?= $testResult['ok'] ? 'success' : 'error' ?>">
    <i class="fas fa-<?= $testResult['ok'] ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= e($testResult['message']) ?>
</div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="save">

    <div class="card mb-2">
        <div class="card-header"><h2><i class="fas fa-globe"></i> Général</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Nom du site</label>
                    <input type="text" name="site_nom" class="form-control" value="<?= e($s['site_nom']) ?>">
                </div>
                <div class="form-group">
                    <label>Slogan</label>
                    <input type="text" name="site_tagline" class="form-control" value="<?= e($s['site_tagline']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Fuseau horaire</label>
                    <input type="text" name="timezone" class="form-control" value="<?= e($s['timezone']) ?>" placeholder="Europe/Paris">
                </div>
                <div class="form-group">
                    <label>Format de date</label>
                    <input type="text" name="date_format" class="form-control" value="<?= e($s['date_format']) ?>" placeholder="d/m/Y">
                </div>
            </div>
            <div class="form-group">
                <label>Éléments par page (listes)</label>
                <input type="number" name="items_per_page" class="form-control" value="<?= e($s['items_per_page']) ?>" min="5" max="200" style="max-width:120px;">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header"><h2><i class="fas fa-cloud"></i> Nextcloud</h2></div>
        <div class="card-body">
            <div class="form-check" style="margin-bottom:1rem;">
                <input type="checkbox" name="nextcloud_enabled" id="nc_en" value="1" <?= ($s['nextcloud_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <label for="nc_en">Activer l'intégration Nextcloud</label>
            </div>
            <div class="form-group">
                <label>URL du serveur</label>
                <input type="url" name="nextcloud_url" class="form-control" value="<?= e($s['nextcloud_url']) ?>" placeholder="https://nextcloud.exemple.com">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Utilisateur</label>
                    <input type="text" name="nextcloud_user" class="form-control" value="<?= e($s['nextcloud_user']) ?>">
                </div>
                <div class="form-group">
                    <label>Mot de passe / App Password</label>
                    <input type="password" name="nextcloud_password" class="form-control" value="<?= e($s['nextcloud_password']) ?>" autocomplete="new-password">
                </div>
            </div>
            <div class="form-group">
                <label>URL WebDAV</label>
                <input type="url" name="nextcloud_webdav" class="form-control" value="<?= e($s['nextcloud_webdav']) ?>"
                       placeholder="https://…/remote.php/dav/files/UTILISATEUR">
                <p class="text-muted text-sm mt-1">Si vide, calculée automatiquement à partir de l'URL + utilisateur.</p>
            </div>
            <div class="form-group">
                <label>Dossier racine distant</label>
                <input type="text" name="nextcloud_root" class="form-control" value="<?= e($s['nextcloud_root']) ?>" placeholder="ProjectFlow">
                <p class="text-muted text-sm mt-1">Arborescence : <code>/{racine}/Projet_{id}_{nom}/{phase}/fichier</code></p>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Taille max upload (Mo, 0 = illimité)</label>
                    <input type="number" name="max_upload_mb" class="form-control" value="<?= e($s['max_upload_mb']) ?>" min="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <label class="form-check">
                        <input type="checkbox" name="allow_all_mime" value="1" <?= ($s['allow_all_mime'] ?? '1') === '1' ? 'checked' : '' ?>>
                        Autoriser tous les types de fichiers
                    </label>
                </div>
            </div>
            <button type="submit" name="action" value="test_nextcloud" class="btn btn-secondary" formaction="" onclick="this.form.action.value='test_nextcloud'">
                <i class="fas fa-plug"></i> Tester la connexion
            </button>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        <a href="<?= url('index.php') ?>" class="btn btn-secondary">Retour</a>
    </div>
</form>

<script>
document.querySelector('button[value="test_nextcloud"]')?.addEventListener('click', function(e) {
    const form = this.closest('form');
    let act = form.querySelector('input[name="action"]');
    if (act) act.value = 'test_nextcloud';
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
