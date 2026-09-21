<?php
$pageTitle = 'Tableau de bord';
$activePage = 'accueil';
require_once __DIR__ . '/includes/auth.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();

$nbProjets = $db->query('SELECT COUNT(*) FROM projets')->fetchColumn();
$nbTaches = $db->query('SELECT COUNT(*) FROM taches')->fetchColumn();
$nbTachesEnCours = $db->query("SELECT COUNT(*) FROM taches WHERE statut = 'en_cours'")->fetchColumn();
$nbTachesTerminees = $db->query("SELECT COUNT(*) FROM taches WHERE statut = 'terminee'")->fetchColumn();

$stmt = $db->query("
    SELECT p.*, u.identifiant AS createur,
           (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id) AS nb_taches
    FROM projets p
    JOIN utilisateurs u ON u.id = p.createur_id
    ORDER BY p.date_creation DESC
    LIMIT 6
");
$projets = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Tableau de bord</h1>
    <a href="<?= url('projets.php?action=creer') ?>" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouveau projet
    </a>
</div>

<div class="card-grid" style="margin-bottom:2rem;">
    <div class="card"><div class="card-body" style="text-align:center;">
        <div style="font-size:2rem;color:var(--primary);"><i class="fas fa-folder-open"></i></div>
        <div style="font-size:1.8rem;font-weight:700;"><?= (int)$nbProjets ?></div>
        <div class="text-muted">Projets</div>
    </div></div>
    <div class="card"><div class="card-body" style="text-align:center;">
        <div style="font-size:2rem;color:var(--info);"><i class="fas fa-tasks"></i></div>
        <div style="font-size:1.8rem;font-weight:700;"><?= (int)$nbTaches ?></div>
        <div class="text-muted">Tâches totales</div>
    </div></div>
    <div class="card"><div class="card-body" style="text-align:center;">
        <div style="font-size:2rem;color:var(--warning);"><i class="fas fa-spinner"></i></div>
        <div style="font-size:1.8rem;font-weight:700;"><?= (int)$nbTachesEnCours ?></div>
        <div class="text-muted">En cours</div>
    </div></div>
    <div class="card"><div class="card-body" style="text-align:center;">
        <div style="font-size:2rem;color:var(--success);"><i class="fas fa-check-circle"></i></div>
        <div style="font-size:1.8rem;font-weight:700;"><?= (int)$nbTachesTerminees ?></div>
        <div class="text-muted">Terminées</div>
    </div></div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> Derniers projets</h2>
        <a href="<?= url('projets.php') ?>" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <div class="card-body">
        <?php if (empty($projets)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>Aucun projet pour le moment.</p>
                <a href="<?= url('projets.php?action=creer') ?>" class="btn btn-primary mt-2">Créer le premier projet</a>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($projets as $p): ?>
                <div class="project-card">
                    <h3><?= e($p['nom']) ?></h3>
                    <p class="text-muted text-sm"><?= e(mb_strimwidth($p['description'] ?? '', 0, 100, '…')) ?></p>
                    <div class="project-meta">
                        <span><i class="fas fa-user"></i> <?= e($p['createur']) ?></span>
                        <span><i class="fas fa-tasks"></i> <?= (int)$p['nb_taches'] ?> tâche(s)</span>
                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($p['date_creation'])) ?></span>
                    </div>
                    <div class="project-actions">
                        <a href="<?= url('projet.php?id=' . (int)$p['id']) ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> Voir
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (estAdmin()): ?>
<div class="card" style="margin-top:2rem;">
    <div class="card-header">
        <h2><i class="fas fa-server"></i> Procédure de déploiement (Linux fraîchement installé)</h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">Guide pour installer ProjectFlow sur Ubuntu/Debian. Détails complets dans le fichier <code>README.md</code>.</p>
        <ol style="padding-left:1.25rem; line-height:1.8;">
            <li><strong>Mise à jour</strong> : <code>sudo apt update && sudo apt upgrade -y</code></li>
            <li><strong>Paquets</strong> : <code>sudo apt install -y apache2 php php-sqlite3 php-mbstring libapache2-mod-php git</code></li>
            <li><strong>Cloner</strong> :
<pre style="background:#f1f5f9;padding:.75rem;border-radius:6px;margin:.5rem 0;overflow-x:auto;">cd /var/www/html
sudo git clone https://github.com/pigeonfou/gestion-projet.git
sudo chown -R www-data:www-data gestion-projet</pre>
            </li>
            <li><strong>Initialiser la base</strong> :
<pre style="background:#f1f5f9;padding:.75rem;border-radius:6px;margin:.5rem 0;overflow-x:auto;">cd /var/www/html/gestion-projet
sudo -u www-data php install/init_database.php</pre>
            </li>
            <li><strong>Accéder</strong> : <code>http://IP_DU_SERVEUR/gestion-projet/</code></li>
            <li><strong>Connexion</strong> : <code>admin</code> / <code>admin123</code> → <strong>changez le mot de passe</strong></li>
            <li><strong>Pare-feu</strong> (optionnel) :
<pre style="background:#f1f5f9;padding:.75rem;border-radius:6px;margin:.5rem 0;overflow-x:auto;">sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable</pre>
            </li>
        </ol>
        <p class="text-sm text-muted mt-2">Le chemin de base est configurable dans <code>config/config.php</code> (<code>BASE_PATH</code>).</p>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
