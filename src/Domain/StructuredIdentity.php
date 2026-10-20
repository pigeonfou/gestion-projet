<?php
function pfIdentity($value, ?string $seed = null): string {
    if (is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value)) return $value;
    return $seed === null ? bin2hex(random_bytes(16)) : substr(hash('sha256', $seed), 0, 32);
}

/** Preserve legacy component JSON; its identity registry is stored separately. */
function pfComponentIdentities(array $data): array {
    $registry = $data['component_identities'] ?? [];
    foreach ($data['specs_techniques'] ?? [] as $st) {
        $uid = $st['uid'];
        foreach ($data['composants_st'][$st['id']] ?? [] as $item) {
            $id = $item['id'] ?? '';
            if ($id !== '') $registry[$uid][$id] = pfIdentity($item['uid'] ?? ($registry[$uid][$id] ?? null), $uid . ':' . $id);
        }
    }
    return $registry;
}

function pfBackfillTaskIdentities(PDO $db, int $pid, array $data): void {
    $stById = array_column($data['specs_techniques'] ?? [], null, 'id');
    $registry = pfComponentIdentities($data);
    $q = $db->prepare('SELECT id,source_key FROM taches WHERE projet_id=? AND source_uid IS NULL');
    $q->execute([$pid]);
    $update = $db->prepare('UPDATE taches SET source_uid=? WHERE id=? AND source_uid IS NULL');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $task) {
        if (!preg_match('/^(st|cp|achat):'.preg_quote((string)$pid,'/').':(S\.T\.\d+\.\d+)(?::(.+))?$/D', $task['source_key'] ?? '', $m)) continue;
        $st = $stById[$m[2]] ?? null;
        if (!$st) continue;
        $uid = $m[1] . ':' . $st['uid'];
        if ($m[1] !== 'st') {
            $item = $registry[$st['uid']][$m[3] ?? ''] ?? null;
            if (!$item) continue;
            $uid .= ':' . $item;
        }
        // A duplicate legacy key is ambiguous: refuse to silently choose an owner.
        $update->execute([$uid, $task['id']]);
    }
}

/** Rebuild display-keyed compatibility data by immutable identities. */
function pfRemapSpecifications(PDO $db, int $cid, array $old, array $newTechniques): array {
    $q = $db->prepare('SELECT projet_id FROM cahiers WHERE id=?'); $q->execute([$cid]);
    $pid = (int)$q->fetchColumn();
    pfBackfillTaskIdentities($db, $pid, $old);
    $newByUid = array_column($newTechniques, null, 'uid');
    $registry = pfComponentIdentities($old);
    $components = [];
    // Legacy imports may contain refined rows before any technical identity exists.
    $oldById = array_column($old['specs_techniques'] ?? [], null, 'id');
    $newById = array_column($newTechniques, null, 'id');
    foreach ($old['composants_st'] ?? [] as $id => $items) {
        if (!isset($oldById[$id]) && isset($newById[$id])) $components[$id] = $items;
    }
    foreach ($old['specs_techniques'] ?? [] as $st) {
        $next = $newByUid[$st['uid']] ?? null;
        if ($next && isset($old['composants_st'][$st['id']])) $components[$next['id']] = $old['composants_st'][$st['id']];
    }
    $q = $db->prepare('SELECT id,source_key,source_uid FROM taches WHERE projet_id=? AND source_uid IS NOT NULL');
    $q->execute([$pid]);
    $update = $db->prepare('UPDATE taches SET source_key=? WHERE id=?');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $task) {
        $parts = explode(':', $task['source_uid']);
        $st = $newByUid[$parts[1] ?? ''] ?? null;
        if (!$st) { $update->execute(['removed:'.$task['id'], $task['id']]); continue; }
        $key = $parts[0].':'.$pid.':'.$st['id'];
        if (isset($parts[2])) {
            $itemId = array_search($parts[2], $registry[$st['uid']] ?? [], true);
            if ($itemId === false) { $update->execute(['removed:'.$task['id'], $task['id']]); continue; }
            $key .= ':'.$itemId;
        }
        $update->execute([$key, $task['id']]);
    }
    $old['composants_st'] = $components;
    $old['component_identities'] = $registry;
    return $old;
}

function specsRevision(int $cid): string {
    $q=getDB()->prepare('SELECT specs_json FROM cahiers WHERE id=?');$q->execute([$cid]);
    return hash('sha256', (string)$q->fetchColumn());
}
function pfAssertSpecsRevision(int $cid, $expected): void {
    if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected) || !hash_equals(specsRevision($cid),$expected)) {
        throw new InvalidArgumentException('Le cahier a changé. Rechargez la page avant de réessayer ; aucune donnée n’a été remplacée.');
    }
}

function pfProjectRevision(array $project): string {
    unset($project['createur']);
    ksort($project);
    return hash('sha256',json_encode($project,JSON_THROW_ON_ERROR));
}

function pfRemapFunctions(PDO $db, int $cid, array $previous, array $next): array {
    $newSf = array_column($next['fonctions'], null, 'uid');
    if (count($newSf) !== count($next['fonctions'])) throw new InvalidArgumentException('Identifiant S.F. dupliqué.');
    $oldSf = array_column($previous['fonctions'] ?? [], null, 'id');
    $techniques = $previous['specs_techniques'] ?? [];
    $counts=[];
    foreach ($techniques as &$st) {
        $old = $oldSf[$st['sf']] ?? null;
        $new = $old ? ($newSf[$old['uid']] ?? null) : null;
        if ($old && !$new) throw new InvalidArgumentException('Supprimez ou déplacez les S.T. avant de supprimer leur S.F. ; aucune donnée n’a été effacée.');
        if (!$new) continue;
        $st['sf'] = $new['id'];
        $n = substr($new['id'],4);
        $counts[$n] = ($counts[$n] ?? 0)+1;
        $st['id'] = 'S.T.'.$n.'.'.$counts[$n];
    }
    unset($st);
    $remapped = pfRemapSpecifications($db,$cid,$previous,$techniques);
    $next['specs_techniques']=$techniques;
    $next['composants_st']=$remapped['composants_st'];
    $next['component_identities']=$remapped['component_identities'];
    return $next;
}

function pfValidateComponents(array $data, array $components): array {
    $stById=array_column($data['specs_techniques'] ?? [],null,'id');
    $registry=pfComponentIdentities($data);
    $seen=[];
    foreach ($components as $stId=>$items) {
        $st=$stById[$stId] ?? null;
        if (!$st) throw new InvalidArgumentException('S.T. inconnue ; rechargez la page.');
        $updated=[];
        foreach ($items as $item) {
            $uid=pfIdentity($item['uid'] ?? null,$st['uid'].':'.$item['id']);
            if (isset($seen[$uid])) throw new InvalidArgumentException('Identifiant de composant dupliqué.');
            $seen[$uid]=true;
            foreach ($registry as $owner=>$ids) {
                if ($owner!==$st['uid'] && in_array($uid,$ids,true)) throw new InvalidArgumentException('Composant rattaché à une autre S.T.');
            }
            $updated[$item['id']]=$uid;
        }
        $registry[$st['uid']]=$updated;
    }
    $data['component_identities']=$registry;
    foreach ($stById as $id => $st) {
        if (!array_key_exists($id, $components)) $data['component_identities'][$st['uid']] = [];
    }
    return $data;
}
