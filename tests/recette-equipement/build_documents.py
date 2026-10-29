#!/usr/bin/env python3
import argparse,json,hashlib
from pathlib import Path
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate,Paragraph,Spacer,Table,TableStyle,PageBreak
from reportlab.lib.styles import getSampleStyleSheet,ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.pagesizes import A4
from xml.sax.saxutils import escape
from scenario import *
parser=argparse.ArgumentParser();parser.add_argument('output');args=parser.parse_args();out=Path(args.output);out.mkdir(parents=True,exist_ok=True)
font='/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';pdfmetrics.registerFont(TTFont('DejaVu',font));pdfmetrics.registerFont(TTFont('DejaVuBold',font.replace('.ttf','-Bold.ttf')))
styles=getSampleStyleSheet()
for s in styles.byName.values():s.fontName='DejaVu'
styles.add(ParagraphStyle(name='TitleAtlas',fontName='DejaVuBold',fontSize=20,leading=25,textColor=colors.HexColor('#102d43'),spaceAfter=18))
styles.add(ParagraphStyle(name='BodyAtlas',fontName='DejaVu',fontSize=9,leading=14,spaceAfter=10))
styles.add(ParagraphStyle(name='SmallAtlas',fontName='DejaVu',fontSize=7.4,leading=10))
P=lambda text:Paragraph(escape(str(text)),styles['BodyAtlas'])
manifest=[]
def pdf(name,title,content,subtitle='Dossier de recette ATLAS-12'):
 path=out/(name+'.pdf')
 def footer(c,d):
  c.setFillColor(colors.HexColor('#9f3a17'));c.setFont('DejaVuBold',7);c.drawString(42,28,'FICTIF - RECETTE DEV - SANS VALEUR DE CERTIFICATION');c.setFillColor(colors.HexColor('#51697a'));c.setFont('DejaVu',7);c.drawRightString(553,28,f'{CODE} | {d.page}')
 story=[Paragraph(title,styles['TitleAtlas']),P(subtitle),Table([[Paragraph(WARNING,styles['SmallAtlas'])]],colWidths=[510],style=TableStyle([('BACKGROUND',(0,0),(-1,-1),colors.HexColor('#fff2d4')),('BOX',(0,0),(-1,-1),.5,colors.HexColor('#efc06a')),('PADDING',(0,0),(-1,-1),10)])),Spacer(1,18)]+content
 SimpleDocTemplate(str(path),pagesize=A4,rightMargin=42,leftMargin=42,topMargin=40,bottomMargin=48,title=title,author='OddWorks - recette fictive').build(story,onFirstPage=footer,onLaterPages=footer)
 manifest.append({'file':path.name,'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'size':path.stat().st_size,'title':title})
def table(rows,widths):
 t=Table([[Paragraph(escape(str(c)),styles['SmallAtlas']) for c in row] for row in rows],colWidths=widths,repeatRows=1,hAlign='LEFT');t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),colors.HexColor('#e4eef3')),('VALIGN',(0,0),(-1,-1),'TOP'),('GRID',(0,0),(-1,-1),.3,colors.HexColor('#b4c9d5')),('PADDING',(0,0),(-1,-1),7)]));return t
for code,name,maker,sup,cost,cat,spec in PARTS:
 pdf('composant_'+code,name,[P('Référence fabricant fictive : AT12-'+code),P('Fabricant : '+MAKERS[maker]),P('Fournisseur de simulation : '+SUPPLIERS[sup]),P('Catégorie : '+cat),P('Spécification de test : '+spec),P(f'Prix unitaire fictif : {cost:.2f} EUR HT. Unité : pièce.'),P('Contrôle de réception : référence lisible, quantité et intégrité conformes au scénario. Conserver le lot et la preuve documentaire dans OddWorks.'),P('Ce document est une fiche technique inventée pour la recette. Il ne remplace pas une fiche fabricant authentique. Aucune conformité réelle n’est attestée.')])
for code,name,maker,bom in ASSEMBLIES:
 pdf('composant_'+code,name,[P('Référence fictive : AT12-'+code),P('Fabricant / intégrateur : '+MAKERS[maker]),P('Ensemble assemblé traçable, nomenclature indice A. Quantités ci-dessous pour un ensemble.'),table([['Composant','Quantité']]+[['AT12-'+part,qty] for part,qty in bom],[400,110]),Spacer(1,12),P('Contrôle : bonne référence de chaque constituant, lot libéré, position et montage corrects. L’assemblage physique est simulé. Les consommations et séries doivent être réellement enregistrées dans le logiciel pendant la recette.')])
for code,title,phase,text in DOCS:
 content=[P(text)]
 if code in ('10_plan_essais','11_resultats','14_tracabilite'):
  content += [table([['Fonction','Critère / réponses techniques']]+[[f'S.F.{i} - {name}',criterion+'\n'+'; '.join(f'S.T.{i}.{j} : {s[0]}' for j,s in enumerate(sts,1))] for i,(name,criterion,sts) in enumerate(FUNCTIONS,1)],[160,350])]
 pdf(code,title,content)
pdf('00_cahier_des_charges','Cahier des charges - ATLAS-12',[P('Objectif : concevoir et simuler deux bancs d’acquisition modulaires regroupant intégration, électronique, mécanique et logiciel.'),P('Quantité cible : 2 équipements. Budget plafond : 8 000 EUR HT. Planning fictif : octobre à décembre 2026. Les dates techniques des journaux ne sont jamais modifiées.'),table([['Référence','Fonction et critère mesurable']]+[[f'S.F.{i}',name+' : '+criterion] for i,(name,criterion,sts) in enumerate(FUNCTIONS,1)],[65,445]),PageBreak(),P('Solutions techniques initiales - 2 ou 3 par fonction'),table([['Référence','Désignation','Type','Article']]+[[f'S.T.{i}.{j}',s[0],s[1],('AT12-'+s[2]) if s[2] else 'Livrable documentaire / logiciel'] for i,(_,_,sts) in enumerate(FUNCTIONS,1) for j,s in enumerate(sts,1)],[65,230,80,135])])
data={'code':CODE,'project':PROJECT,'warning':WARNING,'makers':MAKERS,'suppliers':SUPPLIERS,'parts':[{'code':p[0],'name':p[1],'maker':p[2],'supplier':p[3],'cost':p[4],'category':p[5],'spec':p[6]} for p in PARTS],'assemblies':[{'code':a[0],'name':a[1],'maker':a[2],'bom':a[3]} for a in ASSEMBLIES],'functions':[{'id':f'S.F.{i}','description':name,'indicateur':criterion,'solutions':[{'id':f'S.T.{i}.{j}','description':s[0],'type':s[1],'part':s[2],'quantite':s[3],'cout_unitaire':s[4],'delai_jours':s[5]} for j,s in enumerate(sts,1)]} for i,(name,criterion,sts) in enumerate(FUNCTIONS,1)],'documents':[{'file':c+'.pdf','title':t,'phase':p} for c,t,p,_ in DOCS],'pdfs':manifest}
(out/'scenario.json').write_text(json.dumps(data,ensure_ascii=False,indent=2))
(out/'SHA256SUMS').write_text(''.join(f"{m['sha256']}  {m['file']}\n" for m in manifest))
print(json.dumps({'pdfs':len(manifest),'functions':len(FUNCTIONS),'technical_specs':sum(len(s[2]) for s in FUNCTIONS),'parts':len(PARTS),'assemblies':len(ASSEMBLIES)}))
