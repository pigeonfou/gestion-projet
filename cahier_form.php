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
        'objectifs','resultats_attendus','cas_usage','profils_utilisateurs',
        'delais','livrables_attendus',
    ];
    foreach ($textKeys as $k) {
        $specs[$k] = trim($_POST[$k] ?? '');
    }
    // Arrays (checkboxes)

    // Required validation on "generate"
    $errors = [];
    if ($action === 'generate') {
        if ($specs['objectifs'] === '') $errors[] = 'Les objectifs et le contexte sont obligatoires.';
    }

    if ($errors) {
        setFlash('error', implode(' ', $errors));
    } else {
        saveSpecs($cahier_id, $specs);
        // Mirror some fields into classic cahier columns
        $db->prepare('UPDATE cahiers SET objectifs=?, contexte=?, contraintes=?, date_maj=CURRENT_TIMESTAMP WHERE id=?')
           ->execute([$specs['objectifs'], $specs['cas_usage'], $specs['cas_usage'], $cahier_id]);

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
    <button type="button" class="step" data-step="2"><span>2</span> Spécifications</button>
    <button type="button" class="step" data-step="3"><span>3</span> Planning</button>
</div>
<div class="cdc-progress"><div class="cdc-progress-bar" id="cdcProgress" style="width:33%"></div></div>

<form method="POST" id="cdcForm" action="<?= url('cahier_form.php') ?>">
    <input type="hidden" name="projet_id" value="<?= $projet_id ?>">
    <input type="hidden" name="form_action" id="formAction" value="save">

    <!-- ===== 1 ===== -->
    <div class="cdc-step-panel active" data-panel="1">
        <div class="card">
            <div class="card-header"><h2>1. Contexte, objectifs et besoins utilisateurs</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Objectifs et contexte *</label>
                    <textarea name="objectifs" class="form-control" rows="3" required><?= e($s['objectifs']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Hors périmètre du projet</label>
                    <textarea name="resultats_attendus" class="form-control" rows="3"><?= e($s['resultats_attendus']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Contraintes</label>
                    <textarea name="cas_usage" class="form-control" rows="3"><?= e($s['cas_usage']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Profils des utilisateurs finaux</label>
                    <textarea name="profils_utilisateurs" class="form-control" rows="2"><?= e($s['profils_utilisateurs']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 2 ===== -->
    <div class="cdc-step-panel" data-panel="2">
        <div class="card">
            <div class="card-header"><h2>2. Spécifications fonctionnelles</h2></div>
            <div class="card-body">
                <p class="text-muted text-sm">Aucun champ pour le moment. Les spécifications fonctionnelles pourront être définies ici ultérieurement.</p>
            </div>
        </div>
    </div>


    <!-- ===== 3 ===== -->
    <div class="cdc-step-panel" data-panel="3">
        <div class="card">
            <div class="card-header"><h2>3. Contraintes planning et livrables attendus</h2></div>
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
  const total = 3;
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
