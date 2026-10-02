<?php
/**
 * Schéma et helpers pour le cahier des charges structuré
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/schema.php';

function ensureCahierSpecsColumn(): void {
    runSchemaMigrations();
}

function emptySpecs(): array {
    return [
        // 1. Contexte
        'objectifs' => '',
        'resultats_attendus' => '',
        'cas_usage' => '',
        'profils_utilisateurs' => '',
        // 2. Spécifications fonctionnelles
        'fonctions' => [], // [ ['id'=>'S.F.1', 'description'=>'', 'indicateur'=>'Obligatoire'], ... ]
        // Specs techniques (étape R1b 2) liées aux S.F. : [ ['sf'=>'S.F.1', 'id'=>'S.T.1.1', 'description'=>'', 'type'=>'Matériel'], ... ]
        'specs_techniques' => [],
        // Composants / affectations liés aux S.T. (étape 4) : [ st_id => [ items... ] ]
        'composants_st' => [],
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
    $db = getDB();
    $json = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // date_maj peut manquer sur d'anciennes bases
    $cols = $db->query('PRAGMA table_info(cahiers)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('date_maj', $cols, true)) {
        $stmt = $db->prepare('UPDATE cahiers SET specs_json = ?, date_maj = CURRENT_TIMESTAMP WHERE id = ?');
    } else {
        $stmt = $db->prepare('UPDATE cahiers SET specs_json = ? WHERE id = ?');
    }
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
        $lines[] = str_pad("ID", 8) . " | " . str_pad("Indicateur", 12) . " | Description";
        $lines[] = str_repeat('-', 72);
        foreach ($fonctions as $f) {
            $id = $f['id'] ?? '';
            $ind = $f['indicateur'] ?? '';
            $desc = trim(preg_replace('/\s+/', ' ', $f['description'] ?? ''));
            $lines[] = str_pad($id, 8) . " | " . str_pad($ind, 12) . " | " . $desc;
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
        if ($desc === '') {
            continue;
        }
        $n++;
        $out[] = [
            'id' => 'S.F.' . $n,
            'description' => $desc,
            'indicateur' => $ind,
        ];
    }
    return $out;
}



function ensureJalonsTable(): void {
    runSchemaMigrations();
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
 * Parse specs techniques depuis le POST (st_sf[], st_description[], st_type[]).
 * Renumérote localement par S.F. : S.T.{n}.{m}
 * @return list<array{sf:string,id:string,description:string,type:string}>
 */
/** Les achats comportent un coût ; les réalisations internes un délai seul. */
function stTypeHasCost(string $type): bool {
    return in_array($type, ['Matériel', 'Composant', 'Prestataire', 'PCB'], true);
}

function parseDelaiJours($value): ?float {
    $value = str_replace(',', '.', trim((string)$value));
    return $value !== '' && is_numeric($value) && is_finite((float)$value) && (float)$value >= 0
        ? round((float)$value, 2) : null;
}

function parseSpecsTechniquesFromPost(array $post): array {
    $sfs = $post['st_sf'] ?? [];
    $descs = $post['st_description'] ?? [];
    $types = $post['st_type'] ?? [];
    $costs = $post['st_cout_estime'] ?? [];
    $taxes = $post['st_cout_taxe'] ?? [];
    if (!is_array($sfs) || !is_array($descs)) {
        return [];
    }
    $allowedTypes = ['Matériel', 'Composant', 'Prestataire', 'Logiciel', '3D', 'PCB'];
    // Group by SF keeping order
    $bySf = [];
    foreach ($sfs as $i => $sf) {
        $sf = trim((string)$sf);
        $desc = trim((string)($descs[$i] ?? ''));
        $type = trim((string)($types[$i] ?? 'Matériel'));
        $cost = max(0, (float)str_replace(',', '.', (string)($costs[$i] ?? '0')));
        $taxe = (($taxes[$i] ?? 'HT') === 'TTC') ? 'TTC' : 'HT';
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'Matériel';
        }
        if ($sf === '' || !preg_match('/^S\.F\.(\d+)$/', $sf, $m)) {
            continue;
        }
        // keep empty desc rows only if we still want placeholders - skip empty
        if ($desc === '') {
            continue;
        }
        $n = (int)$m[1];
        if (!isset($bySf[$n])) {
            $bySf[$n] = [];
        }
        $bySf[$n][] = [
            'sf' => 'S.F.' . $n,
            'description' => $desc,
            'type' => $type,
            'cout_estime' => stTypeHasCost($type) ? round($cost, 2) : 0,
            'delai_jours' => parseDelaiJours($post['st_delai_jours'][$i] ?? ''),
            'cout_taxe' => $taxe,
        ];
    }
    ksort($bySf, SORT_NUMERIC);
    $out = [];
    foreach ($bySf as $n => $rows) {
        $m = 0;
        foreach ($rows as $row) {
            $m++;
            $out[] = [
                'sf' => $row['sf'],
                'id' => 'S.T.' . $n . '.' . $m,
                'description' => $row['description'],
                'type' => $row['type'],
                'cout_estime' => $row['cout_estime'] ?? 0,
                'cout_taxe' => $row['cout_taxe'] ?? 'HT',
                'delai_jours' => $row['delai_jours'] ?? null,
            ];
        }
    }
    return $out;
}

function saveSpecsTechniques(int $cahierId, array $techniques): void {
    ensureCahierSpecsColumn();
    $db = getDB();
    $stmt = $db->prepare('SELECT specs_json FROM cahiers WHERE id = ?');
    $stmt->execute([$cahierId]);
    $raw = $stmt->fetchColumn();
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $data['specs_techniques'] = array_values($techniques);
    saveSpecs($cahierId, $data);
}




/**
 * Parse les lignes composants / affectations (étape 4) depuis le POST.
 * @return array<string, list<array>> clé = id S.T.
 */
function parseComposantsStFromPost(array $post): array {
    $stIds = $post['cp_st_id'] ?? [];
    $types = $post['cp_type'] ?? [];
    if (!is_array($stIds)) {
        return [];
    }
    $bySt = [];
    foreach ($stIds as $i => $stId) {
        $stId = trim((string)$stId);
        $type = trim((string)($types[$i] ?? 'Matériel'));
        if ($stId === '') {
            continue;
        }
        if (stTypeHasCost($type)) {
            $des = trim((string)($post['cp_designation'][$i] ?? ''));
            $ref = trim((string)($post['cp_reference'][$i] ?? ''));
            $four = trim((string)($post['cp_fournisseur'][$i] ?? ''));
            $qty = (float)str_replace(',', '.', (string)($post['cp_quantite'][$i] ?? '0'));
            $cu = (float)str_replace(',', '.', (string)($post['cp_cout_unitaire'][$i] ?? '0'));
            $cuTaxe = ($post['cp_cout_unitaire_taxe'][$i] ?? 'HT') === 'TTC' ? 'TTC' : 'HT';
            $ctTaxe = $cuTaxe;
            $delai = parseDelaiJours($post['cp_delai_jours'][$i] ?? '');
            $aff = trim((string)($post['cp_affectation'][$i] ?? ''));
            $duree = trim((string)($post['cp_duree'][$i] ?? ''));
            if ($des === '' && $ref === '' && $four === '' && $qty <= 0 && $cu <= 0 && $delai === null && $aff === '' && $duree === '') {
                continue;
            }
            if (!isset($bySt[$stId])) {
                $bySt[$stId] = ['type' => $type, 'items' => []];
            }
            $bySt[$stId]['items'][] = [
                'designation' => $des,
                'reference' => $ref,
                'fournisseur' => $four,
                'quantite' => $qty,
                'cout_unitaire' => $cu,
                'cout_unitaire_taxe' => $cuTaxe,
                'cout_total' => round($qty * $cu, 2),
                'cout_total_taxe' => $ctTaxe,
                'delai_jours' => $delai,
                'affectation' => $aff,
                'duree' => $duree,
                'variation' => in_array(($post['cp_variation'][$i] ?? ''), ['Forte', 'Moyenne', 'Faible'], true) ? $post['cp_variation'][$i] : 'Moyenne',
            ];
        } else {
            // Logiciel et 3D
            $aff = trim((string)($post['cp_affectation'][$i] ?? ''));
            $duree = trim((string)($post['cp_duree'][$i] ?? ''));
            $var = trim((string)($post['cp_variation'][$i] ?? 'Moyenne'));
            if (!in_array($var, ['Forte', 'Moyenne', 'Faible'], true)) {
                $var = 'Moyenne';
            }
            $delai = parseDelaiJours($post['cp_delai_jours'][$i] ?? '');
            if ($aff === '' && $duree === '' && $delai === null) {
                continue;
            }
            if (!isset($bySt[$stId])) {
                $bySt[$stId] = ['type' => $type, 'items' => []];
            }
            $bySt[$stId]['items'][] = [
                'affectation' => $aff,
                'duree' => $duree,
                'delai_jours' => $delai,
                'variation' => $var,
            ];
        }
    }

    // Renuméroter les ID selon le type
    $out = [];
    foreach ($bySt as $stId => $pack) {
        $type = $pack['type'];
        $prefix = match ($type) {
            'Matériel' => 'M',
            'Composant' => 'C',
            'Prestataire' => 'P',
            'Logiciel' => 'L',
            '3D' => '3D',
            'PCB' => 'PCB',
            default => 'X',
        };
        $items = [];
        $n = 0;
        foreach ($pack['items'] as $it) {
            $n++;
            $it['id'] = $prefix . '.' . $n;
            $items[] = $it;
        }
        $out[$stId] = $items;
    }
    return $out;
}

function saveComposantsSt(int $cahierId, array $composants): void {
    ensureCahierSpecsColumn();
    $db = getDB();
    $stmt = $db->prepare('SELECT specs_json FROM cahiers WHERE id = ?');
    $stmt->execute([$cahierId]);
    $raw = $stmt->fetchColumn();
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $data['composants_st'] = $composants;
    saveSpecs($cahierId, $data);
}






function statutLabel(string $s): string
{
    return match ($s) {
        'a_faire', 'todo' => 'À faire',
        'en_cours', 'in_progress' => 'En cours',
        'terminee', 'done', 'terminé' => 'Terminé',
        'validation' => 'En validation',
        default => $s,
    };
}

function taskKanbanStatus(array $t): string
{
    $k = $t['kanban_status'] ?? '';
    if (in_array($k, ['a_faire', 'en_cours', 'validation', 'terminee'], true)) {
        return $k;
    }
    $s = $t['statut'] ?? 'a_faire';
    return match ($s) {
        'terminee', 'done', 'terminé' => 'terminee',
        'en_cours', 'in_progress' => 'en_cours',
        default => 'a_faire',
    };
}


function ensureTachesExtendedColumns(): void {
    runSchemaMigrations();
}

/**
 * Synchronise les lignes Logiciel / 3D / PCB de l'étape 4 vers la table taches.
 * @param array<string, list<array>> $composants  id S.T. => items
 * @param list<array> $techniques  specs_techniques (pour libellés)
 */
function syncTasksFromComposants(int $projetId, array $composants, array $techniques): void {
    ensureTachesExtendedColumns();
    $db = getDB();
    $techById = [];
    foreach ($techniques as $t) {
        if (!empty($t['id'])) {
            $techById[$t['id']] = $t;
        }
    }
    $wanted = [];
    foreach ($composants as $stId => $items) {
        if (!is_array($items)) continue;
        $tech = $techById[$stId] ?? [];
        $stType = $tech['type'] ?? '';
        $stDesc = $tech['description'] ?? '';
        foreach ($items as $it) {
            $itemId = $it['id'] ?? '';
            if ($itemId === '') continue;
            $prefix = explode('.', $itemId)[0] ?? '';
            $isSoft = in_array($stType, ['Logiciel', '3D', 'PCB'], true)
                || in_array($prefix, ['L', '3D', 'PCB'], true);
            if (!$isSoft) continue;
            $typeLabel = $stType ?: ($prefix === 'L' ? 'Logiciel' : $prefix);
            $key = 'cp:' . $projetId . ':' . $stId . ':' . $itemId;
            $aff = trim((string)($it['affectation'] ?? ''));
            $duree = trim((string)($it['duree'] ?? ''));
            $var = trim((string)($it['variation'] ?? ''));
            $titre = '[' . $itemId . '] ' . $stId . ($stDesc !== '' ? ' — ' . $stDesc : '');
            $description = implode("\n", array_filter([
                'Type : ' . $typeLabel,
                isset($it['delai_jours']) ? 'Délai : ' . $it['delai_jours'] . ' j' : ($duree !== '' ? 'Durée : ' . $duree : ''),
                $var !== '' ? 'Variation : ' . $var : '',
            ]));
            $wanted[$key] = [
                'titre' => $titre,
                'description' => $description,
                'assigne_a' => $aff !== '' ? $aff : null,
            ];
        }
    }
    $stmt = $db->prepare("SELECT id, source_key FROM taches WHERE projet_id = ? AND source_key LIKE 'cp:%'");
    $stmt->execute([$projetId]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['source_key']] = (int)$row['id'];
    }
    $ins = $db->prepare('INSERT INTO taches (projet_id, titre, description, priorite, statut, assigne_a, source_key, kanban_status) VALUES (?,?,?,?,?,?,?,?)');
    $upd = $db->prepare('UPDATE taches SET titre=?, description=?, assigne_a=? WHERE id=?');
    $del = $db->prepare('DELETE FROM taches WHERE id=?');
    $db->beginTransaction();
    try {
        foreach ($wanted as $key => $payload) {
            if (isset($existing[$key])) {
                $upd->execute([$payload['titre'], $payload['description'], $payload['assigne_a'], $existing[$key]]);
                unset($existing[$key]);
            } else {
                $ins->execute([$projetId, $payload['titre'], $payload['description'], 'moyenne', 'a_faire', $payload['assigne_a'], $key, 'a_faire']);
            }
        }
        foreach ($existing as $idLeft) {
            $del->execute([$idLeft]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}


/**
 * Synchronise les spécifications techniques Logiciel / 3D / PCB de l'étape 2
 * vers la table taches. Une S.T. correspond à une tâche de projet.
 *
 * Les composants de l'étape 4 utilisent une synchronisation distincte
 * (syncTasksFromComposants) avec des clés source "cp:*".
 */
function syncTasksFromStructuredSpecs(int $projetId, array $fonctions, array $techniques): void {
    ensureTachesExtendedColumns();
    $db = getDB();
    $wanted = [];

    // Les S.F. ne sont PAS des tâches.
    // Une S.F. sert à générer/porter ses S.T. dans l'étape 2.

    // Chaque S.T., quel que soit son type (Matériel, Logiciel, 3D, PCB),
    // devient une tâche dès qu'elle possède une description.
    foreach ($techniques as $tech) {
        if (!is_array($tech)) continue;
        $stId = trim((string)($tech['id'] ?? ''));
        $desc = trim((string)($tech['description'] ?? ''));
        $type = trim((string)($tech['type'] ?? 'Matériel'));
        if ($stId === '' || $desc === '' || !in_array($type, ['Matériel', 'Composant', 'Prestataire', 'Logiciel', '3D', 'PCB'], true)) {
            continue;
        }

        $key = 'st:' . $projetId . ':' . $stId;
        $wanted[$key] = [
            'titre' => '[' . $stId . '] ' . $desc,
            'description' => 'Type : ' . $type,
            'assigne_a' => null,
        ];
    }

    // Synchronisation uniquement des tâches automatiques issues des S.T.
    // Les éventuelles anciennes tâches issues des S.F. sont supprimées.
    $stmt = $db->prepare(
        "SELECT id, source_key FROM taches
         WHERE projet_id = ? AND (source_key LIKE 'sf:%' OR source_key LIKE 'st:%')"
    );
    $stmt->execute([$projetId]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['source_key']] = (int)$row['id'];
    }

    $ins = $db->prepare(
        'INSERT INTO taches
         (projet_id, titre, description, priorite, statut, assigne_a, source_key, kanban_status)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $upd = $db->prepare('UPDATE taches SET titre=?, description=? WHERE id=?');
    $del = $db->prepare('DELETE FROM taches WHERE id=?');

    $db->beginTransaction();
    try {
        foreach ($wanted as $key => $payload) {
            if (isset($existing[$key])) {
                $upd->execute([$payload['titre'], $payload['description'], $existing[$key]]);
                unset($existing[$key]);
            } else {
                $ins->execute([
                    $projetId,
                    $payload['titre'],
                    $payload['description'],
                    'moyenne',
                    'a_faire',
                    $payload['assigne_a'],
                    $key,
                    'a_faire'
                ]);
            }
        }

        // Supprime les tâches automatiques correspondant aux S.T. supprimées
        // ainsi que les anciennes tâches automatiques issues des S.F.
        foreach ($existing as $idLeft) {
            $del->execute([$idLeft]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
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



function getOrCreateCahierId(int $projetId): int
{
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM cahiers WHERE projet_id = ?');
    $stmt->execute([$projetId]);
    $cid = (int)$stmt->fetchColumn();
    if ($cid <= 0) {
        $db->prepare('INSERT INTO cahiers (projet_id) VALUES (?)')->execute([$projetId]);
        $cid = (int)$db->lastInsertId();
    }
    return $cid;
}
