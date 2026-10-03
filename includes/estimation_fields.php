<?php
/** Shared compact fields for the initial S.T. estimate and supplier research. */
require_once __DIR__ . '/cahier_specs.php';
function renderEstimationVariation(string $prefix, array $data = []): void {
    $variation = normalizeEstimationVariation($data['variation'] ?? '');
    echo '<select name="'.e($prefix).'_variation[]" class="form-control estimation-variation" aria-label="Variation — risque/incertitude">';
    foreach (['Faible', 'Moyen', 'Fort'] as $value) echo '<option value="'.$value.'"'.($variation === $value ? ' selected' : '').'>'.$value.'</option>';
    echo '</select>';
}
function renderEstimationCostCells(string $prefix, array $data = [], bool $hasCost = true, string $extraTotal = ''): void {
    $isEstimate = $prefix === 'st';
    $qty = $data['quantite'] ?? ($isEstimate ? 1 : '');
    $unit = $data['cout_unitaire'] ?? ($isEstimate ? ($data['cout_estime'] ?? 0) : '');
    $tax = ($data[$isEstimate ? 'cout_taxe' : 'cout_unitaire_taxe'] ?? 'HT') === 'TTC' ? 'TTC' : 'HT';
    $total = $hasCost ? estimationTotal(estimationDecimal($qty), estimationDecimal($unit)) : 0;
    $taxName = $prefix . ($isEstimate ? '_cout_taxe' : '_cout_unitaire_taxe');
    ?>
    <td><input type="number" step="any" min="0" name="<?=e($prefix)?>_quantite[]" class="form-control cp-qty" aria-label="Quantité" value="<?=e((string)$qty)?>" <?= $hasCost ? '' : 'hidden readonly' ?>></td>
    <td><div class="cp-cost-cell estimation-cost" <?= $hasCost ? '' : 'hidden' ?>><input type="number" step="any" min="0" inputmode="decimal" name="<?=e($prefix)?>_cout_unitaire[]" class="form-control cp-unit" aria-label="Coût unitaire" value="<?=e((string)$unit)?>" <?= $hasCost ? '' : 'readonly' ?>>
    <select name="<?=e($taxName)?>[]" class="form-control cp-taxe" aria-label="HT/TTC"><option value="HT" <?=$tax==='HT'?'selected':''?>>HT</option><option value="TTC" <?=$tax==='TTC'?'selected':''?>>TTC</option></select></div></td>
    <td><div class="cp-cost-cell estimation-cost" <?= $hasCost ? '' : 'hidden' ?>><input type="text" class="form-control cp-total" aria-label="Coût total" value="<?=e(number_format($total,2,',','').' €')?>" readonly tabindex="-1"><span class="form-control cp-total-taxe"><?=e($tax)?></span>
    <?php if($isEstimate): ?><input type="hidden" name="st_cout_estime[]" class="estimation-total-input" value="<?=e((string)$total)?>"><?php else: ?><input type="hidden" name="cp_cout_total_taxe[]" class="cp-total-taxe-input" value="<?=e($tax)?>"><?php endif; ?></div><?= $extraTotal ?></td>
    <?php
}
