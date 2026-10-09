<?php
$trl=trlLoad($db,$id);$trlLevels=trlLevels();$trlUrl=url('projet.php?id='.$id.'&view=trl');
$trlLabel=static fn($value)=>$value===null?'Non évalué':'TRL '.(int)$value;
?>
<section class="trl-panel" aria-label="Maturité technologique du projet">
 <div class="trl-top"><h3>Maturité technologique · TRL</h3><a class="btn btn-secondary btn-sm" href="<?=e($trlUrl)?>">Échelle et évaluation</a></div>
 <div class="trl-values"><span>À l’entrée<strong><?=e($trlLabel($trl['entree']))?></strong></span><span>Actuel · évalué<strong><?=e($trlLabel($trl['actuel']))?></strong></span><span>Cible à la sortie<strong><?= $trl['cible']===null?'Non définie':e($trlLabel($trl['cible'])) ?></strong></span></div>
 <ol class="trl-scale" aria-label="Échelle TRL de 1 à 9">
 <?php foreach($trlLevels as $level=>$label):?><li class="<?= (int)$trl['actuel']===$level?'trl-current ':'' ?><?= (int)$trl['cible']===$level?'trl-target ':'' ?><?= (int)$trl['entree']===$level?'trl-entry':'' ?>"><a href="<?=e($trlUrl.'#trl-'.$level)?>" title="<?=e('TRL '.$level.' · '.$label)?>" <?= (int)$trl['actuel']===$level?'aria-current="true"':'' ?> aria-label="<?=e('TRL '.$level.' : '.$label.((int)$trl['actuel']===$level?' — niveau actuel':''))?>"><span>TRL</span><b><?=$level?></b></a></li><?php endforeach;?>
 </ol>
 <p class="trl-note">Le TRL mesure la maturité de la technologie, de la recherche à l’exploitation. Une étape R1b validée ne prouve pas, à elle seule, un niveau TRL. Repères : souligné = entrée · plein = actuel · pointillé = cible.</p>
 <?php if($view==='trl'): ?>
  <h4>Évaluer la technologie dans son contexte d’utilisation</h4>
  <p>Le besoin, les études et le GO/NO GO cadrent le développement. Le prototype, les essais et la livraison peuvent apporter des preuves de maturité. Le niveau retenu dépend des résultats et de l’environnement d’essai, pas du numéro de l’étape. La livraison ne démontre pas automatiquement un TRL 9.</p>
  <?php if($trl['preuves']!==''):?><p class="trl-proof"><strong>Preuves / contexte de l’évaluation</strong><br><?=e($trl['preuves'])?></p><?php endif;?>
  <?php if($trl['updated_at']):?><p class="trl-note">Dernière évaluation enregistrée : <?=e($trl['updated_at'])?> UTC.</p><?php endif;?>
  <?php if(projectCanManage($db,$id,$user)): ?>
  <details class="trl-editor" open><summary>Renseigner l’entrée, le niveau actuel et la cible</summary>
  <form method="post" action="<?=e($trlUrl)?>"><?=csrfField()?><input type="hidden" name="action" value="save_trl"><input type="hidden" name="revision" value="<?=(int)$trl['revision']?>">
   <div class="trl-form-grid"><?php foreach(['entree'=>'TRL à l’entrée','actuel'=>'TRL actuel évalué','cible'=>'TRL cible à la sortie'] as $key=>$title):?><label for="trl-<?=$key?>"><?=e($title)?><select id="trl-<?=$key?>" name="<?=$key?>" class="form-control"><option value="">Non renseigné</option><?php foreach($trlLevels as $level=>$label):?><option value="<?=$level?>" <?=(int)$trl[$key]===$level?'selected':''?>><?=e('TRL '.$level.' — '.$label)?></option><?php endforeach;?></select></label><?php endforeach;?></div>
   <label for="trl-preuves">Preuves, environnement d’essai et justification</label><textarea id="trl-preuves" name="preuves" class="form-control" rows="4" maxlength="10000" placeholder="Résultats des essais, prototype évalué, environnement représentatif ou opérationnel, références des documents…"><?=e($trl['preuves'])?></textarea>
   <p class="trl-note">Obligatoire si un niveau actuel est indiqué. La cible exprime un objectif ; elle ne constitue pas une validation. Une réévaluation à la baisse reste possible.</p><button class="btn btn-primary" type="submit">Enregistrer l’évaluation TRL</button>
  </form></details>
  <?php endif;?>
  <div class="trl-reference"><?php foreach($trlLevels as $level=>$label):?><article id="trl-<?=$level?>"><h4>TRL <?=$level?></h4><p><?=e($label)?></p></article><?php endforeach;?></div>
  <p class="trl-note">Repères adaptés au développement d’équipements. <a href="https://www.esa.int/Enabling_Support/Space_Engineering_Technology/Shaping_the_Future/Technology_Readiness_Levels_TRL" target="_blank" rel="noopener">Référence : échelle TRL de l’ESA</a>.</p>
  <details><summary>Historique des évaluations TRL</summary><?php $q=$db->prepare('SELECT h.*,u.identifiant FROM projet_trl_historique h LEFT JOIN utilisateurs u ON u.id=h.acteur_id WHERE h.projet_id=? ORDER BY h.id DESC LIMIT 50');$q->execute([$id]);$history=$q->fetchAll(PDO::FETCH_ASSOC);if(!$history):?><p>Aucune évaluation enregistrée.</p><?php endif;foreach($history as $entry):$state=json_decode($entry['apres_json'],true);?><p><?=e($entry['date_action'].' UTC · '.($entry['identifiant']??'Utilisateur').' · Entrée : '.$trlLabel($state['entree']).' · Actuel : '.$trlLabel($state['actuel']).' · Cible : '.$trlLabel($state['cible']))?></p><p class="trl-proof"><?=e($state['preuves'])?></p><?php endforeach;?></details>
 <?php endif;?>
</section>
