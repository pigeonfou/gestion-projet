<?php
require __DIR__.'/../includes/project_notes.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE projets(id INTEGER PRIMARY KEY,step_notes TEXT);INSERT INTO projets VALUES(1,'Texte antérieur'),(2,'Autre projet')");
function checkNote(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
initProjectNotes($db,1);initProjectNotes($db,1);checkNote(count(projectNotes($db,1))===1,'Import unique');
checkNote(projectNotes($db,1)[0]['created_at']===null,'Pas de date inventée');
changeProjectNote($db,1,4,'add','Nouvelle note');$rows=projectNotes($db,1);$n=$rows[1];checkNote(count($rows)===2&&$n['created_at']!==null&&$n['etape']===4,'Ajout daté');
try{changeProjectNote($db,2,4,'edit','Injection',$n['id'],1);throw new LogicException('Isolation absente');}catch(RuntimeException $e){}
changeProjectNote($db,1,4,'edit','Correction',$n['id'],1);$n=projectNotes($db,1)[1];checkNote($n['contenu']==='Correction'&&$n['updated_at']!==null,'Modification');
try{changeProjectNote($db,1,4,'edit','Perte concurrente',$n['id'],1);throw new LogicException('Revision absente');}catch(RuntimeException $e){}
changeProjectNote($db,1,4,'delete','',$n['id'],2);checkNote(count(projectNotes($db,1))===1&&count(projectNotes($db,1,true))===2,'Suppression restaurable');
changeProjectNote($db,1,4,'restore','',$n['id'],3);checkNote(count(projectNotes($db,1))===2,'Restauration');
checkNote(str_contains($db->query('SELECT step_notes FROM projets WHERE id=1')->fetchColumn(),'Correction'),'Compatibilité');
try{changeProjectNote($db,1,4,'add',' ');throw new LogicException('Vide accepté');}catch(InvalidArgumentException $e){}
echo "Notes : import, dates, isolation, modification, concurrence, suppression et restauration OK\n";
