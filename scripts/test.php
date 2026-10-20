<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=dirname(__DIR__);$failed=[];$linted=0;$tested=0;
function pfRunCheck(array $command): array {
    $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))return [1,'Process could not start'];
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$output];
}
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){
    if($file->getExtension()!=='php'||str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR))continue;
    [$code,$output]=pfRunCheck([PHP_BINARY,'-l',$file->getPathname()]);$linted++;
    if($code){$failed[]=$file->getPathname();echo $output;}
}
foreach(glob($root.'/tests/*.php') as $file){
    $flags=in_array(basename($file),['nextcloud_sans_curl.php','document_upload_stream.php'],true)?['-n']:[];
    [$code,$output]=pfRunCheck([PHP_BINARY,...$flags,$file]);$tested++;
    echo basename($file).': '.($code?'FAIL':'OK').PHP_EOL;
    if($code){$failed[]=$file;echo $output;}
}
$node=getenv('PROJECTFLOW_NODE_BINARY') ?: 'node';
foreach(glob($root.'/assets/js/*.js') as $file){[$code,$output]=pfRunCheck([$node,'--check',$file]);if($code){$failed[]=$file;echo $output;}}
[$code,$output]=pfRunCheck([$node,$root.'/tests/recode_js.js']);if($code){$failed[]='JavaScript fixtures';echo $output;}
echo "$linted PHP files linted; $tested suites; ".count($failed)." failures".PHP_EOL;
exit($failed?1:0);
