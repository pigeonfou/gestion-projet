<?php
function taskFilterInput(array $input): array {
    $f=[];
    foreach(['q','status','priority','assignee','project','due','from','to','sort','order'] as $k) $f[$k]=isset($input['tf_'.$k]) && is_scalar($input['tf_'.$k]) ? trim((string)$input['tf_'.$k]) : '';
    $f['q']=mb_substr($f['q'],0,200);
    foreach(['status'=>['a_faire','en_cours','validation','terminee'],'priority'=>['basse','moyenne','haute','urgente'],'due'=>['late','undated'],'sort'=>['id','title','deadline','priority','assignee','project'],'order'=>['asc','desc']] as $k=>$allowed) if(!in_array($f[$k],$allowed,true))$f[$k]='';
    $f['sort']=$f['sort']?:'id'; $f['order']=$f['order']?:'desc';
    $f['project']=ctype_digit($f['project']) && (int)$f['project']>0?(string)(int)$f['project']:'';
    foreach(['from','to'] as $k){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$f[$k]);if(!$d || $d->format('Y-m-d')!==$f[$k])$f[$k]='';}
    return $f;
}
function taskFilterQuery(array $f): string {
    $out=[]; foreach($f as $k=>$v)if($v!=='')$out['tf_'.$k]=$v;
    return http_build_query($out);
}
function taskFilterApply(array $tasks,array $f,?string $today=null): array {
    $today=$today??(new DateTimeImmutable('now',new DateTimeZone('Europe/Paris')))->format('Y-m-d');
    $needle=mb_strtolower($f['q']);
    $rows=array_values(array_filter($tasks,static function($t)use($f,$needle,$today){
        $status=taskKanbanStatus($t);$due=(string)($t['date_echeance']??'');$who=trim((string)($t['assigne_a']??''));
        if($needle!=='' && !str_contains(mb_strtolower(implode(' ',[(string)$t['id'],$t['titre']??'',$t['description']??'',$t['resultats']??'',$who,$t['projet_nom']??''])),$needle))return false;
        if($f['status']!=='' && $status!==$f['status'])return false;
        if($f['priority']!=='' && ($t['priorite']??'moyenne')!==$f['priority'])return false;
        if($f['assignee']!=='' && ($f['assignee']==='__unassigned'?$who!=='':$who!==$f['assignee']))return false;
        if($f['project']!=='' && (int)($t['projet_id']??0)!==(int)$f['project'])return false;
        if($f['due']==='undated' && $due!=='')return false;
        if($f['due']==='late' && ($due==='' || $due>=$today || $status==='terminee'))return false;
        if($f['from']!=='' && ($due==='' || $due<$f['from']))return false;
        if($f['to']!=='' && ($due==='' || $due>$f['to']))return false;
        return true;
    }));
    $priorities=['basse'=>1,'moyenne'=>2,'haute'=>3,'urgente'=>4];
    usort($rows,static function($a,$b)use($f,$priorities){
        if($f['sort']==='deadline'){
            $ad=$a['date_echeance']??'';$bd=$b['date_echeance']??'';
            if(($ad==='')!==($bd===''))return $ad===''?1:-1;
            $cmp=strcmp($ad,$bd);
        }elseif($f['sort']==='priority')$cmp=($priorities[$a['priorite']??'moyenne']??2)<=>($priorities[$b['priorite']??'moyenne']??2);
        elseif($f['sort']==='id')$cmp=(int)$a['id']<=>(int)$b['id'];
        else{$key=['title'=>'titre','assignee'=>'assigne_a','project'=>'projet_nom'][$f['sort']];$cmp=strnatcasecmp(mb_strtolower((string)($a[$key]??'')),mb_strtolower((string)($b[$key]??'')));}
        return ($cmp?:((int)$a['id']<=>(int)$b['id']))*($f['order']==='asc'?1:-1);
    });
    return $rows;
}
