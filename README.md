# ProjectFlow — Gestion de projets

Application web PHP de gestion de projets, tâches, utilisateurs et cahiers des charges.

## Prérequis

- Linux (Ubuntu 22.04 / 24.04 recommandé)
- PHP ≥ 8.1 + extensions `pdo_sqlite` et `mbstring`
- Apache 2 + `libapache2-mod-php`
- Git

---

## Déploiement (fonctionne du premier coup)

### 1. Mise à jour et installation des paquets

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php php-sqlite3 php-mbstring libapache2-mod-php git
```

### 2. Cloner le projet **dans** `/var/www/html`

```bash
cd /var/www/html
sudo git clone https://github.com/pigeonfou/gestion-projet.git
sudo chown -R www-data:www-data gestion-projet
sudo chmod -R 755 gestion-projet
```

### 3. Initialiser la base de données

La base SQLite est stockée par défaut hors du DocumentRoot, dans `/var/lib/projectflow/database.sqlite`.

```bash
cd /var/www/html/gestion-projet
sudo -u www-data php install/init_database.php
```

Le script génère un mot de passe administrateur aléatoire. Pour fournir un mot de passe précis lors de l'installation :

```bash
sudo -u www-data env PROJECTFLOW_ADMIN_PASSWORD='votre-mot-de-passe' php install/init_database.php
```

### 4. Accéder à l’application

```
http://IP_DU_SERVEUR/gestion-projet/
```

**Compte initial :** `admin` avec le mot de passe affiché par le script d'installation. Il n'existe plus de mot de passe par défaut codé dans le dépôt.

### 5. (Optionnel) Pare-feu

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

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
