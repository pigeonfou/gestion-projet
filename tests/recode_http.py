"""Disposable HTTP integration: login, route permissions, real forms and concurrency."""
from pathlib import Path
import http.cookiejar, os, re, socket, subprocess, tempfile, time, urllib.request, urllib.parse
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('PROJECTFLOW_PHP_BINARY','php')
PASSWORD='Recode-test-password-2026'
def check(ok,message):
    if not ok:raise AssertionError(message)
with tempfile.TemporaryDirectory(prefix='pf-http-') as tmp:
    env=os.environ.copy();env['PROJECTFLOW_DB_PATH']=str(Path(tmp)/'db.sqlite');env['PROJECTFLOW_ADMIN_PASSWORD']=PASSWORD
    subprocess.run([PHP,str(ROOT/'install/init_database.php')],env=env,check=True,capture_output=True)
    fixture=r'''require $argv[1].'/includes/cahier_specs.php';runSchemaMigrations();$db=getDB();
    $db->prepare("INSERT INTO utilisateurs(identifiant,mot_de_passe,role) VALUES('bob',?,'utilisateur')")->execute([password_hash('Recode-test-password-2026',PASSWORD_DEFAULT)]);
    $db->exec("INSERT INTO projets(nom,createur_id) VALUES('Visible project',1),('Private project',1)");
    $db->exec("INSERT INTO cahiers(projet_id,specs_json) VALUES(1,'{}'),(2,'{}')");
    saveSpecs(1,array_merge(emptySpecs(),['fonctions'=>[['uid'=>str_repeat('f',32),'id'=>'S.F.1','description'=>'Function','indicateur'=>'Obligatoire']]]));
    saveSpecs(2,array_merge(emptySpecs(),['objectifs'=>'PRIVATE_SPECIFICATION_SECRET']));
    saveSpecsTechniques(1,[['uid'=>str_repeat('a',32),'parent_uid'=>null,'sf'=>'S.F.1','id'=>'S.T.1.1','description'=>'Hardware','type'=>'Matériel']]);
    require $argv[1].'/includes/project_members.php';projectMembersSchema($db);$db->exec('INSERT INTO projet_contributeurs VALUES(1,2,1)');'''
    subprocess.run([PHP,'-r',fixture,str(ROOT)],env=env,check=True,capture_output=True)
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    log=open(Path(tmp)/'server.log','w',encoding='utf-8')
    server=subprocess.Popen([PHP,'-d','display_errors=1','-S',f'127.0.0.1:{port}',str(ROOT/'tests/support/http_router.php')],cwd=ROOT,env=env,stdout=log,stderr=log)
    base=f'http://127.0.0.1:{port}/gestion-projet/'
    jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    def request(route,data=None):
        body=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
        with client.open(base+route,data=body,timeout=15) as response:
            html=response.read().decode('utf-8');check('Fatal error' not in html and 'Warning:' not in html,'PHP output error: '+html[:500]);return response.geturl(),html
    def token(html,name='_csrf'):
        m=re.search(r'name="'+re.escape(name)+r'"[^>]*value="([^"]*)"',html);check(m is not None,'Missing form field '+name);return m.group(1)
    def login(user):
        jar.clear();_,html=request('login.php');request('verification_connexion.php',{'_csrf':token(html),'identifiant':user,'mot_de_passe':PASSWORD})
    try:
        for _ in range(50):
            try:urllib.request.urlopen(base+'login.php',timeout=1).close();break
            except (OSError,urllib.error.URLError):time.sleep(.1)
        login('bob')
        for route in ['projet.php?id=2','cdc_test_form.php?projet_id=2','specifications.php?projet_id=2','documents_externes.php?projet_id=2']:
            url,html=request(route);check('projets.php' in url and 'PRIVATE_SPECIFICATION_SECRET' not in html,'Cross-project read: '+route+' -> '+url+'; '+html[:300])
        _,html=request('projet.php?id=1&view=processus&step=2');check('Hardware' in html,'Contributor read denied')
        login('admin')
        _,html=request('projet.php?id=1&view=processus&step=2')
        revision=token(html,'specs_revision');csrf=token(html)
        form={'_csrf':csrf,'action':'save_specs_techniques','specs_revision':revision,'st_sf[]':['S.F.1'],'st_description[]':['Updated hardware'],'st_type[]':['Matériel'],'st_uid[]':['a'*32],'st_parent_uid[]':['']}
        request('projet.php?id=1&view=processus&step=2',form)
        _,html=request('projet.php?id=1&view=processus&step=2');check('Updated hardware' in html,'ST save failed')
        form['st_description[]']=['Stale overwrite'];request('projet.php?id=1&view=processus&step=2',form)
        _,html=request('projet.php?id=1&view=processus&step=2');check('Stale overwrite' not in html,'Stale save accepted')
        _,html=request('projet.php?id=1&view=processus&step=4')
        request('projet.php?id=1&view=processus&step=4',{'_csrf':token(html),'action':'save_composants_st','specs_revision':token(html,'specs_revision'),'cp_st_id[]':['S.T.1.1'],'cp_type[]':['Matériel'],'cp_uid[]':['c'*32],'cp_designation[]':['HTTP component'],'cp_quantite[]':['2'],'cp_cout_unitaire[]':['12,5']})
        _,html=request('projet.php?id=1&view=processus&step=5');check('HTTP component' in html,'Component save failed')
        key=re.search(r'name="purchase_create\[\]" value="([^"]+)"',html).group(1)
        payload=token(html,'purchase_payload['+key+']')
        purchase={'_csrf':token(html),'action':'create_purchase_tasks','specs_revision':token(html,'specs_revision'),'purchase_create[]':[key],'purchase_payload['+key+']':payload,'purchase_assignee['+key+']':'admin'}
        _,html=request('projet.php?id=1&view=processus&step=5',purchase);check('1 créée(s)' in html,'Purchase generation failed')
        purchase['specs_revision']=token(html,'specs_revision')
        _,html=request('projet.php?id=1&view=processus&step=5',purchase);check('1 mise(s) à jour' in html,'Purchase idempotency failed')
        for step in range(1,10):request(f'projet.php?id=1&view=processus&step={step}')
        for route in ['cahier_form.php?projet_id=1','projet.php?id=1&view=documents','projet.php?id=1&view=taches','projet.php?id=1&view=historique','stocks.php','stock_mouvements.php','admin/utilisateurs.php','admin/settings.php']:
            request(route)
        _,html=request('cahier.php?projet_id=1');check('_csrf' in html,'Legacy CDC missing CSRF')
        print('HTTP integration: authentication, project isolation, forms, reload and stale-save rejection OK')
    finally:
        server.terminate();server.wait(timeout=10);log.close()
