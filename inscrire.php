<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$erreur = '';
$confirmation = $_SESSION['confirmation_inscription'] ?? null;
unset($_SESSION['confirmation_inscription']);
$inscriptionTerminee = is_array($confirmation);
$confirmationEnvoyee = $inscriptionTerminee && ($confirmation['envoyee'] ?? false) === true;
$emailConfirmation = $inscriptionTerminee && is_string($confirmation['email'] ?? null)
    ? $confirmation['email']
    : '';
$formulaireEnvoye = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($formulaireEnvoye && !verifier_csrf()) {
    $erreur = 'La page a expiré. Rechargez-la puis réessayez.';
} elseif ($formulaireEnvoye) {
    $prenom = trim((string) ($_POST['prenom'] ?? ''));
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
    $confirmation = (string) ($_POST['confirmation'] ?? '');

    if ($prenom === '' || $nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Renseignez votre prénom, votre nom et une adresse e-mail valide.';
    } elseif (strlen($motDePasse) < 12) {
        $erreur = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif ($motDePasse !== $confirmation) {
        $erreur = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $hachage = password_hash($motDePasse, PASSWORD_DEFAULT);
        $requeteNouveauCompte = mysqli_prepare(
            $conn,
            'INSERT INTO utilisateur (nom, prenom, email, mot_de_passe)
             VALUES (?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param($requeteNouveauCompte, 'ssss', $nom, $prenom, $email, $hachage);

        try {
            mysqli_stmt_execute($requeteNouveauCompte);
            $idUtilisateur = mysqli_insert_id($conn);
            mysqli_stmt_close($requeteNouveauCompte);

            try {
                envoyer_confirmation_inscription($email, $prenom);
                $confirmationEnvoyee = true;
            } catch (RuntimeException $exception) {
                error_log($exception->getMessage());
            }

            session_regenerate_id(true);
            $_SESSION['utilisateur'] = [
                'id' => $idUtilisateur,
                'nom' => $nom,
                'prenom' => $prenom,
                'role' => 'CLIENT',
            ];
            unset($_SESSION['jeton_csrf']);
            $_SESSION['confirmation_inscription'] = [
                'email' => $email,
                'envoyee' => $confirmationEnvoyee,
            ];
            header('Location: inscrire.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            mysqli_stmt_close($requeteNouveauCompte);
            if ((int) $exception->getCode() === 1062) {
                $erreur = 'Cette adresse e-mail possède déjà un compte.';
            } else {
                throw $exception;
            }
        }
    }
}

$jeton = jeton_csrf();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription — Hôtel Pixel</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<header class="entete">
    <div class="contenu-entete">
        <a class="logo" href="index.php">
            <img src="logo-hotel.png" alt="">
            <span>Les événements de l’hôtel<small>LE PROGRAMME DE L’HÔTEL</small></span>
        </a>
        <nav class="navigation-compte" aria-label="Compte">
            <a href="connexion.php">Déjà un compte ? Connexion</a>
        </nav>
    </div>
</header>
<main class="page-principale">
    <section class="planning compte-page">
        <p class="petit-titre">Votre compte</p>
        <h1>Créer un compte client</h1>
        <?php if ($erreur !== ''): ?>
            <p class="message-erreur" role="alert"><?= h($erreur) ?></p>
        <?php endif; ?>
        <?php if ($inscriptionTerminee): ?>
            <?php if ($confirmationEnvoyee): ?>
                <p class="message-info" role="status">
                    Votre compte est créé. Un e-mail de confirmation a été envoyé à
                    <?= h($emailConfirmation) ?>.
                </p>
            <?php else: ?>
                <p class="message-erreur" role="alert">
                    Votre compte est créé, mais l’e-mail de confirmation n’a pas pu être envoyé.
                    Le service d’envoi doit être configuré. Vous êtes connecté et pouvez continuer.
                </p>
            <?php endif; ?>
            <p class="lien-formulaire"><a href="index.php">Continuer vers le planning</a></p>
        <?php else: ?>
            <form class="formulaire formulaire-inscription" method="post">
                <input type="hidden" name="jeton_csrf" value="<?= h($jeton) ?>">
                <label for="prenom">Prénom</label>
                <input id="prenom" type="text" name="prenom" maxlength="50" autocomplete="given-name" required>
                <label for="nom">Nom</label>
                <input id="nom" type="text" name="nom" maxlength="50" autocomplete="family-name" required>
                <label for="email">Adresse e-mail</label>
                <input id="email" type="email" name="email" maxlength="120" autocomplete="email" required>
                <label for="mot_de_passe">Mot de passe (12 caractères minimum)</label>
                <input id="mot_de_passe" type="password" name="mot_de_passe" minlength="12" autocomplete="new-password" required>
                <label for="confirmation">Confirmer le mot de passe</label>
                <input id="confirmation" type="password" name="confirmation" minlength="12" autocomplete="new-password" required>
                <button class="bouton" type="submit">Créer mon compte</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
