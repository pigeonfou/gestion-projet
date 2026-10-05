<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/task_assignment.php';
$db=getDB();
$db->beginTransaction();
try {
    foreach ($db->query('SELECT projet_id,specs_json FROM cahiers')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $specs=json_decode($row['specs_json']??'',true);
        if (is_array($specs['composants_st']??null)) taskSyncMainAssignments($db,(int)$row['projet_id'],$specs['composants_st'],true);
    }
    $db->commit();
    echo "Affectations principales manquantes rétablies ; responsables existants conservés.\n";
} catch(Throwable $error) { if($db->inTransaction())$db->rollBack(); throw $error; }
