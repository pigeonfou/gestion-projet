<?php
            $techniquesAll = $specs['specs_techniques'] ?? [];
            $composantsSt = $specs['composants_st'] ?? [];
            if (!is_array($composantsSt)) $composantsSt = [];
            $users = $utilisateursListe ?? [];
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Composants & affectations</h4>
          <p class="text-sm text-muted mb-3">Matériel, Composant, Prestataire et PCB : coûts et délais. Logiciel et 3D : délais de réalisation uniquement.</p>
          <?php if (empty($techniquesAll)): ?>
            <div class="r1b-info-box">
              <p>Aucune spécification technique définie.</p>
              <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>" class="btn btn-primary btn-sm mt-2">Aller à l'étape 2</a>
            </div>
          <?php else: ?>
            <form method="POST" id="formComposantsSt" action="<?= url('projet.php?id=' . $id . '&view=processus&step=4') ?>">
              <input type="hidden" name="action" value="save_composants_st"><input type="hidden" name="specs_revision" value="<?=e(specsRevision(getOrCreateCahierId($id)))?>">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="projet_id" value="<?= (int)$id ?>">
              <?php foreach ($techniquesAll as $stRow): ?>
                <?php
                  $stId = $stRow['id'] ?? '';
                  $stType = $stRow['type'] ?? 'Matériel';
                  if ($stType === 'S.T.x.x') continue;
                  $stDesc = $stRow['description'] ?? '';
                  if ($stId === '') continue;
                  $items = $composantsSt[$stId] ?? [];
                  $stCostHT = 0.0; $stCostTTC = 0.0;
                  foreach ($items as $costItem) {
                      $costValue = (float)($costItem['cout_total'] ?? 0);
                      if (($costItem['cout_unitaire_taxe'] ?? $costItem['cout_total_taxe'] ?? 'HT') === 'TTC') $stCostTTC += $costValue;
                      else $stCostHT += $costValue;
                  }
                  if (empty($items)) {
                      $items = [[]]; // ligne vide
                  }
                  $prefix = match ($stType) {
                      'Matériel' => 'M',
                      'Composant' => 'C',
                      'Prestataire' => 'P',
                      'Logiciel' => 'L',
                      '3D' => '3D',
                      'PCB' => 'PCB',
                      default => 'X',
                  };
                ?>
                <div class="st-sf-block cp-st-block" id="<?=e($stId)?>" data-st-id="<?= e($stId) ?>" data-st-type="<?= e($stType) ?>" data-prefix="<?= e($prefix) ?>">
                  <div class="st-sf-head">
                    <a class="st-id" href="<?=pfStUrl($id,$stRow)?>"><?= e($stId) ?> ↗</a>
                    <span class="st-sf-badge"><?= e($stType) ?></span>
                    <span class="st-sf-desc"><?= e($stDesc ?: '(sans description)') ?></span>
                    <?php if (stTypeHasCost($stType)): ?>
                      <span class="st-sf-badge cp-st-cost">Coût <?= e($stId) ?> :
                        <span class="cp-st-cost-value"><?= number_format($stCostHT, 2, ',', ' ') ?> € HT<?= $stCostTTC > 0 ? ' + ' . number_format($stCostTTC, 2, ',', ' ') . ' € TTC' : '' ?></span>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="sf-table-wrap">
                    <?php if (stTypeHasCost($stType)): ?>
                      <table class="sf-table cp-table">
                        <thead>
                          <tr>
                            <th style="width:3.5rem">ID</th>
                            <th>Désignation</th>
                            <th>Référence</th>
                            <th>Fournisseur</th>
                            <th class="estimation-qty-head">Qté</th>
                            <th class="estimation-unit-head">Coût unitaire</th>
                            <th class="estimation-total-head">Coût total</th>
                            <th class="estimation-variation-head">Variation</th><th class="estimation-delay-head">Délai estimé (jours)</th>
                            <th style="width:2.2rem"></th>
                          </tr>
                        </thead>
                        <tbody class="cp-body">
                          <?php foreach ($items as $ii => $it): ?>
                          <tr class="cp-row">
                            <td>
                              <span class="cp-id"><?= e($it['id'] ?? ($prefix . '.' . ($ii + 1))) ?></span>
                              <input type="hidden" name="cp_st_id[]" value="<?= e($stId) ?>">
                              <input type="hidden" name="cp_uid[]" value="<?=e(pfIdentity($it['uid'] ?? ($specs['component_identities'][$stRow['uid'] ?? ''][$it['id'] ?? ''] ?? null), ($stRow['uid'] ?? $stId).':'.($it['id'] ?? '')))?>"><input type="hidden" name="cp_type[]" value="<?= e($stType) ?>">
                            </td>
                            <td><input type="text" name="cp_designation[]" class="form-control" value="<?= e($it['designation'] ?? '') ?>"></td>
                            <td><input type="text" name="cp_reference[]" class="form-control" value="<?= e($it['reference'] ?? '') ?>"></td>
                            <td><input type="text" name="cp_fournisseur[]" class="form-control" value="<?= e($it['fournisseur'] ?? '') ?>"></td>
                            <?php ob_start(); ?>
                              <?php if ($stType === 'PCB'): ?>
                              <label>Affectation PCB</label><select name="cp_affectation[]" class="form-control"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= e($u['identifiant']) ?>" <?= (($it['affectation'] ?? '') === $u['identifiant']) ? 'selected' : '' ?>><?= e($u['identifiant']) ?></option><?php endforeach; ?></select>
                              <?php else: ?><input type="hidden" name="cp_affectation[]" value="<?= e($it['affectation'] ?? '') ?>"><?php endif; ?>
                              <input type="hidden" name="cp_duree[]" value="<?= e($it['duree'] ?? '') ?>">
                            <?php $extraTotal = ob_get_clean(); renderEstimationCostCells('cp', $it, true, $extraTotal); ?>
                            <td><?php renderEstimationVariation('cp', $it); ?></td>
                            <td><input type="number" min="0" step="0.01" name="cp_delai_jours[]" class="form-control" value="<?= e((string)($it['delai_jours'] ?? '')) ?>" aria-label="Délai en jours"><?php if (!isset($it['delai_jours']) && !empty($it['duree'])): ?><span class="text-xs text-muted">Durée précédente : <?= e($it['duree']) ?></span><?php endif; ?></td>
                            <td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php else: ?>
                      <table class="sf-table cp-table">
                        <thead>
                          <tr>
                            <th style="width:4rem">ID</th>
                            <th>Affectation</th>

                            <th class="estimation-variation-head">Variation</th>
                            <th class="estimation-delay-head">Délai estimé (jours)</th>
                            <th style="width:2.2rem"></th>
                          </tr>
                        </thead>
                        <tbody class="cp-body">
                          <?php foreach ($items as $ii => $it): ?>
                          <tr class="cp-row">
                            <td>
                              <span class="cp-id"><?= e($it['id'] ?? ($prefix . '.' . ($ii + 1))) ?></span>
                              <input type="hidden" name="cp_st_id[]" value="<?= e($stId) ?>">
                              <input type="hidden" name="cp_uid[]" value="<?=e(pfIdentity($it['uid'] ?? ($specs['component_identities'][$stRow['uid'] ?? ''][$it['id'] ?? ''] ?? null), ($stRow['uid'] ?? $stId).':'.($it['id'] ?? '')))?>"><input type="hidden" name="cp_type[]" value="<?= e($stType) ?>">
                              <input type="hidden" name="cp_designation[]" value="">
                              <input type="hidden" name="cp_reference[]" value="">
                              <input type="hidden" name="cp_fournisseur[]" value="">
                              <input type="hidden" name="cp_quantite[]" value="">
                              <input type="hidden" name="cp_cout_unitaire[]" value="">
                              <input type="hidden" name="cp_cout_unitaire_taxe[]" value="HT">
                              <input type="hidden" name="cp_cout_total_taxe[]" value="HT">
                            </td>
                            <td>
                              <select name="cp_affectation[]" class="form-control">
                                <option value="">—</option>
                                <?php foreach ($users as $u): ?>
                                  <option value="<?= e($u['identifiant']) ?>" <?= (($it['affectation'] ?? '') === $u['identifiant']) ? 'selected' : '' ?>><?= e($u['identifiant']) ?></option>
                                <?php endforeach; ?>
                              </select>
                            </td>
                            <td>
                              <input type="hidden" name="cp_duree[]" value="<?= e($it['duree'] ?? '') ?>">
                              <?php renderEstimationVariation('cp', $it); ?>
                            </td>
                            <td><input type="number" min="0" step="0.01" name="cp_delai_jours[]" class="form-control" value="<?= e((string)($it['delai_jours'] ?? '')) ?>" aria-label="Délai en jours"><?php if (!isset($it['delai_jours']) && !empty($it['duree'])): ?><span class="text-xs text-muted">Durée précédente : <?= e($it['duree']) ?></span><?php endif; ?></td>
                            <td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm mt-1 btn-cp-add"><i class="fas fa-plus"></i> Ajouter une ligne</button>
                </div>
              <?php endforeach; ?>
              <?php ob_start(); ?>
              <div class="mt-3">
                <button type="submit" form="formComposantsSt" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les composants</button>
                <a href="<?= url('projet.php?id=' . $id . '&view=processus&step=2') ?>" class="btn btn-secondary btn-sm">Éditer les S.T. (étape 2)</a>
              </div>
              <?php $stepEditorActions = ob_get_clean(); ?>
            </form>
          <?php endif; ?>
