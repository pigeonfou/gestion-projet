<?php
$pageTitle = 'Stocks & Matériel';
$activePage = 'stocks';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/stock/stock_helpers.php';
requerirConnexion();
runSchemaMigrations();

$db = getDB();
$user = utilisateurCourant();

// Suppression article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_article') {
    csrfRequire();
    if (!estAdmin()) {
        setFlash('error', 'Action réservée aux administrateurs.');
        redirect('stocks.php');
    }
    $aid = (int)($_POST['article_id'] ?? 0);
    if ($aid > 0) {
        $st = $db->prepare('SELECT COUNT(*) FROM stock_mouvements WHERE article_id=?');
        $st->execute([$aid]);
        if ((int)$st->fetchColumn() > 0) {
            setFlash('error', 'Impossible de supprimer une référence ayant un historique de stock. Désactivez-la ou conservez-la pour préserver la traçabilité.');
        } else {
            $db->prepare('DELETE FROM stock_articles WHERE id = ?')->execute([$aid]);
            setFlash('success', 'Article supprimé.');
        }
    }
    redirect('stocks.php');
}

$q = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$alertOnly = !empty($_GET['alerte']);

$sql = "SELECT a.*,
    COALESCE((SELECT SUM(CASE
        WHEN m.type IN ('reception','retour_projet','recuperation','correction_inventaire') THEN m.quantite
        WHEN m.type IN ('consommation','affectation_projet','rebut','demontage') THEN -m.quantite
        ELSE 0 END)
        FROM stock_mouvements m WHERE m.article_id=a.id),0) AS stock_calcule,
    (SELECT COUNT(DISTINCT p.id) FROM projets p WHERE
        EXISTS (SELECT 1 FROM stock_usages u WHERE u.article_id=a.id AND u.projet_id=p.id)
        OR EXISTS (SELECT 1 FROM stock_mouvements m WHERE m.article_id=a.id AND m.projet_id=p.id)
    ) AS nb_usages,
    (SELECT GROUP_CONCAT(f.nom, ', ') FROM stock_article_fournisseur af
        JOIN stock_fournisseurs f ON f.id = af.fournisseur_id
        WHERE af.article_id = a.id) AS fournisseurs,
    (SELECT GROUP_CONCAT(e.nom, ', ') FROM stock_emplacements e
        WHERE e.id IN (
            SELECT emplacement_destination_id FROM stock_mouvements m WHERE m.article_id=a.id AND m.emplacement_destination_id IS NOT NULL
            UNION
            SELECT emplacement_source_id FROM stock_mouvements m WHERE m.article_id=a.id AND m.emplacement_source_id IS NOT NULL
        )) AS emplacements
    FROM stock_articles a WHERE 1=1";

$params = [];
if ($q !== '') {
    $sql .= ' AND (a.reference LIKE ? OR a.designation LIKE ? OR a.emplacement LIKE ? OR EXISTS (SELECT 1 FROM stock_emplacements e WHERE e.nom LIKE ? AND (e.id IN (SELECT emplacement_destination_id FROM stock_mouvements WHERE article_id=a.id) OR e.id IN (SELECT emplacement_source_id FROM stock_mouvements WHERE article_id=a.id))))';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (in_array($typeFilter, ['piece', 'equipement'], true)) {
    $sql .= ' AND a.type = ?';
    $params[] = $typeFilter;
}
if ($alertOnly) {
    $sql .= ' AND (SELECT COALESCE(SUM(CASE WHEN m.type IN (\'reception\',\'retour_projet\',\'recuperation\',\'correction_inventaire\') THEN m.quantite WHEN m.type IN (\'consommation\',\'affectation_projet\',\'rebut\',\'demontage\') THEN -m.quantite ELSE 0 END),0) FROM stock_mouvements m WHERE m.article_id=a.id) <= a.quantite_min';
}
$sql .= ' ORDER BY a.reference ASC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// KPI
$kpi = $db->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN a.type='piece' THEN 1 ELSE 0 END) AS pieces,
    SUM(CASE WHEN a.type='equipement' THEN 1 ELSE 0 END) AS equipements,
    SUM(CASE WHEN COALESCE(q.stock_calcule,0) <= a.quantite_min THEN 1 ELSE 0 END) AS alertes,
    SUM(COALESCE(q.stock_calcule,0) * a.valeur_unitaire) AS valeur_totale
    FROM stock_articles a
    LEFT JOIN (
        SELECT article_id, SUM(CASE
            WHEN type IN ('reception','retour_projet','recuperation','correction_inventaire') THEN quantite
            WHEN type IN ('consommation','affectation_projet','rebut','demontage') THEN -quantite
            ELSE 0 END) AS stock_calcule
        FROM stock_mouvements GROUP BY article_id
    ) q ON q.article_id=a.id")->fetch();

require __DIR__ . '/includes/header.php';
?>
<div class="page-header stocks-header">
  <div>
    <h1><i class="fas fa-boxes"></i> Stocks & Matériel</h1>
    <p class="text-muted text-sm mt-1">Pièces détachées et équipements du service R&amp;D — liés aux projets</p>
  </div>
  <div class="stocks-header-actions">
    <a href="<?= url('stock_mouvements.php') ?>" class="btn btn-secondary btn-sm"><i class="fas fa-exchange-alt"></i> Mouvements</a>
    <a href="<?= url('stock_emplacements.php') ?>" class="btn btn-secondary btn-sm"><i class="fas fa-map-marker-alt"></i> Emplacements</a>
    <a href="<?= url('stock_fournisseurs.php') ?>" class="btn btn-secondary btn-sm"><i class="fas fa-truck"></i> Fournisseurs</a>
    <?php if (estAdmin()): ?>
    <a href="<?= url('stock_article.php?action=creer') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouvel article</a>
    <?php endif; ?>
  </div>
</div>

<div class="dash-kpi-grid stocks-kpi">
  <div class="dash-kpi">
    <p class="dash-kpi-label">Articles</p>
    <p class="dash-kpi-value"><?= (int)($kpi['total'] ?? 0) ?></p>
  </div>
  <div class="dash-kpi">
    <p class="dash-kpi-label">Pièces / Équipements</p>
    <p class="dash-kpi-value" style="font-size:1.25rem;"><?= (int)($kpi['pieces'] ?? 0) ?> / <?= (int)($kpi['equipements'] ?? 0) ?></p>
  </div>
  <div class="dash-kpi">
    <p class="dash-kpi-label">Alertes stock bas</p>
    <p class="dash-kpi-value" style="color:<?= ((int)($kpi['alertes'] ?? 0) > 0) ? '#dc2626' : 'inherit' ?>"><?= (int)($kpi['alertes'] ?? 0) ?></p>
  </div>
  <div class="dash-kpi">
    <p class="dash-kpi-label">Valeur stock</p>
    <p class="dash-kpi-value" style="font-size:1.25rem;"><?= number_format((float)($kpi['valeur_totale'] ?? 0), 2, ',', ' ') ?> €</p>
  </div>
</div>

<form method="GET" class="stocks-filters card" style="padding:1rem;margin-bottom:1rem;">
  <div class="form-row" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
    <div class="form-group" style="flex:1;min-width:180px;">
      <label>Recherche</label>
      <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Réf., désignation, emplacement…">
    </div>
    <div class="form-group">
      <label>Type</label>
      <select name="type" class="form-control">
        <option value="">Tous</option>
        <option value="piece" <?= $typeFilter === 'piece' ? 'selected' : '' ?>>Pièce détachée</option>
        <option value="equipement" <?= $typeFilter === 'equipement' ? 'selected' : '' ?>>Équipement</option>
      </select>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="alerte" value="1" <?= $alertOnly ? 'checked' : '' ?>> Stock bas uniquement</label>
    </div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filtrer</button>
    <a href="<?= url('stocks.php') ?>" class="btn btn-secondary btn-sm">Réinitialiser</a>
  </div>
</form>

<div class="card" style="overflow-x:auto;">
  <table class="table stocks-table">
    <thead>
      <tr>
        <th>Référence</th>
        <th>Désignation</th>
        <th>Type</th>
        <th>Stock</th>
        <th>Valeur unit.</th>
        <th>Fournisseurs</th>
        <th>Projets</th>
        <th>Emplacement</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($articles)): ?>
        <tr><td colspan="9" class="text-muted" style="padding:1.5rem;">Aucun article. <a href="<?= url('stock_article.php?action=creer') ?>">Créer le premier</a>.</td></tr>
      <?php endif; ?>
      <?php foreach ($articles as $a): ?>
        <?php
          $low = (float)$a['stock_calcule'] <= (float)$a['quantite_min'];
          $typeLabel = $a['type'] === 'equipement' ? 'Équipement' : 'Pièce';
        ?>
        <tr class="<?= $low ? 'stock-low' : '' ?>">
          <td><a href="<?= url('stock_article.php?id=' . (int)$a['id']) ?>" class="stock-ref"><?= e($a['reference']) ?></a></td>
          <td><?= e($a['designation']) ?></td>
          <td><span class="badge-type badge-<?= e($a['type']) ?>"><?= e($typeLabel) ?></span></td>
          <td>
            <strong><?= e(rtrim(rtrim(number_format((float)$a['stock_calcule'], 2, '.', ''), '0'), '.')) ?></strong>
            <?= e($a['unite'] ?: 'u') ?>
            <?php if ($low): ?><span class="stock-alert" title="Sous le seuil min. (<?= e((string)$a['quantite_min']) ?>)">⚠</span><?php endif; ?>
          </td>
          <td><?= number_format((float)$a['valeur_unitaire'], 2, ',', ' ') ?> € <?= e($a['taxe'] ?: 'HT') ?></td>
          <td class="text-sm text-muted"><?= e($a['fournisseurs'] ?: '—') ?></td>
          <td><?= (int)$a['nb_usages'] ?></td>
          <td class="text-sm"><?= e($a['emplacements'] ?: ($a['emplacement'] ?: '—')) ?></td>
          <td class="table-actions">
            <?php if (estAdmin()): ?>
            <a href="<?= url('stock_article.php?id=' . (int)$a['id']) ?>" class="btn btn-secondary btn-sm" title="Voir / éditer"><i class="fas fa-edit"></i></a>
            <?php else: ?>
            <a href="<?= url('stock_article.php?id=' . (int)$a['id']) ?>" class="btn btn-secondary btn-sm" title="Voir"><i class="fas fa-eye"></i></a>
            <?php endif; ?>
            <?php if (estAdmin()): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet article ?');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete_article">
              <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
