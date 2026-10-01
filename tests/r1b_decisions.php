<?php
require_once __DIR__ . '/../includes/r1b_decisions.php';
foreach ([1,2,4,5,6] as $step) {
    if (r1bAllowedDecisions($step) !== ['DONE','REFUSE']) throw new RuntimeException('Refus absent');
}
if (r1bAllowedDecisions(3) !== ['GO','NO_GO'] || r1bAllowedDecisions(7) !== ['CONFORME','NON_CONFORME'] || r1bAllowedDecisions(9) !== []) throw new RuntimeException('Décisions incorrectes');
if (r1bRefusalState(1, [1,2]) !== ['step'=>1,'validated'=>[]]) throw new RuntimeException('Retour avant première étape');
if (r1bRefusalState(7, [1,2,3,4,5,6,7,8]) !== ['step'=>6,'validated'=>[1,2,3,4,5]]) throw new RuntimeException('Validations aval non invalidées');
echo "OK : décisions par étape, retour en correction et invalidation aval\n";
