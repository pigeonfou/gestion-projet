<?php
// php -n tests/document_upload_stream.php : transport cURL simulé, aucun serveur distant.
require_once __DIR__.'/../includes/NextcloudClient.php';
foreach (['CURLOPT_UPLOAD','CURLOPT_INFILESIZE_LARGE','CURLOPT_USERPWD','CURLOPT_SSL_VERIFYPEER',
    'CURLOPT_SSL_VERIFYHOST','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_HTTPHEADER',
    'CURLOPT_READFUNCTION','CURLOPT_WRITEFUNCTION','CURLINFO_HTTP_CODE','CURL_READFUNC_ABORT'] as $i=>$key) define($key,$i+1);
function curl_init($url) { return (object)['url'=>$url,'options'=>[],'code'=>201]; }
function curl_setopt_array($ch,$options) { $ch->options=$options; return true; }
function curl_exec($ch) {
    $GLOBALS['lastOptions']=$ch->options;
    $GLOBALS['lastUrl']=$ch->url;
    $ch->code=$GLOBALS['responseCode']??201;
    if ($ch->code===412) return true;
    $ctx=hash_init('sha256'); $bytes=0;
    while ($bytes<$ch->options[CURLOPT_INFILESIZE_LARGE]) {
        $data=($ch->options[CURLOPT_READFUNCTION])($ch,null,min(65536,$ch->options[CURLOPT_INFILESIZE_LARGE]-$bytes));
        if (!is_string($data) || $data==='') return false;
        $bytes+=strlen($data); hash_update($ctx,$data);
    }
    $GLOBALS['sentHash']=hash_final($ctx);
    ($ch->options[CURLOPT_WRITEFUNCTION])($ch,'réponse');
    return true;
}
function curl_getinfo($ch,$key) { return $ch->code; }
function curl_close($ch) {}
class StreamTestClient extends NextcloudClient {
    public function __construct() {
        foreach (['baseUrl'=>'https://example.invalid/dav','user'=>'test','password'=>'test'] as $name=>$value) {
            $p=new ReflectionProperty(NextcloudClient::class,$name); $p->setValue($this,$value);
        }
    }
    public function ensureFolder(string $path): bool { return true; }
}
function check($value,$message) { if (!$value) throw new RuntimeException($message); }
$nc=new StreamTestClient();
$file=tmpfile();
try {
    // Plus de 50 Mo, octets nuls et suffixe binaire, mémoire indépendante de la taille.
    fseek($file,55*1024*1024); fwrite($file,"\x00\xff\x80fin"); rewind($file);
    $size=fstat($file)['size'];
    $hash=hash_init('sha256'); hash_update_stream($hash,$file); $expected=hash_final($hash); rewind($file);
    $result=$nc->uploadStream('Projet_3/01_Besoin/pièce jointe.php',$file,$size);
    check($result['ok'],'Upload supérieur à 50 Mo refusé');
    check($GLOBALS['sentHash']===$expected,'Le contenu binaire a changé');
    check(str_contains($GLOBALS['lastUrl'],'pi%C3%A8ce%20jointe.php'),'Nom non encodé');
    check($GLOBALS['lastOptions'][CURLOPT_TIMEOUT]===0,'Plafond de durée');
    check(memory_get_peak_usage(true)<16*1024*1024,'Chargement complet en mémoire');
    rewind($file); $GLOBALS['responseCode']=412;
    check(!$nc->uploadStream('document.php',$file,$size)['ok'],'Écrasement non protégé');
    $GLOBALS['responseCode']=201;
    check(!$nc->uploadStream('incomplet.bin',$file,$size+1)['ok'],'Flux incomplet confirmé');
    $empty=tmpfile();
    try { check($nc->uploadStream('vide.zip',$empty,0)['ok'],'Fichier vide refusé'); } finally { fclose($empty); }
    echo "Transfert binaire, >50 Mo, mémoire, doublon, interruption et fichier vide : OK\n";
} finally { fclose($file); }
