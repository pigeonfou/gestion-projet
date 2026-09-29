<?php
$pageTitle = 'Article stock';
$activePage = 'stocks';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/stock/stock_helpers.php';
requerirConnexion();
runSchemaMigrations();

$db = getDB();
$user = utilisateurCourant();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = $_GET['action'] ?? ($id > 0 ? 'modifier' : 'creer');

// Enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_article') {
    csrfRequire();
    if (!estAdmin()) {
        setFlash('error', 'La gestion du catalogue stock est réservée aux administrateurs.');
        redirect('stocks.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $reference = trim($_POST['reference'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $type = ($_POST['type'] ?? 'piece') === 'equipement' ? 'equipement' : 'piece';
    $description = trim($_POST['description'] ?? '');
    $qStockInitial = (float)str_replace(',', '.', (string)($_POST['quantite_stock'] ?? '0'));
    $qMin = (float)str_replace(',', '.', (string)($_POST['quantite_min'] ?? '0'));
    $unite = trim($_POST['unite'] ?? 'u') ?: 'u';
    $valeur = (float)str_replace(',', '.', (string)($_POST['valeur_unitaire'] ?? '0'));
    $taxe = ($_POST['taxe'] ?? 'HT') === 'TTC' ? 'TTC' : 'HT';
    $documentation = trim($_POST['documentation'] ?? '');
    $emplacement = trim($_POST['emplacement'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($reference === '' || $designation === '') {
        setFlash('error', 'Référence et désignation obligatoires.');
        redirect($id > 0 ? 'stock_article.php?id=' . $id : 'stock_article.php?action=creer');
    }

    try {
        if ($id > 0) {
            // La quantité n'est plus modifiable directement : elle provient du journal.
            $db->prepare('UPDATE stock_articles SET reference=?, designation=?, type=?, description=?, quantite_min=?, unite=?, valeur_unitaire=?, taxe=?, documentation=?, emplacement=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
               ->execute([$reference, $designation, $type, $description, $qMin, $unite, $valeur, $taxe, $documentation, $emplacement, $notes, $id]);
            stockSyncLegacyQuantity($id);
        } else {
            $db->prepare('INSERT INTO stock_articles (reference, designation, type, description, quantite_stock, quantite_min, unite, valeur_unitaire, taxe, documentation, emplacement, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$reference, $designation, $type, $description, 0, $qMin, $unite, $valeur, $taxe, $documentation, $emplacement, $notes]);
            $id = (int)$db->lastInsertId();

            // À la création, la quantité initiale est enregistrée comme un mouvement.
            if ($qStockInitial > 0) {
                $lab = stockDefaultLocationId();
                stockMovement($id, 'correction_inventaire', $qStockInitial, null, $lab, null, null, null, 'CREATION_ARTICLE', 'Stock initial à la création', (int)($user['id'] ?? 0) ?: null);
            }
        }

        // Fournisseurs liés
        $db->prepare('DELETE FROM stock_article_fournisseur WHERE article_id = ?')->execute([$id]);
        $fIds = $_POST['fourn_id'] ?? [];
        $fRefs = $_POST['fourn_ref'] ?? [];
        $fPrix = $_POST['fourn_prix'] ?? [];
        $fDelai = $_POST['fourn_delai'] ?? [];
        $fPref = $_POST['fourn_pref'] ?? [];
        if (is_array($fIds)) {
            $ins = $db->prepare('INSERT OR IGNORE INTO stock_article_fournisseur (article_id, fournisseur_id, reference_fournisseur, prix, delai_jours, preferentiel) VALUES (?,?,?,?,?,?)');
            foreach ($fIds as $i => $fid) {
                $fid = (int)$fid;
                if ($fid <= 0) continue;
                $pref = (isset($fPref[$i]) && (string)$fPref[$i] === '1') ? 1 : 0;
                $prix = (float)str_replace(',', '.', (string)($fPrix[$i] ?? '0'));
                $delai = ($fDelai[$i] ?? '') === '' ? null : (int)$fDelai[$i];
                $ins->execute([$id, $fid, trim((string)($fRefs[$i] ?? '')), $prix, $delai, $pref]);
            }
        }

        setFlash('success', 'Article enregistré.');
        redirect('stock_article.php?id=' . $id);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            setFlash('error', 'Cette référence existe déjà.');
        } else {
            setFlash('error', 'Erreur d\'enregistrement.');
        }
        redirect($id > 0 ? 'stock_article.php?id=' . $id : 'stock_article.php?action=creer');
    }
}

// Ajout usage manuel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_usage') {
    csrfRequire();
    $id = (int)($_POST['id'] ?? 0);
    $projetId = (int)($_POST['projet_id'] ?? 0);
    $qty = (float)str_replace(',', '.', (string)($_POST['quantite'] ?? '1'));
    $note = trim($_POST['note'] ?? '');
    if ($id > 0 && $projetId > 0 && $qty > 0) {
        requerirAccesProjet($projetId);
        try {
            $db->beginTransaction();
            $currentStock = stockQuantity($id);
            if (!empty($_POST['decrementer']) && $currentStock < $qty) {
                throw new RuntimeException('Stock insuffisant (stock calculé : ' . rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.') . ').');
            }
            $db->prepare('INSERT INTO stock_usages (article_id, projet_id, quantite, note) VALUES (?,?,?,?)')
               ->execute([$id, $projetId, $qty, $note]);
            if (!empty($_POST['decrementer'])) {
                $lab = stockDefaultLocationId();
                stockMovement($id, 'consommation', $qty, $lab, null, $projetId, null, null, 'USAGE_PROJET', $note, (int)($user['id'] ?? 0) ?: null);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            setFlash('error', $e->getMessage());
            redirect('stock_article.php?id=' . $id);
        }
        setFlash('success', 'Usage projet enregistré.');
    }
    redirect('stock_article.php?id=' . $id);
}

$article = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM stock_articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) {
        setFlash('error', 'Article introuvable.');
        redirect('stocks.php');
    }
}

$fournisseursAll = $db->query('SELECT * FROM stock_fournisseurs ORDER BY nom')->fetchAll();
$liensFourn = [];
$usages = [];
$projets = $db->query('SELECT id, nom FROM projets ORDER BY nom')->fetchAll();

if ($article) {
    $stmt = $db->prepare('SELECT af.*, f.nom AS fournisseur_nom FROM stock_article_fournisseur af JOIN stock_fournisseurs f ON f.id = af.fournisseur_id WHERE af.article_id = ?');
    $stmt->execute([$id]);
    $liensFourn = $stmt->fetchAll();
    $stmt = $db->prepare('SELECT u.*, p.nom AS projet_nom FROM stock_usages u JOIN projets p ON p.id = u.projet_id WHERE u.article_id = ? ORDER BY u.date_usage DESC');
    $stmt->execute([$id]);
    $usages = $stmt->fetchAll();
    $pageTitle = $article['reference'];
} else {
    $pageTitle = 'Nouvel article';
}

require __DIR__ . '/includes/header.php';
$a = $article ?: [
    'reference' => '', 'designation' => '', 'type' => 'piece', 'description' => '',
    'quantite_stock' => 0, 'quantite_min' => 0, 'unite' => 'u', 'valeur_unitaire' => 0,
    'taxe' => 'HT', 'documentation' => '', 'emplacement' => '', 'notes' => '',
];
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-cube"></i> <?= e($pageTitle) ?></h1>
    <p class="text-muted text-sm mt-1"><a href="<?= url('stocks.php') ?>">← Stocks & Matériel</a></p>
  </div>
</div>

<form method="POST" class="card" style="padding:1.25rem;margin-bottom:1.25rem;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="save_article">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label>Référence *</label>
      <input type="text" name="reference" class="form-control" required value="<?= e($a['reference']) ?>">
    </div>
    <div class="form-group">
      <label>Type *</label>
      <select name="type" class="form-control">
        <option value="piece" <?= $a['type'] === 'piece' ? 'selected' : '' ?>>Pièce détachée</option>
        <option value="equipement" <?= $a['type'] === 'equipement' ? 'selected' : '' ?>>Équipement</option>
      </select>
    </div>
    <div class="form-group" style="grid-column:1/-1;">
      <label>Désignation *</label>
      <input type="text" name="designation" class="form-control" required value="<?= e($a['designation']) ?>">
    </div>
    <div class="form-group" style="grid-column:1/-1;">
      <label>Description</label>
      <textarea name="description" class="form-control" rows="2"><?= e($a['description']) ?></textarea>
    </div>
    <div class="form-group">
      <label>Stock physique calculé</label>
      <input type="text" class="form-control" readonly value="<?= e(rtrim(rtrim(number_format((float)$a['quantite_stock'], 3, '.', ''), '0'), '.')) ?> <?= e($a['unite'] ?: 'u') ?>">
      <small class="text-muted">Le stock est désormais piloté par les mouvements, pas par une saisie directe.</small>
    </div>
    <div class="form-group">
      <label>Seuil minimum (alerte)</label>
      <input type="number" step="any" name="quantite_min" class="form-control" value="<?= e((string)$a['quantite_min']) ?>">
    </div>
    <div class="form-group">
      <label>Unité</label>
      <input type="text" name="unite" class="form-control" value="<?= e($a['unite'] ?: 'u') ?>" placeholder="u, m, kg…">
    </div>
    <div class="form-group">
      <label>Emplacement</label>
      <input type="text" name="emplacement" class="form-control" value="<?= e($a['emplacement']) ?>" placeholder="Rayon / tiroir…">
    </div>
    <div class="form-group">
      <label>Valeur unitaire</label>
      <div style="display:flex;gap:.5rem;">
        <input type="number" step="any" name="valeur_unitaire" class="form-control" value="<?= e((string)$a['valeur_unitaire']) ?>">
        <select name="taxe" class="form-control" style="max-width:5rem;">
          <option value="HT" <?= ($a['taxe'] ?? 'HT') === 'HT' ? 'selected' : '' ?>>HT</option>
          <option value="TTC" <?= ($a['taxe'] ?? '') === 'TTC' ? 'selected' : '' ?>>TTC</option>
        </select>
      </div>
    </div>
    <div class="form-group" style="grid-column:1/-1;">
      <label>Documentation (liens, n° doc, notes techniques)</label>
      <textarea name="documentation" class="form-control" rows="2" placeholder="URL datasheet, n° plan, fichier Nextcloud…"><?= e($a['documentation']) ?></textarea>
    </div>
    <div class="form-group" style="grid-column:1/-1;">
      <label>Notes internes</label>
      <textarea name="notes" class="form-control" rows="2"><?= e($a['notes']) ?></textarea>
    </div>
  </div>

  <h3 style="margin:1.25rem 0 .75rem;font-size:1rem;">Fournisseurs</h3>
  <div id="fournRows">
    <?php
      $rows = $liensFourn ?: [['fournisseur_id' => '', 'reference_fournisseur' => '', 'prix' => '', 'delai_jours' => '', 'preferentiel' => 0]];
      foreach ($rows as $lf):
    ?>
    <div class="fourn-row" style="display:grid;grid-template-columns:2fr 1.5fr 1fr 1fr auto auto;gap:.5rem;margin-bottom:.5rem;align-items:center;">
      <select name="fourn_id[]" class="form-control">
        <option value="">— Fournisseur —</option>
        <?php foreach ($fournisseursAll as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= ((int)($lf['fournisseur_id'] ?? 0) === (int)$f['id']) ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="fourn_ref[]" class="form-control" placeholder="Réf. fournisseur" value="<?= e($lf['reference_fournisseur'] ?? '') ?>">
      <input type="number" step="any" name="fourn_prix[]" class="form-control" placeholder="Prix" value="<?= e((string)($lf['prix'] ?? '')) ?>">
      <input type="number" name="fourn_delai[]" class="form-control" placeholder="Délai j" value="<?= e((string)($lf['delai_jours'] ?? '')) ?>">
      <input type="hidden" name="fourn_pref[]" value="0" class="pref-hidden">
      <label class="text-sm"><input type="checkbox" class="pref-check" value="1" <?= !empty($lf['preferentiel']) ? 'checked' : '' ?> onchange="this.previousElementSibling.value=this.checked?'1':'0'"> Préféré</label>
      <button type="button" class="btn-sf-del" onclick="this.closest('.fourn-row').remove()" title="Retirer">&times;</button>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-secondary btn-sm" id="addFourn"><i class="fas fa-plus"></i> Ajouter un fournisseur</button>
  <p class="text-muted text-xs mt-1">Gérer le catalogue : <a href="<?= url('stock_fournisseurs.php') ?>">Fournisseurs</a></p>

  <div style="margin-top:1.25rem;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
    <a href="<?= url('stocks.php') ?>" class="btn btn-secondary">Annuler</a>
  </div>
</form>

<?php if ($article): ?>
<div class="card" style="padding:1.25rem;margin-bottom:1.25rem;">
  <h3 style="margin:0 0 1rem;font-size:1rem;">Projets ayant utilisé cette référence</h3>
  <?php if (empty($usages)): ?>
    <p class="text-muted text-sm">Aucun usage enregistré.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Projet</th><th>Qté</th><th>Date</th><th>Note</th></tr></thead>
      <tbody>
        <?php foreach ($usages as $u): ?>
          <tr>
            <td><a href="<?= url('projet.php?id=' . (int)$u['projet_id']) ?>"><?= e($u['projet_nom']) ?></a></td>
            <td><?= e((string)$u['quantite']) ?></td>
            <td class="text-sm text-muted"><?= e($u['date_usage'] ?? '') ?></td>
            <td class="text-sm"><?= e($u['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h4 style="margin:1.25rem 0 .5rem;font-size:.9rem;">Enregistrer un usage projet</h4>
  <form method="POST" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add_usage">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-group">
      <label>Projet</label>
      <select name="projet_id" class="form-control" required>
        <option value="">—</option>
        <?php foreach ($projets as $pr): ?>
          <option value="<?= (int)$pr['id'] ?>"><?= e($pr['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Quantité</label>
      <input type="number" step="any" name="quantite" class="form-control" value="1" style="width:6rem;">
    </div>
    <div class="form-group">
      <label>Note</label>
      <input type="text" name="note" class="form-control" placeholder="Optionnel">
    </div>
    <label class="text-sm"><input type="checkbox" name="decrementer" value="1" checked> Décrémenter le stock</label>
    <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
  </form>
</div>
<?php endif; ?>

<script>
(function(){
  const box = document.getElementById('fournRows');
  const addBtn = document.getElementById('addFourn');
  if (!box || !addBtn) return;
  const opts = <?= json_encode(array_map(static fn($f) => ['id' => (int)$f['id'], 'nom' => $f['nom']], $fournisseursAll), JSON_UNESCAPED_UNICODE) ?>;
  addBtn.addEventListener('click', () => {
    let o = '<option value="">— Fournisseur —</option>';
    opts.forEach(f => { o += '<option value="'+f.id+'">'+f.nom.replace(/</g,'&lt;')+'</option>'; });
    const div = document.createElement('div');
    div.className = 'fourn-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 1.5fr 1fr 1fr auto auto;gap:.5rem;margin-bottom:.5rem;align-items:center;';
    div.innerHTML = '<select name="fourn_id[]" class="form-control">'+o+'</select>' +
      '<input type="text" name="fourn_ref[]" class="form-control" placeholder="Réf. fournisseur">' +
      '<input type="number" step="any" name="fourn_prix[]" class="form-control" placeholder="Prix">' +
      '<input type="number" name="fourn_delai[]" class="form-control" placeholder="Délai j">' +
      '<input type="hidden" name="fourn_pref[]" value="0"><label class="text-sm"><input type="checkbox" value="1" onchange="this.previousElementSibling.value=this.checked?\'1\':\'0\'"> Préféré</label>' +
      '<button type="button" class="btn-sf-del" onclick="this.closest(\'.fourn-row\').remove()">&times;</button>';
    box.appendChild(div);
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
