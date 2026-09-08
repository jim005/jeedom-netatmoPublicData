<?php
/**
 * Vérifie que vendor/ correspond bien à composer.lock.
 *
 * Ce contrôle existe à cause d'une panne réelle : Dependabot met à jour composer.lock
 * mais jamais le vendor/ versionné. Les deux arbres ont divergé (lock en Guzzle 7.15.2,
 * vendor/ resté en 7.7.0) et, le lock verrouillant une dépendance exigeant PHP >= 8.1,
 * l'installation des dépendances par Jeedom a échoué sur les installations en PHP 7.4.
 *
 * La comparaison porte sur les noms et versions des paquets, et non sur le contenu des
 * fichiers : selon que Composer installe depuis une archive dist ou depuis les sources,
 * les arbres diffèrent légitimement (tests et docs exclus en dist).
 */

$root = dirname(__DIR__, 2);
$lockFile = $root . '/composer.lock';
$installedFile = $root . '/vendor/composer/installed.json';

function fail($message)
{
    fwrite(STDERR, "\n✗ " . $message . "\n");
    fwrite(STDERR, "\nPour corriger : lancer le workflow « Intégrité des dépendances Composer »\n"
        . "en mode workflow_dispatch sur cette branche, ou en local :\n\n"
        . "    composer install --no-dev --no-plugins --no-scripts --optimize-autoloader\n"
        . "    git add composer.lock vendor && git commit\n\n");
    exit(1);
}

foreach ([$lockFile, $installedFile] as $path) {
    if (!is_file($path)) {
        fail('Fichier introuvable : ' . substr($path, strlen($root) + 1));
    }
}

/**
 * @return array<string, string> nom du paquet => version
 */
function readPackages($path, $key)
{
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        fail('JSON illisible : ' . $path);
    }
    $packages = $key === null ? $data : (isset($data[$key]) ? $data[$key] : null);
    if (!is_array($packages)) {
        fail('Structure inattendue dans ' . $path);
    }

    $out = array();
    foreach ($packages as $package) {
        if (isset($package['name'], $package['version'])) {
            $out[$package['name']] = $package['version'];
        }
    }
    ksort($out);

    return $out;
}

$lock = readPackages($lockFile, 'packages');
$installedRaw = json_decode(file_get_contents($installedFile), true);
$installed = readPackages($installedFile, isset($installedRaw['packages']) ? 'packages' : null);

$errors = array();

foreach ($lock as $name => $version) {
    if (!isset($installed[$name])) {
        $errors[] = sprintf('%-40s absent de vendor/ (lock : %s)', $name, $version);
    } elseif ($installed[$name] !== $version) {
        $errors[] = sprintf('%-40s vendor/ : %-12s lock : %s', $name, $installed[$name], $version);
    }
}

foreach ($installed as $name => $version) {
    if (!isset($lock[$name])) {
        $errors[] = sprintf('%-40s présent dans vendor/ (%s) mais absent du lock', $name, $version);
    }
}

if ($errors) {
    fwrite(STDERR, "vendor/ et composer.lock ont divergé :\n\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '  ' . $error . "\n");
    }
    fail(count($errors) . ' écart(s) détecté(s).');
}

printf("✓ vendor/ et composer.lock sont alignés (%d paquets).\n", count($lock));
