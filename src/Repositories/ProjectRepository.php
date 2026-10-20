<?php
function pfFindProject(PDO $db,int $id): ?array {
    $q=$db->prepare('SELECT p.*,u.identifiant AS createur FROM projets p JOIN utilisateurs u ON u.id=p.createur_id WHERE p.id=?');
    $q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
