<?php
require_once __DIR__.'/Transaction.php';
require_once __DIR__.'/../../includes/r1b_steps.php';
require_once __DIR__.'/../../includes/r1b_decisions.php';
require_once __DIR__.'/../../includes/decision_dashboard.php';
require_once __DIR__.'/../../includes/project_notes.php';
require_once __DIR__.'/../Domain/StructuredIdentity.php';
require_once __DIR__.'/../../includes/project_members.php';

function pfApplyR1bDecision(PDO $db,int $id,int $currentStep,string $decision,string $motif,array $user,string $expected): int {
    return pfTransaction($db,function () use ($db,$id,$currentStep,$decision,$motif,$user,$expected) {
        $q=$db->prepare('SELECT * FROM projets WHERE id=?');$q->execute([$id]);$projet=$q->fetch(PDO::FETCH_ASSOC);
        if(!$projet || !projectCanManage($db,$id,$user)) throw new InvalidArgumentException('Décision non autorisée.');
        if(!hash_equals(pfProjectRevision($projet),$expected)) throw new InvalidArgumentException('Le projet a changé. Rechargez la page.');
        $progressStep=r1bClampStep((int)$projet['current_step']);
        $validatedSteps=json_decode($projet['validated_steps'] ?? '[]',true,512,JSON_THROW_ON_ERROR);
        if(!$validatedSteps && $progressStep>1)$validatedSteps=range(1,$progressStep-1);
        if(!in_array($decision,r1bAllowedDecisions($currentStep),true) || $currentStep>$progressStep) throw new InvalidArgumentException('Décision invalide pour cette étape.');
        if(strlen($motif)>10000 || (in_array($decision,['NO_GO','NON_CONFORME','REFUSE'],true) && trim($motif)==='')) throw new InvalidArgumentException('Motif de refus obligatoire, 10 000 octets maximum.');
        if (in_array($decision, ['GO', 'NO_GO', 'CONFORME', 'NON_CONFORME', 'DONE', 'REFUSE'], true)) {
            if ($decision === 'NO_GO') {
                // Archivage étape 3 (abandon) — reste à l'étape 3, ne passe PAS à l'étape 8
                $db->prepare('UPDATE projets SET go_decision = ?, current_step = 3, status = ? WHERE id = ?')
                   ->execute(['NO_GO', 'archive', $id]);
            } elseif ($decision === 'GO') {
                $validated = $validatedSteps; $validated[] = 3;
                $validated = array_values(array_unique(array_map('intval', $validated))); sort($validated);
                $db->prepare('UPDATE projets SET go_decision = ?, current_step = ?, status = ?, validated_steps = ? WHERE id = ?')
                   ->execute(['GO', max($progressStep, 4), 'actif', json_encode($validated), $id]);
            } elseif ($decision === 'CONFORME') {
                // Le bouton Conforme est l'acte explicite de validation des étapes 7/8.
                $validated = $validatedSteps; $validated[] = $currentStep;
                $validated = array_values(array_unique(array_map('intval', $validated))); sort($validated);
                $validatedJson = json_encode($validated);
                $next = max($progressStep, r1bClampStep($currentStep + 1));
                if ($currentStep >= 8 || $next === 9) {
                    $db->prepare('UPDATE projets SET current_step = 9, status = ?, validated_steps = ? WHERE id = ?')
                       ->execute(['termine', $validatedJson, $id]);
                } else {
                    $db->prepare('UPDATE projets SET current_step = ?, status = ?, validated_steps = ? WHERE id = ?')
                       ->execute([$next, 'actif', $validatedJson, $id]);
                }
            } elseif ($decision === 'DONE') {
                $validated = $validatedSteps;
                $validated[] = $currentStep;
                $validated = array_values(array_unique(array_map('intval', $validated)));
                sort($validated);
                $validatedJson = json_encode($validated);
                // L'étape 5 (Achats) a été insérée après l'étape 4.
                // Elle doit être réellement parcourue, y compris pour un projet ancien
                // dont current_step avait déjà été décalé vers 6+ par la migration.
                $next = ($currentStep === 4)
                    ? 5
                    : max($progressStep, r1bClampStep($currentStep + 1));
                if ($next === 9) {
                    $db->prepare('UPDATE projets SET current_step = 9, status = ?, validated_steps = ? WHERE id = ?')
                       ->execute(['termine', $validatedJson, $id]);
                } else {
                    $db->prepare('UPDATE projets SET current_step = ?, validated_steps = ? WHERE id = ?')->execute([$next, $validatedJson, $id]);
                }
            } elseif ($decision === 'NON_CONFORME' || $decision === 'REFUSE') {
                $refus = r1bRefusalState($currentStep, $validatedSteps);
                $db->prepare("UPDATE projets SET current_step = ?, validated_steps = ?, status = 'actif' WHERE id = ?")
                    ->execute([$refus['step'], json_encode($refus['validated']), $id]);
            }
        }
        $db->prepare('INSERT INTO projet_decisions(projet_id,etape,decision,motif,utilisateur_id,snapshot_json) VALUES(?,?,?,?,?,?)')
            ->execute([$id,$currentStep,$decision,$motif,$user['id'], $currentStep === 3 ? ddSnapshot(loadSpecs(getOrCreateCahierId($id)), $projet, projectNotes($db,$id)) : null]);

        $q->execute([$id]);return r1bClampStep((int)$q->fetch(PDO::FETCH_ASSOC)['current_step']);
    });
}

