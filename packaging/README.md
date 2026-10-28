# Livraison Debian OddWorks pour PROD

Cette procédure est obligatoire pour PROD (192.168.4.9, majiflow), indépendamment de la
conversation ou de l’outil employé. `AGENTS.md` à la racine en impose l’application.

## Préparation

- Dépôt unique : https://github.com/pigeonfou/gestion-projet.
- Valider les modifications sur DEV (192.168.6.28) par son workflow existant.
- Reporter les changements validés dans `extraction-oddworks-autonome` en préservant
  l’architecture autonome et les scripts de packaging. Ne pas fusionner aveuglément une
  configuration OneForAll ou DEV dans la branche de packaging.
- Enregistrer toutes les corrections dans Git, y compris celles du packaging. Laisser le workflow réserver une
  nouvelle version par tag Git avant construction. Aucun fichier provenant d’un ancien .deb.

## Construction officielle

Lancer **OddWorks - construire paquet Debian** (`.github/workflows/package-debian.yml`)
avec la branche `extraction-oddworks-autonome`. Un push sur cette branche lance également
la construction. Le runner est `ubuntu-24.04`.

Le script exige le contexte GitHub Actions du dépôt et de cette branche, le SHA checkouté,
un arbre Git propre et Ubuntu 24.04. La source est exportée directement du commit Git.
Une construction locale, une branche différente ou des modifications non enregistrées
sont refusées. Ne pas simuler ces variables ni contourner ces contrôles pour une livraison.

## Livraison et preuves

Attendre la réussite de **toutes** les étapes : construction, installation et contrôles
fonctionnels CI. Télécharger l’artefact **oddworks-deb-ubuntu-24.04** de cette exécution.
Il contient le .deb, `PROVENANCE.json` et `SHA256SUMS`.

Avant de livrer, vérifier :

1. Dépôt et branche conformes ; exécution verte du workflow officiel.
2. Version et SHA Git complet identiques dans la provenance et dans le paquet
   (`/usr/share/doc/oddworks/source-commit`).
3. Empreinte du .deb conforme à `SHA256SUMS` (`sha256sum -c SHA256SUMS`).
4. Livraison du fichier exact téléchargé : aucune modification ou reconstruction après CI.

Fournir le lien du paquet, sa version, son commit et l’URL de l’exécution. Un fichier sans
cette provenance n’est pas une livraison officielle PROD, même s’il s’installe correctement.

## Validation sur majiflow

La CI sur Ubuntu 24.04 ne remplace pas la recette sur majiflow. Avant installation, vérifier
les chemins et services existants et préparer sauvegarde et retour arrière. L’installation
sur PROD nécessite une instruction qui l’autorise ; ne pas l’inférer d’une simple demande
de fabrication ou téléchargement du paquet.

Consigner le commit, la version, le SHA256, la date, la machine, le résultat d’installation
et les contrôles : connexion, données existantes, projets, stock, paramètres, Nextcloud et
exports concernés. Indiquer explicitement les contrôles non effectués.

Statuts à employer : **construit/testé en CI**, **recette majiflow à faire**, ou **validé sur
majiflow** avec sa preuve. Ne jamais annoncer le dernier statut sans recette effective.

## Blocage

Si le workflow échoue ou si ses accès manquent, corriger dans Git puis relancer le workflow.
Ne pas construire localement, reprendre un ancien .deb ou modifier un paquet téléchargé.
Signaler le blocage lorsqu’il ne peut pas être résolu dans ce parcours.

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
