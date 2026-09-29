<?php
require_once __DIR__ . '/schema.php';
/**
 * Processus R1b – étapes alignées sur Processus-R1b
 * Étape 1 = Mise en forme du besoin (inclut désormais la note de cadrage)
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

function r1bMaxStep(): int {
    return 8;
}

function r1bMinStep(): int {
    return 1;
}

function r1bClampStep(int $step): int {
    return max(r1bMinStep(), min(r1bMaxStep(), $step));
}

function r1bStepFromPhase(string $phase): int {
    foreach (r1bSteps() as $n => $s) {
        if ($s['phase'] === $phase) return $n;
    }
    return match ($phase) {
        'investissement' => 2,
        'production' => 5,
        default => 0,
    };
}

function r1bPhaseFromStep(int $step): string {
    $steps = r1bSteps();
    return $steps[$step]['phase'] ?? 'cadrage';
}

function ensureProjectProcessColumns(): void {
    runSchemaMigrations();
}
