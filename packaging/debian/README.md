# OddWorks — paquet Debian autonome

## Source unique et reproductibilité
Le dépôt `pigeonfou/gestion-projet`, branche `extraction-oddworks-autonome`, est la source unique. Toute correction doit être commitée **avant** construction. Ne jamais reconstruire un paquet à partir d'un ancien .deb.

Le workflow `.github/workflows/package-debian.yml` construit depuis un checkout GitHub Actions Ubuntu 24.04, teste l'installation, puis publie l'artefact `oddworks-deb-ubuntu-24.04`. Le commit source est enregistré dans `/usr/share/doc/oddworks/source-commit` à l'intérieur du paquet.

## Construire pour PROD
Suivre obligatoirement [la procédure centrale](../README.md). La construction locale
est refusée. Utiliser le workflow **OddWorks - construire paquet Debian** sur la branche
`extraction-oddworks-autonome`, puis récupérer l’artefact `oddworks-deb-ubuntu-24.04`.

## Installer sur Ubuntu 24.04 vierge
```bash
sudo apt update
sudo apt install ./oddworks_1.0.2_all.deb
```

Accès LAN : `http://IP_DU_SERVEUR/login.php` (port 80). Chemins `/gestion-projet/` compatibles. Nginx et PHP-FPM dédiés, base `/var/lib/oddworks/database.sqlite`. Aucune dépendance GitHub ni OneForAll à l'exécution ; APT et PyPI sont nécessaires à l'installation.

Identifiant initial : `admin`. Mot de passe initial : `sudo cat /var/lib/oddworks/initial-admin-password`. Changez-le à la première connexion.

## Mise à niveau
Les données sont conservées dans `/var/lib/oddworks`. Les configurations web OddWorks déjà installées sont préservées. Sauvegarder la base avant toute mise à jour.

## Validation
La CI teste une installation neuve sur Ubuntu 24.04. Une recette sur le snapshot de majiflow reste nécessaire avant diffusion en production.

## Choix et sauvegarde à l’installation (depuis 1.0.5)

Le paquet détecte l’installation avant dépaquetage et propose dans le terminal : mise à
jour (par défaut si une base existe), installation neuve ou annulation. Une installation
neuve sur des données existantes exige de taper NOUVELLE. Elle crée une base vide et un
nouveau compte ; les données antérieures sont conservées, et la configuration web reste
préservée. Une mise à jour conserve la base et les comptes.

Avant tout remplacement d’une installation existante, le pool PHP-FPM dédié OddWorks est
mis en maintenance, les requêtes actives sont drainées, puis le code, les données locales,
les sessions et la configuration Nginx/pool PHP sont archivés sous
`/var/backups/oddworks-install-XXXXXXXX` (root uniquement). Une copie SQLite séparée est
créée par l’API backup et contrôlée par integrity_check. Nextcloud distant n’est pas copié.
Une sauvegarde en échec bloque l’installation. Les autres pools PHP restent actifs.

À la fin d’une première installation réussie, le compte admin et son mot de passe généré
sont affichés directement sur le terminal de contrôle, pas sur la sortie capturée par APT.
En non interactif, le mot de passe n’est pas journalisé ; son chemin root est indiqué.
Une mise à jour ne réaffiche et ne réinitialise jamais le mot de passe.

Sans terminal (notamment CI), le choix automatique conserve une base existante ou fait
une première installation s’il n’existe rien. `ODDWORKS_INSTALL_MODE=update|fresh|cancel`
permet d’imposer un choix ; fresh sur une installation existante exige en plus
`ODDWORKS_CONFIRM_FRESH=YES` si aucun terminal ne permet la confirmation.

En cas d’interruption après préparation, les sauvegardes et l’état sont conservés dans
`/var/lib/oddworks-installer`. Ne pas relancer une nouvelle installation aveuglément :
reprendre `sudo dpkg --configure oddworks` et transmettre l’erreur si elle persiste. Si la
reprise échoue, le pool peut rester en maintenance pour ne pas servir une version partielle.
Aucune restauration automatique de base n’est effectuée après migration.
