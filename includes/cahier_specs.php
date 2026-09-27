<?php
/**
 * Schéma et helpers pour le cahier des charges structuré
 */
require_once __DIR__ . '/../config/db.php';

function ensureCahierSpecsColumn(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $cols = $db->query("PRAGMA table_info(cahiers)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('specs_json', $cols, true)) {
        $db->exec('ALTER TABLE cahiers ADD COLUMN specs_json TEXT');
    }
    $done = true;
}

function emptySpecs(): array {
    return [
        // 1. Contexte
        'objectifs' => '',
        'resultats_attendus' => '',
        'cas_usage' => '',
        'profils_utilisateurs' => '',
        // 2. Spécifications fonctionnelles
        'fonctions' => [], // [ ['id'=>'S.F.1', 'description'=>'', 'indicateur'=>'Obligatoire', 'materiel'=>false, 'logiciel'=>false], ... ]
        // Specs techniques (étape R1b 2) liées aux S.F. : [ ['sf'=>'S.F.1', 'id'=>'S.T.1.1', 'description'=>'', 'type'=>'Matériel'], ... ]
        'specs_techniques' => [],
        // 3. Planning
        'delais' => '',
        'livrables_attendus' => '',
    ];
}

function loadSpecs(int $cahierId): array {
    ensureCahierSpecsColumn();
    $stmt = getDB()->prepare('SELECT specs_json FROM cahiers WHERE id = ?');
    $stmt->execute([$cahierId]);
    $json = $stmt->fetchColumn();
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data)) $data = [];
    [$data] = stripObsoleteSpecs($data);
    return array_merge(emptySpecs(), $data);
}

function saveSpecs(int $cahierId, array $specs): void {
    ensureCahierSpecsColumn();
    $json = json_encode($specs, JSON_UNESCAPED_UNICODE);
    $stmt = getDB()->prepare('UPDATE cahiers SET specs_json = ?, date_maj = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$json, $cahierId]);
}

function generateCahierText(array $s, string $projetNom, array $jalons = []): string {
    $lines = [];
    $lines[] = "CAHIER DES CHARGES — " . $projetNom;
    $lines[] = str_repeat('=', 60);

    $lines[] = "\n1. CONTEXTE, OBJECTIFS ET BESOINS UTILISATEURS";
    $lines[] = "Objectifs et contexte :\n" . ($s['objectifs'] ?: '—');
    $lines[] = "Hors périmètre du projet :\n" . ($s['resultats_attendus'] ?: '—');
    $lines[] = "Contraintes :\n" . ($s['cas_usage'] ?: '—');
    $lines[] = "Profils utilisateurs :\n" . ($s['profils_utilisateurs'] ?: '—');

    $lines[] = "\n2. SPÉCIFICATIONS FONCTIONNELLES";
    $fonctions = $s['fonctions'] ?? [];
    if (empty($fonctions)) {
        $lines[] = "—";
    } else {
        $lines[] = str_pad("ID", 8) . " | " . str_pad("Indicateur", 12) . " | Mat. | Log. | Description";
        $lines[] = str_repeat('-', 72);
        foreach ($fonctions as $f) {
            $id = $f['id'] ?? '';
            $ind = $f['indicateur'] ?? '';
            $mat = !empty($f['materiel']) ? 'Oui' : 'Non';
            $log = !empty($f['logiciel']) ? 'Oui' : 'Non';
            $desc = trim(preg_replace('/\s+/', ' ', $f['description'] ?? ''));
            $lines[] = str_pad($id, 8) . " | " . str_pad($ind, 12) . " | " . str_pad($mat, 3) . " | " . str_pad($log, 3) . " | " . $desc;
        }
    }

    $lines[] = "\n3. PLANNING ET LIVRABLES";
    $lines[] = "Délais :\n" . ($s['delais'] ?: '—');
    $lines[] = "Livrables :\n" . ($s['livrables_attendus'] ?: '—');
    $lines[] = "Jalons :";
    if (empty($jalons)) {
        $lines[] = "—";
    } else {
        foreach ($jalons as $j) {
            $d = !empty($j['date_prevue']) ? date('d/m/Y', strtotime($j['date_prevue'])) : 'date non définie';
            $lines[] = "  • " . ($j['nom'] ?? '') . " — " . $d;
        }
    }

    return implode("\n", $lines);
}




/**
 * Parse les lignes de spécifications fonctionnelles depuis le POST.
 * @return list<array{id:string,description:string,indicateur:string,materiel:bool,logiciel:bool}>
 */
function parseFonctionsFromPost(array $post): array {
    $descs = $post['sf_description'] ?? [];
    $inds = $post['sf_indicateur'] ?? [];
    $mats = $post['sf_materiel'] ?? [];
    $logs = $post['sf_logiciel'] ?? [];
    if (!is_array($descs)) {
        return [];
    }
    $out = [];
    $n = 0;
    foreach ($descs as $i => $desc) {
        $desc = trim((string)$desc);
        $ind = trim((string)($inds[$i] ?? 'Obligatoire'));
        if (!in_array($ind, ['Obligatoire', 'Facultative', 'Optionnel'], true)) {
            $ind = 'Obligatoire';
        }
        // Skip completely empty rows
        $mat = isset($mats[$i]);
        $log = isset($logs[$i]);
        if ($desc === '' && !$mat && !$log) {
            continue;
        }
        $n++;
        $out[] = [
            'id' => 'S.F.' . $n,
            'description' => $desc,
            'indicateur' => $ind,
            'materiel' => $mat,
            'logiciel' => $log,
        ];
    }
    return $out;
}



function ensureJalonsTable(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS jalons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cahier_id INTEGER NOT NULL,
        nom TEXT NOT NULL,
        date_prevue DATE,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");
    $done = true;
}

/** @return list<array{id:int,nom:string,date_prevue:?string}> */
function loadJalons(int $cahierId): array {
    ensureJalonsTable();
    $stmt = getDB()->prepare('SELECT id, nom, date_prevue FROM jalons WHERE cahier_id = ? ORDER BY date_prevue IS NULL, date_prevue, id');
    $stmt->execute([$cahierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Remplace tous les jalons d'un cahier à partir du POST (jalon_nom[], jalon_date[]).
 * @return int nombre de jalons enregistrés
 */
function saveJalonsFromPost(int $cahierId, array $post): int {
    ensureJalonsTable();
    $noms = $post['jalon_nom'] ?? [];
    $dates = $post['jalon_date'] ?? [];
    if (!is_array($noms)) {
        $noms = [];
    }
    $db = getDB();
    $db->prepare('DELETE FROM jalons WHERE cahier_id = ?')->execute([$cahierId]);
    $ins = $db->prepare('INSERT INTO jalons (cahier_id, nom, date_prevue) VALUES (?,?,?)');
    $n = 0;
    foreach ($noms as $i => $nom) {
        $nom = trim((string)$nom);
        if ($nom === '') {
            continue;
        }
        $date = trim((string)($dates[$i] ?? ''));
        if ($date === '') {
            $date = null;
        }
        $ins->execute([$cahierId, $nom, $date]);
        $n++;
    }
    return $n;
}




/**
 * Parse specs techniques depuis le POST.
 * Champs : st_sf[], st_uid[], st_description[], st_type[], st_deps[i][] (uids des dépendances).
 * Renumérote localement par S.F. : S.T.{n}.{m}. Les dépendances utilisent des uid stables.
 * @return list<array{sf:string,id:string,uid:string,description:string,type:string,dependances:list<string>}>
 */
function parseSpecsTechniquesFromPost(array $post): array {
    $sfs = $post['st_sf'] ?? [];
    $uids = $post['st_uid'] ?? [];
    $descs = $post['st_description'] ?? [];
    $types = $post['st_type'] ?? [];
    $depsAll = $post['st_deps'] ?? [];
    if (!is_array($sfs) || !is_array($descs)) {
        return [];
    }
    $allowedTypes = ['Matériel', 'Logiciel', '3D', 'PCB'];
    $bySf = [];
    $validUids = [];

    foreach ($sfs as $i => $sf) {
        $sf = trim((string)$sf);
        $desc = trim((string)($descs[$i] ?? ''));
        $type = trim((string)($types[$i] ?? 'Matériel'));
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'Matériel';
        }
        if ($sf === '' || !preg_match('/^S\.F\.(\d+)$/', $sf, $m)) {
            continue;
        }
        if ($desc === '') {
            continue;
        }
        $uid = trim((string)($uids[$i] ?? ''));
        if ($uid === '' || !preg_match('/^[a-zA-Z0-9_-]{6,64}$/', $uid)) {
            $uid = bin2hex(random_bytes(8));
        }
        $deps = [];
        if (isset($depsAll[$i]) && is_array($depsAll[$i])) {
            foreach ($depsAll[$i] as $d) {
                $d = trim((string)$d);
                if ($d !== '' && $d !== $uid) {
                    $deps[] = $d;
                }
            }
            $deps = array_values(array_unique($deps));
        }
        $n = (int)$m[1];
        if (!isset($bySf[$n])) {
            $bySf[$n] = [];
        }
        $bySf[$n][] = [
            'sf' => 'S.F.' . $n,
            'uid' => $uid,
            'description' => $desc,
            'type' => $type,
            'dependances' => $deps,
        ];
        $validUids[$uid] = true;
    }

    ksort($bySf, SORT_NUMERIC);
    $out = [];
    foreach ($bySf as $n => $rows) {
        $mIdx = 0;
        foreach ($rows as $row) {
            $mIdx++;
            // Ne garder que des dépendances vers des S.T. encore présentes
            $deps = array_values(array_filter(
                $row['dependances'],
                static fn($d) => isset($validUids[$d])
            ));
            $out[] = [
                'sf' => $row['sf'],
                'id' => 'S.T.' . $n . '.' . $mIdx,
                'uid' => $row['uid'],
                'description' => $row['description'],
                'type' => $row['type'],
                'dependances' => $deps,
            ];
        }
    }
    return $out;
}

/**
 * Index uid => id affiché pour résoudre les dépendances à l'affichage.
 * @param list<array> $techniques
 * @return array<string,string>
 */
function techniquesUidToId(array $techniques): array {
    $map = [];
    foreach ($techniques as $t) {
        if (!empty($t['uid']) && !empty($t['id'])) {
            $map[$t['uid']] = $t['id'];
        }
    }
    return $map;
}

function saveSpecsTechniques(int $cahierId, array $techniques): void {
    $specs = loadSpecs($cahierId);
    $specs['specs_techniques'] = $techniques;
    // loadSpecs merges emptySpecs and strips obsolete - ensure fonctions preserved
    saveSpecs($cahierId, $specs);
}


/** Clés des onglets supprimés (Performance, Environnement, Technique, Support) */
function obsoleteSpecKeys(): array {
    return [
        'contexte_utilisation', 'contexte_autre', 'contraintes_operationnelles',
        'fonctionnalites', 'fonctionnalites_cochees', 'connectivite', 'connectivite_autre',
        'luminosite_nits', 'tactile_multitouch', 'usage_gants', 'anti_reflet', 'taille_ecran', 'ihm_accessoires',
        'processeur', 'ram', 'stockage', 'autonomie', 'fiabilite', 'securite', 'maintenabilite',
        'ip', 'ip_autre', 'chute_metres', 'vibrations', 'temp_fonc_min', 'temp_fonc_max',
        'temp_stock_min', 'temp_stock_max', 'humidite', 'brouillard_salin', 'altitude_max',
        'uv', 'cem', 'normes', 'normes_autre', 'niveau_durcissement',
        'architecture', 'compatibilite', 'alimentation', 'consommation_max', 'materiaux', 'conformites',
        'mode_installation', 'maintenance', 'garantie', 'formation_doc', 'pieces_sav', 'cycle_vie',
    ];
}

/**
 * Retire les clés obsolètes d'un tableau de specs et retourne [specs nettoyées, nb clés retirées].
 */
function stripObsoleteSpecs(array $specs): array {
    $removed = 0;
    foreach (obsoleteSpecKeys() as $k) {
        if (array_key_exists($k, $specs)) {
            unset($specs[$k]);
            $removed++;
        }
    }
    return [$specs, $removed];
}

/**
 * Nettoie specs_json de tous les cahiers. Retourne le nombre de cahiers modifiés.
 */
function cleanAllObsoleteSpecs(): int {
    ensureCahierSpecsColumn();
    $db = getDB();
    $rows = $db->query('SELECT id, specs_json FROM cahiers')->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $stmt = $db->prepare('UPDATE cahiers SET specs_json = ? WHERE id = ?');
    foreach ($rows as $row) {
        $raw = $row['specs_json'] ?? '';
        if ($raw === null || $raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        [$clean, $n] = stripObsoleteSpecs($decoded);
        if ($n > 0) {
            $stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), (int)$row['id']]);
            $updated++;
        }
    }
    return $updated;
}

