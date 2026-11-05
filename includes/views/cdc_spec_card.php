<?php
$level = $f['indicateur'] ?? 'Obligatoire';
$levels = ['Obligatoire' => 'Impératif', 'Facultative' => 'Facultative', 'Optionnel' => 'Optionnel'];
?>
<details class="cdc-sf-card sf-row">
    <summary>
        <i class="fas fa-list-check cdc-sf-icon" aria-hidden="true"></i>
        <span class="cdc-sf-summary">
            <span class="cdc-sf-meta"><span class="sf-id"><?=e($f['id'] ?? ('S.F.'.($fi + 1)))?></span><span data-sf-domain><?=e($f['domaine'] ?? '')?></span></span>
            <strong data-sf-title><?=e($f['description'] ?? 'Nouvelle exigence')?></strong>
        </span>
        <span class="cdc-sf-level" data-sf-level><?=e($levels[$level] ?? $levels['Obligatoire'])?></span>
    </summary>
    <div class="cdc-sf-fields">
        <label>Domaine<select name="sf_domaine[]" class="form-control">
            <option value="">Choisir un domaine</option>
            <?php foreach ($cdcDomains as $domain): ?>
                <option value="<?=e($domain)?>" <?=strcasecmp($f['domaine'] ?? '', $domain) === 0 ? 'selected' : ''?>><?=e($domain)?></option>
            <?php endforeach; ?>
            <option data-add-domain>Ajouter un domaine…</option>
        </select></label>
        <label>Niveau<select name="sf_indicateur[]" class="form-control">
            <?php foreach ($levels as $value => $label): ?>
                <option value="<?=e($value)?>" <?=$level === $value ? 'selected' : ''?>><?=e($label)?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="cdc-sf-wide">Exigence<textarea name="sf_description[]" class="form-control" rows="2"><?=e($f['description'] ?? '')?></textarea></label>
        <label>Détail / critères<textarea name="sf_criteres[]" class="form-control" rows="3"><?=e($f['criteres'] ?? '')?></textarea></label>
        <label>À préciser / notes<textarea name="sf_notes[]" class="form-control" rows="3"><?=e($f['notes'] ?? '')?></textarea></label>
        <button type="button" class="btn btn-secondary btn-sm btn-sf-del cdc-sf-remove">Supprimer cette exigence</button>
    </div>
</details>
