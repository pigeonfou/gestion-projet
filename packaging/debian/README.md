# OddWorks standalone Ubuntu 24.04 / 26.04

## Construire le paquet

Depuis une copie du code source (GitHub n'est pas nécessaire pour installer le paquet) :

```bash
bash packaging/debian/build.sh 1.0.0
```

Le résultat est `dist/oddworks_1.0.0_all.deb`. Copier ce fichier et `packaging/debian/install-ubuntu.sh` sur le serveur cible.

## Installer

```bash
sudo bash install-ubuntu.sh ./oddworks_1.0.0_all.deb
sudo oddworks-configure
sudo oddworks-web-setup
```

Le script d'installation utilise les dépôts APT Ubuntu pour installer les dépendances système, puis PyPI pour installer `aspose-email-foss` dans un environnement Python isolé. Une connexion aux dépôts de paquets est donc nécessaire pendant l'installation, mais **aucun accès GitHub n'est requis**. Il ne lance pas de mise à niveau générale du système.

Le code est dans `/usr/share/oddworks`, la base dans `/var/lib/oddworks/database.sqlite`, les sessions dans `/var/lib/oddworks/sessions`. Le site est configuré sur `127.0.0.1:18081`, volontairement non exposé sur le réseau. La publication externe est hors périmètre.

## Mises à jour et désinstallation

Installer un nouveau paquet avec `sudo apt install ./oddworks_VERSION_all.deb`. Le gestionnaire de paquets remplace le code sans effacer les données dans `/var/lib/oddworks`. Vérifier les migrations et sauvegarder SQLite avant toute mise à jour de production. La suppression du paquet ne supprime pas volontairement les données. La configuration Nginx/PHP-FPM créée séparément doit être gérée explicitement.

## Limites de la version préparatoire

Les scripts doivent encore être validés sur Ubuntu 24.04 et 26.04, y compris la configuration Nginx/PHP-FPM, les migrations SQLite, les dépendances Python et les tests fonctionnels. Ne pas utiliser sur un serveur de production avant cette recette.
