<?php
$pageTitle = 'Paramètres';
$activePage = 'settings';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/NextcloudClient.php';
requerirAdmin();
seedSettingsIfEmpty();

$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save_appearance') {
        setSetting('ui_theme', in_array($_POST['ui_theme'] ?? '', ['light', 'dark', 'system'], true) ? $_POST['ui_theme'] : 'light');
        setSetting('ui_accent', in_array($_POST['ui_accent'] ?? '', ['ocean', 'indigo', 'slate'], true) ? $_POST['ui_accent'] : 'ocean');
        setSetting('ui_density', ($_POST['ui_density'] ?? '') === 'compact' ? 'compact' : 'comfortable');
        setFlash('success', 'Apparence enregistrée.');
        redirect('admin/settings.php');
    }

    if ($action === 'test_nextcloud') {
        // Sauver d'abord les champs Nextcloud du formulaire pour tester les valeurs saisies
        foreach (['nextcloud_url','nextcloud_user','nextcloud_password','nextcloud_webdav','nextcloud_root'] as $k) {
            if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
        }
        $nc = new NextcloudClient();
        $testResult = $nc->testConnection();
    } else {
        $fields = [
            'site_nom', 'site_tagline', 'search_highlight_color',
            'nextcloud_url', 'nextcloud_user', 'nextcloud_password', 'nextcloud_webdav', 'nextcloud_root',
            'nextcloud_enabled',
            'items_per_page', 'timezone', 'date_format',
            'allow_all_mime', 'max_upload_mb',
        ];
        foreach ($fields as $f) {
            if ($f === 'search_highlight_color') {
                setSetting($f, searchHighlightColor(is_string($_POST[$f] ?? null) ? $_POST[$f] : ''));
            } elseif ($f === 'nextcloud_enabled' || $f === 'allow_all_mime') {
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

<form method="post" class="card card-body" style="margin-bottom:20px"><?=csrfField()?><input type="hidden" name="action" value="save_appearance"><h2>Apparence</h2><div class="form-row"><label>Thème de l’interface<select class="form-control" name="ui_theme"><?php foreach(['light'=>'Clair','dark'=>'Sombre','system'=>'Système'] as $value=>$label):?><option value="<?=e($value)?>" <?=interfaceTheme()===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>Accent de navigation<select class="form-control" name="ui_accent"><?php foreach(['ocean'=>'OddWorks cyan','indigo'=>'Violet créatif','slate'=>'Ardoise'] as $value=>$label):?><option value="<?=e($value)?>" <?=getSetting('ui_accent','ocean')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>Densité<select class="form-control" name="ui_density"><option value="comfortable" <?=getSetting('ui_density','comfortable')==='comfortable'?'selected':''?>>Confortable</option><option value="compact" <?=getSetting('ui_density','comfortable')==='compact'?'selected':''?>>Compacte</option></select></label></div><p class="text-muted">Ces réglages s’appliquent à toute l’application. Système suit automatiquement le thème du navigateur. La sélection, la validation et le refus gardent leurs significations. La densité compacte réduit les espacements des listes.</p><button class="btn btn-primary">Enregistrer l’apparence</button></form>
<form method="POST">
    <?=csrfField()?>
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
        <div class="card-header"><h2><i class="fas fa-palette"></i> Apparence</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="search-highlight-color">Couleur du surlignage des recherches</label>
                <input type="color" id="search-highlight-color" name="search_highlight_color" value="<?= e(searchHighlightColor($s['search_highlight_color'])) ?>">
                <p class="text-muted text-sm mt-1">Orange par défaut. Appliquée aux occurrences trouvées dans les tâches et l’historique des décisions, pour tout le site.</p>
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
                <input type="text" name="nextcloud_root" class="form-control" value="<?= e($s['nextcloud_root']) ?>" placeholder="OddWorks">
                <p class="text-muted text-sm mt-1">Arborescence : <code>/{racine}/Projet_{id}_{nom}/{phase}/fichier</code></p>
            </div>
            <p>Tous les types de fichiers sont acceptés, sans limite de taille imposée par OddWorks.</p>
            <button type="submit" name="action" value="test_nextcloud" class="btn btn-secondary" formaction="" onclick="this.form.action.value='test_nextcloud'">
                <i class="fas fa-plug"></i> Tester la connexion
            </button>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        <a href="<?= url('projets.php') ?>" class="btn btn-secondary">Retour</a>
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
