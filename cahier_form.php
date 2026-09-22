<?php
$pageTitle = 'Cahier des charges';
$activePage = 'projets';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cahier_specs.php';
requerirConnexion();

$db = getDB();
$user = utilisateurCourant();
$projet_id = (int)($_GET['projet_id'] ?? $_POST['projet_id'] ?? 0);
if ($projet_id <= 0) redirect('projets.php');

$stmt = $db->prepare('SELECT * FROM projets WHERE id = ?');
$stmt->execute([$projet_id]);
$projet = $stmt->fetch();
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}

$stmt = $db->prepare('SELECT * FROM cahiers WHERE projet_id = ?');
$stmt->execute([$projet_id]);
$cahier = $stmt->fetch();
if (!$cahier) {
    $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$projet_id]);
    $stmt->execute([$projet_id]);
    $cahier = $stmt->fetch();
}
$cahier_id = (int)$cahier['id'];

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'save';
    $specs = emptySpecs();

    // Text fields
    $textKeys = [
        'contexte_autre','objectifs','resultats_attendus','cas_usage','profils_utilisateurs','contraintes_operationnelles',
        'fonctionnalites','connectivite_autre','luminosite_nits','tactile_multitouch','usage_gants','anti_reflet','taille_ecran','ihm_accessoires',
        'processeur','ram','stockage','autonomie','fiabilite','securite','maintenabilite',
        'ip','ip_autre','chute_metres','vibrations','temp_fonc_min','temp_fonc_max','temp_stock_min','temp_stock_max',
        'humidite','brouillard_salin','altitude_max','uv','cem','normes_autre','niveau_durcissement',
        'architecture','compatibilite','alimentation','consommation_max','materiaux','conformites',
        'mode_installation','maintenance','garantie','formation_doc','pieces_sav','cycle_vie',
        'delais','livrables_attendus',
    ];
    foreach ($textKeys as $k) {
        $specs[$k] = trim($_POST[$k] ?? '');
    }
    // Arrays (checkboxes)
    $specs['contexte_utilisation'] = array_values(array_filter((array)($_POST['contexte_utilisation'] ?? [])));
    $specs['fonctionnalites_cochees'] = array_values(array_filter((array)($_POST['fonctionnalites_cochees'] ?? [])));
    $specs['connectivite'] = array_values(array_filter((array)($_POST['connectivite'] ?? [])));
    $specs['normes'] = array_values(array_filter((array)($_POST['normes'] ?? [])));

    // Required validation on "generate"
    $errors = [];
    if ($action === 'generate') {
        if ($specs['objectifs'] === '') $errors[] = 'Les objectifs du projet sont obligatoires.';
        if (empty($specs['contexte_utilisation']) && $specs['contexte_autre'] === '') $errors[] = 'Le contexte d\'utilisation est obligatoire.';
        if ($specs['niveau_durcissement'] === '') $errors[] = 'Le niveau de durcissement est obligatoire.';
    }

    if ($errors) {
        setFlash('error', implode(' ', $errors));
    } else {
        saveSpecs($cahier_id, $specs);
        // Mirror some fields into classic cahier columns
        $db->prepare('UPDATE cahiers SET objectifs=?, contexte=?, contraintes=?, date_maj=CURRENT_TIMESTAMP WHERE id=?')
           ->execute([$specs['objectifs'], $specs['cas_usage'], $specs['contraintes_operationnelles'], $cahier_id]);

        if ($action === 'generate') {
            $text = generateCahierText($specs, $projet['nom']);
            $_SESSION['cdc_generated'] = $text;
            setFlash('success', 'Cahier des charges généré et enregistré.');
            redirect('cahier_form.php?projet_id=' . $projet_id . '&generated=1');
        }
        setFlash('success', 'Brouillon enregistré.');
        redirect('cahier_form.php?projet_id=' . $projet_id);
    }
}

$s = loadSpecs($cahier_id);
$generated = $_SESSION['cdc_generated'] ?? null;
if (isset($_GET['generated'])) {
    // keep flash; text in session
} else {
    unset($_SESSION['cdc_generated']);
    $generated = null;
}
if (isset($_GET['generated']) && !empty($_SESSION['cdc_generated'])) {
    $generated = $_SESSION['cdc_generated'];
}

$pageTitle = 'CDC — ' . $projet['nom'];
require __DIR__ . '/includes/header.php';

function checked_arr(array $arr, string $val): string {
    return in_array($val, $arr, true) ? 'checked' : '';
}
function sel(?string $cur, string $val): string {
    return ($cur ?? '') === $val ? 'selected' : '';
}
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-alt"></i> Cahier des charges structuré</h1>
        <p class="text-muted text-sm mt-1">Projet : <strong><?= e($projet['nom']) ?></strong></p>
    </div>
    <a href="<?= url('projet.php?id=' . $projet_id . '&phase=cahier') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour au projet</a>
</div>

<?php if ($generated): ?>
<div class="card mb-2">
    <div class="card-header">
        <h2><i class="fas fa-file-export"></i> Document généré</h2>
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cdcOut').value);this.textContent='Copié !'">Copier</button>
    </div>
    <div class="card-body">
        <textarea id="cdcOut" class="form-control" rows="18" readonly style="font-family:monospace;font-size:.8rem;"><?= e($generated) ?></textarea>
        <a class="btn btn-primary mt-2" href="data:text/plain;charset=utf-8,<?= rawurlencode($generated) ?>" download="CDC_<?= e(preg_replace('/\s+/', '_', $projet['nom'])) ?>.txt">
            <i class="fas fa-download"></i> Télécharger .txt
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Stepper -->
<div class="cdc-stepper" id="cdcStepper">
    <button type="button" class="step active" data-step="1"><span>1</span> Contexte</button>
    <button type="button" class="step" data-step="2"><span>2</span> Fonctionnel</button>
    <button type="button" class="step" data-step="3"><span>3</span> Performance</button>
    <button type="button" class="step" data-step="4"><span>4</span> Environnement</button>
    <button type="button" class="step" data-step="5"><span>5</span> Technique</button>
    <button type="button" class="step" data-step="6"><span>6</span> Support</button>
    <button type="button" class="step" data-step="7"><span>7</span> Planning</button>
</div>
<div class="cdc-progress"><div class="cdc-progress-bar" id="cdcProgress" style="width:14%"></div></div>

<form method="POST" id="cdcForm" action="<?= url('cahier_form.php') ?>">
    <input type="hidden" name="projet_id" value="<?= $projet_id ?>">
    <input type="hidden" name="form_action" id="formAction" value="save">

    <!-- ===== 1 ===== -->
    <div class="cdc-step-panel active" data-panel="1">
        <div class="card">
            <div class="card-header"><h2>1. Contexte, objectifs et besoins utilisateurs</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Description du contexte d'utilisation *</label>
                    <div class="check-grid">
                        <?php foreach (['chantier','terrain','véhicule','maritime','industriel','militaire','autre'] as $v): ?>
                        <label class="form-check"><input type="checkbox" name="contexte_utilisation[]" value="<?= $v ?>" <?= checked_arr($s['contexte_utilisation'], $v) ?>> <?= ucfirst($v) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="contexte_autre" class="form-control mt-1" placeholder="Préciser si autre…" value="<?= e($s['contexte_autre']) ?>">
                </div>
                <div class="form-group">
                    <label>Objectifs du projet *</label>
                    <textarea name="objectifs" class="form-control" rows="3" required><?= e($s['objectifs']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Résultats attendus</label>
                    <textarea name="resultats_attendus" class="form-control" rows="3"><?= e($s['resultats_attendus']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Cas d'usage principaux</label>
                    <textarea name="cas_usage" class="form-control" rows="3"><?= e($s['cas_usage']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Profils des utilisateurs finaux</label>
                    <textarea name="profils_utilisateurs" class="form-control" rows="2"><?= e($s['profils_utilisateurs']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Contraintes opérationnelles spécifiques</label>
                    <textarea name="contraintes_operationnelles" class="form-control" rows="2"><?= e($s['contraintes_operationnelles']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 2 ===== -->
    <div class="cdc-step-panel" data-panel="2">
        <div class="card">
            <div class="card-header"><h2>2. Exigences fonctionnelles</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Fonctionnalités attendues</label>
                    <textarea name="fonctionnalites" class="form-control" rows="3" placeholder="Description libre…"><?= e($s['fonctionnalites']) ?></textarea>
                    <div class="check-grid mt-1">
                        <?php foreach (['Lecture de codes','GPS','Caméra','NFC/RFID','Scanner 1D/2D','Audio','Capteurs IO'] as $v): ?>
                        <label class="form-check"><input type="checkbox" name="fonctionnalites_cochees[]" value="<?= $v ?>" <?= checked_arr($s['fonctionnalites_cochees'], $v) ?>> <?= $v ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Connectivité requise</label>
                    <div class="check-grid">
                        <?php foreach (['Wi-Fi','4G/5G','Bluetooth','Ethernet','Ports M12','Docking','Autre'] as $v): ?>
                        <label class="form-check"><input type="checkbox" name="connectivite[]" value="<?= $v ?>" <?= checked_arr($s['connectivite'], $v) ?>> <?= $v ?></label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="connectivite_autre" class="form-control mt-1" placeholder="Autre connectivité…" value="<?= e($s['connectivite_autre']) ?>">
                </div>
                <div class="section-label">Caractéristiques d'affichage</div>
                <div class="form-row">
                    <div class="form-group"><label>Luminosité min. (nits)</label><input type="number" name="luminosite_nits" class="form-control" value="<?= e($s['luminosite_nits']) ?>" min="0"></div>
                    <div class="form-group"><label>Taille d'écran</label><input type="text" name="taille_ecran" class="form-control" value="<?= e($s['taille_ecran']) ?>" placeholder="ex. 10.1&quot;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tactile multi-touch</label>
                        <select name="tactile_multitouch" class="form-control">
                            <option value="">—</option>
                            <option value="oui" <?= sel($s['tactile_multitouch'],'oui') ?>>Oui</option>
                            <option value="non" <?= sel($s['tactile_multitouch'],'non') ?>>Non</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Utilisation avec gants</label>
                        <select name="usage_gants" class="form-control">
                            <option value="">—</option>
                            <option value="oui" <?= sel($s['usage_gants'],'oui') ?>>Oui</option>
                            <option value="non" <?= sel($s['usage_gants'],'non') ?>>Non</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Anti-reflet</label>
                        <select name="anti_reflet" class="form-control">
                            <option value="">—</option>
                            <option value="oui" <?= sel($s['anti_reflet'],'oui') ?>>Oui</option>
                            <option value="non" <?= sel($s['anti_reflet'],'non') ?>>Non</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Interfaces homme-machine et accessoires</label>
                    <textarea name="ihm_accessoires" class="form-control" rows="2" placeholder="Clavier rétroéclairé, batteries hot-swap…"><?= e($s['ihm_accessoires']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 3 ===== -->
    <div class="cdc-step-panel" data-panel="3">
        <div class="card">
            <div class="card-header"><h2>3. Exigences non fonctionnelles / performance</h2></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group"><label>Processeur</label><input type="text" name="processeur" class="form-control" value="<?= e($s['processeur']) ?>"></div>
                    <div class="form-group"><label>RAM</label><input type="text" name="ram" class="form-control" value="<?= e($s['ram']) ?>" placeholder="ex. 8 Go"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Stockage</label><input type="text" name="stockage" class="form-control" value="<?= e($s['stockage']) ?>" placeholder="ex. 256 Go SSD"></div>
                    <div class="form-group"><label>Autonomie</label><input type="text" name="autonomie" class="form-control" value="<?= e($s['autonomie']) ?>" placeholder="ex. 8 h"></div>
                </div>
                <div class="form-group"><label>Fiabilité et disponibilité</label><textarea name="fiabilite" class="form-control" rows="2"><?= e($s['fiabilite']) ?></textarea></div>
                <div class="form-group"><label>Exigences de sécurité (chiffrement, TPM, auth, RGPD/ISO…)</label><textarea name="securite" class="form-control" rows="2"><?= e($s['securite']) ?></textarea></div>
                <div class="form-group"><label>Maintenabilité, évolutivité et interopérabilité</label><textarea name="maintenabilite" class="form-control" rows="2"><?= e($s['maintenabilite']) ?></textarea></div>
            </div>
        </div>
    </div>

    <!-- ===== 4 ===== -->
    <div class="cdc-step-panel" data-panel="4">
        <div class="card">
            <div class="card-header"><h2>4. Exigences environnementales et de durcissement</h2></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Indice de protection IP</label>
                        <select name="ip" class="form-control">
                            <option value="">—</option>
                            <?php foreach (['IP65','IP66','IP67','IP68','autre'] as $v): ?>
                            <option value="<?= $v ?>" <?= sel($s['ip'], $v) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="ip_autre" class="form-control mt-1" placeholder="Autre IP…" value="<?= e($s['ip_autre']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Niveau de durcissement *</label>
                        <select name="niveau_durcissement" class="form-control">
                            <option value="">—</option>
                            <option value="semi-durci" <?= sel($s['niveau_durcissement'],'semi-durci') ?>>Semi-durci</option>
                            <option value="entierement-durci" <?= sel($s['niveau_durcissement'],'entierement-durci') ?>>Entièrement durci</option>
                            <option value="ultra-durci" <?= sel($s['niveau_durcissement'],'ultra-durci') ?>>Ultra-durci</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Résistance chutes (m)</label><input type="number" step="0.1" name="chute_metres" class="form-control" value="<?= e($s['chute_metres']) ?>"></div>
                    <div class="form-group"><label>Résistance vibrations</label><input type="text" name="vibrations" class="form-control" value="<?= e($s['vibrations']) ?>"></div>
                </div>
                <div class="section-label">Températures (°C)</div>
                <div class="form-row">
                    <div class="form-group"><label>Fonctionnement min</label><input type="number" name="temp_fonc_min" class="form-control" value="<?= e($s['temp_fonc_min']) ?>"></div>
                    <div class="form-group"><label>Fonctionnement max</label><input type="number" name="temp_fonc_max" class="form-control" value="<?= e($s['temp_fonc_max']) ?>"></div>
                    <div class="form-group"><label>Stockage min</label><input type="number" name="temp_stock_min" class="form-control" value="<?= e($s['temp_stock_min']) ?>"></div>
                    <div class="form-group"><label>Stockage max</label><input type="number" name="temp_stock_max" class="form-control" value="<?= e($s['temp_stock_max']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Humidité / condensation</label><input type="text" name="humidite" class="form-control" value="<?= e($s['humidite']) ?>"></div>
                    <div class="form-group"><label>Brouillard salin</label><input type="text" name="brouillard_salin" class="form-control" value="<?= e($s['brouillard_salin']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Altitude maximale</label><input type="text" name="altitude_max" class="form-control" value="<?= e($s['altitude_max']) ?>"></div>
                    <div class="form-group"><label>Protection UV / solaire</label><input type="text" name="uv" class="form-control" value="<?= e($s['uv']) ?>"></div>
                </div>
                <div class="form-group"><label>Exigences CEM</label><textarea name="cem" class="form-control" rows="2"><?= e($s['cem']) ?></textarea></div>
                <div class="form-group">
                    <label>Normes et certifications</label>
                    <div class="check-grid">
                        <?php foreach (['MIL-STD-810G','MIL-STD-810H','EN50155','IEC 60945','ATEX','NEMA','autre'] as $v): ?>
                        <label class="form-check"><input type="checkbox" name="normes[]" value="<?= $v ?>" <?= checked_arr($s['normes'], $v) ?>> <?= $v ?></label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="normes_autre" class="form-control mt-1" placeholder="Autres normes…" value="<?= e($s['normes_autre']) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 5 ===== -->
    <div class="cdc-step-panel" data-panel="5">
        <div class="card">
            <div class="card-header"><h2>5. Contraintes techniques, réglementaires et d'intégration</h2></div>
            <div class="card-body">
                <div class="form-group"><label>Architecture matérielle et logicielle souhaitée</label><textarea name="architecture" class="form-control" rows="2"><?= e($s['architecture']) ?></textarea></div>
                <div class="form-group"><label>Compatibilité avec les systèmes existants</label><textarea name="compatibilite" class="form-control" rows="2"><?= e($s['compatibilite']) ?></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Alimentation</label><input type="text" name="alimentation" class="form-control" value="<?= e($s['alimentation']) ?>" placeholder="Secteur, véhicule, batteries…"></div>
                    <div class="form-group"><label>Consommation max</label><input type="text" name="consommation_max" class="form-control" value="<?= e($s['consommation_max']) ?>"></div>
                </div>
                <div class="form-group"><label>Matériaux et construction</label><textarea name="materiaux" class="form-control" rows="2" placeholder="Alliage, fanless, ports protégés…"><?= e($s['materiaux']) ?></textarea></div>
                <div class="form-group"><label>Conformités réglementaires obligatoires</label><textarea name="conformites" class="form-control" rows="2"><?= e($s['conformites']) ?></textarea></div>
            </div>
        </div>
    </div>

    <!-- ===== 6 ===== -->
    <div class="cdc-step-panel" data-panel="6">
        <div class="card">
            <div class="card-header"><h2>6. Contraintes opérationnelles, logistiques et de support</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Mode d'installation / déploiement</label>
                    <select name="mode_installation" class="form-control">
                        <option value="">—</option>
                        <?php foreach (['fixe','portable','embarqué véhicule'] as $v): ?>
                        <option value="<?= $v ?>" <?= sel($s['mode_installation'], $v) ?>><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Exigences de maintenance</label><textarea name="maintenance" class="form-control" rows="2"><?= e($s['maintenance']) ?></textarea></div>
                <div class="form-group"><label>Durée et type de garantie souhaitée</label><input type="text" name="garantie" class="form-control" value="<?= e($s['garantie']) ?>" placeholder="ex. 3 ans, extension 5 ans"></div>
                <div class="form-group"><label>Formation et documentation attendues</label><textarea name="formation_doc" class="form-control" rows="2"><?= e($s['formation_doc']) ?></textarea></div>
                <div class="form-group"><label>Disponibilité pièces détachées et SAV</label><textarea name="pieces_sav" class="form-control" rows="2"><?= e($s['pieces_sav']) ?></textarea></div>
                <div class="form-group"><label>Cycle de vie et gestion de l'obsolescence</label><textarea name="cycle_vie" class="form-control" rows="2"><?= e($s['cycle_vie']) ?></textarea></div>
            </div>
        </div>
    </div>

    <!-- ===== 7 ===== -->
    <div class="cdc-step-panel" data-panel="7">
        <div class="card">
            <div class="card-header"><h2>7. Contraintes planning et livrables attendus</h2></div>
            <div class="card-body">
                <div class="form-group"><label>Délais de livraison et de mise en service</label><textarea name="delais" class="form-control" rows="3"><?= e($s['delais']) ?></textarea></div>
                <div class="form-group"><label>Livrables attendus (prototypes, certificats, rapports de tests…)</label><textarea name="livrables_attendus" class="form-control" rows="3"><?= e($s['livrables_attendus']) ?></textarea></div>
            </div>
        </div>
    </div>

    <div class="cdc-form-actions">
        <button type="button" class="btn btn-secondary" id="btnPrev" disabled><i class="fas fa-arrow-left"></i> Précédent</button>
        <button type="button" class="btn btn-secondary" id="btnNext">Suivant <i class="fas fa-arrow-right"></i></button>
        <span style="flex:1"></span>
        <button type="submit" class="btn btn-secondary" onclick="document.getElementById('formAction').value='save'">
            <i class="fas fa-save"></i> Enregistrer le brouillon
        </button>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('formAction').value='generate'">
            <i class="fas fa-file-export"></i> Générer le cahier des charges
        </button>
    </div>
</form>

<style>
.cdc-stepper {
  display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .5rem;
}
.cdc-stepper .step {
  flex: 1; min-width: 100px; padding: .5rem .4rem; border: 1px solid var(--border);
  background: #fff; border-radius: 6px; cursor: pointer; font-size: .75rem;
  display: flex; align-items: center; gap: .35rem; color: var(--text-muted);
}
.cdc-stepper .step span {
  width: 22px; height: 22px; border-radius: 50%; background: #e2e8f0;
  display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .7rem;
}
.cdc-stepper .step.active { border-color: var(--primary-light); color: var(--primary); font-weight: 600; }
.cdc-stepper .step.active span { background: var(--primary-light); color: #fff; }
.cdc-stepper .step.done span { background: var(--success); color: #fff; }
.cdc-progress { height: 4px; background: #e2e8f0; border-radius: 2px; margin-bottom: 1rem; overflow: hidden; }
.cdc-progress-bar { height: 100%; background: var(--primary-light); transition: width .25s; }
.cdc-step-panel { display: none; }
.cdc-step-panel.active { display: block; }
.check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .35rem .75rem; }
.cdc-form-actions {
  display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;
  margin: 1.25rem 0 2rem; padding: 1rem; background: #fff;
  border: 1px solid var(--border); border-radius: 8px;
}
@media (max-width: 640px) {
  .cdc-stepper .step { min-width: 44px; font-size: 0; }
  .cdc-stepper .step span { font-size: .7rem; }
}
</style>
<script>
(function(){
  let step = 1;
  const total = 7;
  const panels = document.querySelectorAll('.cdc-step-panel');
  const steps = document.querySelectorAll('.cdc-stepper .step');
  const bar = document.getElementById('cdcProgress');
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');

  function show(n) {
    step = Math.max(1, Math.min(total, n));
    panels.forEach(p => p.classList.toggle('active', +p.dataset.panel === step));
    steps.forEach(s => {
      const sn = +s.dataset.step;
      s.classList.toggle('active', sn === step);
      s.classList.toggle('done', sn < step);
    });
    bar.style.width = ((step / total) * 100) + '%';
    btnPrev.disabled = step === 1;
    btnNext.style.display = step === total ? 'none' : '';
  }
  btnPrev.addEventListener('click', () => show(step - 1));
  btnNext.addEventListener('click', () => show(step + 1));
  steps.forEach(s => s.addEventListener('click', () => show(+s.dataset.step)));
  show(1);

  // localStorage draft backup
  const form = document.getElementById('cdcForm');
  const key = 'cdc_draft_<?= (int)$projet_id ?>';
  try {
    const saved = localStorage.getItem(key);
    // server data has priority; only restore empty fields if needed — skip for simplicity
  } catch(e) {}
  form.addEventListener('change', () => {
    try {
      const fd = new FormData(form);
      const obj = {};
      for (const [k,v] of fd.entries()) {
        if (k.endsWith('[]')) {
          const kk = k.slice(0,-2);
          if (!obj[kk]) obj[kk] = [];
          obj[kk].push(v);
        } else obj[k] = v;
      }
      localStorage.setItem(key, JSON.stringify(obj));
    } catch(e) {}
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
