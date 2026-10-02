<?php
// Test isolé sans PHP.ini, sans connexion réseau ni accès à la base de production.
require_once __DIR__.'/../includes/NextcloudClient.php';
if (function_exists('curl_init')) {echo "SKIP : cURL chargé statiquement\n";exit(0);}
$r = new ReflectionClass(NextcloudClient::class);
$nc = $r->newInstanceWithoutConstructor();
foreach (['baseUrl'=>'https://example.invalid/dav','user'=>'fictif','password'=>'fictif'] as $key=>$value) {
    $r->getProperty($key)->setValue($nc,$value);
}
$result=$nc->testConnection();
if ($result['ok'] || !str_contains($result['message'],'cURL absente')) throw new RuntimeException('L’absence de cURL doit produire un message lisible.');
$result=$nc->uploadContent('projet/test.md','document de test');
if ($result['ok']) throw new RuntimeException('Un envoi sans cURL ne doit jamais être annoncé réussi.');
echo "OK : absence de cURL gérée sans erreur fatale, aucun appel réseau\n";
