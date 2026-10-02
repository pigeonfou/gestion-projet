<?php $notesRows=projectNotes($db,$id,true); $activeNotes=array_values(array_filter($notesRows,static fn($n)=>$n['deleted_at']===null)); ?>
<div class="mt-3">
  <label for="notes-summary" class="text-sm font-medium">Notes / résultats</label>
  <textarea id="notes-summary" class="form-control" rows="3" readonly placeholder="Aucune note enregistrée."><?= e(projectNotesText($activeNotes)) ?></textarea>
  <button type="button" class="btn btn-secondary btn-sm mt-1" id="notes-open">Ajouter / gérer les notes</button>
</div>
<?php if($currentStep===1): ?>
<form method="POST" id="form-notes-step1"><input type="hidden" name="action" value="save_notes"><input type="hidden" name="save_cadrage_with_notes" value="1"><?= csrfField() ?><button type="submit" class="btn btn-secondary btn-sm">Enregistrer l’origine et la destination</button></form>
<?php endif; ?>
<dialog id="notes-dialog" aria-labelledby="notes-dialog-title">
  <form method="POST" action="<?= url('projet.php?id='.$id.'&view=processus&step='.$currentStep) ?>" id="note-editor">
    <h3 id="notes-dialog-title">Notes / résultats</h3>
    <?= csrfField() ?><input type="hidden" name="action" value="manage_note"><input type="hidden" name="note_revision" id="note-revision" value="0">
    <label for="note-choice">Note à gérer</label>
    <select id="note-choice" name="note_id" class="form-control"><option value="0">Nouvelle note</option><?php foreach($notesRows as $n): ?><option value="<?= (int)$n['id'] ?>" data-revision="<?= (int)$n['revision'] ?>" data-deleted="<?= $n['deleted_at']!==null?'1':'0' ?>" data-content="<?= e($n['contenu']) ?>"><?= e(($n['deleted_at']!==null?'Supprimée · ':'').($n['created_at']??'Note antérieure'). ' · '.substr($n['contenu'],0,65)) ?></option><?php endforeach; ?></select>
    <label for="note-content">Texte de la note</label><textarea id="note-content" name="note_content" class="form-control" rows="8" maxlength="50000"></textarea>
    <p class="text-sm text-muted">Date serveur (UTC) ajoutée automatiquement. Une note supprimée reste restaurable ici.</p>
    <div class="note-dialog-actions"><button class="btn btn-primary" name="note_action" value="add" id="note-add">Ajouter la note</button><button class="btn btn-primary" name="note_action" value="edit" id="note-edit" hidden>Enregistrer la modification</button><button class="btn btn-danger" name="note_action" value="delete" id="note-delete" hidden>Supprimer cette note</button><button class="btn btn-secondary" name="note_action" value="restore" id="note-restore" hidden>Restaurer cette note</button><button class="btn btn-secondary" type="button" id="notes-close">Fermer</button></div>
  </form>
</dialog>
<script src="<?= url('assets/js/project-notes.js?v=1') ?>" defer></script>
