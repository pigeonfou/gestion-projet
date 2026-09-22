<?php
require_once __DIR__ . '/auth.php';
$user = utilisateurCourant();
$flash = getFlash();
$useAppShell = $useAppShell ?? false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Gestion de Projet') ?> - ProjectFlow</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="<?= url('index.php') ?>" class="logo">
                <i class="fas fa-project-diagram"></i>
                <span>ProjectFlow</span>
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
                <span class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <?= e($user['identifiant']) ?>
                    <span class="badge-role"><?= e($user['role']) ?></span>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn-outline btn-sm" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
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
