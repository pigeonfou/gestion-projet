<?php
/** Protection CSRF (token de session). */

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfVerify(?string $token = null): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $token ?? ($_POST['_csrf'] ?? '');
    $session = $_SESSION['_csrf'] ?? '';
    return is_string($token) && is_string($session) && $session !== '' && hash_equals($session, $token);
}

function csrfRequire(): void
{
    if (!csrfVerify()) {
        http_response_code(403);
        if (function_exists('setFlash')) {
            setFlash('error', 'Jeton de sécurité invalide ou expiré. Réessayez.');
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '') {
            header('Location: ' . $ref);
            exit;
        }
        if (function_exists('redirect')) {
            redirect('projets.php');
        }
        exit;
    }
}
