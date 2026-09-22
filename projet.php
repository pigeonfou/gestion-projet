<?php
$pageTitle = 'Projet';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings_helper.php';
requerirConnexion();
seedSettingsIfEmpty();

$db = getDB();
$user = utilisateurCourant();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$phase = $_GET['phase'] ?? 'cahier';
$phasesValides = ['cahier','capacite','investissement','proto','tests','production','livraison'];
if (!in_array($phase, $phasesValides)) $phase = 'cahier';

if ($id <= 0) redirect('projets.php');

$stmt = $db->prepare("SELECT p.*, u.identifiant AS createur FROM projets p JOIN utilisateurs u ON u.id = p.createur_id WHERE p.id = ?");
$stmt->execute([$id]);
$projet = $stmt->fetch();
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}

// Charger cahier + données associées
$stmt = $db->prepare('SELECT * FROM cahiers WHERE projet_id = ?');
$stmt->execute([$id]);
$cahier = $stmt->fetch();
if (!$cahier) {
    $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$id]);
    $stmt->execute([$id]);
    $cahier = $stmt->fetch();
}
$cahier_id = (int)$cahier['id'];

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

// Tâches groupées pour étude capacité / investissement
$taches = $db->prepare('SELECT * FROM taches WHERE projet_id = ? ORDER BY id');
$taches->execute([$id]);
$taches = $taches->fetchAll();

ensureSettingsTable();
$stmtDocs = $db->prepare('SELECT * FROM documents WHERE projet_id = ? AND phase = ? ORDER BY date_upload DESC');
$stmtDocs->execute([$id, $phase]);
$documents = $stmtDocs->fetchAll();
$ncEnabled = getSetting('nextcloud_enabled', '0') === '1';

$phaseActive = $phase;
$pageTitle = $projet['nom'];
$useAppShell = true;

require __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require __DIR__ . '/includes/phase_sidebar.php'; ?>

    <div class="main-workspace">
        <?php if ($phase === 'cahier'): ?>
            <!-- ===== EXPRESSION DE BESOIN & CAHIER DES CHARGES ===== -->
            <div class="phase-title">
                Expression de besoin & Cahier des charges
                <?php if (estAdmin() || $projet['createur_id'] == $user['id']): ?>
                <a href="<?= url('cahier.php?projet_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Éditer</a>
                <?php endif; ?>
            </div>

            <div class="workflow-bar">
                <div class="workflow-step"><span class="box done"></span> Exp. B</div>
                <span class="workflow-arrow">→</span>
                <div class="workflow-step"><span class="box current"></span> CDC</div>
                <span class="workflow-arrow">→</span>
                <div class="workflow-step"><span class="box"></span> V.Dir.</div>
                <span class="workflow-arrow">→</span>
                <div class="workflow-step"><span class="box"></span> V.Client</div>
                <span class="workflow-arrow">→</span>
                <div class="workflow-step"><span class="box"></span> Étude</div>
            </div>

            <div class="section-label">Fonction</div>
            <div class="cdc-block">
                <div class="cdc-body" style="padding:0;">
                    <?php if (empty($fonctions)): ?>
                        <div class="fn-row text-muted">Aucune fonction définie</div>
                    <?php else: ?>
                        <?php foreach ($fonctions as $f): ?>
                        <div class="fn-row">
                            <div>
                                <strong>#<?= (int)$f['id'] ?></strong>
                                <?= e($f['nom']) ?>
                                <?php if ($f['description']): ?>
                                    <span class="text-muted"> — <?= e($f['description']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="opt-check <?= $f['obligatoire'] ? '' : 'checked' ?>" title="<?= $f['obligatoire'] ? 'Obligatoire' : 'Optionnelle' ?>">
                                <?= $f['obligatoire'] ? '' : '✗' ?>
                            </span>
                            <span class="text-muted text-sm"><?= $f['obligatoire'] ? 'Oblig.' : 'Opt.' ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-label">Matériel</div>
            <div class="cdc-block">
                <div class="cdc-body" style="padding:0;">
                    <?php if (empty($materiels)): ?>
                        <div class="fn-row text-muted">Aucun matériel</div>
                    <?php else: ?>
                        <?php foreach ($materiels as $m): ?>
                        <div class="fn-row">
                            <div><strong>#<?= (int)$m['id'] ?></strong> <?= e($m['description']) ?></div>
                            <span class="opt-check"></span>
                            <span></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-label">Environnement</div>
            <div class="cdc-block">
                <div class="cdc-body" style="padding:0;">
                    <?php if (empty($environnements)): ?>
                        <div class="fn-row text-muted">Aucun élément</div>
                    <?php else: ?>
                        <?php foreach ($environnements as $en): ?>
                        <div class="fn-row">
                            <div><strong>#<?= (int)$en['id'] ?></strong> <?= e($en['element']) ?></div>
                            <span class="opt-check"></span>
                            <span></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (estAdmin()): ?>
            <div class="submit-bar">
                <button class="btn btn-primary" onclick="alert('Fonctionnalité de validation à connecter au workflow.')">
                    <i class="fas fa-paper-plane"></i> Soumettre CDC à validation DIR
                </button>
            </div>
            <?php endif; ?>

        <?php elseif ($phase === 'capacite'): ?>
            <!-- ===== ÉTUDE DE CAPACITÉ ===== -->
            <div class="phase-title">Étude de capacité</div>

            <?php
            // Grouper les tâches comme des "CDC" pour l'affichage capacité
            if (empty($taches) && empty($fonctions)):
            ?>
                <div class="empty-state">
                    <i class="fas fa-users-cog"></i>
                    <p>Aucune ressource planifiée.</p>
                    <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary mt-2">Ajouter une ressource / tâche</a>
                </div>
            <?php else: ?>
                <?php if (!empty($fonctions)): ?>
                <div class="cdc-block">
                    <div class="cdc-header">
                        <span class="cdc-id">CDC — Fonctions</span>
                        <span style="flex:1"></span>
                        <a href="<?= url('cahier.php?projet_id=' . $id . '&tab=fonctions') ?>" class="btn-add" title="Ajouter">+</a>
                    </div>
                    <div class="cdc-body">
                        <div class="cdc-sub">
                            <div>
                                <div class="cdc-sub-title">DEV LOGICIEL / ÉQUIPE DEV</div>
                                <ul class="cdc-items">
                                    <?php foreach ($fonctions as $idx => $f): ?>
                                    <li>
                                        <span class="item-num">#<?= $idx + 1 ?></span>
                                        <?= e($f['nom']) ?>
                                        <?php if ($f['description']): ?> — <span class="text-muted"><?= e($f['description']) ?></span><?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn-add">+</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($materiels)): ?>
                <div class="cdc-block">
                    <div class="cdc-header">
                        <span class="cdc-id">CDC — Matériel</span>
                        <span style="flex:1"></span>
                        <a href="<?= url('cahier.php?projet_id=' . $id . '&tab=materiel') ?>" class="btn-add">+</a>
                    </div>
                    <div class="cdc-body">
                        <div class="cdc-sub">
                            <div>
                                <div class="cdc-sub-title">APPRO MATÉRIEL</div>
                                <ul class="cdc-items">
                                    <?php foreach ($materiels as $idx => $m): ?>
                                    <li><span class="item-num">#<?= $idx + 1 ?></span> <?= e($m['description']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="<?= url('cahier.php?projet_id=' . $id . '&tab=materiel') ?>" class="btn-add">+</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php foreach ($taches as $t): ?>
                <div class="cdc-block">
                    <div class="cdc-header">
                        <span class="cdc-id">Tâche #<?= (int)$t['id'] ?></span>
                        <?= e($t['titre']) ?>
                        <span style="flex:1"></span>
                        <span class="badge badge-<?= e($t['priorite']) ?>"><?= e($t['priorite']) ?></span>
                        <span class="badge badge-<?= e($t['statut']) ?>">
                            <?= match($t['statut']) { 'a_faire'=>'À faire','en_cours'=>'En cours','terminee'=>'Terminée', default=>$t['statut'] } ?>
                        </span>
                    </div>
                    <div class="cdc-body">
                        <?php if ($t['description']): ?>
                        <p class="text-sm text-muted"><?= e($t['description']) ?></p>
                        <?php endif; ?>
                        <div class="flex gap-1 mt-1">
                            <a href="<?= url('tache.php?action=modifier&id=' . (int)$t['id']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:.75rem;">
                    <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter une ressource</a>
                </div>
            <?php endif; ?>

        <?php elseif ($phase === 'investissement'): ?>
            <!-- ===== BESOIN INVESTISSEMENT ===== -->
            <div class="phase-title">Besoin investissement</div>

            <?php if (empty($taches) && empty($materiels)): ?>
                <div class="empty-state">
                    <i class="fas fa-coins"></i>
                    <p>Aucun besoin d'investissement saisi.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($taches)): ?>
                <div class="cdc-block">
                    <div class="cdc-header"><span class="cdc-id">Charges de travail</span></div>
                    <div class="cdc-body">
                        <ul class="cdc-items">
                            <?php foreach ($taches as $idx => $t): ?>
                            <li>
                                <span class="item-num">#<?= $idx + 1 ?></span>
                                <strong><?= e($t['titre']) ?></strong>
                                <?php if ($t['description']): ?> — <?= e($t['description']) ?><?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($materiels)): ?>
                <div class="cdc-block">
                    <div class="cdc-header"><span class="cdc-id">Matériel / Appro</span></div>
                    <div class="cdc-body">
                        <ul class="cdc-items">
                            <?php foreach ($materiels as $idx => $m): ?>
                            <li><span class="item-num">#<?= $idx + 1 ?></span> <?= e($m['description']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($cahier['budget']): ?>
                <div class="cdc-block">
                    <div class="cdc-header"><span class="cdc-id">Budget</span></div>
                    <div class="cdc-body"><?= nl2br(e($cahier['budget'])) ?></div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($phase === 'proto'): ?>
            <div class="phase-title">Prototype</div>
            <div class="cdc-block">
                <div class="cdc-body">
                    <p class="text-muted">Phase prototype — utilisez les tâches et livrables pour suivre les itérations.</p>
                    <a href="<?= url('tache.php?action=creer&projet_id=' . $id) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Nouvelle tâche proto</a>
                </div>
            </div>
            <?php
            $tachesProto = array_filter($taches, fn($t) => str_contains(strtolower($t['titre'] . ' ' . ($t['description']??'')), 'proto'));
            foreach ($taches as $t):
            ?>
            <div class="cdc-block">
                <div class="cdc-header">
                    <?= e($t['titre']) ?>
                    <span style="flex:1"></span>
                    <span class="badge badge-<?= e($t['statut']) ?>">
                        <?= match($t['statut']) { 'a_faire'=>'À faire','en_cours'=>'En cours','terminee'=>'Terminée', default=>$t['statut'] } ?>
                    </span>
                </div>
                <?php if ($t['description']): ?><div class="cdc-body text-sm"><?= e($t['description']) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>

        <?php elseif ($phase === 'tests'): ?>
            <div class="phase-title">Tests conformité</div>
            <?php if (!empty($environnements)): ?>
            <div class="cdc-block">
                <div class="cdc-header">Environnement de test</div>
                <div class="cdc-body">
                    <ul class="cdc-items">
                        <?php foreach ($environnements as $en): ?>
                        <li><?= e($en['element']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach ($taches as $t): ?>
            <div class="cdc-block">
                <div class="cdc-header">
                    <?= e($t['titre']) ?>
                    <span style="flex:1"></span>
                    <span class="badge badge-<?= e($t['statut']) ?>">
                        <?= match($t['statut']) { 'a_faire'=>'À faire','en_cours'=>'En cours','terminee'=>'Terminée', default=>$t['statut'] } ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($taches)): ?>
            <div class="empty-state"><i class="fas fa-check-double"></i><p>Aucun test planifié.</p></div>
            <?php endif; ?>

        <?php elseif ($phase === 'production'): ?>
            <div class="phase-title">Production</div>
            <?php if (!empty($jalons)): ?>
            <div class="cdc-block">
                <div class="cdc-header">Jalons de production</div>
                <div class="cdc-body">
                    <ul class="cdc-items">
                        <?php foreach ($jalons as $j): ?>
                        <li>
                            <strong><?= e($j['nom']) ?></strong>
                            <?php if ($j['date_prevue']): ?> — <?= date('d/m/Y', strtotime($j['date_prevue'])) ?><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            <div class="empty-state" style="padding:1.5rem;"><p class="text-muted">Suivi de production — jalons et tâches associés.</p></div>

        <?php elseif ($phase === 'livraison'): ?>
            <div class="phase-title">Livraison</div>
            <?php if (!empty($livrables)): ?>
                <?php foreach ($livrables as $l): ?>
                <div class="cdc-block">
                    <div class="cdc-header">
                        <?= e($l['description']) ?>
                        <span style="flex:1"></span>
                        <?php if ($l['date_livraison']): ?>
                        <span class="badge-h"><?= date('d/m/Y', strtotime($l['date_livraison'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-truck"></i><p>Aucun livrable défini.</p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ===== PANNEAU DOCUMENTS ===== -->
    <aside class="docs-panel">
        <h3>
            Documents
            <?php if ($ncEnabled): ?>
            <label for="fileUpload" class="btn-add" title="Uploader un fichier" style="cursor:pointer;">+</label>
            <?php endif; ?>
        </h3>

        <?php if ($ncEnabled): ?>
        <form class="upload-form" action="<?= url('actions/upload.php') ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
            <input type="hidden" name="phase" value="<?= e($phase) ?>">
            <input type="file" name="fichier" id="fileUpload" required onchange="this.form.submit()">
            <p class="text-muted text-sm">Dossier : <code><?= e(phaseFolderName($phase)) ?></code></p>
        </form>
        <?php else: ?>
        <p class="text-muted text-sm">Nextcloud désactivé — configurez-le dans Paramètres.</p>
        <?php endif; ?>

        <ul class="docs-list" style="margin-top:.75rem;">
            <?php if (empty($documents)): ?>
            <li class="text-muted text-sm">Aucun document pour cette phase</li>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                <li>
                    <i class="fas fa-file"></i>
                    <span>
                        <?= e($doc['nom_fichier']) ?>
                        <span class="doc-meta"><?= date('d/m/Y H:i', strtotime($doc['date_upload'])) ?>
                        <?php if ($doc['taille']): ?> — <?= number_format($doc['taille']/1024, 1) ?> Ko<?php endif; ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <?php if ($phase === 'cahier'): ?>
        <div class="docs-section">
            <h3>Cahier des charges validé DIR</h3>
            <ul class="docs-list"><li class="text-muted text-sm">En attente de validation</li></ul>
        </div>
        <div class="docs-section">
            <h3>Cahier des charges validé Client</h3>
            <ul class="docs-list"><li class="text-muted text-sm">En attente de validation</li></ul>
        </div>
        <?php endif; ?>

        <?php if ($phase === 'investissement'): ?>
        <div class="docs-section">
            <h3>Estimation</h3>
            <div class="estimation-box">
                <div class="row"><span>Coûts :</span> <strong><?= e($cahier['budget'] ?: '—') ?></strong></div>
                <div class="row"><span>Délais :</span> <strong><?= e($cahier['dates_info'] ?: '—') ?></strong></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="docs-section">
            <h3>Infos projet</h3>
            <div class="estimation-box">
                <div class="row"><span>Créateur</span> <strong><?= e($projet['createur']) ?></strong></div>
                <div class="row"><span>Créé le</span> <strong><?= date('d/m/Y', strtotime($projet['date_creation'])) ?></strong></div>
                <div class="row"><span>Tâches</span> <strong><?= count($taches) ?></strong></div>
            </div>
        </div>
    </aside>
</div>

<?php
// Footer minimal without the default main-content wrapper closing issues
?>
</main>
<footer class="footer">
    <div class="footer-container">
        <p>&copy; <?= date('Y') ?> ProjectFlow — Gestion de projets, processus & documentation</p>
    </div>
</footer>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
