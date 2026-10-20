<?php
function syncTasksFromComposants(int $projetId, array $composants, array $techniques): void {
    pfTransaction(getDB(), function () use ($projetId, $composants, $techniques) {
    ensureTachesExtendedColumns();
    $db = getDB();
    $techById = [];
    foreach ($techniques as $t) {
        if (!empty($t['id'])) {
            $techById[$t['id']] = $t;
        }
    }
    pfBackfillTaskIdentities($db, $projetId, ['specs_techniques'=>$techniques, 'composants_st'=>$composants]);
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
            $displayKey = 'cp:' . $projetId . ':' . $stId . ':' . $itemId;
            $key = 'cp:' . $tech['uid'] . ':' . pfIdentity($it['uid'] ?? null, $tech['uid'] . ':' . $itemId);
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
                'source_key' => $displayKey,
                'titre' => $titre,
                'description' => $description,
                'assigne_a' => $aff !== '' ? $aff : null,
            ];
        }
    }
    $stmt = $db->prepare("SELECT id, source_key, source_uid FROM taches WHERE projet_id = ? AND source_key LIKE 'cp:%'");
    $stmt->execute([$projetId]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['source_uid'] ?: $row['source_key']] = (int)$row['id'];
    }
    $ins = $db->prepare('INSERT INTO taches (projet_id, titre, description, priorite, statut, assigne_a, source_key, kanban_status, source_uid) VALUES (?,?,?,?,?,?,?,?,?)');
    $upd = $db->prepare('UPDATE taches SET titre=?, description=?, assigne_a=?, source_key=?, source_uid=? WHERE id=?');
    $del = $db->prepare('DELETE FROM taches WHERE id=?');
    
    try {
        foreach ($wanted as $key => $payload) {
            if (isset($existing[$key])) {
                $upd->execute([$payload['titre'], $payload['description'], $payload['assigne_a'], $payload['source_key'], $key, $existing[$key]]);
                unset($existing[$key]);
            } else {
                $ins->execute([$projetId, $payload['titre'], $payload['description'], 'moyenne', 'a_faire', $payload['assigne_a'], $payload['source_key'], 'a_faire', $key]);
            }
        }
        foreach ($existing as $idLeft) {
            $del->execute([$idLeft]);
        }
        
    } catch (Throwable $e) {
        
        throw $e;
    }

    });
}


/**
 * Synchronise les spécifications techniques Logiciel / 3D / PCB de l'étape 2
 * vers la table taches. Une S.T. correspond à une tâche de projet.
 *
 * Les composants de l'étape 4 utilisent une synchronisation distincte
 * (syncTasksFromComposants) avec des clés source "cp:*".
 */
function syncTasksFromStructuredSpecs(int $projetId, array $fonctions, array $techniques): void {
    pfTransaction(getDB(), function () use ($projetId, $fonctions, $techniques) {
    ensureTachesExtendedColumns();
    $db = getDB();
    pfBackfillTaskIdentities($db, $projetId, ['specs_techniques'=>$techniques, 'composants_st'=>[]]);
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

        $displayKey = 'st:' . $projetId . ':' . $stId;
        $key = 'st:' . pfIdentity($tech['uid'] ?? null, $projetId . ':' . $stId);
        $wanted[$key] = [
                'source_key' => $displayKey,
            'titre' => '[' . $stId . '] ' . $desc,
            'description' => 'Type : ' . $type,
            'assigne_a' => null,
        ];
    }

    // Synchronisation uniquement des tâches automatiques issues des S.T.
    // Les éventuelles anciennes tâches issues des S.F. sont supprimées.
    $stmt = $db->prepare(
        "SELECT id, source_key, source_uid FROM taches
         WHERE projet_id = ? AND (source_key LIKE 'sf:%' OR source_key LIKE 'st:%')"
    );
    $stmt->execute([$projetId]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['source_uid'] ?: $row['source_key']] = (int)$row['id'];
    }

    $ins = $db->prepare(
        'INSERT INTO taches
         (projet_id, titre, description, priorite, statut, assigne_a, source_key, kanban_status, source_uid)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $upd = $db->prepare('UPDATE taches SET titre=?, description=?, source_key=?, source_uid=? WHERE id=?');
    $del = $db->prepare('DELETE FROM taches WHERE id=?');

    
    try {
        foreach ($wanted as $key => $payload) {
            if (isset($existing[$key])) {
                $upd->execute([$payload['titre'], $payload['description'], $payload['source_key'], $key, $existing[$key]]);
                unset($existing[$key]);
            } else {
                $ins->execute([
                    $projetId,
                    $payload['titre'],
                    $payload['description'],
                    'moyenne',
                    'a_faire',
                    $payload['assigne_a'],
                    $payload['source_key'],
                    'a_faire',
                    $key
                ]);
            }
        }

        // Supprime les tâches automatiques correspondant aux S.T. supprimées
        // ainsi que les anciennes tâches automatiques issues des S.F.
        foreach ($existing as $idLeft) {
            $del->execute([$idLeft]);
        }
        
    } catch (Throwable $e) {
        
        throw $e;
    }

    });
}
/** Clés des onglets supprimés (Performance, Environnement, Technique, Support) */
