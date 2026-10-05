<?php
$pageTitle = 'Mouvements de stock';
$activePage = 'stocks';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/stock/stock_helpers.php';
requerirConnexion();
runSchemaMigrations();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'movement') {
    csrfRequire();
    if (!estAdmin()) {
        setFlash('error', 'Action réservée aux administrateurs.');
        redirect('stock_mouvements.php');
    }
    $articleId = (int)($_POST['article_id'] ?? 0);
    $type = trim($_POST['type'] ?? '');
    $qty = (float)str_replace(',', '.', (string)($_POST['quantite'] ?? '0'));
    $source = (int)($_POST['source'] ?? 0) ?: null;
    $dest = (int)($_POST['destination'] ?? 0) ?: null;
    $projet = (int)($_POST['projet_id'] ?? 0) ?: null;
    $note = trim($_POST['note'] ?? '');
    try {
        if ($articleId <= 0) throw new RuntimeException('Article obligatoire.');
        if ($type === 'transfert' && (!$source || !$dest)) throw new RuntimeException('Source et destination obligatoires pour un transfert.');
        if (in_array($type, ['consommation','affectation_projet','rebut','demontage'], true) && !$source) {
            $source = stockDefaultLocationId();
        }
        if (in_array($type, ['reception','retour_projet','recuperation','correction_inventaire'], true) && !$dest) {
            $dest = stockDefaultLocationId();
        }
        if ($source && in_array($type, ['consommation','affectation_projet','rebut','demontage','transfert'], true) && stockQuantity($articleId, $source) < $qty) {
            throw new RuntimeException('Stock insuffisant dans l’emplacement source.');
        }
        stockMovement($articleId, $type, $qty, $source, $dest, $projet, null, null, 'MANUEL', $note, (int)(utilisateurCourant()['id'] ?? 0) ?: null);
        setFlash('success', 'Mouvement enregistré.');
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
    }
    redirect('stock_mouvements.php');
}

$articleFilter=(int)($_GET['article_id']??0);$pid=(int)($_GET['projet_id']??0);
$movementQuery = $db->prepare("SELECT m.*, a.reference, a.designation, es.nom AS source_nom, ed.nom AS destination_nom,
    p.nom AS projet_nom, f.nom AS fournisseur_nom
    FROM stock_mouvements m
    JOIN stock_articles a ON a.id=m.article_id
    LEFT JOIN stock_emplacements es ON es.id=m.emplacement_source_id
    LEFT JOIN stock_emplacements ed ON ed.id=m.emplacement_destination_id
    LEFT JOIN projets p ON p.id=m.projet_id
    LEFT JOIN stock_fournisseurs f ON f.id=m.fournisseur_id
    WHERE (?=0 OR m.article_id=?) AND (?=0 OR m.projet_id=?)
    ORDER BY m.created_at DESC, m.id DESC LIMIT 200");
$movementQuery->execute([$articleFilter,$articleFilter,$pid,$pid]);
$rows=$movementQuery->fetchAll();
$articles = $db->query("SELECT id,reference,designation FROM stock_articles ORDER BY reference")->fetchAll();
$locations = $db->query("SELECT id,nom,chemin FROM stock_emplacements WHERE actif=1 ORDER BY chemin")->fetchAll();
$projets = $db->query("SELECT id,nom FROM projets ORDER BY nom")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-exchange-alt"></i> Mouvements de stock</h1>
    <p class="text-muted text-sm mt-1"><a href="<?= url('stocks.php') ?>">← Stocks</a> — journal de traçabilité physique</p>
  </div>
</div>

<?php if (estAdmin()): ?>
<div class="card" style="padding:1.25rem;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;">Enregistrer un mouvement</h3>
  <form method="POST" style="display:grid;grid-template-columns:2fr 1.5fr 1fr 1.5fr 1.5fr 1.5fr;gap:.75rem;align-items:end;">
    <?= csrfField() ?><input type="hidden" name="action" value="movement">
    <div class="form-group"><label>Article</label><select name="article_id" class="form-control" required><option value="">—</option><?php foreach($articles as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['reference'].' — '.$a['designation']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Type</label><select name="type" class="form-control" required>
      <option value="reception">Réception</option><option value="consommation">Consommation</option><option value="affectation_projet">Affectation projet</option><option value="retour_projet">Retour projet</option><option value="transfert">Transfert</option><option value="correction_inventaire">Correction inventaire</option><option value="rebut">Rebut</option><option value="demontage">Démontage</option><option value="recuperation">Récupération</option>
    </select></div>
    <div class="form-group"><label>Quantité</label><input type="number" step="any" min="0.000001" name="quantite" class="form-control" required></div>
    <div class="form-group"><label>Source</label><select name="source" class="form-control"><option value="">—</option><?php foreach($locations as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['chemin']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Destination</label><select name="destination" class="form-control"><option value="">—</option><?php foreach($locations as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['chemin']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Projet</label><select name="projet_id" class="form-control"><option value="">—</option><?php foreach($projets as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nom']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group" style="grid-column:1/-1;"><label>Note</label><input type="text" name="note" class="form-control"></div>
    <div><button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Enregistrer</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card" style="overflow-x:auto;">
<table class="table"><thead><tr><th>Date</th><th>Article</th><th>Type</th><th>Qté</th><th>Source</th><th>Destination</th><th>Projet</th><th>Note</th></tr></thead><tbody>
<?php foreach($rows as $m): ?>
<tr><td class="text-sm"><?= e($m['created_at']) ?></td><td><a href="<?= url('stock_article.php?id='.(int)$m['article_id']) ?>"><?= e($m['reference']) ?></a><br><span class="text-muted text-xs"><?= e($m['designation']) ?></span></td><td><?= e(str_replace('_',' ',$m['type'])) ?></td><td><?= e((string)$m['quantite']) ?></td><td><?= e($m['source_nom'] ?: '—') ?></td><td><?= e($m['destination_nom'] ?: '—') ?></td><td><?= e($m['projet_nom'] ?: '—') ?></td><td><?= e($m['note'] ?: '') ?></td></tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="text-muted">Aucun mouvement.</td></tr><?php endif; ?>
</tbody></table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
