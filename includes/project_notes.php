<?php
function initProjectNotes(PDO $db, int $project): void {
    $db->exec('CREATE TABLE IF NOT EXISTS project_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, projet_id INTEGER NOT NULL, etape INTEGER NOT NULL DEFAULT 0, contenu TEXT NOT NULL, created_at TEXT, updated_at TEXT, deleted_at TEXT, revision INTEGER NOT NULL DEFAULT 1)');
    $db->exec('CREATE INDEX IF NOT EXISTS project_notes_project ON project_notes(projet_id,id)');
    $s=$db->prepare("INSERT INTO project_notes(projet_id,etape,contenu) SELECT id,0,step_notes FROM projets WHERE id=? AND trim(COALESCE(step_notes,''))<>'' AND NOT EXISTS(SELECT 1 FROM project_notes WHERE projet_id=?)");
    $s->execute([$project,$project]);
}
function projectNotes(PDO $db,int $project,bool $deleted=false): array {
    $s=$db->prepare('SELECT * FROM project_notes WHERE projet_id=?'.($deleted?'':' AND deleted_at IS NULL').' ORDER BY id');$s->execute([$project]);return $s->fetchAll(PDO::FETCH_ASSOC);
}
function projectNotesText(array $rows): string {
    return implode("\n\n",array_map(static fn($n)=>($n['created_at'] ? '['.$n['created_at'].' · Étape '.$n['etape'].']' : '[Note antérieure · date inconnue]').($n['updated_at']?' · modifiée le '.$n['updated_at']:'')."\n".$n['contenu'],$rows));
}
function changeProjectNote(PDO $db,int $project,int $step,string $action,string $text,int $note=0,int $revision=0): void {
    $text=trim($text);
    if(in_array($action,['add','edit'],true)&&($text===''||strlen($text)>50000)) throw new InvalidArgumentException('Saisissez une note de 1 à 50 000 caractères maximum.');
    if(!in_array($action,['add','edit','delete','restore'],true)) throw new InvalidArgumentException('Action de note invalide.');
    $db->beginTransaction();
    try {
        if($action==='add') {$s=$db->prepare("INSERT INTO project_notes(projet_id,etape,contenu,created_at) VALUES(?,?,?,datetime('now'))");$s->execute([$project,$step,$text]);}
        else {
            $sql=match($action){'edit'=>"contenu=?,updated_at=datetime('now')",'delete'=>"deleted_at=datetime('now')",'restore'=>'deleted_at=NULL'};
            $s=$db->prepare('UPDATE project_notes SET '.$sql.',revision=revision+1 WHERE id=? AND projet_id=? AND revision=? AND deleted_at IS '.($action==='restore'?'NOT NULL':'NULL'));
            $args=$action==='edit'?[$text,$note,$project,$revision]:[$note,$project,$revision];$s->execute($args);
            if($s->rowCount()!==1) throw new RuntimeException('La note a changé ou n’est plus disponible. Rechargez avant de réessayer.');
        }
        $db->prepare('UPDATE projets SET step_notes=? WHERE id=?')->execute([projectNotesText(projectNotes($db,$project)),$project]);
        $db->commit();
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
