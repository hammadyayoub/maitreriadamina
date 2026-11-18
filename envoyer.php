<?php
declare(strict_types=1);

const DESTINATAIRE = 'contact@maitreriadamina.com';

function champ(string $cle): string
{
    $valeur = isset($_POST[$cle]) ? trim((string) $_POST[$cle]) : '';
    return str_replace(["\r", "\n"], ' ', $valeur);
}

function message(): string
{
    $valeur = isset($_POST['message']) ? trim((string) $_POST['message']) : '';
    return str_replace(["\r\n", "\r"], "\n", $valeur);
}

function page_retour(): string
{
    $page = champ('retour');
    return $page === 'services.html' ? 'services.html' : 'index.html';
}

function rediriger(string $resultat): void
{
    $ancre = page_retour() === 'services.html' ? 'rendez-vous' : 'contact';
    header('Location: ' . page_retour() . '?envoye=' . $resultat . '#' . $ancre);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.html');
    exit;
}

if (champ('societe') !== '') {
    rediriger('1');
}

$nom = champ('nom');
$email = champ('email');
$date = champ('date');
$type = champ('type');
$texte = message();

$valide = $nom !== ''
    && strlen($nom) <= 120
    && filter_var($email, FILTER_VALIDATE_EMAIL)
    && strlen($email) <= 120
    && strlen($date) <= 20
    && strlen($type) <= 80
    && strlen($texte) <= 5000
    && ($texte !== '' || $date !== '');

if (!$valide) {
    rediriger('0');
}

$sujet = $date !== ''
    ? 'Demande de rendez-vous — ' . $nom
    : 'Message du site — ' . $nom;

$corps = "Nom : {$nom}\nE-mail : {$email}\n";
if ($date !== '') {
    $corps .= "Date souhaitée : {$date}\n";
}
if ($type !== '') {
    $corps .= "Type de consultation : {$type}\n";
}
$corps .= "\nMessage :\n" . ($texte !== '' ? $texte : '(aucun message)') . "\n";

$entetes = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: Site Maître Amina Riad <' . DESTINATAIRE . '>',
    'Reply-To: ' . $email,
]);

$ok = mail(
    DESTINATAIRE,
    '=?UTF-8?B?' . base64_encode($sujet) . '?=',
    $corps,
    $entetes,
    '-f' . DESTINATAIRE
);

rediriger($ok ? '1' : '0');
