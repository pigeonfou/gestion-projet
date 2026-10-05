<?php
require_once __DIR__.'/decision_dashboard.php';

function reportRecipients(string $raw): array {
    $items=preg_split('/[\s,;]+/',trim($raw),-1,PREG_SPLIT_NO_EMPTY);
    $out=[];
    foreach($items as $email) {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Adresse e-mail invalide : '.$email);
        $out[strtolower($email)]=$email;
    }
    return array_values($out);
}
function reportEscape($text): string { return htmlspecialchars((string)$text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function reportEmailHtml(array $project,array $specs,array $notes=[],string $validationUrl=""): string {
    $d=ddConsolidate($specs);
    $esc='reportEscape';
    $cell='padding:9px;border:1px solid #cbd5e1;text-align:left;vertical-align:top;';
    $table=static function(array $heads,array $rows) use($esc,$cell):string {
        $html='<table cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:12px 0;font-size:13px"><tr>';
        foreach($heads as $head) $html.='<th style="'.$cell.'background:#e2f3f6;">'.$esc($head).'</th>';
        $html.='</tr>';
        foreach($rows as $row){$html.='<tr>';foreach($row as $value)$html.='<td style="'.$cell.'">'.$esc($value).'</td>';$html.='</tr>';}
        return $html.'</table>';
    };
    $html='<html><body style="margin:0;background:#f1f5f9;color:#172b40;font-family:Arial,sans-serif"><table width="100%" cellpadding="20"><tr><td><table width="100%" cellpadding="20" cellspacing="0" style="background:#ffffff;border:1px solid #cbd5e1"><tr><td>';
    if($validationUrl!=='' && preg_match('~^https://[^\s]+$~D',$validationUrl)) {
        $html.='<p><a href="'.$esc($validationUrl).'" style="display:inline-block;padding:14px 20px;background:#078c9c;color:#ffffff;text-decoration:none;font-weight:bold;border-radius:6px">Se rendre sur la page de validation du projet : '.$esc($project['nom']??'Projet').'</a></p>';
    }
    $html.='<h1 style="color:#078c9c;font-size:24px">OddWorks · '.$esc($project['nom']??'Projet').'</h1><h2>Étape 3 – GO / NO GO</h2><p>Rapport des données enregistrées des étapes 1 et 2 · '.$esc(date('d/m/Y H:i')).'</p>';
    $html.=$table(['Capacité','Coût estimé','Délai estimé','Risque / Incertitude'],[[
        $d['capacityConfirmed'].' / '.count($d['capacity']).' S.T. confirmées',
        ddCostSummary($d).($d['costIncompleteCount']?' — '.ddCostPrecision($d):'').' · '.($d['qty']===null?'un équipement':$d['qty'].' équipement(s)'),
        ddDelaySummary($d),$d['riskCounts']['Faible'].' Faible · '.$d['riskCounts']['Moyen'].' Moyen · '.$d['riskCounts']['Fort'].' Fort'
    ]]);
    $html.='<h3>Analyse capacité</h3>';
    $capacity=[];foreach($d['capacity'] as $row)$capacity[]=[$row['id'],$row['confirmed']?'Confirmée':'À compléter',implode(', ',$row['missing'])];
    $html.=$table(['S.T.','Confirmation','Champs à compléter'],$capacity);
    $html.='<h3>Analyse des coûts estimés</h3>';
    $html.=$table(['Budget cible du CDC','Coût du périmètre','Solde avant compléments'],[[
        $d['budget']===null?'Non renseigné':ddMoney($d['budget'],$d['targets']['taxe']??'HT'),ddCostSummary($d),$d['margin']===null?'Comparaison impossible':ddMoney($d['margin'])
    ]]);
    if($d['costIncompleteCount'])$html.='<p>'.$esc(ddCostPrecision($d)).'</p>';
    $rows=[];foreach($d['groups'] as $type=>$group)$rows[]=[$type,ddCostSubtotal($group)];
    $html.=$table(['Type · un équipement','Coûts connus'],$rows);
    $rows=[];foreach($d['bySf'] as $sf=>$group)$rows[]=[$sf,ddCostSubtotal($group)];
    $html.=$table(['Sous-fonction · un équipement','Coûts connus'],$rows);
    $html.='<p>Quantités S.T. × coûts unitaires, puis quantité du CDC. Chaque S.T. est comptée une seule fois. Logiciel et 3D sont exclus des achats. Les valeurs inconnues ne constituent pas un résultat favorable.</p><h3>Analyse des délais estimés</h3>';
    $html.=$table(['Objectif CDC','Plus long délai élémentaire','Délai global'],[[ddDays($d['targetDays']),ddDelaySummary($d),'Non déterminé : parallélisation et intégration à préciser']]);
    $rows=[];foreach($d['lines'] as $line)$rows[]=[$line['id'],$line['description']??'',$line['type'],!empty($line['delay_unknown'])?'Inconnu':ddDays($line['delay']),$line['risk']];
    $html.=$table(['S.T.','Description','Type','Délai (jours)','Risque / Incertitude'],$rows);
    $html.='<h3>Risque / Incertitude initial</h3>';
    $rows=[];foreach($d['risks'] as $risk)$rows[]=[$risk['subject'],$risk['level'],$risk['detail'],$risk['action']];
    $html.=$table(['Point','Niveau','Détail','Action'],$rows);
    $html.='<h3>Informations à confirmer</h3>';
    $rows=[];foreach($d['missing'] as $missing)$rows[]=[$missing['subject'],'Étape '.$missing['source'],$missing['action']];
    $html.=$table(['Point','Source','Action'],$rows);
    $html.='<h3>Besoin et contraintes — Étape 1</h3>';
    foreach(['objectifs'=>'Objectifs et contexte','resultats_attendus'=>'Hors périmètre','cas_usage'=>'Contraintes','profils_utilisateurs'=>'Utilisateurs','livrables_attendus'=>'Livrables','delais'=>'Objectifs et planning textuel'] as $key=>$label)$html.='<h4>'.$esc($label).'</h4><p style="white-space:pre-wrap">'.nl2br($esc($specs[$key]??'Non renseigné')).'</p>';
    $html.='<h3>Notes / résultats — Étape 3</h3>';
    foreach($notes as $note)if((int)($note['etape']??0)===3)$html.='<p>'.nl2br($esc($note['contenu']??'')).'</p>';
    $html.='<h3>Synthèse avant décision</h3><p>'.$esc(ddCostSummary($d)).' · délai : '.$esc(ddDelaySummary($d)).' · '.count($d['missing']).' informations à confirmer. La décision GO / NO GO reste humaine.</p>';
    return $html.'</td></tr></table></td></tr></table></body></html>';
}
function reportEmailDraft(string $subject,array $recipients,string $html): string {
    $recipients=reportRecipients(implode(',',$recipients));
    $subject=str_replace(["\r","\n"],' ',$subject);
    $boundary='oddworks-'.bin2hex(random_bytes(16));
    $plain=html_entity_decode(strip_tags(str_replace(['</p>','</tr>','</h1>','</h2>','</h3>','</td>'],["\n","\n","\n","\n","\n"," | "],$html)),ENT_QUOTES|ENT_HTML5,'UTF-8');
    return 'X-Unsent: 1'."\r\n".'To: '.implode(', ',$recipients)."\r\n".'Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n".'Message-ID: <'.bin2hex(random_bytes(16)).'@oddworks.local>'."\r\n".'Date: '.date(DATE_RFC2822)."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n".
        "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($plain)."\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($html)."\r\n--$boundary--\r\n";
}
