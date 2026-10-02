<?php
function taskDependencyCheck(PDO $db, int $taskId, int $projectId, ?int $dependency, string $status): void {
    if (!$dependency) return;
    $q = $db->prepare('SELECT id, projet_id, statut, dependance_id FROM taches WHERE id=?');
    $seen = [$taskId => true];
    $next = $dependency;
    $first = true;
    while ($next) {
        if (isset($seen[$next])) throw new InvalidArgumentException('Dépendance circulaire interdite.');
        $seen[$next] = true;
        $q->execute([$next]); $t = $q->fetch(PDO::FETCH_ASSOC);
        if (!$t || (int)$t['projet_id'] !== $projectId) throw new InvalidArgumentException('La dépendance doit appartenir au même projet.');
        if ($first && in_array($status, ['en_cours','validation','terminee'], true) && $t['statut'] !== 'terminee') {
            throw new InvalidArgumentException('La tâche précédente doit être terminée avant de commencer cette tâche.');
        }
        $first = false;
        $next = (int)($t['dependance_id'] ?? 0);
    }
}

function taskSetStatus(PDO $db, int $id, string $status, array $actor, ?int $projectId = null): void {
    if (!in_array($status, ['a_faire','en_cours','validation','terminee'], true)) throw new InvalidArgumentException('Statut invalide.');
    $db->beginTransaction();
    try {
        $q = $db->prepare('SELECT t.*, p.createur_id FROM taches t JOIN projets p ON p.id=t.projet_id WHERE t.id=?');
        $q->execute([$id]); $t = $q->fetch(PDO::FETCH_ASSOC);
        if (!$t || ($projectId !== null && (int)$t['projet_id'] !== $projectId)) throw new InvalidArgumentException('Tâche introuvable.');
        if (($actor['role'] ?? '') !== 'admin' && ($t['assigne_a'] ?? '') !== ($actor['identifiant'] ?? '') && (int)$t['createur_id'] !== (int)$actor['id']) {
            throw new InvalidArgumentException('Vous ne pouvez modifier que vos tâches.');
        }
        taskDependencyCheck($db, $id, (int)$t['projet_id'], (int)($t['dependance_id'] ?? 0), $status);
        $db->prepare('UPDATE taches SET kanban_status=?, statut=? WHERE id=?')->execute([$status,$status === 'validation' ? 'en_cours' : $status,$id]);
        $db->prepare('INSERT INTO tache_historique(tache_id,utilisateur_id,action,details) VALUES(?,?,?,?)')->execute([$id,$actor['id'],'statut',($t['kanban_status'] ?? $t['statut']).' → '.$status]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
