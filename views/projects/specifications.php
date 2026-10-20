<?php
            $fonctionsSF = $specs['fonctions'] ?? [];
            $techniquesAll = $specs['specs_techniques'] ?? [];
            $techBySf = [];
            foreach ($techniquesAll as $trow) {
                $sfKey = $trow['sf'] ?? '';
                if ($sfKey === '') continue;
                $techBySf[$sfKey][] = $trow;
            }
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Spécifications techniques</h4>
          <p class="text-sm text-muted mb-3">Pour chaque spécification fonctionnelle (S.F.) du CDC, ajoutez les spécifications techniques associées (S.T.n.m).</p>
          <?php if (empty($fonctionsSF)): ?>
            <div class="r1b-info-box">
              <p>Aucune spécification fonctionnelle dans le cahier des charges.</p>
              <a href="<?= url('cahier_form.php?projet_id=' . $id . $cdcReturnQuery) ?>" class="btn btn-primary btn-sm mt-2"><i class="fas fa-edit"></i> Ouvrir le CDC</a>
            </div>
          <?php else: ?>
            <script type="application/json" id="st-reference-data"><?= json_encode($techniquesAll, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            <dialog id="st-reference-dialog" aria-labelledby="st-reference-title">
              <h3 id="st-reference-title">Choisir un S.T. existant</h3>
              <input type="search" id="st-reference-search" class="form-control" placeholder="Rechercher une référence ou une désignation" aria-label="Rechercher un S.T.">
              <div id="st-reference-list"></div>
              <button type="button" id="st-reference-cancel" class="btn btn-secondary">Annuler</button>
              <label>Revenir à un type classique <select id="st-reference-classic" class="form-control"><option value="">— Choisir —</option><?php foreach (['Matériel','Composant','Prestataire','Logiciel','3D','PCB'] as $classic): ?><option><?= e($classic) ?></option><?php endforeach; ?></select></label>
            </dialog>
            <form method="POST" id="formSpecsTech" action="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>">
              <input type="hidden" name="action" value="save_specs_techniques"><input type="hidden" name="specs_revision" value="<?=e(specsRevision(getOrCreateCahierId($id)))?>">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
              <div id="st-hierarchy-status" role="status" aria-live="polite"></div>
              <?php foreach ($fonctionsSF as $sf): ?>
                <?php
                  $sfId = $sf['id'] ?? '';
                  if (!preg_match('/^S\.F\.(\d+)$/', $sfId, $mSf)) continue;
                  $sfNum = (int)$mSf[1];
                  $rows = $techBySf[$sfId] ?? [];
                  $sfCostHT = 0.0; $sfCostTTC = 0.0; $sfUnknownCosts = 0;
                  foreach ($rows as $costRow) {
                      if (stTypeHasCost($costRow['type'] ?? 'Matériel') && !empty($costRow['cout_unitaire_inconnu'])) $sfUnknownCosts++;
                      $v = stTypeHasCost($costRow['type'] ?? 'Matériel') ? (float)($costRow['cout_estime'] ?? 0) : 0;
                      if (($costRow['cout_taxe'] ?? 'HT') === 'TTC') $sfCostTTC += $v; else $sfCostHT += $v;
                  }
                  if (empty($rows)) {
                      $rows = [['id' => 'S.T.' . $sfNum . '.1', 'description' => '', 'type' => 'Matériel']];
                  }
                ?>
                <div class="st-sf-block" data-sf="<?= e($sfId) ?>" data-sf-num="<?= $sfNum ?>">
                  <div class="st-sf-head">
                    <span class="st-sf-id"><?= e($sfId) ?></span>
                    <span class="st-sf-desc"><?= e($sf['description'] ?: '(sans description)') ?></span>
                    <?php if (!empty($sf['indicateur'])): ?>
                      <span class="st-sf-badge"><?= e($sf['indicateur']) ?></span>
                    <?php endif; ?>
                    <span class="st-sf-badge sf-cost-badge">Estimation coût <?= e($sfId) ?> :
                      <span class="sf-cost-value"><?= number_format($sfCostHT, 2, ',', ' ') ?> € HT<?php if ($sfCostTTC > 0): ?> + <?= number_format($sfCostTTC, 2, ',', ' ') ?> € TTC<?php endif; ?><?= $sfUnknownCosts ? ' · sous-total connu · '.$sfUnknownCosts.' coût(s) inconnu(s)' : '' ?></span>
                    </span>
                  </div>
                  <div class="sf-table-wrap">
                    <table class="sf-table st-table">
                      <thead>
                        <tr>
                          <th style="width:5.5rem">ID</th>
                          <th>Description</th>
                          <th style="width:8.5rem">Type</th>
                          <th class="estimation-qty-head">Qté</th><th class="estimation-unit-head">Coût unitaire</th><th class="estimation-total-head">Coût total</th><th class="estimation-variation-head">Variation</th>
                          <th class="estimation-delay-head">Délai estimé (jours)</th>
                          <th style="width:2.5rem"></th>
                        </tr>
                      </thead>
                      <tbody class="st-body">
                        <?php foreach ($rows as $ri => $tr): ?>
                        <tr class="st-row" id="<?=e($tr['id']??'')?>">
                          <td><a class="st-id" href="<?=pfStUrl($id,$tr)?>"><?= e($tr['id'] ?? ('S.T.' . $sfNum . '.' . ($ri + 1))) ?></a>
                            <input type="hidden" name="st_sf[]" value="<?= e($sfId) ?>">
                            <input type="hidden" name="st_uid[]" value="<?= e($tr['uid'] ?? bin2hex(random_bytes(16))) ?>">
                            <input type="hidden" name="st_reference_uid[]" value="<?= e($tr['reference_uid'] ?? '') ?>">
                          </td>
                          <td><input type="text" name="st_description[]" class="form-control" value="<?= e($tr['description'] ?? '') ?>" placeholder="Description technique…">
                            <input type="hidden" name="st_parent_uid[]" value="<?= e($tr['parent_uid'] ?? '') ?>">
                          </td>
                          <td>
                            <?php $ty = $tr['type'] ?? 'Matériel'; ?>
                            <select name="st_type[]" class="form-control">
                              <option value="Matériel" <?= $ty === 'Matériel' ? 'selected' : '' ?>>Matériel</option>
                              <option value="Composant" <?= $ty === 'Composant' ? 'selected' : '' ?>>Composant</option>
                              <option value="Prestataire" <?= $ty === 'Prestataire' ? 'selected' : '' ?>>Prestataire</option>
                              <option value="Logiciel" <?= $ty === 'Logiciel' ? 'selected' : '' ?>>Logiciel</option>
                              <option value="3D" <?= $ty === '3D' ? 'selected' : '' ?>>3D</option>
                              <option value="PCB" <?= $ty === 'PCB' ? 'selected' : '' ?>>PCB</option>
                              <option value="S.T.x.x" <?= $ty === 'S.T.x.x' ? 'selected' : '' ?>>S.T.x.x</option>
                            </select>
                          </td>
                          <?php renderEstimationCostCells('st', $tr, stTypeHasCost($ty)); ?>
                          <td><?php renderEstimationVariation('st', $tr); ?></td>
                          <td><input type="text" inputmode="decimal" name="st_delai_jours[]" class="form-control st-delay" value="<?= e(!empty($tr['delai_inconnu']) ? 'inconnue' : (string)($tr['delai_jours'] ?? '')) ?>" aria-label="Délai estimé en jours"></td>
                          <td><button type="button" class="btn-sf-del btn-st-del" title="Supprimer">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm mt-1 btn-st-add"><i class="fas fa-plus"></i> Ajouter une S.T.</button>
                </div>
              <?php endforeach; ?>
              <details class="st-structure" open><summary>Structure des S.T.</summary><div id="st-structure-tree"></div></details>
              <?php ob_start(); ?>
              <div class="mt-3">
                <button type="submit" form="formSpecsTech" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les spécifications techniques</button>
                <a href="<?= url('cahier_form.php?projet_id=' . $id . '&step=2&return_view=processus&return_step=2') ?>" class="btn btn-secondary btn-sm">Éditer les S.F. (CDC)</a>
              </div>
              <?php $stepEditorActions = ob_get_clean(); ?>
            </form>
          <?php endif; ?>
