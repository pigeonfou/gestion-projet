<?php
$history=loadDecisionHistory($db,$id,$_GET);
$filters=$history['filters'];
$historyUrl=static fn(array $changes=[]): string => url('projet.php?'.http_build_query(array_merge(['id'=>$id,'view'=>'historique','step'=>$currentStep,'embedded'=>!empty($historyEmbedded)?'1':'0'],$filters,$changes)));
$labels=['DONE'=>'Étape validée','REFUSE'=>'Refus','GO'=>'GO','NO_GO'=>'NO GO','CONFORME'=>'Conforme','NON_CONFORME'=>'Non conforme'];
?>
<?php if(empty($historyEmbedded)): ?>
<div class="r1b-page-head">
  <div><h2>Historique des décisions</h2><p class="text-muted text-sm">Projet : <strong><?= e($projet['nom']) ?></strong> · Historique indépendant des formulaires des étapes.</p></div>
  <a class="btn btn-secondary" href="<?= url('projet.php?id='.$id.'&view=processus&step='.$currentStep) ?>">Retour à l’étape <?= $currentStep ?></a>
</div>
<?php endif; ?>
<section class="r1b-card decision-history" aria-label="Historique des décisions du projet">
  <div class="history-toolbar">
  <form method="get" action="<?= url('projet.php') ?>" class="decision-history-filters">
    <?php if(!empty($historyEmbedded)): ?><input type="hidden" name="embedded" value="1"><?php endif; ?>
    <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="view" value="historique"><input type="hidden" name="step" value="<?= $currentStep ?>">
    <label class="history-search" for="history-search">Rechercher<input id="history-search" type="search" name="h_q" class="form-control" value="<?= e($filters['h_q']) ?>" maxlength="500" placeholder="Motif, résultat, décision ou acteur"></label>
    <label for="history-step">Étape<select id="history-step" name="h_step" class="form-control"><option value="0">Toutes les étapes</option><?php foreach($steps as $n=>$s): ?><option value="<?= $n ?>" <?= $filters['h_step']===$n?'selected':'' ?>><?= $n ?> · <?= e($s['title']) ?></option><?php endforeach; ?></select></label>
    <label for="history-decision">Décision<select id="history-decision" name="h_decision" class="form-control"><option value="">Toutes les décisions</option><?php foreach($labels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['h_decision']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label for="history-actor">Acteur<select id="history-actor" name="h_actor" class="form-control"><option value="0">Tous les acteurs</option><?php foreach($history['actors'] as $actor): ?><option value="<?= (int)$actor['utilisateur_id'] ?>" <?= $filters['h_actor']===(int)$actor['utilisateur_id']?'selected':'' ?>><?= e($actor['identifiant'] ?? ('Utilisateur #'.$actor['utilisateur_id'])) ?></option><?php endforeach; ?></select></label>
    <label for="history-from">Du (date serveur)<input id="history-from" type="date" name="h_from" class="form-control" value="<?= e($filters['h_from']) ?>"></label>
    <label for="history-to">Au (date serveur)<input id="history-to" type="date" name="h_to" class="form-control" value="<?= e($filters['h_to']) ?>"></label>
    <label for="history-sort">Trier par<select id="history-sort" name="h_sort" class="form-control"><?php foreach(['date'=>'Date serveur','step'=>'Étape','decision'=>'Décision','actor'=>'Acteur'] as $key=>$label): ?><option value="<?= $key ?>" <?= $filters['h_sort']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <label for="history-order">Ordre<select id="history-order" name="h_order" class="form-control"><option value="desc" <?= $filters['h_order']==='desc'?'selected':'' ?>>Décroissant</option><option value="asc" <?= $filters['h_order']==='asc'?'selected':'' ?>>Croissant</option></select></label>
    <label for="history-size">Par page<select id="history-size" name="h_size" class="form-control"><?php foreach([25,50,100] as $size): ?><option value="<?= $size ?>" <?= $filters['h_size']===$size?'selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label>
    <div class="history-filter-actions"><button class="btn btn-primary" type="submit">Appliquer</button><a class="btn btn-secondary" href="<?= url('projet.php?id='.$id.'&view=historique&step='.$currentStep.(!empty($historyEmbedded)?'&embedded=1':'')) ?>">Réinitialiser</a></div>
  </form>
  <p class="text-sm history-count" role="status"><?= $history['count'] ?> décision<?= $history['count']>1?'s':'' ?> trouvée<?= $history['count']>1?'s':'' ?> sur <?= $history['total'] ?> · Page <?= $filters['h_page'] ?> / <?= $history['pages'] ?></p>
  </div>
  <?php if(!$history['rows']): ?><p class="text-muted"><?= $history['total'] ? 'Aucune décision ne correspond aux critères. Modifiez les filtres ou réinitialisez la recherche.' : 'Aucune décision enregistrée pour ce projet.' ?></p><?php else: ?>
  <div class="table-wrapper"><table class="decision-history-table"><caption class="history-table-caption">Décisions et motifs enregistrés (horodatages serveur)</caption><thead><tr><th scope="col">Date serveur</th><th scope="col">Étape</th><th scope="col">Décision</th><th scope="col">Acteur</th><th scope="col">Motif / résultats</th></tr></thead><tbody>
    <?php foreach($history['rows'] as $d): ?><tr><td><?= e($d['date_decision']) ?></td><td><a <?= !empty($historyEmbedded)?'target="_blank" rel="noopener"':'' ?> href="<?= url('projet.php?id='.$id.'&view=processus&step='.(int)$d['etape']) ?>"><?= (int)$d['etape'] ?> · <?= e($steps[(int)$d['etape']]['title'] ?? '') ?></a></td><td><span class="history-decision history-<?= e(strtolower($d['decision'])) ?>"><?= e($labels[$d['decision']] ?? $d['decision']) ?></span></td><td><?= e($d['identifiant'] ?? ('Utilisateur #'.$d['utilisateur_id'])) ?></td><td class="history-motif"><?= e($d['motif']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if($history['pages']>1): ?><nav class="history-pagination" aria-label="Pages de l’historique"><?php if($filters['h_page']>1): ?><a class="btn btn-secondary" href="<?= e($historyUrl(['h_page'=>$filters['h_page']-1])) ?>">Page précédente</a><?php endif; ?><span>Page <?= $filters['h_page'] ?> / <?= $history['pages'] ?></span><?php if($filters['h_page']<$history['pages']): ?><a class="btn btn-secondary" href="<?= e($historyUrl(['h_page'=>$filters['h_page']+1])) ?>">Page suivante</a><?php endif; ?></nav><?php endif; ?>
  <?php endif; ?>
</section>
