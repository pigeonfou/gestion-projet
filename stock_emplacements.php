<?php
$pageTitle = 'Emplacements stock';
$activePage = 'stocks';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/stock/stock_helpers.php';
requerirConnexion();
runSchemaMigrations();
$db=getDB();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_location') {
    csrfRequire();
    if (!estAdmin()) { setFlash('error','Action réservée aux administrateurs.'); redirect('stock_emplacements.php'); }
    $code=strtoupper(trim($_POST['code']??'')); $nom=trim($_POST['nom']??''); $parent=(int)($_POST['parent_id']??0);
    if($code===''||$nom===''){ setFlash('error','Code et nom obligatoires.'); redirect('stock_emplacements.php'); }
    try {
        $parentRow=null;
        if($parent){ $st=$db->prepare('SELECT id,chemin,niveau FROM stock_emplacements WHERE id=?'); $st->execute([$parent]); $parentRow=$st->fetch(); }
        $path=$parentRow ? $parentRow['chemin'].'/'.$code : $code;
        $level=$parentRow ? (int)$parentRow['niveau']+1 : 0;
        $db->prepare('INSERT INTO stock_emplacements(parent_id,code,nom,type,chemin,niveau) VALUES(?,?,?,?,?,?)')->execute([$parent?:null,$code,$nom,'zone',$path,$level]);
        setFlash('success','Emplacement créé.');
    } catch(Throwable $e){ setFlash('error','Impossible de créer cet emplacement : '.$e->getMessage()); }
    redirect('stock_emplacements.php');
}
$locations=$db->query('SELECT * FROM stock_emplacements WHERE actif=1 ORDER BY chemin')->fetchAll();
require __DIR__.'/includes/header.php';
?>
<div class="page-header"><div><h1><i class="fas fa-map-marker-alt"></i> Emplacements physiques</h1><p class="text-muted text-sm mt-1"><a href="<?= url('stocks.php') ?>">← Stocks</a> — arborescence du laboratoire</p></div></div>
<?php if(estAdmin()): ?><div class="card" style="padding:1.25rem;margin-bottom:1.25rem;"><h3 style="margin-top:0;">Nouvel emplacement</h3><form method="POST" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap"><?= csrfField() ?><input type="hidden" name="action" value="add_location"><div class="form-group"><label>Code</label><input name="code" class="form-control" placeholder="R01" required></div><div class="form-group"><label>Nom</label><input name="nom" class="form-control" placeholder="Tiroir R01" required></div><div class="form-group"><label>Parent</label><select name="parent_id" class="form-control"><option value="">— Racine —</option><?php foreach($locations as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['chemin']) ?></option><?php endforeach; ?></select></div><button class="btn btn-primary" type="submit">Créer</button></form></div><?php endif; ?>
<div class="card"><table class="table"><thead><tr><th>Code</th><th>Nom</th><th>Chemin</th><th>Niveau</th></tr></thead><tbody><?php foreach($locations as $l): ?><tr><td><strong><?= e($l['code']) ?></strong></td><td><?= e($l['nom']) ?></td><td><?= e($l['chemin']) ?></td><td><?= (int)$l['niveau'] ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require __DIR__.'/includes/footer.php'; ?>
