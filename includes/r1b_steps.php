<?php
/**
 * Processus R1b – étapes alignées sur Processus-R1b
 */
function r1bSteps(): array {
    return [
        1 => ['key' => 'besoin',         'title' => 'Mise en forme du besoin',           'phase' => 'cahier'],
        2 => ['key' => 'etudes',         'title' => 'Études capacités & investissement', 'phase' => 'capacite'],
        3 => ['key' => 'go_nogo',        'title' => 'GO / NO GO',                        'phase' => 'go_nogo'],
        4 => ['key' => 'composants',     'title' => 'Recherche composants & matériels',  'phase' => 'composants'],
        5 => ['key' => 'proto',          'title' => 'Fabrication / Prototypage',         'phase' => 'proto'],
        6 => ['key' => 'tests',          'title' => 'Tests de conformité',               'phase' => 'tests'],
        7 => ['key' => 'livraison',      'title' => 'Livraison DG',                      'phase' => 'livraison'],
        8 => ['key' => 'archivage',      'title' => 'Archivage → R2 Vente',              'phase' => 'archivage'],
    ];
}

function r1bStepFromPhase(string $phase): int {
    foreach (r1bSteps() as $n => $s) {
        if ($s['phase'] === $phase) return $n;
    }
    return match ($phase) {
        'investissement' => 2,
        'production' => 5,
        default => 1,
    };
}

function r1bPhaseFromStep(int $step): string {
    $steps = r1bSteps();
    return $steps[$step]['phase'] ?? 'cahier';
}

function ensureProjectProcessColumns(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $cols = $db->query('PRAGMA table_info(projets)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('current_step', $cols, true)) {
        $db->exec('ALTER TABLE projets ADD COLUMN current_step INTEGER DEFAULT 1');
    }
    if (!in_array('go_decision', $cols, true)) {
        $db->exec('ALTER TABLE projets ADD COLUMN go_decision TEXT');
    }
    if (!in_array('step_notes', $cols, true)) {
        $db->exec('ALTER TABLE projets ADD COLUMN step_notes TEXT');
    }
    if (!in_array('status', $cols, true)) {
        $db->exec("ALTER TABLE projets ADD COLUMN status TEXT DEFAULT 'actif'");
    }
    $done = true;
}
