<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/decision_dashboard.php';
require_once __DIR__ . '/includes/cahier_specs.php';
require_once __DIR__ . '/includes/r1b_steps.php';
require_once __DIR__ . '/includes/cdc_pdf.php';

requerirConnexion();
$projectId = (int)($_GET['projet_id'] ?? 0);
$embedded = (($_GET['embedded'] ?? '') === '1');
if ($projectId <= 0) {
    setFlash('error', 'Projet non spécifié.');
    redirect('projets.php');
}

$db = getDB();
$stmt = $db->prepare('SELECT id, nom FROM projets WHERE id = ?');
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    setFlash('error', 'Projet introuvable.');
    redirect('projets.php');
}
requerirAccesProjet($projectId);

$stmt = $db->prepare('SELECT id FROM cahiers WHERE projet_id = ?');
$stmt->execute([$projectId]);
$cahierId = (int)$stmt->fetchColumn();
if ($cahierId <= 0) {
    setFlash('error', 'Aucun cahier des charges n’est enregistré pour ce projet.');
    redirect('projet.php?id=' . $projectId);
}

try {
    $specs = loadSpecs($cahierId);
    $milestones = loadJalons($cahierId);
    $generated = cdcEnsureGeneratedPdf($projectId, (string)$project['nom'], $specs, $milestones);
} catch (Throwable $e) {
    error_log('OddWorks CDC PDF generation failed for project ' . $projectId . ': ' . $e->getMessage());
    setFlash('error', 'Le PDF du cahier des charges n’a pas pu être généré.');
    redirect('cahier_form.php?projet_id=' . $projectId . ($embedded ? '&embedded=1' : ''));
}

$safeName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$project['nom']);
$safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$safeName) ?: 'Projet';
$filename = 'CDC_' . trim($safeName, '_') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($generated['path']));
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode('CDC_' . $project['nom'] . '.pdf'));
header('Cache-Control: private, no-store, max-age=0');
readfile($generated['path']);
exit;
