<?php
$trl = trlLoad($db, $id);
$trlLevels = trlLevels();
$trlGroups = trlGroups();
$trlUrl = url('projet.php?id=' . $id . '&view=trl');
$trlLabel = static fn($value) => trlDisplayLabel($value);
$trlValueLabel = static fn(string $key) => isset($trl['_excluded_levels'][$key])
    ? 'À réévaluer' : trlDisplayLabel($trl[$key], $key === 'cible' ? 'Non définie' : 'Non évalué');
$trlTitles = [3=>'Faisabilité démontrée', 4=>'Validation en laboratoire', 5=>'Validation en environnement représentatif',
    6=>'Démonstration en environnement très représentatif', 7=>'Démonstration en environnement opérationnel'];
$trlPhase = $trl['actuel'] === null ? null : ((int)$trl['actuel'] <= 5 ? 'ri' : 'de');
?>
<?php if ($view !== 'trl'): ?>
<aside class="trl-overview" aria-label="Maturité technologique du projet">
 <span class="trl-overview-label">Maturité technologique</span>
 <strong><?= e($trlValueLabel('actuel')) ?></strong>
 <?php if ($trlPhase !== null): ?><span class="trl-phase-chip trl-phase-<?= e($trlPhase) ?>"><?= e($trlGroups[$trlPhase]['code']) ?></span><?php endif; ?>
 <span class="trl-overview-target">Cible : <?= e($trlValueLabel('cible')) ?></span>
 <a href="<?= e($trlUrl) ?>">Consulter l’évaluation <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
</aside>
<?php else: ?>
<section class="trl-page" aria-labelledby="trl-page-title">
 <header class="trl-page-header">
  <span class="trl-eyebrow">Référentiel AID/DGA · TRL 3 à 7</span>
  <h2 id="trl-page-title">Maturité technologique · TRL</h2>
  <p>Positionner la technologie selon les résultats démontrés et l’environnement d’essai.</p>
 </header>

 <div class="trl-status-grid" aria-label="Positionnement du projet">
  <div class="trl-status-card">
   <span class="trl-status-label">À l’entrée du projet</span>
   <strong><?= e($trlValueLabel('entree')) ?></strong>
   <span class="trl-status-caption">Point de départ</span>
  </div>
  <div class="trl-status-card trl-status-current">
   <span class="trl-status-label">Niveau actuel évalué</span>
   <strong><?= e($trlValueLabel('actuel')) ?></strong>
   <span class="trl-status-caption"><?= $trlPhase === null ? 'À établir sur la base de preuves' : e($trlGroups[$trlPhase]['code'] . ' · ' . $trlGroups[$trlPhase]['title']) ?></span>
  </div>
  <div class="trl-status-card">
   <span class="trl-status-label">Cible à la sortie</span>
   <strong><?= e($trlValueLabel('cible')) ?></strong>
   <span class="trl-status-caption">Objectif à atteindre</span>
  </div>
 </div>

 <?php if ($trl['_excluded_levels']): ?>
 <p class="trl-reassess" role="status">Une évaluation antérieure utilise un niveau hors du périmètre actuel. Renseignez une nouvelle évaluation entre TRL 3 et TRL 7. Les données antérieures restent conservées pour la traçabilité.</p>
 <?php endif; ?>

 <div class="trl-section-heading">
  <h3>Deux natures de projet, cinq niveaux de maturité</h3>
  <p>Du composant ou de la maquette fonctionnelle à la démonstration du système.</p>
 </div>
 <div class="trl-domains">
 <?php foreach ($trlGroups as $groupId => $group): ?>
  <section class="trl-domain trl-domain-<?= e($groupId) ?>" aria-labelledby="trl-domain-<?= e($groupId) ?>">
   <header class="trl-domain-header">
    <div class="trl-domain-meta"><span class="trl-phase-chip"><?= e($group['code']) ?></span><span><?= e($group['range']) ?></span></div>
    <h3 id="trl-domain-<?= e($groupId) ?>"><?= e($group['title']) ?></h3>
    <p><?= e($group['description']) ?></p>
    <p class="trl-domain-environment"><i class="fas fa-flask" aria-hidden="true"></i> <?= e($group['environment']) ?></p>
   </header>
   <ol class="trl-level-list" start="<?= (int)$group['levels'][0] ?>" aria-label="<?= e($group['range']) ?>">
   <?php foreach ($group['levels'] as $level): $isCurrent = (int)$trl['actuel'] === $level; ?>
    <li value="<?= $level ?>">
     <article id="trl-<?= $level ?>" class="trl-level-card<?= $isCurrent ? ' is-current' : '' ?>" aria-labelledby="trl-title-<?= $level ?>">
      <div class="trl-level-heading"><span class="trl-level-number">TRL <?= $level ?></span><div class="trl-level-markers">
       <?php if ((int)$trl['entree'] === $level): ?><span class="trl-marker">Entrée</span><?php endif; ?>
       <?php if ($isCurrent): ?><span class="trl-marker trl-marker-current"><i class="fas fa-check" aria-hidden="true"></i> Actuel</span><?php endif; ?>
       <?php if ((int)$trl['cible'] === $level): ?><span class="trl-marker trl-marker-target">Cible</span><?php endif; ?>
      </div></div>
      <h4 id="trl-title-<?= $level ?>"><?= e($trlTitles[$level]) ?></h4>
      <p><?= e($trlLevels[$level]) ?>.</p>
     </article>
    </li>
   <?php endforeach; ?>
   </ol>
  </section>
 <?php endforeach; ?>
 </div>
 <p class="trl-source">Définitions : AID/DGA, annexe « Niveaux de maturité technologique », page 9. Périmètre utilisé dans OddWorks : TRL 3 à 7.</p>

 <section class="trl-evaluation" aria-labelledby="trl-evaluation-title">
  <div class="trl-section-heading">
   <h3 id="trl-evaluation-title">Évaluation du projet</h3>
   <p>Le niveau actuel s’appuie sur des preuves. La cible reste un objectif ; la validation d’une étape R1b ne modifie pas automatiquement le TRL.</p>
  </div>
  <?php if (projectCanManage($db, $id, $user)): ?>
  <form method="post" action="<?= e($trlUrl) ?>" class="trl-evaluation-form">
   <?= csrfField() ?>
   <input type="hidden" name="action" value="save_trl">
   <input type="hidden" name="revision" value="<?= (int)$trl['revision'] ?>">
   <div class="trl-form-grid">
   <?php foreach (['entree'=>'Niveau à l’entrée', 'actuel'=>'Niveau actuel évalué', 'cible'=>'Niveau cible'] as $key => $title): ?>
    <label for="trl-<?= $key ?>"><?= e($title) ?>
     <select id="trl-<?= $key ?>" name="<?= $key ?>" class="form-control">
      <option value="">Non renseigné</option>
      <?php foreach ($trlGroups as $group): ?>
      <optgroup label="<?= e($group['code'] . ' · ' . $group['title'] . ' — ' . $group['range']) ?>">
       <?php foreach ($group['levels'] as $level): ?><option value="<?= $level ?>" <?= (int)$trl[$key] === $level ? 'selected' : '' ?>><?= e('TRL ' . $level . ' — ' . $trlTitles[$level]) ?></option><?php endforeach; ?>
      </optgroup>
      <?php endforeach; ?>
     </select>
    </label>
   <?php endforeach; ?>
   </div>
   <label class="trl-proof-label" for="trl-preuves">Preuves, environnement d’essai et justification</label>
   <textarea id="trl-preuves" name="preuves" class="form-control" rows="5" maxlength="10000" aria-describedby="trl-proof-help" placeholder="Décrivez ce qui a été démontré, dans quel environnement et avec quels résultats. Indiquez les références des essais ou documents justificatifs."><?= e($trl['preuves']) ?></textarea>
   <p id="trl-proof-help" class="trl-note">Une justification est obligatoire pour renseigner le niveau actuel. Une réévaluation à la baisse est possible si les résultats le nécessitent.</p>
   <div class="trl-form-footer"><span class="trl-note"><?= $trl['updated_at'] ? e('Dernière évaluation : ' . $trl['updated_at'] . ' UTC') : 'Aucune évaluation enregistrée' ?></span><button class="btn btn-primary" type="submit">Enregistrer l’évaluation</button></div>
  </form>
  <?php else: ?>
   <?php if ($trl['preuves'] !== ''): ?><p class="trl-proof"><?= e($trl['preuves']) ?></p><?php else: ?><p class="trl-note">Aucune justification enregistrée.</p><?php endif; ?>
   <?php if ($trl['updated_at']): ?><p class="trl-note">Dernière évaluation : <?= e($trl['updated_at']) ?> UTC.</p><?php endif; ?>
  <?php endif; ?>
 </section>

 <details class="trl-history">
  <summary>Historique des évaluations</summary>
  <?php
   $q = $db->prepare('SELECT h.*,u.identifiant FROM projet_trl_historique h LEFT JOIN utilisateurs u ON u.id=h.acteur_id WHERE h.projet_id=? ORDER BY h.id DESC LIMIT 50');
   $q->execute([$id]); $history = $q->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <?php if (!$history): ?><p class="trl-note">Aucune évaluation enregistrée.</p><?php endif; ?>
  <?php foreach ($history as $entry): $state = json_decode($entry['apres_json'], true); ?>
  <article class="trl-history-entry">
   <p class="trl-history-meta"><?= e($entry['date_action'] . ' UTC · ' . ($entry['identifiant'] ?? 'Utilisateur')) ?></p>
   <p>Entrée : <strong><?= e($trlLabel($state['entree'])) ?></strong> · Actuel : <strong><?= e($trlLabel($state['actuel'])) ?></strong> · Cible : <strong><?= e($trlLabel($state['cible'])) ?></strong></p>
   <p class="trl-proof"><?= e($state['preuves']) ?></p>
  </article>
  <?php endforeach; ?>
 </details>
</section>
<?php endif; ?>
