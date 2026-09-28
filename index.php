<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!estConnecte()) {
    redirect('login.php');
}
redirect('projets.php');
