<?php
/** Shared project navigation. No reads from downstream objects in the step 3 context. */
function pfProjectLinks(int $pid): array {
 return ['Synthèse'=>'projet.php?id='.$pid,'R1b'=>'projet.php?id='.$pid.'&view=processus','S.F. / S.T.'=>'specifications.php?projet_id='.$pid,'Tâches'=>'projet.php?id='.$pid.'&view=taches','Planning'=>'planning.php?projet_id='.$pid,'Achats'=>'projet.php?id='.$pid.'&view=processus&step=5','Documents'=>'documents_externes.php?projet_id='.$pid,'Qualité'=>'qualite_projet.php?projet_id='.$pid,'Production'=>'production.php?projet_id='.$pid];
}
function pfRenderContext(?array $project, string $title): void {
 $pid=(int)($project['id']??0); $file=basename($_SERVER['SCRIPT_NAME']??'');
 ?>
 <div class="pf-context">
 <nav class="pf-breadcrumb" aria-label="Fil d’Ariane"><a href="<?=url('projets.php')?>">Projets</a><?php if($pid):?><span>›</span><a href="<?=url('projet.php?id='.$pid)?>"><?=e($project['nom']??'Projet')?></a><?php endif;?><span>›</span><span aria-current="page"><?=e($title)?></span></nav>
 <?php if($pid):?><div class="pf-context-meta"><span class="pf-status"><?=e(['actif'=>'En cours','termine'=>'Validé / vente','abandonne'=>'Abandonné'][$project['status']??'']??'Projet')?></span><?php if(isset($project['current_step'])):?><span>R1b · étape <?=(int)$project['current_step']?> / 9</span><?php endif;?><?php if(!empty($project['createur'])):?><span>Pilotage · <?=e($project['createur'])?></span><?php endif;?></div>
 <nav class="pf-context-links" aria-label="Vues du projet"><?php foreach(pfProjectLinks($pid) as $label=>$path):?><a href="<?=url($path)?>" <?=$file===strtok($path,'?')&&$file!=='projet.php'?'aria-current="page"':''?>><?=e($label)?></a><?php endforeach;?></nav><?php endif;?>
 </div>
 <?php
}
