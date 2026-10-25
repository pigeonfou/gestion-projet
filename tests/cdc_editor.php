<?php
require __DIR__.'/../includes/cahier_specs.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE projets(id INTEGER PRIMARY KEY);INSERT INTO projets VALUES(1)");
function e($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function csrfField(): string { return '<input type="hidden" name="_csrf" value="test">'; }
function editorCheck(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
$projet_id=1;$returnView='processus';$returnStep=1;$cdcEmbedded=false;$cdcInline=true;
$s=emptySpecs();$s['objectifs']='<script>unsafe</script>';$s['fonctions']=[['id'=>'S.F.1','description'=>'Mesurer','indicateur'=>'Obligatoire']];
$jalonsList=[['nom'=>'Premier prototype','date_prevue'=>'2026-11-12']];
$revision=cdcEditorRevision($s,$jalonsList);
editorCheck(hash_equals($revision,cdcEditorRevision($s,$jalonsList)),'Stable revision');
$changed=$s;$changed['objectifs']='Nouveau besoin';
editorCheck(!hash_equals($revision,cdcEditorRevision($changed,$jalonsList)),'Changed content detected');
editorCheck(!hash_equals($revision,cdcEditorRevision($s,[])),'Changed milestones detected');
ob_start();require __DIR__.'/../includes/views/cdc_editor.php';$html=ob_get_clean();
editorCheck(substr_count($html,'class="cdc-step-panel')===4,'Four CDC tabs');
editorCheck(str_contains($html,'Historique des validations')&&str_contains($html,'Aucune demande de validation'),'Validation history included');
editorCheck(!str_contains($html,'id="btnPrev"')&&!str_contains($html,'id="btnNext"'),'No bottom previous/next controls');
editorCheck(str_contains($html,'&lt;script&gt;unsafe&lt;/script&gt;')&&!str_contains($html,'<script>unsafe'),'Context escaped');
editorCheck(str_contains($html,'Mesurer')&&str_contains($html,'Premier prototype'),'Editable specifications and planning preserved');
editorCheck(!str_contains($html,'id="saveCdcDraft"')&&str_contains($html,'data-inline="1"'),'Inline actions live in review panel');
editorCheck(str_contains($html,'id="cdcValidation-request"')&&str_contains($html,'id="cdcValidation-import"'),'Separate validation dialogs');
editorCheck(str_contains($html,'name="cdc_revision" value="'.$revision.'"'),'Concurrency token in form');
$cdcInline=false;ob_start();require __DIR__.'/../includes/views/cdc_editor.php';$standalone=ob_get_clean();
editorCheck(str_contains($standalone,'id="saveCdcDraft"')&&str_contains($standalone,'id="generateCdcPdf"'),'Standalone CDC remains usable');
echo "CDC editor: shared tabs, escaping, history, separate dialogs, revision and standalone actions OK\n";
