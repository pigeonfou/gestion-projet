<?php
function cdcTestSections(): array {
    return [
        'demande'=>['title'=>'La demande','hint'=>'Identifier le sujet et retrouver l’échange à son origine.','fields'=>[
            'nom'=>['Nom du projet ou de l’équipement','text','','Un nom provisoire suffit.'],
            'porteur'=>['Demandeur / contact','text','','Entreprise, service ou personne à contacter.'],
            'date_redaction'=>['Date de l’échange','date',''],
            'source'=>['Réunion ou mail de référence','text','','Date, participants ou objet du mail.'],
        ]],
        'besoin'=>['title'=>'Le besoin et les usages','hint'=>'Décrire avec vos mots ce que l’équipement doit permettre.','fields'=>[
            'besoin'=>['Quel problème faut-il résoudre ?','textarea','','Situation actuelle, difficulté rencontrée et résultat souhaité.'],
            'utilisateurs'=>['Qui utilisera l’équipement, et comment ?','textarea','','Utilisateur, lieu d’utilisation et déroulement d’un usage courant.'],
            'fonctions'=>['Que doit faire l’équipement ?','textarea','','Une fonction par ligne si cela vous aide. Précisez les performances connues et ce qui est hors périmètre.'],
        ]],
        'contraintes'=>['title'=>'Les contraintes connues','hint'=>'Renseigner seulement ce qui est déjà connu ou imposé.','fields'=>[
            'alimentation'=>['Alimentation et autonomie','textarea','','Secteur, batterie, tension disponible, durée de fonctionnement souhaitée…'],
            'interfaces'=>['Commandes, connexions et échanges','textarea','','Boutons, voyants, écran, capteurs, connecteurs, liaison avec un autre équipement ou logiciel…'],
            'integration'=>['Dimensions, fixation et manipulation','textarea','','Place disponible, poids, boîtier, montage, accès pour l’entretien… Ajoutez les unités aux valeurs.'],
            'environnement'=>['Conditions d’utilisation','textarea','','Intérieur ou extérieur, température, eau, poussière, chocs, vibrations…'],
            'exigences'=>['Autres exigences à respecter','textarea','','Règles du client, sécurité, normes connues, composants imposés ou interdits…'],
        ]],
        'attendus'=>['title'=>'Le résultat attendu','hint'=>'Définir ce qui doit être livré et les limites du projet.','fields'=>[
            'livrables'=>['Ce qui doit être livré et validé','textarea','','Maquette, prototype, équipement, logiciel, plans ou documentation. Comment saura-t-on que le résultat convient ?'],
            'quantites'=>['Quantités envisagées','text','','Prototype(s), petite série, quantité à terme… Une estimation suffit.'],
            'delai'=>['Échéance ou délai souhaité','text','','Date cible, délai indicatif ou contrainte de planning.'],
            'budget'=>['Budget ou coût cible, si connu','text','','Précisez montant, devise et HT/TTC, ainsi que ce que le budget couvre.'],
        ]],
        'suivi'=>['title'=>'Notes et points à préciser','hint'=>'Garder les questions ouvertes et les éléments utiles pour la suite.','fields'=>[
            'questions'=>['Questions / décisions à confirmer','textarea','','Point à clarifier, personne à consulter et prochaine action.'],
            'notes'=>['Notes de réunion, extrait de mail ou documents utiles','textarea','','Collez les passages utiles, les décisions prises ou les références des documents.'],
        ]],
    ];
}

function cdcTestDefaults(): array {
    $data=[];foreach(cdcTestSections() as $s)foreach($s['fields'] as $key=>$field)$data[$key]=$field[2];return $data;
}
function cdcTestValidate(array $input): array {
    $data=[];
    foreach(cdcTestSections() as $s)foreach($s['fields'] as $key=>$field){
        $raw=$input[$key]??'';
        if(!is_string($raw)&&!is_numeric($raw))throw new InvalidArgumentException('Valeur invalide : '.$field[0]);
        $value=trim((string)$raw);
        if(strlen($value)>20000)throw new InvalidArgumentException('Texte trop long : '.$field[0]);
        if(in_array($field[1],['number','integer'],true)&&$value!==''){
            if(!preg_match($field[1]==='integer'?'/^\d+$/':'/^\d+(?:[.,]\d+)?$/',$value))throw new InvalidArgumentException('Montant ou quantité invalide : '.$field[0]);
            $value=str_replace(',','.',$value);
            if((float)$value>1000000000)throw new InvalidArgumentException('Valeur trop élevée : '.$field[0]);
        }
        if($field[1]==='date'&&$value!==''){
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
            if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Date de rédaction invalide.');
        }
        $data[$key]=$value;
    }
    return $data;
}
function cdcTestEnsure(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS cdc_test_forms_generic (projet_id INTEGER PRIMARY KEY, payload TEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 1, updated_at TEXT NOT NULL, updated_by INTEGER NOT NULL)');
}
function cdcTestLoad(PDO $db,int $project): ?array {
    $s=$db->prepare('SELECT * FROM cdc_test_forms_generic WHERE projet_id=?');$s->execute([$project]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;
}
function cdcTestSave(PDO $db,int $project,int $user,int $revision,array $data): void {
    $payload=json_encode(cdcTestValidate($data),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if($revision===0){$s=$db->prepare("INSERT OR IGNORE INTO cdc_test_forms_generic(projet_id,payload,updated_at,updated_by) VALUES(?,?,datetime('now'),?)");$s->execute([$project,$payload,$user]);}
    else{$s=$db->prepare("UPDATE cdc_test_forms_generic SET payload=?,revision=revision+1,updated_at=datetime('now'),updated_by=? WHERE projet_id=? AND revision=?");$s->execute([$payload,$user,$project,$revision]);}
    if($s->rowCount()!==1)throw new RuntimeException('Une autre modification a été enregistrée. Rechargez le formulaire avant de réessayer.');
}
