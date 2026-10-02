<?php
/** Calendar dates only: creation/result timestamps are not planned starts. */
function ddGanttDate($value): ?DateTimeImmutable {
    if (!is_string($value) || $value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}
function ddGantt(array $tasks): array {
    $rows = []; $undated = []; $min = null; $max = null;
    foreach ($tasks as $task) {
        $start = ddGanttDate($task['date_debut'] ?? null);
        $end = ddGanttDate($task['date_echeance'] ?? null);
        if (!$end || ($start && $start > $end)) { $undated[] = $task; continue; }
        $row = ['task'=>$task, 'start'=>$start ?? $end, 'end'=>$end, 'milestone'=>!$start];
        $rows[] = $row;
        $min = $min === null || $row['start'] < $min ? $row['start'] : $min;
        $max = $max === null || $end > $max ? $end : $max;
    }
    usort($rows, static fn($a,$b)=>($a['start'] <=> $b['start']) ?: ((int)$a['task']['id'] <=> (int)$b['task']['id']));
    $days = $min ? (int)$min->diff($max)->days + 1 : 0;
    foreach ($rows as &$row) {
        $row['left'] = 100 * (int)$min->diff($row['start'])->days / $days;
        $row['width'] = 100 * ((int)$row['start']->diff($row['end'])->days + 1) / $days;
    }
    unset($row);
    $ticks = [];
    if ($min) {
        $step = max(1, (int)ceil($days / 10));
        for ($i=0; $i<$days; $i+=$step) $ticks[]=['left'=>100*$i/$days,'label'=>$min->modify('+'.$i.' days')->format('d/m/Y')];
    }
    return compact('rows','undated','min','max','days','ticks');
}

/** Prefer the task source; legacy generated tasks carry their S.T. in the title. */
function ddGanttTaskLabel(array $task): string {
    $label = '#'.(int)$task['id'];
    foreach (['source_key','titre'] as $field) {
        if (preg_match('/(?<![A-Za-z0-9])S\.T\.(\d+)\.(\d+)(?![\d.])/', (string)($task[$field] ?? ''), $match)) return $label.' · '.$match[0];
    }
    return $label;
}
