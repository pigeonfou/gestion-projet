<?php
require_once __DIR__.'/../includes/cahier_specs.php';
require_once __DIR__.'/../includes/task_filters.php';
function checkTF(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$tasks=[
 ['id'=>1,'titre'=>'Étude PCB','description'=>'Réseau durci','resultats'=>'Essai validé','projet_id'=>3,'projet_nom'=>'ARV-8','assigne_a'=>'alice','priorite'=>'haute','statut'=>'en_cours','kanban_status'=>'validation','date_echeance'=>'2026-10-01'],
 ['id'=>2,'titre'=>'Boîtier','projet_id'=>2,'projet_nom'=>'Autre','assigne_a'=>null,'priorite'=>'urgente','statut'=>'a_faire','date_echeance'=>null],
 ['id'=>3,'titre'=>'Logiciel','projet_id'=>3,'projet_nom'=>'ARV-8','assigne_a'=>'bob','priorite'=>'basse','statut'=>'terminee','date_echeance'=>'2026-10-01'],
 ['id'=>4,'titre'=>'Test','projet_id'=>3,'assigne_a'=>'alice','priorite'=>'moyenne','statut'=>'en_cours','date_echeance'=>'2026-10-02'],
];
$run=static fn($input)=>taskFilterApply($tasks,taskFilterInput($input),'2026-10-02');
checkTF(array_column($run([]),'id')===[4,3,2,1],'Default newest first');
checkTF(array_column($run(['tf_q'=>'étude','tf_status'=>'validation','tf_assignee'=>'alice','tf_project'=>'3']),'id')===[1],'Case-insensitive Unicode search and combined filters, Kanban validation');
checkTF(count($run(['tf_q'=>'validé']))===1 && count($run(['tf_q'=>'ARV-8']))===2,'Results and project name searchable');
checkTF(array_column($run(['tf_due'=>'late']),'id')===[1],'Overdue excludes completed and today');
checkTF(array_column($run(['tf_due'=>'undated','tf_assignee'=>'__unassigned']),'id')===[2],'Unassigned and no deadline');
checkTF(array_column($run(['tf_from'=>'2026-10-01','tf_to'=>'2026-10-01']),'id')===[3,1],'Inclusive deadline range excludes undated');
checkTF(array_column($run(['tf_sort'=>'priority','tf_order'=>'desc']),'id')===[2,1,4,3],'Business priority order');
checkTF(array_column($run(['tf_sort'=>'deadline','tf_order'=>'asc']),'id')===[1,3,4,2],'Missing dates always last, stable ties');
checkTF(array_column($run(['tf_sort'=>'deadline','tf_order'=>'desc']),'id')===[4,3,1,2],'Missing dates last in descending sort');
checkTF(count($run(['tf_q'=>'%']))===0 && count($run(['tf_q'=>"' OR 1=1"]))===0,'Literal search, no SQL wildcard or injection');
$bad=taskFilterInput(['tf_q'=>[],'tf_status'=>[],'tf_sort'=>'drop table','tf_from'=>'2026-02-30']);
checkTF($bad['q']==='' && $bad['status']==='' && $bad['sort']==='id' && $bad['from']==='','Malformed parameters safely normalized');
checkTF(taskFilterApply([$tasks[1]],taskFilterInput(['tf_project'=>'3']))===[],'Filters cannot introduce tasks outside authorized input');
parse_str(taskFilterQuery(taskFilterInput(['tf_q'=>'PCB & réseau','tf_status'=>'validation'])),$roundtrip);
checkTF($roundtrip['tf_q']==='PCB & réseau' && $roundtrip['tf_status']==='validation','Query state roundtrip');
echo "Task search, filters, ordering and authorized scope: OK\n";
