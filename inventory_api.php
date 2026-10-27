<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/stock/catalogue.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function inventoryReply(array $data,int $status=200): never {http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!estConnecte())inventoryReply(['error'=>'Session expirée. Reconnectez-vous à OddWorks.'],401);
$db=getDB();$db->exec('PRAGMA busy_timeout=5000');inventorySchema($db);$actor=utilisateurCourant();
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4000000)inventoryReply(['error'=>'Requête trop volumineuse.'],413);
        $p=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($p)||!csrfVerify($p['_csrf']??null))inventoryReply(['error'=>'Jeton de sécurité invalide. Rechargez la page.'],403);
        $action=invText($p,'action',60,true);
        if($action==='reserve'||$action==='release'){
            $pid=(int)($p['projet_id']??0);
            if($action==='release')$pid=(int)(invOne($db,'SELECT projet_id FROM stock_reservations WHERE id=?',[(int)($p['id']??0)])['projet_id']??0);
            if(!projectCanContribute($db,$pid,$actor))inventoryReply(['error'=>'Vous ne pouvez pas modifier les réservations de ce projet.'],403);
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            if(!hash_equals(invRevision($db),invText($p,'revision',64,true))){$db->exec('ROLLBACK');inventoryReply(['error'=>'Le stock a changé depuis votre ouverture. Rechargez les données puis recommencez.'],409);}
            $id=invCommand($db,$action,$p,(int)$actor['id'],estAdmin());$db->exec('COMMIT');
        }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    }elseif($_SERVER['REQUEST_METHOD']!=='GET')inventoryReply(['error'=>'Méthode refusée.'],405);
    // One consistent read snapshot for rows and optimistic revision.
    $db->beginTransaction();$state=invState($db);$db->commit();
    $state['projects']=array_values(array_filter(invRows($db,'SELECT id,nom,status FROM projets ORDER BY nom'),fn($p)=>projectCanContribute($db,(int)$p['id'],$actor)));
    $state['csrf']=csrfToken();$state['result_id']=$id??null;inventoryReply($state);
}catch(InvalidArgumentException|JsonException $e){inventoryReply(['error'=>$e->getMessage()],422);}
catch(PDOException $e){error_log('OddWorks inventory: '.$e->getMessage());inventoryReply(['error'=>'Enregistrement refusé : référence déjà utilisée, relation invalide ou base occupée. Vos données restent conservées.'],409);}
catch(Throwable $e){error_log('OddWorks inventory: '.$e->getMessage());inventoryReply(['error'=>'Erreur interne de l’inventaire. Consultez le journal du serveur.'],500);}
