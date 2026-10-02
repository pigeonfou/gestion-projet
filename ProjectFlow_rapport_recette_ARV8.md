# ProjectFlow — rapport de recette et simulation ARV-8

DOCUMENT FICTIF — SIMULATION PROJECTFLOW — SANS VALEUR DE CERTIFICATION

## Conclusion factuelle

Le projet fictif 3 « SIMULATION R&D — ARV-8 réseau durci » a parcouru les neuf étapes R1b jusqu’à Archivage validé / vente. Deux prototypes et dix unités de présérie ont été assemblés dans la simulation, avec nomenclature, lots, séries, contrôles, non-conformités et généalogie. Les 42 tâches du projet sont terminées dans la simulation ; aucun achat, paiement, envoi à un tiers ou transport réel n’a eu lieu.

La branche Refonte est déployée au commit d338c0daf92fdaf27f467fb8c33d94eebb9ae5c3. Le pipeline 36984854889 et son job 110767435601 sont réussis : lint PHP, suites métier et déploiement. Les tests comprennent les six types et coûts HT/TTC, décisions R1b, droits/dépendances des tâches, qualité, lots, BOM/production/séries/généalogie, inventaires, réservations, management et contributeurs par projet.

La campagne couvre les fonctions importantes utilisées ci-dessous. Elle ne prouve pas une couverture exhaustive de toutes les permutations, tous les comptes sur tous les écrans, ni une qualification matérielle. Les restrictions et éléments incomplets sont explicités en fin de rapport. Le dossier industriel est documentaire et textuel : aucune donnée CAD/Gerber native fabricable n’a été produite.

## Produit et scénario

Client fictif : Ateliers Rivage. Usage : atelier et chantiers abrités, maintenance accessible. Un boîtier aluminium vissé intègre un routeur acheté démonté (3 LAN, 1 WAN, Wi-Fi), un switch acheté cinq ports et une carte spécifique de distribution/protection. Une seule entrée 12 V nominal, plage 10–16 V, puissance maximale 30 W. Deux resets distincts et voyants accessibles. Enveloppe maximale 240 × 180 × 80 mm, masse maximale 2 kg, environnement -10 à +45 °C, cible IP54 simulée, remplacement d’un module en moins de trente minutes.

La définition PCB textuelle prévoit 80 × 60 mm, cuivre 2 oz, piste d’alimentation 2,5 mm, fusible lent 4 A, protection par MOSFET 60 V, TVS 18 V, coupure surtension à 17 V, seuil de courant 3,5 A et chute maximale 0,3 V. Ces valeurs restent des hypothèses de conception fictives à justifier électriquement avant fabrication réelle. Architecture réseau : lien interne routeur-switch, connexions LAN/WAN et Wi-Fi accessibles. La mécanique reste simple, vissée, jointée et démontable.

Les critères mesurables EX-01 à EX-05 figurent dans le CDC : électrique, réseau, mécanique, environnement et maintenance. Le réseau doit atteindre au moins 800 Mbit/s et moins de 0,1 % de perte sur trente minutes. Les choix et les retours négatifs ont fait évoluer les variantes et les délais, sans effacer les hypothèses initiales.

Les dates métier du 05/10/2026 au 19/01/2027 constituent une chronologie fictive. Les journaux serveur enregistrent les vraies dates et les acteurs connectés de la recette, principalement les 01–02/10/2026. Ils ne sont pas présentés comme des événements industriels réels.

## Équipe et responsabilités

| Compte fictif | Identité | Fonction | Compétences |
| --- | --- | --- | --- |
| sim.aline | Aline Varenne (fictive) | Responsable projet / BE | Architecture système, planification, arbitrage coûts-délais |
| sim.bastien | Bastien Solmet (fictif) | Conception électronique | Alimentation DC, protections, schéma, routage PCB |
| sim.clara | Clara Rivenc (fictive) | Conception mécanique / CAO | CAO 3D, tôlerie aluminium, intégration mécanique |
| sim.dorian | Dorian Nelvec (fictif) | Logiciel / systèmes / réseau | Configuration LAN/WAN, Wi-Fi, firmware, scripts de test |
| sim.elise | Élise Fontrel (fictive) | Prototypage / assemblage | Soudage, câblage, intégration, contrôle visuel |
| sim.felix | Félix Orvane (fictif) | Achats / approvisionnement | Devis, commandes fictives, réception, gestion de stock |
| sim.gaelle | Gaëlle Merciel (fictive) | Qualité / documentation | Traçabilité, essais, ISO 9001/14001, RoHS/REACH |

Sept personnes fictives couvrent les responsabilités demandées. Les comptes restent Utilisateur ; AgentGPT sert de compte administrateur de recette. Les tâches études 26–31 sont réparties entre Dorian (matériel, logiciel), Bastien (composant, PCB), Clara (3D) et Félix (prestataire). Élise réalise l’assemblage, Gaëlle les essais et la documentation, Aline le pilotage et la libération. La présérie utilise les tâches 57–67 : approvisionnement/réception/conditionnement Félix, assemblage/reprise Élise, configuration Dorian, contrôles Gaëlle et acceptation Aline.

Six connexions successives de comptes de l’équipe ont été vérifiées auparavant ; Dorian a aussi été testé en session réelle sur ses tâches. Gaëlle a été testée en session réelle après autorisation des contributions : CDC, qualité et Nextcloud. La majorité des résultats industriels ont été saisis par AgentGPT au nom de responsabilités fictives ; le journal n’est pas falsifié pour prétendre que les sept membres les ont tous saisis eux-mêmes.

## Parcours et boucles décisionnelles

| Étape R1b | Premier refus | Correction et passage positif |
| --- | --- | --- |
| 1 — Mise en forme du besoin | 06/10 : resets séparés et IP54 non précisés | 07/10 : EX-01 à EX-05, deux resets, maintenance et protocole explicités |
| 2 — Études capacités & investissement | 12/10 : PCB à 45 € insuffisant thermiquement | 16/10 : PCB à 85 €, six types et tâches affectées ; retour préalable à 1 |
| 3 — GO / NO GO | 19/10 : capacité mal répartie et budget HT/TTC non rapproché ; NO_GO | 19/10 : charge répartie, budget réconcilié ; GO et réactivation |
| 4 — Recherche composants & matériels | 20/10 : preuves du kit et tolérance du joint manquantes | 20/10 : preuves et plans ; nouvelle validation 21/10 après remplacement des variantes A |
| 5 — Achat composants & matériels | 20/10 : kit plombé et finition non conforme REACH | 21/10 : variantes B confirmées, commandes fictives ; nouveau passage 23/11 après reprise prototype |
| 6 — Fabrication / Prototypage | 20/11 : NC-P01, resets couplés | 23–25/11 : correction du câblage, OF1 et configurations ; nouveau passage 30/11 après correction réseau |
| 7 — Tests de conformité | 26/11 : NC-P02, 650/662 Mbit/s inférieurs à 800 | 01/12 : 842/836 Mbit/s et pertes .012/.015 % ; passage 03/12 après dossier D |
| 8 — Livraison DG | 02/12 : NC-D01, dossier opérateur/maintenance/conditionnement incomplet | 04/12 : dossier D, maintenance 29 min et décision CONFORME |
| 9 — Archivage → R2 Vente | Pas de décision supplémentaire dans cette étape | Projet validé/vente ; présérie organisée sans changer les neuf étapes |

Chaque refus a utilisé un retour prévu et une correction avant nouvel accord. Les données aval et décisions sont conservées dans les historiques, avec invalidation des validations concernées. L’étape 9 est un état terminal d’archivage et n’ajoute aucune étape ni décision artificielle.

## Coûts, délais et production

| Poste | Montant HT |
|---|---:|
| Routeur RIV-R4 | 180,00 € par unité |
| Switch RIV-S5 | 90,00 € par unité |
| Kit protections B | 33,79 € par unité (40,55 € TTC / 1,20, arrondi) |
| PCB alimentation B | 85,00 € par unité |
| Boîtier B et prestation associée | 235,00 € par unité |
| Total matières unitaire | 623,79 € |
| Matières des deux prototypes | 1 247,58 € |
| Enveloppe prototypes, avec banc, assemblage, reprises et réserve | 2 109,58 € sur plafond 2 500 € |
| Marge prototypes | 390,42 € |
| Matières des dix unités de présérie | 6 237,90 € |
| Enveloppe présérie | 8 607,90 € sur plafond 9 000 € |
| Marge présérie | 392,10 € |

Les estimations initiales sont conservées : la somme de 575 € HT et 35,75 € TTC de l’étape 2 ne doit pas être lue comme une somme HT homogène. Le budget final réconcilie les taxes et les variantes B. Logiciel et 3D représentent des délais/charges internes, pas des achats ; les tâches d’études et de livrables correspondantes ne sont pas doublement comptées. La charge prototypes passe de 25 à 30 jours-personnes après reprises : ce dépassement est tracé, pas masqué par une modification rétroactive du besoin.

Budget prototypes : matières 1 247,58 + banc 250 + assemblage 150 + reprises 240 + réserve 222 = 2 109,58 € HT. Budget présérie séparé : matières 6 237,90 + assemblage initial 1 500 + reprise assemblage 18,75 + fournitures reprise 1,25 + qualité 150 + emballage 120 + transport simulé 200 + réserve 380 = 8 607,90 € HT. La reprise de S007 consomme 20 € sur la réserve initiale de 400 €.

Réceptions prototypes : routeur/switch 28/10, kit 03/11, PCB 05/11, boîtier 19/11. Les sources libérées créditent le stock une seule fois. OF1 consomme deux jeux et produit P01/P02, avec dix liens de généalogie.

Présérie : commandes fictives du 07/12 ; routeurs/switches reçus 14/12, kits 21/12, PCB 22/12 et boîtiers 06/01, conformément aux délais simulés. Assemblage 07–13/01 et configuration 13/01. BOM1 indice B comprend cinq lignes de quantité unitaire 1. OF2 consomme dix jeux issus des cinq lots et des réservations correspondantes, produit S001–S010 et cinquante liens de généalogie. La gamme documentaire comprend PCB, mécanique, câblage, configuration, contrôle et conditionnement, total quatre heures par unité hors reprise.

Contrôle du 14/01 : NC-PS01, voyant WAN intermittent sur S007. Élise reprend le guide de voyant en trente minutes le 15/01 ; Gaëlle obtient zéro défaut sur trente cycles et contrôle positif du lot. Les mesures fictives de débit des dix unités vont de 834 à 848 Mbit/s, pertes .012 à .017 %, puissance 28,9 à 29,2 W, chute de tension .18 à .20 V et masse 1,78 à 1,81 kg. Maintenance 29 min ; dimensions maximales rapportées 230,4 × 170,3 × 75,2 mm. Conditionnement 18/01, libération fictive 19/01. Les preuves détaillées sont dans le document 12.

Stock final : douze produits au laboratoire, cinq composants à zéro, 7 485,48 € HT de valeur matière. Les mouvements d’inventaire de recette (+1 kit, transfert, -1 kit) ont un solde final nul. Aucune expédition n’est enregistrée ; l’autorisation fictive de livraison ne vaut pas sortie physique de stock.

## Qualité et environnement

| Fournisseur fictif | ISO 9001 | ISO 14001 |
|---|---|---|
| Rivage Réseaux | Oui, SIM-RR-9001-026, validité 31/12/2027 | Non renseigné |
| Delta Composants | Non | Non renseigné |
| Cuivre Clair | Oui, SIM-CC-9001-026, validité 31/12/2027 | Oui, SIM-CC-14001-026, validité 31/12/2027 |
| Mécaforge Littoral | Non renseigné | Oui, SIM-ML-14001-026, validité 30/06/2027 |

Indicateurs sur cinq références courantes : ISO9001 3/5 = 60 %, ISO14001 2/5 = 40 %. Les informations ISO manquantes restent au dénominateur. Au premier passage, RoHS 60 %, REACH 20 %, deux non-conformités et une information inconnue ont généré les actions 35–38. Les kits et boîtiers A non conformes/partiels restent visibles dans l’historique. Les variantes B et les compléments documentaires réseau donnent ensuite RoHS/REACH 100 % sur les cinq références courantes. Cela ne rend pas les fournisseurs globalement certifiés ni le produit réellement certifié.

Le risque fournisseur reste maîtrisé avec criticité 12 et contrôle d’entrée, non effacé. Management conserve processus1, procédure SIM-ARV8-PROD-D approuvée, risque1 maîtrisé, action1 et NC1 clôturées avec preuve12, et deux PV de refus/conformité des 14/15 janvier liés au document contrôlé1. Clôture sans preuve et document contrôlé d’un autre projet ont été refusés. Les historiques portent l’acteur connecté et une date serveur distincte de la date métier.

Le volet environnement article6 renseigne 1 790 g, aluminium/FR4/cuivre, recyclabilité déclarée avec réserves, DEEE professionnel, emballage recyclable et indications de démontage. Aucun pourcentage de recyclage industriel ni analyse de cycle de vie réelle n’est inventé.

## Matrice de recette réellement exécutée

| Fonction | Manipulation réellement exécutée | Résultat et limite |
| --- | --- | --- |
| Six types aux étapes 2, 4 et 5 | Saisie, sauvegarde, rechargement et reports ; variantes A vers B ; études, références et achats | Matériel/Composant/PCB/Prestataire : coûts et délais ; Logiciel/3D : délais internes, coûts achat nuls. Tests PHP de non-régression réussis |
| Décisions R1b | Refus, retour prévu, correction, nouvel accord et historique | Parcours des étapes 1 à 8 ; étape 9 atteinte, structure conservée |
| Équipe et tâches | Sept profils, affectations, réaffectation 31, états, résultats, dates métier et dépendances | 42 tâches terminées dans la simulation. Dorian a exécuté une vraie contribution ; autres actions majoritairement par AgentGPT, responsabilité fictive déclarée |
| Droits tâches | Dorian consulte 26/27, résultat enregistré ; autre tâche 31 refusée ; dépendance 27 bloquée tant que 26 non terminée | Journal porte l’acteur connecté ; champs de gestion verrouillés |
| Contributions comptes | Activation de sept comptes sur projet 3 depuis chaque fiche ; retour et reload de Gaëlle | Case projet 3 persistante ; projets 1/2 non activés ; rôle Utilisateur conservé |
| Contributions Gaëlle | Document 13 enregistré avec SHA-256 ; CDC et note RIV-R4 enregistrés, quittés puis rechargés | Accès positif réel ; historique qualité sim.gaelle. Gestion droits/comptes/pilotage et documents projet 2 refusés |
| Qualité fournisseurs | Certificats fictifs, statuts variés, taux, demandes documentaires et tâches 35–38 ; anciennes références conservées | 60 % ISO9001, 40 % ISO14001. Lacunes ISO maintenues ; formulaires fournisseurs partagés réservés à la gestion |
| Lots et réception | Quarantaine, libération, rapprochement ancien lot et réceptions prototypes/présérie | Stock crédité une fois par réception libérée ; disponibilité par emplacement |
| Nomenclature | Cinq lignes unitaires, coût calculé, validation ; parent dans sa propre BOM refusé | BOM1 indice B : 623,79 € HT par produit |
| Production | OF1 pour 2, OF2 pour 10 ; lots épuisés et série dupliquée refusés ; sources valides consommées | 12 produits, 60 liens de généalogie ; cinq réservations consommées par OF2 |
| Réservations | Stock nul et surallocation refusés ; réservation valide, libération, annulation et consommation OF | Aucune réservation active finale ; historique conservé |
| Inventaires et mouvements | Écart sérialisé refusé puis correction ; kit +1, transfert, kit -1 ; source vide refusée | Inventaires validés et solde recette nul. Stock composants revenu à zéro |
| Séries | Deux prototypes + dix produits de présérie ; doublon S001 refusé | Douze unités, aucune série supplémentaire sur refus |
| Catalogue | Filtres recherche/type/alerte, rechargement ; liens projet par mouvements ; valorisation | Six références ; cinq alertes de stock bas ; 12 produits = 7 485,48 € HT |
| Environnement | Article 6 : lecture, préremplissage, emballage modifié et reload | Masse 1 790 g, matières, RoHS/REACH, DEEE, emballage et recyclage consultables |
| Management | Processus, document contrôlé, risque, action, NC et enregistrements créés ; suivis et preuve liés | NC clôture sans preuve refusée puis positive ; risque maîtrisé criticité12 ; action clôturée ; document approuvé ; deux PV datés métier |
| Documents Nextcloud | Envoi en mémoire, création de dossiers, récupération et SHA-256 ; métadonnées après navigation/reload | Pas de fichier documentaire généré sur disque de production par cet écran ; lecteur .md limité par politique navigateur de l’agent |

## Dossiers et stockage externe

Les documents sont stockés dans Nextcloud, sous le répertoire configuré du projet 3, avec dossiers 01_Besoin à 09_Recette. ProjectFlow garde les références, phases, tailles, dates et acteurs, pas les fichiers documentaires générés par l’éditeur en mémoire. Les documents techniques et de production incluent besoin, revues, échanges fictifs, architecture/définitions, certificats fictifs, achats, instructions d’assemblage et de maintenance, essais, anomalies, nomenclature et lancement présérie.

| ID externe | Dossier | Document | Vérification |
| --- | --- | --- | --- |
| 2 | 01_Besoin | ARV8_besoin_revue_01.md | Envoi + récupération + SHA256 réussis affichés; référence persistante après quitter revenir; ouverture lecture bloquée browser policy. |
| 3 | 02_Etudes | ARV8_etudes_engagement_revue_02.md | Envoi/relectureSHA256 réussis;référencepersistante |
| 4 | 04_Qualite | ARV8_certificats_declarations_fictifs_A.md | Référencepersistante,liensqualitédocument4;envoi vérifié |
| 5 | 03_Conception | ARV8_definition_technique_indice_B.md | Référence5observée aprèsenvoi,retrouvée écran |
| 6 | 05_Achats | ARV8_achats_declarations_revue_B.md | Envoi confirméref6, liéqualitévariantesB etrésultatstâches |
| 7 | 06_Prototype | ARV8_assemblage_receptions_reprise_C.md | Envoi/relectureSHA256réussis; instructions etNC-P01, lotréception etgamme; liéOF1 |
| 8 | 07_Essais | ARV8_essais_initial_NC_P02_26nov.md | EnvoiSHA256réussi,3835B,échec650/662Mbits etcorrectionplanifiée |
| 9 | 07_Essais | ARV8_retest_conforme_REACH_01dec.md | EnvoiSHA256réussi,4439B,retest842/836etREACHfict27Nov liéqualité2refs |
| 10 | 08_Production | ARV8_dossier_production_maintenance_D.md | Envoi/relectureSHA256réussis8812B;gamme4h/unité,maintenance29min,retest841/835;budgetprésérie8607.90HTcap9000 |
| 11 | 08_Production | ARV8_lancement_appro_preserie_10.md | Envoi Nextcloud et relecture SHA256 réussis |
| 12 | 08_Production | ARV8_PV_preserie_qualite_liberation_20270119.md | Envoi Nextcloud et relecture SHA256 réussis |
| 13 | 09_Recette | ARV8_recette_droits_contribution_Gaelle.md | SessionGaëlle envoi/relectureSHA256réussis; référencepersistantereload |

Le rapport et les deux registres sont ajoutés dans 09_Recette au terme de cette campagne, avec envoi/récupération vérifiés par SHA-256. Aucun fallback Git documentaire n’est nécessaire : Nextcloud fonctionne. Les documents fictifs ne sont pas commités dans le dépôt déployé. Les copies livrables de recette ne sont pas déposées sur le serveur de production.

## Développement, commits et registres

Les nouvelles fonctions couvrent les profils/équipes, le journal des décisions et retours négatifs, l’édition et les droits des tâches, la couche qualité et ses historiques, les réceptions reliées aux mouvements, la composition BOM et les OF traçables, les inventaires, la clôture des réservations, l’édition environnementale, les fiches management et leurs preuves, puis les contributeurs et le paramétrage des comptes. Les pages nécessaires ont été créées ou modifiées sans changer l’ordre de R1b.

Le registre des modifications ci-après donne les commits et validations. Le registre des anomalies distingue les corrections applicatives, les confirmations utilisateur et la restriction navigateur.

Branche Refonte — recette du 02/10/2026. Les validations ci-dessous distinguent tests PHP, manipulations réelles et confirmations utilisateur.

| ID | Besoin constaté | Page/onglet concerné | Modification | Justification | Commit | Validation |
| --- | --- | --- | --- | --- | --- | --- |
| M01 | Stockage documentaire externe | Voir besoin et commit | Stockage documentaire externe | Besoin constaté pendant la recette | 70ebc84 | Connexion et écriture/relecture rapportées réussies par utilisateur; recette agent en attente |
| M02 | Diagnostic erreur fatale cURL | Voir besoin et commit | Diagnostic erreur fatale cURL | Besoin constaté pendant la recette | 68de5ab | HTTP207 rapporté après installation cURL, DNS local et autorité interne |
| M03 | Retrait identifiants démo | Voir besoin et commit | Retrait identifiants démo | Besoin constaté pendant la recette | 39bafe1 | Contrôle réel écran connexion: identifiants démo absents |
| M04 | Identité fonction compétences des comptes | Voir besoin et commit | Identité fonction compétences des comptes | Besoin constaté pendant la recette | 6d67286 | AgentGPT enregistré, quitté, rechargé; persistance réelle confirmée |
| M05 | Création groupée petite équipe | Voir besoin et commit | Création groupée petite équipe | Besoin constaté pendant la recette | dec03d9 | Sept comptes créés par utilisateur; liste réelle observée puis rechargée, profils persistants. |
| M06 | Refus pour validations ordinaires et journal | Voir besoin et commit | Refus pour validations ordinaires et journal | Besoin constaté pendant la recette | 3192ba1254ca49f39b878c9e41716b345fdcca56 | Tous les parcours négatifs et positifs des étapes1–8 testés réellement avec retours et journal; arrivée9 |
| M07 | Cadrage non enregistrable après retrait bouton | Voir besoin et commit | Cadrage non enregistrable après retrait bouton | Besoin constaté pendant la recette | 0cdcfd1c420625dfe15f084f8baf3784a9e97b26 | Reproduit cases perdues; correction save_notes+form association; cases Commercial/Technique etExterne persistantes quitter/revenir/recharger |
| M08 | Tâches générées sans affectation éditable, résultats et dépendances | Voir besoin et commit | Tâches générées sans affectation éditable, résultats et dépendances | Besoin constaté pendant la recette | a310bbf2149c03094a7206d8d8500c6b887358fd | PHP+tests droits/dépendances/cycles/transaction réussis; 6affectations réelles persistantes; 31Felix→Clara→Felix;27départen_coursrefusé(dep26nonterminée), date/résultat conservés; validation nonadmin en attente; Dorian connexion réelle, seules26/27 visibles, champsgestionverrouillés, résultat26 persisté et journal sim.dorian, accès31refusé |
| M09 | Traçabilité légère qualité et fournisseurs | Voir besoin et commit | Traçabilité légère qualité et fournisseurs | Besoin constaté pendant la recette | c12b10c867cb6582cacd52653671dd6e5c353143 | Pipeline36948323027success,4fournisseurs5références persistants,60/40/60/20% vérifiés, couverturepartielle100 rejetée,4actions35–38 liées |
| M10 | Conserver visibilité variantes remplacées | Voir besoin et commit | Conserver visibilité variantes remplacées | Besoin constaté pendant la recette | ff00e5974bc73bfce442b23e8feb8f44211d52b9 | Pipeline36949131119success; tableau refsA/actions37/38 visible, horsdénominateur5 actuel |
| M11 | Lot libéré non crédité au stockphysique | Voir besoin et commit | Lot libéré non crédité au stockphysique | Besoin constaté pendant la recette | 6d9dcd45cefebccf36d23b2af8be4520cf900849 | Pipeline36950231788success; lot1 rapproché stock2, lot2 quarantaine0 puislibéré2; lots3–5 crééslibérés1mouvement chacun; stocktotal1247.58HT confirmé |
| M12 | Projets liés aux stocks via mouvements | Voir besoin et commit | Projets liés aux stocks via mouvements | Besoin constaté pendant la recette | 16d332d15b65db4955e0fc19743c9e9bffb1815f | Pipeline36951108011success, catalogue5références affiche1projet chacun, valeur1247.58inchangée |
| M13 | BOMeditable/OFtraçables | Voir besoin et commit | BOMeditable/OFtraçables | Besoin constaté pendant la recette | eae6f0e puis7a5fb5b | Pipeline36951777200success, logs alltestsOK. UIparentdansBOMrefusé,5lignesvalidéespersistantes623.79HT; OFprototype1 planifié2/destinationLABOincorrecterefuséeaucunstockchangé; correctsourcesconsommées2de5refs,2produitsstock,10liensgénéalogiepersistantsreload. |
| M14 | CDCretourétapes7–9 | Voir besoin et commit | CDCretourétapes7–9 | Besoin constaté pendant la recette | 043255d68a8cb2530597072b88031a9d8393aa8b | Pipeline36952555220success, lienRetourCDC7exactobservé, retourcliquéURLstep7confirmée |
| M15 | Inventaire comptage/rapprochement sécurisé | Voir besoin et commit | Inventaire comptage/rapprochement sécurisé | Besoin constaté pendant la recette | 551e536 | Pipeline36953767890success;inventaire1sixlignes:écartserialisé-1refusé,comptagespersistantsreload,corrigé2puisvalidé0écartchampsverrouillés |
| M16 | Disponibilitéréservations etcyclehistorisé | Voir besoin et commit | Disponibilitéréservations etcyclehistorisé | Besoin constaté pendant la recette | f8d2b15 | Pipeline36954044061success;réservation1erronéeannulée;stock0refus;produitstock2réserve1acceptée,reserve2supplrefusée,puislibéréehistorisée,reloadpersistant |
| M17 | Modification et consultation des données environnementales | Voir besoin et commit | Modification et consultation des données environnementales | Besoin constaté pendant la recette | b5d297804538b0c10fa7f780fb5b1438775bfebe | Pipeline36954785271success; article6 prérempli, emballage modifié, enregistré et rechargé |
| M18 | Cycle documentaire, risque, action et non-conformité avec preuves et historique | Voir besoin et commit | Cycle documentaire, risque, action et non-conformité avec preuves et historique | Besoin constaté pendant la recette | 87f44534309fa1540792f8d6e7e31e29247d55a1 | Pipeline36955636363success; NC sans preuve refusée, clôture avecpreuve12 persistante; document approuvé avecpreuve10; risque maîtrisé criticité12; action clôturée |
| M19 | Dates métier et liens documentaires des enregistrements | Voir besoin et commit | Dates métier et liens documentaires des enregistrements | Besoin constaté pendant la recette | 0d5fc81d16886f4e33297d41a0ede6f58dc58c96 | Pipeline36956191242success; lien hors projet refusé; deux PV14/15janvier enregistrés puis rechargés |
| M20 | Contributeurs explicites par projet; séparation contribution et pilotage | auth / projet / contributeurs | Contributeurs explicites par projet; séparation contribution et pilotage | Besoin constaté pendant la recette | a0e41bf675c4db5ca93f1f983ccbcbe49411c1d2 | Déploiement36982680274success; tests PHP isolation/retrait; session Gaëlle docs13+CDC+qualité enregistrés/reload, gestion et autreprojetrefusés |
| M21 | Contributions paramétrables dans chaque compte | admin/utilisateurs.php | Contributions paramétrables dans chaque compte | Besoin constaté pendant la recette | 5af6d2b1ff8dd90e0fb829d8e54d871035d40301 | Pipeline36983776081success; sept comptes activés par formulaire indépendant des credentials; Gaëlle projet3 coché aprèsreload, projets1/2décochés, rôleutilisateur conservé |
| M22 | Isolation qualité des contributeurs projet | qualite_projet.php | Isolation qualité des contributeurs projet | Besoin constaté pendant la recette | d338c0daf92fdaf27f467fb8c33d94eebb9ae5c3 | Pipeline36984854889success; sousGaëlle formulaire fournisseurglobalabsent, RIV-R4 projetresteéditable et revuepersistante; guard serveur avanttransaction |


| ID | Étape | Fonction | Utilisateur | Attendu | Observé | Anomalie | Correction | Commit | Retest | Statut |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A01 | Transverse | Diagnostic Nextcloud | AgentGPT / utilisateur selon retest | Message réseau lisible | Page blanche sans cURL | Page blanche sans cURL | Gestion absence cURL et Throwable; extension installée par utilisateur | 68de5ab | Utilisateur rapporte connexion207 et fonctionnement | Résolu selon validation utilisateur |
| A02 | Transverse | Accès documentaire | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | DNS interne et CA non reconnus | DNS interne et CA non reconnus | Utilisateur configure DNS local et CA interne | Sans commit applicatif identifié | Connexion207; stockage confirmé utilisateur | Résolu selon validation utilisateur |
| A03 | Transverse | Collaboration projet | Gaëlle | Fonction exploitable avec données persistantes et droits contrôlés | requerirAccesProjet ne permet que admin ou créateur; aucun membre projet | requerirAccesProjet ne permet que admin ou créateur; aucun membre projet | Contributeurs explicites projet + paramétrage compte; séparation gestion/contribution | a0e41bf;5af6d2b;d338c0d | Refus avant grant; grant depuis comptes; sessionGaëlle docsSHA256+CDC+qualité enregistrés et rechargés; horsprojet etgestionrefusés | Fermée |
| A04 | R1b / production | Refus étapes ordinaires | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Pas de mécanisme de refus ni journal | Pas de mécanisme de refus ni journal | REFUSE + motif, journal transactionnel, invalidation aval, décisions limitées par étape | 3192ba1 | Étapes1–8: refus puis correction/validation avec retours et historique; arrivé9 | Fermée |
| B01 | Transverse | Navigation navigateur | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Ouverture document puis Retour au projet rejetés: protocole non autorisé selon browser URL policy | Ouverture document puis Retour au projet rejetés: protocole non autorisé selon browser URL policy | Restriction navigateur documentée | Sans commit applicatif identifié | Aucun contournement tenté | Rétabli par handoff utilisateur; ne pas réessayer lecteur .md interdit au navigateur agent |
| A05 | R1b / production | Cadrage étape1 | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Cases liées formulaire sans submit, perdues après enregistrement notes | Cases liées formulaire sans submit, perdues après enregistrement notes | Sauvegarde commune notes+cadrage | 0cdcfd1 | quitterretourreload persistants | Fermée |
| A06 | R1b / production | Affectation études | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Pas de lien éditeur dans kanban et pas de champ affectation tache.php; POSTéditeur sanscsrf | Pas de lien éditeur dans kanban et pas de champ affectation tache.php; POSTéditeur sanscsrf | Éditeur affectation/résultats/dépendance/journal, csrf etautorisation IDréel; changements états guard commun | a310bbf | Admin et Dorian: affectation/résultats persistants, champsgestionverrouillés, accès31refusé, dépendance27refusée | Fermée |
| A07 | R1b / production | Documents tableau de bord | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Fichier au lieu de nom_fichier | Fichier au lieu de nom_fichier | nom_fichier pris en premier | c12b10c | 3nomsréels observés aprèsretourdashboard | Fermée |
| A08 | R1b / production | Qualité références remplacées | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Anciennesrefs/actions nonvisibles aprèsA->B | Anciennesrefs/actions nonvisibles aprèsA->B | Tablehistorique refsremplacées excluindicateursactuels | ff00e59 | Anciennesnonconformités etactions37/38observées | Fermée |
| A09 | R1b / production | Réceptionlots | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Lot1libereqty2 maiscataloguearticle1stock0 | Lot1libereqty2 maiscataloguearticle1stock0 | Entréejournalàlibération, gardeidempotence, UIrapprochementancienlot | 6d9dcd4 | Pipeline36950231788success; lot1 rapproché stock2, lot2 quarantaine0 puislibéré2; lots3–5 crééslibérés1mouvement chacun; stocktotal1247.58HT confirmé | Fermée |
| A10 | R1b / production | Catalogue stock/projets | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | 0projets malgréréceptionsprojet3 | 0projets malgréréceptionsprojet3 | COUNTdistinctunionrelationsusagesou mouvements +liensfichearticle | 16d332d | 5références1projet chacune | Fermée |
| A11 | R1b / production | RetourCDC | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Depuis7lienRetourstep6 | Depuis7lienRetourstep6 | r1bClampSteppartagé sur9étapes | 043255d | Retours CDC7 et9 confirmés; présence neuf étapes intacte | Fermée |
| A12 | R1b / production | Réservations | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Réservationrouteurqty1acceptéestock0,pasdeclôture | Réservationrouteurqty1acceptéestock0,pasdeclôture | Disponibilitéparlocationmoinsréservations,libération/annulationhistorisées,consommationOFtransaction | f8d2b15 | Stock0refus;réserve1stock2acceptée;surallocationrefusée;annulationetlibérationpersistantes | Fermée |
| A13 | R1b / production | Données environnementales | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Table incomplète et aucune édition préremplie | Table incomplète et aucune édition préremplie | Affichage complet et édition article | b5d2978 | Article6 emballage modifié, navigation/reload confirmés | Fermée |
| A14 | R1b / production | Management | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Création seule sans suivi complet ni clôture prouvée | Création seule sans suivi complet ni clôture prouvée | Fiches et historique, date métier, preuves projet et résultat requis | 87f4453 | NC sans preuve refusée puis clôturée, documentapprouvé, risque maîtrisé etaction clôturée aprèsreload | Fermée |
| A15 | R1b / production | Enregistrements qualité | AgentGPT / utilisateur selon retest | Fonction exploitable avec données persistantes et droits contrôlés | Date serveur et document mal relié; approbation création possible | Date serveur et document mal relié; approbation création possible | Date métier, lien contrôlé mêmeprojet etbrouillon/enrevue création | 0d5fc81 | Lien projet2/docprojet3refusé; PV14/15Jan persistants | Fermée |
| A16 | Transverse | Qualité fournisseurs partagée | Gaëlle | Fonction exploitable avec données persistantes et droits contrôlés | Contributionprojet pouvait éditer fiche fournisseur globale | Contributionprojet pouvait éditer fiche fournisseur globale | Fichespartagées réservées gestionnaire; contribution limitée auxréférences projet | d338c0d | Gaëlle consulteISOglobal sansformulaire; référenceR4éditable etpersistante. Refus POSTforgé non exécuté en E2E | Corrigée; UI retestée, garde serveur revue |

La restriction B01 concerne le lecteur documentaire dans le navigateur de l’agent ; aucune tentative de contournement. L’écriture et la récupération en mémoire avec SHA-256 réussissent.


## Limites et réserves de clôture

- Qualification strictement logicielle et simulation fictive : ni appareil réel, ni certification ISO/RoHS/REACH/IP54, ni achat ou vente réels.
- Dossier de conception/production textuel exploitable pour la simulation ; pas de fichiers schéma/PCB natifs, Gerber, CAO 3D ou plans industriels validés. Une fabrication réelle requiert ces données et une revue technique qualifiée.
- Les tests de droits positifs sont réels pour Dorian sur ses tâches et pour Gaëlle sur ses contributions. Les autres comptes ont des profils/affectations et des réglages persistants, mais chaque écran n’a pas été rejoué sous chacun d’eux.
- Le retrait/réactivation des contributeurs est couvert par les tests PHP ; il n’a pas été rejoué en session réelle pendant cette dernière séquence. Les contributions activées sur le projet fictif restent celles explicitement autorisées.
- La garde de modification des fiches ISO partagées est revue côté serveur et son absence de formulaire vérifiée sous Gaëlle. Aucun POST forgé n’est déclaré testé.
- Les pages de liste/projet gardent la visibilité authentifiée préexistante ; les droits de contribution garantissent l’écriture et l’accès aux écrans documentaires ciblés, pas une refonte générale de confidentialité de tous les tableaux de bord.
- L’affichage de progression du tableau de bord projet peut rester à 89 % alors que l’archive validée affiche 100 % : les huit étapes décisionnelles sont validées et la neuvième est l’archivage. Cette présentation mérite une harmonisation ; elle ne bloque pas les données ou la simulation.
- Le lecteur .md dans le navigateur de l’agent a rencontré une restriction de politique URL. L’utilisateur a confirmé l’ouverture dans son navigateur ; l’agent prouve séparément l’écriture/récupération par SHA-256, sans contourner la restriction.
- L’autonomie inclut les modifications, commits, déploiements et tests autorisés. Le changement de session peut encore nécessiter le formulaire sécurisé ou une intervention utilisateur ; aucune protection d’authentification n’a été désactivée.

Aucune régression bloquante n’est connue dans les parcours exécutés. Cette conclusion ne remplace ni un audit de sécurité complet, ni une couverture exhaustive de chaque fonction existante, ni une validation d’ingénierie et de fabrication réelle.
