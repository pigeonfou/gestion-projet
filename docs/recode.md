# Recode : exploitation et vérifications

Cette branche poursuit NewUxUi sans changement de framework. Les règles R1b, les rapports figés, les références et la hiérarchie des S.T. restent couverts par les tests existants. Les estimations inconnues et les panneaux réglables des derniers commits NewUxUi sont conservés.

## Changements

- Contrôle des accès projet avant lecture, protection CSRF des anciens formulaires et sessions réévaluées après modification du compte. Une reconnexion est nécessaire pour les sessions créées avant cette version.
- Transactions pour les écritures du cahier, les composants, les tâches générées, les décisions et les mouvements de stock. Un formulaire de spécifications périmé est refusé explicitement.
- Identités immuables des S.F., S.T. et composants. Le numéro affiché peut changer sans réattribuer une tâche ou son historique. Les tâches dont la source disparaît sont conservées avec une clé `removed:<id>`.
- Services séparés pour les comptes, les décisions, le stock et les documents ; vues extraites des principales pages. Calculs numériques partagés et index DOM pour les éditeurs S.T.
- Migration SQL versionnée `001_recode.php`, index de recherche et registre des opérations documentaires. Les fichiers historiques de recette se trouvent dans `docs/recette/`.

## Vérifier une version

Prérequis : PHP 8.1 ou supérieur avec PDO SQLite, SQLite3, mbstring et cURL ; Node.js et Python 3. Les tests utilisent des bases temporaires et un serveur HTTP local éphémère.

```sh
php scripts/test.php
python3 tests/recode_http.py
```

Le premier script vérifie la syntaxe PHP/JavaScript et toutes les suites PHP. Le second exerce la connexion, les permissions et les formulaires réels avec des cookies de session. `PROJECTFLOW_PHP_BINARY` et `PROJECTFLOW_NODE_BINARY` permettent de sélectionner les exécutables.

## Déploiement et retour arrière

Le workflow `deploy.yml` accepte `recode`, vérifie une archive du commit candidat puis appelle le déployeur système existant. Il contrôle ensuite que le commit installé correspond au commit testé. Le runner doit disposer des prérequis ci-dessus. Le programme `/usr/local/sbin/gestion-projet-deploy` reste administré sur le serveur ; son contenu ne figure pas dans ce dépôt.

Les scripts de mise à jour et de retour arrière utilisent une sauvegarde cohérente SQLite via `scripts/backup_database.php`, suivie de `PRAGMA quick_check`. Conserver la sauvegarde et le commit déployé ensemble. Le retour vers un code antérieur aux identités stables est refusé par le script : restaurer la base correspondante dans le cadre d’une maintenance préparée, sans remplacer une base active par une copie brute.

Les migrations sont exécutées dans une transaction lors de l’initialisation. Une migration impossible produit une réponse 503 et une erreur dans le journal serveur ; elle ne doit pas être ignorée.

## Reprise documentaire

L’envoi binaire à Nextcloud enregistre une intention avant l’appel distant. Les états `pending`, `uploaded`, `confirmed` distinguent l’intention, le succès distant et l’enregistrement local. Après une interruption :

```sh
php scripts/reconcile_documents.php
```

Seules les opérations `uploaded` dont le fichier distant existe sont finalisées automatiquement, sans doublon. Pour `pending`, vérifier manuellement le résultat distant avant toute nouvelle tentative : une réponse réseau absente ne prouve pas que le fichier n’a pas été reçu. Les envois Markdown existants conservent leur chemin spécifique. Aucun plafond arbitraire de taille, de durée ou de type de fichier n’est ajouté.

Les identifiants Nextcloud peuvent provenir de l’environnement. Le mot de passe enregistré n’est jamais renvoyé dans le formulaire d’administration ; un champ vide conserve sa valeur. Utiliser une URL HTTPS sans identifiants intégrés.
