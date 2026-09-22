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
        'contexte_utilisation' => [],
        'contexte_autre' => '',
        'objectifs' => '',
        'resultats_attendus' => '',
        'cas_usage' => '',
        'profils_utilisateurs' => '',
        'contraintes_operationnelles' => '',
        // 2. Fonctionnelles
        'fonctionnalites' => '',
        'fonctionnalites_cochees' => [],
        'connectivite' => [],
        'connectivite_autre' => '',
        'luminosite_nits' => '',
        'tactile_multitouch' => '',
        'usage_gants' => '',
        'anti_reflet' => '',
        'taille_ecran' => '',
        'ihm_accessoires' => '',
        // 3. Non fonctionnelles
        'processeur' => '',
        'ram' => '',
        'stockage' => '',
        'autonomie' => '',
        'fiabilite' => '',
        'securite' => '',
        'maintenabilite' => '',
        // 4. Environnementales
        'ip' => '',
        'ip_autre' => '',
        'chute_metres' => '',
        'vibrations' => '',
        'temp_fonc_min' => '',
        'temp_fonc_max' => '',
        'temp_stock_min' => '',
        'temp_stock_max' => '',
        'humidite' => '',
        'brouillard_salin' => '',
        'altitude_max' => '',
        'uv' => '',
        'cem' => '',
        'normes' => [],
        'normes_autre' => '',
        'niveau_durcissement' => '',
        // 5. Techniques
        'architecture' => '',
        'compatibilite' => '',
        'alimentation' => '',
        'consommation_max' => '',
        'materiaux' => '',
        'conformites' => '',
        // 6. Opérationnelles
        'mode_installation' => '',
        'maintenance' => '',
        'garantie' => '',
        'formation_doc' => '',
        'pieces_sav' => '',
        'cycle_vie' => '',
        // 7. Planning
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
    return array_merge(emptySpecs(), $data);
}

function saveSpecs(int $cahierId, array $specs): void {
    ensureCahierSpecsColumn();
    $json = json_encode($specs, JSON_UNESCAPED_UNICODE);
    $stmt = getDB()->prepare('UPDATE cahiers SET specs_json = ?, date_maj = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$json, $cahierId]);
}

function generateCahierText(array $s, string $projetNom): string {
    $lines = [];
    $lines[] = "CAHIER DES CHARGES";
    $lines[] = "Projet : " . $projetNom;
    $lines[] = "Date : " . date('d/m/Y H:i');
    $lines[] = str_repeat('=', 60);

    $lines[] = "\n1. CONTEXTE, OBJECTIFS ET BESOINS UTILISATEURS";
    $lines[] = "Contexte d'utilisation : " . implode(', ', $s['contexte_utilisation'] ?? []) . ($s['contexte_autre'] ? ' — ' . $s['contexte_autre'] : '');
    $lines[] = "Objectifs :\n" . ($s['objectifs'] ?: '—');
    $lines[] = "Résultats attendus :\n" . ($s['resultats_attendus'] ?: '—');
    $lines[] = "Cas d'usage :\n" . ($s['cas_usage'] ?: '—');
    $lines[] = "Profils utilisateurs :\n" . ($s['profils_utilisateurs'] ?: '—');
    $lines[] = "Contraintes opérationnelles :\n" . ($s['contraintes_operationnelles'] ?: '—');

    $lines[] = "\n2. EXIGENCES FONCTIONNELLES";
    $lines[] = "Fonctionnalités :\n" . ($s['fonctionnalites'] ?: '—');
    if (!empty($s['fonctionnalites_cochees'])) $lines[] = "Options : " . implode(', ', $s['fonctionnalites_cochees']);
    $lines[] = "Connectivité : " . implode(', ', $s['connectivite'] ?? []) . ($s['connectivite_autre'] ? ' — ' . $s['connectivite_autre'] : '');
    $lines[] = "Affichage : luminosité {$s['luminosite_nits']} nits, tactile multi-touch : {$s['tactile_multitouch']}, gants : {$s['usage_gants']}, anti-reflet : {$s['anti_reflet']}, taille : {$s['taille_ecran']}";
    $lines[] = "IHM / accessoires :\n" . ($s['ihm_accessoires'] ?: '—');

    $lines[] = "\n3. EXIGENCES NON FONCTIONNELLES / PERFORMANCE";
    $lines[] = "Processeur : {$s['processeur']} | RAM : {$s['ram']} | Stockage : {$s['stockage']} | Autonomie : {$s['autonomie']}";
    $lines[] = "Fiabilité :\n" . ($s['fiabilite'] ?: '—');
    $lines[] = "Sécurité :\n" . ($s['securite'] ?: '—');
    $lines[] = "Maintenabilité / évolutivité :\n" . ($s['maintenabilite'] ?: '—');

    $lines[] = "\n4. EXIGENCES ENVIRONNEMENTALES ET DURCISSEMENT";
    $lines[] = "IP : " . ($s['ip'] === 'autre' ? $s['ip_autre'] : $s['ip']);
    $lines[] = "Chute : {$s['chute_metres']} m | Vibrations : {$s['vibrations']}";
    $lines[] = "Temp. fonctionnement : {$s['temp_fonc_min']} à {$s['temp_fonc_max']} °C";
    $lines[] = "Temp. stockage : {$s['temp_stock_min']} à {$s['temp_stock_max']} °C";
    $lines[] = "Humidité : {$s['humidite']} | Brouillard salin : {$s['brouillard_salin']}";
    $lines[] = "Altitude max : {$s['altitude_max']} | UV : {$s['uv']}";
    $lines[] = "CEM :\n" . ($s['cem'] ?: '—');
    $lines[] = "Normes : " . implode(', ', $s['normes'] ?? []) . ($s['normes_autre'] ? ' — ' . $s['normes_autre'] : '');
    $lines[] = "Niveau de durcissement : {$s['niveau_durcissement']}";

    $lines[] = "\n5. CONTRAINTES TECHNIQUES, RÉGLEMENTAIRES ET D'INTÉGRATION";
    $lines[] = "Architecture :\n" . ($s['architecture'] ?: '—');
    $lines[] = "Compatibilité :\n" . ($s['compatibilite'] ?: '—');
    $lines[] = "Alimentation : {$s['alimentation']} | Conso. max : {$s['consommation_max']}";
    $lines[] = "Matériaux :\n" . ($s['materiaux'] ?: '—');
    $lines[] = "Conformités :\n" . ($s['conformites'] ?: '—');

    $lines[] = "\n6. CONTRAINTES OPÉRATIONNELLES, LOGISTIQUES ET SUPPORT";
    $lines[] = "Installation : {$s['mode_installation']}";
    $lines[] = "Maintenance :\n" . ($s['maintenance'] ?: '—');
    $lines[] = "Garantie : {$s['garantie']}";
    $lines[] = "Formation / documentation :\n" . ($s['formation_doc'] ?: '—');
    $lines[] = "Pièces / SAV :\n" . ($s['pieces_sav'] ?: '—');
    $lines[] = "Cycle de vie :\n" . ($s['cycle_vie'] ?: '—');

    $lines[] = "\n7. PLANNING ET LIVRABLES";
    $lines[] = "Délais :\n" . ($s['delais'] ?: '—');
    $lines[] = "Livrables :\n" . ($s['livrables_attendus'] ?: '—');

    return implode("\n", $lines);
}
