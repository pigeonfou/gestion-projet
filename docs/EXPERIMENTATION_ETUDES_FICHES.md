# Expérimentation de l’étape 2 en fiches

Branche dédiée : `experimentation/etudes-fiches`, issue de `main`.
Demande explicite : construire l’expérimentation et la déployer sur DEV sans intégrer à main.
Aucun changement de base : les champs POST, UIDs, références, hiérarchie et calculs sont conservés.
Sans JavaScript, le tableau d’origine reste accessible. Les préférences d’apparence sont réutilisées.
La vue en fiches ne concerne que l’étape 2 du processus.

## Recette

Workflow `Expérimentation études en fiches` : régressions PHP et navigateur sur SQLite jetable,
sauvegarde et rechargement réels, HT/TTC, coûts et délais inconnus, enfants dynamiques,
suppression de parent, références S.T., clair/sombre, densité, mobile et repli sans JS.
Les captures sont conservées dans l’artefact `etudes-fiches-apercus`.

## Déploiement expérimental DEV

Cette demande constitue une exception explicite à la branche main de la procédure habituelle.
Le workflow historique `.github/workflows/deploy.yml` conserve les tests, la vérification
IP/services, la sauvegarde SQLite, l’installation du SHA exact et les contrôles HTTP.
Sur cette branche uniquement, il sélectionne le programme `oddworks-deploy-etudes`.
Ce programme est une copie du programme de référence avec une seule cible approuvée :
`experimentation/etudes-fiches`. Il refuse tout SHA qui n’appartient pas à son historique.
Le programme main et son autorisation sudo ne sont pas modifiés.

Un administrateur doit installer ce programme distinct, une seule fois, sur `.28` :
`sudo bash scripts/install-oddworks-etudes-deploy.sh`, depuis une copie du commit vérifié.
Le workflow refuse de déployer si le programme installé est absent ou différent.
Le runner ne peut pas s’accorder lui-même de nouveaux droits sudo.
Relancer ensuite le job de déploiement du workflow historique.

Le serveur DEV partagé affichera l’expérimentation à tous les utilisateurs. Les modifications
saisies et enregistrées restent des données réelles de DEV. Un futur déploiement main peut
remplacer l’interface ; il conserve ces données puisque leur format ne change pas.
PROD et la construction Debian ne sont pas concernés.

Pour désactiver la permission expérimentale, l’administrateur peut supprimer
`/etc/sudoers.d/oddworks-deploy-etudes` et `/usr/local/sbin/oddworks-deploy-etudes`.
Revenir à la version main par le workflow historique pour retirer l’interface du serveur.
