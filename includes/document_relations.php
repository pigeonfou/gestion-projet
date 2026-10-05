<?php
/** Explicit document ↔ S.T. metadata; content remains in Nextcloud. */
function pfDocumentLinksSchema(PDO $db): void {
 $db->exec('CREATE TABLE IF NOT EXISTS document_st_links (document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE, projet_id INTEGER NOT NULL REFERENCES projets(id) ON DELETE CASCADE, st_uid TEXT NOT NULL, actor_id INTEGER NOT NULL REFERENCES utilisateurs(id), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(document_id,st_uid))');
}
function pfDocumentLink(PDO $db,int $pid,int $did,string $uid,array $specs,int $actor): void {
 $q=$db->prepare('SELECT 1 FROM documents WHERE id=? AND projet_id=?');$q->execute([$did,$pid]);if(!$q->fetchColumn())throw new InvalidArgumentException('Document du même projet requis.');
 $found=false;foreach($specs['specs_techniques']??[] as $st)if(($st['uid']??'')===$uid)$found=true;
 if(!$found||!preg_match('/^[a-f0-9]{32}$/D',$uid))throw new InvalidArgumentException('Sélectionnez une S.T. existante du projet.');
 $db->prepare('INSERT OR IGNORE INTO document_st_links(document_id,projet_id,st_uid,actor_id) VALUES(?,?,?,?)')->execute([$did,$pid,$uid,$actor]);
}
