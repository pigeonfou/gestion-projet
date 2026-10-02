<?php
/** Quality evidence is metadata only; documents remain on Nextcloud. */
function qualitySchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS qualite_fournisseurs (
        fournisseur_id INTEGER PRIMARY KEY REFERENCES stock_fournisseurs(id),
        iso9001 TEXT NOT NULL DEFAULT 'inconnu', organisme9001 TEXT, certificat9001 TEXT, validite9001 TEXT,
        iso14001 TEXT NOT NULL DEFAULT 'inconnu', organisme14001 TEXT, certificat14001 TEXT, validite14001 TEXT,
        document_id INTEGER REFERENCES documents(id), updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    ); CREATE TABLE IF NOT EXISTS qualite_references (
        projet_id INTEGER NOT NULL REFERENCES projets(id), reference TEXT NOT NULL,
        fournisseur_id INTEGER REFERENCES stock_fournisseurs(id),
        rohs TEXT NOT NULL DEFAULT 'inconnu', rohs_pct REAL,
        reach TEXT NOT NULL DEFAULT 'inconnu', reach_pct REAL,
        document_id INTEGER REFERENCES documents(id), notes TEXT NOT NULL DEFAULT '',
        action_task_id INTEGER REFERENCES taches(id) ON DELETE SET NULL,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(projet_id,reference)
    ); CREATE TABLE IF NOT EXISTS qualite_historique (
        id INTEGER PRIMARY KEY, projet_id INTEGER NOT NULL REFERENCES projets(id),
        utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id), objet TEXT NOT NULL,
        details TEXT NOT NULL, date_action TEXT DEFAULT CURRENT_TIMESTAMP
    )");
}

function qualityCoverage(string $status, $raw): ?float {
    if (!in_array($status,['conforme','non_conforme','partiel','inconnu'],true)) throw new InvalidArgumentException('Statut de conformité invalide.');
    if ($status==='inconnu') return null;
    if ($raw==='' || !is_numeric($raw)) throw new InvalidArgumentException('Couverture numérique requise.');
    $value=(float)$raw;
    if (!is_finite($value) || $value<0 || $value>100 || ($status==='conforme' && $value!==100.0) || ($status==='partiel' && ($value<=0 || $value>=100))) throw new InvalidArgumentException('Couverture entre 0 et 100; conforme=100, partiel strictement entre 0 et 100.');
    return $value;
}

function qualityCertificate(array $post, string $norm): array {
    $status=(string)($post['iso'.$norm]??'inconnu');
    if (!in_array($status,['oui','non','inconnu'],true)) throw new InvalidArgumentException('Statut ISO invalide.');
    $organisme=trim((string)($post['organisme'.$norm]??''));
    $numero=trim((string)($post['certificat'.$norm]??''));
    $date=trim((string)($post['validite'.$norm]??''));
    if (strlen($organisme)>200 || strlen($numero)>200) throw new InvalidArgumentException('Certificat trop long.');
    if ($status==='oui') {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if ($organisme==='' || $numero==='' || !$d || $d->format('Y-m-d')!==$date) throw new InvalidArgumentException('ISO Oui nécessite organisme, numéro et date de validité.');
    } else { $organisme=''; $numero=''; $date=''; }
    return [$status,$organisme,$numero,$date?:null];
}

function qualityAlerts(array $row): array {
    $alerts=[];
    foreach(['rohs'=>'RoHS','reach'=>'REACH'] as $key=>$label) {
        if (($row[$key]??'inconnu')!=='conforme') $alerts[]=$label.' : '.($row[$key]??'inconnu');
    }
    if (empty($row['document_id'])) $alerts[]='Preuve documentaire absente';
    if (empty($row['fournisseur_id'])) $alerts[]='Fournisseur non relié';
    return $alerts;
}

function qualityIndicators(array $rows): array {
    $out=['references'=>count($rows),'iso9001'=>0,'iso14001'=>0,'rohs'=>0,'reach'=>0,'inconnues'=>0,'non_conformes'=>0,'alertes'=>0];
    foreach($rows as $row) {
        foreach(['iso9001','iso14001'] as $key) if (($row[$key]??'inconnu')==='oui' && !empty($row['validite'.substr($key,3)]) && $row['validite'.substr($key,3)]>=date('Y-m-d')) $out[$key]++;
        foreach(['rohs','reach'] as $key) if (($row[$key]??'inconnu')==='conforme') $out[$key]++;
        if (($row['rohs']??'inconnu')==='inconnu' || ($row['reach']??'inconnu')==='inconnu') $out['inconnues']++;
        if (($row['rohs']??'inconnu')==='non_conforme' || ($row['reach']??'inconnu')==='non_conforme') $out['non_conformes']++;
        if (qualityAlerts($row)) $out['alertes']++;
    }
    return $out;
}
