# ProjectFlow — Gestion de projets

Application web PHP de gestion de projets, tâches, utilisateurs et cahiers des charges.

## Prérequis

- Linux (Ubuntu 22.04 / 24.04 recommandé)
- PHP ≥ 8.1 + extensions `pdo_sqlite` et `mbstring`
- Apache 2 + `libapache2-mod-php`
- Git

---

## Déploiement sur un Ubuntu fraîchement installé

Le dépôt contient un installateur interactif : `install/install_ubuntu.sh`.

### Installation recommandée

Depuis une session SSH sur un serveur Ubuntu 22.04 ou 24.04 fraîchement installé :

```bash
sudo apt-get update
sudo apt-get install -y curl ca-certificates
curl -fsSL https://raw.githubusercontent.com/pigeonfou/gestion-projet/main/install/install_ubuntu.sh -o /tmp/projectflow-install.sh
sudo bash /tmp/projectflow-install.sh
```

Le menu permet de :

1. installer/déployer la branche `main` ;
2. vérifier une installation existante ;
3. consulter le journal d'installation.

Pour lancer directement l'installation sans menu :

```bash
sudo bash /tmp/projectflow-install.sh --install
```

### Ce que fait l'installateur

- vérifie qu'il s'agit d'Ubuntu ;
- installe Apache 2, PHP, SQLite, Git et les extensions PHP nécessaires ;
- clone ou met à jour `https://github.com/pigeonfou/gestion-projet.git` sur la branche `main` ;
- installe le projet dans `/var/www/html/gestion-projet` ;
- crée `/var/lib/projectflow` avec les droits nécessaires ;
- initialise la base SQLite si elle n'existe pas ;
- demande éventuellement le mot de passe initial du compte `admin` ;
- applique les migrations du schéma et initialise les paramètres ;
- configure Apache pour autoriser le `.htaccess` du projet ;
- interdit l'accès HTTP au répertoire `install/` ;
- effectue un test HTTP local ;
- écrit le journal dans `/var/log/projectflow-install.log`.

La base SQLite reste hors du DocumentRoot :

```
/var/lib/projectflow/database.sqlite
```

L'installateur **ne réinitialise jamais une base existante**.

### Accès

Après installation :

```
http://IP_DU_SERVEUR/gestion-projet/
```

Le compte initial est :

```
identifiant : admin
```

Le mot de passe est soit celui saisi pendant l'installation, soit celui affiché une seule fois par `install/init_database.php` lorsqu'il est généré automatiquement.

### Pare-feu (optionnel)

Si `ufw` est installé :

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

### Installation manuelle

La procédure manuelle reste possible si nécessaire :

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php php-sqlite3 php-mbstring libapache2-mod-php git
cd /var/www/html
sudo git clone --branch main https://github.com/pigeonfou/gestion-projet.git
sudo chown -R www-data:www-data gestion-projet
sudo install -d -o www-data -g www-data -m 750 /var/lib/projectflow
cd /var/www/html/gestion-projet
sudo -u www-data php install/init_database.php
```

Après l'initialisation manuelle, une première exécution de l'application applique les migrations du schéma. L'installateur automatique effectue cette étape immédiatement et vérifie également Apache et SQLite.

---

## Configuration du chemin de base

Le fichier `config/config.php` contient :

```php
define('BASE_PATH', '/gestion-projet');
// DB_PATH peut être surchargé par la variable d'environnement PROJECTFLOW_DB_PATH.
```

- Laissez `/gestion-projet` si le projet est dans un sous-dossier (recommandé).
- Mettez `''` (chaîne vide) si vous placez le projet directement à la racine du DocumentRoot.

---

## Structure

```
gestion-projet/
├── admin/               # Gestion des utilisateurs
├── assets/              # CSS & JS
├── config/              # Configuration (BASE_PATH + DB)
├── includes/            # Auth, header, footer
├── install/             # Script d’initialisation de la base
├── index.php
├── login.php
├── projets.php
├── projet.php
├── tache.php
├── cahier.php
└── README.md
```

## Sécurité

- Mots de passe hashés (`password_hash` / `password_verify`)
- Requêtes préparées PDO
- Échappement HTML (`htmlspecialchars`)
- Vérification des droits à chaque action sensible
- Protection CSRF des mutations
- Suppression des actions destructives en GET
- Sessions sécurisées et régénérées à la connexion
- Base SQLite hors du DocumentRoot
