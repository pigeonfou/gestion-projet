<?php
$pageTitle='Qualité fournisseurs'; $activePage='projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/cahier_specs.php';
require_once __DIR__.'/includes/quality.php';
requerirConnexion();
$pid=(int)($_GET['projet_id']??$_POST['projet_id']??0);
requerirAccesProjet($pid,false); $db=getDB(); qualitySchema($db);
$q=$db->prepare('SELECT id,nom FROM projets WHERE id=?');$q->execute([$pid]);$project=$q->fetch();
if (!$project) { http_response_code(404);exit('Projet introuvable.'); }
$q=$db->prepare('SELECT specs_json FROM cahiers WHERE projet_id=? ORDER BY id LIMIT 1');$q->execute([$pid]);
$specs=json_decode((string)$q->fetchColumn(),true)?:[];
$refs=[];
foreach(($specs['composants_st']??[]) as $items) foreach($items as $item) {
    $ref=trim((string)($item['reference']??''));
    if ($ref!=='') $refs[$ref]=['designation'=>$item['designation']??'', 'fournisseur_attendu'=>$item['fournisseur']??''];
}
$q=$db->prepare('SELECT id,nom_fichier FROM documents WHERE projet_id=? AND chemin_nextcloud IS NOT NULL ORDER BY nom_fichier');$q->execute([$pid]);$docs=$q->fetchAll();
$docIds=array_map('intval',array_column($docs,'id'));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfRequire(); requerirAccesProjet($pid);
    try {
        $action=(string)($_POST['action']??'');
        $doc=(int)($_POST['document_id']??0);
        if ($doc && !in_array($doc,$docIds,true)) throw new InvalidArgumentException('Document externe de ce projet requis.');
        $db->beginTransaction();
        if ($action==='fournisseur') {
            $fid=(int)($_POST['fournisseur_id']??0);$name=trim((string)($_POST['nom']??''));
            if ($name==='' || strlen($name)>200) throw new InvalidArgumentException('Nom fournisseur requis (200 caractères maximum).');
            $v1=qualityCertificate($_POST,'9001');$v2=qualityCertificate($_POST,'14001');
            if (($v1[0]==='oui'||$v2[0]==='oui')&&!$doc) throw new InvalidArgumentException('Une preuve Nextcloud est requise pour un certificat ISO.');
            if ($fid) {
                $q=$db->prepare('SELECT id FROM stock_fournisseurs WHERE id=?');$q->execute([$fid]);
                if (!$q->fetchColumn()) throw new InvalidArgumentException('Fournisseur introuvable.');
                $db->prepare('UPDATE stock_fournisseurs SET nom=? WHERE id=?')->execute([$name,$fid]);
            } else {
                $q=$db->prepare('SELECT id FROM stock_fournisseurs WHERE nom=?');$q->execute([$name]);
                if ($q->fetchColumn()) throw new InvalidArgumentException('Fournisseur existant : utilisez Modifier.');
                $db->prepare('INSERT INTO stock_fournisseurs(nom) VALUES(?)')->execute([$name]);$fid=(int)$db->lastInsertId();
            }
            $values=array_merge([$fid],$v1,$v2,[$doc?:null]);
            $db->prepare('INSERT INTO qualite_fournisseurs(fournisseur_id,iso9001,organisme9001,certificat9001,validite9001,iso14001,organisme14001,certificat14001,validite14001,document_id) VALUES(?,?,?,?,?,?,?,?,?,?) ON CONFLICT(fournisseur_id) DO UPDATE SET iso9001=excluded.iso9001,organisme9001=excluded.organisme9001,certificat9001=excluded.certificat9001,validite9001=excluded.validite9001,iso14001=excluded.iso14001,organisme14001=excluded.organisme14001,certificat14001=excluded.certificat14001,validite14001=excluded.validite14001,document_id=excluded.document_id,updated_at=CURRENT_TIMESTAMP')->execute($values);
            $object='fournisseur:'.$fid;$details=json_encode($values,JSON_UNESCAPED_UNICODE);
        } elseif ($action==='reference' || $action==='alerte') {
            $ref=(string)($_POST['reference']??'');
            if (!isset($refs[$ref])) throw new InvalidArgumentException('Référence absente de la recherche actuelle du projet.');
            if ($action==='reference') {
                $fid=(int)($_POST['fournisseur_id']??0);
                if ($fid) { $q=$db->prepare('SELECT id FROM stock_fournisseurs WHERE id=?');$q->execute([$fid]);if(!$q->fetchColumn()) throw new InvalidArgumentException('Fournisseur introuvable.'); }
                $rohs=(string)($_POST['rohs']??'inconnu');$reach=(string)($_POST['reach']??'inconnu');
                $rp=qualityCoverage($rohs,$_POST['rohs_pct']??'');$ep=qualityCoverage($reach,$_POST['reach_pct']??'');
                $notes=trim((string)($_POST['notes']??''));if(strlen($notes)>10000)throw new InvalidArgumentException('Notes trop longues.');
                $values=[$pid,$ref,$fid?:null,$rohs,$rp,$reach,$ep,$doc?:null,$notes];
                $db->prepare('INSERT INTO qualite_references(projet_id,reference,fournisseur_id,rohs,rohs_pct,reach,reach_pct,document_id,notes) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(projet_id,reference) DO UPDATE SET fournisseur_id=excluded.fournisseur_id,rohs=excluded.rohs,rohs_pct=excluded.rohs_pct,reach=excluded.reach,reach_pct=excluded.reach_pct,document_id=excluded.document_id,notes=excluded.notes,updated_at=CURRENT_TIMESTAMP')->execute($values);
                $details=json_encode($values,JSON_UNESCAPED_UNICODE);
            } else {
                $assignee=(string)($_POST['assigne_a']??'');$q=$db->prepare('SELECT id FROM utilisateurs WHERE identifiant=?');$q->execute([$assignee]);
                if (!$q->fetchColumn()) throw new InvalidArgumentException('Responsable requis.');
                $q=$db->prepare('SELECT * FROM qualite_references WHERE projet_id=? AND reference=?');$q->execute([$pid,$ref]);$row=$q->fetch()?:[];
                $alerts=qualityAlerts($row);if(!$alerts)throw new InvalidArgumentException('Aucune lacune documentaire sur cette référence.');
                $key='qualite:'.$ref;$q=$db->prepare('SELECT id FROM taches WHERE projet_id=? AND source_key=?');$q->execute([$pid,$key]);$task=$q->fetchColumn();
                if (!$task) {
                    $db->prepare("INSERT INTO taches(projet_id,titre,description,priorite,statut,kanban_status,assigne_a,source_key) VALUES(?,?,?,'haute','a_faire','a_faire',?,?)")->execute([$pid,'[Qualité] '.$ref,implode('; ',$alerts).' — obtenir preuves et enregistrer correction avant série.',$assignee,$key]);$task=(int)$db->lastInsertId();
                    $db->prepare('INSERT INTO tache_historique(tache_id,utilisateur_id,action,details) VALUES(?,?,?,?)')->execute([$task,utilisateurCourant()['id'],'creation','Action qualité '.$ref.' affectée à '.$assignee]);
                }
                $db->prepare('INSERT INTO qualite_references(projet_id,reference,action_task_id) VALUES(?,?,?) ON CONFLICT(projet_id,reference) DO UPDATE SET action_task_id=excluded.action_task_id')->execute([$pid,$ref,$task]);
                $details='Action qualité #'.$task;
            }
            $object='reference:'.$ref;
        } else throw new InvalidArgumentException('Action invalide.');
        $db->prepare('INSERT INTO qualite_historique(projet_id,utilisateur_id,objet,details) VALUES(?,?,?,?)')->execute([$pid,utilisateurCourant()['id'],$object,$details]);
        $db->commit();setFlash('success','Qualité enregistrée.');redirect('qualite_projet.php?projet_id='.$pid);
    } catch (InvalidArgumentException $e) {if($db->inTransaction())$db->rollBack();$error=$e->getMessage();}
      catch (Throwable $e) {if($db->inTransaction())$db->rollBack();error_log('Qualité ProjectFlow: '.get_class($e));$error='Échec de sauvegarde; aucune modification confirmée.';}
}
$suppliers=$db->query('SELECT f.*,q.iso9001,q.organisme9001,q.certificat9001,q.validite9001,q.iso14001,q.organisme14001,q.certificat14001,q.validite14001,q.document_id FROM stock_fournisseurs f LEFT JOIN qualite_fournisseurs q ON q.fournisseur_id=f.id ORDER BY f.nom')->fetchAll();
$supplierMap=array_column($suppliers,null,'id');
$q=$db->prepare('SELECT * FROM qualite_references WHERE projet_id=?');$q->execute([$pid]);$stored=array_column($q->fetchAll(),null,'reference');$rows=[];
foreach($refs as $ref=>$base) {$r=array_merge(['reference'=>$ref],$base,$stored[$ref]??[]);$s=$supplierMap[$r['fournisseur_id']??0]??[];foreach(['iso9001','iso14001','validite9001','validite14001'] as $k)$r[$k]=$s[$k]??null;$rows[]=$r;}
$ind=qualityIndicators($rows);
$edit=$supplierMap[(int)($_GET['fournisseur']??0)]??[];
$users=$db->query('SELECT identifiant,nom_affiche FROM utilisateurs ORDER BY identifiant')->fetchAll();
function qualitySelect(string $name,array $choices,$value): void {echo '<select class="form-control" name="'.e($name).'">';foreach($choices as $k=>$label)echo '<option value="'.e((string)$k).'" '.((string)$value===(string)$k?'selected':'').'>'.e($label).'</option>';echo '</select>';}
function qualityDocuments(array $docs,$value):void {$choices=[''=>'Sans preuve'];foreach($docs as $d)$choices[$d['id']]=$d['nom_fichier'];qualitySelect('document_id',$choices,$value);}
require __DIR__.'/includes/header.php';
?>
<div class="page-header"><h1>Qualité fournisseurs — <?=e($project['nom'])?></h1><a class="btn btn-secondary" href="<?=url('projet.php?id='.$pid)?>">Retour au projet</a><a class="btn btn-secondary" href="<?=url('documents_externes.php?projet_id='.$pid)?>">Documents Nextcloud</a></div>
<?php if(isset($error)):?><p class="alert alert-error"><?=e($error)?></p><?php endif;?>
<p>Couche documentaire simplifiée. Les pourcentages décrivent une couverture estimée, ils ne remplacent pas une certification. Les inconnues restent au dénominateur. ISO Oui est compté si la validité couvre la date du jour. Une tâche terminée ne supprime pas une lacune encore présente.</p>
<div class="card" style="padding:1rem"><h2>Indicateurs sur <?= $ind['references']?> références physiques actuelles</h2><?php foreach(['iso9001'=>'Fournisseur ISO 9001','iso14001'=>'Fournisseur ISO 14001','rohs'=>'RoHS conforme','reach'=>'REACH conforme'] as $k=>$label):?><p><?=e($label)?> : <?=$ind[$k]?> / <?=$ind['references']?> (<?=number_format($ind['references']?100*$ind[$k]/$ind['references']:0,1,',',' ')?> %)</p><?php endforeach;?><p>Références avec information inconnue : <?=$ind['inconnues']?>. Non conformes : <?=$ind['non_conformes']?>. Références avec alertes : <?=$ind['alertes']?>.</p></div>
<h2>Fournisseurs du stock et preuves ISO</h2><table class="table"><tr><th>Fournisseur</th><th>ISO 9001</th><th>ISO 14001</th><th></th></tr><?php foreach($suppliers as $s):?><tr><td><?=e($s['nom'])?></td><?php foreach(['9001','14001'] as $n):?><td><?=e($s['iso'.$n]??'inconnu')?> — <?=e($s['certificat'.$n]??'')?> — <?=e($s['validite'.$n]??'')?></td><?php endforeach;?><td><a href="<?=url('qualite_projet.php?projet_id='.$pid.'&fournisseur='.$s['id'])?>">Modifier</a></td></tr><?php endforeach;?></table>
<form id="supplierForm" class="card" style="padding:1rem" method="post"><?=csrfField()?><input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="fournisseur"><input type="hidden" name="fournisseur_id" value="<?=(int)($edit['id']??0)?>"><h3><?=$edit?'Modifier':'Créer'?> un fournisseur</h3><label>Nom fournisseur<input class="form-control" name="nom" required maxlength="200" value="<?=e($edit['nom']??'')?>"></label>
<?php foreach(['9001','14001'] as $n):?><fieldset><legend>ISO <?=$n?></legend><label>Statut ISO <?=$n?><?php qualitySelect('iso'.$n,['inconnu'=>'Non renseigné','oui'=>'Oui','non'=>'Non'],$edit['iso'.$n]??'inconnu');?></label><label>Organisme <?=$n?><input class="form-control" name="organisme<?=$n?>" value="<?=e($edit['organisme'.$n]??'')?>"></label><label>Numéro certificat <?=$n?><input class="form-control" name="certificat<?=$n?>" value="<?=e($edit['certificat'.$n]??'')?>"></label><label>Validité <?=$n?><input class="form-control" type="date" name="validite<?=$n?>" value="<?=e($edit['validite'.$n]??'')?>"></label></fieldset><?php endforeach;?>
<label>Preuve externe ISO<?php qualityDocuments($docs,$edit['document_id']??'');?></label><button class="btn btn-primary">Enregistrer le fournisseur</button></form>
<h2>Conformité des références de l’étape 4</h2>
<?php foreach($rows as $r):$ref=$r['reference'];$alerts=qualityAlerts($r);?>
<section class="card" style="padding:1rem;margin:1rem 0"><h3><?=e($ref.' — '.$r['designation'])?></h3><p>Fournisseur déclaré en recherche : <?=e($r['fournisseur_attendu'])?></p>
<?php if($alerts):?><p class="alert alert-error">Alertes : <?=e(implode('; ',$alerts))?></p><?php else:?><p>Preuves RoHS et REACH complètes.</p><?php endif;?>
<form class="qualityRefForm" method="post"><?=csrfField()?><input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="reference"><input type="hidden" name="reference" value="<?=e($ref)?>"><label>Fournisseur lié<?php $choices=[''=>'Non relié'];foreach($suppliers as $s)$choices[$s['id']]=$s['nom'];qualitySelect('fournisseur_id',$choices,$r['fournisseur_id']??'');?></label>
<?php foreach(['rohs'=>'RoHS','reach'=>'REACH'] as $k=>$label):?><label>Statut <?=$label?><?php qualitySelect($k,['inconnu'=>'Non renseigné','conforme'=>'Conforme','non_conforme'=>'Non conforme','partiel'=>'Partiellement conforme'],$r[$k]??'inconnu');?></label><label>Couverture <?=$label?> (%)<input class="form-control" type="number" min="0" max="100" step="0.01" name="<?=$k?>_pct" value="<?=e(isset($r[$k.'_pct'])?(string)$r[$k.'_pct']:'')?>"></label><?php endforeach;?>
<label>Preuve externe conformité<?php qualityDocuments($docs,$r['document_id']??'');?></label><label>Notes et décision / date métier<textarea class="form-control" name="notes" maxlength="10000"><?=e($r['notes']??'')?></textarea></label><button class="btn btn-primary">Enregistrer <?=e($ref)?></button></form>
<?php if(!empty($r['action_task_id'])):?><p>Action corrective : <a href="<?=url('tache.php?id='.$r['action_task_id'])?>">Tâche #<?=(int)$r['action_task_id']?></a></p><?php elseif($alerts):?><form class="qualityAlertForm" method="post"><?=csrfField()?><input type="hidden" name="projet_id" value="<?=$pid?>"><input type="hidden" name="action" value="alerte"><input type="hidden" name="reference" value="<?=e($ref)?>"><label>Responsable de l’action<?php $choices=[''=>'Choisir'];foreach($users as $u)$choices[$u['identifiant']]=$u['identifiant'].' — '.$u['nom_affiche'];qualitySelect('assigne_a',$choices,'');?></label><button class="btn btn-secondary">Créer l’action corrective <?=e($ref)?></button></form><?php endif;?></section>
<?php endforeach;?>
<?php $replaced=array_diff_key($stored,$refs);if($replaced):?>
<h2>Références remplacées — hors indicateurs actuels</h2>
<p>Le changement de référence ne clôture pas ses actions et ne supprime pas ses preuves. Les références actuelles doivent recevoir leurs propres déclarations.</p>
<table class="table"><tr><th>Référence retirée</th><th>RoHS / REACH</th><th>Notes conservées</th><th>Action corrective</th></tr>
<?php foreach($replaced as $ref=>$old):?><tr><td><?=e($ref)?></td><td><?=e($old['rohs'].' / '.$old['reach'])?></td><td><?=e($old['notes'])?></td><td><?php if($old['action_task_id']):?><a href="<?=url('tache.php?id='.$old['action_task_id'])?>">Tâche #<?=(int)$old['action_task_id']?></a><?php else:?>Aucune<?php endif;?></td></tr><?php endforeach;?></table>
<?php endif;?>
<h2>Historique qualité</h2><?php $q=$db->prepare('SELECT h.*,u.identifiant FROM qualite_historique h JOIN utilisateurs u ON u.id=h.utilisateur_id WHERE h.projet_id=? ORDER BY h.id DESC LIMIT 50');$q->execute([$pid]);foreach($q->fetchAll() as $h):?><p><?=e($h['date_action'].' — '.$h['identifiant'].' — '.$h['objet'])?><br><?=e($h['details'])?></p><?php endforeach;?>
<?php require __DIR__.'/includes/footer.php';?>
