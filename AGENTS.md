# Règles de travail OddWorks

Lire ce fichier avant toute modification ou livraison. Il s’applique à tout le dépôt.

## Serveurs

- DEV : 192.168.6.28. Utiliser le workflow GitHub Actions de développement existant.
- PROD : 192.168.4.9, machine majiflow. La procédure ci-dessous est obligatoire.
- Ne pas confondre un déploiement DEV, une construction de paquet et une validation PROD.

## Serveur DEV — procédure obligatoire

Pour toute modification fonctionnelle de DEV (192.168.6.28), utiliser le workflow
historique **Déployer OddWorks sur .28**, fichier `.github/workflows/deploy.yml`,
depuis le dépôt `pigeonfou/gestion-projet`.

1. Enregistrer les modifications dans Git, les contrôler et les intégrer à `main`.
2. Le push sur `main` déclenche les tests PHP et les régressions GitHub Actions.
3. Après réussite, le runner `oddworks-28` vérifie l’IP 192.168.6.28 et les services
   `oneforall-oddworks-web.service` et `oneforall-nginx.service`.
4. Le workflow déploie le commit exact avec sauvegarde SQLite via le programme
   administrateur `oddworks-deploy`, puis vérifie la page locale et
   `https://www.pigeonfou.com/oddworks/login.php`.
5. Vérifier les parcours authentifiés concernés lorsque l’accès le permet ; signaler
   explicitement les contrôles non réalisés et ne pas confondre connexion HTTP et recette métier.

Ne pas remplacer ce parcours par une installation Debian sur DEV, un déploiement manuel
SSH ou le workflow autonome. Ne pas renommer ou migrer les services OneForAll pour une
modification ordinaire. Si le runner ou le workflow échoue, corriger dans Git ou signaler
le blocage sans improviser un autre déploiement.

Voir `docs/DEPLOY_ODDWORKS_28.md`. Pour PROD, utiliser exclusivement la procédure Debian
ci-dessous : le workflow DEV ne déploie pas 192.168.4.9/majiflow.

## Paquets Debian destinés à PROD — procédure obligatoire

1. Source unique : dépôt GitHub `pigeonfou/gestion-projet`.
2. Branche de packaging : `extraction-oddworks-autonome`.
3. Enregistrer toutes les corrections, scripts et configurations dans Git avant construction.
4. Construire exclusivement avec GitHub Actions, workflow **OddWorks - construire paquet Debian**,
   fichier `.github/workflows/package-debian.yml`, sur **Ubuntu 24.04**.
5. Livrer exclusivement le .deb de cette exécution réussie : artefact **oddworks-deb-ubuntu-24.04** ou fichier identique publié par ce workflow dans GitHub Releases.
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

## Numérotation et téléchargement direct

Le dernier numéro historique consommé est `1.0.2` (fichier
`packaging/debian/last-legacy-version`). Le workflow prend le plus grand numéro entre
cet historique et les tags de version, incrémente le correctif, puis réserve atomiquement
`oddworks-deb-vX.Y.Z` sur le commit source avant construction. Les tags réservés ne doivent
pas être supprimés ni réutilisés, même après échec ou relance. Une relance réserve un nouveau
numéro. Les exécutions sont sérialisées pour éviter les collisions.

Après les tests et la conservation de l’artefact officiel, le workflow publie exactement les
mêmes fichiers (.deb, provenance, empreintes) dans une GitHub Release marquée préversion,
avec le statut « recette majiflow à effectuer ». Le résumé Actions fournit un lien direct
vers le .deb, sans ZIP. Cette publication n’est pas une validation sur majiflow. Ne jamais
remplacer les fichiers d’une version existante : corriger dans Git et produire la suivante.

## Correspondance obligatoire avec DEV

Avant toute livraison, `packaging/debian/dev-baseline.json` doit désigner le commit
réellement déployé avec succès par le workflow DEV et son exécution. Le workflow Debian
vérifie ce déploiement et compare tous les fichiers applicatifs au commit référencé.
Les seules différences admises sont des adaptations autonomes explicitement justifiées
et identifiées par leurs empreintes dans ce fichier. Toute autre différence bloque le
paquet. Mettre à jour cette référence et reporter les changements DEV dans Git avant une
nouvelle livraison ; une branche de packaging ancienne n’est pas une version DEV validée.
La provenance du paquet contient aussi cette référence DEV.

## Expérimentation explicitement demandée : études en fiches

Sur la branche `experimentation/etudes-fiches`, la demande utilisateur autorise un
 déploiement de cette branche sur DEV sans intégration à main. Utiliser le workflow
historique et le programme administrateur distinct limité à cette branche ; ne pas
modifier le programme main installé. L’installation de cette permission supplémentaire
reste un acte administrateur, le runner n’ayant pas les droits correspondants.
Voir `docs/EXPERIMENTATION_ETUDES_FICHES.md`. Cette exception ne concerne jamais PROD.
