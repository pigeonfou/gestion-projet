<?php
require_once __DIR__.'/includes/bootstrap.php';
requerirConnexion();
$pageTitle = 'Stock & inventaire';
$activePage = 'inventaire';
$useAppShell = true;
$mainClass = 'inventory-shell';
$pageStyles = ['assets/css/inventaire.css?v=2'];
require __DIR__.'/includes/header.php';
?>
<div class="inventory-app">
<div class="layout"><aside><div class="navtitle">INVENTAIRE</div><button class="sidenav active" data-module="components">Composants</button><button class="sidenav" data-module="stock">Stock</button><button class="sidenav" data-module="quality">Réceptions / qualité</button><button class="sidenav" data-module="reservations">Réservations</button><div class="navtitle" id="contextLabel">COMPOSANTS</div><div id="contextNav"></div></aside><div id="workspace"><p class="inventory-loading">Chargement de l’inventaire…</p></div></div>
<dialog id="entryDialog" class="formdialog"><form id="entryForm"><div class="modalhead"><h2 id="entryTitle"></h2><button type="button" class="btn small" data-close>Fermer</button></div><div class="formbody"><div class="formgrid" id="entryFields"></div><p class="formerror" id="entryError" role="alert"></p></div><div class="modalfoot"><button type="button" class="btn" data-close>Annuler</button><button type="submit" id="entrySave" class="btn primary">Enregistrer</button></div></form></dialog>
<div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
<script>window.inventoryConfig={api:<?=json_encode(url('inventory_api.php'),JSON_HEX_TAG)?>,documents:<?=json_encode(url('inventory_document.php'),JSON_HEX_TAG)?>};</script><script src="<?=e(url('assets/js/inventaire.js?v=1'))?>" defer></script>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
