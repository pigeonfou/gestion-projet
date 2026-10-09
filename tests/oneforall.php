<?php
// Targeted OneForAll regressions. Run with PDO SQLite enabled.
putenv('PROJECTFLOW_BASE_PATH=/');
require __DIR__ . '/../config/config.php';
if (BASE_PATH !== '' || url('login.php') !== '/login.php') {
    throw new RuntimeException('Root deployment URL failed');
}
$directory = sys_get_temp_dir() . '/oneforall-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$path = $directory . '/database.sqlite';
putenv('PROJECTFLOW_DB_PATH=' . $path);
putenv('PROJECTFLOW_ADMIN_PASSWORD=OneForAll-Test-Password-Only');
try {
    ob_start();
    require __DIR__ . '/../install/init_database.php';
    $output = ob_get_clean();
    if (str_contains($output, 'OneForAll-Test-Password-Only')) {
        throw new RuntimeException('Admin secret appeared in output');
    }
    $db = new PDO('sqlite:' . $path);
    $hash = $db->query("SELECT mot_de_passe FROM utilisateurs WHERE identifiant='admin'")->fetchColumn();
    if (!password_verify('OneForAll-Test-Password-Only', $hash)) {
        throw new RuntimeException('Admin password was not preserved');
    }
    $db = null;
    echo "OneForAll URL and admin-secret regressions OK\n";
} finally {
    unlink($path);
    rmdir($directory);
}
