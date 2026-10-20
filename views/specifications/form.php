<div class="page-header">
    <div>
        <h1><i class="fas fa-file-alt"></i> Cahier des charges structuré</h1>
        <p class="text-muted text-sm mt-1">Projet : <strong><?= e($projet['nom']) ?></strong></p>
    </div>
    <a href="<?= url($returnUrl) ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour au projet</a>
</div>

<?php if ($generated): ?>
<div class="card mb-2">
    <div class="card-header">
        <h2><i class="fas fa-file-export"></i> Document généré</h2>
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cdcOut').value);this.textContent='Copié !'">Copier</button>
    </div>
    <div class="card-body">
        <textarea id="cdcOut" class="form-control" rows="18" readonly style="font-family:monospace;font-size:.8rem;"><?= e($generated) ?></textarea>
        <a class="btn btn-primary mt-2" href="data:text/plain;charset=utf-8,<?= rawurlencode($generated) ?>" download="CDC_<?= e(preg_replace('/\s+/', '_', $projet['nom'])) ?>.txt">
            <i class="fas fa-download"></i> Télécharger .txt
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Stepper -->
<div class="cdc-stepper" id="cdcStepper">
    <button type="button" class="step active" data-step="1"><span>1</span> Contexte</button>
    <button type="button" class="step" data-step="2"><span>2</span> Spécifications</button>
    <button type="button" class="step" data-step="3"><span>3</span> Planning</button>
</div>
<div class="cdc-progress"><div class="cdc-progress-bar" id="cdcProgress" style="width:33%"></div></div>

<form method="POST" id="cdcForm" action="<?= url('cahier_form.php?projet_id=' . $projet_id . '&return_view=' . rawurlencode($returnView) . '&return_step=' . $returnStep) ?>">
    <input type="hidden" name="projet_id" value="<?= $projet_id ?>">
    <input type="hidden" name="return_view" value="<?= e($returnView) ?>">
    <input type="hidden" name="return_step" value="<?= $returnStep ?>">
    <input type="hidden" name="form_action" id="formAction" value="save">
    <?= csrfField() ?>
    <input type="hidden" name="specs_revision" value="<?=e(specsRevision($cahier_id))?>">

    <!-- ===== 1 ===== -->
    <div class="cdc-step-panel active" data-panel="1">
        <div class="card">
            <div class="card-header"><h2>1. Contexte, objectifs et besoins utilisateurs</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Objectifs et contexte *</label>
                    <textarea name="objectifs" class="form-control" rows="3" required><?= e($s['objectifs']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Hors périmètre du projet</label>
                    <textarea name="resultats_attendus" class="form-control" rows="3"><?= e($s['resultats_attendus']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Contraintes</label>
                    <textarea name="cas_usage" class="form-control" rows="3"><?= e($s['cas_usage']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Profils des utilisateurs finaux</label>
                    <textarea name="profils_utilisateurs" class="form-control" rows="2"><?= e($s['profils_utilisateurs']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 2 ===== -->
    <div class="cdc-step-panel" data-panel="2">
        <div class="card">
            <div class="card-header"><h2>2. Spécifications fonctionnelles</h2></div>
            <div class="card-body">
                <p class="text-muted text-sm mb-2">Ajoutez les fonctions attendues. L’ID est généré automatiquement (S.F.1, S.F.2…).</p>
                <div class="sf-table-wrap">
                    <table class="sf-table" id="sfTable">
                        <thead>
                            <tr>
                                <th style="width:5.5rem">ID</th>
                                <th>Description</th>
                                <th style="width:9rem">Indicateur</th>
                                <th style="width:2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody id="sfBody">
                            <?php
                            $fonctions = $s['fonctions'] ?? [];
                            if (empty($fonctions)) {
                                $fonctions = [['id' => 'S.F.1', 'description' => '', 'indicateur' => 'Obligatoire']];
                            }
                            foreach ($fonctions as $fi => $f):
                                $ind = $f['indicateur'] ?? 'Obligatoire';
                            ?>
                            <tr class="sf-row">
                                <td><span class="sf-id"><?= e($f['id'] ?? ('S.F.' . ($fi + 1))) ?></span></td>
                                <td><input type="hidden" name="sf_uid[]" value="<?=e($f['uid'] ?? '')?>"><input type="text" name="sf_description[]" class="form-control" value="<?= e($f['description'] ?? '') ?>" placeholder="Description de la fonction…"></td>
                                <td>
                                    <select name="sf_indicateur[]" class="form-control">
                                        <option value="Obligatoire" <?= ($ind === 'Obligatoire') ? 'selected' : '' ?>>Obligatoire</option>
                                        <option value="Facultative" <?= ($ind === 'Facultative') ? 'selected' : '' ?>>Facultative</option>
                                        <option value="Optionnel" <?= ($ind === 'Optionnel') ? 'selected' : '' ?>>Optionnel</option>
                                    </select>
                                </td>
                                <td><button type="button" class="btn-sf-del" title="Supprimer" aria-label="Supprimer">&times;</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" id="sfAddRow"><i class="fas fa-plus"></i> Ajouter une fonction</button>
            </div>
        </div>
    </div>


    <!-- ===== 3 ===== -->
    <div class="cdc-step-panel" data-panel="3">
        <div class="card">
            <div class="card-header"><h2>3. Contraintes planning et livrables attendus</h2></div>
            <div class="card-body">
                <div class="form-group"><label>Délais de livraison et de mise en service</label><textarea name="delais" class="form-control" rows="3"><?= e($s['delais']) ?></textarea></div>
                <div class="form-group"><label>Livrables attendus (prototypes, certificats, rapports de tests…)</label><textarea name="livrables_attendus" class="form-control" rows="3"><?= e($s['livrables_attendus']) ?></textarea></div>

                <div class="section-label mt-3">Jalons du projet</div>
                <p class="text-muted text-sm mb-2">Définissez les jalons clés (nom + date prévue). Ils apparaissent aussi sur le tableau de bord du projet.</p>
                <div class="sf-table-wrap">
                    <table class="sf-table" id="jalonTable">
                        <thead>
                            <tr>
                                <th>Nom du jalon</th>
                                <th style="width:11rem">Date prévue</th>
                                <th style="width:2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody id="jalonBody">
                            <?php
                            $jl = $jalonsList ?? [];
                            if (empty($jl)) {
                                $jl = [['nom' => '', 'date_prevue' => '']];
                            }
                            foreach ($jl as $j):
                            ?>
                            <tr class="jalon-row">
                                <td><input type="text" name="jalon_nom[]" class="form-control" value="<?= e($j['nom'] ?? '') ?>" placeholder="Ex. Revue GO/NO GO, Livraison proto…"></td>
                                <td><input type="date" name="jalon_date[]" class="form-control" value="<?= e($j['date_prevue'] ?? '') ?>"></td>
                                <td><button type="button" class="btn-sf-del btn-jalon-del" title="Supprimer" aria-label="Supprimer">&times;</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" id="jalonAddRow"><i class="fas fa-plus"></i> Ajouter un jalon</button>

                <fieldset style="border:1px solid #dbe3ef;padding:16px;margin:16px 0;border-radius:8px">
                  <legend style="font-weight:600">Cibles de décision (optionnelles)</legend>
                  <p class="text-muted text-sm">Ces valeurs servent aux comparaisons de l’Étape 3. Précisez le périmètre ; laissez vide ce qui reste inconnu. Les contraintes textuelles restent la référence.</p>
                  <?php $cibles=$s['decision_cibles']??[]; ?>
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
                  <?php foreach(['budget'=>'Budget cible (€)','quantite'=>'Quantité d’équipements','tva'=>'TVA de rapprochement (%)','delai'=>'Délai cible (jours calendaires)','charge'=>'Plafond de charge (jours-personnes)'] as $key=>$label): ?>
                  <div class="form-group"><label for="cible-<?=$key?>"><?=e($label)?></label><input id="cible-<?=$key?>" name="cible_<?=$key?>" type="number" min="<?=$key==='quantite'?'0.01':'0'?>" <?= $key==='tva'?'max="100"':'' ?> step="any" class="form-control" value="<?=e((string)($cibles[$key]??''))?>"></div>
                  <?php endforeach; ?>
                  <div class="form-group"><label for="cible-taxe">Base fiscale du budget</label><select id="cible-taxe" name="cible_taxe" class="form-control"><option value="HT" <?=($cibles['taxe']??'HT')==='HT'?'selected':''?>>HT</option><option value="TTC" <?=($cibles['taxe']??'HT')==='TTC'?'selected':''?>>TTC</option></select></div>
                  </div>
                  <div class="form-group"><label for="cible-perimetre">Périmètre du budget et source des cibles</label><input id="cible-perimetre" name="cible_perimetre" maxlength="1000" class="form-control" value="<?=e($cibles['perimetre']??'')?>" placeholder="Ex. deux prototypes, achats et assemblage ; besoin validé du…"></div>
                </fieldset>
            </div>
        </div>
    </div>

    <div class="cdc-form-actions">
        <button type="button" class="btn btn-secondary" id="btnPrev" disabled><i class="fas fa-arrow-left"></i> Précédent</button>
        <button type="button" class="btn btn-secondary" id="btnNext">Suivant <i class="fas fa-arrow-right"></i></button>
        <span style="flex:1"></span>
        <button type="submit" class="btn btn-secondary" onclick="document.getElementById('formAction').value='save'">
            <i class="fas fa-save"></i> Enregistrer le brouillon
        </button>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('formAction').value='generate'">
            <i class="fas fa-file-export"></i> Générer le cahier des charges
        </button>
    </div>
</form>

<link rel="stylesheet" href="<?=url('assets/css/cahier-form.css')?>">
<script src="<?=url('assets/js/cahier-form.js')?>"></script>

