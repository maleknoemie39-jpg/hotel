<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

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

    if (!is_string($jeton) || !isset($_SESSION['jeton_csrf'])) {
        return false;
    }

    return hash_equals($_SESSION['jeton_csrf'], $jeton);
}

function utilisateur_connecte(): ?array
{
    if (!isset($_SESSION['utilisateur']) || !is_array($_SESSION['utilisateur'])) {
        return null;
    }

    return $_SESSION['utilisateur'];
}

function exiger_role(string $role): array
{
    $utilisateur = utilisateur_connecte();
    if ($utilisateur === null || $utilisateur['role'] !== $role) {
        header('Location: connexion.php');
        exit;
    }

    return $utilisateur;
}

function envoyer_confirmation_inscription(string $adresse, string $prenom): void
{
    $hote = getenv('HOTEL_SMTP_HOST');
    $port = filter_var(getenv('HOTEL_SMTP_PORT'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    $utilisateur = getenv('HOTEL_SMTP_USERNAME');
    $motDePasse = getenv('HOTEL_SMTP_PASSWORD');
    $expediteur = getenv('HOTEL_EMAIL_FROM');
    $chiffrement = getenv('HOTEL_SMTP_ENCRYPTION');

    if (
        !$hote || $port === false || !$utilisateur || !$motDePasse
        || !$expediteur || !filter_var($expediteur, FILTER_VALIDATE_EMAIL)
        || !in_array($chiffrement, ['tls', 'ssl'], true)
    ) {
        throw new RuntimeException('Configuration SMTP manquante ou invalide.');
    }

    $courriel = new PHPMailer\PHPMailer\PHPMailer(true);
    $courriel->isSMTP();
    $courriel->Host = $hote;
    $courriel->SMTPAuth = true;
    $courriel->Username = $utilisateur;
    $courriel->Password = $motDePasse;
    $courriel->Port = $port;
    $courriel->SMTPSecure = $chiffrement === 'ssl'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $courriel->CharSet = 'UTF-8';
    $courriel->setFrom($expediteur, 'Les événements de l’hôtel');
    $courriel->addAddress($adresse);
    $courriel->isHTML(false);
    $courriel->Subject = 'Confirmation de votre inscription';
    $prenom = str_replace(["\r", "\n"], ' ', $prenom);
    $courriel->Body = "Bonjour {$prenom},\n\n"
        . "Votre compte client sur le site des événements de l’hôtel a bien été créé. "
        . "Vous pouvez maintenant consulter le planning et vous connecter à votre compte.\n\n"
        . "À bientôt !";

    try {
        $courriel->send();
    } catch (PHPMailer\PHPMailer\Exception $exception) {
        throw new RuntimeException('Échec de l’envoi SMTP : ' . $courriel->ErrorInfo, 0, $exception);
    }
}
