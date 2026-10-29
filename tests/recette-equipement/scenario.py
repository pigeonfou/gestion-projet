"""Reusable, explicitly fictional DEV acceptance dataset. No real supplier or certification."""
CODE='REC-ATLAS12-20261029'
PROJECT='[RECETTE DEV FICTIVE] ATLAS-12 - Banc technique modulaire'
WARNING='FICTIF - RECETTE DEV - AUCUN ACHAT, ENVOI OU ESSAI MATERIEL REEL'
MAKERS=['RECETTE - Circuits Atlas','RECETTE - Modules Horizon','RECETTE - Meca Aluminium','RECETTE - Polymere Atelier']
SUPPLIERS=['RECETTE - Electro Distribution','RECETTE - Integration Services','RECETTE - Usinage Express','RECETTE - Plasturgie Locale']
# code, designation, manufacturer index, supplier index, unit HT, category, key specifications
PARTS=[
 ('CPU','Calculateur industriel existant',1,1,180,'Integration','12 V DC; Ethernet; Linux local; 8 W typiques'),
 ('SW5','Switch Ethernet 5 ports existant',1,1,45,'Integration','5 ports cuivre 1 Gbit/s; 12 V DC; fixation vissée'),
 ('LCD','Ecran tactile 5 pouces',1,0,55,'Integration','800 x 480; interface USB; face avant protégée'),
 ('PT100','Sonde de température Pt100',1,0,12,'Capteurs','4 fils; plage de recette 0 à 80 °C; sonde passive'),
 ('ADC','Convertisseur analogique 16 bits',0,0,8,'Electronique','8 voies; SPI; 0 à 10 V après adaptation'),
 ('MCU','Microcontrôleur de supervision',0,0,6,'Electronique','SPI; watchdog indépendant; firmware de recette'),
 ('DCDC','Convertisseur DC/DC 24 vers 12 V',0,0,18,'Electronique','Entrée 18 à 30 V; sortie 12 V; puissance simulée 36 W'),
 ('FUSE','Porte-fusible et fusible 2 A',0,0,3,'Electronique','Protection entrée; remplacement hors tension'),
 ('TVS','Diode de protection transitoire',0,0,2,'Electronique','Protection DC; seuil à confirmer par étude réelle'),
 ('PCB-A','Circuit imprimé acquisition nu',0,0,22,'Electronique','FR4; deux couches; 100 x 70 mm; indice A fictif'),
 ('PCB-P','Circuit imprimé alimentation nu',0,0,16,'Electronique','FR4; deux couches; 80 x 50 mm; indice A fictif'),
 ('BASE','Embase aluminium usinée',2,2,65,'Mecanique','Aluminium 5754 supposé; 240 x 180 x 3 mm; plans fictifs'),
 ('CAP','Capot aluminium plié',2,2,40,'Mecanique','Tôle 2 mm; fixation M3; liaison de masse prévue'),
 ('ABS','Façade plastique ABS imprimée',3,3,18,'Mecanique','180 x 80 x 3 mm; inserts; prototype additif'),
 ('SUP','Support plastique interne',3,3,8,'Mecanique','Support démontable; passage de câbles; matière supposée ABS'),
 ('JOINT','Joint périphérique silicone',3,3,6,'Mecanique','Joint découpé; compression cible 20 %; IP54 non certifié'),
 ('VIS','Visserie et entretoises M3',2,2,1,'Mecanique','Lot de vis, rondelles et entretoises; accès maintenance'),
 ('RJ45','Connecteur traversée Ethernet',1,0,7,'Connectique','Traversée panneau; liaison blindage; raccord CAT6'),
 ('BORNE','Bornier alimentation débrochable',0,0,4,'Connectique','Deux pôles; repérage polarité; détrompage'),
 ('CABLE','Faisceau interne repéré',0,0,9,'Connectique','Alimentation; sondes; Ethernet court; repérage aux extrémités'),
]
ASSEMBLIES=[
 ('ACQ','Sous-ensemble carte acquisition',0,[('PCB-A',1),('ADC',1),('MCU',1),('BORNE',1)]),
 ('PWR','Sous-ensemble carte alimentation',0,[('PCB-P',1),('DCDC',1),('FUSE',1),('TVS',1),('BORNE',1)]),
 ('BOX','Sous-ensemble boîtier aluminium / plastique',2,[('BASE',1),('CAP',1),('ABS',1),('SUP',2),('JOINT',1),('VIS',12)]),
 ('SYS','Equipement ATLAS-12 assemblé',0,[('ACQ',1),('PWR',1),('BOX',1),('CPU',1),('SW5',1),('LCD',1),('PT100',2),('RJ45',2),('CABLE',1)]),
]
# function label, measurable criterion, 2 or 3 technical responses (label,type,part,qty,price,delay)
FUNCTIONS=[
 ('Acquérir huit tensions analogiques','8 voies 0-10 V à 10 Hz; erreur simulée <= 1 % pleine échelle.',[
 ('Carte acquisition 8 voies spécifique','PCB','ACQ',1,40,15),('Conversion 16 bits et adaptation des entrées','Composant','ADC',1,8,7)]),
 ('Mesurer deux températures','Deux sondes; 0-80 °C; écart simulé <= 1 °C.',[
 ('Sondes Pt100 quatre fils','Composant','PT100',2,12,5),('Acquisition et conversion température','Logiciel',None,1,0,6),('Etalonnage documentaire des deux voies','Prestataire',None,1,60,3)]),
 ('Intégrer les équipements existants','Calculateur et switch remplaçables sans modifier les cartes spécifiques.',[
 ('Calculateur industriel du commerce','Matériel','CPU',1,180,5),('Switch Ethernet cinq ports','Matériel','SW5',1,45,5)]),
 ('Alimenter et protéger le système','Entrée 18-30 V DC; puissance maximale simulée 45 W; polarité repérée.',[
 ('Carte alimentation et protections','PCB','PWR',1,43,15),('Convertisseur 24 V vers 12 V','Composant','DCDC',1,18,7),('Essai des protections et du fusible','Prestataire',None,1,90,3)]),
 ('Communiquer sur Ethernet','Acquisition et supervision sur LAN; perte simulée < 0,1 % sur 30 min.',[
 ('Traversées Ethernet de face arrière','Composant','RJ45',2,7,5),('Protocole local JSON sur HTTP','Logiciel',None,1,0,8)]),
 ('Afficher les états et alarmes','Rafraîchissement 1 s; alarme capteur absente visible en moins de 3 s.',[
 ('Ecran tactile intégré cinq pouces','Matériel','LCD',1,55,8),('Interface de supervision locale','Logiciel',None,1,0,12),('Maquette de la face avant','3D',None,1,0,4)]),
 ('Protéger par une enveloppe aluminium','Encombrement <= 260 x 200 x 100 mm; masse simulée <= 2,5 kg.',[
 ('Embase et capot aluminium démontables','Matériel','BOX',1,157,12),('Conception mécanique aluminium','3D',None,1,0,8)]),
 ('Créer les pièces plastiques adaptées','Façade et supports démontables; absence de collision dans la définition fictive.',[
 ('Façade ABS avec logement écran','Matériel','ABS',1,18,6),('Supports internes imprimés','Matériel','SUP',2,8,6),('Modèles des pièces plastiques','3D',None,1,0,7)]),
 ('Piloter les cartes électroniques','Watchdog 2 s; reprise simulée après coupure en moins de 30 s.',[
 ('Firmware de supervision version 0.1.0-recette','Logiciel',None,1,0,10),('Microcontrôleur avec watchdog','Composant','MCU',1,6,5)]),
 ('Enregistrer et exporter les mesures','CSV UTF-8; horodatage ISO 8601; export simulé de 10 000 lignes.',[
 ('Service Linux de collecte locale','Logiciel',None,1,0,10),('Export CSV et rotation des journaux','Logiciel',None,1,0,7),('Vérification du logiciel et des sauvegardes','Prestataire',None,1,120,3)]),
 ('Permettre la maintenance et la traçabilité','Remplacement du calculateur en moins de 20 min; références et séries accessibles.',[
 ('Faisceau repéré et connectique démontable','Composant','CABLE',1,9,5),('Notice maintenance et configuration','Prestataire',None,1,70,4)]),
 ('Documenter la conformité et la livraison','Matrice 12 fonctions/30 ST; preuves PDF et contrôle documentaire complet.',[
 ('Plan de qualification et rapport de recette','Prestataire',None,1,160,5),('Dossier matière, RoHS/REACH et fin de vie fictif','Prestataire',None,1,80,4),('Gamme assemblage et dossier de livraison','Prestataire',None,1,100,4)]),
]
DOCS=[
 ('01_cadrage','Cadrage et compte rendu de lancement','01_Besoin','Le banc ATLAS-12 regroupe des équipements du commerce, deux cartes électroniques spécifiques, une enveloppe aluminium avec pièces plastiques et des logiciels locaux. Deux exemplaires fictifs sont prévus. Budget plafond de recette : 8 000 EUR HT. Aucun marché ni commande réelle.'),
 ('02_architecture','Architecture système et interfaces','02_Etudes','Entrée 24 V -> protections -> conversion 12 V -> calculateur, switch et écran. Carte acquisition -> SPI -> microcontrôleur -> liaison locale au calculateur. Sondes Pt100 -> acquisition. Liaisons Ethernet -> switch -> traversées panneau. Tous les raccords sont démontables et repérés.'),
 ('03_electronique','Définition cartes acquisition et alimentation','03_Conception','ACQ : huit voies analogiques avec adaptation 0-10 V, convertisseur 16 bits, MCU de supervision et borniers. PWR : protection polarité, fusible et TVS, DC/DC vers 12 V. Les valeurs sont des hypothèses documentaires, sans PCB fabricable ni qualification électrique réelle.'),
 ('04_aluminium','Plan de définition aluminium','03_Conception','Embase 240 x 180 x 3 mm. Capot plié en tôle supposée aluminium 2 mm. Fixations M3 accessibles, arêtes ébavurées, point de masse identifié. Tolérance générale documentaire +/-0,5 mm. Encombrement nominal assemblé 250 x 190 x 95 mm.'),
 ('05_plastiques','Plan façade et supports plastiques','03_Conception','Façade ABS 180 x 80 x 3 mm avec logement écran, deux supports internes imprimés et inserts M3. Inspection simulée : jeu écran 0,5 mm, passage faisceau libre, démontage possible. Aucun modèle natif de fabrication n’est prétendu fourni par ce PDF.'),
 ('06_logiciel','Spécification logiciel et firmware','03_Conception','Firmware 0.1.0-recette : balayage 10 Hz, watchdog 2 s, alarme capteur. Collecteur Linux : mesures, horodatage ISO 8601, CSV UTF-8 et rotation journaux. Interface locale : états en 1 s. Identifiant logiciel fictif ATLAS12-SIM-001 ; aucun commit inexistant n’est présenté comme réel.'),
 ('07_achats','Devis et commande de simulation','05_Achats','Fournisseurs et fabricants nommés RECETTE sont inventés. Les quantités couvrent deux prototypes et un reliquat de maintenance. Les prix sont des données de test, sans valeur commerciale. Toutes les réceptions seront explicitement marquées fictives.'),
 ('08_qualite','Plan qualité et déclarations fictives','04_Qualite','Contrôle des références, quantité, intégrité et repérage. Les pièces passent de quarantaine à libéré après contrôle documentaire simulé. Les mentions RoHS/REACH, matières et pratiques ISO sont des données de recette, jamais des certificats ou attestations authentiques.'),
 ('09_gamme','Instruction assemblage et contrôles','06_Prototype','Assembler ACQ et PWR ; monter le sous-ensemble BOX ; installer calculateur, switch et écran ; raccorder faisceau et sondes ; charger les versions logicielles de simulation ; contrôler polarité hors tension ; effectuer les essais documentaires. Chaque sous-ensemble possède une nomenclature distincte.'),
 ('10_plan_essais','Plan essais et critères d’acceptation','07_Essais','Les douze critères du CDC sont vérifiés par simulation documentaire. Contrôles négatifs : coût inconnu, stock insuffisant, auto-composition BOM, mauvais identifiant de validation. Contrôles positifs : sauvegarde, import MSG correct, réservation et assemblage traçable. Aucun essai physique n’est exécuté.'),
 ('11_resultats','Rapport de simulation des essais','07_Essais','Résultats de scénario : erreur tension 0,4 %; température 0,6 °C; perte réseau 0,02 %; rafraîchissement 1 s; alarme 2 s; puissance 39 W; masse 2,2 kg; redémarrage 22 s; maintenance 16 min; CSV 10 000 lignes. Toutes ces mesures sont inventées pour tester les champs et les décisions.'),
 ('12_maintenance','Notice opérateur et maintenance','08_Livraison','Mettre hors tension avant intervention. Identifier l’appareil et les cartes par les références de recette et la généalogie. Exporter les mesures avant maintenance. Remplacer les modules à références compatibles puis rejouer les contrôles. Séparer aluminium, plastiques et électronique en fin de vie.'),
 ('13_livraison','Procès-verbal de livraison fictive','08_Livraison','Deux appareils de simulation ATLAS12-REC-001 et ATLAS12-REC-002 sont prévus. Vérifier CDC accepté, liste des composants, nomenclatures, versions, documents et résultats de test. Réception documentaire fictive, sans expédition ni acceptation d’un client réel.'),
 ('14_tracabilite','Matrice de traçabilité exigences et livrables','09_Recette','La matrice relie chaque S.F. à ses deux ou trois S.T., aux articles du catalogue et aux documents justificatifs. Les liens sont vérifiés par relecture de la base et des fichiers archivés. Les horodatages techniques restent les dates réelles de recette.'),
 ('15_limites','Limites et statut de cette recette','09_Recette','Ce dossier sert exclusivement à tester OddWorks sur DEV. Les mesures, références, sociétés, accords et fabrications sont fictifs. La réussite logicielle ne vaut ni certification réglementaire, ni validation de matériel, ni recette utilisateur. Les contrôles serveur réellement exécutés sont consignés séparément dans le rapport final.'),
]
