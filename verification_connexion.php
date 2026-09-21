<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

$identifiant = trim($_POST['identifiant'] ?? '');
$mot_de_passe = $_POST['mot_de_passe'] ?? '';

if ($identifiant === '' || $mot_de_passe === '') {
    redirect('login.php?erreur=champs');
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, identifiant, mot_de_passe, role FROM utilisateurs WHERE identifiant = ?');
    $stmt->execute([$identifiant]);
    $user = $stmt->fetch();

    if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['identifiant'] = $user['identifiant'];
        $_SESSION['role'] = $user['role'];
        redirect('index.php');
    }
    redirect('login.php?erreur=identifiants');
} catch (PDOException $e) {
    redirect('login.php?erreur=identifiants');
}
