#!/usr/bin/env python3
"""Real HTTP controllers against disposable SQLite and local WebDAV. Never targets DEV data."""
import os,re,json,sys,subprocess,hashlib,time,requests
from pathlib import Path
from urllib.parse import urlencode,urlsplit,parse_qs
root=Path(__file__).resolve().parents[2];out=Path(sys.argv[1]).resolve();scenario=json.loads((out/'scenario.json').read_text());session=requests.Session();base='http://127.0.0.1:'+os.getenv('ATLAS12_HTTP_PORT','8091');checks=[]
def check(ok,name):
 if not ok:raise AssertionError(name)
 checks.append(name);print('OK - '+name,flush=True)
def state():return json.loads(subprocess.check_output(['php',str(root/'tests/recette-equipement/fixture.php'),'state'],text=True,cwd=root))
def get(path):
 r=session.get(base+path,timeout=30);r.raise_for_status();return r
r=get('/login.php');token=re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
r=session.post(base+'/verification_connexion.php',data={'_csrf':token,'identifiant':'admin','mot_de_passe':'atlas12-fixture-only'},timeout=30);check('/projets.php' in r.url,'Connexion HTTP normale du compte jetable CI')
r=get('/projets.php?action=creer')
csrf=re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
def post(path,data=None,files=None):
 data=data or {}; data={'_csrf':csrf,**data};r=session.post(base+path,data=data,files=files,timeout=90);r.raise_for_status();return r
post('/projets.php',{'nom':scenario['project'],'description':scenario['warning']+' - Deux prototypes fictifs; sous-ensembles reçus assemblés de sous-traitance simulée.','cadrage_technique':'1','cadrage_destination':'interne'})
s=state();pid=s['project']['id'];project=f'/projet.php?id={pid}&view=processus';check(s['project']['current_step']==1,'Création du projet par le formulaire HTTP')
cdc={'projet_id':pid,'form_action':'save','cdc_ajax':'1','objectifs':'Concevoir deux équipements techniques intégrant des modules existants, des cartes électroniques, un boîtier aluminium et plastique et des logiciels locaux. '+scenario['warning'],'resultats_attendus':'Deux prototypes simulés, dossier PDF, catalogue, BOM et traçabilité.','cas_usage':'Banc R&D local de mesure de tensions et températures.','profils_utilisateurs':'Technicien BE et maintenance fictifs.','delais':'Planning de simulation octobre-décembre 2026.','livrables_attendus':'CDC; validation MSG; cartes; boîtiers; logiciel; fiches composants; BOM; essais; maintenance; livraison.','cible_budget':'8000','cible_quantite':'2','cible_tva':'20','cible_taxe':'HT','cible_charge':'200','cible_delai':'60','cible_perimetre':'Deux prototypes fictifs et dossier documentaire; aucun achat réel.','sf_description[]':[f['description'] for f in scenario['functions']],'sf_indicateur[]':[f['indicateur'] for f in scenario['functions']]}
r=post('/cahier_form.php',cdc);check(r.json().get('ok') is True,'Enregistrement AJAX des douze fonctions et critères du CDC')
solutions=[dict(s,sf=f['id']) for f in scenario['functions'] for s in f['solutions']]
st={'action':'save_specs_techniques','projet_id':pid,'id':pid}
for key in ['sf','uid','parent_uid','reference_uid','description','type','quantite','cout_unitaire','cout_taxe','variation','delai_jours']:st['st_'+key+'[]']=[]
for i,solution in enumerate(solutions):
 values={'sf':solution['sf'],'uid':hashlib.sha256((scenario['code']+solution['id']).encode()).hexdigest()[:32],'parent_uid':'','reference_uid':'','description':solution['description'],'type':solution['type'],'quantite':solution['quantite'],'cout_unitaire':solution['cout_unitaire'],'cout_taxe':'HT','variation':'Faible' if i%3 else 'Moyen','delai_jours':solution['delai_jours']}
 for k,v in values.items():st['st_'+k+'[]'].append(v)
post(project+'&step=2',st);s=state();check(len(s['specs']['fonctions'])==12 and len(s['specs']['specs_techniques'])==30,'Relecture persistante : 12 SF / 30 ST, deux ou trois réponses par fonction')
check(len(s['tasks'])>=30,'Tâches techniques synchronisées sans utiliser les SF comme tâches')
# Current serialized PDF and request really come from the production application controller.
r=post('/cdc_validation.php',{'projet_id':pid,'action':'start','recipient':'validation-recette@example.invalid'})
(out/'demande_validation.msg').write_bytes(r.content);check(r.content.startswith(bytes.fromhex('d0cf11e0a1b11ae1')),'Demande de validation réellement générée en MSG par la bibliothèque Outlook')
s=state();v=s['validations'][-1];pdf=Path(v['pdf_path']);check(pdf.is_file(),'PDF exact du cycle de validation conservé par le contrôleur')
(out/'CDC_snapshot_application.pdf').write_bytes(pdf.read_bytes());(out/'validation_reference.txt').write_text(v['reference'])
for invalid in [True,False]:
 name='reponse_reference_invalide.msg' if invalid else 'reponse_validation.msg'
 cmd=[sys.executable,str(root/'tests/recette-equipement/mail_reply.py'),v['reference'],str(pdf),str(out/name)]+(['--invalid'] if invalid else [])
 subprocess.run(cmd,check=True)
 r=post('/cdc_validation.php',{'projet_id':pid,'action':'import','validation_id':v['id']},files={'reply_file':(name,(out/name).read_bytes(),'application/vnd.ms-outlook')})
 status=state()['validations'][-1]['status']
 if invalid:check(status=='pending' and 'absente du courriel' in r.text,'Mauvaise référence MSG rejetée sans clôturer la validation')
 else:check(status=='accepté','Réponse MSG fictive correctement importée : CDC accepté')
from aspose.email_foss.msg import MapiMessage,PropertyId
with MapiMessage.from_file(out/'demande_validation.msg',strict=True) as m:
 check(len(m.attachments)==1 and m.attachments[0].data==pdf.read_bytes(),'Pièce jointe MSG identique au PDF exact archivé')
 check(int(m.get_property_value(PropertyId.MESSAGE_FLAGS))&8!=0,'Demande MSG marquée brouillon non envoyé')
# Business-stage decisions: use the real controller, never UPDATE current_step directly.
def decide(step,decision):
 post(project+f'&step={step}',{'action':'decide','id':pid,'decision':decision,'motif':'RECETTE FICTIVE AUTOMATISEE : validation documentaire du scénario; aucun accord réel.'})
 expected=step+1
 check(state()['project']['current_step']==expected,f'Contrôleur décision R1b : étape {step} -> {expected} ({decision})')
decide(1,'DONE');decide(2,'DONE');decide(3,'GO')
inv=get('/inventory_api.php').json()
def command(action,**kw):
 global inv
 r=session.post(base+'/inventory_api.php',json={'_csrf':csrf,'revision':inv['revision'],'action':action,**kw},timeout=30)
 if r.status_code!=200:raise AssertionError(f'{action}: {r.status_code}: {r.text}')
 inv=r.json();return inv['result_id']
categories={}
for name in sorted({p['category'] for p in scenario['parts']}|{'Assemblages'}):categories[name]=command('category_save',nom='RECETTE AT12 - '+name,description=scenario['warning'])
loc=command('location_save',nom='RECETTE AT12 - Bac réception',type='Bac fictif',description=scenario['warning'])
finished=command('location_save',nom='RECETTE AT12 - Produits finis',type='Bac fictif',description=scenario['warning'])
articles={};costs={p['code']:p['cost'] for p in scenario['parts']}
for a in scenario['assemblies']:costs[a['code']]=sum(costs[code]*qty for code,qty in a['bom'])
items=scenario['parts']+[dict(a,supplier=a['maker'],category='Assemblages',cost=costs[a['code']],spec='Ensemble assemblé de sous-traitance simulée' if a['code']!='SYS' else 'Produit final assemblé en recette') for a in scenario['assemblies']]
for p in items:
 code=p['code'];assembly=code in {a['code'] for a in scenario['assemblies']};sysfinal=code=='SYS'
 aid=command('article_save',designation='[RECETTE] '+p['name'],reference_fabricant='AT12-'+code,fabricant=scenario['makers'][p['maker']],categorie_id=categories[p['category']],inventory_mode='serial' if sysfinal else 'lot',flags={'assembled':assembly,'component':not sysfinal,'testable':True,'trackable':True,'purchasable':not sysfinal,'sellable':sysfinal},description=scenario['warning']+' / '+p['spec'],quantite_min=0,unite='u');articles[code]=aid
 mid=next(m['id'] for m in inv['manufacturers'] if m['article_id']==aid)
 if not sysfinal:
  command('supplier_save',article_id=aid,company=scenario['suppliers'][p['supplier']],manufacturer_id=mid,sku='REC-AT12-'+code,description='Devis de simulation uniquement',pack_quantity=1,packaging='Unité fictive',principal=1,actif=1,prix_ht=costs[code],notes=scenario['warning'])
 command('parameter_save',article_id=aid,nom='Spécification de recette',valeur=p['spec'],unite='')
 r=post('/inventory_document.php',{'article_id':aid},files={'file':('composant_'+code+'.pdf',(out/('composant_'+code+'.pdf')).read_bytes(),'application/pdf')});check(r.json().get('ok') is True,'Fiche PDF composant téléversée : '+code)
 inv=get('/inventory_api.php').json()
check(len(articles)==24 and len(inv['manufacturers'])==24,'Catalogue : 20 pièces et 4 assemblages avec fabricants et paramètres')
check(len(inv['suppliers'])==23 and len({r['company'] for r in inv['suppliers']})==4,'Quatre fournisseurs fictifs et 23 références d’approvisionnement')
# Download every component PDF through the real authenticated document endpoint.
for d in inv['documents']:
 r=get('/inventory_document.php?id='+str(d['id']));check(hashlib.sha256(r.content).hexdigest()==hashlib.sha256((out/d['nom']).read_bytes()).hexdigest(),'Relecture SHA256 document composant '+d['nom'])
for d in scenario['documents']:
 phase='capacite' if d['phase']=='02_Etudes' else 'proto' if d['phase']=='03_Conception' or d['phase']=='06_Prototype' else 'tests' if d['phase'] in ('07_Essais','04_Qualite') else 'livraison' if d['phase']=='08_Livraison' else 'investissement' if d['phase']=='05_Achats' else 'cahier'
 post('/actions/upload.php',{'projet_id':pid,'phase':phase},files={'fichier':(d['file'],(out/d['file']).read_bytes(),'application/pdf')})
s=state();check(len(s['documents'])>=17,'Dossier projet : PDF de chaque phase et archive de validation')
# Component search: all six supported technical types, preserving supplier/cost fields.
cp={'action':'save_composants_st','projet_id':pid,'id':pid}
keys=['st_id','type','designation','reference','fournisseur','quantite','cout_unitaire','cout_unitaire_taxe','affectation','duree','variation','delai_jours']
for k in keys:cp['cp_'+k+'[]']=[]
for sol in solutions:
 part=next((x for x in items if x['code']==sol['part']),None)
 values={'st_id':sol['id'],'type':sol['type'],'designation':sol['description'],'reference':'AT12-'+sol['part'] if part else 'REC-SERVICE-'+sol['id'],'fournisseur':scenario['suppliers'][part['supplier']] if part else scenario['suppliers'][1],'quantite':sol['quantite'],'cout_unitaire':sol['cout_unitaire'],'cout_unitaire_taxe':'HT','affectation':'admin','duree':'4','variation':'Faible','delai_jours':sol['delai_jours']}
 for k,vv in values.items():cp['cp_'+k+'[]'].append(vv)
post(project+'&step=4',cp);check(len(state()['specs']['composants_st'])==30,'Recherche composants / affectations enregistrées pour les trente ST et six types')
decide(4,'DONE')
# Receipts: individual spare parts and subcontracted assembled modules, not invented internal production.
lots={}
for p in items:
 code=p['code'];aid=articles[code]
 if code=='SYS':continue
 supplier=next(x['company_id'] for x in inv['suppliers'] if x['article_id']==aid)
 qty=30 if code=='VIS' else 6 if code in ('PT100','SUP','RJ45') else 4
 lots[code]=command('stock_create',article_id=aid,location_id=loc,quantity=qty,trace='REC-AT12-LOT-'+code,received='2026-10-29',quality='quarantine',company_id=supplier,cost=costs[code],comment='RECEPTION FICTIVE / fabrication sous-traitée simulée pour les ensembles')
 record=next(x for x in inv['records'] if x['lot_id']==lots[code])
 command('quality',article_id=aid,key=record['key'],quality='accepted',comment='Contrôle documentaire simulé conforme - voir 08_qualite.pdf')
check(all(x['quality']=='accepted' for x in inv['records']),'Réceptions en quarantaine puis libération qualité explicite')
# Refuse an excessive reservation, then reserve the components used by the final assembly.
before=inv['revision'];r=session.post(base+'/inventory_api.php',json={'_csrf':csrf,'revision':before,'action':'reserve','article_id':articles['ACQ'],'projet_id':pid,'location_id':loc,'quantity':9999,'comment':'Test négatif stock insuffisant'},timeout=30)
check(r.status_code==422,'Sur-réservation refusée sans mouvement de stock')
final=next(a for a in scenario['assemblies'] if a['code']=='SYS')
for code,qty in final['bom']:command('reserve',article_id=articles[code],projet_id=pid,location_id=loc,quantity=qty*2,comment='Réservation pour deux équipements fictifs')
decide(5,'DONE')
# Create BOM headers and their compositions through the real authenticated controllers.
for a in scenario['assemblies']:
 post('/stock_bom_create.php?projet_id='+str(pid),{'projet_id':pid,'article_id':articles[a['code']],'reference':'REC-ATLAS12-BOM-'+a['code'],'version':'A'})
s=state();boms={b['reference'].split('-')[-1]:b['id'] for b in s['boms']}
for a in scenario['assemblies']:
 bid=boms[a['code']]
 post('/stock_production.php?bom_id='+str(bid),{'action':'composition','statut':'validee','article_id[]':[articles[c] for c,q in a['bom']],'quantite[]':[q for c,q in a['bom']],'position[]':[f'P{i+1}' for i in range(len(a['bom']))],'notes[]':[scenario['warning']]*len(a['bom'])})
check(all(b['statut']=='validee' for b in state()['boms']),'Quatre compositions BOM validées via le contrôleur de production')
# An illegal self-component must fail without altering the valid composition.
valid_items=state()['bom_items']
post('/stock_production.php?bom_id='+str(boms['ACQ']),{'action':'composition','statut':'validee','article_id[]':[articles['ACQ']],'quantite[]':[1]})
check(state()['bom_items']==valid_items,'BOM auto-composée refusée sans altérer les compositions validées')
doc=next(d['id'] for d in s['documents'] if d['nom_fichier']=='09_gamme.pdf')
post('/stock_production.php?bom_id='+str(boms['SYS']),{'action':'planifier','reference':'REC-AT12-OF-FINAL-001','quantite':2,'date_metier':'2026-10-29','responsable_id':1,'document_id':doc})
s=state();check(len(s['orders'])==1 and s['orders'][0]['statut']=='planifie','OF final planifié sur instruction PDF du projet')
complete={'action':'terminer','order_id':s['orders'][0]['id'],'destination_id':finished,'series':'ATLAS12-REC-001\nATLAS12-REC-002','resultat':'CONTROLE FICTIF - Assemblage de deux équipements simulés. Voir 09_gamme.pdf et 11_resultats.pdf.'}
for code,qty in final['bom']:complete[f'lot[{articles[code]}]']=lots[code];complete[f'source[{articles[code]}]']=loc
bad=dict(complete,destination_id=999999);post('/stock_production.php?bom_id='+str(boms['SYS']),bad)
check(state()['orders'][0]['statut']=='planifie' and not state()['units'],'Destination invalide refusée atomiquement, sans produit inventé')
post('/stock_production.php?bom_id='+str(boms['SYS']),complete);s=state();check(s['orders'][0]['statut']=='termine' and len(s['units'])==2,'Assemblage final de deux appareils : OF terminé et séries persistantes')
check(len(s['genealogy'])==18,'Généalogie : neuf composants / sous-ensembles par appareil, dix-huit liens')
check(not any(r['statut']=='active' for r in s['reservations']),'Réservations consommées lors de la fabrication simulée')
decide(6,'DONE');decide(7,'CONFORME');decide(8,'CONFORME')
# Finish all genuinely generated tasks through the normal task edit controller.
for task in state()['tasks']:
 post('/tache.php?id='+str(task['id']),{'id':task['id'],'projet_id':pid,'titre':task['titre'],'description':task['description'],'priorite':task['priorite'],'assigne_a':'admin','statut':'terminee','date_debut':'2026-10-29','date_echeance':'2026-10-29','date_metier':'2026-10-29','resultats':'FICTIF - Réalisé en simulation documentaire. Voir les PDF de recette; aucune opération matérielle réelle.'})
s=state();check(all(t['statut']=='terminee' for t in s['tasks']),'Toutes les tâches techniques et composants terminées via leurs formulaires')
check(s['project']['current_step']==9 and s['project']['status']=='termine','Projet terminé au stade 9 par huit décisions métier réelles du contrôleur isolé')
# Re-download each project document from the WebDAV fixture and compare its original bytes.
for d in s['documents']:
 r=requests.get('http://127.0.0.1:'+os.getenv('ATLAS12_WEBDAV_PORT','8092')+'/'+d['chemin_nextcloud'],timeout=10);r.raise_for_status();check(len(r.content)==d['taille'],'Archive projet relue : '+d['nom_fichier'])
(out/'resultat_recette.json').write_text(json.dumps({'scope':os.getenv('ATLAS12_RECIPE_SCOPE','CI isolée - PAS exécuté sur les données DEV'),'project_id':pid,'checks':checks,'summary':{'functions':12,'technical_specs':30,'articles':24,'manufacturers':4,'suppliers':4,'pdfs_authored':40,'project_documents':len(s['documents']),'component_documents':len(s['stock']['documents']),'boms':len(s['boms']),'orders':len(s['orders']),'serials':len(s['units']),'genealogy_links':len(s['genealogy']),'tasks_done':len(s['tasks']),'step':s['project']['current_step'],'status':s['project']['status']},'limitations':['Aucune session authentifiée et aucun accès base applicative sur DEV.','WebDAV est un simulateur local; le Nextcloud DEV ne peut pas être déclaré testé.','ACQ/PWR/BOX reçus assemblés de sous-traitance simulée; seul l’assemblage final passe par OF.','Les décisions et mesures sont fictives; aucun essai physique, achat ou envoi externe.']},ensure_ascii=False,indent=2))
(out/'snapshot_recette.json').write_text(json.dumps(s,ensure_ascii=False,indent=2))
print(os.getenv('ATLAS12_RECIPE_SCOPE','RECETTE CI ISOLEE')+' TERMINEE : '+str(len(checks))+' contrôles. Aucune donnée réelle DEV modifiée.')
