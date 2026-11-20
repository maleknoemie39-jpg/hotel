<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$erreur = '';
$formulaireEnvoye = $_SERVER['REQUEST_METHOD'] === 'POST';

// Le jeton vérifie que le formulaire vient bien de cette page.
if ($formulaireEnvoye && !verifier_csrf()) {
    $erreur = 'La page a expiré. Rechargez-la puis réessayez.';
} elseif ($formulaireEnvoye) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

    // Chercher le compte qui correspond à l'adresse e-mail.
    $requeteCompte = mysqli_prepare(
        $conn,
        'SELECT u.id_utilisateur, u.nom, u.prenom, u.mot_de_passe,
                a.id_utilisateur AS id_admin
         FROM utilisateur u
         LEFT JOIN admin a ON a.id_utilisateur = u.id_utilisateur
         WHERE u.email = ?
         LIMIT 1'
    );
    mysqli_stmt_bind_param($requeteCompte, 's', $email);
    mysqli_stmt_execute($requeteCompte);
    $compte = mysqli_fetch_assoc(mysqli_stmt_get_result($requeteCompte));
    mysqli_stmt_close($requeteCompte);

    // Comparer le mot de passe saisi avec celui enregistré.
    if ($compte === null || !password_verify($motDePasse, $compte['mot_de_passe'])) {
        $erreur = 'Adresse e-mail ou mot de passe incorrect.';
    } else {
        $role = 'CLIENT';
        if ($compte['id_admin'] !== null) {
            $role = 'ADMIN';
        }

        // Garder les informations du compte pour les prochaines pages.
        session_regenerate_id(true);
        $_SESSION['utilisateur'] = [
            'id' => (int) $compte['id_utilisateur'],
            'nom' => $compte['nom'],
            'prenom' => $compte['prenom'],
            'role' => $role,
        ];
        unset($_SESSION['jeton_csrf']);

        if ($role === 'ADMIN') {
            header('Location: admin.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }
}

$jeton = jeton_csrf();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — Hôtel Pixel</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<header class="entete">
    <div class="contenu-entete">
        <a class="logo" href="index.php">
            <img src="logo-hotel.png" alt="">
            <span>Les événements de l’hôtel<small>LE PROGRAMME DE L’HÔTEL</small></span>
        </a>
    </div>
</header>
<main class="page-principale">
    <section class="planning compte-page">
        <p class="petit-titre">Votre compte</p>
        <h1>Se connecter</h1>
        <?php if ($erreur !== ''): ?>
            <p class="message-erreur" role="alert"><?= h($erreur) ?></p>
        <?php endif; ?>
        <form class="formulaire formulaire-connexion" method="post">
            <input type="hidden" name="jeton_csrf" value="<?= h($jeton) ?>">
            <label for="email">Adresse e-mail</label>
            <input id="email" type="email" name="email" autocomplete="email" required>
            <label for="mot_de_passe">Mot de passe</label>
            <input id="mot_de_passe" type="password" name="mot_de_passe" autocomplete="current-password" required>
            <button class="bouton" type="submit">Se connecter</button>
        </form>
        <p class="lien-formulaire">Pas encore de compte ? <a href="inscrire.php">Créer un compte client</a></p>
    </section>
</main>
</body>
</html>
