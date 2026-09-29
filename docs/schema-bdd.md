# ProjectFlow — Schéma base de données (SQLite)

## Vue d’ensemble des relations

```
utilisateurs ──< projets (createur_id)
utilisateurs ──< documents (uploader_id)
projets ──1:1── cahiers
projets ──< taches
projets ──< documents
projets ──< stock_usages
cahiers ──< fonctions | materiel | environnement | livrables | jalons
stock_articles ──< stock_article_fournisseur >── stock_fournisseurs
stock_articles ──< stock_usages
```

## Tables

### utilisateurs
| Colonne | Type | Notes |
|---------|------|--------|
| id | INTEGER PK | |
| identifiant | TEXT UNIQUE | |
| mot_de_passe | TEXT | hash |
| role | TEXT | admin / utilisateur |

### projets
| Colonne | Type | Notes |
|---------|------|--------|
| id | INTEGER PK | |
| nom, description | TEXT | |
| createur_id | FK → utilisateurs | |
| current_step | INTEGER | processus R1b (0–8) |
| go_decision | TEXT | GO / NO_GO |
| step_notes | TEXT | |
| status | TEXT | actif / archive |
| cadrage_commerciale, cadrage_technique | INTEGER | étape 0 |
| cadrage_destination | TEXT | interne / externe |

### taches
| Colonne | Type | Notes |
|---------|------|--------|
| id | INTEGER PK | |
| projet_id | FK → projets | |
| titre, description | TEXT | |
| priorite | TEXT | basse…urgente |
| statut | TEXT | a_faire / en_cours / terminee |
| assigne_a | TEXT | identifiant user |
| source_key | TEXT | sync étape 4 |
| kanban_status | TEXT | a_faire / en_cours / validation / terminee |

### cahiers (+ legacy)
| Colonne | Type | Notes |
|---------|------|--------|
| id | INTEGER PK | |
| projet_id | FK UNIQUE → projets | 1 cahier / projet |
| specs_json | TEXT | CDC structuré |
| categorie, contexte, objectifs… | TEXT | ancien formulaire |

**Enfants cahier :** fonctions, materiel, environnement, livrables, jalons

### documents
projet_id FK, phase, nom_fichier, chemin_nextcloud, uploader_id FK

### parametres
cle PK, valeur (config Nextcloud, site…)

### Stocks
- **stock_articles** : reference UNIQUE, type piece|equipement, quantités, valeur, doc
- **stock_fournisseurs** : nom, contact…
- **stock_article_fournisseur** : N–N article ↔ fournisseur
- **stock_usages** : article + projet (historique utilisation)
