<?php
require_once __DIR__.'/Transaction.php';
/** A durable intent survives a successful remote upload followed by a SQL failure. */
function pfDocumentIntent(PDO $db,array $metadata): int {
    $q=$db->prepare('INSERT INTO document_operations(projet_id,remote_path,metadata_json) VALUES(?,?,?)');
    $q->execute([$metadata['projet_id'],$metadata['chemin_nextcloud'],json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    return (int)$db->lastInsertId();
}
function pfDocumentComplete(PDO $db,int $operation): int {
    return pfTransaction($db,function () use ($db,$operation) {
        $q=$db->prepare('SELECT * FROM document_operations WHERE id=?');$q->execute([$operation]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row || !in_array($row['statut'],['uploaded','confirmed'],true)) throw new InvalidArgumentException('Transfert documentaire non confirmé.');
        $m=json_decode($row['metadata_json'],true,512,JSON_THROW_ON_ERROR);
        $q=$db->prepare('SELECT id FROM documents WHERE projet_id=? AND chemin_nextcloud=?');$q->execute([$m['projet_id'],$m['chemin_nextcloud']]);$id=$q->fetchColumn();
        if (!$id) {
            $db->prepare('INSERT INTO documents(projet_id,phase,nom_fichier,chemin_nextcloud,taille,mime,uploader_id) VALUES(?,?,?,?,?,?,?)')->execute([$m['projet_id'],$m['phase'],$m['nom_fichier'],$m['chemin_nextcloud'],$m['taille'],$m['mime'],$m['uploader_id']]);
            $id=$db->lastInsertId();
        }
        $db->prepare("UPDATE document_operations SET statut='confirmed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$operation]);
        return (int)$id;
    });
}
function pfDocumentUploaded(PDO $db,int $operation): void {
    $db->prepare("UPDATE document_operations SET statut='uploaded',updated_at=CURRENT_TIMESTAMP WHERE id=? AND statut='pending'")->execute([$operation]);
}
function pfDocumentPath(int $pid,string $folder,string $name,?string $legacyProjectName=null): string {
    if($pid<=0||$folder===''||str_contains($folder,'/')||str_contains($folder,chr(92))||in_array($folder,['.','..'],true)||$name===''||in_array($name,['.','..'],true)||str_contains($name,'/')||str_contains($name,chr(92))||preg_match('/[\x00-\x1f\x7f]/',$name)) throw new InvalidArgumentException('Chemin documentaire invalide.');
    $project='Projet_'.$pid.($legacyProjectName===null?'':'_'.sanitizePathSegment($legacyProjectName));
    return trim(getSetting('nextcloud_root','ProjectFlow'),'/').'/'.$project.'/'.$folder.'/'.$name;
}
