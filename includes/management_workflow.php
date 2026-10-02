<?php
/** Pilotage léger : les fichiers restent dans Nextcloud, seule leur référence est conservée. */
function managementDefinition(string $type): array {
    $definitions = [
        'nc' => ['table'=>'non_conformites','title'=>'Non-conformité','label'=>'reference','result'=>'verification_efficacite','closed'=>['cloturee'],'statuses'=>['ouverte','en_cours','validation','cloturee']],
        'action' => ['table'=>'actions_qualite','title'=>'Action qualité','label'=>'description','result'=>'verification_efficacite','closed'=>['cloturee'],'statuses'=>['ouverte','en_cours','verification','cloturee']],
        'risque' => ['table'=>'risques_opportunites','title'=>'Risque / opportunité','label'=>'description','result'=>'efficacite','closed'=>['maitrise','clos'],'statuses'=>['ouvert','maitrise','clos']],
        'document' => ['table'=>'documents_controles','title'=>'Document maîtrisé','label'=>'reference','result'=>'description','closed'=>['approuve'],'statuses'=>['brouillon','en_revue','approuve','obsolete']],
    ];
    if (!isset($definitions[$type])) throw new InvalidArgumentException('Type de fiche inconnu.');
    return $definitions[$type];
}
function managementSchema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS management_historique (id INTEGER PRIMARY KEY AUTOINCREMENT,type TEXT NOT NULL,fiche_id INTEGER NOT NULL,utilisateur_id INTEGER NOT NULL,date_action DATETIME DEFAULT CURRENT_TIMESTAMP,date_metier TEXT NOT NULL,document_externe_id INTEGER,avant TEXT NOT NULL,apres TEXT NOT NULL,FOREIGN KEY(document_externe_id) REFERENCES documents(id))');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_management_fiche ON management_historique(type,fiche_id,id)');
}
function managementRead(PDO $db, string $type, int $id): array {
    $definition=managementDefinition($type);
    $stmt=$db->prepare('SELECT * FROM '.$definition['table'].' WHERE id=?');$stmt->execute([$id]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Fiche introuvable.');
    return $row;
}
function managementUpdate(PDO $db, string $type, int $id, array $data, int $userId): void {
    $definition=managementDefinition($type);
    $status=(string)($data['statut']??'');$date=(string)($data['date_metier']??'');
    $result=trim((string)($data['resultat']??''));$proof=(int)($data['document_externe_id']??0)?:null;
    if (!in_array($status,$definition['statuses'],true)) throw new InvalidArgumentException('Statut non autorisé.');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Date métier invalide.');
    if ($result==='') throw new InvalidArgumentException('Renseignez le résultat ou la vérification d’efficacité.');
    $db->beginTransaction();
    try {
        $before=managementRead($db,$type,$id);
        if ($proof) {
            $stmt=$db->prepare('SELECT projet_id FROM documents WHERE id=?');$stmt->execute([$proof]);$document=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$document || empty($before['projet_id']) || (int)$document['projet_id']!==(int)$before['projet_id']) throw new InvalidArgumentException('La preuve Nextcloud doit appartenir au projet de la fiche.');
        }
        $closed=in_array($status,$definition['closed'],true);
        if ($closed && !empty($before['projet_id']) && !$proof) throw new InvalidArgumentException('Une preuve Nextcloud du projet est requise pour approuver ou clore la fiche.');
        $fields=['statut'=>$status,$definition['result']=>$result];
        if ($type==='nc') {
            $fields['cause']=trim((string)($data['cause']??''));$fields['action_corrective']=trim((string)($data['action_corrective']??''));
            if ($closed && ($fields['cause']==='' || $fields['action_corrective']==='')) throw new InvalidArgumentException('La clôture exige la cause et l’action corrective.');
            $fields['date_cloture']=$closed?$date:null;
        } elseif ($type==='action') $fields['date_cloture']=$closed?$date:null;
        elseif ($type==='document') {
            $fields['approbateur']=trim((string)($data['approbateur']??''));
            if ($closed && $fields['approbateur']==='') throw new InvalidArgumentException('Indiquez l’approbateur.');
            $fields['date_approbation']=$closed?$date:null;
        }
        $sql='UPDATE '.$definition['table'].' SET '.implode(',',array_map(fn($field)=>$field.'=?',array_keys($fields)));
        if (in_array($type,['action','risque'],true)) $sql.=',updated_at=CURRENT_TIMESTAMP';
        $stmt=$db->prepare($sql.' WHERE id=?');$stmt->execute([...array_values($fields),$id]);
        $after=managementRead($db,$type,$id);
        $stmt=$db->prepare('INSERT INTO management_historique(type,fiche_id,utilisateur_id,date_metier,document_externe_id,avant,apres) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$type,$id,$userId,$date,$proof,json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
}
