<?php
$pageTitle = 'Mon apparence';
$activePage = 'apparence';
require_once __DIR__ . '/includes/bootstrap.php';
requerirConnexion();

$user = utilisateurCourant();
$userId = (int)$user['id'];
$presets = appearancePresets();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $preset = validAppearancePreset(is_string($_POST['ui_preset'] ?? null) ? $_POST['ui_preset'] : null);
    $theme = $_POST['ui_theme'] ?? '';
    $theme = in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'light';
    setUserPreference($userId, 'ui_preset', $preset);
    setUserPreference($userId, 'ui_theme', $theme);
    setFlash('success', 'Vos préférences d’apparence sont enregistrées.');
    redirect('apparence.php');
}

$selectedPreset = interfacePreset($userId);
$selectedTheme = interfaceTheme($userId);
require __DIR__ . '/includes/header.php';
?>

<section class="appearance-page">
    <header class="appearance-page-heading">
        <div>
            <p class="appearance-eyebrow">PRÉFÉRENCES PERSONNELLES</p>
            <h1><i class="fas fa-palette" aria-hidden="true"></i> Mon apparence</h1>
            <p class="text-muted">Choisissez le style OddWorks qui vous convient. Votre sélection suit votre compte sur vos appareils.</p>
        </div>
        <a class="btn btn-secondary" href="<?= url('projets.php') ?>"><i class="fas fa-arrow-left"></i> Retour aux projets</a>
    </header>

    <form method="post" class="appearance-form">
        <?= csrfField() ?>
        <section class="card card-body appearance-mode-panel" aria-labelledby="appearance-mode-title">
            <div>
                <p class="appearance-eyebrow">LUMINOSITÉ</p>
                <h2 id="appearance-mode-title">Mode d’affichage</h2>
                <p class="text-muted">Ce réglage reste indépendant du style visuel choisi ci-dessous.</p>
            </div>
            <label class="appearance-mode-select" for="appearance-mode">
                <span>Mode</span>
                <select class="form-control" id="appearance-mode" name="ui_theme">
                    <?php foreach (['light' => 'Clair', 'dark' => 'Sombre', 'system' => 'Système'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $selectedTheme === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </section>

        <div class="appearance-presets-heading">
            <div>
                <p class="appearance-eyebrow">STYLES D’INTERFACE</p>
                <h2>Choisissez votre OddWorks</h2>
                <p class="text-muted">Les onze nouveaux styles sont proposés avec l’interface actuelle, pour vous permettre de revenir à l’apparence classique.</p>
            </div>
            <span class="appearance-count">11 nouveaux styles + Classique</span>
        </div>

        <fieldset class="appearance-presets-grid" data-preset-selector>
            <legend class="sr-only">Style visuel OddWorks</legend>
            <?php foreach ($presets as $value => $preset): ?>
            <label class="appearance-preset-card" data-preset-card="<?= e($value) ?>">
                <input type="radio" name="ui_preset" value="<?= e($value) ?>" <?= $selectedPreset === $value ? 'checked' : '' ?>>
                <span class="appearance-preset-topline">
                    <span class="appearance-preset-name"><?= e($preset['label']) ?></span>
                    <span class="appearance-preset-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                </span>
                <span class="appearance-mini-ui" data-mini-preset="<?= e($value) ?>" aria-hidden="true">
                    <span class="appearance-mini-top"><i></i><i></i><i></i><b>ODDWORKS</b></span>
                    <span class="appearance-mini-sidebar"><i></i><i></i><i></i></span>
                    <span class="appearance-mini-main">
                        <span class="appearance-mini-title"></span>
                        <span class="appearance-mini-stats"><i></i><i></i><i></i></span>
                        <span class="appearance-mini-row"><i></i><i></i><i></i></span>
                    </span>
                </span>
                <span class="appearance-preset-description"><?= e($preset['description']) ?></span>
            </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="appearance-form-actions">
            <p class="text-muted">La sélection est prévisualisée sur cette page. Enregistrez pour l’appliquer à toute l’application.</p>
            <button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Enregistrer mes préférences</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
