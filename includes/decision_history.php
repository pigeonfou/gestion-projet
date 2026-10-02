<?php
/** Read-only, project-scoped decision history. */
function decisionHistoryFilters(array $input): array
{
    $text = static fn(string $key): string => is_scalar($input[$key] ?? '') ? trim((string)($input[$key] ?? '')) : '';
    $date = static function (string $value): string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value ? $value : '';
    };
    $step = (int)$text('h_step');
    $decision = $text('h_decision');
    $sort = $text('h_sort');
    $size = (int)$text('h_size');
    return [
        'h_q' => substr($text('h_q'), 0, 500),
        'h_step' => $step >= 1 && $step <= 9 ? $step : 0,
        'h_decision' => in_array($decision, ['DONE','REFUSE','GO','NO_GO','CONFORME','NON_CONFORME'], true) ? $decision : '',
        'h_actor' => max(0, (int)$text('h_actor')),
        'h_from' => $date($text('h_from')),
        'h_to' => $date($text('h_to')),
        'h_sort' => in_array($sort, ['date','step','decision','actor'], true) ? $sort : 'date',
        'h_order' => $text('h_order') === 'asc' ? 'asc' : 'desc',
        'h_page' => max(1, (int)$text('h_page')),
        'h_size' => in_array($size, [25,50,100], true) ? $size : 25,
    ];
}
function loadDecisionHistory(PDO $db, int $projectId, array $input): array
{
    $filters = decisionHistoryFilters($input);
    $where = ['d.projet_id = ?']; $params = [$projectId];
    foreach (['h_step'=>'d.etape', 'h_decision'=>'d.decision', 'h_actor'=>'d.utilisateur_id'] as $key=>$column) {
        if ($filters[$key] !== '' && $filters[$key] !== 0) { $where[] = "$column = ?"; $params[] = $filters[$key]; }
    }
    if ($filters['h_from']) { $where[] = 'd.date_decision >= ?'; $params[] = $filters['h_from'].' 00:00:00'; }
    if ($filters['h_to']) {
        $where[] = 'd.date_decision < ?';
        $params[] = (new DateTimeImmutable($filters['h_to']))->modify('+1 day')->format('Y-m-d').' 00:00:00';
    }
    if ($filters['h_q'] !== '') {
        $where[] = "(d.motif LIKE ? ESCAPE '\\' OR d.decision LIKE ? ESCAPE '\\' OR u.identifiant LIKE ? ESCAPE '\\')";
        $query = '%'.strtr($filters['h_q'], ['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']).'%';
        array_push($params, $query, $query, $query);
    }
    $from = ' FROM projet_decisions d LEFT JOIN utilisateurs u ON u.id=d.utilisateur_id WHERE '.implode(' AND ', $where);
    $stmt=$db->prepare('SELECT COUNT(*)'.$from); $stmt->execute($params); $count=(int)$stmt->fetchColumn();
    $pages=max(1,(int)ceil($count/$filters['h_size'])); $filters['h_page']=min($filters['h_page'],$pages);
    $sort=['date'=>'d.date_decision','step'=>'d.etape','decision'=>'d.decision','actor'=>"COALESCE(u.identifiant, '')"][$filters['h_sort']];
    $order=$filters['h_order']==='asc'?'ASC':'DESC';
    $offset=($filters['h_page']-1)*$filters['h_size'];
    $stmt=$db->prepare('SELECT d.*, u.identifiant'.$from." ORDER BY $sort $order, d.id $order LIMIT ".$filters['h_size'].' OFFSET '.$offset);
    $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$db->prepare('SELECT DISTINCT d.utilisateur_id, u.identifiant FROM projet_decisions d LEFT JOIN utilisateurs u ON u.id=d.utilisateur_id WHERE d.projet_id=? ORDER BY u.identifiant');
    $stmt->execute([$projectId]); $actors=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$db->prepare('SELECT COUNT(*) FROM projet_decisions WHERE projet_id=?');$stmt->execute([$projectId]);
    return ['filters'=>$filters,'rows'=>$rows,'count'=>$count,'total'=>(int)$stmt->fetchColumn(),'pages'=>$pages,'actors'=>$actors];
}
