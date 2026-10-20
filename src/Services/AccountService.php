<?php
require_once __DIR__.'/Transaction.php';
function pfValidateAccount(string $login, string $password, bool $required, string $name, string $function, string $skills): void {
    if (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/D',$login)) throw new InvalidArgumentException('Identifiant : 1 à 50 lettres ASCII, chiffres, points, tirets ou soulignements.');
    if (($required || $password!=='') && (strlen($password)<12 || strlen($password)>72)) throw new InvalidArgumentException('Mot de passe : 12 à 72 octets.');
    if (strlen($name)>200 || strlen($function)>300 || strlen($skills)>2000) throw new InvalidArgumentException('Profil trop long.');
}

function pfSaveAccount(PDO $db,int $id,array $p): int {
    return pfTransaction($db,function () use ($db,$id,$p) {
        $login=$p['identifiant'];$password=$p['mot_de_passe'];$role=$p['role'];
        pfValidateAccount($login,$password,$id===0,$p['nom_affiche'],$p['fonction'],$p['competences']);
        if (!in_array($role,['admin','utilisateur'],true)) throw new InvalidArgumentException('Rôle invalide.');
        $q=$db->prepare('SELECT id FROM utilisateurs WHERE lower(identifiant)=lower(?) AND id<>?');$q->execute([$login,$id]);
        if ($q->fetchColumn()) throw new InvalidArgumentException('Cet identifiant existe déjà.');
        $q=$db->prepare('SELECT identifiant,role,mot_de_passe FROM utilisateurs WHERE id=?');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if ($id && !$old) throw new InvalidArgumentException('Utilisateur introuvable.');
        if ($old && $old['role']==='admin' && $role!=='admin' && (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE role='admin'")->fetchColumn()<=1) throw new InvalidArgumentException('Le dernier administrateur doit être conservé.');
        $hash=$password!==''?password_hash($password,PASSWORD_DEFAULT):($old['mot_de_passe'] ?? '');
        $values=[$login,$hash,$role,$p['nom_affiche'],$p['fonction'],$p['competences']];
        if ($id) {
            $db->prepare('UPDATE utilisateurs SET identifiant=?,mot_de_passe=?,role=?,nom_affiche=?,fonction=?,competences=? WHERE id=?')->execute([...$values,$id]);
            if ($old['identifiant']!==$login) {
                $db->prepare('UPDATE taches SET assigne_a=? WHERE assigne_a=?')->execute([$login,$old['identifiant']]);
                foreach ($db->query('SELECT id,specs_json FROM cahiers WHERE specs_json IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $data=json_decode($row['specs_json'],true,512,JSON_THROW_ON_ERROR);$changed=false;
                    foreach ($data['composants_st'] ?? [] as $key=>$items) foreach ($items as $i=>$item) if (($item['affectation'] ?? '')===$old['identifiant']) {
                        $data['composants_st'][$key][$i]['affectation']=$login;$changed=true;
                    }
                    if ($changed) $db->prepare('UPDATE cahiers SET specs_json=? WHERE id=?')->execute([json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$row['id']]);
                }
            }
        } else {
            $db->prepare('INSERT INTO utilisateurs(identifiant,mot_de_passe,role,nom_affiche,fonction,competences) VALUES(?,?,?,?,?,?)')->execute($values);
            $id=(int)$db->lastInsertId();
        }
        return $id;
    });
}
