<?php
function pfStartSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Password changes revoke existing sessions; role/name changes are read immediately. */
function pfRefreshSession(PDO $db): bool {
    if (empty($_SESSION['user_id'])) return false;
    $q = $db->prepare('SELECT id,identifiant,role,mot_de_passe FROM utilisateurs WHERE id=?');
    $q->execute([(int)$_SESSION['user_id']]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    $fingerprint = $user ? hash('sha256', $user['mot_de_passe']) : '';
    if (!$user || !isset($_SESSION['auth_fingerprint']) || !hash_equals($_SESSION['auth_fingerprint'], $fingerprint)) {
        unset($_SESSION['user_id'], $_SESSION['identifiant'], $_SESSION['role'], $_SESSION['auth_fingerprint']);
        session_regenerate_id(true);
        return false;
    }
    $_SESSION['identifiant'] = $user['identifiant'];
    $_SESSION['role'] = $user['role'];
    return true;
}
