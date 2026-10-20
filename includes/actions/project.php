<?php
// Context: validated project access, PDO, actor, current/progress steps and return context.
// ——— Actions POST ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? '';
    if ($action !== 'update_task_status') {
        requerirGestionProjet($id);
    }
    if ($action === 'manage_note' || $action === 'save_notes') {
        try {
            if ($action === 'manage_note') {
                changeProjectNote($db,$id,$currentStep,(string)($_POST['note_action']??''),(string)($_POST['note_content']??''),(int)($_POST['note_id']??0),(int)($_POST['note_revision']??0));
            } elseif (trim((string)($_POST['step_notes']??''))!=='') {
                changeProjectNote($db,$id,$currentStep,'add',(string)$_POST['step_notes']);
            }
            if ($currentStep === 1 && !empty($_POST['save_cadrage_with_notes'])) {
                $dest=$_POST['cadrage_destination']??null;
                if(!in_array($dest,['interne','externe'],true))$dest=null;
                $db->prepare('UPDATE projets SET cadrage_commerciale=?,cadrage_technique=?,cadrage_destination=? WHERE id=?')->execute([!empty($_POST['cadrage_commerciale'])?1:0,!empty($_POST['cadrage_technique'])?1:0,$dest,$id]);
            }
            setFlash('success','Notes et informations enregistrées.');
        } catch(Throwable $e) { setFlash('error',$e->getMessage()); }
        redirect('projet.php?id='.$id.'&view=processus&step='.$currentStep);
    }
    if ($action === 'set_step') {
        // La navigation ne valide ni ne modifie l'avancement du projet.
        $n = r1bClampStep((int)($_POST['step'] ?? $progressStep));
        redirect('projet.php?id=' . $id . '&view=processus&step=' . $n);
    }
    if ($action === 'save_cadrage') {
        $comm = !empty($_POST['cadrage_commerciale']) ? 1 : 0;
        $tech = !empty($_POST['cadrage_technique']) ? 1 : 0;
        $dest = $_POST['cadrage_destination'] ?? '';
        if (!in_array($dest, ['interne', 'externe'], true)) $dest = null;
        $db->prepare('UPDATE projets SET cadrage_commerciale = ?, cadrage_technique = ?, cadrage_destination = ? WHERE id = ?')
           ->execute([$comm, $tech, $dest, $id]);
        setFlash('success', 'Note de cadrage enregistrée.');
        redirect('projet.php?id=' . $id . '&view=processus&step=1');
    }
    if ($action === 'save_specs_techniques') {
        try {
            $cid = getOrCreateCahierId($id);
            pfTransaction($db, function () use ($db, $cid, $id) {
            pfAssertSpecsRevision($cid, $_POST['specs_revision'] ?? null);
            $techniques = parseSpecsTechniquesFromPost($_POST);
            saveSpecsTechniques($cid, $techniques, is_array($_POST['st_deleted_parent_uid'] ?? null) ? $_POST['st_deleted_parent_uid'] : []);
            $techniques = loadSpecs($cid)['specs_techniques'];

            // À l'étape 2, toutes les S.T. (Matériel, Composant, Prestataire, Logiciel, 3D, PCB)
            // sont synchronisées comme tâches.
            $fonctions = loadSpecs($cid)['fonctions'] ?? [];
            syncTasksFromStructuredSpecs(
                $id,
                is_array($fonctions) ? $fonctions : [],
                $techniques
            );

            syncStockUsagesFromComposants($id, loadSpecs($cid)['composants_st'] ?? []);
            $n = count($techniques);
            setFlash('success', $n > 0
                ? ("Spécifications techniques enregistrées ($n ligne(s)).")
                : 'Aucune ligne avec description : rien à enregistrer. Saisissez une description pour chaque S.T.');
            });
        } catch (Throwable $e) {
            setFlash('error', 'Erreur d\'enregistrement des S.T. : ' . $e->getMessage());
        }
        redirect('projet.php?id=' . $id . '&view=processus&step=2');
    }
    if ($action === 'save_composants_st') {
        try {
            $cid = getOrCreateCahierId($id);
            pfTransaction($db, function () use ($db, $cid, $id) {
            pfAssertSpecsRevision($cid, $_POST['specs_revision'] ?? null);
            $composants = parseComposantsStFromPost($_POST);
            saveComposantsSt($cid, $composants);
            $composants = loadSpecs($cid)['composants_st'];
            $techs = loadSpecs($cid)['specs_techniques'] ?? [];
            syncTasksFromComposants($id, $composants, is_array($techs) ? $techs : []);
            syncStockUsagesFromComposants($id, $composants);
            $n = 0;
            foreach ($composants as $items) { $n += count($items); }
            setFlash('success', $n > 0
                ? ("Composants / affectations enregistrés ($n ligne(s)). Tâches Logiciel/3D/PCB synchronisées.")
                : 'Aucune ligne renseignée à enregistrer.');
            });
        } catch (Throwable $e) {
            setFlash('error', 'Erreur d\'enregistrement : ' . $e->getMessage());
        }
        redirect('projet.php?id=' . $id . '&view=processus&step=4');
    }
    if ($action === 'create_purchase_tasks') {
        try {
            ensureTachesExtendedColumns();
            $selected = $_POST['purchase_create'] ?? [];
            $assignees = $_POST['purchase_assignee'] ?? [];
            $payloads = $_POST['purchase_payload'] ?? [];
            if (!is_array($selected)) $selected = [];
            if (!is_array($assignees)) $assignees = [];
            if (!is_array($payloads)) $payloads = [];

            $validUsers = $db->query('SELECT identifiant FROM utilisateurs')->fetchAll(PDO::FETCH_COLUMN);
            $validUsers = array_flip(array_map('strval', $validUsers));
            $ins = $db->prepare('INSERT INTO taches (projet_id, titre, description, priorite, statut, assigne_a, source_key, kanban_status, source_uid) VALUES (?,?,?,?,?,?,?,?,?)');
            $upd = $db->prepare('UPDATE taches SET titre=?, description=?, assigne_a=?,source_key=?,source_uid=? WHERE projet_id=? AND source_uid=?');
            $find = $db->prepare('SELECT id FROM taches WHERE projet_id=? AND source_uid=? LIMIT 1');
            $db->beginTransaction();
            $cid=getOrCreateCahierId($id);pfAssertSpecsRevision($cid,$_POST['specs_revision'] ?? null);
            $purchaseSpecs=loadSpecs($cid);$purchaseSt=array_column($purchaseSpecs['specs_techniques'],null,'id');
            pfBackfillTaskIdentities($db,$id,$purchaseSpecs);
            $created = 0; $updated = 0;

            foreach ($selected as $key) {
                $key = (string)$key;
                if (!isset($payloads[$key])) continue;
                $p = json_decode(base64_decode((string)$payloads[$key], true) ?: '', true);
                if (!is_array($p)) continue;
                $stId = trim((string)($p['st_id'] ?? ''));
                $itemId = trim((string)($p['item_id'] ?? ''));
                if ($stId === '' || $itemId === '') continue;
                $st=$purchaseSt[$stId] ?? null;
                $item=null;foreach($purchaseSpecs['composants_st'][$stId] ?? [] as $candidate)if(($candidate['id'] ?? '')===$itemId)$item=$candidate;
                if(!$st||!$item||!stTypeHasCost($st['type']))throw new InvalidArgumentException('Ligne d’achat inconnue. Rechargez la page.');
                $p=array_merge($item,['st_id'=>$stId,'item_id'=>$itemId]);
                $sourceUid='achat:'.$st['uid'].':'.pfIdentity($item['uid'] ?? ($purchaseSpecs['component_identities'][$st['uid']][$itemId] ?? null),$st['uid'].':'.$itemId);
                $sourceKey = 'achat:' . $id . ':' . $stId . ':' . $itemId;
                $assignee = trim((string)($assignees[$key] ?? ''));
                if ($assignee !== '' && !isset($validUsers[$assignee])) $assignee = '';

                $designation = trim((string)($p['designation'] ?? ''));
                $reference = trim((string)($p['reference'] ?? ''));
                $fournisseur = trim((string)($p['fournisseur'] ?? ''));
                $qty = (float)($p['quantite'] ?? 0);
                $cu = (float)($p['cout_unitaire'] ?? 0);
                $tax = (($p['cout_unitaire_taxe'] ?? 'HT') === 'TTC') ? 'TTC' : 'HT';
                $total = round($qty * $cu, 2);
                $titre = '[ACHAT ' . $stId . '/' . $itemId . '] ' . ($designation !== '' ? $designation : ($reference !== '' ? $reference : 'Composant / matériel'));
                $description = implode("\n", array_filter([
                    'S.T. : ' . $stId,
                    'Ligne : ' . $itemId,
                    $reference !== '' ? 'Référence : ' . $reference : '',
                    $fournisseur !== '' ? 'Fournisseur : ' . $fournisseur : '',
                    'Quantité : ' . rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'),
                    'Coût unitaire : ' . number_format($cu, 2, ',', ' ') . ' € ' . $tax,
                    'Coût total estimé : ' . number_format($total, 2, ',', ' ') . ' € ' . $tax,
                    isset($p['delai_jours']) ? 'Délai : ' . $p['delai_jours'] . ' j' : '',
                ]));

                $find->execute([$id, $sourceUid]);
                if ($find->fetchColumn()) {
                    $upd->execute([$titre, $description, $assignee !== '' ? $assignee : null, $sourceKey, $sourceUid, $id, $sourceUid]);
                    $updated++;
                } else {
                    $ins->execute([$id, $titre, $description, 'moyenne', 'a_faire', $assignee !== '' ? $assignee : null, $sourceKey, 'a_faire', $sourceUid]);
                    $created++;
                }
            }
            $db->commit();
            setFlash('success', "Tâches d'achat : $created créée(s), $updated mise(s) à jour.");
        } catch (Throwable $e) {
            if($db->inTransaction())$db->rollBack();
            setFlash('error', "Erreur lors de la génération des tâches d'achat : " . $e->getMessage());
        }
        redirect('projet.php?id=' . $id . '&view=processus&step=5');
    }
    if (($_POST['action'] ?? '') === 'update_task_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfRequire();
        $aliases = ['todo'=>'a_faire','in_progress'=>'en_cours','done'=>'terminee'];
        $status = (string)($_POST['status'] ?? 'a_faire');
        $status = $aliases[$status] ?? $status;
        try {
            taskSetStatus($db, (int)($_POST['task_id'] ?? 0), $status, $user, $id);
            setFlash('success', 'Statut de la tâche mis à jour.');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
        } catch (Throwable $e) {
            setFlash('error', 'Statut non enregistré.');
        }
        redirect('projet.php?id=' . $id . '&view=taches&'.taskFilterQuery(taskFilterInput($_GET)));
    }
    if ($action === 'decide') {
        require_once __DIR__.'/../../src/Services/R1bDecisionService.php';
        try {
            $step=pfApplyR1bDecision($db,$id,$currentStep,(string)($_POST['decision'] ?? ''),trim((string)($_POST['motif'] ?? $projet['step_notes'] ?? '')),$user,(string)($_POST['project_revision'] ?? ''));
            setFlash('success','Décision enregistrée.');
            redirect('projet.php?id='.$id.'&view=processus&step='.$step);
        } catch(Throwable $e) {
            error_log('ProjectFlow R1b: '.$e->getMessage());
            setFlash('error',$e instanceof InvalidArgumentException ? $e->getMessage() : 'Décision non enregistrée. Consultez le journal serveur.');
            redirect('projet.php?id='.$id.'&view=processus&step='.$currentStep);
        }
    }
}
