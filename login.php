<?php
require_once __DIR__ . '/includes/auth.php';
if (estConnecte()) {
    redirect('projets.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ProjectFlow</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="logo-login">
            <i class="fas fa-project-diagram"></i>
            <h1>ProjectFlow</h1>
            <p class="subtitle">Gestion de projets, processus & documentation</p>
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
        <p class="text-muted text-sm mt-2" style="text-align:center;">
            Compte démo : <strong>admin</strong> / <strong>admin123</strong>
        </p>
    </div>
</body>
</html>
