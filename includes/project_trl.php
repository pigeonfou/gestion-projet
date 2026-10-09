<?php
require_once __DIR__.'/project_members.php';

function trlLevels(): array {
    return [1=>'Principes scientifiques observés',2=>'Concept technologique formulé',3=>'Preuve de concept expérimentale',4=>'Technologie validée en laboratoire',5=>'Technologie validée en environnement représentatif',6=>'Prototype démontré en environnement représentatif',7=>'Prototype démontré en environnement opérationnel',8=>'Système complet et qualifié',9=>'Système éprouvé en exploitation réelle'];
}
function trlSchema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS projet_trl (projet_id INTEGER PRIMARY KEY REFERENCES projets(id) ON DELETE CASCADE, entree INTEGER, actuel INTEGER, cible INTEGER, preuves TEXT NOT NULL DEFAULT \'\', revision INTEGER NOT NULL DEFAULT 0, updated_at TEXT, acteur_id INTEGER REFERENCES utilisateurs(id))');
    $db->exec('CREATE TABLE IF NOT EXISTS projet_trl_historique (id INTEGER PRIMARY KEY AUTOINCREMENT, projet_id INTEGER NOT NULL REFERENCES projets(id) ON DELETE CASCADE, acteur_id INTEGER NOT NULL REFERENCES utilisateurs(id), avant_json TEXT NOT NULL, apres_json TEXT NOT NULL, date_action TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
}
function trlLoad(PDO $db, int $id): array {
    trlSchema($db);
    $q=$db->prepare('SELECT * FROM projet_trl WHERE projet_id=?');$q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: ['projet_id'=>$id,'entree'=>null,'actuel'=>null,'cible'=>null,'preuves'=>'','revision'=>0,'updated_at'=>null,'acteur_id'=>null];
}
function trlLevel($value): ?int {
    if ($value==='' || $value===null) return null;
    if (!is_scalar($value) || !preg_match('/^[1-9]$/D',(string)$value)) throw new InvalidArgumentException('Chaque niveau TRL doit être compris entre 1 et 9, ou rester non évalué.');
    return (int)$value;
}
function trlSave(PDO $db, int $id, array $actor, array $input): void {
    if (!projectCanManage($db,$id,$actor)) throw new InvalidArgumentException('Seul le pilote du projet ou un administrateur peut modifier le TRL.');
    $values=[];foreach(['entree','actuel','cible'] as $key)$values[$key]=trlLevel($input[$key]??null);
    $proofs=$input['preuves']??'';
    if (!is_string($proofs) || strlen($proofs)>10000) throw new InvalidArgumentException('Les preuves et le contexte sont limités à 10 000 octets.');
    $proofs=trim($proofs);
    if ($values['actuel']!==null && $proofs==='') throw new InvalidArgumentException('Précisez les preuves et l’environnement d’essai pour évaluer le TRL actuel.');
    $rev=$input['revision']??null;
    if (!is_scalar($rev) || !ctype_digit((string)$rev)) throw new InvalidArgumentException('Révision TRL invalide. Rechargez la page.');
    trlSchema($db);$db->beginTransaction();
    try {
        $before=trlLoad($db,$id);
        if ((int)$before['revision']!==(int)$rev) throw new InvalidArgumentException('Le TRL a été modifié depuis votre ouverture. Rechargez la page avant de réessayer.');
        $db->prepare('INSERT INTO projet_trl(projet_id,entree,actuel,cible,preuves,revision,updated_at,acteur_id) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,?) ON CONFLICT(projet_id) DO UPDATE SET entree=excluded.entree,actuel=excluded.actuel,cible=excluded.cible,preuves=excluded.preuves,revision=excluded.revision,updated_at=excluded.updated_at,acteur_id=excluded.acteur_id')
            ->execute([$id,$values['entree'],$values['actuel'],$values['cible'],$proofs,(int)$rev+1,$actor['id']]);
        $after=trlLoad($db,$id);
        $db->prepare('INSERT INTO projet_trl_historique(projet_id,acteur_id,avant_json,apres_json) VALUES(?,?,?,?)')->execute([$id,$actor['id'],json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $db->commit();
    } catch(Throwable $error) {if($db->inTransaction())$db->rollBack();throw $error;}
}
