<?php
putenv('PROJECTFLOW_DB_PATH=' . sys_get_temp_dir() . '/oddworks-appearance-' . getmypid() . '.sqlite');
require_once __DIR__ . '/../includes/settings_helper.php';

function checkAppearance(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = getDB();
$db->exec('CREATE TABLE utilisateurs (id INTEGER PRIMARY KEY, identifiant TEXT NOT NULL)');
$db->exec("INSERT INTO utilisateurs (id, identifiant) VALUES (1, 'alice'), (2, 'bob')");

$expected = [
    'Space', 'Carnet de laboratoire', 'Bauhaus industriel', 'Organique contemporain',
    'Grille typographique suisse', 'Arcade Y2K', 'Atelier Art déco', 'Roman graphique',
    'Bureau Studio', 'Atelier isométrique', 'Console de studio',
];
$presets = appearancePresets();
$newPresetLabels = array_values(array_map(static fn(array $preset): string => $preset['label'], array_diff_key($presets, ['classic' => true])));
checkAppearance($newPresetLabels === $expected, 'The eleven approved preset names and order are present.');
checkAppearance(validAppearancePreset('space') === 'space', 'Known presets are accepted.');
checkAppearance(validAppearancePreset('not-a-theme') === 'classic', 'Unknown presets safely fall back to classic.');

setUserPreference(1, 'ui_preset', 'space');
setUserPreference(1, 'ui_theme', 'dark');
checkAppearance(interfacePreset(1) === 'space', 'Preset selection is stored for the current user.');
checkAppearance(interfaceTheme(1) === 'dark', 'Color mode is stored independently for the current user.');
checkAppearance(interfacePreset(2) === 'classic', 'A second user retains the classic default.');
setUserPreference(1, 'ui_preset', 'atelier-art-deco');
checkAppearance(interfacePreset(1) === 'atelier-art-deco', 'User preset can be changed without replacing another user’s preference.');

$css = file_get_contents(__DIR__ . '/../assets/css/appearance-presets.css');
foreach (array_keys($presets) as $slug) {
    if ($slug === 'classic') continue;
    checkAppearance(str_contains($css, 'html[data-preset="' . $slug . '"]'), 'Theme stylesheet is missing ' . $slug . '.');
}

$path = getenv('PROJECTFLOW_DB_PATH');
unset($db);
foreach ([$path, $path . '-journal', $path . '-wal', $path . '-shm'] as $file) if (is_file($file)) unlink($file);
echo "Account appearance presets, persistence, isolation and CSS coverage: OK\n";
