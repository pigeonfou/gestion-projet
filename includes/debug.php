<?php
/** Debug tools remain admin-only even when the global switch is enabled. */
function debugChangeStep(PDO $db, array $actor, int $id, int $step, string $state, string $reason): void {
    if (($actor['role'] ?? '') !== 'admin') throw new RuntimeException('Mode debug réservé aux administrateurs.');
    $stmt=$db->prepare('SELECT valeur FROM parametres WHERE cle=?'); $stmt->execute(['debug_enabled']);
    if ($stmt->fetchColumn() !== '1') throw new RuntimeException('Le mode debug est désactivé.');
    $reason=trim($reason);
    if ($step<1 || $step>9 || !in_array($state,['en_cours','validee','abandon'],true) || ($state==='abandon' && $step!==3) || $reason==='' || strlen($reason)>5000) {
        throw new InvalidArgumentException('Étape, état ou motif debug invalide (motif obligatoire, 5 000 octets maximum).');
    }
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT current_step,status,go_decision,validated_steps FROM projets WHERE id=?'); $stmt->execute([$id]);
        $before=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) throw new RuntimeException('Projet introuvable.');
        // Selecting an advanced step explicitly validates its prerequisites for debugging.
        $validated=$step>1 ? range(1,$step-1) : [];
        if ($state==='validee') $validated[]=$step;
        $next=$state==='validee' ? min(9,$step+1) : $step;
        $status=$state==='abandon' ? 'archive' : (($state==='validee' && $step>=8) ? 'termine' : 'actif');
        $go=$state==='abandon' ? 'NO_GO' : (($step>3 || ($step===3 && $state==='validee')) ? 'GO' : null);
        $after=['current_step'=>$next,'status'=>$status,'go_decision'=>$go,'validated_steps'=>json_encode($validated)];
        $db->prepare('UPDATE projets SET current_step=?,status=?,go_decision=?,validated_steps=? WHERE id=?')
            ->execute([$next,$status,$go,$after['validated_steps'],$id]);
        $decision=$state==='en_cours' ? 'DEBUG_RESET' : ($state==='abandon' ? 'NO_GO' : ($step===3 ? 'GO' : (in_array($step,[7,8],true) ? 'CONFORME' : 'DONE')));
        $motif='[DEBUG] '.$reason."\nAvant : ".json_encode($before,JSON_UNESCAPED_UNICODE)."\nAprès : ".json_encode($after,JSON_UNESCAPED_UNICODE);
        $db->prepare('INSERT INTO projet_decisions(projet_id,etape,decision,motif,utilisateur_id) VALUES(?,?,?,?,?)')
            ->execute([$id,$step,$decision,$motif,$actor['id']]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
