<?php
/**
 * Sidebar des phases du projet
 * Variables attendues : $projet, $phaseActive
 */
$phases = [
    'cahier'      => ['label' => 'Exp. besoin & Cahier des charges', 'icon' => 'fa-file-alt'],
    'capacite'    => ['label' => 'Étude de capacité', 'icon' => 'fa-users-cog'],
    'investissement' => ['label' => 'Besoin investissement', 'icon' => 'fa-coins'],
    'proto'       => ['label' => 'Proto', 'icon' => 'fa-microchip'],
    'tests'       => ['label' => 'Tests conformité', 'icon' => 'fa-check-double'],
    'production'  => ['label' => 'Production', 'icon' => 'fa-industry'],
    'livraison'   => ['label' => 'Livraison', 'icon' => 'fa-truck'],
];
$pid = (int)($projet['id'] ?? 0);
$phaseActive = $phaseActive ?? 'cahier';
?>
<aside class="phase-sidebar">
    <div class="project-name">
        <i class="fas fa-folder-open"></i>
        <?= e($projet['nom'] ?? 'Projet') ?>
    </div>
    <ul class="phase-nav">
        <?php $i = 1; foreach ($phases as $key => $ph): ?>
        <li>
            <a href="<?= url('projet.php?id=' . $pid . '&phase=' . $key) ?>"
               class="<?= $phaseActive === $key ? 'active' : '' ?>">
                <span class="phase-num"><?= $i++ ?>.</span>
                <?= e($ph['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <div style="padding:1rem; margin-top:1rem; border-top:1px solid #334155;">
        <a href="<?= url('projets.php') ?>" style="color:#94a3b8;font-size:.8rem;text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Tous les projets
        </a>
    </div>
</aside>
