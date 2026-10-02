<?php
/** Décisions autorisées à l'intérieur des neuf étapes existantes. */
function r1bAllowedDecisions(int $step): array {
    if ($step === 3) return ['GO', 'NO_GO'];
    if ($step === 7 || $step === 8) return ['CONFORME', 'NON_CONFORME'];
    return in_array($step, [1, 2, 4, 5, 6], true) ? ['DONE', 'REFUSE'] : [];
}

function r1bRefusalState(int $step, array $validated): array {
    $returnStep = max(1, $step - 1);
    $kept = array_values(array_filter(array_map('intval', $validated), static fn($n) => $n >= 1 && $n < $returnStep));
    $kept = array_values(array_unique($kept));
    sort($kept);
    return ['step' => $returnStep, 'validated' => $kept];
}
