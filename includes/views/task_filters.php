<?php
require_once __DIR__.'/../settings_helper.php';
require_once __DIR__.'/../task_filters.php';
$taskFilters=taskFilterInput($_GET);
$taskFilterTotal=count($taches);
$filterPeople=[];$filterProjects=[];
foreach($taches as $t){if(trim((string)($t['assigne_a']??''))!=='')$filterPeople[$t['assigne_a']]=$t['assigne_a'];if(!empty($t['projet_nom']))$filterProjects[(int)$t['projet_id']]=$t['projet_nom'];}
natcasesort($filterPeople);natcasesort($filterProjects);
$taches=taskFilterApply($taches,$taskFilters);
$taskFilterBase=$showProjectLink?'taches.php':'projet.php?id='.(int)$id.'&view=taches';
$statusFormAction=url($taskFilterBase.'&'.taskFilterQuery($taskFilters));
if($showProjectLink)$statusFormAction=url('taches.php?'.taskFilterQuery($taskFilters));
?>
<link rel="stylesheet" href="<?=url('assets/css/task-filters.css?v=night-3')?>">
<form class="task-filter-toolbar" data-live-filters data-highlight-color="<?= e(searchHighlightColor()) ?>" method="GET" action="<?=url($showProjectLink?'taches.php':'projet.php')?>" aria-label="Recherche, filtres et tri des tâches">
<?php if(!$showProjectLink):?><input type="hidden" name="id" value="<?=(int)$id?>"><input type="hidden" name="view" value="taches"><?php endif;?>
<div class="task-filter-search"><label for="tf_q">Recherche</label><input id="tf_q" name="tf_q" type="search" maxlength="200" value="<?=e($taskFilters['q'])?>" placeholder="Titre, description, résultats, responsable, n°…"></div>
<?php $selects=['status'=>['label'=>'Statut','items'=>[''=>'Tous les statuts','a_faire'=>'À faire','en_cours'=>'En cours','validation'=>'En validation','terminee'=>'Terminée']], 'priority'=>['label'=>'Priorité','items'=>[''=>'Toutes les priorités','urgente'=>'Urgente','haute'=>'Haute','moyenne'=>'Moyenne','basse'=>'Basse']], 'assignee'=>['label'=>'Responsable','items'=>[''=>'Tous les responsables','__unassigned'=>'Non assignées']+$filterPeople], 'due'=>['label'=>'Échéance','items'=>[''=>'Toutes les échéances','late'=>'En retard (non terminées)','undated'=>'Sans échéance']]]; if($showProjectLink)$selects['project']=['label'=>'Projet','items'=>[''=>'Tous les projets']+$filterProjects]; foreach($selects as $key=>$field):?>
<div><label for="tf_<?=e($key)?>"><?=e($field['label'])?></label><select id="tf_<?=e($key)?>" name="tf_<?=e($key)?>"><?php foreach($field['items'] as $value=>$label):?><option value="<?=e((string)$value)?>" <?=(string)$value===$taskFilters[$key]?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><?php endforeach;?>
<div><label for="tf_from">Échéance à partir du</label><input type="date" id="tf_from" name="tf_from" value="<?=e($taskFilters['from'])?>"></div><div><label for="tf_to">Échéance jusqu’au</label><input type="date" id="tf_to" name="tf_to" value="<?=e($taskFilters['to'])?>"></div>
<div><label for="tf_sort">Trier par</label><select id="tf_sort" name="tf_sort"><?php $sorts=['id'=>'Création','title'=>'Titre','deadline'=>'Échéance','priority'=>'Priorité','assignee'=>'Responsable'];if($showProjectLink)$sorts['project']='Projet';foreach($sorts as $v=>$label):?><option value="<?=e($v)?>" <?=$taskFilters['sort']===$v?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div>
<div><label for="tf_order">Ordre</label><select id="tf_order" name="tf_order"><option value="asc" <?=$taskFilters['order']==='asc'?'selected':''?>>Croissant</option><option value="desc" <?=$taskFilters['order']==='desc'?'selected':''?>>Décroissant</option></select></div>

</form>
<div data-live-results="tasks">
<p class="task-filter-count" role="status"><?=count($taches)?> tâche(s) affichée(s) sur <?=$taskFilterTotal?> · tri dans chaque colonne du Kanban. Échéances absentes en fin de liste.</p>
<?php if(!$taches && $taskFilterTotal):?><p class="task-filter-empty">Aucune tâche ne correspond à ces critères. Modifiez les filtres ou effacez la recherche.</p><?php endif;?>
