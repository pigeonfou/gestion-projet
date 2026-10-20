<?php
            $purchaseRows = [];
            $composantsAchat = $specs['composants_st'] ?? [];
            $techAchat = $specs['specs_techniques'] ?? [];
            $techAchatById = [];
            foreach ($techAchat as $ta) if (!empty($ta['id'])) $techAchatById[$ta['id']] = $ta;
            foreach ($composantsAchat as $stId => $items) {
                if (!stTypeHasCost($techAchatById[$stId]['type'] ?? '') || !is_array($items)) continue;
                foreach ($items as $it) {
                    if (!is_array($it)) continue;
                    $itemId = trim((string)($it['id'] ?? ''));
                    if ($itemId === '') continue;
                    $purchaseRows[] = ['st_id' => $stId, 'st_description' => $techAchatById[$stId]['description'] ?? '', 'item' => $it];
                }
            }
            $purchaseTasks = [];
            foreach ($tachesAll as $pt) {
                $sk = (string)($pt['source_key'] ?? '');
                if (str_starts_with($sk, 'achat:' . $id . ':')) $purchaseTasks[$sk] = $pt;
            }
          ?>
          <h4 class="mb-2" style="font-size:1rem;font-weight:600;">Liste des achats issus de l'étape 4</h4>
          <p class="text-sm text-muted mb-3">Sélectionnez les composants ou matériels à commander, affectez un utilisateur puis générez les tâches d'achat. Une tâche existante est mise à jour plutôt que dupliquée.</p>
          <?php if (empty($purchaseRows)): ?>
            <div class="r1b-info-box">Aucune ligne Matériel, Composant, Prestataire ou PCB renseignée à l'étape 4. Enregistrez d'abord les composants et matériels à acheter.</div>
          <?php else: ?>
            <form method="POST" action="<?= url('projet.php?id=' . $id . '&view=processus&step=5') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="create_purchase_tasks"><input type="hidden" name="specs_revision" value="<?=e(specsRevision(getOrCreateCahierId($id)))?>">
              <div class="sf-table-wrap">
                <table class="sf-table r1b-purchase-table">
                  <thead><tr>
                    <th style="width:2.5rem">Créer</th><th>S.T.</th><th>ID</th><th>Désignation</th><th>Référence</th><th>Fournisseur</th><th>Qté</th><th>Coût estimé</th><th class="estimation-delay-head">Délai estimé (jours)</th><th>Affecter à</th><th>État tâche</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($purchaseRows as $pr): ?>
                    <?php
                      $it = $pr['item']; $stId = $pr['st_id']; $itemId = (string)$it['id'];
                      $rowKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $stId . '__' . $itemId);
                      $sourceKey = 'achat:' . $id . ':' . $stId . ':' . $itemId;
                      $existingTask = $purchaseTasks[$sourceKey] ?? null;
                      $tax = (($it['cout_unitaire_taxe'] ?? 'HT') === 'TTC') ? 'TTC' : 'HT';
                      $total = (float)($it['quantite'] ?? 0) * (float)($it['cout_unitaire'] ?? 0);
                      $payload = base64_encode(json_encode([
                        'st_id'=>$stId,'item_id'=>$itemId,'designation'=>$it['designation'] ?? '',
                        'reference'=>$it['reference'] ?? '','fournisseur'=>$it['fournisseur'] ?? '',
                        'quantite'=>$it['quantite'] ?? 0,'cout_unitaire'=>$it['cout_unitaire'] ?? 0,
                        'cout_unitaire_taxe'=>$tax,'delai_jours'=>$it['delai_jours'] ?? null
                      ], JSON_UNESCAPED_UNICODE));
                    ?>
                    <tr>
                      <td><input type="checkbox" name="purchase_create[]" value="<?= e($rowKey) ?>" <?= $existingTask ? 'checked' : '' ?>></td>
                      <td><a class="pf-object-id" href="<?=url('specification.php?projet_id='.$id.'&st='.rawurlencode($stId))?>"><?= e($stId) ?></a><div class="text-xs text-muted"><?= e($pr['st_description']) ?></div></td>
                      <td><?= e($itemId) ?></td>
                      <td><?= e($it['designation'] ?? '—') ?></td>
                      <td><?= e($it['reference'] ?? '—') ?></td>
                      <td><?= e($it['fournisseur'] ?? '—') ?></td>
                      <td class="estimate-quantity"><?= e((string)($it['quantite'] ?? '0')) ?></td>
                      <td class="estimate-money"><?= number_format($total, 2, ',', ' ') ?> € <?= e($tax) ?></td>
                      <td class="estimate-delay"><?= e(isset($it['delai_jours']) ? (string)$it['delai_jours'] : '—') ?></td>
                      <td>
                        <select name="purchase_assignee[<?= e($rowKey) ?>]" class="form-control">
                          <option value="">— Non affectée —</option>
                          <?php foreach ($utilisateursListe as $u): ?>
                            <option value="<?= e($u['identifiant']) ?>" <?= (($existingTask['assigne_a'] ?? '') === $u['identifiant']) ? 'selected' : '' ?>><?= e($u['identifiant']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="purchase_payload[<?= e($rowKey) ?>]" value="<?= e($payload) ?>">
                      </td>
                      <td><?php if($existingTask):?><a href="<?=url('tache.php?id='.(int)$existingTask['id'])?>"><?=e(statutLabel($existingTask['statut']??'a_faire'))?> ↗</a><?php else:?><span class="text-muted">Non créée</span><?php endif;?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-shopping-cart"></i> Générer / mettre à jour les tâches d'achat</button>
            </form>
          <?php endif; ?>
