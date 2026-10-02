<?php
require_once __DIR__.'/../decision_gantt.php';
$gantt = ddGantt($tachesAll);
$ganttStates = ['a_faire'=>'À faire','en_cours'=>'En cours','validation'=>'En validation','terminee'=>'Terminée'];
?>
<div class="dd-section-head"><h5>Diagramme de Gantt</h5><a href="<?=e($sourceLink('tasks'))?>">Planifier les tâches</a></div>
<p class="dd-muted">Toutes les phases du projet · dates prévues enregistrées dans les tâches. Une échéance seule apparaît comme un losange ; ajoutez un début prévu dans la fiche tâche pour afficher sa durée. Les dates métier des résultats restent distinctes.</p>
<div class="dd-gantt-legend"><span>● À faire</span><span>● En cours</span><span>● En validation</span><span>● Terminée</span><span>◆ Échéance sans début prévu</span></div>
<?php if($gantt['rows']): ?>
<p><strong><?=e($gantt['min']->format('d/m/Y'))?> → <?=e($gantt['max']->format('d/m/Y'))?></strong> · <?=count($gantt['rows'])?> tâches datées · <?=count($gantt['undated'])?> à planifier</p>
<div class="dd-gantt-scroll" tabindex="0" role="region" aria-label="Diagramme de Gantt, défilement horizontal">
 <div class="dd-gantt">
  <div class="dd-gantt-row dd-gantt-header"><div>ID tâche</div><div class="dd-gantt-axis"><?php foreach($gantt['ticks'] as $tick):?><span style="left:<?=round($tick['left'],4)?>%;"><?=e($tick['label'])?></span><?php endforeach;?></div></div>
  <?php foreach($gantt['rows'] as $row): $task=$row['task']; $state=$task['kanban_status']??$task['statut']??'a_faire'; if(!isset($ganttStates[$state]))$state='a_faire'; $period=$row['milestone']?'Échéance '.$row['end']->format('d/m/Y'):$row['start']->format('d/m/Y').' → '.$row['end']->format('d/m/Y'); ?>
  <div class="dd-gantt-row"><div class="dd-gantt-label"><a href="<?=url('tache.php?id='.(int)$task['id'])?>" title="<?=e($task['titre'].' · '.$period.' · '.$ganttStates[$state].(!empty($task['dependance_id'])?' · après #'.(int)$task['dependance_id']:''))?>">#<?=(int)$task['id']?></a></div>
   <div class="dd-gantt-lane"><?php foreach($gantt['ticks'] as $tick):?><i style="left:<?=round($tick['left'],4)?>%"></i><?php endforeach;?>
    <a class="dd-gantt-bar dd-gantt-<?=e($state)?> <?= $row['milestone']?'dd-gantt-milestone':'' ?>" href="<?=url('tache.php?id='.(int)$task['id'])?>" style="left:<?=round($row['left'],4)?>%;width:<?=round($row['width'],4)?>%" title="<?=e($task['titre'].' · '.$period.' · '.$ganttStates[$state])?>" aria-label="<?=e('#'.$task['id'].' '.$task['titre'].' · '.$period.' · '.$ganttStates[$state])?>"><?= $row['milestone']?'◆':'#'.(int)$task['id'] ?></a>
   </div></div>
  <?php endforeach;?>
 </div>
</div>
<?php else: ?><p class="dd-alert">Aucune échéance valide enregistrée. Renseignez le début prévu et l’échéance des tâches pour construire le Gantt.</p><?php endif;?>
<?php if($gantt['undated']):?><details class="dd-details"><summary>Tâches à planifier (<?=count($gantt['undated'])?>)</summary><?php foreach($gantt['undated'] as $task):?><p><a href="<?=url('tache.php?id='.(int)$task['id'])?>">#<?=(int)$task['id']?></a> · échéance absente ou période invalide</p><?php endforeach;?></details><?php endif;?>
<p class="dd-muted">Survolez un ID pour consulter le titre, les dates, le statut et la dépendance enregistrée. Le Gantt ne décale pas automatiquement les tâches et ne calcule pas de chemin critique sans durées et disponibilités complètes.</p>
