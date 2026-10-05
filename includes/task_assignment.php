<?php
/** Keep the source of an automatically generated task aligned with manual assignment. Caller owns the transaction. */
function taskSyncAssignmentSource(PDO $db, array $task, string $assignee): void {
    $source=(string)($task['source_key']??'');
    $pid=(int)$task['projet_id'];
    if (!str_starts_with($source,'cp:'.$pid.':')) return;
    $q=$db->prepare('SELECT id,specs_json FROM cahiers WHERE projet_id=?');$q->execute([$pid]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $cahier) {
        $specs=json_decode($cahier['specs_json']??'',true);
        if (!is_array($specs) || !is_array($specs['composants_st']??null)) continue;
        $changed=false;
        foreach ($specs['composants_st'] as $stId=>&$items) {
            if (!is_array($items)) continue;
            foreach ($items as &$item) {
                if (is_array($item) && $source==='cp:'.$pid.':'.$stId.':'.($item['id']??'')) {
                    $item['affectation']=$assignee; $changed=true;
                }
            }
            unset($item);
        }
        unset($items);
        if ($changed) $db->prepare('UPDATE cahiers SET specs_json=? WHERE id=?')->execute([json_encode($specs,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$cahier['id']]);
    }
}

function taskVisibleTo(array $task, array $actor, int $ownerId): bool {
    return ($actor['role']??'')==='admin' || ($ownerId>0 && $ownerId===(int)($actor['id']??0))
        || (($actor['identifiant']??'')!=='' && ($task['assigne_a']??null)===$actor['identifiant']);
}
