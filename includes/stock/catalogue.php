<?php
/** Catalogue partagé : données natives, journal historique conservé. */
function inventorySchema(PDO $db): void {
    $extensions = [
        'stock_articles' => ['inventory_mode'=>"TEXT NOT NULL DEFAULT 'quantity'",'inventory_flags'=>"TEXT NOT NULL DEFAULT '{}'",'inventory_parent'=>'INTEGER REFERENCES stock_articles(id)','inventory_photo'=>"TEXT NOT NULL DEFAULT ''"],
        'stock_categories' => ['description'=>"TEXT NOT NULL DEFAULT ''"],
        'stock_emplacements' => ['structurel'=>'INTEGER NOT NULL DEFAULT 0','externe'=>'INTEGER NOT NULL DEFAULT 0','icone'=>"TEXT NOT NULL DEFAULT ''"],
        'stock_lots' => ['inventory_quality'=>'TEXT','emplacement_id'=>'INTEGER REFERENCES stock_emplacements(id)'],
    ];
    foreach ($extensions as $table=>$fields) {
        $cols=$db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN,1);
        foreach ($fields as $name=>$definition) if(!in_array($name,$cols,true)) $db->exec("ALTER TABLE $table ADD COLUMN $name $definition");
    }
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_meta(id INTEGER PRIMARY KEY CHECK(id=1), revision INTEGER NOT NULL DEFAULT 0);
        INSERT OR IGNORE INTO inventory_meta(id) VALUES(1);
        CREATE TABLE IF NOT EXISTS inventory_parameters(id INTEGER PRIMARY KEY AUTOINCREMENT,article_id INTEGER NOT NULL REFERENCES stock_articles(id),nom TEXT NOT NULL,valeur TEXT NOT NULL,unite TEXT NOT NULL DEFAULT '',UNIQUE(article_id,nom));
        CREATE TABLE IF NOT EXISTS inventory_companies(id INTEGER PRIMARY KEY AUTOINCREMENT,nom TEXT NOT NULL COLLATE NOCASE UNIQUE,fournisseur INTEGER NOT NULL DEFAULT 0,fabricant INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE IF NOT EXISTS inventory_manufacturers(id INTEGER PRIMARY KEY AUTOINCREMENT,article_id INTEGER NOT NULL REFERENCES stock_articles(id),company_id INTEGER NOT NULL REFERENCES inventory_companies(id),reference TEXT NOT NULL,description TEXT NOT NULL DEFAULT '',lien TEXT NOT NULL DEFAULT '',notes TEXT NOT NULL DEFAULT '',UNIQUE(article_id,company_id,reference));
        CREATE TABLE IF NOT EXISTS inventory_suppliers(id INTEGER PRIMARY KEY AUTOINCREMENT,article_id INTEGER NOT NULL REFERENCES stock_articles(id),company_id INTEGER NOT NULL REFERENCES inventory_companies(id),manufacturer_id INTEGER REFERENCES inventory_manufacturers(id),sku TEXT NOT NULL,description TEXT NOT NULL DEFAULT '',tags TEXT NOT NULL DEFAULT '',lien TEXT NOT NULL DEFAULT '',notes TEXT NOT NULL DEFAULT '',pack_quantity REAL NOT NULL DEFAULT 1 CHECK(pack_quantity>0),packaging TEXT NOT NULL DEFAULT '',principal INTEGER NOT NULL DEFAULT 0,actif INTEGER NOT NULL DEFAULT 1,prix_ht REAL CHECK(prix_ht>=0),UNIQUE(article_id,company_id,sku));
        CREATE TABLE IF NOT EXISTS inventory_documents(id INTEGER PRIMARY KEY AUTOINCREMENT,article_id INTEGER NOT NULL REFERENCES stock_articles(id),nom TEXT NOT NULL,chemin TEXT NOT NULL UNIQUE,taille INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
        CREATE TABLE IF NOT EXISTS inventory_history(id INTEGER PRIMARY KEY AUTOINCREMENT,article_id INTEGER REFERENCES stock_articles(id),user_id INTEGER NOT NULL,action TEXT NOT NULL,details TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_imports(version INTEGER PRIMARY KEY)");
    if (!invOne($db,'SELECT version FROM inventory_imports WHERE version=1')) {
    // Migration additive et idempotente des références fournisseur existantes.
    $db->exec("INSERT OR IGNORE INTO inventory_companies(nom,fournisseur) SELECT nom,1 FROM stock_fournisseurs WHERE trim(nom)<>'';
        INSERT OR IGNORE INTO inventory_suppliers(article_id,company_id,sku,prix_ht,principal)
        SELECT af.article_id,c.id,COALESCE(af.reference_fournisseur,''),af.prix,af.preferentiel FROM stock_article_fournisseur af JOIN stock_fournisseurs f ON f.id=af.fournisseur_id JOIN inventory_companies c ON c.nom=f.nom");
    $db->exec("UPDATE stock_articles SET inventory_mode='lot' WHERE EXISTS(SELECT 1 FROM stock_lots WHERE article_id=stock_articles.id);
        UPDATE stock_articles SET inventory_mode='serial' WHERE EXISTS(SELECT 1 FROM stock_unites WHERE article_id=stock_articles.id);
        INSERT OR IGNORE INTO inventory_imports(version) VALUES(1)");
    }
}
function invRows(PDO $db,string $sql,array $args=[]): array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function invOne(PDO $db,string $sql,array $args=[]): ?array {return invRows($db,$sql,$args)[0]??null;}
function invRun(PDO $db,string $sql,array $args=[]): int {$q=$db->prepare($sql);$q->execute($args);return (int)$db->lastInsertId();}
function invText(array $p,string $key,int $max=240,bool $required=false): string {
    $s=$p[$key]??'';if(!is_scalar($s))throw new InvalidArgumentException('Champ invalide : '.$key);$s=trim((string)$s);
    if(strlen($s)>$max||($required&&$s===''))throw new InvalidArgumentException('Champ requis ou trop long : '.$key);return $s;
}
function invNumber(array $p,string $key,float $min=0): float {$v=filter_var($p[$key]??null,FILTER_VALIDATE_FLOAT);if($v===false||!is_finite($v)||$v<$min)throw new InvalidArgumentException('Nombre invalide : '.$key);return $v;}
function invBool(array $p,string $key): int {return !empty($p[$key])?1:0;}
function invUrl(array $p,string $key): string {$s=invText($p,$key,2000);if($s!==''&&(!filter_var($s,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($s,PHP_URL_SCHEME)??''),['http','https'],true)))throw new InvalidArgumentException('Lien HTTP(S) requis.');return $s;}
function invArticle(PDO $db,int $id,bool $write=false): array {
    $a=invOne($db,'SELECT * FROM stock_articles WHERE id=?',[$id]);if(!$a)throw new InvalidArgumentException('Composant introuvable.');
    if($write&&!empty(json_decode($a['inventory_flags'],true)['locked']))throw new InvalidArgumentException('Fiche verrouillée.');return $a;
}
function invFlags(array $a): array {return array_replace(['locked'=>false,'template'=>false,'assembled'=>false,'component'=>true,'testable'=>false,'trackable'=>false,'purchasable'=>true,'sellable'=>false,'virtual'=>false],json_decode($a['inventory_flags'],true)?:[]);}
function invHistory(PDO $db,?int $article,int $user,string $action,array $details):void {invRun($db,'INSERT INTO inventory_history(article_id,user_id,action,details) VALUES(?,?,?,?)',[$article,$user,$action,json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);}
function invPath(array $locations,int $id,array $seen=[]):string {if(isset($seen[$id]))throw new InvalidArgumentException('Cycle dans les emplacements.');$seen[$id]=true;foreach($locations as $l)if((int)$l['id']===$id)return ($l['parent_id']?invPath($locations,(int)$l['parent_id'],$seen).' / ':'').$l['nom'];return '';}
function invExternal(array $locations,int $id,array $seen=[]):bool {if(isset($seen[$id]))return false;$seen[$id]=true;foreach($locations as $l)if((int)$l['id']===$id)return !empty($l['externe'])||($l['parent_id']&&invExternal($locations,(int)$l['parent_id'],$seen));return false;}
/** Ledger balances by lot, serial and location, including legacy untracked movements. */
function invRecords(PDO $db):array {
    $sql="SELECT article_id,lot_id,unite_id,location_id,SUM(delta) quantity FROM (
        SELECT article_id,lot_id,unite_id,emplacement_destination_id location_id,quantite delta FROM stock_mouvements WHERE emplacement_destination_id IS NOT NULL AND type NOT IN ('reservation','liberation_reservation')
        UNION ALL SELECT article_id,lot_id,unite_id,emplacement_source_id,-quantite FROM stock_mouvements WHERE emplacement_source_id IS NOT NULL AND type NOT IN ('reservation','liberation_reservation')
        UNION ALL SELECT article_id,lot_id,unite_id,NULL,CASE WHEN type IN ('consommation','affectation_projet','rebut','demontage') THEN -quantite ELSE quantite END FROM stock_mouvements WHERE emplacement_source_id IS NULL AND emplacement_destination_id IS NULL AND type NOT IN ('transfert','reservation','liberation_reservation')
    ) GROUP BY article_id,lot_id,unite_id,location_id HAVING ABS(SUM(delta))>0.000001";
    $rows=invRows($db,$sql);$lots=[];foreach(invRows($db,'SELECT * FROM stock_lots') as $l)$lots[$l['id']]=$l;
    $units=[];foreach(invRows($db,'SELECT * FROM stock_unites') as $u)$units[$u['id']]=$u;
    foreach($rows as &$r){$l=$lots[$r['lot_id']]??null;$u=$units[$r['unite_id']]??null;$r['trace']=$u['numero_serie']??$l['reference_lot']??'';$r['quality']=$l['inventory_quality']??(['libere'=>'accepted','quarantaine'=>'quarantine','rejete'=>'rejected'][$l['statut']??'libere']??'pending');$r['cost']=$l['cout_unitaire_ht']??null;$r['received']=$l['date_reception']??'';$r['metadata_only']=false;$r['key']=implode(':',[$r['article_id'],$r['lot_id']??0,$r['unite_id']??0,$r['location_id']??0]);}unset($r);
    // Old quarantined lots had no physical movement. Expose them without inventing a receipt.
    foreach($lots as $l){if(invOne($db,'SELECT id FROM stock_mouvements WHERE lot_id=? LIMIT 1',[$l['id']]))continue;if((float)$l['quantite_initiale']<=0)continue;$rows[]=['article_id'=>$l['article_id'],'lot_id'=>$l['id'],'unite_id'=>null,'location_id'=>$l['emplacement_id'],'quantity'=>$l['quantite_initiale'],'trace'=>$l['reference_lot'],'quality'=>$l['inventory_quality']??'quarantine','cost'=>$l['cout_unitaire_ht'],'received'=>$l['date_reception'],'metadata_only'=>true,'key'=>$l['article_id'].':'.$l['id'].':0:'.($l['emplacement_id']??0)];}
    return $rows;
}
function invReserved(PDO $db,int $article,?int $location=null):float {$sql="SELECT COALESCE(SUM(quantite),0) n FROM stock_reservations WHERE article_id=? AND statut='active'";$args=[$article];if($location!==null){$sql.=' AND (emplacement_id=? OR emplacement_id IS NULL)';$args[]=$location;}return (float)invOne($db,$sql,$args)['n'];}
function invAvailable(PDO $db,int $article,?int $location=null):float {$sum=0;foreach(invRecords($db) as $r)if((int)$r['article_id']===$article&&($location===null||(int)$r['location_id']===$location)&&$r['quality']==='accepted')$sum+=(float)$r['quantity'];return max(0,$sum-invReserved($db,$article,$location));}
function invRevision(PDO $db):string {
    $state=[];foreach(['inventory_meta','stock_articles','stock_categories','stock_emplacements','stock_mouvements','stock_lots','stock_unites','stock_reservations','inventory_parameters','inventory_suppliers','inventory_manufacturers','inventory_documents'] as $t)$state[]=invRows($db,"SELECT * FROM $t ORDER BY id");return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
}
function invState(PDO $db):array {
    $articles=invRows($db,'SELECT a.*,c.nom category FROM stock_articles a LEFT JOIN stock_categories c ON c.id=a.categorie_id ORDER BY COALESCE(a.reference_fabricant,a.reference),a.id');
    $records=invRecords($db);foreach($articles as &$a){$a['flags']=invFlags($a);$a['physical']=0;$a['held']=0;foreach($records as $r)if((int)$r['article_id']===(int)$a['id']){$a['physical']+=(float)$r['quantity'];if($r['quality']!=='accepted')$a['held']+=(float)$r['quantity'];}$a['reserved']=invReserved($db,(int)$a['id']);$a['available']=max(0,$a['physical']-$a['held']-$a['reserved']);$a['price']=invOne($db,'SELECT MAX(cout_unitaire_ht) n FROM stock_lots WHERE article_id=?',[$a['id']])['n'];}unset($a);
    $locations=invRows($db,'SELECT * FROM stock_emplacements WHERE actif=1 ORDER BY chemin,nom');foreach($locations as &$l){$l['path']=invPath($locations,(int)$l['id']);$l['external']=invExternal($locations,(int)$l['id']);}unset($l);
    return ['revision'=>invRevision($db),'articles'=>$articles,'categories'=>invRows($db,'SELECT * FROM stock_categories WHERE actif=1 ORDER BY nom'),'locations'=>$locations,'records'=>$records,'parameters'=>invRows($db,'SELECT * FROM inventory_parameters ORDER BY nom'),'companies'=>invRows($db,'SELECT * FROM inventory_companies ORDER BY nom'),'manufacturers'=>invRows($db,'SELECT r.*,c.nom company FROM inventory_manufacturers r JOIN inventory_companies c ON c.id=r.company_id'),'suppliers'=>invRows($db,'SELECT r.*,c.nom company FROM inventory_suppliers r JOIN inventory_companies c ON c.id=r.company_id'),'documents'=>invRows($db,'SELECT id,article_id,nom,taille,created_at FROM inventory_documents'),'history'=>invRows($db,'SELECT h.*,u.identifiant acteur FROM inventory_history h LEFT JOIN utilisateurs u ON u.id=h.user_id ORDER BY h.id DESC LIMIT 1000'),'reservations'=>invRows($db,'SELECT * FROM stock_reservations ORDER BY id DESC')];
}
function invMovement(PDO $db,int $article,float $quantity,string $type,?int $source,?int $dest,?int $lot,?int $unit,int $user,string $note):void {
    invRun($db,'INSERT INTO stock_mouvements(article_id,type,quantite,emplacement_source_id,emplacement_destination_id,lot_id,unite_id,user_id,note) VALUES(?,?,?,?,?,?,?,?,?)',[$article,$type,$quantity,$source,$dest,$lot,$unit,$user,$note]);
    $q=0;foreach(invRecords($db) as $r)if((int)$r['article_id']===$article&&!$r['metadata_only'])$q+=(float)$r['quantity'];invRun($db,'UPDATE stock_articles SET quantite_stock=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$q,$article]);
}
/** Caller holds an IMMEDIATE SQLite transaction and has checked revision/CSRF. */
function invCommand(PDO $db,string $action,array $p,int $user,bool $admin):?int {
    $id=(int)($p['id']??0);$article=(int)($p['article_id']??0);$result=null;
    if($action==='category_save'){
        $name=invText($p,'nom',100,true);$description=invText($p,'description',2000);
        if(invOne($db,'SELECT id FROM stock_categories WHERE lower(nom)=lower(?) AND id<>?',[$name,$id]))throw new InvalidArgumentException('Cette catégorie existe déjà.');
        if($id){if(!invOne($db,'SELECT id FROM stock_categories WHERE id=?',[$id]))throw new InvalidArgumentException('Catégorie introuvable.');invRun($db,'UPDATE stock_categories SET nom=?,description=? WHERE id=?',[$name,$description,$id]);$result=$id;}else $result=invRun($db,'INSERT INTO stock_categories(code,nom,description) VALUES(?,?,?)',['CAT-'.bin2hex(random_bytes(8)),$name,$description]);
    }elseif($action==='category_delete'){
        if(invOne($db,'SELECT id FROM stock_articles WHERE categorie_id=? LIMIT 1',[$id])||invOne($db,'SELECT id FROM stock_categories WHERE parent_id=? LIMIT 1',[$id]))throw new InvalidArgumentException('Catégorie utilisée : suppression refusée.');invRun($db,'DELETE FROM stock_categories WHERE id=?',[$id]);
    }elseif($action==='location_save'){
        $name=invText($p,'nom',100,true);$parent=(int)($p['parent_id']??0)?:null;$all=invRows($db,'SELECT * FROM stock_emplacements');
        if($id&&!invOne($db,'SELECT id FROM stock_emplacements WHERE id=?',[$id]))throw new InvalidArgumentException('Emplacement introuvable.');
        if($parent&&!invOne($db,'SELECT id FROM stock_emplacements WHERE id=? AND actif=1',[$parent]))throw new InvalidArgumentException('Parent invalide.');
        foreach($all as $l)if((int)$l['id']!==$id&&(int)$l['parent_id']===(int)$parent&&strcasecmp($l['nom'],$name)===0)throw new InvalidArgumentException('Nom déjà utilisé sous ce parent.');
        if($id&&invBool($p,'structurel'))foreach(invRecords($db) as $r)if((int)$r['location_id']===$id&&abs((float)$r['quantity'])>0)throw new InvalidArgumentException('Déplacez le stock avant de rendre cet emplacement structurel.');
        $new=['id'=>$id?:-1,'parent_id'=>$parent,'nom'=>$name];$tree=array_values(array_filter($all,fn($l)=>(int)$l['id']!==$id));$tree[]=$new;invPath($tree,$new['id']);
        $vals=[$parent,$name,invText($p,'type',100),invText($p,'description',2000),invBool($p,'structurel'),invBool($p,'externe'),invText($p,'icone',40)];
        if($id){$vals[]=$id;invRun($db,'UPDATE stock_emplacements SET parent_id=?,nom=?,type=?,notes=?,structurel=?,externe=?,icone=? WHERE id=?',$vals);$result=$id;}else{$vals[]='LOC-'.bin2hex(random_bytes(8));$result=invRun($db,"INSERT INTO stock_emplacements(parent_id,nom,type,notes,structurel,externe,icone,code,chemin) VALUES(?,?,?,?,?,?,?,?,'')",$vals);}
        $all=invRows($db,'SELECT * FROM stock_emplacements');foreach($all as $l){$path=invPath($all,(int)$l['id']);invRun($db,'UPDATE stock_emplacements SET chemin=?,niveau=? WHERE id=?',[$path,substr_count($path,' / '),$l['id']]);}
    }elseif($action==='article_save'){
        $old=$id?invArticle($db,$id,true):null;$name=invText($p,'designation',180,true);$ref=invText($p,'reference_fabricant',160,true);$maker=invText($p,'fabricant',100,true);$mode=invText($p,'inventory_mode');
        if(!in_array($mode,['quantity','lot','serial'],true))throw new InvalidArgumentException('Suivi invalide.');
        if(invOne($db,"SELECT id FROM stock_articles WHERE lower(COALESCE(reference_fabricant,reference))=lower(?) AND lower(COALESCE(fabricant,''))=lower(?) AND id<>?",[$ref,$maker,$id]))throw new InvalidArgumentException('Ce couple fabricant / référence existe déjà.');
        $cat=(int)($p['categorie_id']??0)?:null;if($cat&&!invOne($db,'SELECT id FROM stock_categories WHERE id=? AND actif=1',[$cat]))throw new InvalidArgumentException('Catégorie invalide.');
        $flags=[];foreach(['locked','template','assembled','component','testable','trackable','purchasable','sellable','virtual'] as $k)$flags[$k]=!empty($p['flags'][$k]);
        if(($flags['virtual']||$flags['template'])&&$flags['trackable'])throw new InvalidArgumentException('Un modèle ou un composant virtuel ne peut pas être suivi physiquement.');
        if($old){$has=invOne($db,'SELECT id FROM stock_mouvements WHERE article_id=? LIMIT 1',[$id])||invOne($db,'SELECT id FROM stock_lots WHERE article_id=? LIMIT 1',[$id]);if($has&&($mode!==$old['inventory_mode']||invText($p,'unite',20,true)!==$old['unite']||$flags['virtual']||$flags['template']))throw new InvalidArgumentException('Historique existant : suivi, unité et nature physique ne peuvent plus changer.');$flags['locked']=invFlags($old)['locked'];}
        $parent=(int)($p['inventory_parent']??0)?:null;if($old)$parent=$old['inventory_parent'];if($parent)invArticle($db,(int)$parent);
        $photo=invText($p,'inventory_photo',3000000);if($photo!==''&&(!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D',$photo,$m)||!($bytes=base64_decode($m[2],true))||!($info=@getimagesizefromstring($bytes))||!in_array($info['mime'],['image/png','image/jpeg','image/webp'],true)))throw new InvalidArgumentException('Image PNG, JPEG ou WebP invalide (2 Mo maximum).');
        $vals=[$name,$ref,$maker,$cat,$mode,json_encode($flags,JSON_THROW_ON_ERROR),$parent,$photo,invText($p,'description',10000),invNumber($p,'quantite_min'),invText($p,'unite',20,true)];
        if($id){$vals[]=$id;invRun($db,'UPDATE stock_articles SET designation=?,reference_fabricant=?,fabricant=?,categorie_id=?,inventory_mode=?,inventory_flags=?,inventory_parent=?,inventory_photo=?,description=?,quantite_min=?,unite=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',$vals);$result=$id;}else{$internal=$ref;if(invOne($db,'SELECT id FROM stock_articles WHERE reference=?',[$internal]))$internal=$ref.' @ '.$maker.' #'.bin2hex(random_bytes(4));$vals[]=$internal;$result=invRun($db,'INSERT INTO stock_articles(designation,reference_fabricant,fabricant,categorie_id,inventory_mode,inventory_flags,inventory_parent,inventory_photo,description,quantite_min,unite,reference) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',$vals);}$article=$result;
    }elseif($action==='lock'){
        $a=invArticle($db,$article);$f=invFlags($a);$f['locked']=!$f['locked'];invRun($db,'UPDATE stock_articles SET inventory_flags=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[json_encode($f),$article]);
    }elseif($action==='parameter_save'||$action==='parameter_delete'){
        invArticle($db,$article,true);if($action==='parameter_delete')invRun($db,'DELETE FROM inventory_parameters WHERE id=? AND article_id=?',[$id,$article]);else{$name=invText($p,'nom',100,true);if(invOne($db,'SELECT id FROM inventory_parameters WHERE article_id=? AND lower(trim(nom))=lower(?) AND id<>?',[$article,$name,$id]))throw new InvalidArgumentException('Paramètre déjà présent.');$vals=[$name,invText($p,'valeur',2000,true),invText($p,'unite',40)];if($id){$vals[]=$id;$vals[]=$article;invRun($db,'UPDATE inventory_parameters SET nom=?,valeur=?,unite=? WHERE id=? AND article_id=?',$vals);}else{$vals[]=$article;$result=invRun($db,'INSERT INTO inventory_parameters(nom,valeur,unite,article_id) VALUES(?,?,?,?)',$vals);}}
    }elseif(in_array($action,['manufacturer_save','supplier_save'],true)){
        $a=invArticle($db,$article,true);$supplier=$action==='supplier_save';if($supplier&&!invFlags($a)['purchasable'])throw new InvalidArgumentException('Ce composant n’est pas achetable.');
        $company=invText($p,'company',120,true);$field=$supplier?'fournisseur':'fabricant';invRun($db,"INSERT INTO inventory_companies(nom,$field) VALUES(?,1) ON CONFLICT(nom) DO UPDATE SET $field=1",[$company]);$cid=(int)invOne($db,'SELECT id FROM inventory_companies WHERE nom=?',[$company])['id'];
        if(!$supplier){$vals=[$cid,invText($p,'reference',160,true),invText($p,'description',2000),invUrl($p,'lien'),invText($p,'notes',4000)];if($id){$vals[]=$id;$vals[]=$article;invRun($db,'UPDATE inventory_manufacturers SET company_id=?,reference=?,description=?,lien=?,notes=? WHERE id=? AND article_id=?',$vals);}else{$vals[]=$article;$result=invRun($db,'INSERT INTO inventory_manufacturers(company_id,reference,description,lien,notes,article_id) VALUES(?,?,?,?,?,?)',$vals);}}
        else{$mid=(int)($p['manufacturer_id']??0)?:null;if($mid&&!invOne($db,'SELECT id FROM inventory_manufacturers WHERE id=? AND article_id=?',[$mid,$article]))throw new InvalidArgumentException('Référence fabricant étrangère au composant.');if(invBool($p,'principal'))invRun($db,'UPDATE inventory_suppliers SET principal=0 WHERE article_id=?',[$article]);$vals=[$cid,$mid,invText($p,'sku',160,true),invText($p,'description',2000),invText($p,'tags',500),invUrl($p,'lien'),invText($p,'notes',4000),invNumber($p,'pack_quantity',0.000001),invText($p,'packaging',240),invBool($p,'principal'),invBool($p,'actif'),($p['prix_ht']??'')===''?null:invNumber($p,'prix_ht')];if($id){$vals[]=$id;$vals[]=$article;invRun($db,'UPDATE inventory_suppliers SET company_id=?,manufacturer_id=?,sku=?,description=?,tags=?,lien=?,notes=?,pack_quantity=?,packaging=?,principal=?,actif=?,prix_ht=? WHERE id=? AND article_id=?',$vals);}else{$vals[]=$article;$result=invRun($db,'INSERT INTO inventory_suppliers(company_id,manufacturer_id,sku,description,tags,lien,notes,pack_quantity,packaging,principal,actif,prix_ht,article_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',$vals);}}
    }elseif($action==='stock_create'){
        $a=invArticle($db,$article,true);$f=invFlags($a);if($f['virtual']||$f['template'])throw new InvalidArgumentException('Ce composant ne reçoit pas de stock.');
        $loc=(int)($p['location_id']??0);$l=invOne($db,'SELECT * FROM stock_emplacements WHERE id=? AND actif=1',[$loc]);if(!$l||$l['structurel'])throw new InvalidArgumentException('Sélectionnez un emplacement non structurel.');
        $qty=invNumber($p,'quantity',0.000001);if(($a['inventory_mode']==='serial'&&$qty!==1.0)||(in_array($a['unite'],['u','pce'],true)&&floor($qty)!==$qty))throw new InvalidArgumentException('Quantité entière requise ; une unité par numéro de série.');
        $trace=invText($p,'trace',160,$a['inventory_mode']!=='quantity');$date=invText($p,'received',10,true);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('Date de réception invalide.');$quality=invText($p,'quality');if(!in_array($quality,['pending','quarantine','accepted','rejected'],true))throw new InvalidArgumentException('Contrôle invalide.');
        if($a['inventory_mode']==='serial'&&invOne($db,'SELECT id FROM stock_unites WHERE numero_serie=?',[$trace]))throw new InvalidArgumentException('Ce numéro de série existe déjà.');
        if($a['inventory_mode']==='lot'&&invOne($db,'SELECT id FROM stock_lots WHERE article_id=? AND reference_lot=?',[$article,$trace]))throw new InvalidArgumentException('Ce lot existe déjà.');
        $cost=invNumber($p,'cost');$lot=invRun($db,'INSERT INTO stock_lots(article_id,reference_lot,date_reception,quantite_initiale,cout_unitaire_ht,statut,inventory_quality,emplacement_id,notes) VALUES(?,?,?,?,?,?,?,?,?)',[$article,$trace?:'REC-'.bin2hex(random_bytes(8)),$date,$qty,$cost,$quality==='accepted'?'libere':($quality==='rejected'?'rejete':'quarantaine'),$quality,$loc,invText($p,'comment',4000)]);
        $unit=$a['inventory_mode']==='serial'?invRun($db,'INSERT INTO stock_unites(article_id,numero_serie,emplacement_id,date_acquisition) VALUES(?,?,?,?)',[$article,$trace,$loc,$date]):null;
        invMovement($db,$article,$qty,'reception',null,$loc,$lot,$unit,$user,invText($p,'comment',4000));invRun($db,'UPDATE stock_articles SET valeur_unitaire=MAX(COALESCE(valeur_unitaire,0),?),updated_at=CURRENT_TIMESTAMP WHERE id=?',[$cost,$article]);$result=$lot;
    }elseif($action==='quality'){
        invArticle($db,$article,true);$key=invText($p,'key',160,true);$selected=null;foreach(invRecords($db) as $r)if($r['key']===$key&&(int)$r['article_id']===$article)$selected=$r;
        if(!$selected||!$selected['lot_id'])throw new InvalidArgumentException('Entrée historique sans lot : rapprochez sa traçabilité avant contrôle.');
        $quality=invText($p,'quality');$comment=invText($p,'comment',4000,true);if(!in_array($quality,['pending','quarantine','accepted','rejected'],true))throw new InvalidArgumentException('Contrôle invalide.');
        // Conservateur : une réservation non attachée au lot interdit de bloquer le composant.
        if($quality!=='accepted'&&invReserved($db,$article)>0)throw new InvalidArgumentException('Libérez les réservations avant de bloquer le contrôle.');
        $lot=(int)$selected['lot_id'];if($selected['metadata_only']&&$quality==='accepted'){if(!$selected['location_id'])throw new InvalidArgumentException('Emplacement du lot historique manquant.');invMovement($db,$article,(float)$selected['quantity'],'reception',null,(int)$selected['location_id'],$lot,null,$user,$comment);}
        invRun($db,'UPDATE stock_lots SET inventory_quality=?,statut=? WHERE id=?',[$quality,$quality==='accepted'?'libere':($quality==='rejected'?'rejete':'quarantaine'),$lot]);
    }elseif($action==='reserve'){
        invArticle($db,$article,true);$project=(int)($p['projet_id']??0);$loc=(int)($p['location_id']??0);$qty=invNumber($p,'quantity',0.000001);if(!invOne($db,'SELECT id FROM projets WHERE id=?',[$project])||!$loc||invAvailable($db,$article,$loc)+0.000001<$qty)throw new InvalidArgumentException('Projet, emplacement ou disponibilité insuffisants.');
        $result=invRun($db,'INSERT INTO stock_reservations(article_id,projet_id,quantite,emplacement_id,note) VALUES(?,?,?,?,?)',[$article,$project,$qty,$loc,invText($p,'comment',4000)]);
    }elseif($action==='release'){
        $r=invOne($db,"SELECT * FROM stock_reservations WHERE id=? AND statut='active'",[$id]);if(!$r)throw new InvalidArgumentException('Réservation active introuvable.');$article=(int)$r['article_id'];invArticle($db,$article,true);invRun($db,"UPDATE stock_reservations SET statut='liberee',date_fin=CURRENT_TIMESTAMP WHERE id=?",[$id]);invText($p,'comment',4000,true);
    }elseif($action==='stock_out'){
        $a=invArticle($db,$article,true);$key=invText($p,'key',160,true);$selected=null;foreach(invRecords($db) as $r)if($r['key']===$key&&(int)$r['article_id']===$article)$selected=$r;$qty=invNumber($p,'quantity',0.000001);$reason=invText($p,'comment',4000,true);
        if(!$selected||$selected['metadata_only']||$selected['quality']!=='accepted'||$qty>(float)$selected['quantity']||invAvailable($db,$article,(int)$selected['location_id'])+0.000001<$qty||($a['inventory_mode']==='serial'&&$qty!==1.0)||(in_array($a['unite'],['u','pce'],true)&&floor($qty)!==$qty))throw new InvalidArgumentException('Sortie supérieure au stock conforme libre, ou quantité invalide.');
        invMovement($db,$article,$qty,'consommation',$selected['location_id']?(int)$selected['location_id']:null,null,$selected['lot_id']?(int)$selected['lot_id']:null,$selected['unite_id']?(int)$selected['unite_id']:null,$user,$reason);
    }else throw new InvalidArgumentException('Action inconnue.');
    // Do not duplicate image payloads or other bulky contents in the audit journal.
    $audit=$p;unset($audit['inventory_photo'],$audit['revision'],$audit['_csrf']);invHistory($db,$article?:null,$user,$action,$audit);invRun($db,'UPDATE inventory_meta SET revision=revision+1 WHERE id=1');return $result;
}
