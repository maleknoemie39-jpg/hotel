<?php
declare(strict_types=1);

require_once __DIR__ . '/initialisation.php';

// Garder la session active entre les pages du site.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Afficher du texte sans laisser interpréter du code HTML.
function h(string $valeur): string
{
    return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Créer et vérifier le jeton qui protège les formulaires.
function jeton_csrf(): string
{
    if (!isset($_SESSION['jeton_csrf'])) {
        $_SESSION['jeton_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['jeton_csrf'];
}

function verifier_csrf(): bool
{
    $jeton = $_POST['jeton_csrf'] ?? '';

    return is_string($jeton)
        && isset($_SESSION['jeton_csrf'])
        && hash_equals($_SESSION['jeton_csrf'], $jeton);
}

// Renvoyer le compte connecté, ou null si personne n'est connecté.
function utilisateur_connecte(): ?array
{
    return isset($_SESSION['utilisateur']) && is_array($_SESSION['utilisateur'])
        ? $_SESSION['utilisateur']
        : null;
}

// Empêcher l'accès à une page si le compte n'a pas le bon rôle.
function exiger_role(string $role): array
{
    $utilisateur = utilisateur_connecte();
    if ($utilisateur === null || $utilisateur['role'] !== $role) {
        header('Location: connexion.php');
        exit;
    }

    return $utilisateur;
}
