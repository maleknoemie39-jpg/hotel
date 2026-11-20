<?php
declare(strict_types=1);

require_once __DIR__ . '/initialisation.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function h(string $valeur): string
{
    return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

function utilisateur_connecte(): ?array
{
    return isset($_SESSION['utilisateur']) && is_array($_SESSION['utilisateur'])
        ? $_SESSION['utilisateur']
        : null;
}

function exiger_role(string $role): array
{
    $utilisateur = utilisateur_connecte();
    if ($utilisateur === null || $utilisateur['role'] !== $role) {
        header('Location: login.php');
        exit;
    }

    return $utilisateur;
}
