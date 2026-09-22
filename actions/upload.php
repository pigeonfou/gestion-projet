<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/NextcloudClient.php';
requerirConnexion();
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

$file = $_FILES['fichier'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', 'Erreur upload (code ' . $file['error'] . ').');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$maxMb = (int) getSetting('max_upload_mb', '0');
if ($maxMb > 0 && $file['size'] > $maxMb * 1024 * 1024) {
    setFlash('error', 'Fichier trop volumineux (max ' . $maxMb . ' Mo).');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$safeName = preg_replace('/[^\p{L}\p{N}\_\-\.\s]/u', '_', $file['name']) ?? 'fichier';
$safeName = preg_replace('/\s+/', '_', $safeName);

$root = trim(getSetting('nextcloud_root', 'ProjectFlow'), '/');
$projFolder = 'Projet_' . $projet_id . '_' . sanitizePathSegment($projet['nom']);
$phaseFolder = phaseFolderName($phase);
$remotePath = $root . '/' . $projFolder . '/' . $phaseFolder . '/' . $safeName;

$nc = new NextcloudClient();
if (!$nc->isConfigured()) {
    setFlash('error', 'Nextcloud non configuré.');
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$result = $nc->upload($remotePath, $file['tmp_name']);
if (!$result['ok']) {
    setFlash('error', 'Nextcloud : ' . $result['message']);
    redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
}

$user = utilisateurCourant();
ensureSettingsTable();
$stmt = $db->prepare('INSERT INTO documents (projet_id, phase, nom_fichier, chemin_nextcloud, taille, mime, uploader_id) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([
    $projet_id,
    $phase,
    $safeName,
    $remotePath,
    (int)$file['size'],
    $file['type'] ?? null,
    $user['id'] ?? null,
]);

setFlash('success', 'Fichier « ' . $safeName . ' » envoyé vers Nextcloud.');
redirect('projet.php?id=' . $projet_id . '&phase=' . $phase);
