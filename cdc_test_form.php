<?php
$pageTitle='CDC-Test-Form';$activePage='projets';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/cdc_test.php';
requerirConnexion();
$db=getDB();$user=utilisateurCourant();$id=(int)($_GET['projet_id']??$_POST['projet_id']??0);
$s=$db->prepare('SELECT id,nom FROM projets WHERE id=?');$s->execute([$id]);$project=$s->fetch();
if(!$project){setFlash('error','Projet introuvable.');redirect('projets.php');}
cdcTestEnsure($db);$saved=cdcTestLoad($db,$id);$revision=(int)($saved['revision']??0);
$data=array_merge(cdcTestDefaults(),$saved?json_decode($saved['payload'],true,512,JSON_THROW_ON_ERROR):[]);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfRequire();requerirGestionProjet($id);
    try{
        if(!is_array($_POST['cdc']??null)) throw new InvalidArgumentException('Formulaire invalide.');
        foreach(cdcTestDefaults() as $key=>$unused) {
            if(isset($_POST['cdc'][$key]) && is_scalar($_POST['cdc'][$key])) $data[$key]=(string)$_POST['cdc'][$key];
        }
        $data=cdcTestValidate($_POST['cdc']);
        cdcTestSave($db,$id,(int)$user['id'],(int)($_POST['revision']??0),$data);
        setFlash('success','CDC-Test-Form enregistré.');redirect('cdc_test_form.php?projet_id='.$id);
    }catch(InvalidArgumentException|RuntimeException $e){$error=$e->getMessage();$revision=(int)($_POST['revision']??0);}
}
$sections=cdcTestSections();require __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="<?= url('assets/css/cdc-test.css?v=night-3') ?>">
<div class="cdc-test-page">
  <div class="cdc-test-heading"><div><span class="cdc-test-badge">FORMULAIRE D’ESSAI</span><h1>CDC-Test-Form</h1><p>Projet associé : <strong><?= e($project['nom']) ?></strong></p></div><a class="btn btn-secondary" href="<?= url('projet.php?id='.$id.'&view=processus&step=1') ?>">Retour au projet</a></div>
  <p class="cdc-test-notice">Équipement électronique · À compléter en réunion ou à partir d’un mail. Tous les champs sont facultatifs : notez ce qui est connu, laissez le reste vide et complétez au fil des échanges.</p>
  <?php if($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="POST" action="<?= url('cdc_test_form.php?projet_id='.$id) ?>" id="cdc-test-form">
    <?= csrfField() ?><input type="hidden" name="projet_id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= $revision ?>">
    <div class="cdc-test-toolbar"><button type="submit" class="btn btn-primary">Enregistrer le formulaire</button><span id="cdc-save-state" role="status"><?= $saved?'Enregistré le '.e($saved['updated_at']).' (UTC) · Version '.$revision:'Brouillon vide · non enregistré' ?></span></div>
    <div class="cdc-test-layout"><nav class="cdc-test-nav" aria-label="Sections du cahier des charges"><?php $n=0;foreach($sections as $key=>$section):$n++; ?><a href="#cdc-section-<?= e($key) ?>"><?= $n ?>. <?= e($section['title']) ?></a><?php endforeach; ?></nav>
    <div class="cdc-test-sections"><?php $n=0;foreach($sections as $key=>$section):$n++; ?>
      <section class="cdc-test-section" id="cdc-section-<?= e($key) ?>"><header><span class="cdc-section-number"><?= $n ?></span><div><h2><?= e($section['title']) ?></h2><p><?= e($section['hint']) ?></p></div></header>
      <div class="cdc-test-fields"><?php foreach($section['fields'] as $fieldKey=>$field):$type=$field[1];$value=$data[$fieldKey]??''; ?>
        <div class="cdc-test-field <?= $type==='textarea'?'cdc-wide':'' ?>"><label for="cdc-<?= e($fieldKey) ?>"><?= e($field[0]) ?></label>
        <?php if($type==='textarea'): ?><textarea class="form-control" id="cdc-<?= e($fieldKey) ?>" name="cdc[<?= e($fieldKey) ?>]" rows="<?= in_array($fieldKey,['fonctions','inclus','nettoyage'],true)?5:3 ?>" maxlength="20000" placeholder="<?= e($field[3]??'') ?>"><?= e($value) ?></textarea>
        <?php else: ?><input class="form-control" id="cdc-<?= e($fieldKey) ?>" name="cdc[<?= e($fieldKey) ?>]" type="<?= in_array($type,['number','integer'],true)?'number':e($type) ?>" value="<?= e($value) ?>"  <?= in_array($type,['number','integer'],true)?'min="0" max="1000000000" step="'.($type==='integer'?'1':'0.01').'"':'maxlength="20000"' ?> placeholder="<?= e($field[3]??'') ?>"><?php endif; ?>
        </div><?php endforeach; ?></div></section>
    <?php endforeach; ?></div></div>
  </form>
</div>
<script src="<?= url('assets/js/cdc-test.js?v=1') ?>" defer></script>
<?php require __DIR__.'/includes/footer.php'; ?>
