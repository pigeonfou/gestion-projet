<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(count($argv)!==3){fwrite(STDERR,"Usage: php backup_database.php SOURCE DESTINATION\n");exit(1);}
umask(0077);$source=$argv[1];$target=$argv[2];
if(!is_file($source)||realpath($source)===realpath($target)||file_exists($target)){fwrite(STDERR,"Source absente ou destination déjà présente.\n");exit(1);}
$created=false;
try {
    $file=fopen($target,'x');if(!$file)throw new RuntimeException('Destination impossible.');fclose($file);$created=true;
    $input=new SQLite3($source,SQLITE3_OPEN_READONLY);$output=new SQLite3($target,SQLITE3_OPEN_READWRITE);
    if(!$input->backup($output))throw new RuntimeException('Sauvegarde SQLite impossible.');
    if($output->querySingle('PRAGMA quick_check')!=='ok')throw new RuntimeException('Sauvegarde invalide.');
    $output->close();$input->close();chmod($target,0600);echo "Sauvegarde SQLite vérifiée.\n";
}catch(Throwable $e){if(isset($output))$output->close();if(isset($input))$input->close();if($created)@unlink($target);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
