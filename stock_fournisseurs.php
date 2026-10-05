<?php
$pageTitle = 'Fournisseurs';
$activePage = 'stocks';
require_once __DIR__ . '/includes/bootstrap.php';
requerirConnexion();
runSchemaMigrations();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $fid = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tel = trim($_POST['telephone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($nom === '') {
            setFlash('error', 'Nom obligatoire.');
        } elseif ($fid > 0) {
            $db->prepare('UPDATE stock_fournisseurs SET nom=?, contact=?, email=?, telephone=?, notes=? WHERE id=?')
               ->execute([$nom, $contact, $email, $tel, $notes, $fid]);
            setFlash('success', 'Fournisseur mis à jour.');
        } else {
            $db->prepare('INSERT INTO stock_fournisseurs (nom, contact, email, telephone, notes) VALUES (?,?,?,?,?)')
               ->execute([$nom, $contact, $email, $tel, $notes]);
            setFlash('success', 'Fournisseur créé.');
        }
    }
    if ($action === 'delete' && estAdmin()) {
        $fid = (int)($_POST['id'] ?? 0);
        if ($fid > 0) {
            $db->prepare('DELETE FROM stock_fournisseurs WHERE id = ?')->execute([$fid]);
            setFlash('success', 'Fournisseur supprimé.');
        }
    }
    redirect('stock_fournisseurs.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM stock_fournisseurs WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}
$list = $db->query('SELECT f.*, (SELECT COUNT(*) FROM stock_article_fournisseur af WHERE af.fournisseur_id = f.id) AS nb_articles FROM stock_fournisseurs f ORDER BY f.nom')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-truck"></i> Fournisseurs</h1>
    <p class="text-muted text-sm mt-1"><a href="<?= url('stocks.php') ?>">← Stocks & Matériel</a></p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:1.25rem;align-items:start;">
  <form method="POST" class="card" style="padding:1.25rem;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <h3 style="margin:0 0 1rem;font-size:1rem;"><?= $edit ? 'Modifier' : 'Nouveau fournisseur' ?></h3>
    <div class="form-group">
      <label>Nom *</label>
      <input type="text" name="nom" class="form-control" required value="<?= e($edit['nom'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Contact</label>
      <input type="text" name="contact" class="form-control" value="<?= e($edit['contact'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>E-mail</label>
      <input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Téléphone</label>
      <input type="text" name="telephone" class="form-control" value="<?= e($edit['telephone'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Notes</label>
      <textarea name="notes" class="form-control" rows="2"><?= e($edit['notes'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer</button>
    <?php if ($edit): ?><a href="<?= url('stock_fournisseurs.php') ?>" class="btn btn-secondary btn-sm">Annuler</a><?php endif; ?>
  </form>

  <div class="card" style="overflow-x:auto;">
    <table class="table">
      <thead><tr><th>Nom</th><th>Contact</th><th>Articles</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($list)): ?>
          <tr><td colspan="4" class="text-muted">Aucun fournisseur.</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $f): ?>
          <tr>
            <td><a href="<?=url('fournisseur.php?id='.(int)$f['id'])?>"><strong><?= e($f['nom']) ?></strong></a>
              <?php if ($f['email'] || $f['telephone']): ?>
                <br><span class="text-xs text-muted"><?= e(trim(($f['email'] ?? '') . ' · ' . ($f['telephone'] ?? ''), ' ·')) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= e($f['contact'] ?: '—') ?></td>
            <td><?= (int)$f['nb_articles'] ?></td>
            <td class="table-actions">
              <a href="<?= url('stock_fournisseurs.php?edit=' . (int)$f['id']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
              <?php if (estAdmin()): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
