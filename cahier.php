<?php
$pageTitle = 'Cahier des charges';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
requerirAdmin(); // Accès réservé aux administrateurs

$db = getDB();
$projet_id = isset($_GET['projet_id']) ? (int)$_GET['projet_id'] : 0;

if ($projet_id <= 0) {
    setFlash('error', 'Projet non spécifié.');
    redirect('projets.php');
    exit;
}

// Vérifier projet
$stmt = $db->prepare('SELECT nom FROM projets WHERE id = ?');
$stmt->execute([$projet_id]);
$projetNom = $stmt->fetchColumn();
if (!$projetNom) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
    exit;
}

// Récupérer ou créer le cahier
$stmt = $db->prepare('SELECT * FROM cahiers WHERE projet_id = ?');
$stmt->execute([$projet_id]);
$cahier = $stmt->fetch();

if (!$cahier) {
    $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$projet_id]);
    $stmt->execute([$projet_id]);
    $cahier = $stmt->fetch();
}
$cahier_id = (int)$cahier['id'];

// ===== Traitements POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    try {
        if ($section === 'general') {
            $fields = ['categorie','contexte','objectifs','contraintes','dates_info','budget','ressources','risques','criteres_reussite'];
            $data = [];
            foreach ($fields as $f) {
                $data[$f] = trim($_POST[$f] ?? '');
            }
            $stmt = $db->prepare('UPDATE cahiers SET categorie=?, contexte=?, objectifs=?, contraintes=?, dates_info=?, budget=?, ressources=?, risques=?, criteres_reussite=?, date_maj=CURRENT_TIMESTAMP WHERE id=?');
            $stmt->execute([...array_values($data), $cahier_id]);
            setFlash('success', 'Informations générales enregistrées.');
        }
        elseif ($section === 'fonction_add') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $obligatoire = isset($_POST['obligatoire']) ? 1 : 0;
            if ($nom === '') {
                setFlash('error', 'Le nom de la fonction est obligatoire.');
            } else {
                $db->prepare('INSERT INTO fonctions (cahier_id, nom, description, obligatoire) VALUES (?,?,?,?)')
                   ->execute([$cahier_id, $nom, $description, $obligatoire]);
                setFlash('success', 'Fonction ajoutée.');
            }
        }
        elseif ($section === 'fonction_del') {
            $fid = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM fonctions WHERE id=? AND cahier_id=?')->execute([$fid, $cahier_id]);
            setFlash('success', 'Fonction supprimée.');
        }
        elseif ($section === 'materiel_add') {
            $desc = trim($_POST['description'] ?? '');
            if ($desc === '') {
                setFlash('error', 'Description obligatoire.');
            } else {
                $db->prepare('INSERT INTO materiel (cahier_id, description) VALUES (?,?)')->execute([$cahier_id, $desc]);
                setFlash('success', 'Matériel ajouté.');
            }
        }
        elseif ($section === 'materiel_del') {
            $mid = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM materiel WHERE id=? AND cahier_id=?')->execute([$mid, $cahier_id]);
            setFlash('success', 'Matériel supprimé.');
        }
        elseif ($section === 'env_add') {
            $elem = trim($_POST['element'] ?? '');
            if ($elem === '') {
                setFlash('error', 'Élément obligatoire.');
            } else {
                $db->prepare('INSERT INTO environnement (cahier_id, element) VALUES (?,?)')->execute([$cahier_id, $elem]);
                setFlash('success', 'Élément d\'environnement ajouté.');
            }
        }
        elseif ($section === 'env_del') {
            $eid = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM environnement WHERE id=? AND cahier_id=?')->execute([$eid, $cahier_id]);
            setFlash('success', 'Élément supprimé.');
        }
        elseif ($section === 'livrable_add') {
            $desc = trim($_POST['description'] ?? '');
            $date = $_POST['date_livraison'] ?? null;
            if ($date === '') $date = null;
            if ($desc === '') {
                setFlash('error', 'Description obligatoire.');
            } else {
                $db->prepare('INSERT INTO livrables (cahier_id, description, date_livraison) VALUES (?,?,?)')
                   ->execute([$cahier_id, $desc, $date]);
                setFlash('success', 'Livrable ajouté.');
            }
        }
        elseif ($section === 'livrable_del') {
            $lid = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM livrables WHERE id=? AND cahier_id=?')->execute([$lid, $cahier_id]);
            setFlash('success', 'Livrable supprimé.');
        }
        elseif ($section === 'jalon_add') {
            $nom = trim($_POST['nom'] ?? '');
            $date = $_POST['date_prevue'] ?? null;
            if ($date === '') $date = null;
            if ($nom === '') {
                setFlash('error', 'Nom du jalon obligatoire.');
            } else {
                $db->prepare('INSERT INTO jalons (cahier_id, nom, date_prevue) VALUES (?,?,?)')
                   ->execute([$cahier_id, $nom, $date]);
                setFlash('success', 'Jalon ajouté.');
            }
        }
        elseif ($section === 'jalon_del') {
            $jid = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM jalons WHERE id=? AND cahier_id=?')->execute([$jid, $cahier_id]);
            setFlash('success', 'Jalon supprimé.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Erreur lors de l\'opération.');
    }
    redirect('cahier.php?projet_id=' . $projet_id . '&tab=' . urlencode($_POST['tab'] ?? 'general'));
    exit;
}

// Charger les données associées
$fonctions = $db->prepare('SELECT * FROM fonctions WHERE cahier_id = ? ORDER BY obligatoire DESC, nom');
$fonctions->execute([$cahier_id]);
$fonctions = $fonctions->fetchAll();

$materiels = $db->prepare('SELECT * FROM materiel WHERE cahier_id = ? ORDER BY id');
$materiels->execute([$cahier_id]);
$materiels = $materiels->fetchAll();

$environnements = $db->prepare('SELECT * FROM environnement WHERE cahier_id = ? ORDER BY id');
$environnements->execute([$cahier_id]);
$environnements = $environnements->fetchAll();

$livrables = $db->prepare('SELECT * FROM livrables WHERE cahier_id = ? ORDER BY date_livraison IS NULL, date_livraison');
$livrables->execute([$cahier_id]);
$livrables = $livrables->fetchAll();

$jalons = $db->prepare('SELECT * FROM jalons WHERE cahier_id = ? ORDER BY date_prevue IS NULL, date_prevue');
$jalons->execute([$cahier_id]);
$jalons = $jalons->fetchAll();

$tab = $_GET['tab'] ?? 'general';
$pageTitle = 'Cahier des charges — ' . $projetNom;
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-book"></i> Cahier des charges</h1>
        <p class="text-muted text-sm mt-1">Projet : <strong><?= e($projetNom) ?></strong></p>
    </div>
    <a href="<?= url('projet.php?id=' . $projet_id) ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour au projet
    </a>
</div>

<div class="tabs">
    <button class="tab-btn <?= $tab === 'general' ? 'active' : '' ?>" data-tab="general">Informations générales</button>
    <button class="tab-btn <?= $tab === 'fonctions' ? 'active' : '' ?>" data-tab="fonctions">Fonctions</button>
    <button class="tab-btn <?= $tab === 'materiel' ? 'active' : '' ?>" data-tab="materiel">Matériel</button>
    <button class="tab-btn <?= $tab === 'environnement' ? 'active' : '' ?>" data-tab="environnement">Environnement</button>
    <button class="tab-btn <?= $tab === 'livrables' ? 'active' : '' ?>" data-tab="livrables">Livrables</button>
    <button class="tab-btn <?= $tab === 'jalons' ? 'active' : '' ?>" data-tab="jalons">Jalons</button>
</div>

<!-- ===== ONGLET GÉNÉRAL ===== -->
<div id="tab-general" class="tab-content <?= $tab === 'general' ? 'active' : '' ?>">
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="section" value="general">
                <input type="hidden" name="tab" value="general">
                <div class="form-row">
                    <div class="form-group">
                        <label>Catégorie</label>
                        <input type="text" name="categorie" class="form-control" value="<?= e($cahier['categorie'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Budget</label>
                        <input type="text" name="budget" class="form-control" value="<?= e($cahier['budget'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Contexte</label>
                    <textarea name="contexte" class="form-control" rows="3"><?= e($cahier['contexte'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Objectifs</label>
                    <textarea name="objectifs" class="form-control" rows="3"><?= e($cahier['objectifs'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Contraintes</label>
                    <textarea name="contraintes" class="form-control" rows="2"><?= e($cahier['contraintes'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Dates (planning)</label>
                    <textarea name="dates_info" class="form-control" rows="2"><?= e($cahier['dates_info'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Ressources</label>
                    <textarea name="ressources" class="form-control" rows="2"><?= e($cahier['ressources'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Risques</label>
                    <textarea name="risques" class="form-control" rows="2"><?= e($cahier['risques'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Critères de réussite</label>
                    <textarea name="criteres_reussite" class="form-control" rows="2"><?= e($cahier['criteres_reussite'] ?? '') ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== ONGLET FONCTIONS ===== -->
<div id="tab-fonctions" class="tab-content <?= $tab === 'fonctions' ? 'active' : '' ?>">
    <div class="card mb-2">
        <div class="card-header"><h3>Ajouter une fonction</h3></div>
        <div class="card-body">
            <form method="POST" class="form-row" style="align-items:end;">
                <input type="hidden" name="section" value="fonction_add">
                <input type="hidden" name="tab" value="fonctions">
                <div class="form-group" style="flex:1;">
                    <label>Nom *</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group" style="flex:2;">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="obligatoire" value="1"> Obligatoire
                    </label>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i></button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (empty($fonctions)): ?>
                <div class="empty-state"><i class="fas fa-puzzle-piece"></i><p>Aucune fonction définie.</p></div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Nom</th><th>Description</th><th>Type</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($fonctions as $f): ?>
                            <tr>
                                <td><strong><?= e($f['nom']) ?></strong></td>
                                <td><?= e($f['description']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $f['obligatoire'] ? 'obligatoire' : 'facultative' ?>">
                                        <?= $f['obligatoire'] ? 'Obligatoire' : 'Facultative' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette fonction ?');">
                                        <input type="hidden" name="section" value="fonction_del">
                                        <input type="hidden" name="tab" value="fonctions">
                                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== ONGLET MATÉRIEL ===== -->
<div id="tab-materiel" class="tab-content <?= $tab === 'materiel' ? 'active' : '' ?>">
    <div class="card mb-2">
        <div class="card-header"><h3>Ajouter du matériel</h3></div>
        <div class="card-body">
            <form method="POST" class="flex gap-1 items-center">
                <input type="hidden" name="section" value="materiel_add">
                <input type="hidden" name="tab" value="materiel">
                <input type="text" name="description" class="form-control" placeholder="Description du matériel..." required style="flex:1;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (empty($materiels)): ?>
                <div class="empty-state"><i class="fas fa-toolbox"></i><p>Aucun matériel listé.</p></div>
            <?php else: ?>
                <ul style="list-style:none;">
                    <?php foreach ($materiels as $m): ?>
                    <li style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border);">
                        <span><?= e($m['description']) ?></span>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');">
                            <input type="hidden" name="section" value="materiel_del">
                            <input type="hidden" name="tab" value="materiel">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== ONGLET ENVIRONNEMENT ===== -->
<div id="tab-environnement" class="tab-content <?= $tab === 'environnement' ? 'active' : '' ?>">
    <div class="card mb-2">
        <div class="card-header"><h3>Ajouter un élément d'environnement</h3></div>
        <div class="card-body">
            <form method="POST" class="flex gap-1 items-center">
                <input type="hidden" name="section" value="env_add">
                <input type="hidden" name="tab" value="environnement">
                <input type="text" name="element" class="form-control" placeholder="Contrainte technique, outil, serveur..." required style="flex:1;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (empty($environnements)): ?>
                <div class="empty-state"><i class="fas fa-server"></i><p>Aucun élément d'environnement.</p></div>
            <?php else: ?>
                <ul style="list-style:none;">
                    <?php foreach ($environnements as $e): ?>
                    <li style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border);">
                        <span><?= e($e['element']) ?></span>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');">
                            <input type="hidden" name="section" value="env_del">
                            <input type="hidden" name="tab" value="environnement">
                            <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== ONGLET LIVRABLES ===== -->
<div id="tab-livrables" class="tab-content <?= $tab === 'livrables' ? 'active' : '' ?>">
    <div class="card mb-2">
        <div class="card-header"><h3>Ajouter un livrable</h3></div>
        <div class="card-body">
            <form method="POST" class="form-row" style="align-items:end;">
                <input type="hidden" name="section" value="livrable_add">
                <input type="hidden" name="tab" value="livrables">
                <div class="form-group" style="flex:2;">
                    <label>Description *</label>
                    <input type="text" name="description" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Date de livraison</label>
                    <input type="date" name="date_livraison" class="form-control">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i></button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (empty($livrables)): ?>
                <div class="empty-state"><i class="fas fa-box-open"></i><p>Aucun livrable.</p></div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Description</th><th>Date livraison</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($livrables as $l): ?>
                            <tr>
                                <td><?= e($l['description']) ?></td>
                                <td><?= $l['date_livraison'] ? date('d/m/Y', strtotime($l['date_livraison'])) : '—' ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?');">
                                        <input type="hidden" name="section" value="livrable_del">
                                        <input type="hidden" name="tab" value="livrables">
                                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== ONGLET JALONS ===== -->
<div id="tab-jalons" class="tab-content <?= $tab === 'jalons' ? 'active' : '' ?>">
    <div class="card mb-2">
        <div class="card-header"><h3>Ajouter un jalon</h3></div>
        <div class="card-body">
            <form method="POST" class="form-row" style="align-items:end;">
                <input type="hidden" name="section" value="jalon_add">
                <input type="hidden" name="tab" value="jalons">
                <div class="form-group" style="flex:2;">
                    <label>Nom *</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Date prévue</label>
                    <input type="date" name="date_prevue" class="form-control">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i></button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (empty($jalons)): ?>
                <div class="empty-state"><i class="fas fa-flag-checkered"></i><p>Aucun jalon défini.</p></div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Jalon</th><th>Date prévue</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($jalons as $j): ?>
                            <tr>
                                <td><strong><?= e($j['nom']) ?></strong></td>
                                <td><?= $j['date_prevue'] ? date('d/m/Y', strtotime($j['date_prevue'])) : '—' ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?');">
                                        <input type="hidden" name="section" value="jalon_del">
                                        <input type="hidden" name="tab" value="jalons">
                                        <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Activer l'onglet depuis l'URL
document.addEventListener('DOMContentLoaded', () => {
    const tab = '<?= e($tab) ?>';
    const btn = document.querySelector('.tab-btn[data-tab="' + tab + '"]');
    if (btn) btn.click();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
