<?php
/**
 * Génération et cache des PDF du CDC.
 * Le cache est conservé hors de la racine web, à côté de la base de données.
 * Le hash inclut l’intégralité des données du CDC, des jalons et le nom du projet.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/cahier_specs.php';

function cdcGeneratedPdfHash(int $projectId, string $projectName, array $specs, array $milestones): string {
    $payload = json_encode(
        ['project_id' => $projectId, 'project_name' => $projectName, 'specs' => $specs, 'milestones' => $milestones],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if (!is_string($payload)) {
        throw new RuntimeException('Impossible de calculer l’empreinte du CDC.');
    }
    return hash('sha256', $payload);
}

function cdcGeneratedPdfPath(int $projectId, string $projectName, array $specs, array $milestones): string {
    $hash = cdcGeneratedPdfHash($projectId, $projectName, $specs, $milestones);
    return dirname(DB_PATH) . '/generated-cdc/' . $projectId . '-' . $hash . '.pdf';
}

function cdcGeneratedPdfExists(int $projectId, string $projectName, array $specs, array $milestones): bool {
    $path = cdcGeneratedPdfPath($projectId, $projectName, $specs, $milestones);
    return is_file($path) && filesize($path) > 0;
}

/**
 * Retourne le fichier PDF associé exactement au contenu courant.
 * Une seule génération a lieu pour chaque empreinte de contenu.
 */
function cdcEnsureGeneratedPdf(int $projectId, string $projectName, array $specs, array $milestones): array {
    $path = cdcGeneratedPdfPath($projectId, $projectName, $specs, $milestones);
    if (is_file($path) && filesize($path) > 0) {
        return ['path' => $path, 'hash' => cdcGeneratedPdfHash($projectId, $projectName, $specs, $milestones), 'created' => false];
    }

    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Le dossier privé de génération des CDC n’a pas pu être créé.');
    }

    $text = generateCahierText($specs, $projectName, $milestones);
    $pdf = cdcBuildPdf($text, $projectName);
    $temporaryPath = tempnam($directory, '.cdc-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Un fichier temporaire pour le PDF n’a pas pu être créé.');
    }

    try {
        $written = file_put_contents($temporaryPath, $pdf, LOCK_EX);
        if ($written !== strlen($pdf)) {
            throw new RuntimeException('Le PDF n’a pas pu être écrit entièrement.');
        }
        @chmod($temporaryPath, 0600);
        if (!@rename($temporaryPath, $path)) {
            if (is_file($path) && filesize($path) > 0) {
                @unlink($temporaryPath);
                return ['path' => $path, 'hash' => cdcGeneratedPdfHash($projectId, $projectName, $specs, $milestones), 'created' => false];
            }
            throw new RuntimeException('Le PDF généré n’a pas pu être archivé.');
        }
    } finally {
        if (is_file($temporaryPath)) @unlink($temporaryPath);
    }

    return ['path' => $path, 'hash' => cdcGeneratedPdfHash($projectId, $projectName, $specs, $milestones), 'created' => true];
}

function cdcPdfEscapeBytes(string $encoded): string {
    return str_replace(["\\", '(', ')'], ["\\\\", '\\(', '\\)'], $encoded);
}

function cdcPdfLiteral(string $text): string {
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    if (!is_string($encoded)) $encoded = '';
    return cdcPdfEscapeBytes($encoded);
}

function cdcBuildPdf(string $documentText, string $projectName): string {
    $sourceLines = preg_split('/\r\n|\r|\n/', $documentText) ?: [];
    if (count($sourceLines) > 0) array_shift($sourceLines); // le titre est rendu dans l’en-tête
    if (count($sourceLines) > 0 && preg_match('/^=+$/', trim($sourceLines[0]))) array_shift($sourceLines);

    $lines = [];
    foreach ($sourceLines as $sourceLine) {
        $line = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$sourceLine);
        if (!is_string($line)) $line = '';
        $line = str_replace("\t", '    ', $line);
        if ($line === '') {
            $lines[] = '';
            continue;
        }
        foreach (explode("\n", wordwrap($line, 92, "\n", true)) as $wrapped) $lines[] = $wrapped;
    }
    if ($lines === []) $lines = ['—'];
    $perPage = 43;
    $pages = array_chunk($lines, $perPage);
    $pageCount = count($pages);
    $generatedAt = date('d/m/Y H:i');

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>';
    $pageIds = [];

    foreach ($pages as $pageIndex => $pageLines) {
        $pageId = 5 + ($pageIndex * 2);
        $contentId = $pageId + 1;
        $pageIds[] = $pageId . ' 0 R';
        $stream = "0.035 0.12 0.20 rg\nBT /F2 16 Tf 48 794 Td (" . cdcPdfLiteral('CAHIER DES CHARGES') . ") Tj ET\n";
        $stream .= "0.25 0.31 0.38 rg\nBT /F1 10 Tf 48 776 Td (" . cdcPdfLiteral($projectName) . ") Tj ET\n";
        $stream .= "0.08 0.48 0.55 RG 1.2 w 48 764 m 547 764 l S\n";
        $y = 744;
        foreach ($pageLines as $line) {
            $trimmed = trim($line);
            $isHeading = preg_match('/^[1-9][0-9]*\.\s/u', $trimmed) === 1;
            if ($isHeading) {
                $stream .= "0.035 0.30 0.39 rg\nBT /F2 11 Tf 48 {$y} Td (" . cdcPdfLiteral($trimmed) . ") Tj ET\n";
                $y -= 18;
            } elseif ($trimmed === '') {
                $y -= 7;
            } else {
                $stream .= "0.12 0.16 0.21 rg\nBT /F1 10 Tf 52 {$y} Td (" . cdcPdfEscapeBytes($line) . ") Tj ET\n";
                $y -= 14;
            }
        }
        $stream .= "0.55 0.60 0.65 RG 0.5 w 48 48 m 547 48 l S\n";
        $footer = 'OddWorks  |  CDC généré le ' . $generatedAt . '  |  Page ' . ($pageIndex + 1) . ' / ' . $pageCount;
        $stream .= "0.35 0.40 0.45 rg\nBT /F1 8 Tf 48 32 Td (" . cdcPdfLiteral($footer) . ") Tj ET\n";

        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    }

    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . $pageCount . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    $maxObject = max(array_keys($objects));
    for ($objectId = 1; $objectId <= $maxObject; $objectId++) {
        $offsets[$objectId] = strlen($pdf);
        $pdf .= $objectId . " 0 obj\n" . ($objects[$objectId] ?? '<< >>') . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
    for ($objectId = 1; $objectId <= $maxObject; $objectId++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId]);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
    return $pdf;
}
