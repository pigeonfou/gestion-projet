<?php
require_once __DIR__.'/project_members.php';

/** Définitions de l’annexe AID/DGA, « Niveaux de maturité technologique », p. 9. */
function trlLevels(): array {
    return [
        3 => 'Démonstration analytique et expérimentale de la faisabilité des fonctions et/ou des caractéristiques critiques',
        4 => 'Validation au stade des composants et/ou des maquettes fonctionnelles en environnement laboratoire',
        5 => 'Validation en environnement représentatif au stade des composants et/ou maquettes fonctionnelles',
        6 => 'Démonstration système ou sous-systèmes à l’aide d’un modèle ou prototype en environnement très représentatif',
        7 => 'Démonstration d’un prototype du système en environnement opérationnel',
    ];
}
function trlGroups(): array {
    return [
        'ri' => ['code'=>'RI', 'title'=>'Recherche industrielle', 'range'=>'TRL 3 à 5', 'levels'=>[3,4,5],
            'description'=>'Établir la faisabilité, puis valider les composants et les maquettes fonctionnelles.',
            'environment'=>'De la faisabilité au laboratoire, puis à un environnement représentatif.'],
        'de' => ['code'=>'DE', 'title'=>'Développement expérimental', 'range'=>'TRL 6 à 7', 'levels'=>[6,7],
            'description'=>'Démontrer le système ou ses sous-systèmes au moyen d’un modèle ou d’un prototype.',
            'environment'=>'D’un environnement très représentatif à un environnement opérationnel.'],
    ];
}
function trlDisplayLabel($value, string $empty = 'Non évalué'): string {
    if ($value === null || $value === '') return $empty;
    return isset(trlLevels()[(int)$value]) ? 'TRL ' . (int)$value : 'À réévaluer';
}
function trlSchema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS projet_trl (projet_id INTEGER PRIMARY KEY REFERENCES projets(id) ON DELETE CASCADE, entree INTEGER, actuel INTEGER, cible INTEGER, preuves TEXT NOT NULL DEFAULT \'\', revision INTEGER NOT NULL DEFAULT 0, updated_at TEXT, acteur_id INTEGER REFERENCES utilisateurs(id))');
    $db->exec('CREATE TABLE IF NOT EXISTS projet_trl_historique (id INTEGER PRIMARY KEY AUTOINCREMENT, projet_id INTEGER NOT NULL REFERENCES projets(id) ON DELETE CASCADE, acteur_id INTEGER NOT NULL REFERENCES utilisateurs(id), avant_json TEXT NOT NULL, apres_json TEXT NOT NULL, date_action TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
}
function trlLoad(PDO $db, int $id): array {
    trlSchema($db);
    $q=$db->prepare('SELECT * FROM projet_trl WHERE projet_id=?');$q->execute([$id]);
    $state = $q->fetch(PDO::FETCH_ASSOC) ?: ['projet_id'=>$id,'entree'=>null,'actuel'=>null,'cible'=>null,'preuves'=>'','revision'=>0,'updated_at'=>null,'acteur_id'=>null];
    // Preserve stored values until an explicit reassessment; never invent a replacement level.
    $state['_excluded_levels'] = [];
    foreach (['entree', 'actuel', 'cible'] as $key) {
        if ($state[$key] !== null && !isset(trlLevels()[(int)$state[$key]])) {
            $state['_excluded_levels'][$key] = $state[$key];
            $state[$key] = null;
        }
    }
    return $state;
}
function trlLevel($value): ?int {
    if ($value==='' || $value===null) return null;
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[3-7]$/D',(string)$value)) throw new InvalidArgumentException('Choisissez un niveau TRL de 3 à 7, ou laissez le niveau non évalué.');
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
