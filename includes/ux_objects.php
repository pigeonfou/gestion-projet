<?php
require_once __DIR__.'/cahier_specs.php';
require_once __DIR__.'/decision_dashboard.php';
function pfLoadProject(int $pid): array {
 requerirAccesProjet($pid,false);$q=getDB()->prepare('SELECT p.*,u.identifiant createur FROM projets p JOIN utilisateurs u ON u.id=p.createur_id WHERE p.id=?');$q->execute([$pid]);$p=$q->fetch();if(!$p){setFlash('error','Projet introuvable.');redirect('projets.php');}return $p;
}
function pfSpecs(int $pid): array {
 $q=getDB()->prepare('SELECT id FROM cahiers WHERE projet_id=? ORDER BY id LIMIT 1');$q->execute([$pid]);$cid=(int)$q->fetchColumn();return $cid?loadSpecs($cid):emptySpecs();
}
function pfStUrl(int $pid,array $st): string {return url('specification.php?projet_id='.$pid.'&uid='.rawurlencode($st['uid']??'').'&st='.rawurlencode($st['id']??''));}
/** Only explicit structured source keys establish a task relation; titles do not. */
function pfTaskStId(array $task): ?string {
 $pid=(int)($task['projet_id']??0);$key=(string)($task['source_key']??'');
 return preg_match('/^(?:st|cp|achat):'.preg_quote((string)$pid,'/').':(S\.T\.\d+\.\d+)(?::|$)/D',$key,$m)?$m[1]:null;
}
function pfTaskStUrl(array $task): ?string { $st=pfTaskStId($task);return $st?url('specification.php?projet_id='.(int)$task['projet_id'].'&st='.rawurlencode($st)):null; }
function pfTaskLinks(array $task): void {
 $st=pfTaskStId($task);$pid=(int)($task['projet_id']??0);?><div class="pf-task-links"><?php if($st):?><a class="pf-object-id" href="<?=e(pfTaskStUrl($task))?>"><?=e($st)?> ↗</a><?php endif;?><?php if(!empty($task['assigne_a'])):?><a href="<?=url('personne.php?identifiant='.rawurlencode($task['assigne_a']).'&projet_id='.$pid)?>"><?=e($task['assigne_a'])?></a><?php endif;?><?php if(!empty($task['dependance_id'])):?><a href="<?=url('tache.php?id='.(int)$task['dependance_id'])?>">Après #<?=(int)$task['dependance_id']?></a><?php endif;?></div><?php
}
function pfRiskBadge(string $risk): string { $risk=normalizeEstimationVariation($risk);return '<span class="pf-tag pf-risk-'.strtolower($risk).'">'.e($risk?:'Non renseigné').'</span>'; }
function pfMoney($value,string $tax='HT'): string{return number_format((float)$value,2,',',' ').' € '.($tax==='TTC'?'TTC':'HT');}
function pfStateBadge(string $state): string {
 $states=['validee'=>['Validée','success'],'termine'=>['Terminé','success'],'libere'=>['Libéré','success'],'brouillon'=>['Brouillon','neutral'],'en_cours'=>['En cours','info'],'quarantaine'=>['Quarantaine','warning'],'rejete'=>['Rejeté','danger'],'annule'=>['Annulé','neutral']];
 [$label,$tone]=$states[$state]??[$state,'neutral'];
 return '<span class="pf-tag pf-tone-'.$tone.'">'.e($label).'</span>';
}
