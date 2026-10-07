<?php
/**
 * Initialisation de la base de données SQLite
 * Usage : php install/init_database.php
 */
$dbPath = getenv('PROJECTFLOW_DB_PATH') ?: '/var/lib/projectflow/database.sqlite';
$dbDir = dirname($dbPath);
if (!is_dir($dbDir) && !mkdir($dbDir, 0770, true) && !is_dir($dbDir)) {
    fwrite(STDERR, "Impossible de créer le répertoire de base : $dbDir\n");
    exit(1);
}

if (file_exists($dbPath)) {
    echo "La base existe déjà : $dbPath\n";
    echo "Supprimez-la manuellement pour la recréer.\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');

    $db->exec("CREATE TABLE utilisateurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifiant TEXT UNIQUE NOT NULL,
        mot_de_passe TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'utilisateur' CHECK(role IN ('admin','utilisateur')),
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE projets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        description TEXT,
        createur_id INTEGER NOT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (createur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE taches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL,
        titre TEXT NOT NULL,
        description TEXT,
        priorite TEXT NOT NULL DEFAULT 'moyenne' CHECK(priorite IN ('basse','moyenne','haute','urgente')),
        statut TEXT NOT NULL DEFAULT 'a_faire' CHECK(statut IN ('a_faire','en_cours','terminee')),
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_echeance DATE,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE cahiers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER UNIQUE NOT NULL,
        categorie TEXT, contexte TEXT, objectifs TEXT, contraintes TEXT,
        dates_info TEXT, budget TEXT, ressources TEXT, risques TEXT, criteres_reussite TEXT,
        date_maj DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE fonctions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cahier_id INTEGER NOT NULL, nom TEXT NOT NULL, description TEXT, obligatoire INTEGER DEFAULT 0,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE materiel (
        id INTEGER PRIMARY KEY AUTOINCREMENT, cahier_id INTEGER NOT NULL, description TEXT NOT NULL,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE environnement (
        id INTEGER PRIMARY KEY AUTOINCREMENT, cahier_id INTEGER NOT NULL, element TEXT NOT NULL,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE livrables (
        id INTEGER PRIMARY KEY AUTOINCREMENT, cahier_id INTEGER NOT NULL, description TEXT NOT NULL, date_livraison DATE,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE jalons (
        id INTEGER PRIMARY KEY AUTOINCREMENT, cahier_id INTEGER NOT NULL, nom TEXT NOT NULL, date_prevue DATE,
        FOREIGN KEY (cahier_id) REFERENCES cahiers(id) ON DELETE CASCADE
    )");

    $adminPassword = getenv('PROJECTFLOW_ADMIN_PASSWORD') ?: bin2hex(random_bytes(12));
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO utilisateurs (identifiant, mot_de_passe, role) VALUES (?,?,?)')
       ->execute(['admin', $hash, 'admin']);

    chmod($dbPath, 0660);
    echo "✓ Base créée : $dbPath\n";
    echo "✓ Compte admin : admin\n";
    if (getenv('PROJECTFLOW_ADMIN_PASSWORD') === false) {
        echo "  Mot de passe initial : $adminPassword\n";
    }
    echo "⚠ Changez le mot de passe dès la première connexion !\n";
    echo "ℹ Base : $dbPath\n";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
