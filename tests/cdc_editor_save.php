<?php
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/oddworks-cdc-save-'.bin2hex(random_bytes(6));mkdir($dir,0700,true);
putenv('PROJECTFLOW_DB_PATH='.$dir.'/test.sqlite');putenv('PROJECTFLOW_BASE_PATH=/oddworks');putenv('PROJECTFLOW_ADMIN_PASSWORD=fixture-only');
function runCdcChild(string $code): string {
    $pipes=[];$p=proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('PHP child unavailable');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0)throw new RuntimeException($err);
    return $out;
}
function saveCheck(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
try {
    runCdcChild('require '.var_export($root.'/install/init_database.php',true).';');
    require $root.'/includes/cahier_specs.php';
    runSchemaMigrations();$db=getDB();
    $db->exec("INSERT INTO projets(id,nom,createur_id) VALUES(1,'Fixture CDC',1);INSERT INTO cahiers(id,projet_id) VALUES(1,1)");
    $s=emptySpecs();$s['objectifs']='Initial';$s['fonctions']=[['id'=>'S.F.1','description'=>'Mesurer','indicateur'=>'Obligatoire']];
    $s['specs_techniques']=[['id'=>'S.T.1.1','sf'=>'S.F.1','uid'=>str_repeat('a',32),'description'=>'Capteur conservé','type'=>'Matériel']];
    $s['composants_st']=['S.T.1.1'=>[['description'=>'Composant conservé']]];
    saveSpecs(1,$s);
    $initial=loadSpecs(1);
    $payload=['projet_id'=>1,'form_action'=>'save','cdc_ajax'=>'1','_csrf'=>'fixture-csrf','cdc_revision'=>cdcEditorRevision($initial,[]),
        'objectifs'=>'Besoin modifié','sf_description'=>['Mesurer'],'sf_indicateur'=>['Obligatoire'],
        'jalon_nom'=>['Prototype'],'jalon_date'=>['2026-12-01']];
    $execute=function(array $post)use($root):array{
        $code='session_start();$_SESSION=["user_id"=>1,"identifiant"=>"admin","role"=>"admin","_csrf"=>"fixture-csrf"];$_SERVER["REQUEST_METHOD"]="POST";$_GET=["projet_id"=>1];$_POST=json_decode('.var_export(json_encode($post),true).',true);require '.var_export($root.'/cahier_form.php',true).';';
        $out=runCdcChild($code);$result=json_decode($out,true);
        if(!is_array($result))throw new RuntimeException('Non-JSON CDC response: '.substr($out,0,300));
        return $result;
    };
    $result=$execute($payload);saveCheck($result['ok']===true,'AJAX save confirmed');
    $stored=loadSpecs(1);
    saveCheck($stored['objectifs']==='Besoin modifié'&&loadJalons(1)[0]['nom']==='Prototype','All step-one tabs persist');
    saveCheck($stored['specs_techniques']===$initial['specs_techniques']&&$stored['composants_st']===$initial['composants_st'],'Steps two and four preserved');
    saveCheck($result['revision']===cdcEditorRevision($stored,loadJalons(1)),'Saved revision returned');
    $payload['objectifs']='Stale overwrite';$stale=$execute($payload);
    saveCheck($stale['ok']===false&&loadSpecs(1)['objectifs']==='Besoin modifié','Stale save rejected without losing changes');
    $payload['cdc_revision']=$result['revision'];$payload['form_action']='generate';$payload['objectifs']='';
    saveCheck($execute($payload)['ok']===false&&loadSpecs(1)['objectifs']==='Besoin modifié','Invalid PDF action does not overwrite saved CDC');
    echo "CDC save: real endpoint, all tabs persisted, S.T. and components preserved, stale content rejected OK\n";
} finally {
    foreach(glob($dir.'/*') as $file)@unlink($file);@rmdir($dir);
}
