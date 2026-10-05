<?php
/** A mail link may only answer a project still waiting for its first GO/NO GO. */
function projectAwaitingValidation(array $project): bool {
    return (int)($project['current_step'] ?? 0) === 3
        && ($project['status'] ?? '') === 'actif'
        && trim((string)($project['go_decision'] ?? '')) === '';
}

function recordProjectValidation(PDO $db, int $id, int $actorId, string $decision, string $motif, string $snapshot): void {
    $motif = trim($motif);
    if (!in_array($decision, ['GO','NO_GO'], true) || strlen($motif) > 10000 || ($decision === 'NO_GO' && $motif === '')) {
        throw new InvalidArgumentException('Choisissez une décision et indiquez un motif pour l’abandon (10 000 octets maximum).');
    }
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM projets WHERE id=?');
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project || !projectAwaitingValidation($project)) throw new RuntimeException('Ce projet ne sollicite plus de décision à l’étape 3.');
        $validated = json_decode($project['validated_steps'] ?? '[]', true);
        if (!is_array($validated) || !$validated) $validated = [1,2];
        if ($decision === 'GO') $validated[] = 3;
        $validated = array_values(array_unique(array_map('intval', $validated))); sort($validated);
        $stmt = $db->prepare("UPDATE projets SET go_decision=?,current_step=?,status=?,validated_steps=? WHERE id=? AND current_step=3 AND status='actif' AND COALESCE(go_decision,'')=''");
        $stmt->execute([$decision, $decision === 'GO' ? 4 : 3, $decision === 'GO' ? 'actif' : 'archive', json_encode($validated), $id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Une décision a déjà été enregistrée. Rechargez la page.');
        $db->prepare('INSERT INTO projet_decisions(projet_id,etape,decision,motif,utilisateur_id,snapshot_json) VALUES(?,3,?,?,?,?)')
            ->execute([$id,$decision,$motif,$actorId,$snapshot]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
