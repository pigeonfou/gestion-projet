# Déploiement direct d’OddWorks sur 192.168.6.28

Le runner du dépôt est `oddworks-28`, avec les labels `self-hosted`, `Linux`,
`X64`, `oddworks-28`. Les déploiements de main utilisent ce runner uniquement.
Une branche de préparation lance les contrôles sans déployer.

## Installation administrateur, une seule fois

Depuis une copie de cette version du dépôt :

```bash
sudo bash scripts/install-oddworks-deploy.sh
```

Ce script installe les outils nécessaires et le programme root
`/usr/local/sbin/oddworks-deploy`. Il autorise l’utilisateur `ubuntu` à appeler
uniquement ce programme sans mot de passe. Le programme refuse tout argument
autre qu’un SHA complet de commit appartenant à l’historique de main du dépôt
officiel. Cette autorisation permet au runner de mettre à jour le code et de
lancer les migrations comme utilisateur applicatif `ofa-oddworks`.
Le programme root doit être réinstallé explicitement si son code change.

## Chemins existants conservés

| Élément | Chemin |
| --- | --- |
| Version active | `/opt/oneforall/apps/oddworks/current` |
| Versions | `/opt/oneforall/apps/oddworks/releases/` |
| Base | `/var/lib/oneforall/oddworks/database.sqlite` |
| Sessions | `/var/lib/oneforall/oddworks/sessions/` |
| Sauvegardes | `/var/backups/oddworks/` |
| PHP-FPM | `oneforall-oddworks-web.service` |
| Frontend | `oneforall-nginx.service` |

Les noms historiques sont conservés pour éviter une migration simultanée
d’hébergement. Le déploiement n’appelle aucun orchestrateur OneForAll.
Les fichiers distants Nextcloud, les secrets et les sessions ne sont pas déplacés.

## Séquence et limites

1. Tests PHP dans GitHub sur des bases isolées ; contrôle du serveur et de sa page locale.
2. Récupération du commit exact, validation de son appartenance à main et syntaxe PHP.
3. Préparation d’une version root non modifiable par PHP-FPM.
4. Arrêt du seul PHP-FPM OddWorks : brève indisponibilité pendant sauvegarde et migration.
5. Sauvegarde SQLite cohérente, contrôle d’intégrité, migration sous `ofa-oddworks`.
6. Bascule atomique de `current`, redémarrage PHP-FPM et contrôle de la page locale.
7. Contrôle de la page publique via Cloudflare ; vérification authentifiée dans le navigateur par l’agent.

En cas d’échec avant réouverture du service, la base sauvegardée et l’ancien
lien sont rétablis automatiquement. Après tentative de redémarrage, aucune
restauration automatique de la base : des requêtes pourraient déjà avoir écrit.
Les chemins de sauvegarde et de version précédente sont alors affichés pour
permettre une récupération supervisée. Une sauvegarde n’est pas un test de
restauration complet. Les anciennes versions et sauvegardes ne sont pas purgées.

Les contrôles HTTP vérifient la page de connexion, pas tous les parcours métier.
Un job vert ne remplace pas la vérification authentifiée de la fonctionnalité modifiée.
