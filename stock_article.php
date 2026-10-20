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
        $db->beginTransaction();
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

        $db->commit();
        setFlash('success', 'Article enregistré.');
        redirect('stock_article.php?id=' . $id);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
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
        requerirGestionProjet($projetId);
        try {
            $db->beginTransaction();
            $lab = stockDefaultLocationId();
            $currentStock = stockQuantity($id, $lab);
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
$locationsStock = [];
$mouvements = [];
$projets = $db->query('SELECT id, nom FROM projets ORDER BY nom')->fetchAll();
$projetsMouvements = [];
if ($article) {
    $stmt = $db->prepare('SELECT DISTINCT p.id, p.nom FROM projets p JOIN stock_mouvements m ON m.projet_id=p.id WHERE m.article_id=? ORDER BY p.nom');
    $stmt->execute([$id]);
    $projetsMouvements = $stmt->fetchAll();
}

if ($article) {
    $stmt = $db->prepare('SELECT af.*, f.nom AS fournisseur_nom FROM stock_article_fournisseur af JOIN stock_fournisseurs f ON f.id = af.fournisseur_id WHERE af.article_id = ?');
    $stmt->execute([$id]);
    $liensFourn = $stmt->fetchAll();
    $stmt = $db->prepare('SELECT u.*, p.nom AS projet_nom FROM stock_usages u JOIN projets p ON p.id = u.projet_id WHERE u.article_id = ? ORDER BY u.date_usage DESC');
    $stmt->execute([$id]);
    $usages = $stmt->fetchAll();
    $locationsStock = stockByLocation($id);
    $stmt = $db->prepare("SELECT m.*, es.nom AS source_nom, ed.nom AS destination_nom
        FROM stock_mouvements m
        LEFT JOIN stock_emplacements es ON es.id=m.emplacement_source_id
        LEFT JOIN stock_emplacements ed ON ed.id=m.emplacement_destination_id
        WHERE m.article_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT 100");
    $stmt->execute([$id]);
    $mouvements = $stmt->fetchAll();
    $pageTitle = $article['reference'];
} else {
    $pageTitle = 'Nouvel article';
}

require __DIR__ . '/includes/header.php';
require __DIR__.'/views/stock/article.php';
