<?php
/** Nested operations use savepoints; only the outer operation commits. */
function pfTransaction(PDO $db, callable $operation) {
    static $sequence = 0;
    $nested = $db->inTransaction();
    $savepoint = 'pf_' . ++$sequence;
    if ($nested) $db->exec('SAVEPOINT ' . $savepoint);
    else $db->beginTransaction();
    try {
        $result = $operation();
        if ($nested) $db->exec('RELEASE SAVEPOINT ' . $savepoint);
        else $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($nested && $db->inTransaction()) {
            $db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $db->exec('RELEASE SAVEPOINT ' . $savepoint);
        } elseif ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
