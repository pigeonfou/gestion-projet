<?php
// Used only by the isolated CLI-server integration suite; Apache uses .htaccess.
if(PHP_SAPI!=='cli-server'){http_response_code(403);exit;}
$root=dirname(__DIR__,2);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$path=preg_replace('#^/gestion-projet/#','/',$path);
$file=realpath($root.$path);
if(!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}
if(pathinfo($file,PATHINFO_EXTENSION)!=='php'){
    $mime=['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml'];
    header('Content-Type: '.($mime[pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;
}
$_SERVER['SCRIPT_NAME']='/gestion-projet/'.$path;require $file;
