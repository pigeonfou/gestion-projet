<?php
/** Pure consolidation; never stores or invents project-specific values. */
function ddNumber($v): ?float {
    if ($v === null || $v === '' || !is_scalar($v)) return null;
    $s = str_replace([',', ' ', "\u{00A0}", "\u{202F}"], ['.', '', '', ''], (string)$v);
    return is_numeric($s) && is_finite((float)$s) && (float)$s >= 0 ? (float)$s : null;
}
function ddExplicitUnknown(array $st, string $field): bool {
    $flag = $field === 'cout_unitaire' ? 'cout_unitaire_inconnu' : 'delai_inconnu';
    $raw = $st[$field] ?? null;
    if (!empty($st[$flag])) return true;
    if (!is_scalar($raw)) return false;
    $text = strtolower(str_replace(',', '.', trim((string)$raw)));
    return in_array($text, ['inconnue','inconnu'], true) || (is_numeric($text) && (float)$text < 0);
}
function ddCostSubtotal(array $group): string {
    $text = ddMoney($group['HT']).($group['TTC'] > 0 ? ' + '.ddMoney($group['TTC'], 'TTC') : '');
    return !empty($group['unknown']) ? 'Sous-total connu : '.$text.' · '.$group['unknown'].' coût(s) inconnu(s)' : $text;
}
function ddCostSummary(array $d): string {
    if (!empty($d['costIncompleteCount'])) return ddCostSubtotal($d['totals']);
    return $d['lotHT'] === null ? ddCostSubtotal($d['totals']) : ddMoney($d['lotHT']);
}
function ddCostPrecision(array $d): string {
    if (empty($d['costIncompleteCount'])) return '';
    $ids = [];
    foreach ($d['lines'] ?? [] as $line) {
        if (!empty($line['cost_unknown'])) $ids[] = $line['id'];
    }
    return 'Sous-total des éléments renseignés · '.$d['costIncompleteCount'].' coût(s) inconnu(s)'.($ids ? ' : '.implode(', ', $ids) : '').' · estimation incomplète';
}
function ddDelaySummary(array $d): string {
    return !empty($d['delayIncompleteCount']) ? 'Inconnu' : ddDays($d['maxDelay']);
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
/** Fields actually applicable to an ordinary S.T. in step 2; zero is a filled value. */
function ddCapacityMissing(array $st): array {
    $missing=[];
    foreach(['sf'=>'S.F.','description'=>'Description technique','type'=>'Type'] as $key=>$label)
        if(trim((string)($st[$key]??''))==='') $missing[]=$label;
    if(!in_array($st['type']??'', ['Matériel','Composant','Prestataire','Logiciel','3D','PCB'],true)) $missing[]='Type valide';
    if(!in_array($st['variation']??'', ['Faible','Moyen','Fort','Forte'],true)) $missing[]='Risque / Incertitude';
    if(ddExplicitUnknown($st,'delai_jours') || ddNumber($st['delai_jours']??null)===null) $missing[]=ddExplicitUnknown($st,'delai_jours')?'Délai estimé inconnu':'Délai estimé';
    if(ddHasCost($st['type']??'')) {
        if(ddNumber($st['quantite']??null)===null) $missing[]='Quantité';
        if(ddExplicitUnknown($st,'cout_unitaire') || ddNumber($st['cout_unitaire']??null)===null) $missing[]=ddExplicitUnknown($st,'cout_unitaire')?'Coût unitaire inconnu':'Coût unitaire';
        if(!in_array($st['cout_taxe']??'', ['HT','TTC'],true)) $missing[]='HT/TTC';
    }
    return $missing;
}
function ddMoney(float $v, string $tax='HT'): string { return number_format($v,2,',',' ').' € '.$tax; }
function ddDays($v): string { return $v===null?'Non renseigné':number_format((float)$v,2,',',' ').' j'; }
/** Explicit allow-list: future fields never enter calculations or historical snapshots. */
function ddUpstream(array $specs): array {
    return array_intersect_key($specs, array_flip(['objectifs','resultats_attendus','cas_usage','profils_utilisateurs','fonctions','specs_techniques','delais','livrables_attendus','decision_cibles']));
}
function ddSnapshot(array $specs, array $project, array $notes = []): string {
    return json_encode(['version'=>1,'analysis'=>ddConsolidate($specs),'sources'=>ddUpstream($specs),'notes'=>array_values(array_filter($notes,static fn($n)=>(int)($n['etape']??0)>=1 && (int)$n['etape']<=2 && empty($n['deleted_at']))),'project'=>array_intersect_key($project,array_flip(['nom','cadrage_commerciale','cadrage_technique','cadrage_destination']))], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
}
function ddConsolidate(array $specs, array $unusedTasks = [], array $unusedUsers = []): array {
    $specs=ddUpstream($specs);
    $targets=$specs['decision_cibles']??[];
    $qty=ddNumber($targets['quantite']??null); $vat=ddNumber($targets['tva']??null);
    $sfs=[]; foreach($specs['fonctions']??[] as $sf) $sfs[$sf['id']??'']=$sf;
    $capacity=[]; $capacityGroups=[]; $bySf=[]; $groups=[]; $lines=[]; $missing=[]; $risks=[]; $references=[];
    $riskCounts=['Faible'=>0,'Moyen'=>0,'Fort'=>0,'Non renseigné'=>0];
    $totals=['HT'=>0.0,'TTC'=>0.0]; $covered=[];
    $costIncompleteCount=0; $delayIncompleteCount=0;
    $byUid=[]; foreach($specs['specs_techniques']??[] as $st) if(!empty($st['uid'])) $byUid[$st['uid']]=$st;
    foreach($specs['specs_techniques']??[] as $st) {
        $sid=(string)($st['id']??''); $sf=(string)($st['sf']??''); $type=(string)($st['type']??'');
        if($type==='S.T.x.x') {
            $seen=[]; $target=$st;
            while(($target['type']??'')==='S.T.x.x') {
                $ref=$target['reference_uid']??'';
                if(!$ref || isset($seen[$ref]) || !isset($byUid[$ref])) {$target=null;break;}
                $seen[$ref]=true; $target=$byUid[$ref];
            }
            $fields=$target?ddCapacityMissing($target):['Référence S.T. valide'];
            if(trim((string)($st['description']??''))==='') $fields[]='Description technique';
            if(!isset($sfs[$sf])) $fields[]='S.F. source';
            $capacity[]=['id'=>$sid,'confirmed'=>!$fields,'missing'=>$fields];
            $references[]=['id'=>$sid,'target'=>$target['id']??null,'sf'=>$sf,'confirmed'=>!$fields,'missing'=>$fields];
            if($target) $covered[$sf]=true;
            else $missing[]=['subject'=>$sid.' : référence absente ou cyclique','source'=>2,'action'=>'Corriger la référence S.T.'];
            continue;
        }
        $covered[$sf]=true;
        $fields=ddCapacityMissing($st);
        if(!isset($sfs[$sf])) $fields[]='S.F. source';
        $confirmed=!$fields;
        $capacity[]=['id'=>$sid,'confirmed'=>$confirmed,'missing'=>$fields];
        $capacityGroups[$type]??=['confirmed'=>0,'count'=>0];
        $capacityGroups[$type]['count']++;
        if($confirmed) $capacityGroups[$type]['confirmed']++;
        else $missing[]=['subject'=>$sid.' : capacité à confirmer','source'=>2,'action'=>'Compléter : '.implode(', ', $fields).'.'];
        $costUnknown=ddHasCost($type) && ddExplicitUnknown($st,'cout_unitaire');
        $delayUnknown=ddExplicitUnknown($st,'delai_jours');
        $cost=ddHasCost($type) && !$costUnknown?ddNumber($st['cout_estime']??null):null;
        $tax=($st['cout_taxe']??'HT')==='TTC'?'TTC':'HT'; $delay=$delayUnknown?null:ddNumber($st['delai_jours']??null);
        $risk=$st['variation']??''; if($risk==='Forte')$risk='Fort';
        if(!in_array($risk,['Faible','Moyen','Fort'],true))$risk='Non renseigné';
        $riskCounts[$risk]++;
        $groups[$type]??=['HT'=>0.0,'TTC'=>0.0,'count'=>0,'unknown'=>0]; $groups[$type]['count']++;
        $bySf[$sf]??=['HT'=>0.0,'TTC'=>0.0,'unknown'=>0];
        if(ddHasCost($type)) {
            if($cost===null) { $costIncompleteCount++; $groups[$type]['unknown']++; $bySf[$sf]['unknown']++; }
            if($cost===null || $cost==0) $missing[]=['subject'=>$sid.($costUnknown?' : coût unitaire inconnu':' : coût estimé absent ou nul'),'source'=>2,'action'=>$costUnknown?'Obtenir une estimation : le coût inconnu est exclu du sous-total connu.':'Renseigner l’estimation ou confirmer la gratuité.'];
            if(ddNumber($st['quantite']??null)===null || ddNumber($st['quantite'])<=0) $missing[]=['subject'=>$sid.' : quantité absente ou nulle','source'=>2,'action'=>'Préciser la quantité prévue dans l’estimation.'];
            if($cost!==null) {$totals[$tax]+=$cost; $groups[$type][$tax]+=$cost; $bySf[$sf][$tax]+=$cost;}
        }
        if($delay===null) { $delayIncompleteCount++; $missing[]=['subject'=>$sid.($delayUnknown?' : délai estimé inconnu':' : délai estimé non renseigné'),'source'=>2,'action'=>'Estimer le délai de cet élément.']; }
        if($risk==='Non renseigné') $missing[]=['subject'=>$sid.' : risque / incertitude non renseigné','source'=>2,'action'=>'Qualifier l’incertitude initiale.'];
        if(trim((string)($st['description']??''))==='') $missing[]=['subject'=>$sid.' : description technique absente','source'=>2,'action'=>'Compléter la spécification.'];
        if(!isset($sfs[$sf])) $missing[]=['subject'=>$sid.' : S.F. source absente','source'=>2,'action'=>'Revoir le rattachement fonctionnel.'];
        $lines[]=array_merge($st,['cost'=>$cost,'tax'=>$tax,'delay'=>$delay,'risk'=>$risk,'capacity_confirmed'=>$confirmed,'capacity_missing'=>$fields,'cost_unknown'=>$costUnknown,'delay_unknown'=>$delayUnknown]);
        if($risk==='Fort') $risks[]=['subject'=>$sid.' · '.($st['description']??''),'impact'=>'Risque / Incertitude','detail'=>'Fort — qualification initiale de l’Étape 2.','source'=>2,'action'=>'Clarifier ce point avant de décider.','level'=>'Fort'];
    }
    if(!$lines) $missing[]=['subject'=>'Aucune S.T. économique ou technique enregistrée','source'=>2,'action'=>'Définir les études avant décision.'];
    foreach($sfs as $sid=>$sf) if(!isset($covered[$sid])) $missing[]=['subject'=>$sid.' : aucune S.T. associée','source'=>2,'action'=>'Définir la solution pour cette exigence.'];
    $capacityConfirmed=count(array_filter($capacity,static fn($line)=>$line['confirmed']));
    $capacityComplete=count($capacity)>0 && $capacityConfirmed===count($capacity);
    $budget=ddNumber($targets['budget']??null);
    if($qty===null || $qty<=0) {$qty=null;$missing[]=['subject'=>'Quantité du périmètre budgétaire non renseignée','source'=>1,'action'=>'Préciser le nombre d’équipements visé.'];}
    if($budget===null) $missing[]=['subject'=>'Budget cible non renseigné — comparaison impossible','source'=>1,'action'=>'Confirmer l’enveloppe financière.'];
    if($totals['TTC']>0 && $vat===null) $missing[]=['subject'=>'TVA de rapprochement HT/TTC non renseignée','source'=>1,'action'=>'Confirmer le taux avant comparaison.'];
    $unitHT=$totals['TTC']==0?$totals['HT']:($vat===null?null:$totals['HT']+$totals['TTC']/(1+$vat/100));
    if($costIncompleteCount>0) $unitHT=null;
    $lotHT=$qty!==null && $unitHT!==null?round($unitHT*$qty,2):null;
    $budgetHT=$budget===null?null:(($targets['taxe']??'HT')==='HT'?$budget:($vat===null?null:$budget/(1+$vat/100)));
    $margin=$budgetHT!==null && $lotHT!==null?round($budgetHT-$lotHT,2):null;
    $maxDelay=null; foreach($lines as $line) if($line['delay']!==null) $maxDelay=max($maxDelay??0,$line['delay']);
    $targetDays=ddNumber($targets['delai']??null);
    if($targetDays===null) $missing[]=['subject'=>'Objectif de délai non renseigné','source'=>1,'action'=>'Confirmer le délai maximum.'];
    $missing[]=['subject'=>'Délai global non déterminé','source'=>2,'action'=>'Le plus long délai élémentaire ne constitue pas le délai global : parallélisation et intégration à préciser.'];
    if($margin!==null && $margin<0) $risks[]=['subject'=>'Dépassement estimé du budget','impact'=>'Coût','detail'=>ddMoney(-$margin).' au-dessus de la cible.','action'=>'Revoir le besoin, le périmètre ou l’estimation.','source'=>1,'level'=>'Critique'];
    if($targetDays!==null && $maxDelay!==null && $maxDelay>$targetDays) $risks[]=['subject'=>'Un délai estimé dépasse l’objectif','impact'=>'Délai','detail'=>ddDays($maxDelay).' contre '.ddDays($targetDays).'.','action'=>'Revoir les estimations des S.T. concernées.','source'=>2,'level'=>'Critique'];
    return compact('capacity','capacityGroups','capacityConfirmed','capacityComplete','targets','qty','vat','sfs','groups','bySf','lines','missing','risks','riskCounts','references','totals','unitHT','lotHT','budget','budgetHT','margin','maxDelay','targetDays','costIncompleteCount','delayIncompleteCount');
}
