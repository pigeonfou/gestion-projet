#!/usr/bin/env python3
"""Générateur du dossier fictif LUMI-12. Aucun accès à OddWorks ou au réseau."""
from pathlib import Path
from xml.etree import ElementTree as ET
import json, csv, hashlib, math, subprocess, shutil, zipfile
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import ImageReader
from reportlab.graphics.shapes import Drawing, Line, Rect, Circle, String, Polygon
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parent
for folder in ['00_Parcours','01_Besoin_et_validations','02_Fournisseurs','03_Electronique','04_Mecanique','05_Logiciel','06_Essais_et_livraison','99_Correction']:
    (ROOT/folder).mkdir(exist_ok=True)
FONT='/usr/share/fonts/truetype/dejavu/'
for name, file in [('DV','DejaVuSans.ttf'),('DVB','DejaVuSans-Bold.ttf'),('DVM','DejaVuSansMono.ttf')]:
    pdfmetrics.registerFont(TTFont(name,FONT+file))
INK=colors.HexColor('#183046'); CYAN=colors.HexColor('#007F91'); PURPLE=colors.HexColor('#6741A5')
ORANGE=colors.HexColor('#A75200'); GREEN=colors.HexColor('#24714C'); RED=colors.HexColor('#AB3143')
PALE=colors.HexColor('#F0F5F8'); LIGHT=colors.HexColor('#D8E5EB')
styles=getSampleStyleSheet()
for n in ['BodyText','Normal']: styles[n].fontName='DV';styles[n].fontSize=9;styles[n].leading=13;styles[n].textColor=INK
styles.add(ParagraphStyle(name='TitleL',fontName='DVB',fontSize=27,leading=32,textColor=INK,spaceAfter=12))
styles.add(ParagraphStyle(name='SubL',fontName='DV',fontSize=12,leading=17,textColor=CYAN,spaceAfter=14))
styles.add(ParagraphStyle(name='H',fontName='DVB',fontSize=15,leading=19,textColor=INK,spaceBefore=10,spaceAfter=9))
styles.add(ParagraphStyle(name='SmallL',fontName='DV',fontSize=7.5,leading=10,textColor=INK))
styles.add(ParagraphStyle(name='Cell',fontName='DV',fontSize=8,leading=11,textColor=INK))
styles.add(ParagraphStyle(name='CardTitle',fontName='DVB',fontSize=9,leading=12,textColor=CYAN,spaceAfter=5))
styles.add(ParagraphStyle(name='MonoL',fontName='DVM',fontSize=8,leading=11,textColor=INK))
PDFS=[]
def p(t,style='BodyText'):return Paragraph(t,styles[style])
def gap(h=8):return Spacer(1,h)
def card(label, text, color=CYAN):
    # A label AND a color encode the action, so the guide remains readable in grayscale.
    title=Paragraph(label,ParagraphStyle(name='temp',parent=styles['CardTitle'],textColor=color))
    body=[p(x) for x in text] if isinstance(text,list) else [p(text)]
    t=Table([[title],*[ [b] for b in body]],colWidths=[475])
    t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),colors.Color(color.red,color.green,color.blue,alpha=.06)),('LINEBEFORE',(0,0),(0,-1),4,color),('LEFTPADDING',(0,0),(-1,-1),12),('RIGHTPADDING',(0,0),(-1,-1),12),('TOPPADDING',(0,0),(-1,0),9),('BOTTOMPADDING',(0,-1),(-1,-1),9)]))
    return KeepTogether([t,gap(9)])
def table(headers,rows,widths=None):
    widths=widths or [475/len(headers)]*len(headers)
    t=Table([[p('<font color="#FFFFFF"><b>'+str(x)+'</b></font>','Cell') for x in headers]]+[[p(str(x),'Cell') for x in row] for row in rows],colWidths=widths,repeatRows=1,hAlign='LEFT')
    t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),INK),('TEXTCOLOR',(0,0),(-1,0),colors.white),('VALIGN',(0,0),(-1,-1),'TOP'),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,PALE]),('LINEBELOW',(0,0),(-1,0),1,CYAN),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),7),('BOTTOMPADDING',(0,0),(-1,-1),7)]))
    # Paragraph colors do not inherit the table's text color.
    for cell in t._cellvalues[0]:cell.style=ParagraphStyle(name='head',parent=styles['Cell'],fontName='DVB',textColor=colors.white)
    return t
def footer(c,doc):
    w,h=doc.pagesize
    c.saveState();c.setFillColor(INK);c.rect(0,h-31,w,31,fill=1,stroke=0)
    c.setFont('DVB',8);c.setFillColor(colors.white);c.drawString(42,h-20,'ODDWORKS / LUMI-12 / DOSSIER DE RECETTE')
    c.setFont('DV',7);c.setFillColor(RED);c.drawRightString(w-42,23,'FICTIF - FORMATION - SANS VALIDITE INDUSTRIELLE')
    c.setFillColor(INK);c.drawString(42,23,f'Page {doc.page}')
    c.setStrokeColor(LIGHT);c.line(42,37,w-42,37);c.restoreState()
def make_pdf(rel,title,subtitle,body):
    path=ROOT/rel
    doc=SimpleDocTemplate(str(path),pagesize=(595.28,841.89),leftMargin=60,rightMargin=60,topMargin=52,bottomMargin=53,title=title,author='OddWorks - scenario fictif',invariant=1)
    doc.build([p(title,'TitleL'),p(subtitle,'SubL'),*body],onFirstPage=footer,onLaterPages=footer)
    PDFS.append(path)
    return path
def page(title,body): return [p(title,'H'),*body]
def bullets(items):return [p('• '+x) for x in items]

PROJECT='RECETTE-MANUELLE-LUMI12-2026'
COMMIT_A='a17c4d8e91f23567b0c9d4e6f8123456789abcde'
COMMIT_B='b28d5e9f02a34678c1d0e5f70923456789abcdef'
ST=[
 ('S.T.1.1','Adaptateur de laboratoire 12 V','Matériel','1','8,00','8,00','2','Faible'),
 ('S.T.1.2','LED verte 3 mm','Composant','2','0,60','1,20','3','Moyen'),
 ('S.T.1.3','Résistances de limitation','Composant','2','0,15','0,30','2','Faible'),
 ('S.T.1.4','Diode de protection','Composant','1','0,50','0,50','2','Faible'),
 ('S.T.1.5','Carte nue 40 x 25 mm','PCB','1','6,00','6,00','5','Moyen'),
 ('S.T.1.6','Contrôle électrique sous-traité','Prestataire','1','4,00','4,00','2','Faible'),
 ('S.T.2.1','Boîtier PETG et couvercle','3D','-','-','0,00','2','Faible'),
 ('S.T.3.1','Outil PC de recette C++','Logiciel','-','-','0,00','1','Faible')]
TASKS=[
 ('T01','[S.T.1.1] Adaptateur de laboratoire 12 V','2026-11-03','2026-11-04','-','2026-11-04'),
 ('T02','[S.T.1.2] LED verte 3 mm','2026-11-03','2026-11-06','-','2026-11-09'),
 ('T03','[S.T.1.3] Résistances de limitation','2026-11-03','2026-11-05','-','2026-11-05'),
 ('T04','[S.T.1.4] Diode de protection','2026-11-03','2026-11-05','-','2026-11-05'),
 ('T05','[S.T.1.5] Carte nue 40 x 25 mm','2026-11-04','2026-11-09','-','2026-11-12'),
 ('T06','[S.T.1.6] Contrôle électrique sous-traité','2026-11-12','2026-11-13','T05','2026-11-17'),
 ('T07','[S.T.2.1] Boîtier PETG et couvercle','2026-11-04','2026-11-06','-','2026-11-06'),
 ('T08','[S.T.3.1] Outil PC de recette C++','2026-11-04','2026-11-05','-','2026-11-05')]

def generate_guide():
    b=[card('VOTRE MISSION','Vous êtes le pilote de LUMI-12. Saisissez le projet vous-même dans OddWorks. Les personnes qui interviennent dans les réunions sont des personnages ; toutes les opérations applicatives se font avec <b>votre compte actuel</b>.'),
       card('COMMENT LIRE CE GUIDE', ['<b>VIOLET / REÇU</b> : réunion, mail ou document qui arrive dans la vie du projet.','<b>BLEU / À FAIRE</b> : chemin exact, valeurs à saisir et bouton à utiliser.','<b>VERT / CONTRÔLE</b> : résultat à constater avant de poursuivre.','<b>ORANGE / IMPRÉVU</b> : donnée nouvelle ou écart à traiter.','<b>ROUGE / ARRÊT</b> : opération exclue ou dépendance manquante.'],PURPLE),
       card('RÈGLE DE RÉALISME','Deux prototypes sont à développer. Le scénario avance du 02 au 18 novembre 2026, mais vous pouvez le saisir en une seule séance. N’attendez pas les jours simulés. Ne changez ni l’horloge du serveur ni les droits d’un compte.',ORANGE),
       p('Le guide donne les actions. Le corrigé 99_Correction/99_CORRIGE_LUMI12.pdf est à ouvrir après exécution. Tous les éléments sont fictifs, y compris les fiches fournisseur, certificats, mesures et identifiants Git.'),PageBreak()]
    scenes=[
    ('01','Avant de commencer','Préparer votre séance',
     [('REÇU','Vous recevez ce dossier pour un exercice autonome. Les fichiers CAO, les PDF et les deux identifiants Git sont déjà fournis. Aucun logiciel de CAO n’est nécessaire pour saisir les métadonnées dans OddWorks.',PURPLE),
      ('À FAIRE - INSTALLATION DU DOSSIER',['1. Téléchargez puis extrayez LUMI12_Dossier_complet.zip. Conservez l’arborescence.','2. Ouvrez https://www.pigeonfou.com/oddworks/ et connectez-vous avec votre compte habituel.','3. Projets → Nouveau projet. Nom : <b>'+PROJECT+'</b>. Description : « EXERCICE FICTIF - deux témoins 12 V et leur banc PC. Aucun achat ni essai réel. »','4. Origine : cochez <b>Service Technique</b>. Destination : <b>Interne</b>. Enregistrer.'],CYAN),
      ('CONTRÔLE','Le projet apparaît parmi les projets actifs. Son TRL est non évalué. Ne reprenez pas le projet automatique « Mini témoin LED » utilisé lors de la recette précédente.',GREEN)]),
    ('02','02/11/2026 - 09 h 00','Réunion de lancement',
     [('REÇU - CR01','La direction technique demande deux témoins lumineux pour les essais de laboratoire. Claire Martin (direction), Sami Durand (électronique), Élise Robin (mécanique) et vous convenez d’un budget de 60 € HT et d’une livraison le 13 novembre. Le CDC provisoire ne fixe pas encore le courant maximal ni la polarité.',PURPLE),
      ('À FAIRE - ÉTAPE 1',['1. Projet → Cahier des charges → Contexte. Objectif : « Deux témoins 12 V, visibles sur banc, accompagnés d’un outil PC de recette. »','2. Hors périmètre : série, certification produit et communication réseau. Contraintes : 12 V CC ; deux prototypes ; budget global 60 € HT. Utilisateur : technicien de laboratoire.','3. Dans Planning : budget cible <b>60</b>, base <b>HT</b>, quantité d’équipements <b>2</b>, délai cible <b>11 jours calendaires</b>. Périmètre : « Deux prototypes ; achats et contrôle externe ; les activités Logiciel/3D sont suivies en délai. »','4. Ajouter deux jalons : « Revue GO/NO GO » au 03/11/2026 et « Livraison initiale » au 13/11/2026. Enregistrer le brouillon.','5. Processus R1b → étape 1 → Ajouter / gérer les notes → nouvelle note : « [02/11 - CR01] Lancement fictif ; CDC R0.1 en revue, ne pas valider. » → Ajouter la note.'],CYAN),
      ('DOCUMENT À LIRE','01_Besoin_et_validations/01_CR_Lancement.pdf puis 02_CDC_R01_BROUILLON.pdf.',PURPLE)]),
    ('03','02/11/2026 - 15 h 00','Le CDC revient refusé',
     [('REÇU - VISA REFUSÉ','La revue retourne le CDC R0.1. Il manque : plage de tension, protection d’inversion, courant maximal et critères de réception. La direction ne valide pas un document incomplet.',ORANGE),
      ('À FAIRE - RETOUR DOCUMENTAIRE',['1. Lisez 03_AVIS_Refus_CDC_R01.pdf. Le refus concerne <b>le document</b>, pas l’abandon du projet.','2. Étape 1 → Notes → Ajouter une note : « [02/11 - VISA R0.1 REFUSÉ] Préciser 10,8 à 13,2 V ; protection de polarité ; I total ≤ 25 mA ; contrôle des deux LED. Correction demandée, projet maintenu. »','3. Ne cliquez pas NO GO. Ne validez pas l’étape 1 à ce stade. Préparez la version corrigée dans l’épisode suivant.'],CYAN),
      ('CONTRÔLE','Le projet reste actif à l’étape 1. La demande de correction est visible dans les notes. Le refus et la version du CDC sont explicitement reliés.',GREEN)]),
    ('04','03/11/2026 - 09 h 00','CDC R1.0 accepté',
     [('REÇU - BASELINE','Le CDC R1.0 intègre les critères demandés. Le visa de la direction est favorable. Il devient la référence contractuelle fictive du développement.',PURPLE),
      ('À FAIRE - CORRECTION ET VALIDATION',['1. Cahier des charges → remplacez les contraintes par : « Entrée 12 V CC, 10,8 à 13,2 V ; inversion protégée ; I total ≤ 25 mA ; PCB 40 x 25 mm ; boîtier 52 x 37 x 20 mm. »','2. Onglet Spécifications : S.F.1 = « Signaler la présence de l’alimentation par deux LED vertes ». S.F.2 = « Protéger et fixer la carte dans un boîtier PETG ». S.F.3 = « Tracer la réception électrique de chaque prototype avec l’outil PC ». Trois fonctions obligatoires.','3. Livrables : « Deux prototypes, schéma .sch, PCB .brd, STEP, version du logiciel et rapport d’essais. » Enregistrer puis Générer le cahier des charges.','4. Note étape 1 : « [03/11] CDC LUMI-CDC-001 R1.0 accepté selon le visa figurant dans le PDF validé. » Puis <b>Valider l’étape</b>.','5. Maturité technologique → entrée <b>2</b>, actuel <b>2</b>, cible <b>7</b>. Preuve : « EXERCICE FICTIF - concept défini dans CDC R1.0, pas de preuve expérimentale à ce stade. » Enregistrer.'],CYAN),
      ('DOCUMENT À LIRE','04_CDC_R10_VALIDE.pdf. Le PDF fourni est la version de référence de l’exercice ; le texte généré par OddWorks sert à contrôler la saisie, pas à fabriquer un visa réel.',GREEN)]),
    ('05','03/11/2026 - 10 h 00','Études techniques et estimations',
     [('REÇU - REVUE TECHNIQUE','L’équipe propose une carte à deux branches LED, protégée par diode. La mécanique et l’outil PC n’engendrent pas de coût d’achat dans ce modèle de suivi. Les montants d’étude sont <b>par prototype</b>.',PURPLE),
      ('À FAIRE - ÉTAPE 2',['1. Reportez les <b>8 lignes</b> de la fiche de saisie de la page suivante. Utilisez Ajouter une S.T. dans la bonne S.F.','2. Les quantités de S.T. sont celles d’un prototype. Gardez la quantité globale du CDC à 2 : ne doublez pas les quantités techniques.','3. Pour S.T.1.2, choisissez comme parent <b>S.T.1.5 - Carte nue 40 x 25 mm</b>. Ne choisissez pas le type « S.T.x.x » : ici, vous créez une hiérarchie, pas une référence réutilisée.','4. Pour 3D et Logiciel, ne saisissez aucun prix. Enregistrer les spécifications techniques. Attendez le message de succès avant toute navigation.','5. Vérifiez que les huit tâches automatiques existent, puis Valider l’étape.'],CYAN),
      ('CONTRÔLE','Total d’étude par prototype : <b>20,00 € HT</b>. Périmètre CDC : <b>40,00 € HT</b>. Le plus long délai élémentaire est 5 jours ; ce n’est pas automatiquement le délai global du projet.',GREEN)]),
    ('06','Fiche de saisie','Les huit S.T. et leurs valeurs',
     [('À FAIRE - TRANSCRIPTION','Utilisez le tableau ci-dessous. La notation est attendue si les trois S.F. ont été créées dans l’ordre indiqué. Prix en euros HT ; délais en jours.',CYAN),
      ('NOTE DE COHÉRENCE','S.T.1.3 s’intitule « Résistances de limitation » sans figer sa valeur dans le titre. Les versions A et B du dossier électronique permettront ensuite de tracer le passage de 470 Ω à 1 kΩ.',ORANGE)]),
    ('07','03/11/2026 - 11 h 00','Décision GO et mise en planning',
     [('REÇU - CR03','La direction autorise la poursuite sous réserve de recevoir les justificatifs fournisseur et de vérifier le courant des deux prototypes. L’enveloppe reste à 60 € HT.',PURPLE),
      ('À FAIRE - ÉTAPE 3',['1. Consultez la synthèse : 8 S.T., coût 40 € HT pour le périmètre, solde 20 € HT.','2. Dans Justification / réserves du GO : « [03/11 - CR03] GO fictif pour deux prototypes. Budget 60 € HT ; estimation 40 € HT. Réserve : documents fournisseur et essais de courant à lever avant livraison. » Puis GO.','3. Ouvrez les tâches créées depuis le projet. Affectez-les toutes à <b>votre compte actuel</b>. Les personnes citées dans les réunions ne deviennent pas des comptes.','4. Saisissez les dates de début/échéance de la fiche planning suivante. Pour le contrôle externe, imposez comme tâche précédente la tâche Carte nue.','5. Ouvrez Planning &amp; Gantt et vérifiez les huit lignes. Ne changez pas les dates métier de réalisation tant que l’événement correspondant n’est pas arrivé dans le récit.'],CYAN),
      ('CONTRÔLE','Le GO est enregistré. La tâche de contrôle est dépendante de la disponibilité de la carte. Aucun courriel réel et aucune invitation ne sont envoyés.',GREEN)]),
    ('08','Fiche de saisie','Planning initial des tâches',
     [('À FAIRE - DATES','Les dates ci-dessous sont les dates prévues du scénario. La colonne Réalisation ne doit être saisie qu’en suivant les événements ultérieurs.',CYAN),
      ('IMPORTANT - RETARD SIMULÉ','La date serveur reste réelle. Un badge « en retard » ne s’affichera que si l’échéance est passée par rapport à cette date réelle. Pour cet exercice, tracez le retard par les dates métier et les notes ; ne modifiez pas l’horloge pour forcer un badge.',ORANGE)]),
    ('09','04/11/2026','Préparer les achats et les dossiers',
     [('REÇU - CONSULTATION','Les fournisseurs fictifs proposent une carte à 6 €, des LED à 0,60 € et les autres éléments au prix d’étude. Le prestataire retenu s’appelle <b>Atelier Horizon Fictif</b>. Les documents techniques A et les justificatifs sont disponibles dans le dossier.',PURPLE),
      ('À FAIRE - ÉTAPE 4',['1. Pour les six S.T. achetables, ajoutez une ligne de composant. Désignation et prix = valeurs d’étude ; références : ADP12-F, LED3-G-F, RES-470-F, DIO-S-F, PCB-L12-A, CTRL-L12-F. Fournisseur = Atelier Horizon Fictif. Quantités : 1, 2, 2, 1, 1, 1.','2. Pour 3D et Logiciel, affectez votre compte et saisissez les délais 2 et 1. Enregistrer les composants.','3. Lisez le schéma et le PCB dans 03_Electronique/Revision_A ; la 3D dans 04_Mecanique ; le commit A dans 05_Logiciel/VERSIONS_FICTIVES.txt.','4. Dans Notes, indiquez les noms des documents effectivement reçus. Ne remplissez pas un prix pour 3D/Logiciel.','5. Validez l’étape 4 après sauvegarde, puis passez à l’épisode achats.'],CYAN),
      ('DOCUMENTS REÇUS','Les datasheets du dossier 02_Fournisseurs sont synthétiques. Les résistances à 470 Ω de la révision A sont intentionnellement le mauvais choix de conception ; ne les corrigez pas avant le premier essai.',ORANGE)]),
    ('10','04/11/2026','Transformer la liste en tâches d’achat',
     [('REÇU - DEMANDE INTERNE','Vous préparez des tâches d’achat, pas des commandes réelles. Les quantités des lignes de l’étape 4 sont celles d’un prototype, comme le modèle de données de l’exercice.',PURPLE),
      ('À FAIRE - ÉTAPE 5',['1. Cochez les six lignes achetables. Affectez votre compte. Cliquez Générer / mettre à jour les tâches d’achat.','2. Sans changer les choix, recliquez une fois. Le second résultat attendu est <b>0 créée, 6 mises à jour</b>.','3. Dans chaque tâche, ajoutez au résultat/contexte : « EXERCICE FICTIF - approvisionnement pour DEUX prototypes : adapter la quantité de commande à 2 × la quantité par prototype. Aucune commande à émettre. »','4. Important : la génération des tâches achat ne constitue pas une multiplication garantie par la quantité globale du CDC. Vérifiez la description générée ; conservez le calcul manuel dans la note.','5. Ne validez pas encore l’étape 5 : attendez l’épisode de livraison des cartes et des LED.'],CYAN),
      ('CONTRÔLE','Six tâches achat existent, sans doublon. Les tâches automatiques de S.T. et les sous-tâches de réalisation PCB/3D/Logiciel sont des objets différents ; ne les supprimez pas en les prenant pour des doublons.',GREEN)]),
    ('11','05/11/2026','Le fournisseur annonce un retard et une hausse',
     [('IMPRÉVU - MAIL FOURNISSEUR','La carte prévue le 9 novembre arrivera le 12. Le prix passe de 6,00 à 7,50 € HT. Les LED prévues le 6 sont substituées à 0,80 € l’unité et seront reçues le 9. La direction accepte le surcoût, sans augmenter l’enveloppe.',ORANGE),
      ('À FAIRE - MISE À JOUR MAÎTRISÉE',['1. Lisez 05_MAIL_Retard_et_Substitution.pdf et 06_ACCORD_Surcout.pdf dans 01_Besoin_et_validations.','2. Retournez étape 2 : S.T.1.5 = coût unitaire <b>7,50</b>, délai <b>8</b>, risque <b>Fort</b>. S.T.1.2 = coût unitaire <b>0,80</b>, délai <b>4</b>, risque <b>Moyen</b>. Enregistrer.','3. Étape 4 : mettez les mêmes prix/délais pour les lignes PCB et LED ; LED devient LED3-G-F2. Enregistrer. Étape 5 : régénérez les tâches achat pour les mettre à jour.','4. Ne décalez pas silencieusement les échéances initiales des tâches PCB/LED. Dans leurs résultats, notez respectivement « prévu 09/11, reçu 12/11 : +3 j calendaires » et « prévu 06/11, reçu 09/11 : +3 j calendaires ».','5. Ajouter un jalon « Livraison révisée » au 18/11/2026. Conservez le jalon initial du 13/11. Note : « Date révisée approuvée ; retard de livraison projet = 5 j calendaires, soit 3 jours ouvrés lun-ven. »'],CYAN),
      ('CONTRÔLE','Nouvelle estimation : <b>21,90 € HT par prototype ; 43,80 € HT pour deux ; solde 16,20 € HT</b>. Le GO historique doit encore montrer ses anciennes sources à 40 € HT.',GREEN)]),
    ('12','06/11/2026','Revue documentaire fournisseur',
     [('REÇU - CERTIFICAT PÉRIMÉ','ISO 9001 est encore valable. Le premier certificat ISO 14001 fourni est expiré depuis le 30/09/2026. Vous recevez ensuite une version renouvelée. Une fiche SDS PETG et des datasheets sont jointes.',PURPLE),
      ('À FAIRE - QUALITÉ',['1. Qualité fournisseurs → Créer un fournisseur : nom <b>Atelier Horizon Fictif</b>. ISO 9001 : Oui, validité au 31/12/2028. ISO 14001 : Oui, validité au 30/09/2026. Associez les preuves si Nextcloud est disponible.','2. Reliez les six références physiques de l’étape 4 à ce fournisseur. ISO 14001 expiré ne doit pas produire une couverture valide. Laissez RoHS/REACH <b>Non renseigné</b> : les PDF de cet exercice ne sont pas des attestations réglementaires.','3. Pour la référence PCB, créez une action corrective affectée à vous : « RECETTE - obtenir le certificat 14001 renouvelé ».','4. À réception du certificat renouvelé : remplacez la validité ISO 14001 par <b>19/10/2029</b>, référence ISO14-F-2026-02. Ajoutez une note « reçu le 06/11 ; document fictif ». Terminez l’action avec cette preuve/référence.','5. Sans Nextcloud : conservez les noms et dates dans les notes, mais ne prétendez pas que la preuve a été téléversée.'],CYAN),
      ('POINT DE VIGILANCE','Une tâche corrective terminée ne fait pas disparaître une information de conformité inconnue. La SDS d’un filament n’est ni un certificat ISO ni une attestation RoHS/REACH.',ORANGE)]),
    ('13','06 au 12/11/2026','Réceptions et retards réellement tracés',
     [('REÇU - LIVRAISONS','Le boîtier est prêt le 6, les LED le 9, et les PCB le 12. Le banc logiciel A est prêt le 5. Vous conservez les échéances initiales dans les tâches et renseignez les dates métier de réalisation.',PURPLE),
      ('À FAIRE - TÂCHES ET STOCK',['1. Dans les huit tâches principales, utilisez la colonne Réalisation de la fiche planning. Affectation : vous. Résultats : « RECETTE FICTIVE - réception/réalisation simulée le ... ». Statut : Terminée, sauf contrôle externe encore à faire.','2. Terminez les six tâches achat quand leur réception fictive est décrite : adaptateur 04/11, LED 09/11, résistances 05/11, diode 05/11, PCB 12/11, prestation 17/11. Pour cette dernière, attendez l’épisode 16.','3. Si vous essayez de commencer la tâche Contrôle externe avant de terminer Carte nue, vous devez être bloqué. Après réception de la carte, la dépendance est levée.','4. Stocks → Nouvel article : réf REC-L12-LED, désignation « RECETTE FICTIVE - LED verte », valeur 0,80 HT. Stock avancé → Lots : créez REC-L12-LOT01, quantité <b>10</b>, date 09/11/2026, projet courant, statut Quarantaine, emplacement LABO/PROJETS.','5. Ouvrez l’article : stock 0. Revenez au lot → Libérer et mettre en stock. Rouvrez l’article : stock <b>10</b>, une seule entrée physique. Toutes ces unités sont fictives.'],CYAN),
      ('CONTRÔLE','Le retard n’est pas masqué par un effacement des dates initiales. La quarantaine n’alimente pas le stock disponible. Les données du projet et les lignes de stock portent RECETTE/FICTIF.',GREEN)]),
    ('14','12/11/2026','Intégration et première vérification',
     [('REÇU - PROTOTYPES A','Les deux cartes A sont assemblées avec R1/R2 = 470 Ω. Le premier outil C++ A ne contrôle que l’allumage des LED et accepte à tort les prototypes. Le contrôle indépendant mesure environ 45 mA à 13,2 V.',ORANGE),
      ('À FAIRE - ÉTAPES 6 ET 7',['1. Étape 5 : validez les achats une fois les réceptions simulées prises en compte. Étape 6 : notez les fichiers LUMI12_A.sch, LUMI12_A.brd et le commit A fictif.','2. Terminez les tâches de réalisation PCB (12/11), 3D (06/11) et Logiciel (05/11) issues de l’étape 4, en plus des tâches principales correspondantes. Ne renseignez jamais un commit réel du dépôt OddWorks comme version de LUMI-12.','3. Validez l’étape 6. Lisez 02_RAPPORT_Essais_R01_NONCONFORME.pdf.','4. Étape 7 → Motif de non-conformité : « [12/11 - ESS-R01] 45,4 et 44,8 mA à 13,2 V, limite 25 mA. Résistances 470 Ω trop faibles ; outil A incomplet. » → Non conforme.','5. Constatez le retour à l’étape 6. Ajoutez une note qui relie l’écart au CDC R1.0 et à DM01.'],CYAN),
      ('CONTRÔLE','L’historique conserve la non-conformité avec son auteur et l’heure serveur. Le projet n’est ni abandonné ni livré. Le TRL n’augmente pas automatiquement avec les étapes.',GREEN)]),
    ('15','13 au 16/11/2026','Demande de modification DM01',
     [('REÇU - REPRISE APPROUVÉE','DM01 impose R1/R2 = 1 kΩ et une vérification du courant total par le logiciel B. Les prix des résistances restent à 0,15 €. La carte n’est pas refabriquée : elle est retouchée ; les nouveaux fichiers B deviennent le dossier de définition.',PURPLE),
      ('À FAIRE - ACTIONS CORRECTIVES',['1. Créez deux tâches manuelles : « RECETTE - DM01 remplacer R1/R2 par 1 kΩ » et « RECETTE - DM01 ajouter le contrôle I ≤ 25 mA ». Affectez-vous ; priorité Haute ; début 13/11, échéance 16/11.','2. Dans le contexte de chaque tâche, citez 01_DM01_Modification.pdf. Dans les résultats, citez respectivement les fichiers LUMI12_B.sch/.brd et le commit B fictif.','3. Terminez les deux tâches, date métier 16/11/2026, résultat « reprise simulée conforme à DM01 ».','4. Étape 4 : changez la référence des résistances en RES-1K-F, quantité 2, prix 0,15 HT ; enregistrez. Régénérez les tâches achat : la ligne de résistance doit être mise à jour, pas dupliquée.','5. Si la mise à jour a conservé une tâche terminée, contrôlez sa nouvelle description ; ne considérez pas cette mise à jour comme une nouvelle réception. Retournez étape 6 et validez la reprise.'],CYAN),
      ('CONTRÔLE','Coût total d’étude toujours 43,80 € HT pour deux. Révision A conservée, révision B distincte. Aucune règle spéciale du scénario n’a besoin d’être ajoutée au code d’OddWorks.',GREEN)]),
    ('16','17/11/2026','Contre-essais et dossier final',
     [('REÇU - ESS-R02','Les deux prototypes B consomment au maximum 21,4 et 21,8 mA à 13,2 V. L’allumage des deux LED et la protection en inversion sont jugés conformes dans le rapport fictif.',PURPLE),
      ('À FAIRE - CLÔTURE TECHNIQUE',['1. Terminez Contrôle externe et la tâche d’achat de prestation : date métier 17/11 ; résultats = référence ESS-R02.','2. Étape 7 : ajoutez une note « [17/11] ESS-R02 conforme au CDC R1.0 après DM01. Documents fictifs fournis ; aucune mesure réelle. »','3. Maturité technologique : actuel <b>4</b>, entrée 2, cible 7. Justification : « EXERCICE FICTIF - validation en laboratoire simulée dans ESS-R02 ; aucune démonstration opérationnelle. » Enregistrer.','4. Ne positionnez pas TRL 7 ou 8 pour atteindre la cible : le scénario ne fournit pas ces preuves.','5. Revenez étape 7 → Conforme → Livraison DG.'],CYAN),
      ('DOCUMENTS À RÉUNIR','CDC validé ; DM01 ; schéma/PCB B ; STEP ; commit B ; ESS-R02 ; PV de livraison ; datasheets, SDS et certificats identifiés comme fictifs.',GREEN)]),
    ('17','18/11/2026','Livraison, réserves et archivage',
     [('REÇU - CR05 / PV','La direction accepte les deux prototypes de démonstration. Le retard final est reconnu : livraison initiale 13/11, réelle simulée 18/11. La couverture documentaire fournisseur est suivie séparément de la conformité électrique.',PURPLE),
      ('À FAIRE - FIN DU PROJET',['1. Lisez le PV et le CR05. Ajoutez une note : « [18/11] Livraison fictive de 2 prototypes. Retard +5 jours calendaires / +3 jours ouvrés. Budget final estimé 43,80 € HT, enveloppe 60 €. TRL actuel 4. »','2. Vérifiez toutes les tâches de votre projet. Terminez celles dont la réalisation simulée est fournie ; laissez une éventuelle action documentaire sans preuve à l’état En cours, avec une note explicite.','3. Étape 8 → Conforme → Archivage validé/vente. Aucune vente réelle n’est réalisée par ce bouton dans l’exercice.','4. Projets → Archivage : vérifiez le projet à 100 %. Rouvrez-le : le TRL doit rester 4, la cible 7.','5. Ouvrez l’historique, filtrez Non conforme puis retirez le filtre. Vérifiez que le GO et l’échec ESS-R01 sont encore présents.'],CYAN),
      ('CONTRÔLE FINAL','Ouvrez maintenant le corrigé séparé. Conservez le projet et ses objets fictifs pour revue. Ne supprimez ni les versions A ni les documents refusés.',GREEN)]),
    ('18','Cas particulier','Documents, Nextcloud et absence de double compte',
     [('À FAIRE - SI NEXTCLOUD EST CONFIGURÉ',['Documents du projet → Importer un fichier sur Nextcloud. Importez les livrables au moment où le récit les reçoit. Conservez les noms et indices. Reliez schéma/PCB/datasheets à la S.T. concernée, STEP à S.T.2.1 et version logicielle à S.T.3.1.','Un refus de document se trace par une note avec son indice ; ne remplacez pas le document refusé par le document validé. Les deux fichiers existent.','Pour les certificats, saisissez également le statut et la date dans Qualité fournisseurs : déposer un PDF seul ne met pas les indicateurs à jour.'],CYAN),
      ('À FAIRE - SI NEXTCLOUD N’EST PAS CONFIGURÉ',['N’essayez pas de créer une URL de preuve inventée. Gardez les PDF/CAO localement et inscrivez les références exactes dans les notes et résultats de tâches.','Poursuivez les étapes du scénario en mode « preuve locale uniquement ». Dans le corrigé, marquez les tests de dépôt/lien documentaire <b>NON EXÉCUTÉS</b>, jamais Réussis.','La planification d’un ordre d’assemblage exige une instruction Nextcloud du projet. Si le sélecteur ne propose rien, arrêtez ce sous-parcours ; ne contournez pas l’exigence.'],ORANGE),
      ('ARRÊT - HORS EXERCICE','Ne changez pas de compte. N’envoyez aucun email, message, invitation ni commande. Ne créez aucune identité ou certification réelle. Ne changez pas les paramètres globaux ou l’horloge.',RED)])]
    for i,(num,date,title,cards) in enumerate(scenes):
        b+= [p('ÉPISODE '+num+' / '+date,'SubL'),p(title,'H')]
        for lab,txt,col in cards:b.append(card(lab,txt,col))
        if num=='06':b += [table(['S.T. / description','Type','Qté','CU HT','Total','Délai / risque'],[(x[0]+'<br/>'+x[1],x[2],x[3],x[4],x[5],x[6]+' j / '+x[7]) for x in ST],[155,64,30,46,46,134])]
        if num=='08':b += [table(['Repère / tâche principale','Début','Échéance','Après','Réalisation simulée'],[(x[0]+'<br/>'+x[1],x[2],x[3],x[4],x[5]) for x in TASKS],[207,64,64,36,104])]
        if i<len(scenes)-1:b.append(PageBreak())
    make_pdf('00_Parcours/00_GUIDE_EXECUTION_LUMI12.pdf','LUMI-12','Votre projet fictif, de la première réunion à l’archivage - guide d’exécution',b)

def generate_cdc():
    for rev,valid in [('R01',False),('R10',True)]:
        b=[card('STATUT DOCUMENTAIRE','<b>VALIDÉ POUR LA RECETTE</b> - visa fictif Claire Martin, 03/11/2026. Baseline R1.0.' if valid else '<b>BROUILLON SOUMIS À REVUE</b> - R0.1 du 02/11/2026. Non autorisé pour lancement.',GREEN if valid else ORANGE),
           p('LUMI-CDC-001 / '+('R1.0' if valid else 'R0.1'),'H'),
           p('Objet : développer deux témoins lumineux 12 V, protégés dans un boîtier PETG, et un outil PC de contrôle pour le laboratoire.'),gap(),
           table(['Exigence','Critère'],[
             ('S.F.1 - Indication','Deux LED vertes visibles à 1 m sur banc de laboratoire, dans une ambiance de bureau.'),
             ('S.F.2 - Intégration','Carte 40 x 25 mm ; boîtier externe 52 x 37 x 20 mm ; quatre fixations M2.'),
             ('S.F.3 - Traçabilité','Un enregistrement par prototype : identifiant, tension, courant total, résultat et version du logiciel.'),
             ('Alimentation','10,8 à 13,2 V CC ; 12 V nominal ; protection contre une inversion brève de polarité.' if valid else '12 V nominal ; plage de fonctionnement et polarité à préciser.'),
             ('Courant total','≤ 25 mA à toute tension de la plage, mesuré sur chaque prototype.' if valid else 'À définir en revue ; aucun seuil approuvé.'),
             ('Budget et quantité','60 € HT pour les deux prototypes ; quantité du périmètre = 2.'),
             ('Délais','GO le 03/11/2026 ; livraison initiale le 13/11/2026 ; objectif global = 11 jours calendaires après lancement.'),
           ],[120,355]),gap(),
           p('Hors périmètre : industrialisation série, batterie, alimentation secteur conçue en interne, essais réglementaires et qualification en environnement opérationnel.'),PageBreak(),
           p('Réception et livrables','H'),
           table(['Livrable','Règle documentaire'],[
             ('CDC','Conserver le brouillon refusé, l’avis de retour et le CDC validé.'),
             ('Électronique','Schéma .sch et PCB .brd dans un format CAO déclaré ; indices A et B distincts.'),
             ('Mécanique','Boîtier et couvercle STEP ; dimensions cohérentes avec la carte.'),
             ('Logiciel','Identifiant de commit FICTIF clairement identifié et source C++ de l’outil de recette.'),
             ('Fournisseurs','Datasheets, SDS PETG et certificats ISO simulés ; les preuves réglementaires non fournies restent inconnues.'),
             ('Essais','Pour chaque prototype : 10,8 V, 12 V et 13,2 V ; courant total ; deux LED ; protection d’inversion. Seuils applicables uniquement au scénario.' if valid else 'Plan de tests détaillé à écrire après la revue.'),
             ('Livraison','PV fictif de livraison ; dates initiale et révisée ; réserves et résultat de la recette.'),
           ],[120,355]),gap(),
           card('VISA FICTIF','Rédaction : Sami Durand. Revue mécanique : Élise Robin. Acceptation : Claire Martin. Ces noms sont des personnages et ces visas n’ont aucune valeur réelle.',PURPLE),
           p('Historique : R0.1 - émission initiale ; R1.0 - ajout des limites électriques, du plan de réception et de la protection de polarité. Le changement R1.0 ne modifie ni la quantité ni le budget initial.')]
        make_pdf('01_Besoin_et_validations/'+('04_CDC_R10_VALIDE.pdf' if valid else '02_CDC_R01_BROUILLON.pdf'),'Cahier des charges','LUMI-12 / '+('R1.0 - validé fictivement' if valid else 'R0.1 - brouillon'),b)

def short_pdf(rel,title,ref,date,sections):
    body=[card('IDENTIFICATION',f'<b>{ref}</b> / date métier simulée : {date}. Projet {PROJECT}. Ce document est entièrement fictif.',PURPLE)]
    for heading,txt in sections: body += [p(heading,'H'),*[p(t) for t in (txt if isinstance(txt,list) else [txt])],gap()]
    return make_pdf(rel,title,'Pièce du dossier de recette - aucune transaction ou validation réelle',body)

def generate_meetings():
    short_pdf('01_Besoin_et_validations/01_CR_Lancement.pdf','Réunion de lancement','CR01','02/11/2026 09:00',[
      ('Participants','Claire Martin, direction technique ; Sami Durand, électronique ; Élise Robin, mécanique ; vous, pilote. Ces intervenants sont fictifs.'),
      ('Décisions','Deux prototypes, budget global 60 € HT, livraison initiale le 13 novembre. Concept disponible, TRL de départ 2, cible 7 non garantie.'),
      ('Actions','Le pilote prépare le CDC R0.1 le 2 novembre. Sami estime les composants. Élise vérifie l’encombrement. La direction vise le CDC le 3 novembre.'),
      ('Points ouverts','Courant maximal, plage de tension et inversion de polarité restent à fixer. Le premier document ne doit pas être considéré comme validé.')])
    short_pdf('01_Besoin_et_validations/03_AVIS_Refus_CDC_R01.pdf','Retour de revue : corrections requises','VISA-CDC-R01','02/11/2026 15:00',[
      ('Décision','CDC R0.1 REFUSÉ pour insuffisance des critères de réception. Le projet est maintenu ; ce retour n’est pas un NO GO.'),
      ('Corrections demandées',['1. Définir une plage 10,8 à 13,2 V CC et la polarité.','2. Limiter le courant total à 25 mA, pour chaque prototype.','3. Prévoir la protection d’inversion et le contrôle des deux LED.','4. Identifier les fichiers, indices, mesures et version logicielle à livrer.']),
      ('Réponse attendue','Émettre R1.0 le 3 novembre et conserver R0.1 dans l’historique documentaire. Visa fictif : Claire Martin.')])
    short_pdf('01_Besoin_et_validations/07_CR_GO_Reserve.pdf','Revue GO / NO GO','CR03','03/11/2026 11:00',[
      ('État des connaissances','CDC R1.0 accepté. Huit S.T. identifiées. Coût de base 20,00 € HT par prototype, soit 40,00 € HT pour deux.'),
      ('Décision','GO fictif pour poursuivre les achats et la réalisation, sous réserve de justificatifs fournisseur et d’un rapport d’essais favorable.'),
      ('Réserves','Le maximum des délais élémentaires (5 j) n’est pas un planning global. Les documents ISO ne qualifient pas la sécurité électrique du produit.'),
      ('Traçabilité','Conserver dans la décision les sources d’étude à 40,00 € HT. Les changements ultérieurs seront visibles dans la synthèse courante, sans réécrire cette décision.')])
    short_pdf('01_Besoin_et_validations/05_MAIL_Retard_et_Substitution.pdf','Mail fournisseur : retard et substitution','MAIL-FOUR-05','05/11/2026 14:10',[
      ('Émetteur fictif','Atelier Horizon Fictif, service approvisionnement. Aucun domaine ni adresse réelle utilisés.'),
      ('Carte nue','La livraison initiale du 09/11 est reportée au 12/11. Prix par carte : 7,50 € HT au lieu de 6,00 €. Délai d’étude à reporter : 8 j.'),
      ('LED','La référence LED3-G-F est indisponible. Remplacement LED3-G-F2, mêmes critères simulés, à 0,80 € HT au lieu de 0,60 €. Livraison 09/11 au lieu du 06/11. Délai d’étude : 4 j.'),
      ('Demande','Retourner un accord interne de surcoût. Aucune commande réelle ne doit être transmise dans cet exercice.')])
    short_pdf('01_Besoin_et_validations/06_ACCORD_Surcout.pdf','Accord de surcoût et de délai','ACC-01','05/11/2026 16:00',[
      ('Calcul','PCB : +1,50 € par prototype. LED : 2 × 0,20 = +0,40 € par prototype. Total révisé : 21,90 € HT par prototype ; 43,80 € HT pour deux.'),
      ('Accord','Surcoût global +3,80 € HT accepté dans l’enveloppe de 60 €. Solde avant compléments : 16,20 € HT. Aucune augmentation de budget.'),
      ('Planning','Livraison initiale conservée : 13/11. Livraison révisée : 18/11. Écart : 5 jours calendaires, ou 3 jours ouvrés selon une semaine lundi-vendredi, sans autre calendrier.'),
      ('Action du pilote','Mettre à jour études, composants et tâches achat. Conserver les échéances initiales PCB/LED pour ne pas effacer le retard. Ajouter le jalon révisé.')])
    short_pdf('01_Besoin_et_validations/08_CR_Incident_Electrique.pdf','Réunion d’analyse de l’écart électrique','CR04','12/11/2026 17:00',[
      ('Constat','Le rapport ESS-R01 révèle environ 45 mA à 13,2 V, au-dessus de 25 mA. Les résistances de 470 Ω de la révision A sont trop faibles.'),
      ('Cause technique','Le calcul de courant a été sous-estimé. L’outil A ne vérifie que les deux indicateurs visuels et omet le seuil de courant. C’est un défaut intentionnel du scénario.'),
      ('Décision','Non-conformité, retour en fabrication. Appliquer DM01 : résistances 1 kΩ, logiciel B avec seuil 25 mA, contre-essais des deux prototypes.'),
      ('Traçabilité','La correction de valeur ne change pas les empreintes du PCB. Les cartes sont retouchées ; aucune nouvelle fabrication de cartes n’est simulée.')])
    short_pdf('01_Besoin_et_validations/09_CR_Cloture.pdf','Réunion de clôture et retour d’expérience','CR05','18/11/2026 11:00',[
      ('Livraison','Deux prototypes B acceptés fictivement après ESS-R02. Les sources A et les premiers essais restent conservés.'),
      ('Écarts','Retard fournisseur +3 j pour PCB et LED ; retard final +5 j calendaires. Surcoût estimé global +3,80 € HT. Budget final estimé 43,80 € HT.'),
      ('Leçons','Un résultat logiciel positif n’est pas une preuve indépendante. Le TRL est justifié par l’environnement d’essai et reste 4 ; la cible 7 n’est pas atteinte.'),
      ('Réserves documentaires','Si Nextcloud n’est pas configuré, les fichiers sont disponibles localement mais les tests de dépôt et de liaison documentaire restent non exécutés.')])

def generate_supplier_docs():
    specs=[('01_DATASHEET_LED3_G_F2.pdf','LED verte 3 mm','DS-LED-F2',[
       ('Usage simulé','Indicateur visuel traversant 3 mm, deux unités par carte.'),('Brochage du dossier CAO','A : anode / pad A ; K : cathode / pad K. Entraxe 2,54 mm.'),('Valeurs de calcul fictives','Tension directe de calcul : 2,0 V ; courant visé après correction : environ 10 à 11 mA par LED. Ces données ne décrivent aucun produit commercial.'),('Substitution','LED3-G-F2 remplace LED3-G-F ; prix d’étude 0,80 € HT/unité, livraison métier 09/11/2026.'),('Conformité','Aucune attestation RoHS ou REACH n’est fournie. Ne pas déduire une conformité de cette fiche.')]),
      ('02_DATASHEET_Diode_DIO_S_F.pdf','Diode de protection','DS-DIO-F',[
       ('Fonction','Diode Schottky fictive en série sur l’alimentation. D1, A vers entrée positive, K vers rail protégé.'),('Empreinte','Axiale 7,62 mm, deux pads A/K, perçages 1 mm. Valeur de calcul : chute 0,3 V pour le scénario.'),('Limite','L’exercice ne constitue pas une qualification thermique, CEM ou de tenue aux transitoires. Aucune fiche réelle ou certification de produit n’est reproduite.')]),
      ('03_DATASHEET_Resistances.pdf','Résistances de limitation','DS-RES-F',[
       ('Révision A','RES-470-F : 470 Ω, deux unités, prix 0,15 € HT/unité. Valeur intentionnellement insuffisante pour la limite de courant du CDC.'),('Révision B','RES-1K-F : 1 kΩ, deux unités, même prix, même empreinte axiale 7,62 mm. R1 et R2 sont remplacées selon DM01.'),('Calcul indicatif','À 13,2 V : (13,2 - 0,3 - 2,0) / 1000 ≈ 10,9 mA par branche ; deux branches ≈ 21,8 mA. Puissance par résistance ≈ 0,119 W. Le dossier prévoit des composants 0,25 W fictifs.'),('Limite','Valeurs pédagogiques. Les dispersions, températures et marges de fabrication ne sont pas qualifiées.')]),
      ('04_DATASHEET_Adaptateur_ADP12_F.pdf','Adaptateur de banc 12 V','DS-ADP-F',[
       ('Usage','Alimentation de laboratoire externe fictive, réglable pour les trois points 10,8 / 12,0 / 13,2 V. Ce dossier ne contient aucune conception secteur.'),('Raccordement','Sortie positive vers J1.1 ; retour vers J1.2. L’adaptateur est un matériel achetable : 8,00 € HT par prototype.'),('Limite','Les essais de polarité et d’alimentation décrits sont des événements simulés ; ne pas les reproduire sur du matériel réel à partir de cette fiche.')])]
    for filename,title,ref,rows in specs:
        short_pdf('02_Fournisseurs/'+filename,title+' - fiche synthétique',ref,'04/11/2026',rows)
    sds=[p('SDS-PETG-F / indice 1.0','H'),card('AVERTISSEMENT','Fiche synthétique de formation. Aucun mélange commercial identifié ; aucune donnée de sécurité vérifiée. <b>Ne pas utiliser pour décider d’un EPI, d’un stockage, d’un transport ou d’une intervention.</b> Utiliser la fiche réelle du fabricant pour tout produit réel.',RED)]
    sections=[('1. Identification','Filament fictif PETG-LUMI-F ; usage narratif : boîtier de prototype imprimé. Fournisseur : Atelier Horizon Fictif.'),('2. Dangers','Aucune classification réelle attribuée dans cet exercice ; ne pas interpréter le document comme une absence de danger.'),('3. Composition','Polymère et additifs non spécifiés ; aucune formulation commerciale ni numéro CAS simulé.'),('4. Premiers secours','Non renseigné : consulter la fiche réelle du produit et les consignes de l’établissement.'),('5. Incendie','Aucune donnée opérationnelle fournie pour cet exercice.'),('6. Dispersion accidentelle','Aucune procédure réelle fournie.'),('7. Manipulation et stockage','Non qualifiés ; suivre les instructions du fournisseur réel.'),('8. Exposition / protection','Aucune limite d’exposition ni recommandation d’EPI fabriquée.'),('9. Propriétés','Couleur illustrative : gris ; diamètre narratif du filament : 1,75 mm ; autres propriétés non mesurées.'),('10. Stabilité / réactivité','Non évaluées.'),('11. Toxicologie','Aucune donnée réelle disponible dans cette pièce fictive.'),('12. Écologie','Aucune donnée réelle disponible.'),('13. Élimination','Appliquer les filières et consignes réelles du site, pas ce document.'),('14. Transport','Aucune classification transport attribuée.'),('15. Réglementation','Ce document ne constitue pas une SDS réglementaire, ni une déclaration de conformité.'),('16. Autres informations','Créé exclusivement pour la recette documentaire OddWorks ; garder le marquage FICTIF dans tout export.')]
    for i,(h,t) in enumerate(sections):
        if i==8:sds.append(PageBreak())
        sds += [p(h,'H'),p(t)]
    make_pdf('02_Fournisseurs/05_SDS_PETG_FICTIVE.pdf','Fiche SDS - PETG fictif','Structure en 16 rubriques pour l’exercice documentaire uniquement',sds)
    certs=[('06_ISO9001_FICTIF.pdf','ISO 9001','ISO9-F-2026-01','01/01/2026','31/12/2028','VALIDE DANS LE RÉCIT'),('07_ISO14001_EXPIRE_FICTIF.pdf','ISO 14001','ISO14-F-2023-01','01/10/2023','30/09/2026','EXPIRÉ - NE PAS COMPTER COMME VALIDE'),('08_ISO14001_RENOUVELE_FICTIF.pdf','ISO 14001','ISO14-F-2026-02','20/10/2026','19/10/2029','RENOUVELÉ - REÇU LE 06/11/2026')]
    for fn,standard,ref,start,end,status in certs:
        make_pdf('02_Fournisseurs/'+fn,'Attestation fictive',standard+' / '+ref,[gap(18),card('SANS VALEUR DE CERTIFICATION','Ce document est une pièce de formation. Il n’est délivré par aucun organisme certificateur et ne démontre aucune certification réelle.',RED),gap(14),p('ATELIER HORIZON FICTIF','TitleL'),p('Périmètre narratif : approvisionnement et assemblage de petits prototypes de laboratoire.'),gap(20),table(['Champ','Valeur de recette'],[('Référentiel mentionné',standard+' - intitulé de scénario, pas de certification réelle'),('Référence',ref),('Début de validité simulé',start),('Fin de validité simulée',end),('Statut attendu',status),('Émetteur','Bureau de recette documentaire imaginaire - sans accréditation'),('Visa','SIMULATION / aucune signature ni marque d’accréditation réelle')],[160,315]),gap(18),p('Ce PDF sert uniquement à tester le statut, la date d’expiration, le renouvellement et la liaison d’une preuve dans OddWorks. Il ne certifie ni les composants ni le produit LUMI-12.')])

def generate_tests():
    short_pdf('06_Essais_et_livraison/01_DM01_Modification.pdf','Demande de modification DM01','LUMI-DM-001','13/11/2026',[
      ('Origine','ESS-R01 non conforme au CDC R1.0 : consommation totale trop forte et absence du contrôle de courant dans l’outil A.'),
      ('Modification approuvée','R1 et R2 passent de 470 Ω à 1 kΩ, puissance narrative 0,25 W. Empreintes et netlist inchangées. Retouche des cartes A, puis émission du dossier B.'),
      ('Logiciel','L’outil B doit exiger deux LED allumées, une tension dans [10,8 ; 13,2] V et un courant dans [0 ; 25] mA. Commit B fictif : '+COMMIT_B+'.'),
      ('Impacts','Deux tâches correctives datées du 13 au 16 novembre. Aucun supplément de coût matière simulé. Prix des résistances inchangé ; coût final estimé du périmètre toujours 43,80 € HT.'),
      ('Validation','Accord fictif Claire Martin le 13/11. Contre-essais le 17/11. Les indices A et ESS-R01 restent conservés.')])
    for rev,ok in [('R01',False),('R02',True)]:
        vals=[('P01','10,8','18,2' if ok else '36,2','Oui','Oui'),('P01','12,0','19,5' if ok else '40,4','Oui','Oui'),('P01','13,2','21,4' if ok else '45,4','Oui','Oui'),('P02','10,8','18,5' if ok else '35,8','Oui','Oui'),('P02','12,0','20,0' if ok else '40,1','Oui','Oui'),('P02','13,2','21,8' if ok else '44,8','Oui','Oui')]
        make_pdf('06_Essais_et_livraison/'+('03_RAPPORT_Essais_R02_CONFORME.pdf' if ok else '02_RAPPORT_Essais_R01_NONCONFORME.pdf'),'Rapport d’essais '+rev,'LUMI-ESS-'+rev+' / '+('17/11/2026' if ok else '12/11/2026'),[
         card('RÉSULTAT','<b>CONFORME dans la simulation</b> après DM01.' if ok else '<b>NON CONFORME dans la simulation</b> : dépassement du courant maximal.',GREEN if ok else RED),
         p('Toutes les mesures ci-dessous sont inventées pour l’exercice ; aucune mesure matérielle n’a été réalisée.'),gap(),
         table(['Prototype','Tension V','Courant mA','LED 1','LED 2'],vals,[90,90,100,97,98]),gap(),
         p('Critères : tension 10,8 à 13,2 V ; deux LED allumées ; courant total ≤ 25 mA. Inversion de polarité : résultat fictif conforme sur P01 et P02, sans procédure matérielle fournie.'),gap(),
         table(['Dossier','Référence'],[('CDC','LUMI-CDC-001 R1.0'),('Schéma / PCB','LUMI12_B.sch / LUMI12_B.brd' if ok else 'LUMI12_A.sch / LUMI12_A.brd'),('R1 / R2','1 kΩ / 1 kΩ' if ok else '470 Ω / 470 Ω'),('Logiciel','B / '+COMMIT_B if ok else 'A / '+COMMIT_A),('Visa','Sami Durand - visa fictif de recette')],[110,365]),gap(),
         card('INTERPRÉTATION','L’outil B détecte le seuil ; les essais de laboratoire simulés justifient au plus le TRL 4 retenu dans le scénario, pas une démonstration opérationnelle.' if ok else 'L’outil A peut afficher CONFORME malgré le courant excessif : défaut volontaire de sa règle de décision. Le contrôle indépendant impose la non-conformité et DM01.',ORANGE)])
    short_pdf('06_Essais_et_livraison/04_PV_Livraison.pdf','Procès-verbal de livraison fictive','LUMI-PV-001','18/11/2026',[
      ('Objet remis','Deux prototypes P01/P02, dossier de définition B, STEP, version logicielle B, CDC R1.0 et rapport ESS-R02.'),
      ('Acceptation','Acceptation fictive par Claire Martin pour une démonstration de laboratoire. Aucune vente, facturation ou livraison matérielle réelle.'),
      ('Délais','Date initiale 13/11/2026 ; réalisation simulée 18/11/2026 ; retard +5 jours calendaires / +3 jours ouvrés lundi-vendredi.'),
      ('Budget','Estimation courante 43,80 € HT pour les deux prototypes, enveloppe 60 € HT ; solde avant compléments 16,20 € HT. Ce n’est ni une facture ni un coût réel de main-d’œuvre.'),
      ('TRL et réserves','Entrée 2, actuel 4, cible 7. Le dossier ne démontre pas le TRL 7. Si Nextcloud indisponible, conserver la réserve « preuves locales, dépôts non testés ».')])
    short_pdf('06_Essais_et_livraison/05_SYNTHESE_Qualite.pdf','Synthèse documentaire fournisseur','LUMI-QUAL-001','18/11/2026',[
      ('Périmètre','Six références physiques actuelles : adaptateur, LED, résistances, diode, PCB et prestation de contrôle. Les lignes Logiciel et 3D ne doivent pas être assimilées à six nouvelles références physiques.'),
      ('ISO','ISO 9001 : fin 31/12/2028. ISO 14001 initial : fin 30/09/2026, expiré. Renouvellement : fin 19/10/2029, document reçu fictivement le 06/11.'),
      ('RoHS / REACH','Informations inconnues pour toutes les références dans le dossier fourni. Les datasheets et SDS fictives ne suffisent pas à déclarer une conformité.'),
      ('Règle de clôture','La tâche de renouvellement peut être terminée après mise à jour des métadonnées. Les alertes pour preuves absentes ou RoHS/REACH inconnus restent légitimes.'),
      ('Portée','Ce document entraîne à la lecture d’indicateurs. Il ne constitue aucun avis réglementaire ni validation d’un fournisseur réel.')])

def generate_correction():
    b=[card('À OUVRIR APRÈS EXÉCUTION','Ce corrigé évalue la saisie et la traçabilité dans OddWorks, pas l’aptitude industrielle du produit. Une pièce disponible sur votre PC n’est pas une pièce déposée dans Nextcloud.',PURPLE),
       p('Contrôle express - cinq minutes','H'),
       table(['Vérifier','Résultat attendu'],[
        ('Projet','Nom '+PROJECT+' ; destination Interne ; origine Technique ; état validé/vente / terminé à l’étape 9.'),('Périmètre','3 S.F. ; 8 S.T. principales ; quantité globale du CDC 2 ; budget 60 € HT.'),('Coût initial','20,00 € par prototype ; 40,00 € pour deux ; solde 20,00 €.'),('Coût révisé','21,90 € par prototype ; 43,80 € pour deux ; solde 16,20 € ; variation +3,80 €.'),('Planning','Jalons GO 03/11, livraison initiale 13/11, livraison révisée 18/11. Ne pas effacer la date initiale.'),('TRL','Entrée 2 ; actuel 4 ; cible 7. L’archivage ne doit pas faire passer automatiquement au niveau cible.'),('Historique','CDC refusé R0.1 ; CDC accepté R1.0 ; GO ; non-conformité ESS-R01 ; DM01 ; ESS-R02 ; livraison fictive.'),('Sources','A et B conservés ; commits fictifs distincts ; certificat 14001 expiré et renouvelé conservés.')],[115,360]),PageBreak(),
       p('Calculs de référence','H'),table(['S.T.','Initial HT / proto','Final HT / proto','Pourquoi'],[(x[0],x[5],('1,60' if x[0]=='S.T.1.2' else '7,50' if x[0]=='S.T.1.5' else x[5]),'LED : 2 × 0,80' if x[0]=='S.T.1.2' else 'PCB : 1 × 7,50' if x[0]=='S.T.1.5' else 'Inchangé') for x in ST],[60,125,125,165]),gap(),
       card('VÉRIFICATION ARITHMÉTIQUE',['Initial : 8 + 1,20 + 0,30 + 0,50 + 6 + 4 = <b>20,00 € HT</b>.','Final : 8 + 1,60 + 0,30 + 0,50 + 7,50 + 4 = <b>21,90 € HT</b>.','Deux prototypes : 21,90 × 2 = <b>43,80 € HT</b>. Surcoût : 3,80 € HT / 40 € = <b>9,5 %</b>. Solde : 60 - 43,80 = <b>16,20 € HT</b>.','Le 3D et le Logiciel sont suivis en délai seulement. Ne pas additionner une seconde fois les coûts des tâches achat aux estimations S.T.'],GREEN),
       p('Délais : maximum élémentaire initial = 5 j ; final = 8 j. Un maximum élémentaire n’est pas la durée globale. PCB : échéance 09/11, date métier 12/11, +3 j calendaires. LED : échéance 06/11, date métier 09/11, +3 j. Livraison : 13 au 18 novembre, +5 j calendaires / +3 jours ouvrés lundi-vendredi.'),PageBreak(),
       p('Contrôles des objets et du processus','H'),
       table(['Contrôle','Attendu / diagnostic rapide'],[
        ('Hiérarchie','S.T.1.2 a pour parent S.T.1.5. Un cycle ne doit pas pouvoir être choisi. Le type de S.T.1.2 reste Composant.'),('Tâches principales','8 tâches de S.T. ; toutes affectées au compte utilisé. Les dates proviennent de la fiche planning.'),('Réalisation détaillée','Une tâche PCB, une tâche 3D et une tâche Logiciel peuvent être créées par les lignes d’étape 4 : elles sont distinctes des tâches principales. Les terminer aussi avec une référence de résultat.'),('Achats','6 tâches achat ; seconde génération : 0 création / 6 mises à jour. Les ID doivent rester identiques après les mises à jour de prix/références.'),('Quantité de commande','Une ligne décrit un prototype. Deux prototypes nécessitent 2 adaptateurs, 4 LED, 4 résistances, 2 diodes, 2 PCB et 2 contrôles. Noter cette quantité réelle simulée dans les tâches ; ne pas supposer que le générateur la multiplie.'),('Actions correctives','2 tâches DM01 + 1 action documentaire 14001, si cette dernière a été créée. Leurs résultats citent les versions et documents précis.'),('Dépendance','Contrôle externe après Carte nue. Avant clôture de cette dernière, démarrage/clôture du contrôle bloqué ; après, autorisé.'),('Boucle qualité','Non conforme en étape 7 renvoie en étape 6. Une nouvelle validation de fabrication permet le retour aux essais.'),('Archivage','Conforme en étape 8 mène à 9. Le projet n’est pas dans les abandons NO GO.'),('Nombre indicatif','8 principales + 3 réalisations détaillées + 6 achats + 2 DM01 + 1 documentaire = 20 tâches si toutes ont été générées comme décrit. Vérifier surtout les sources et les doublons, pas seulement le total.')],[110,365]),PageBreak(),
       p('Documents, qualité, stock et CAO','H'),
       table(['Contrôle','Attendu'],[
        ('CDC','R0.1 = refus ; avis de correction conservé ; R1.0 = accepté avec seuil 25 mA et plage 10,8 à 13,2 V.'),('Électronique','A : R1/R2 470 Ω. B : 1 kΩ. Même netlist, même carte 40 x 25 mm et mêmes empreintes. Pas de nouveau PCB commandé pour DM01.'),('Mécanique','Boîtier externe 52 x 37 x 20 mm. Fond et couvercle STEP fournis ; carte 40 x 25 x 1,6 mm en référence dans l’assemblage.'),('Logiciel','Commit A fictif '+COMMIT_A+' ; commit B fictif '+COMMIT_B+'. Aucun des deux n’est un commit du dépôt OddWorks.'),('Premier essai','ESS-R01 : 45,4 / 44,8 mA à 13,2 V, non conforme au seuil 25 mA. Outil A insuffisant.'),('Contre-essai','ESS-R02 : maxima 21,4 / 21,8 mA ; outil B inclut le courant ; résultat fictif conforme.'),('Fournisseur','Atelier Horizon Fictif ; 9001 jusqu’au 31/12/2028 ; 14001 initial expiré au 30/09/2026 puis renouvelé jusqu’au 19/10/2029.'),('Conformité matière','RoHS et REACH restent inconnus dans le scénario. Une SDS ne les remplace pas. Les inconnus restent comptés au dénominateur.'),('Lot LED','REC-L12-LOT01, 10 unités : en quarantaine stock 0 ; après libération stock 10, une seule entrée. Ne pas créer une deuxième réception pour constater le changement.'),('Preuve locale','Sans Nextcloud, noms/références dans les notes = mode dégradé déclaré, pas succès d’un téléversement ni preuve externe valide.')],[110,365]),PageBreak(),
       p('Grille de notation rapide','H'),
       table(['Domaine','Points','Critère de réussite'],[
        ('Besoin et CDC','15','Cadrage, fonctions, cibles, documents refusé/accepté et notes cohérents.'),('Études et coûts','20','8 S.T., types, quantités, hiérarchie, coûts initial/final et risques corrects.'),('Tâches et délais','20','Affectations, dates, dépendance, retard conservé, pas de doublon achat.'),('Versions et reprises','15','Indices A/B, commits, échec ESS-R01, DM01, nouvelle réception ESS-R02.'),('Qualité et stock','15','Expiration/renouvellement tracés ; inconnus honnêtes ; lot quarantaine/libération cohérent.'),('Clôture et TRL','15','Livraison, écart de délai, archivage et TRL 4 justifié, cible non inventée.')],[120,45,310]),gap(),
       card('INTERPRÉTATION',['90-100 : exécution cohérente ; 75-89 : quelques écarts à reprendre ; 50-74 : traçabilité partielle ; moins de 50 : rejouer à partir du premier écart.','Le dépôt documentaire n’est pas noté en échec si Nextcloud est explicitement indisponible : marquer <b>non exécuté</b>, conserver la réserve et évaluer les références/indices locaux. Ne déclarer aucun succès de dépôt fictif.'],GREEN),
       p('Journal de correction à remplir','H'),table(['Écart constaté','Objet / lien OddWorks','Correction faite','Recontrôle'],[['________________','________________','________________','____'] for _ in range(5)],[130,130,145,70]),gap(),
       card('PIÈGES À ÉVITER','Doubler à la fois la quantité S.T. et celle du CDC ; confondre hiérarchie et référence S.T.x.x ; écraser R0.1/A ; effacer les échéances initiales ; assimiler livraison à TRL 7 ; déclarer ISO/REACH valides sans donnée ; envoyer une commande réelle.',ORANGE)]
    make_pdf('99_Correction/99_CORRIGE_LUMI12.pdf','Corrigé & évaluation','À consulter après votre saisie - contrôle express puis détail',b)

LAYERS=[(1,'Top',4),(16,'Bottom',1),(17,'Pads',2),(18,'Vias',2),(19,'Unrouted',6),(20,'Dimension',15),(21,'tPlace',7),(22,'bPlace',7),(25,'tNames',7),(26,'bNames',7),(27,'tValues',7),(28,'bValues',7),(29,'tStop',7),(30,'bStop',7),(44,'Drills',7),(45,'Holes',7),(46,'Milling',3),(51,'tDocu',7),(52,'bDocu',7),(91,'Nets',2),(92,'Busses',1),(93,'Pins',2),(94,'Symbols',4),(95,'Names',7),(96,'Values',7),(97,'Info',7),(98,'Guide',6)]
NETS={'+12_RAW':[('J1','1'),('D1','A')],'+12_PROT':[('D1','K'),('R1','1'),('R2','1')],'LED1_A':[('R1','2'),('LED1','A')],'LED2_A':[('R2','2'),('LED2','A')],'GND':[('J1','2'),('LED1','K'),('LED2','K')]}
PARTS={'J1':('CONN2','BORNIER5','12V'), 'D1':('DIODE','AXIAL','DIO-S-F'),'R1':('RES','AXIAL','1000'),'R2':('RES','AXIAL','1000'),'LED1':('LED','LED3','LED3-G-F2'),'LED2':('LED','LED3','LED3-G-F2')}
BOARD_POS={'J1':(4,10),'D1':(11,10),'R1':(20,17),'R2':(20,7),'LED1':(15,12.5),'LED2':(25,12.5)}
SCH_POS={'J1':(10,30),'D1':(35,30),'R1':(60,40),'R2':(60,20),'LED1':(85,40),'LED2':(85,20)}
def el(parent,tag,**attrs): return ET.SubElement(parent,tag,{k:str(v) for k,v in attrs.items()})
def wire(parent,x1,y1,x2,y2,layer,width=.254):return el(parent,'wire',x1=x1,y1=y1,x2=x2,y2=y2,width=width,layer=layer)
def rect_lines(parent,x1,y1,x2,y2,layer=21):
    for a,b,c,d in [(x1,y1,x2,y1),(x2,y1,x2,y2),(x2,y2,x1,y2),(x1,y2,x1,y1)]:wire(parent,a,b,c,d,layer,.15)
def eagle_base(kind):
    root=ET.Element('eagle',version='9.6.2');d=el(root,'drawing');s=el(d,'settings');el(s,'setting',alwaysvectorfont='yes');el(s,'setting',verticaltext='up')
    el(d,'grid',distance='1.27',unitdist='mm',unit='mm',style='lines',multiple='1',display='no',altdistance='.127',altunitdist='mm',altunit='mm')
    ll=el(d,'layers')
    for num,name,color in LAYERS:el(ll,'layer',number=num,name=name,color=color,fill=1,visible='yes',active='yes')
    obj=el(d,kind)
    return root,obj
def packages(parent):
    ps=el(parent,'packages')
    for name,pads in [('AXIAL',[('A',-3.81,0),('K',3.81,0)]),('LED3',[('A',-1.27,0),('K',1.27,0)]),('BORNIER5',[('1',0,0),('2',0,5.08)])]:
        pack=el(ps,'package',name=name)
        el(pack,'description').text='Empreinte synthetique de recette - FICTIF - non qualifiee pour fabrication.'
        for pname,x,y in pads:el(pack,'pad',name=pname,x=x,y=y,drill=1,diameter=1.8 if name!='BORNIER5' else 2.5,shape='octagon')
        if name=='LED3':el(pack,'circle',x=0,y=0,radius=1.5,width=.15,layer=21)
        elif name=='AXIAL':rect_lines(pack,-2,-1,2,1)
        else:rect_lines(pack,-2,-2,3,7.08)
        el(pack,'text',x=-2,y=2.3,size=.8,layer=25).text='>NAME'
        el(pack,'text',x=-2,y=-2.8,size=.65,layer=27).text='>VALUE'
    return ps
def library(parent,full=True):
    libs=el(parent,'libraries');lib=el(libs,'library',name='LUMI12_FICTIF');packages(lib)
    if not full:return
    syms=el(lib,'symbols')
    for name in ['CONN2','DIODE','RES','LED']:
        sym=el(syms,'symbol',name=name)
        if name=='CONN2':
            rect_lines(sym,0,-7.62,5.08,2.54,94)
            for pin,y in [('1',0),('2',-5.08)]:el(sym,'pin',name=pin,x=-5.08,y=y,visible='pad',length='short',direction='pas')
        else:
            names=['1','2'] if name=='RES' else ['A','K']
            el(sym,'pin',name=names[0],x=-5.08,y=0,visible='pad',length='short',direction='pas')
            el(sym,'pin',name=names[1],x=5.08,y=0,visible='pad',length='short',direction='pas',rot='R180')
            if name=='RES':rect_lines(sym,-2.54,-1,2.54,1,94)
            else:
                wire(sym,-2.54,0,-1.5,0,94);wire(sym,1.5,0,2.54,0,94)
                poly=el(sym,'polygon',width=.15,layer=94)
                for x,y in [(-1.5,-1.5),(1.5,0),(-1.5,1.5)]:el(poly,'vertex',x=x,y=y)
                wire(sym,1.5,-1.6,1.5,1.6,94)
                if name=='LED':
                    for x in [-.5,1.5]:wire(sym,x,2,x+1.7,3.7,94,.15)
        el(sym,'text',x=-3,y=5,size=1.27,layer=95).text='>NAME'
        el(sym,'text',x=-3,y=-4,size=1.0,layer=96).text='>VALUE'
    dss=el(lib,'devicesets')
    for name,package,pins in [('CONN2','BORNIER5',['1','2']),('DIODE','AXIAL',['A','K']),('RES','AXIAL',['1','2']),('LED','LED3',['A','K'])]:
        ds=el(dss,'deviceset',name=name,prefix=name[0],uservalue='yes');gates=el(ds,'gates');el(gates,'gate',name='G$1',symbol=name,x=0,y=0)
        devices=el(ds,'devices');dev=el(devices,'device',name='',package=package);con=el(dev,'connects')
        for pin,pad in zip(pins,['A','K'] if package=='AXIAL' else pins):el(con,'connect',gate='G$1',pin=pin,pad=pad)
        techs=el(dev,'technologies');el(techs,'technology',name='')
def write_xml(path,root):
    ET.indent(root,space='  ')
    path.parent.mkdir(exist_ok=True)
    path.write_text('<?xml version="1.0" encoding="utf-8"?>\n<!DOCTYPE eagle SYSTEM "eagle.dtd">\n'+ET.tostring(root,encoding='unicode')+'\n')
def generate_eagle():
    # Coordinates in mm; embedded library eliminates third-party dependencies.
    sch_routes={'+12_RAW':[(4.92,30,29.92,30)],'+12_PROT':[(40.08,30,45,30),(45,30,45,40),(45,40,54.92,40),(45,30,45,20),(45,20,54.92,20)],'LED1_A':[(65.08,40,79.92,40)],'LED2_A':[(65.08,20,79.92,20)],'GND':[(4.92,24.92,2,24.92),(2,24.92,2,10),(2,10,100,10),(100,10,100,20),(100,20,100,40),(90.08,20,100,20),(90.08,40,100,40)]}
    board_routes={'+12_RAW':[(4,10,7.19,10,1)],'+12_PROT':[(14.81,10,18,10,1),(18,10,18,17,1),(18,17,16.19,17,1),(18,10,18,7,1),(18,7,16.19,7,1)],'LED1_A':[(23.81,17,23.81,19,1),(23.81,19,12,19,1),(12,19,12,12.5,1),(12,12.5,13.73,12.5,1)],'LED2_A':[(23.81,7,24.5,7,1),(24.5,7,24.5,11,1),(24.5,11,23.73,11,1),(23.73,11,23.73,12.5,1)],'GND':[(4,15.08,4,21,16),(4,21,16.27,21,16),(16.27,21,34,21,16),(34,21,34,12.5,16),(34,12.5,26.27,12.5,16),(16.27,12.5,16.27,21,16)]}
    for rev,value in [('A','470'),('B','1000')]:
        folder=ROOT/'03_Electronique'/('Revision_'+rev);folder.mkdir(exist_ok=True)
        root,sc=eagle_base('schematic');sc.set('xreflabel','%F%N/%S.%C%R');sc.set('xrefpart','/%S.%C%R');library(sc)
        el(sc,'attributes');el(sc,'variantdefs');classes=el(sc,'classes');el(classes,'class',number=0,name='default',width=.254,drill=.8)
        parts=el(sc,'parts')
        for name,(ds,pack,val) in PARTS.items():el(parts,'part',name=name,library='LUMI12_FICTIF',deviceset=ds,device='',value=value if name in ['R1','R2'] else val)
        sheets=el(sc,'sheets');sheet=el(sheets,'sheet');plain=el(sheet,'plain')
        el(plain,'text',x=0,y=65,size=2,layer=97).text=f'LUMI-12 / REV {rev} / FICTIF - RECETTE / NE PAS FABRIQUER'
        el(plain,'text',x=0,y=59,size=1.3,layer=97).text=f'R1=R2={value} ohms. Revision A volontairement non conforme ; B apres DM01.'
        instances=el(sheet,'instances')
        for name,(x,y) in SCH_POS.items():el(instances,'instance',part=name,gate='G$1',x=x,y=y,smashed='no')
        el(sheet,'busses');nets=el(sheet,'nets')
        for net,refs in NETS.items():
            n=el(nets,'net',name=net,**{'class':0});seg=el(n,'segment')
            for part,pin in refs:el(seg,'pinref',part=part,gate='G$1',pin=pin)
            for coords in sch_routes[net]:wire(seg,*coords,91)
            if net=='+12_PROT':el(seg,'junction',x=45,y=30)
            if net=='GND':el(seg,'junction',x=100,y=20)
            x,y=sch_routes[net][0][:2];el(seg,'label',x=x,y=y,size=1.0,layer=95)
        el(sc,'errors');write_xml(folder/f'LUMI12_{rev}.sch',root)
        root,bd=eagle_base('board');plain=el(bd,'plain');rect_lines(plain,0,0,40,25,20)
        for x,y in [(3,3),(37,3),(37,22),(3,22)]:el(plain,'hole',x=x,y=y,drill=2.2)
        el(plain,'text',x=18,y=2,size=1,layer=21).text=f'L12 {rev} FICTIF'
        library(bd,False);el(bd,'attributes');el(bd,'variantdefs');classes=el(bd,'classes');el(classes,'class',number=0,name='default',width=.5,drill=.8)
        rules=el(bd,'designrules',name='RECETTE_NON_QUALIFIEE');el(rules,'description',language='en').text='Synthetic training board. Native ERC/DRC and manufacturing qualification not performed.'
        for name,val in [('layerSetup','(1*16)'),('mdWireWire','8mil'),('mdWirePad','8mil'),('mdPadPad','8mil'),('msWidth','10mil'),('msDrill','0.8mm')]:el(rules,'param',name=name,value=val)
        elements=el(bd,'elements')
        for name,(ds,pack,val) in PARTS.items():
            x,y=BOARD_POS[name];el(elements,'element',name=name,library='LUMI12_FICTIF',package=pack,value=value if name in ['R1','R2'] else val,x=x,y=y,smashed='no')
        signals=el(bd,'signals')
        for net,refs in NETS.items():
            sig=el(signals,'signal',name=net)
            for part,pin in refs:
                pad={'1':'A','2':'K'}.get(pin,pin) if part.startswith('R') else pin
                el(sig,'contactref',element=part,pad=pad)
            for x1,y1,x2,y2,layer in board_routes[net]:wire(sig,x1,y1,x2,y2,layer,.5)
        el(bd,'errors');write_xml(folder/f'LUMI12_{rev}.brd',root)
    return board_routes

def generate_step():
    import cadquery as cq
    folder=ROOT/'04_Mecanique'
    # XY extents match the documented outer size; z=0 is the bottom of the base.
    base=cq.Workplane('XY').box(52,37,18,centered=(False,False,False))
    cavity=cq.Workplane('XY').box(48,33,18,centered=(False,False,False)).translate((2,2,2))
    base=base.cut(cavity)
    for x,y in [(9,9),(43,9),(43,28),(9,28)]:
        boss=cq.Workplane('XY').circle(2.5).extrude(3).translate((x,y,2))
        bore=cq.Workplane('XY').circle(.9).extrude(5).translate((x,y,0))
        base=base.union(boss).cut(bore)
    slot=cq.Workplane('XY').box(4,10,6,centered=(False,True,False)).translate((-1,16,5));base=base.cut(slot)
    lid=cq.Workplane('XY').box(52,37,2,centered=(False,False,False)).translate((0,0,18))
    for x,y in [(21,18.5),(31,18.5)]:lid=lid.cut(cq.Workplane('XY').circle(1.7).extrude(4).translate((x,y,17)))
    pcb=cq.Workplane('XY').box(40,25,1.6,centered=(False,False,False)).translate((6,6,5))
    for x,y in [(9,9),(43,9),(43,28),(9,28)]:pcb=pcb.cut(cq.Workplane('XY').circle(1.1).extrude(3).translate((x,y,4.5)))
    for name,shape in [('LUMI12_Fond.step',base),('LUMI12_Couvercle.step',lid),('LUMI12_Carte_Reference.step',pcb)]:cq.exporters.export(shape,str(folder/name))
    assembly=cq.Compound.makeCompound([base.val(),lid.val(),pcb.val()]);cq.exporters.export(assembly,str(folder/'LUMI12_Assemblage.step'))
    checks=[]
    for path in sorted(folder.glob('*.step')):
        shape=cq.importers.importStep(str(path)).val();box=shape.BoundingBox()
        checks.append({'file':str(path.relative_to(ROOT)),'valid':shape.isValid(),'solids':len(shape.Solids()),'bounds_mm':[round(box.xlen,4),round(box.ylen,4),round(box.zlen,4)],'volume_mm3':round(shape.Volume(),3)})
        assert shape.isValid() and shape.Volume()>0
    assert checks[0]['bounds_mm']==[52.0,37.0,20.0]
    # Vector preview, generated from the actual CAD solids.
    cq.exporters.export(cq.Workplane(obj=assembly),str(folder/'LUMI12_Apercu.svg'),exportType='SVG',opt={'width':900,'height':550,'projectionDir':(1,-1,1),'showHidden':False})
    return checks

def generate_software():
    folder=ROOT/'05_Logiciel'
    code='''// EXERCICE FICTIF LUMI-12 : outil PC de recette, aucune commande materielle.
#include <iostream>
#include <string>
#include <cmath>
int main(int argc, char** argv) {
  if (argc != 5) { std::cerr << "Usage: outil tension_V courant_mA led1(0|1) led2(0|1)\\n"; return 2; }
  try {
    auto number=[](const char* s) { std::size_t n=0; double v=std::stod(s,&n); if(n!=std::string(s).size() || !std::isfinite(v)) throw 1; return v; };
    double volts=number(argv[1]), milliamps=number(argv[2]);
    auto bit=[](const char* s) { std::string v(s); if(v!="0" && v!="1") throw 1; return v=="1"; };
    bool led1=bit(argv[3]),led2=bit(argv[4]);
    bool ok = CONDITION;
    std::cout << "VERSION FICTIVE VERSION_TAG | " << (ok?"CONFORME":"NON_CONFORME") << "\\n";
    return ok?0:1;
  } catch (...) { std::cerr << "Entree invalide\\n"; return 2; }
}
'''
    (folder/'controle_A.cpp').write_text(code.replace('CONDITION','led1 && led2').replace('VERSION_TAG','A (regle incomplete volontaire)'))
    (folder/'controle_B.cpp').write_text(code.replace('CONDITION','led1 && led2 && volts >= 10.8 && volts <= 13.2 && milliamps >= 0.0 && milliamps <= 25.0').replace('VERSION_TAG','B'))
    (folder/'VERSIONS_FICTIVES.txt').write_text(f'''LUMI-12 / LOGICIEL DE RECETTE / IDENTIFIANTS GIT FICTIFS

A / {COMMIT_A}
Date metier : 05/11/2026
Fichier : controle_A.cpp
Defaut volontaire : decide seulement sur les deux LED ; ignore le courant.

B / {COMMIT_B}
Date metier : 16/11/2026
Fichier : controle_B.cpp
Correction DM01 : verifie LED, 10.8 <= V <= 13.2 et 0 <= I <= 25 mA.

Ces chaines de 40 caracteres ne sont pas des commits reels, ni des commits
du depot OddWorks. Ne pas fabriquer un lien GitHub vers elles.
Le programme est un outil PC de controle de mesures saisies, pas un firmware.
Compilation : g++ -std=c++17 -Wall -Wextra -pedantic controle_B.cpp -o controle_B
Exemple : ./controle_B 13.2 21.8 1 1
Codes retour : 0 conforme ; 1 non conforme ; 2 entree invalide.
Ne pas utiliser pour qualifier un equipement reel.
''')
    tmp=ROOT.parent.parent/'tmp'/'lumi12_build';tmp.mkdir(parents=True,exist_ok=True)
    results=[]
    cases=[('A',['13.2','45.4','1','1'],0),('B',['13.2','45.4','1','1'],1),('B',['13.2','21.8','1','1'],0),('B',['13.2','25','1','1'],0),('B',['13.2','25.1','1','1'],1),('B',['12','21','0','1'],1),('B',['nan','21','1','1'],2),('B',['12x','21','1','1'],2),('B',['12','-1','1','1'],1)]
    for rev in ['A','B']:subprocess.run(['g++','-std=c++17','-Wall','-Wextra','-pedantic',str(folder/f'controle_{rev}.cpp'),'-o',str(tmp/f'controle_{rev}')],check=True,capture_output=True)
    for rev,args,expected in cases:
        r=subprocess.run([str(tmp/f'controle_{rev}'),*args],capture_output=True,text=True)
        assert r.returncode==expected,(rev,args,r)
        results.append({'revision':rev,'arguments':args,'expected_exit':expected,'exit':r.returncode,'stdout':r.stdout.strip(),'stderr':r.stderr.strip()})
    return results

def diagram_schematic(rev):
    d=Drawing(475,180)
    d.add(String(8,165,f'LUMI-12 {rev} / schema simplifie / FICTIF',fontName='DVB',fontSize=10,fillColor=INK))
    d.add(Line(30,110,90,110,strokeColor=CYAN,strokeWidth=2));d.add(Rect(90,98,55,24,fillColor=PALE,strokeColor=INK));d.add(String(99,107,'D1',fontName='DVB',fontSize=9))
    d.add(Line(145,110,175,110,strokeColor=CYAN,strokeWidth=2))
    d.add(String(8,115,'+12 V',fontName='DV',fontSize=9));d.add(String(8,36,'GND',fontName='DV',fontSize=9))
    d.add(Line(30,30,440,30,strokeColor=INK,strokeWidth=2))
    for y,label in [(125,'1'),(75,'2')]:
        d.add(Line(175,110,175,y,strokeColor=CYAN,strokeWidth=2));d.add(Line(175,y,210,y,strokeColor=CYAN,strokeWidth=2));d.add(Rect(210,y-8,75,16,fillColor=PALE,strokeColor=INK));d.add(String(216,y-3,'R'+label+' '+('470R' if rev=='A' else '1k'),fontName='DV',fontSize=9));d.add(Line(285,y,330,y,strokeColor=CYAN,strokeWidth=2));d.add(Circle(345,y,14,fillColor=colors.HexColor('#D9F0DF'),strokeColor=GREEN));d.add(String(335,y-3,'LED'+label,fontName='DV',fontSize=7));d.add(Line(359,y,410,y,strokeColor=CYAN,strokeWidth=2));d.add(Line(410,y,410,30,strokeColor=INK,strokeWidth=2))
    d.add(String(180,4,'Les fichiers .sch contiennent la netlist detaillee.',fontName='DV',fontSize=8,fillColor=INK))
    return d
def diagram_board():
    d=Drawing(475,330);scale=9;ox=45;oy=48
    d.add(Rect(ox,oy,40*scale,25*scale,fillColor=colors.HexColor('#E3F1E9'),strokeColor=GREEN,strokeWidth=2))
    for x,y in [(3,3),(37,3),(37,22),(3,22)]:d.add(Circle(ox+x*scale,oy+y*scale,1.1*scale,fillColor=colors.white,strokeColor=INK))
    for part,(x,y) in BOARD_POS.items():
        d.add(Rect(ox+x*scale-13,oy+y*scale-6,26,12,fillColor=colors.white,strokeColor=CYAN));d.add(String(ox+x*scale-11,oy+y*scale-3,part,fontName='DVB',fontSize=7))
    d.add(String(ox,oy+25*scale+22,'PCB 40 x 25 mm / 4 percages 2,2 mm',fontName='DVB',fontSize=10,fillColor=INK))
    d.add(String(ox,oy-25,'FICTIF - implantation de recette, pas de dossier de fabrication',fontName='DV',fontSize=8,fillColor=RED))
    return d
def generate_notice():
    make_pdf('03_Electronique/00_NOTICE_CAO_et_Assemblage.pdf','Dossier technique CAO','Formats, indices et limites - LUMI-12 fictif',[
      card('CONTENU NATIF',['Schéma et PCB : <b>EAGLE XML 9.6.2</b>, extensions .sch/.brd, bibliothèque synthétique embarquée. Ce ne sont pas des fichiers KiCad simplement renommés.','3D : fichiers STEP générés par OpenCascade via CadQuery ; fond, couvercle, carte de référence et assemblage.','Les versions A et B diffèrent par R1/R2 ; la substitution LED est déjà intégrée aux deux dossiers. La topologie électrique et l’implantation restent identiques.'],CYAN),
      diagram_schematic('A'),gap(),diagram_schematic('B'),
      card('PORTÉE','Contrôles réalisés : structure XML, correspondance des contacts schéma/PCB, continuité des routes et import STEP avec contrôle des volumes. Aucun ERC/DRC dans un éditeur EAGLE/Fusion, aucun Gerber qualifié et aucun essai matériel. <b>Ne pas lancer une fabrication à partir de ce dossier de recette.</b>',ORANGE),PageBreak(),
      p('Dimensions et implantation','H'),diagram_board(),
      table(['Objet','Dimensions / cohérence'],[('PCB','40 x 25 x 1,6 mm ; trous Ø 2,2 aux coordonnées (3,3), (37,3), (37,22), (3,22).'),('Boîtier','52 x 37 x 20 mm au total ; fond jusqu’à z=18 ; couvercle z=18 à 20 ; paroi/fond 2 mm.'),('Carte dans le boîtier','Décalage XY (6,6), bas de carte z=5 ; trous alignés aux bossages.'),('Fenêtres LED','Deux trous Ø 3,4 dans le couvercle, centres (21 ; 18,5) et (31 ; 18,5) mm.'),('Finition','Assemblage conceptuel ; jeux, clips, vissage du couvercle, dispersion d’impression et accès aux fils à qualifier dans une conception réelle.')],[110,365]),PageBreak(),
      p('Ouverture des fichiers et version logicielle','H'),
      *bullets(['Importer les .sch et .brd comme fichiers EAGLE XML dans un logiciel qui prend ce format en charge. Conserver chaque paire dans son dossier Revision_A ou Revision_B.','Le générateur valide la structure et la netlist, pas le résultat d’une importation dans tous les logiciels CAO. Vérifier l’import avant de modifier les fichiers.','Ouvrir les .step dans un visualiseur STEP/CAO. L’assemblage est un composé de trois solides. La carte incluse est une référence mécanique nue, sans modèle 3D détaillé des composants.','L’outil C++ se compile sur PC. A accepte volontairement un courant excessif si les LED sont allumées ; B rejette ce cas. Les identifiants Git sont fictifs et ne doivent pas être résolus sur le dépôt OddWorks.']),gap(),
      p('Références de format','H'),p('Documentation du format EAGLE : https://dev-docs.kicad.org/en/import-formats/eagle/ ; objets EAGLE : documentation Autodesk Fusion Electronics. Ces références expliquent le format, pas la qualification de ce projet fictif.')])

def validate_eagle():
    results=[]
    for rev in ['A','B']:
        folder=ROOT/'03_Electronique'/('Revision_'+rev)
        sch=ET.parse(folder/f'LUMI12_{rev}.sch');brd=ET.parse(folder/f'LUMI12_{rev}.brd')
        sn={n.get('name'):set((r.get('part'),r.get('pin')) for r in n.findall('.//pinref')) for n in sch.findall('.//sheet/nets/net')}
        bn={n.get('name'):set((r.get('element'),r.get('pad')) for r in n.findall('contactref')) for n in brd.findall('.//board/signals/signal')}
        mapped={n:set((part,{'1':'A','2':'K'}.get(pin,pin) if part.startswith('R') else pin) for part,pin in refs) for n,refs in sn.items()}
        assert sn=={n:set(refs) for n,refs in NETS.items()};assert mapped==bn
        parts={p.get('name'):p.get('value') for p in sch.findall('.//schematic/parts/part')}
        elements={p.get('name'):p.get('value') for p in brd.findall('.//board/elements/element')}
        assert parts==elements and parts['R1']==('470' if rev=='A' else '1000')
        # Ensure every signal's contacts are connected by the supplied wire graph.
        padmap={p.get('name'):{q.get('name'):(float(q.get('x')),float(q.get('y'))) for q in p.findall('pad')} for p in brd.findall('.//board/libraries/library/packages/package')}
        elems={p.get('name'):p for p in brd.findall('.//board/elements/element')}
        for signal in brd.findall('.//board/signals/signal'):
            graph={}
            key=lambda x,y:(round(x,4),round(y,4))
            for w in signal.findall('wire'):
                a=key(float(w.get('x1')),float(w.get('y1')));b=key(float(w.get('x2')),float(w.get('y2')))
                graph.setdefault(a,set()).add(b);graph.setdefault(b,set()).add(a)
            contacts=[]
            for r in signal.findall('contactref'):
                e=elems[r.get('element')];dx,dy=padmap[e.get('package')][r.get('pad')]
                contacts.append(key(float(e.get('x'))+dx,float(e.get('y'))+dy))
            reached=set();todo=[contacts[0]]
            while todo:
                node=todo.pop()
                if node in reached:continue
                reached.add(node);todo+=list(graph.get(node,set())-reached)
            assert set(contacts)<=reached,(rev,signal.get('name'),contacts,reached)
        results.append({'revision':rev,'parts':len(parts),'nets':len(sn),'netlist_matches_board':True,'all_signal_contacts_connected':True,'resistor_ohms':int(parts['R1']),'native_erc_drc':False})
    return results

def write_support(step_checks,software_checks,eagle_checks):
    with (ROOT/'00_Parcours/FICHE_SAISIE_ST.csv').open('w',newline='') as f:
        w=csv.writer(f,delimiter=';');w.writerow(['ID','Description','Type','Quantite_par_proto','CU_HT_initial','Total_HT_initial','Delai_j_initial','Risque_initial']);w.writerows(ST)
    with (ROOT/'00_Parcours/PLANNING_TACHES.csv').open('w',newline='') as f:
        w=csv.writer(f,delimiter=';');w.writerow(['Repere','Titre_principal','Debut_prevu','Echeance_initiale','Dependance','Date_metier_realisation']);w.writerows(TASKS)
    checks={'fiction':True,'scenario_dates':'2026-11-02 to 2026-11-18','eagle':eagle_checks,'step':step_checks,'software':software_checks,'pdf_count':len(PDFS),'pdf_pages':{str(x.relative_to(ROOT)):len(PdfReader(x).pages) for x in PDFS},'native_editor_import_tested':False,'manufacturing_qualified':False}
    (ROOT/'99_Correction/VALIDATION_TECHNIQUE.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2)+'\n')
    readme='''# Projet exécution TEST - LUMI-12

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
'''
    (ROOT/'README.md').write_text(readme)
    manifest=[]
    for f in sorted(ROOT.rglob('*')):
        if f.is_file() and f.name not in ['MANIFEST_SHA256.txt','LUMI12_Dossier_complet.zip'] and '__pycache__' not in f.parts:
            manifest.append(hashlib.sha256(f.read_bytes()).hexdigest()+'  '+str(f.relative_to(ROOT)))
    (ROOT/'MANIFEST_SHA256.txt').write_text('\n'.join(manifest)+'\n')
    archive=ROOT/'LUMI12_Dossier_complet.zip'
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
        for f in sorted(ROOT.rglob('*')):
            if f.is_file() and f!=archive and '__pycache__' not in f.parts:z.write(f,Path('LUMI12_Dossier')/f.relative_to(ROOT))
    assert len(PDFS)==25,len(PDFS)
    print(json.dumps({'pdfs':len(PDFS),'pages':sum(len(PdfReader(x).pages) for x in PDFS),'files':len(manifest)+2,'archive_bytes':archive.stat().st_size,'checks':checks},ensure_ascii=False,indent=2))

if __name__=='__main__':
    generate_guide();generate_cdc();generate_meetings();generate_supplier_docs();generate_tests();generate_correction()
    generate_eagle();step_checks=generate_step();software_checks=generate_software();generate_notice();eagle_checks=validate_eagle()
    write_support(step_checks,software_checks,eagle_checks)
