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
