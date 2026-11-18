<?php
declare(strict_types=1);

function chemin_publications(): string
{
    return dirname(__DIR__) . '/data/publications.json';
}

function chemin_publications_defaut(): string
{
    return dirname(__DIR__) . '/data/publications.default.json';
}

function publications_lire(): array
{
    $chemin = chemin_publications();
    if (!is_file($chemin)) {
        $chemin = chemin_publications_defaut();
    }
    if (!is_file($chemin)) {
        return [];
    }
    $donnees = json_decode((string) file_get_contents($chemin), true);
    if (!is_array($donnees)) {
        return [];
    }
    usort($donnees, static function (array $a, array $b): int {
        $cleA = ($a['date'] ?? '') !== '' ? $a['date'] : substr((string) ($a['cree'] ?? ''), 0, 10);
        $cleB = ($b['date'] ?? '') !== '' ? $b['date'] : substr((string) ($b['cree'] ?? ''), 0, 10);
        return strcmp($cleB, $cleA);
    });
    return $donnees;
}

function publications_enregistrer(array $publications): bool
{
    $chemin = chemin_publications();
    $dossier = dirname($chemin);
    if (!is_dir($dossier) && !mkdir($dossier, 0755, true) && !is_dir($dossier)) {
        return false;
    }
    $json = json_encode(array_values($publications), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($chemin, $json . "\n", LOCK_EX) !== false;
}

function mot_de_passe_valide(string $saisi): bool
{
    $config = require dirname(__DIR__) . '/config.php';
    $calcule = hash_pbkdf2('sha256', $saisi, hex2bin($config['password_salt']), 120000);
    return hash_equals($config['password_hash'], $calcule);
}

function publication_nettoyer(array $source): array
{
    $titre = trim((string) ($source['titre'] ?? ''));
    $resume = trim((string) ($source['resume'] ?? ''));
    $date = trim((string) ($source['date'] ?? ''));
    $lien = trim((string) ($source['lien'] ?? ''));

    if ($titre === '' || strlen($titre) > 180 || $resume === '' || strlen($resume) > 2000) {
        return [];
    }
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return [];
    }
    if ($lien !== '') {
        $valide = filter_var($lien, FILTER_VALIDATE_URL);
        $schema = parse_url($lien, PHP_URL_SCHEME);
        if ($valide === false || !in_array($schema, ['http', 'https'], true) || strlen($lien) > 500) {
            return [];
        }
    }

    return [
        'titre' => $titre,
        'resume' => $resume,
        'date' => $date,
        'lien' => $lien,
    ];
}
