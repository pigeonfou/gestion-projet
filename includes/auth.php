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

function requerirAccesProjet(int $projetId, bool $ecriture = true): void {
    requerirConnexion();
    if ($projetId <= 0) {
        setFlash('error', 'Projet invalide.');
        redirect('projets.php');
    }
    if (estAdmin()) return;
    $stmt = getDB()->prepare('SELECT createur_id FROM projets WHERE id = ?');
    $stmt->execute([$projetId]);
    $createurId = $stmt->fetchColumn();
    if ($createurId === false || (int)$createurId !== (int)($_SESSION['user_id'] ?? 0)) {
        setFlash('error', $ecriture ? 'Vous n\'êtes pas autorisé à modifier ce projet.' : 'Accès refusé.');
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
