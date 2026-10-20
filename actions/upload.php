<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/NextcloudClient.php';
require_once __DIR__.'/../src/Services/DocumentService.php';
requerirConnexion();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfRequire(); }
seedSettingsIfEmpty();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projets.php');
}

$projet_id = (int)($_POST['projet_id'] ?? 0);
$phase = $_POST['phase'] ?? 'cahier';
$phasesValides = ['cahier','capacite','investissement','proto','tests','production','livraison'];
if (!in_array($phase, $phasesValides)) $phase = 'cahier';

if ($projet_id <= 0 || empty($_FILES['fichier']['name'])) {
    setFlash('error', 'Projet ou fichier manquant.');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

if (getSetting('nextcloud_enabled', '0') !== '1') {
    setFlash('error', 'Intégration Nextcloud désactivée. Activez-la dans Paramètres.');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$db = getDB();
$stmt = $db->prepare('SELECT id, nom FROM projets WHERE id = ?');
$stmt->execute([$projet_id]);
$projet = $stmt->fetch();
if (!$projet) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}
requerirAccesProjet($projet_id);

$file = $_FILES['fichier'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', 'Erreur upload (code ' . $file['error'] . ').');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$safeName = preg_replace('/[^\p{L}\p{N}\_\-\.\s]/u', '_', $file['name']) ?? 'fichier';
$safeName = preg_replace('/\s+/', '_', $safeName);

$root = trim(getSetting('nextcloud_root', 'ProjectFlow'), '/');
$projFolder = 'Projet_' . $projet_id . '_' . sanitizePathSegment($projet['nom']);
$phaseFolder = phaseFolderName($phase);
$remotePath = pfDocumentPath($projet_id,$phaseFolder,$safeName,$projet['nom']);

$nc = new NextcloudClient();
if (!$nc->isConfigured()) {
    setFlash('error', 'Nextcloud non configuré.');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$operation=pfDocumentIntent($db,['projet_id'=>$projet_id,'phase'=>$phase,'nom_fichier'=>$safeName,'chemin_nextcloud'=>$remotePath,'taille'=>(int)$file['size'],'mime'=>$file['type'] ?? null,'uploader_id'=>utilisateurCourant()['id']]);
$result = $nc->upload($remotePath, $file['tmp_name']);
if (!$result['ok']) {
    setFlash('error', 'Nextcloud : ' . $result['message']);
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

try { pfDocumentUploaded($db,$operation); pfDocumentComplete($db,$operation); }
catch (Throwable $e) { error_log('ProjectFlow document: '.$e->getMessage()); setFlash('error','Transfert effectué ; confirmation différée. Consultez les opérations documentaires avant de réessayer.'); redirect('documents_externes.php?projet_id='.$projet_id); }

setFlash('success', 'Fichier « ' . $safeName . ' » envoyé vers Nextcloud.');
redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
