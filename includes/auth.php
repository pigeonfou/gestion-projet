<?php
if (session_status() === PHP_SESSION_NONE) {
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

function requerirAdmin(): void {
    requerirConnexion();
    if (!estAdmin()) {
        redirect('index.php?erreur=acces_refuse');
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
