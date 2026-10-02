<?php
function projectMembersSchema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS projet_contributeurs (projet_id INTEGER NOT NULL REFERENCES projets(id), utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id), actif INTEGER NOT NULL DEFAULT 1, PRIMARY KEY(projet_id,utilisateur_id))');
    $db->exec('CREATE TABLE IF NOT EXISTS projet_contributeurs_historique (id INTEGER PRIMARY KEY AUTOINCREMENT, projet_id INTEGER NOT NULL, utilisateur_id INTEGER NOT NULL, acteur_id INTEGER NOT NULL, actif INTEGER NOT NULL, date_action TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
}
function projectCanManage(PDO $db, int $pid, array $actor): bool {
    $q=$db->prepare('SELECT createur_id FROM projets WHERE id=?');$q->execute([$pid]);$owner=$q->fetchColumn();
    return $owner!==false && ((($actor['role']??'')==='admin') || ((int)$owner===(int)($actor['id']??0) && (int)$owner>0));
}
function projectCanContribute(PDO $db, int $pid, array $actor): bool {
    if (projectCanManage($db,$pid,$actor)) return true;
    if ((int)($actor['id']??0)<=0) return false;
    projectMembersSchema($db);
    $q=$db->prepare('SELECT 1 FROM projet_contributeurs WHERE projet_id=? AND utilisateur_id=? AND actif=1');$q->execute([$pid,(int)$actor['id']]);
    return (bool)$q->fetchColumn();
}
function projectSetContributor(PDO $db, int $pid, int $uid, bool $active, array $actor): void {
    if (!projectCanManage($db,$pid,$actor)) throw new InvalidArgumentException('Seul le créateur ou un administrateur peut gérer les contributeurs.');
    $q=$db->prepare('SELECT id FROM utilisateurs WHERE id=?');$q->execute([$uid]);
    if (!$q->fetchColumn()) throw new InvalidArgumentException('Utilisateur introuvable.');
    projectMembersSchema($db);$db->beginTransaction();
    try {
        $db->prepare('INSERT INTO projet_contributeurs(projet_id,utilisateur_id,actif) VALUES(?,?,?) ON CONFLICT(projet_id,utilisateur_id) DO UPDATE SET actif=excluded.actif')->execute([$pid,$uid,$active?1:0]);
        $db->prepare('INSERT INTO projet_contributeurs_historique(projet_id,utilisateur_id,acteur_id,actif) VALUES(?,?,?,?)')->execute([$pid,$uid,(int)$actor['id'],$active?1:0]);
        $db->commit();
    } catch(Throwable $e) {$db->rollBack();throw $e;}
}
