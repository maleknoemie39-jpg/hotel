<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';
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
            <a href="login.php">Déjà un compte ? Connexion</a>
        </nav>
    </div>
</header>
<main class="page-principale">
    <section class="planning compte-page">
        <p class="petit-titre">Votre compte</p>
        <h1>Créer un compte client</h1>
        <form class="formulaire formulaire-inscription" method="post" action="login.php">
            <input type="hidden" name="jeton_csrf" value="<?= h($jeton) ?>">
            <input type="hidden" name="action" value="inscription">
            <label for="prenom">Prénom</label>
            <input id="prenom" type="text" name="prenom" maxlength="50" autocomplete="given-name" required>
            <label for="nom">Nom</label>
            <input id="nom" type="text" name="nom" maxlength="50" autocomplete="family-name" required>
            <label for="inscription-email">Adresse e-mail</label>
            <input id="inscription-email" type="email" name="email" maxlength="120" autocomplete="email" required>
            <label for="inscription-mdp">Mot de passe (8 caractères minimum)</label>
            <input id="inscription-mdp" type="password" name="mot_de_passe" minlength="8" autocomplete="new-password" required>
            <label for="confirmation">Confirmer le mot de passe</label>
            <input id="confirmation" type="password" name="confirmation" minlength="8" autocomplete="new-password" required>
            <button class="bouton" type="submit">Créer mon compte</button>
        </form>
    </section>
</main>
</body>
</html>
