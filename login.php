<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$erreur = '';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreur = 'La page a expiré. Rechargez-la puis réessayez.';
    } elseif ($action === 'connexion') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

        $requete = mysqli_prepare(
            $conn,
            'SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role_utilisateur
             FROM utilisateur WHERE email = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($requete, 's', $email);
        mysqli_stmt_execute($requete);
        $compte = mysqli_fetch_assoc(mysqli_stmt_get_result($requete));
        mysqli_stmt_close($requete);

        if ($compte !== null && password_verify($motDePasse, $compte['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['utilisateur'] = [
                'id' => (int) $compte['id_utilisateur'],
                'nom' => $compte['nom'],
                'prenom' => $compte['prenom'],
                'role' => $compte['role_utilisateur'],
            ];
            unset($_SESSION['jeton_csrf']);
            header('Location: ' . ($compte['role_utilisateur'] === 'ADMIN' ? 'admin.php' : 'index.php'));
            exit;
        }

        $erreur = 'Adresse e-mail ou mot de passe incorrect.';
    } elseif ($action === 'inscription') {
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        if ($prenom === '' || $nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Renseignez votre prénom, votre nom et une adresse e-mail valide.';
        } elseif (strlen($motDePasse) < 8) {
            $erreur = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($motDePasse !== $confirmation) {
            $erreur = 'Les deux mots de passe ne correspondent pas.';
        } else {
            $hachage = password_hash($motDePasse, PASSWORD_DEFAULT);
            $requete = mysqli_prepare(
                $conn,
                "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role_utilisateur)
                 VALUES (?, ?, ?, ?, 'CLIENT')"
            );
            mysqli_stmt_bind_param($requete, 'ssss', $nom, $prenom, $email, $hachage);

            try {
                mysqli_stmt_execute($requete);
                $id = mysqli_insert_id($conn);
                mysqli_stmt_close($requete);

                session_regenerate_id(true);
                $_SESSION['utilisateur'] = [
                    'id' => $id,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'role' => 'CLIENT',
                ];
                unset($_SESSION['jeton_csrf']);
                header('Location: index.php');
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_stmt_close($requete);
                if ((int) $exception->getCode() === 1062) {
                    $erreur = 'Cette adresse e-mail possède déjà un compte.';
                } else {
                    throw $exception;
                }
            }
        }
    } else {
        $erreur = 'Action non reconnue.';
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
        <div class="formulaires-compte formulaire-connexion-conteneur">
            <form class="formulaire formulaire-connexion" method="post">
                <input type="hidden" name="jeton_csrf" value="<?= h($jeton) ?>">
                <input type="hidden" name="action" value="connexion">
                <label for="connexion-email">Adresse e-mail</label>
                <input id="connexion-email" type="email" name="email" autocomplete="email" required>
                <label for="connexion-mdp">Mot de passe</label>
                <input id="connexion-mdp" type="password" name="mot_de_passe" autocomplete="current-password" required>
                <button class="bouton" type="submit">Se connecter</button>
            </form>
        </div>
        <p class="lien-formulaire">Pas encore de compte ? <a href="inscrire.php">Créer un compte client</a></p>
    </section>
</main>
</body>
</html>
