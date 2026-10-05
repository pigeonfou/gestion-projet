<?php
require_once __DIR__.'/../includes/report_email.php';
function checkReport(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
checkReport(reportRecipients("a@example.org; b@example.org\nA@example.org")===['A@example.org','b@example.org'],'Addresses validated and deduplicated');
foreach(['bad','a@example.org\r\nBcc:evil@example.org'] as $bad){try{reportRecipients($bad);throw new RuntimeException('Invalid address accepted');}catch(InvalidArgumentException $expected){}}
$s=['decision_cibles'=>['quantite'=>3],'specs_techniques'=>[['id'=>'S.T.1.1','sf'=>'S.F.1','type'=>'Matériel','description'=>'<script>alert(1)</script>','cout_estime'=>100,'cout_taxe'=>'HT','delai_jours'=>4]]];
$html=reportEmailHtml(['nom'=>'Projet <test>'],$s,[['etape'=>3,'contenu'=>'Réserve du GO']]);
 $linked=reportEmailHtml(['nom'=>'Projet <test>'],$s,[],'https://projet.pigeonfou.com/gestion-projet/validation_projet.php?id=12');
checkReport(str_contains($linked,'href="https://projet.pigeonfou.com/gestion-projet/validation_projet.php?id=12"') && str_contains($linked,'Se rendre sur la page de validation du projet : Projet &lt;test&gt;') && strpos($linked,'validation_projet.php') < strpos($linked,'<h1'),'Validation link at start with escaped project name');
checkReport(!str_contains(reportEmailHtml(['nom'=>'Test'],$s,[],'javascript:alert(1)'),'href='),'Unsafe link rejected');
checkReport(str_contains($html,'300,00') && str_contains($html,'Réserve du GO') && !str_contains($html,'<script>'),'Current quantity, notes and escaped content');
$eml=reportEmailDraft("Rapport\r\nBcc: evil",['a@example.org'],$html);
checkReport(str_starts_with($eml,"X-Unsent: 1\r\nTo: a@example.org\r\n") && str_contains($eml,'multipart/alternative') && str_contains($eml,'Content-Type: text/html; charset=UTF-8') && !str_contains($eml,"\r\nBcc:"),'Editable multipart HTML draft with safe headers');
checkReport(str_contains(quoted_printable_decode($eml),'300,00'),'Encoded report retains costs');
echo "Report email: OK\n";
