# Règles de travail OddWorks

Lire ce fichier avant toute modification ou livraison. Il s’applique à tout le dépôt.

## Serveurs

- DEV : 192.168.6.28. Utiliser le workflow GitHub Actions de développement existant.
- PROD : 192.168.4.9, machine majiflow. La procédure ci-dessous est obligatoire.
- Ne pas confondre un déploiement DEV, une construction de paquet et une validation PROD.

## Paquets Debian destinés à PROD — procédure obligatoire

1. Source unique : dépôt GitHub `pigeonfou/gestion-projet`.
2. Branche de packaging : `extraction-oddworks-autonome`.
3. Enregistrer toutes les corrections, scripts et configurations dans Git avant construction.
4. Construire exclusivement avec GitHub Actions, workflow **OddWorks - construire paquet Debian**,
   fichier `.github/workflows/package-debian.yml`, sur **Ubuntu 24.04**.
5. Livrer exclusivement l’artefact **oddworks-deb-ubuntu-24.04** de cette exécution réussie.
6. Identifier version, commit complet, URL de l’exécution et empreinte SHA256 du .deb livré.
7. Tester l’installation et le fonctionnement sur **majiflow** avant de déclarer la version validée.

Interdictions : reconstruire à partir d’un ancien .deb, récupérer des morceaux d’un ancien
paquet, injecter des corrections uniquement dans le paquet, construire localement un paquet
à livrer en PROD, ou remplacer ce parcours par une méthode improvisée.

Si la branche, le workflow, ses permissions ou l’artefact sont indisponibles, signaler le
blocage et ne pas livrer un paquet de substitution. Ne pas modifier ni contourner les
gardes de construction pour accomplir une livraison ordinaire.

Une réussite CI signifie « construit et testé en CI », jamais « validé sur majiflow ».
Conserver la preuve de recette sur majiflow séparément. Une demande de construction n’est
pas une autorisation d’installation sur PROD.

Voir `packaging/README.md` pour la procédure complète. Ces règles doivent rester présentes
sur `main` et `extraction-oddworks-autonome` lors des merges, extractions et reprises.
