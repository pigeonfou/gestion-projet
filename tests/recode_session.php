<?php
require_once __DIR__.'/../src/Security/Session.php';pfStartSession();
$db=new PDO('sqlite::memory:');$db->exec("CREATE TABLE utilisateurs(id INTEGER PRIMARY KEY,identifiant TEXT,role TEXT,mot_de_passe TEXT);INSERT INTO utilisateurs VALUES(1,'admin','admin','hash1')");
$_SESSION=['user_id'=>1,'auth_fingerprint'=>hash('sha256','hash1')];
if(!pfRefreshSession($db)||$_SESSION['role']!=='admin')throw new RuntimeException('Session not loaded');
$db->exec("UPDATE utilisateurs SET role='utilisateur',identifiant='new' WHERE id=1");
if(!pfRefreshSession($db)||$_SESSION['role']!=='utilisateur'||$_SESSION['identifiant']!=='new')throw new RuntimeException('Stale privileges');
$db->exec("UPDATE utilisateurs SET mot_de_passe='hash2' WHERE id=1");
if(pfRefreshSession($db)||isset($_SESSION['user_id']))throw new RuntimeException('Password reset failed to revoke');
$params=session_get_cookie_params();if(!$params['httponly']||$params['samesite']!=='Lax'||ini_get('session.use_strict_mode')!=='1')throw new RuntimeException('Session settings missing');
echo "Recode session: cookie settings, role/name refresh and password revocation OK\n";
