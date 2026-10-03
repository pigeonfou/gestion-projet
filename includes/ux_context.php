<?php
/** Shared project navigation. No reads from downstream objects in the step 3 context. */
function pfProjectLinks(int $pid): array {
 return ['Synthèse'=>'projet.php?id='.$pid,'R1b'=>'projet.php?id='.$pid.'&view=processus','S.F. / S.T.'=>'specifications.php?projet_id='.$pid,'Tâches'=>'projet.php?id='.$pid.'&view=taches','Planning'=>'planning.php?projet_id='.$pid,'Achats'=>'projet.php?id='.$pid.'&view=processus&step=5','Documents'=>'documents_externes.php?projet_id='.$pid,'Qualité'=>'qualite_projet.php?projet_id='.$pid,'Production'=>'production.php?projet_id='.$pid];
}
function pfRenderContext(?array $project, string $title): void {
 $pid=(int)($project['id']??0);
 if (!$pid) return;
 $file=basename($_SERVER['SCRIPT_NAME']??'');
 ?>
 <div class="pf-context">
 <nav class="pf-context-links" aria-label="Vues du projet"><?php foreach(pfProjectLinks($pid) as $label=>$path):
 $selected=$file===strtok($path,'?');
 if($file==='projet.php') {
  $view=(string)($_GET['view']??'dashboard');
  $selected=match($label){'Synthèse'=>$view==='dashboard','R1b'=>$view==='processus'&&(int)($_GET['step']??0)!==5,'Achats'=>$view==='processus'&&(int)($_GET['step']??0)===5,'Tâches'=>$view==='taches',default=>false};
 }
 if($file==='specification.php'&&$label==='S.F. / S.T.')$selected=true;
 ?><a href="<?=url($path)?>" <?=$selected?'aria-current="page"':''?>><?=e($label)?></a><?php endforeach;?></nav>
 </div>
 <?php
}
