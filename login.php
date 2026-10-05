<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (estConnecte()) {
    redirect('projets.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - OddWorks</title>
    <meta name="theme-color" content="#081726">
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/oddworks-mark.svg') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css?v=night-4') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/oddworks-theme.css?v=night-4') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="login-page oddworks-login">
    <div class="oddworks-login-shell">
        <section class="oddworks-login-hero" aria-label="OddWorks">
            <div class="oddworks-login-brand">
                <img src="<?= url('assets/img/oddworks-mark.svg') ?>" alt="" width="74" height="74">
                <div>
                    <div class="oddworks-login-wordmark"><span>Odd</span><span>Works</span></div>
                    <p>DES IDÉES UN PEU DIFFÉRENTES<br>POUR DES PROJETS BIEN RÉELS</p>
                </div>
            </div>
            <div class="oddworks-login-orbit" aria-hidden="true">
                <span class="ow-orbit-ring"></span>
                <span class="ow-orbit-card ow-orbit-card-a"><i class="fas fa-lightbulb"></i></span>
                <span class="ow-orbit-card ow-orbit-card-b"><i class="fas fa-list-check"></i></span>
                <span class="ow-orbit-card ow-orbit-card-c"><i class="fas fa-cube"></i></span>
            </div>
            <p class="oddworks-login-promise">Un même espace pour relier idées, conception, tâches, coûts, délais, achats et production.</p>
        </section>
        <div class="login-card">
            <div class="logo-login">
                <img src="<?= url('assets/img/oddworks-mark.svg') ?>" alt="" width="52" height="52">
                <h1>Bienvenue dans <span class="ow-text-accent">OddWorks</span></h1>
                <p class="subtitle">Connectez-vous pour reprendre vos projets.</p>
            </div>
        <?php if (isset($_GET['erreur'])): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?php
                $msgs = [
                    'champs' => 'Veuillez remplir tous les champs.',
                    'identifiants' => 'Identifiant ou mot de passe incorrect.',
                    'session' => 'Votre session a expiré. Reconnectez-vous.'
                ];
                echo e($msgs[$_GET['erreur']] ?? 'Erreur de connexion.');
                ?>
            </div>
        <?php endif; ?>
        <form action="<?= url('verification_connexion.php') ?>" method="POST" autocomplete="off">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="identifiant"><i class="fas fa-user"></i> Identifiant</label>
                <input type="text" id="identifiant" name="identifiant" class="form-control" required autofocus placeholder="Votre identifiant">
            </div>
            <div class="form-group">
                <label for="mot_de_passe"><i class="fas fa-lock"></i> Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" required placeholder="Votre mot de passe">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>
        </div>
    </div>
</body>
</html>
