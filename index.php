<?php
require_once __DIR__ . '/includes/auth.php';
if (!estConnecte()) {
    redirect('login.php');
}
redirect('projets.php');
