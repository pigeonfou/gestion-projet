<?php
/** Pure consolidation; never stores or invents project-specific values. */
function ddNumber($v): ?float {
    if ($v === null || $v === '' || !is_scalar($v)) return null;
    $s = str_replace([',', ' ', "\u{00A0}", "\u{202F}"], ['.', '', '', ''], (string)$v);
    return is_numeric($s) && is_finite((float)$s) && (float)$s >= 0 ? (float)$s : null;
}
function ddTargets(array $post): array {
    $out=[];
    foreach (['budget','quantite','tva','delai','charge'] as $k) {
        $raw=$post['cible_'.$k]??'';
        $n=ddNumber($raw);
        if ($raw!=='' && $n===null) throw new InvalidArgumentException('Cible numérique invalide : '.$k);
        if ($k==='tva' && $n!==null && $n>100) throw new InvalidArgumentException('TVA : 0 à 100 %.');
        if ($k==='quantite' && $n!==null && $n<=0) throw new InvalidArgumentException('Quantité strictement positive.');
        $out[$k]=$n;
    }
    $out['taxe']=($post['cible_taxe']??'HT')==='TTC'?'TTC':'HT';
    $out['perimetre']=trim((string)($post['cible_perimetre']??''));
    if(strlen($out['perimetre'])>1000) throw new InvalidArgumentException('Périmètre : 1 000 caractères maximum.');
    return $out;
}
function ddHasCost(string $type): bool { return in_array($type,['Matériel','Composant','PCB','Prestataire'],true); }
function ddMoney(float $v, string $tax='HT'): string { return number_format($v,2,',',' ').' € '.$tax; }
function ddDays($v): string { return $v===null?'Non renseigné':number_format((float)$v,2,',',' ').' j'; }
function ddConsolidate(array $specs, array $tasks, array $users): array {
    $targets=$specs['decision_cibles']??[];
    $qty=ddNumber($targets['quantite']??null); $vat=ddNumber($targets['tva']??null);
    $sfs=[]; foreach($specs['fonctions']??[] as $sf) $sfs[$sf['id']??'']=$sf;
    $groups=[]; $bySf=[]; $lines=[]; $missing=[]; $risks=[]; $totals=['HT'=>0.0,'TTC'=>0.0];
    $byUser=[]; foreach($users as $u) $byUser[$u['identifiant']]=$u;
    $assigned=[]; $unassigned=[]; $taskBySource=[]; $taskById=[]; $dependencies=[];
    foreach($tasks as $task) {
        $taskById[(int)$task['id']]=$task;
        if(!empty($task['source_key'])) $taskBySource[$task['source_key']]=$task;
        $who=trim((string)($task['assigne_a']??''));
        if($who==='') $unassigned[]=$task; else $assigned[$who][]=$task;
        if(!empty($task['dependance_id'])) $dependencies[]=$task;
    }
    foreach($specs['specs_techniques']??[] as $st) {
        if (($st['type'] ?? '') === 'S.T.x.x') continue;
        $type=(string)($st['type']??'Matériel'); $sid=(string)($st['id']??''); $sf=(string)($st['sf']??'');
        $cost=ddHasCost($type)?ddNumber($st['cout_estime']??null):null;
        $tax=($st['cout_taxe']??'HT')==='TTC'?'TTC':'HT'; $delay=ddNumber($st['delai_jours']??null);
        $task=null; foreach($taskBySource as $key=>$t) if(preg_match('/^st:\d+:'.preg_quote($sid,'/').'$/',$key)) {$task=$t; break;}
        $who=trim((string)($task['assigne_a']??''));
        $groups[$type]??=['HT'=>0.0,'TTC'=>0.0,'count'=>0,'assigned'=>0,'people'=>[]];
        $bySf[$sf]??=['HT'=>0.0,'TTC'=>0.0];
        $groups[$type]['count']++;
        if($who!=='') {$groups[$type]['assigned']++; $groups[$type]['people'][$who]=$byUser[$who]??['identifiant'=>$who];}
        if(ddHasCost($type)) {
            if($cost===null || $cost==0) $missing[]=['subject'=>$sid.' : coût non confirmé (vide ou nul)','source'=>2,'action'=>'Confirmer le coût unitaire ou justifier la gratuité.'];
            if($cost!==null) {$totals[$tax]+=$cost; $groups[$type][$tax]+=$cost; $bySf[$sf][$tax]+=$cost;}
        }
        if($delay===null) $missing[]=['subject'=>$sid.' : délai non renseigné','source'=>2,'action'=>'Obtenir un délai de réalisation ou fournisseur.'];
        if(trim((string)($st['description']??''))==='') $missing[]=['subject'=>$sid.' : description technique absente','source'=>2,'action'=>'Compléter la spécification.'];
        if(!isset($sfs[$sf])) $missing[]=['subject'=>$sid.' : S.F. source absente','source'=>2,'action'=>'Revoir le rattachement fonctionnel.'];
        $lines[]=array_merge($st,['cost'=>$cost,'tax'=>$tax,'delay'=>$delay,'task'=>$task,'who'=>$who]);
    }
    if(!$lines) $missing[]=['subject'=>'Aucune S.T. enregistrée','source'=>2,'action'=>'Définir les études avant décision.'];
    foreach($sfs as $sid=>$sf) if(!isset($bySf[$sid])) $missing[]=['subject'=>$sid.' : aucune S.T. associée','source'=>2,'action'=>'Définir une solution technique.'];
    if($unassigned) $missing[]=['subject'=>count($unassigned).' tâche(s) sans responsable','source'=>'tasks','action'=>'Affecter les tâches identifiées.'];
    if($qty===null) $missing[]=['subject'=>'Quantité du périmètre budgétaire non renseignée','source'=>1,'action'=>'Préciser le nombre d’équipements visé.'];
    $budget=ddNumber($targets['budget']??null);
    if($budget===null) $missing[]=['subject'=>'Budget cible non renseigné — comparaison impossible','source'=>1,'action'=>'Confirmer l’enveloppe financière.'];
    if($totals['TTC']>0 && $vat===null) $missing[]=['subject'=>'TVA de rapprochement HT/TTC non renseignée','source'=>1,'action'=>'Confirmer le taux avant consolidation.'];
    $unitHT=$totals['TTC']==0?$totals['HT']:($vat===null?null:$totals['HT']+$totals['TTC']/(1+$vat/100));
    $lotHT=$qty!==null && $unitHT!==null?round($unitHT*$qty,2):null;
    $budgetHT=$budget===null?null:(($targets['taxe']??'HT')==='HT'?$budget:($vat===null?null:$budget/(1+$vat/100)));
    $margin=$budgetHT!==null && $lotHT!==null?round($budgetHT-$lotHT,2):null;
    $maxDelay=null; foreach($lines as $line) if($line['delay']!==null) $maxDelay=max($maxDelay??0,$line['delay']);
    $targetDays=ddNumber($targets['delai']??null);
    if($targetDays===null) $missing[]=['subject'=>'Objectif de délai non renseigné','source'=>1,'action'=>'Confirmer le délai maximum en jours calendaires.'];
    $missing[]=['subject'=>'Délai global et chemin critique non établis','source'=>'tasks','action'=>'Préciser durées, dates de départ et dépendances ; le plus long délai n’est pas le délai du prototype.'];
    $missing[]=['subject'=>'Disponibilités et charge par ressource non renseignées','source'=>'tasks','action'=>'Valider la disponibilité avec les responsables ; les nombres de tâches ne sont pas des heures.'];
    $risks[]=['subject'=>'Couverture financière partielle','impact'=>'Coût','detail'=>'Les coûts unitaires de l’Étape 2 couvrent les S.T. chiffrées. Banc, assemblage, essais, reprises et réserve ne sont pas automatiquement inclus.','action'=>'Rapprocher cette base avec l’enveloppe complète avant GO.','source'=>2,'level'=>'Vigilance'];
    if($margin!==null && $margin<0) $risks[]=['subject'=>'Dépassement du budget dès les S.T.','impact'=>'Coût','detail'=>ddMoney(-$margin).' au-dessus de la cible, avant les postes complémentaires.','action'=>'Réviser la solution ou le budget.','source'=>1,'level'=>'Critique'];
    if($targetDays!==null && $maxDelay!==null && $maxDelay>$targetDays) $risks[]=['subject'=>'Un délai élémentaire dépasse l’objectif global','impact'=>'Délai','detail'=>ddDays($maxDelay).' contre '.ddDays($targetDays).'.','action'=>'Revoir ce poste et confirmer la base calendaire.','source'=>2,'level'=>'Critique'];
    foreach($groups as $type=>$g) if(count($g['people'])===1) $risks[]=['subject'=>$type.' : une seule ressource affectée','impact'=>'Capacité','detail'=>implode(', ',array_keys($g['people'])).' pour '.$g['count'].' S.T. ; disponibilité non vérifiée.','action'=>'Confirmer disponibilité et solution de relais.','source'=>'tasks','level'=>'À confirmer'];
    $risks[]=['subject'=>'Plus long délai S.T. connu','impact'=>'Délai','detail'=>$maxDelay===null?'Aucun délai connu.':ddDays($maxDelay).' ; pas de seuil arbitraire de retard.','action'=>'Vérifier réception, parallélisation, intégration et essais.','source'=>2,'level'=>'À confirmer'];
    // The current sourcing is a separate scope, never added to the initial estimate.
    $current=['HT'=>0.0,'TTC'=>0.0,'count'=>0,'suppliers'=>[]];
    foreach($lines as $line) if(ddHasCost($line['type']??'')) foreach($specs['composants_st'][$line['id']]??[] as $item) {
        $q=ddNumber($item['quantite']??null);$c=ddNumber($item['cout_unitaire']??null);
        if($q!==null && $c!==null){$tax=($item['cout_unitaire_taxe']??'HT')==='TTC'?'TTC':'HT';$current[$tax]+=round($q*$c,2);$current['count']++;}
        if(!empty($item['fournisseur']))$current['suppliers'][$item['fournisseur']]=true;
    }
    $current['ht']=$current['TTC']==0?$current['HT']:($vat===null?null:round($current['HT']+$current['TTC']/(1+$vat/100),2));
    return compact('targets','qty','vat','sfs','groups','bySf','lines','missing','risks','totals','unitHT','lotHT','budget','budgetHT','margin','maxDelay','targetDays','assigned','unassigned','byUser','taskById','dependencies','current');
}
