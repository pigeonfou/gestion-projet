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

## Mise à jour et rollback

Le projet fournit deux scripts d'exploitation pour mettre à jour le serveur ou revenir à une version précédente :

- `scripts/update_gestion_projet.sh` : mise à jour vers une branche Git ;
- `scripts/rollback_gestion_projet.sh` : retour interactif vers l'un des 10 derniers commits de la branche.

Les deux scripts sont prévus pour être exécutés sur le serveur avec les droits administrateur.

### Mise à jour

Pour mettre à jour la branche `main` :

```bash
cd /var/www/html/gestion-projet
sudo bash scripts/update_gestion_projet.sh
```

La branche `main` est utilisée par défaut. Une autre branche peut être indiquée :

```bash
sudo bash scripts/update_gestion_projet.sh Stock
```

Le script :

1. sauvegarde la base SQLite dans `/var/backups/gestion-projet/` ;
2. récupère les modifications depuis GitHub ;
3. vérifie que la branche demandée existe ;
4. bascule sur cette branche ;
5. met à jour le code avec `git reset --hard origin/<branche>` ;
6. vérifie la syntaxe de tous les fichiers PHP ;
7. vérifie la configuration Apache puis recharge Apache.

Le script affiche à la fin le commit effectivement installé et le chemin de la sauvegarde de la base.

> **Important :** les éventuelles modifications locales du code sont écrasées par la mise à jour. Le script sauvegarde la base avant l'opération, mais pas les modifications locales du dépôt.

### Rollback

Pour revenir à une version précédente de `main` :

```bash
cd /var/www/html/gestion-projet
sudo bash scripts/rollback_gestion_projet.sh
```

Une autre branche peut également être indiquée :

```bash
sudo bash scripts/rollback_gestion_projet.sh Stock
```

Le script :

1. vérifie l'état du dépôt et refuse l'opération en présence de modifications locales ;
2. récupère la branche depuis GitHub ;
3. affiche les 10 derniers commits disponibles ;
4. demande de sélectionner la version à restaurer ;
5. demande une confirmation explicite en saisissant `ROLLBACK` ;
6. sauvegarde la base SQLite avant l'opération ;
7. crée une branche locale de sauvegarde du commit actuellement installé ;
8. revient au commit sélectionné, vérifie PHP et recharge Apache.

Le rollback **ne restaure pas la base de données**. Une sauvegarde de la base est créée avant le rollback dans `/var/backups/gestion-projet/`, mais elle n'est pas automatiquement réinjectée. Cette précaution évite qu'un retour du code ne détruise des données créées par une version plus récente.

### Sauvegardes de la base

Les scripts créent leurs sauvegardes dans :

```text
/var/backups/gestion-projet/
```

Il est recommandé de conserver ces sauvegardes et de prévoir une stratégie de sauvegarde externe régulière pour la base SQLite de production.

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
