<?php
$pageTitle = 'Projets';
$activePage = 'projets';
require_once __DIR__ . '/includes/bootstrap.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$action = $_GET['action'] ?? 'liste';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {
    csrfRequire();
    $deleteId = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT createur_id FROM projets WHERE id = ?');
    $stmt->execute([$deleteId]);
    $proj = $stmt->fetch();
    if (!$proj) {
        setFlash('error', 'Projet introuvable.');
    } elseif ((int)$proj['createur_id'] !== (int)$user['id'] && !estAdmin()) {
        setFlash('error', 'Seul le créateur peut supprimer ce projet.');
    } else {
        $nbTaches = $db->prepare('SELECT COUNT(*) FROM taches WHERE projet_id = ?');
        $nbTaches->execute([$deleteId]);
        if ((int)$nbTaches->fetchColumn() > 0) {
            setFlash('error', 'Impossible de supprimer : des tâches sont associées.');
        } else {
            $db->prepare('DELETE FROM projets WHERE id = ?')->execute([$deleteId]);
            setFlash('success', 'Projet supprimé.');
        }
    }
    redirect('projets.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $editId = (int)($_POST['id'] ?? 0);

    if ($nom === '') {
        setFlash('error', 'Le nom du projet est obligatoire.');
        redirect('projets.php?action=' . ($editId ? 'modifier&id=' . $editId : 'creer'));
    }

    try {
        if ($editId > 0) {
            $stmt = $db->prepare('SELECT createur_id FROM projets WHERE id = ?');
            $stmt->execute([$editId]);
            $proj = $stmt->fetch();
            if (!$proj || ($proj['createur_id'] != $user['id'] && !estAdmin())) {
                setFlash('error', 'Vous n\'êtes pas autorisé à modifier ce projet.');
                redirect('projets.php');
            }
            $stmt = $db->prepare('UPDATE projets SET nom = ?, description = ? WHERE id = ?');
            $stmt->execute([$nom, $description, $editId]);
            setFlash('success', 'Projet mis à jour avec succès.');
            redirect('projet.php?id=' . $editId);
        } else {
            require_once __DIR__ . '/includes/r1b_steps.php';
            ensureProjectProcessColumns();
            $stmt = $db->prepare('INSERT INTO projets (nom, description, createur_id, current_step, status) VALUES (?, ?, ?, 0, ?)');
            $stmt->execute([$nom, $description, $user['id'], 'actif']);
            $newId = $db->lastInsertId();
            setFlash('success', 'Projet créé avec succès.');
            redirect('projet.php?id=' . $newId);
        }
    } catch (PDOException $e) {
        setFlash('error', 'Erreur lors de l\'enregistrement.');
        redirect('projets.php');
    }
}


if ($action === 'creer' || $action === 'modifier') {
    $projet = null;
    if ($action === 'modifier' && $id > 0) {
        $stmt = $db->prepare('SELECT * FROM projets WHERE id = ?');
        $stmt->execute([$id]);
        $projet = $stmt->fetch();
        if (!$projet) {
            setFlash('error', 'Projet introuvable.');
            redirect('projets.php');
        }
        if ($projet['createur_id'] != $user['id'] && !estAdmin()) {
            setFlash('error', 'Accès refusé.');
            redirect('projets.php');
        }
    }
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="page-header">
        <h1><i class="fas fa-<?= $projet ? 'edit' : 'plus' ?>"></i> <?= $projet ? 'Modifier le projet' : 'Nouveau projet' ?></h1>
        <a href="<?= url('projets.php') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <div class="card" style="max-width:600px;">
        <div class="card-body">
            <form method="POST" action="<?= url('projets.php') ?>">
                <?= csrfField() ?>
                <?php if ($projet): ?><input type="hidden" name="id" value="<?= (int)$projet['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="nom">Nom du projet *</label>
                    <input type="text" id="nom" name="nom" class="form-control" required value="<?= e($projet['nom'] ?? '') ?>" maxlength="150">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?= e($projet['description'] ?? '') ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <a href="<?= url('projets.php') ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

require_once __DIR__ . '/includes/r1b_steps.php';
ensureProjectProcessColumns();

$tri = $_GET['tri'] ?? 'date_desc';
$order = match($tri) {
    'nom_asc' => 'p.nom ASC',
    'nom_desc' => 'p.nom DESC',
    'date_asc' => 'p.date_creation ASC',
    default => 'p.date_creation DESC'
};

// Compter les jalons via cahiers (colonne cahier_id, pas projet_id)
$jalonsSub = '0';
try {
    $db->query('SELECT 1 FROM jalons LIMIT 1');
    $db->query('SELECT 1 FROM cahiers LIMIT 1');
    $jalonsSub = "(SELECT COUNT(*) FROM jalons j
              INNER JOIN cahiers c ON c.id = j.cahier_id
              WHERE c.projet_id = p.id)";
} catch (Throwable $e) {
    $jalonsSub = '0';
}

$stmt = $db->query("
    SELECT p.*, u.identifiant AS createur,
           (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id) AS nb_taches,
           (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.statut IN ('a_faire','en_cours')) AS nb_taches_ouvertes,
           $jalonsSub AS nb_jalons
    FROM projets p
    JOIN utilisateurs u ON u.id = p.createur_id
    ORDER BY $order
");
$projets = $stmt->fetchAll();

$steps = r1bSteps();

/**
 * Classification archivage :
 * - Abandonnés : décision NO_GO (étape 3) ou status archive à l'étape 3
 * - Validé/vente : étape 8 sans NO_GO (ou status archive à l'étape 8)
 * - En cours : le reste
 */
$isAbandonne = static function (array $p): bool {
    $cs = (int)($p['current_step'] ?? 1);
    $st = $p['status'] ?? 'actif';
    $go = $p['go_decision'] ?? '';
    return $go === 'NO_GO' || ($st === 'archive' && $cs === 3);
};
$isValideVente = static function (array $p) use ($isAbandonne): bool {
    if ($isAbandonne($p)) {
        return false;
    }
    $cs = (int)($p['current_step'] ?? 1);
    $st = $p['status'] ?? 'actif';
    return $cs === 8 || ($st === 'archive' && $cs === 8) || $st === 'termine';
};

$projetsEnCours = [];
$projetsAbandonnes = [];
$projetsValides = [];
foreach ($projets as $p) {
    if ($isAbandonne($p)) {
        $projetsAbandonnes[] = $p;
    } elseif ($isValideVente($p)) {
        $projetsValides[] = $p;
    } else {
        $projetsEnCours[] = $p;
    }
}

// Stats globales (alignées Tableau de bord Processus-R1b)
$nbProjetsActifs = count($projetsEnCours);
$nbTachesOuvertes = 0;
$nbValidationsPending = 0;
$nbJalonsTotal = 0;
foreach ($projets as $p) {
    $nbTachesOuvertes += (int)($p['nb_taches_ouvertes'] ?? 0);
    $nbJalonsTotal += (int)($p['nb_jalons'] ?? 0);
    $cs = (int)($p['current_step'] ?? 1);
    if ($cs === 3 && empty($p['go_decision'])) {
        $nbValidationsPending++;
    }
}

/** Affiche une carte projet (réutilisé pour en cours / archivage). */
$renderProjetCard = static function (array $p, array $steps, array $user) use ($isAbandonne): void {
    $cs = r1bClampStep((int)($p['current_step'] ?? 0));
    $pct = (int)round(($cs / r1bMaxStep()) * 100);
    $stepLabel = $steps[$cs]['title'] ?? ($p['status'] ?? 'actif');
    if ($isAbandonne($p)) {
        $stepLabel = 'Abandonné (NO GO)';
        $pct = (int)round((3 / r1bMaxStep()) * 100);
    } elseif ($cs === 8) {
        $stepLabel = 'Validé / Vente';
    }
    $peutSupprimer = ($p['createur_id'] == $user['id'] || estAdmin()) && (int)$p['nb_taches'] === 0;
    $urlProjet = url('projet.php?id=' . (int)$p['id']);
    ?>
    <div class="dash-proj-item" onclick="if(!event.target.closest('a,button')) location.href='<?= $urlProjet ?>'">
      <div class="dash-proj-top">
        <div>
          <span class="dash-proj-name"><?= e($p['nom']) ?></span>
          <span class="dash-proj-badge"><?= e($stepLabel) ?></span>
        </div>
        <span class="dash-proj-pct"><?= $pct ?>%</span>
      </div>
      <div class="dash-proj-bar">
        <div class="dash-proj-fill" style="width:<?= $pct ?>%"></div>
      </div>
      <p class="dash-proj-meta">
        <?= e($p['createur']) ?> · <?= (int)$p['nb_taches'] ?> tâche(s) · <?= (int)($p['nb_jalons'] ?? 0) ?> jalon(s)
        <?php if (!empty($p['description'])): ?>
          · <?= e(mb_strimwidth($p['description'], 0, 80, '…')) ?>
        <?php endif; ?>
      </p>
      <div class="dash-proj-actions" onclick="event.stopPropagation()">
        <a href="<?= $urlProjet ?>" class="btn-r1b btn-r1b-primary btn-r1b-sm"><i class="fas fa-eye"></i> Voir</a>
        <?php if ($p['createur_id'] == $user['id'] || estAdmin()): ?>
        <a href="<?= url('projets.php?action=modifier&id=' . (int)$p['id']) ?>" class="btn-r1b btn-r1b-secondary btn-r1b-sm"><i class="fas fa-edit"></i></a>
        <?php endif; ?>
        <?php if ($peutSupprimer): ?>
        <form method="POST" action="<?= url('projets.php') ?>" style="display:inline" onsubmit="return confirm('Supprimer définitivement ce projet ?');">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="supprimer">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button type="submit" class="btn-r1b btn-r1b-danger btn-r1b-sm"><i class="fas fa-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
};

require __DIR__ . '/includes/header.php';
?>
<style>
/* Tableau de bord Projets – style Processus-R1b */
.dash-wrap { max-width: 1100px; margin: 0 auto; }
.dash-page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem; }
.dash-page-head h2 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; }
.dash-page-head .subtitle { color: #64748b; font-size: .875rem; margin-top: .25rem; }
.dash-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
.dash-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; }
.dash-kpi-label { font-size: .875rem; color: #64748b; margin: 0; }
.dash-kpi-value { font-size: 1.875rem; font-weight: 700; margin-top: .5rem; color: #0f172a; }
.dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
.dash-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; }
.dash-card h3 { font-size: 1.05rem; font-weight: 600; margin: 0 0 1rem; color: #0f172a; }
.dash-proj-list { display: flex; flex-direction: column; gap: .75rem; }
.dash-proj-item {
  border: 1px solid #f1f5f9; border-radius: 10px; padding: 1rem;
  cursor: pointer; transition: border-color .15s, background .15s; text-decoration: none; color: inherit; display: block;
}
.dash-proj-item:hover { border-color: #c4b5fd; background: #faf5ff; }
.dash-proj-top { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; flex-wrap: wrap; }
.dash-proj-name { font-weight: 600; font-size: .95rem; color: #0f172a; }
.dash-proj-badge {
  display: inline-block; margin-left: .5rem; padding: .15rem .55rem; border-radius: 999px;
  font-size: .7rem; font-weight: 500; background: #ede9fe; color: #5b21b6; vertical-align: middle;
}
.dash-proj-pct { font-size: .875rem; color: #64748b; font-weight: 500; white-space: nowrap; }
.dash-proj-bar { margin-top: .5rem; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.dash-proj-fill { height: 100%; background: #7c3aed; border-radius: 999px; transition: width .3s; }
.dash-proj-meta { margin-top: .5rem; font-size: .75rem; color: #64748b; }
.dash-proj-actions { margin-top: .65rem; display: flex; gap: .4rem; flex-wrap: wrap; }
.dash-side-item { padding: .65rem 0; border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
.dash-side-item:last-child { border-bottom: none; }
.dash-side-title { font-weight: 500; margin: 0 0 .15rem; }
.dash-side-meta { font-size: .75rem; color: #64748b; }
.btn-r1b { display: inline-flex; align-items: center; gap: .35rem; padding: .55rem 1rem; border-radius: 8px; font-size: .875rem; font-weight: 500; border: none; cursor: pointer; text-decoration: none; }
.btn-r1b-primary { background: #7c3aed; color: #fff; }
.btn-r1b-primary:hover { background: #6d28d9; color: #fff; }
.btn-r1b-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.btn-r1b-secondary:hover { background: #e2e8f0; }
.btn-r1b-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.btn-r1b-danger:hover { background: #fecaca; }
.btn-r1b-sm { padding: .35rem .65rem; font-size: .8rem; }
.dash-toolbar { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.dash-toolbar select.form-control { width: auto; min-width: 140px; border-radius: 8px; border: 1px solid #e2e8f0; padding: .4rem .65rem; font-size: .85rem; }
.dash-empty { text-align: center; padding: 2.5rem 1rem; color: #64748b; }
.dash-empty i { font-size: 2.5rem; opacity: .35; margin-bottom: .75rem; display: block; }
.dash-archive-wrap { margin-top: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.dash-archive-section { border: 1px solid #f1f5f9; border-radius: 10px; padding: 1rem; background: #f8fafc; }
.dash-archive-section h4 {
  font-size: .9rem; font-weight: 600; margin: 0 0 .75rem; color: #334155;
  display: flex; align-items: center; gap: .4rem;
}
.dash-archive-section h4 .count {
  font-size: .7rem; font-weight: 500; padding: .1rem .45rem; border-radius: 999px;
  background: #e2e8f0; color: #475569;
}
.dash-archive-section.abandonnes h4 { color: #b91c1c; }
.dash-archive-section.abandonnes h4 .count { background: #fee2e2; color: #b91c1c; }
.dash-archive-section.valides h4 { color: #047857; }
.dash-archive-section.valides h4 .count { background: #d1fae5; color: #047857; }
.dash-archive-section .dash-empty { padding: 1rem; font-size: .85rem; }
.dash-col-main { display: flex; flex-direction: column; gap: 1.25rem; }
@media (max-width: 900px) {
  .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .dash-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .dash-kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="dash-wrap">
  <div class="dash-page-head">
    <div>
      <h2>Tableau de bord</h2>
      <p class="subtitle">Vue d'ensemble des projets de conception</p>
    </div>
    <div class="dash-toolbar">
      <form method="GET" style="display:flex;gap:.5rem;">
        <select name="tri" class="form-control" onchange="this.form.submit()">
          <option value="date_desc" <?= $tri === 'date_desc' ? 'selected' : '' ?>>Plus récents</option>
          <option value="date_asc" <?= $tri === 'date_asc' ? 'selected' : '' ?>>Plus anciens</option>
          <option value="nom_asc" <?= $tri === 'nom_asc' ? 'selected' : '' ?>>Nom A→Z</option>
          <option value="nom_desc" <?= $tri === 'nom_desc' ? 'selected' : '' ?>>Nom Z→A</option>
        </select>
      </form>
      <a href="<?= url('projets.php?action=creer') ?>" class="btn-r1b btn-r1b-primary">
        <i class="fas fa-plus"></i> Nouveau projet
      </a>
    </div>
  </div>

  <!-- KPI (identiques Processus-R1b) -->
  <div class="dash-kpi-grid">
    <div class="dash-kpi">
      <p class="dash-kpi-label">Projets actifs</p>
      <p class="dash-kpi-value"><?= (int)$nbProjetsActifs ?></p>
    </div>
    <div class="dash-kpi">
      <p class="dash-kpi-label">Tâches en cours</p>
      <p class="dash-kpi-value"><?= (int)$nbTachesOuvertes ?></p>
    </div>
    <div class="dash-kpi">
      <p class="dash-kpi-label">Validations en attente</p>
      <p class="dash-kpi-value"><?= (int)$nbValidationsPending ?></p>
    </div>
    <div class="dash-kpi">
      <p class="dash-kpi-label">Jalons</p>
      <p class="dash-kpi-value"><?= (int)$nbJalonsTotal ?></p>
    </div>
  </div>

  <div class="dash-grid">
    <div class="dash-col-main">
      <div class="dash-card">
        <h3>Projets (En cours)</h3>
        <?php if (empty($projetsEnCours)): ?>
          <div class="dash-empty">
            <i class="fas fa-folder-open"></i>
            <p>Aucun projet en cours.</p>
            <a href="<?= url('projets.php?action=creer') ?>" class="btn-r1b btn-r1b-primary" style="margin-top:.75rem;">Créer un projet</a>
          </div>
        <?php else: ?>
          <div class="dash-proj-list">
            <?php foreach ($projetsEnCours as $p) {
                $renderProjetCard($p, $steps, $user);
            } ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="dash-card">
        <h3>Archivage</h3>
        <div class="dash-archive-wrap">
          <div class="dash-archive-section abandonnes">
            <h4>
              <i class="fas fa-times-circle"></i> Projets abandonnés
              <span class="count"><?= count($projetsAbandonnes) ?></span>
            </h4>
            <?php if (empty($projetsAbandonnes)): ?>
              <div class="dash-empty"><p>Aucun projet abandonné.</p></div>
            <?php else: ?>
              <div class="dash-proj-list">
                <?php foreach ($projetsAbandonnes as $p) {
                    $renderProjetCard($p, $steps, $user);
                } ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="dash-archive-section valides">
            <h4>
              <i class="fas fa-check-circle"></i> Projets validé/vente
              <span class="count"><?= count($projetsValides) ?></span>
            </h4>
            <?php if (empty($projetsValides)): ?>
              <div class="dash-empty"><p>Aucun projet validé / vente.</p></div>
            <?php else: ?>
              <div class="dash-proj-list">
                <?php foreach ($projetsValides as $p) {
                    $renderProjetCard($p, $steps, $user);
                } ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="dash-card">
      <h3>Activité récente</h3>
      <?php
      $recent = array_slice($projetsEnCours, 0, 8);
      if (empty($recent)):
      ?>
        <p class="text-muted text-sm">Aucune activité</p>
      <?php else: ?>
        <?php foreach ($recent as $p):
          $cs = r1bClampStep((int)($p['current_step'] ?? 0));
          $stepLabel = $steps[$cs]['title'] ?? '—';
        ?>
        <div class="dash-side-item">
          <p class="dash-side-title"><?= e($p['nom']) ?></p>
          <p class="dash-side-meta"><?= e($stepLabel) ?> · <?= date('d/m/Y', strtotime($p['date_creation'])) ?> · <?= e($p['createur']) ?></p>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
