<?php
declare(strict_types=1);

require __DIR__ . '/includes/store.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$publications = array_map(static function (array $item): array {
    return [
        'titre' => (string) ($item['titre'] ?? ''),
        'resume' => (string) ($item['resume'] ?? ''),
        'date' => (string) ($item['date'] ?? ''),
        'lien' => (string) ($item['lien'] ?? ''),
    ];
}, publications_lire());

echo json_encode($publications, JSON_UNESCAPED_UNICODE);
