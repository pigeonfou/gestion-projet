# Projet exécution TEST - LUMI-12

Exercice manuel OddWorks : deux témoins lumineux 12 V et un outil PC C++ de recette.
Tout est fictif : personnes, fournisseur, visas, certificats, SDS, datasheets,
mesures et identifiants Git. Aucun achat, message ni essai matériel réel.

## Commencer

1. Lire `00_Parcours/00_GUIDE_EXECUTION_LUMI12.pdf` (18 épisodes).
2. Créer soi-même le projet `RECETTE-MANUELLE-LUMI12-2026` dans OddWorks.
3. Utiliser les pièces au fil de leur réception dans le récit ; garder les indices.
4. Ouvrir `99_Correction/99_CORRIGE_LUMI12.pdf` seulement après l’exercice.

Le ZIP contient le même dossier, sans le ZIP lui-même. Les tableaux CSV sont des
fiches de saisie, pas des imports automatiques dans OddWorks.

## Histoire et points à vérifier

- Dates métier simulées du 2 au 18 novembre 2026 ; exécution possible en une séance.
- CDC R0.1 refusé, puis R1.0 accepté : ne pas confondre refus documentaire et NO GO.
- 3 S.F. et 8 S.T. couvrant Matériel, Composant, Prestataire, Logiciel, 3D et PCB.
- Quantités/prix par prototype, quantité CDC de 2 : 40 € HT initialement, 43,80 € après
  surcoût PCB et substitution LED, pour un budget global de 60 € HT.
- Hiérarchie parent-enfant indépendante du type de référence S.T.x.x.
- Retard fournisseur et livraison finale +5 jours calendaires / +3 jours ouvrés.
- Mauvais dimensionnement de résistances en A, outil A incomplet, non-conformité,
  DM01, résistances 1 kΩ et outil B, contre-essais et livraison.
- ISO 14001 expiré puis renouvelé ; SDS distincte des certificats et des attestations
  RoHS/REACH. La conformité inconnue n’est pas inventée.
- Lot fictif en quarantaine puis libéré, pas de double entrée de stock.
- Fin du processus à 100 % avec TRL 4 et cible 7 non atteinte.

## Particularités de mise en œuvre

Un seul compte : les personnages représentent des validations métier narrées dans
les documents, pas des utilisateurs à créer ou à incarner. Aucune notification réelle.
Attendre le message de sauvegarde avant de naviguer. Si un clic est ambigu, utiliser
Tab jusqu’au bouton puis Entrée, sans modifier le DOM ni contourner une validation.

Nextcloud peut être absent : garder alors les preuves localement, saisir leurs
références et déclarer le dépôt documentaire NON EXÉCUTÉ. Ne pas inventer de lien.
La production avec ordre d’assemblage nécessite une instruction Nextcloud : ce
sous-parcours s’arrête si aucune instruction n’est disponible. Il n’est pas requis
pour le parcours principal, qui reste une recette documentaire du processus.

Les dates serveur sont réelles et inchangées. Le badge de retard dépend de l’horloge
réelle ; les retards de ce récit sont vérifiés par les dates métier et les notes.
Les quantités des tâches achat générées doivent être vérifiées : ne pas supposer
une multiplication implicite par la quantité globale du CDC.

## Livrables techniques et portée

- `03_Electronique/Revision_A` et `Revision_B` : paires EAGLE XML .sch/.brd avec
  bibliothèque synthétique embarquée, pas de fichiers renommés depuis un autre format.
- `04_Mecanique` : STEP fond, couvercle, carte nue de référence, assemblage ; aperçu SVG.
- `05_Logiciel` : source C++ A/B et SHA fictifs de 40 caractères, non résolubles sur GitHub.
- `02_Fournisseurs` : 4 datasheets, SDS en 16 rubriques et 3 attestations ISO fictives.
- CDC, retours de validation, réunions, retard, accord, DM01, rapports d’essais et PV.

Les fichiers XML et les netlists sont contrôlés ; les STEP sont réimportés et leurs
solides/volumes vérifiés ; les deux programmes sont compilés et leurs décisions testées.
Aucun import dans un éditeur EAGLE/Fusion, ERC/DRC natif ou qualification de fabrication
n’a été réalisé. Le PCB et le boîtier ne sont pas à fabriquer en l’état.
Les documents de sécurité/certification sont impropres à tout usage réglementaire réel.

## Reproduction

`python3 generer_dossier.py` avec ReportLab, pypdf, CadQuery/OpenCascade et g++.
Les rendus PNG de contrôle ne font pas partie du livrable. Les sources, le manifeste
SHA-256 et le rapport de validation sont inclus pour permettre une vérification.
