# Stock & inventaire

`inventaire.php` remplace les entrées Stocks & Matériel et Stock avancé. Les anciennes adresses `stocks.php` et `stock_pilotage.php` redirigent après authentification. Les autres écrans historiques ne sont pas supprimés ; leurs données restent conservées.

Le module utilise PHP et la base SQLite OddWorks existants. Il n’ajoute aucun service serveur. Les dépendances navigateur mentionnées dans le workflow de recette sont installées uniquement sur les machines éphémères GitHub Actions.

## Données

La migration est additive. Les composants, catégories, emplacements, lots, unités, réservations et mouvements existants sont réutilisés. Les références fournisseur existantes sont reprises une seule fois. Aucun jeu de démonstration n’est importé. Les paramètres, références commerciales enrichies et historiques d’action disposent de tables complémentaires.

Le journal des mouvements détermine les soldes physiques par composant, lot, série et emplacement. Le disponible correspond au stock accepté diminué des réservations actives. Les réceptions à contrôler ou refusées restent visibles physiquement. Les anciens lots de quarantaine sans mouvement sont affichés explicitement et ne créent une réception qu’à leur libération. Une entrée historique sans lot ne peut recevoir arbitrairement un contrôle de lot.

Le prix de référence est le maximum des prix unitaires des lots reçus. Les valeurs anciennes sans réception chiffrée ne sont pas converties artificiellement en prix d’achat. Les prix fournisseur sont indicatifs ; la saisie s’effectue en EUR HT par unité, et la quantité par emballage est conservée séparément.

Les modifications de suivi/unité sont refusées dès qu’un historique existe. Le verrouillage protège les modifications de la fiche et de son stock. Une variante conserve un lien avec sa référence d’origine et démarre sans stock ni document. Un emplacement structurel ne reçoit aucun stock. Les cycles et noms frères identiques sont refusés.

## Sessions et écritures

Les accès utilisent les comptes OddWorks. Chaque écriture nécessite une session et un jeton CSRF ; les opérations projet nécessitent les droits de contribution au projet. Les suppressions de catégories utilisées sont refusées. Chaque mutation est atomique, journalisée et soumise à une révision optimiste sous verrou SQLite `BEGIN IMMEDIATE`. En cas de conflit, recharger les données puis ressaisir la modification.

Les images PNG/JPEG/WebP sont validées côté serveur et conservées avec la fiche (2 Mo maximum). Les fichiers documentaires sont transférés vers le Nextcloud déjà configuré dans un espace distinct `OddWorks-Stock/composants/<id>/...`. Les projets ne reçoivent pas de copies. Le téléchargement est authentifié, via le serveur, sans exposer les identifiants WebDAV. Une panne Nextcloud doit être signalée par le formulaire ; elle ne doit pas produire de faux document disponible.

## Recette et déploiement

`tests/inventory_catalogue.php` teste sur une base SQLite temporaire les identités fabricant/référence, variantes, catégories, emplacements, paramètres, lots, séries, états qualité, prix, réservations, verrous et annulations atomiques. `tests/inventory_browser.cjs` teste les parcours sur le serveur PHP éphémère du workflow `inventory.yml`, y compris rechargement, session, CSRF et conflits.

Le workflow existant `deploy.yml` sauvegarde SQLite puis déploie le commit exact de `main` sur **192.168.6.28**. Il ne déploie pas le serveur de production **192.168.4.9**. Le contrôle public du déploiement vérifie la connexion ; un contrôle authentifié des données réelles et un transfert Nextcloud doivent également être faits sur l’environnement cible avant validation d’exploitation.

Les ordres et consommations de production restent du périmètre MajiProd. Le module ne crée ni ordre de fabrication ni transfert automatique de BOM.
