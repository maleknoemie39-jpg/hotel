<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$utilisateur = exiger_role('CLIENT');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEvenement = filter_input(INPUT_POST, 'id_evenement', FILTER_VALIDATE_INT);

    // Annuler uniquement une réservation qui appartient au client connecté.
    if (
        !verifier_csrf()
        || $idEvenement === false
        || $idEvenement === null
        || $idEvenement < 1
    ) {
        $message = 'La demande n’a pas pu être vérifiée. Rechargez la page.';
    } else {
        $requeteAnnulation = mysqli_prepare(
            $conn,
            "UPDATE inscrire
             SET statut = 'ANNULEE'
             WHERE id_evenement = ? AND id_utilisateur = ? AND statut = 'CONFIRMEE'"
        );
        mysqli_stmt_bind_param($requeteAnnulation, 'ii', $idEvenement, $utilisateur['id']);
        mysqli_stmt_execute($requeteAnnulation);
        $annulationEffectuee = mysqli_stmt_affected_rows($requeteAnnulation) > 0;
        mysqli_stmt_close($requeteAnnulation);
        if ($annulationEffectuee) {
            $message = 'Votre place a été libérée.';
        } else {
            $message = 'Cette inscription est déjà annulée ou introuvable.';
        }
    }
}

// Afficher les réservations du client connecté.
$requeteInscriptions = mysqli_prepare(
    $conn,
    "SELECT i.id_evenement, i.statut, e.titre, e.date_debut, e.date_fin, s.nom AS salle
     FROM inscrire i
     JOIN evenement e ON e.id_evenement = i.id_evenement
     LEFT JOIN derouler d ON d.id_evenement = e.id_evenement
     LEFT JOIN salle s ON s.id_salle = d.id_salle
     WHERE i.id_utilisateur = ?
     ORDER BY e.date_debut DESC"
);
mysqli_stmt_bind_param($requeteInscriptions, 'i', $utilisateur['id']);
mysqli_stmt_execute($requeteInscriptions);
$inscriptions = mysqli_fetch_all(mysqli_stmt_get_result($requeteInscriptions), MYSQLI_ASSOC);
mysqli_stmt_close($requeteInscriptions);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes inscriptions — Hôtel Pixel</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<header class="entete">
    <div class="contenu-entete">
        <a class="logo" href="index.php">
            <img src="logo-hotel.png" alt="">
            <span>Les événements de l’hôtel<small>LE PROGRAMME DE L’HÔTEL</small></span>
        </a>
        <nav class="navigation-compte">
            <a href="index.php">Planning public</a>
            <a href="deconnexion.php">Déconnexion</a>
        </nav>
    </div>
</header>
<main class="page-principale">
    <section class="planning compte-page">
        <p class="petit-titre">Bonjour <?= h($utilisateur['prenom']) ?></p>
        <h1>Mes inscriptions</h1>
        <?php if ($message !== ''): ?>
            <p class="message-info" role="status"><?= h($message) ?></p>
        <?php endif; ?>
        <?php if ($inscriptions === []): ?>
            <div class="aucun-evenement">
                <strong>Vous n’êtes inscrit à aucun événement.</strong>
                <p>Consultez le planning et réservez votre place.</p>
            </div>
        <?php else: ?>
            <div class="liste-evenements">
                <?php foreach ($inscriptions as $inscription): ?>
                    <article class="carte-evenement">
                        <div class="details-evenement">
                            <span class="etiquette"><?= h($inscription['statut']) ?></span>
                            <h2><?= h($inscription['titre']) ?></h2>
                            <p>
                                <?= h(date('d/m/Y à H\hi', strtotime($inscription['date_debut']))) ?>
                                · Salle : <?= h($inscription['salle'] ?? 'À préciser') ?>
                            </p>
                        </div>
                        <?php if ($inscription['statut'] === 'CONFIRMEE'): ?>
                            <form method="post">
                                <input type="hidden" name="jeton_csrf" value="<?= h(jeton_csrf()) ?>">
                                <input type="hidden" name="id_evenement" value="<?= (int) $inscription['id_evenement'] ?>">
                                <button class="bouton bouton-secondaire" type="submit">Annuler ma place</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
