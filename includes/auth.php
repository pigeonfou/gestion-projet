<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
require_once __DIR__ . '/../config/db.php';

function estConnecte(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requerirConnexion(): void {
    if (!estConnecte()) {
        redirect('login.php');
    }
}

function estAdmin(): bool {
    return estConnecte() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

require_once __DIR__.'/project_members.php';
function requerirAccesProjet(int $projetId, bool $ecriture = true): void {
    requerirConnexion();
    if (!projectCanContribute(getDB(),$projetId,utilisateurCourant())) {
        setFlash('error', $ecriture ? 'Contribution non autorisée pour ce projet.' : 'Accès refusé.');
        redirect('projets.php');
    }
}
function requerirGestionProjet(int $projetId): void {
    requerirConnexion();
    if (!projectCanManage(getDB(),$projetId,utilisateurCourant())) {
        setFlash('error','Seul le créateur ou un administrateur peut piloter ce projet.');
        redirect('projets.php');
    }
}

function requerirAdmin(): void {
    requerirConnexion();
    if (!estAdmin()) {
        redirect('projets.php?erreur=acces_refuse');
    }
}

function utilisateurCourant(): ?array {
    if (!estConnecte()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'identifiant' => $_SESSION['identifiant'] ?? '',
        'role' => $_SESSION['role'] ?? 'utilisateur'
    ];
}

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
