<?php
function cdcTestSections(): array {
    return [
        'presentation'=>['title'=>'Présentation du projet et contexte','hint'=>'Définir le besoin et le résultat attendu.','fields'=>[
            'nom'=>['Nom du projet','text','EcoFlask Connect'],
            'porteur'=>['Porteur du projet / client','text','','Nom de votre entreprise ou du client'],
            'date_redaction'=>['Date de rédaction','date',date('Y-m-d')],
            'contexte'=>['Contexte','textarea',"Face à la hausse de la consommation de bouteilles en plastique et à un besoin croissant de suivi d’hydratation, l’entreprise souhaite lancer une gourde réutilisable intégrant un bouchon connecté mesurant l’apport en eau."],
            'objectif'=>['Objectif principal','textarea',"Concevoir, industrialiser et commercialiser une gourde isotherme de 500 ml avec rappel lumineux d’hydratation, étanche, au prix cible de vente de 39 € TTC."],
        ]],
        'perimetre'=>['title'=>'Périmètre du projet','hint'=>'Délimiter les travaux inclus et exclus.','fields'=>[
            'inclus'=>['Inclus','textarea',"Design industriel (ID) et mécanique (MD).\nChoix des matériaux et sourcing des composants.\nDéveloppement du prototype fonctionnel (POC) et outillages de pré-série.\nIntégration électronique basique : capteur de niveau/température et LED.\nTests de certification : normes alimentaires, étanchéité, CE/RoHS."],
            'exclus'=>['Exclus','textarea',"Développement d’une application mobile complète : la gourde fonctionne en mode autonome avec un simple signal lumineux."],
        ]],
        'fonctionnel'=>['title'=>'Spécifications fonctionnelles · Le quoi','hint'=>'Décrire les fonctions, les performances et l’usage.','fields'=>[
            'fonctions'=>['Fonctions d’usage','textarea',"Conserver un liquide chaud jusqu’à 12 h ou froid jusqu’à 24 h.\nPermettre une ouverture et une fermeture rapides d’un seul geste, sans fuite.\nAlerter l’utilisateur toutes les 2 heures par une LED discrète sur le bouchon pour l’inviter à boire."],
            'volume'=>['Contenance (ml)','number','500'],
            'poids'=>['Poids à vide maximal (g)','number','320'],
            'diametre'=>['Diamètre maximal indicatif (mm)','number','72'],
            'chaud'=>['Conservation au chaud (h)','number','12'],
            'froid'=>['Conservation au froid (h)','number','24'],
            'rappel'=>['Intervalle du rappel lumineux (h)','number','2'],
            'ergonomie'=>['Ergonomie et interface','textarea',"Diamètre adapté aux porte-gourdes de vélo et aux porte-gobelets de voiture.\nOuverture et fermeture d’un seul geste. LED discrète sur le bouchon."],
            'nettoyage'=>['Nettoyage et entretien','textarea',"Corps lavable en machine, ou goulot large facilitant le nettoyage à la main.\nBouchon électronique : IPX7 minimal, résistant au lavage rapide à l’éponge ; non submersible en lave-vaisselle."],
            'acceptation'=>['Critères et méthodes de validation','textarea','','Préciser les températures d’essai, la méthode de mesure de l’apport en eau, les conditions de lavage et les seuils d’acceptation.'],
        ]],
        'technique'=>['title'=>'Spécifications techniques et matériaux · Le comment','hint'=>'Décrire la construction, l’électronique et les exigences de conformité.','fields'=>[
            'corps'=>['Corps de la gourde','textarea',"Double paroi sous vide en acier inoxydable 304 (18/8)."],
            'finition'=>['Finition extérieure','textarea',"Revêtement poudre mat (powder coating), anti-rayures."],
            'bouchon'=>['Bouchon et matériaux','textarea',"Plastique sans BPA : polypropylène ou Tritan."],
            'electronique'=>['Électronique et alimentation','textarea',"Capteur de niveau/température.\nBatterie rechargeable par câble magnétique propriétaire.\nTémoin lumineux LED basse consommation."],
            'autonomie'=>['Autonomie minimale (jours)','number','15'],
            'ip'=>['Indice de protection minimal du bouchon','text','IPX7'],
            'reglementation'=>['Normes et réglementations demandées','textarea',"Contact alimentaire : UE n° 1935/2004 et FDA.\nMarquage CE, RoHS et DEEE pour la partie électronique."],
            'chute'=>['Hauteur du test de chute (m)','number','1.20'],
            'essai_chute'=>['Critère du test de chute','textarea',"Chute sur sol béton sans rupture de l’étanchéité."],
            'preuves'=>['Preuves de conformité attendues','textarea','','Rapports d’essais, déclarations fournisseurs et références des documents à fournir.'],
        ]],
        'livrables'=>['title'=>'Livrables attendus du prestataire / bureau d’études','hint'=>'Préciser les éléments à remettre et les conditions de réception.','fields'=>[
            'livrables'=>['Phase 1 · Livrable attendu','textarea',"Production d’une série de validation de 50 exemplaires (prototypes T1/T2)."],
            'quantite_validation'=>['Quantité de validation (exemplaires)','integer','50'],
            'reception'=>['Conditions de réception des livrables','textarea','','Responsable de la validation, essais requis, documents remis et critères d’acceptation.'],
        ]],
        'budget'=>['title'=>'Budget et contraintes financières','hint'=>'Séparer le budget de développement, le coût de revient et le prix de vente.','fields'=>[
            'budget'=>['Enveloppe globale prévisionnelle (HT)','number','45000'],
            'budget_perimetre'=>['Contenu de l’enveloppe','textarea',"Ingénierie, prototypage et outillage ; hors coût unitaire de fabrication de série.\n45 000 € HT est une hypothèse d’exemple à confirmer."],
            'cogs'=>['Coût de revient cible inférieur à (€/unité HT)','number','8.50'],
            'volume_commande'=>['Volume de commande initial (pièces)','integer','3000'],
            'prix_vente'=>['Prix cible de vente (€/unité TTC)','number','39'],
            'contraintes'=>['Autres contraintes / hypothèses financières','textarea','','Devise, hypothèses de chiffrage, exclusions et marges de réserve.'],
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
        if($key==='nom'&&$value==='')throw new InvalidArgumentException('Le nom du projet est obligatoire.');
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
    $db->exec('CREATE TABLE IF NOT EXISTS cdc_test_forms (projet_id INTEGER PRIMARY KEY, payload TEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 1, updated_at TEXT NOT NULL, updated_by INTEGER NOT NULL)');
}
function cdcTestLoad(PDO $db,int $project): ?array {
    $s=$db->prepare('SELECT * FROM cdc_test_forms WHERE projet_id=?');$s->execute([$project]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;
}
function cdcTestSave(PDO $db,int $project,int $user,int $revision,array $data): void {
    $payload=json_encode(cdcTestValidate($data),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if($revision===0){$s=$db->prepare("INSERT OR IGNORE INTO cdc_test_forms(projet_id,payload,updated_at,updated_by) VALUES(?,?,datetime('now'),?)");$s->execute([$project,$payload,$user]);}
    else{$s=$db->prepare("UPDATE cdc_test_forms SET payload=?,revision=revision+1,updated_at=datetime('now'),updated_by=? WHERE projet_id=? AND revision=?");$s->execute([$payload,$user,$project,$revision]);}
    if($s->rowCount()!==1)throw new RuntimeException('Une autre modification a été enregistrée. Rechargez le formulaire avant de réessayer.');
}
