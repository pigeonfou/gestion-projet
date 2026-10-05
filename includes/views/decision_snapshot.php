<?php
require_once __DIR__.'/../decision_dashboard.php';
$snapshot=json_decode($d['snapshot_json']??'',true);
?>
<?php if(is_array($snapshot) && ($snapshot['version']??0)===1 && is_array($snapshot['sources']??null)):
$historicalSources=$snapshot['sources']; $historical=is_array($snapshot['analysis']??null)?$snapshot['analysis']:ddConsolidate($historicalSources); ?>
<details class="dd-details decision-snapshot"><summary>État des connaissances conservé lors de cette décision</summary>
<p>Sources : Étapes 1 et 2 · enregistré le <?=e($d['date_decision'])?> UTC. Ces valeurs restent indépendantes des corrections suivantes.</p>
<p><strong><?=e($snapshot['project']['nom']??'')?></strong></p>
<p>Origine : <?=!empty($snapshot['project']['cadrage_commerciale'])?'commerciale ':''?><?=!empty($snapshot['project']['cadrage_technique'])?'technique':''?> · Destination : <?=e($snapshot['project']['cadrage_destination']??'Non renseignée')?></p>
<p><strong>Coût estimé S.T. :</strong> <?=e(ddCostSummary($historical))?> ; périmètre <?= $historical['lotHT']===null?'non comparable':e(ddMoney($historical['lotHT']))?>.</p>
<p>Budget cible : <?= $historical['budget']===null?'Non renseigné':e(ddMoney($historical['budget'],$historical['targets']['taxe']??'HT'))?> ; quantité du périmètre : <?=e((string)($historical['qty']??'Non renseignée'))?> ; TVA : <?=e((string)($historical['vat']??'Non renseignée'))?> ; périmètre : <?=e($historical['targets']['perimetre']??'')?></p>
<p><strong>Délai estimé élémentaire :</strong> <?=e(ddDelaySummary($historical))?><?= !empty($historical['delayIncompleteCount']) ? ' · '.(int)$historical['delayIncompleteCount'].' délai(s) inconnu(s) · maximum connu : '.e(ddDays($historical['maxDelay'])) : '' ?> ; objectif <?=e(ddDays($historical['targetDays']))?> ; délai global non déterminé.</p>
<p><strong>Risque initial :</strong> <?=$historical['riskCounts']['Faible']?> Faible · <?=$historical['riskCounts']['Moyen']?> Moyen · <?=$historical['riskCounts']['Fort']?> Fort · <?=$historical['riskCounts']['Non renseigné']?> non renseigné(s).</p>
<p>Capacités : non déterminées · <?=$historical['groups']?count($historical['groups']):0?> domaines techniques.</p>
<?php foreach(['objectifs'=>'Objectifs / contexte','resultats_attendus'=>'Hors périmètre','cas_usage'=>'Contraintes','profils_utilisateurs'=>'Utilisateurs','delais'=>'Objectif et planning textuel','livrables_attendus'=>'Livrables'] as $key=>$label):?><p><strong><?=e($label)?> :</strong> <?=e(($historicalSources[$key]??'')?:'Non renseigné')?></p><?php endforeach;?>
<?php foreach($historical['sfs'] as $sf):?><p><strong><?=e($sf['id'])?> · <?=e($sf['indicateur']??'')?></strong> — <?=e($sf['description']??'')?></p><?php endforeach;?>
<div class="table-wrapper"><table><thead><tr><th>S.T. / S.F.</th><th>Définition</th><th>Type</th><th>Quantité / unité estimée</th><th>Total estimé</th><th>Délai estimé</th><th>Risque initial</th></tr></thead><tbody>
<?php foreach($historical['lines'] as $line):?><tr><td><?=e($line['id'].' / '.$line['sf'])?></td><td><?=e($line['description']??'')?></td><td><?=e($line['type']??'')?></td><td><?=ddHasCost($line['type'])?e((string)($line['quantite']??'Non renseignée')).' × '.(ddNumber($line['cout_unitaire']??null)===null?(!empty($line['cout_unitaire_inconnu'])?'Inconnu':'Non renseigné'):e(ddMoney((float)$line['cout_unitaire'],$line['tax']))):'Sans coût d’achat'?></td><td><?= $line['cost']===null?(!empty($line['cost_unknown'])?'Inconnu':'Non renseigné / sans coût d’achat'):e(ddMoney($line['cost'],$line['tax']))?></td><td><?=e(!empty($line['delay_unknown'])?'Inconnu':ddDays($line['delay']))?></td><td><?=e($line['risk'])?></td></tr><?php endforeach;?></tbody></table></div>
<?php foreach($historical['references'] as $ref):?><p>Relation <?=e($ref['id'])?> → <?=e($ref['target']??'Référence invalide')?> (sans double comptage).</p><?php endforeach;?>
<?php foreach($snapshot['notes']??[] as $note):?><p>Note Étape <?=(int)$note['etape']?> · <?=e($note['created_at']??'')?> — <?=e($note['contenu']??'')?></p><?php endforeach;?>
<p><strong>Informations à confirmer :</strong></p><ul><?php foreach($historical['missing'] as $m):?><li><?=e($m['subject'])?></li><?php endforeach;?></ul>
</details>
<?php else: ?><p><small>État des sources non conservé pour cette ancienne décision. Les valeurs actuelles ne permettent pas de le reconstituer.</small></p><?php endif; ?>
