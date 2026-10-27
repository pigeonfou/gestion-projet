# OddWorks — paquet autonome Ubuntu 24.04 et 26.04

## Construire

```bash
bash packaging/debian/build.sh 1.0.0
```

## Installer

Copier `dist/oddworks_1.0.0_all.deb` sur le serveur Ubuntu, puis :

```bash
sudo apt update
sudo apt install ./oddworks_1.0.0_all.deb
```

APT télécharge les dépendances système depuis les dépôts configurés. Lors de la configuration du paquet, OddWorks crée la base SQLite et le compte administrateur, installe les dépendances Python d'export Outlook dans un environnement virtuel, puis configure un serveur Nginx et un pool PHP-FPM dédiés. Aucun accès GitHub ni OneForAll n'est nécessaire. PyPI doit être accessible pour installer les dépendances Python.

Application : `http://127.0.0.1:18081/login.php` (accessible seulement depuis la machine).

Identifiant initial : `admin`. Le mot de passe généré est conservé dans un fichier accessible à root uniquement :

```bash
sudo cat /var/lib/oddworks/initial-admin-password
```

Changez ce mot de passe à la première connexion, puis supprimez le fichier initial.

## Mise à jour

```bash
sudo apt update
sudo apt install ./oddworks_VERSION_all.deb
```

Les données persistantes sont dans `/var/lib/oddworks` et ne sont pas incluses dans les fichiers remplacés par le paquet. Les configurations web existantes sont conservées lors des mises à jour. Sauvegardez la base avant une mise à jour de production.

## Important

La recette d'installation réelle sur Ubuntu 24.04 et 26.04, la migration de données et le fonctionnement de l'export Outlook restent à valider. Le paquet ne doit pas être déployé en production avant ces tests. Aucune mise à niveau générale du serveur n'est déclenchée.
