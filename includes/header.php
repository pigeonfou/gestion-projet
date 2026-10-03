<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/ux_context.php';
$user = utilisateurCourant();
$flash = getFlash();
$useAppShell = $useAppShell ?? false;
seedSettingsIfEmpty();
$siteNom = getSetting('site_nom', 'ProjectFlow');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Gestion de Projet') ?> - <?= e($siteNom) ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/projet-r1b.css?v=dimensions-4') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/projectflow-ui.css?v=1') ?>">
</head>
<body class="pf-app">
    <header class="header">
        <div class="header-container">
            <a href="<?= url('projets.php') ?>" class="logo">
                <i class="fas fa-project-diagram"></i>
                <span><?= e($siteNom) ?></span>
            </a>
            <?php if ($user): ?>
            <nav class="nav" aria-label="Navigation principale">
                <a href="<?= url('projets.php') ?>" class="nav-link <?= in_array(($activePage ?? ''), ['accueil','projets'], true) ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i> Projets
                </a>
                <a href="<?= url('taches.php') ?>" class="nav-link <?= ($activePage ?? '') === 'taches' ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i> Tâches
                </a>
                <a href="<?= url('stocks.php') ?>" class="nav-link <?= ($activePage ?? '') === 'stocks' ? 'active' : '' ?>">
                    <i class="fas fa-boxes"></i> Stocks & Matériel
                </a>
                <a href="<?= url('stock_pilotage.php') ?>" class="nav-link <?= ($activePage ?? '') === 'stock_pilotage' ? 'active' : '' ?>">
                    <i class="fas fa-warehouse"></i> Stock avancé
                </a>
                <a href="<?= url('management.php') ?>" class="nav-link <?= ($activePage ?? '') === 'management' ? 'active' : '' ?>">
                    <i class="fas fa-sitemap"></i> Management
                </a>
            </nav>
            <a class="pf-search-launch" href="<?=url('recherche.php')?>" aria-label="Rechercher dans ProjectFlow"><i class="fas fa-search"></i><span>Rechercher</span><kbd>/</kbd></a>
            <div class="user-menu">
                <div class="user-dropdown">
                    <button type="button" class="user-dropdown-toggle" id="userMenuBtn" aria-expanded="false">
                        <i class="fas fa-user-circle"></i>
                        <?= e($user['identifiant']) ?>
                        <span class="badge-role"><?= e($user['role']) ?></span>
                        <i class="fas fa-caret-down" style="font-size:.75rem;opacity:.7;"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userMenu">
                        <?php if (estAdmin()): ?>
                        <a href="<?= url('admin/settings.php') ?>"><i class="fas fa-cog"></i> Paramètres</a>
                        <a href="<?= url('admin/utilisateurs.php') ?>"><i class="fas fa-users"></i> Utilisateurs</a>
                        <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <a href="<?= url('logout.php') ?>"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!$useAppShell): ?>
    <main class="main-content">
        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <?= e($flash['message']) ?>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" style="margin:0.75rem 1rem 0;">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= e($flash['message']) ?>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php endif; ?>
        <main>
    <?php endif; ?>

<?php if ($user && !$useAppShell) pfRenderContext($projet ?? null, $pageTitle ?? ''); ?>
