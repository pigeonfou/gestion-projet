<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings_helper.php';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="<?= url('index.php') ?>" class="logo">
                <i class="fas fa-project-diagram"></i>
                <span><?= e($siteNom) ?></span>
            </a>
            <?php if ($user): ?>
            <nav class="nav">
                <a href="<?= url('index.php') ?>" class="nav-link <?= ($activePage ?? '') === 'accueil' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="<?= url('projets.php') ?>" class="nav-link <?= ($activePage ?? '') === 'projets' ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i> Projets
                </a>
                <?php if (estAdmin()): ?>
                <a href="<?= url('admin/utilisateurs.php') ?>" class="nav-link <?= ($activePage ?? '') === 'utilisateurs' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Utilisateurs
                </a>
                <?php endif; ?>
            </nav>
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
