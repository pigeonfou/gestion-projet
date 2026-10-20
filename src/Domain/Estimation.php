<?php
function stTypeHasCost(string $type): bool {
    return in_array($type, ['Matériel', 'Composant', 'Prestataire', 'PCB'], true);
}

/** Canonical qualitative values, with compatibility for older labels. */
function normalizeEstimationVariation($value): string {
    return match (is_scalar($value) ? (string)$value : '') {
        'Faible' => 'Faible', 'Fort', 'Forte' => 'Fort', 'Moyen', 'Moyenne' => 'Moyen', default => '',
    };
}
function estimationDecimal($value, float $default = 0): float {
    if (!is_scalar($value)) return $default;
    $value = str_replace(',', '.', trim((string)$value));
    return $value !== '' && is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 ? (float)$value : $default;
}
/** A negative estimate is explicitly unknown, never a zero-cost/zero-day value. */
function estimateIsUnknown($value): bool {
    if (!is_scalar($value)) return false;
    $text = str_replace(',', '.', trim((string)$value));
    return in_array(strtolower($text), ['inconnue', 'inconnu'], true)
        || (is_numeric($text) && is_finite((float)$text) && (float)$text < 0);
}
function estimationTotal(float $quantity, float $unit): float {
    return is_finite($quantity * $unit) ? round($quantity * $unit, 2) : 0;
}

function parseDelaiJours($value): ?float {
    if (!is_scalar($value)) return null;
    $value = str_replace(',', '.', trim((string)$value));
    return $value !== '' && is_numeric($value) && is_finite((float)$value) && (float)$value >= 0
        ? round((float)$value, 2) : null;
}

