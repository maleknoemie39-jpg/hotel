<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$utilisateur = utilisateur_connecte();
$idUtilisateur = 0;
if ($utilisateur !== null) {
    $idUtilisateur = (int) $utilisateur['id'];
}
$dateDebut = new DateTimeImmutable('today');
$dateFin = $dateDebut->modify('+7 days');

// Charger les événements publiés de la semaine et compter les places réservées.
$sqlEvenements = "SELECT e.id_evenement, e.titre, e.description, e.type_evenement,
               e.date_debut, e.date_fin, e.nombre_place,
               GROUP_CONCAT(DISTINCT j.titre ORDER BY j.titre SEPARATOR ', ') AS jeux,
               GROUP_CONCAT(DISTINCT s.nom ORDER BY s.nom SEPARATOR ', ') AS salles,
               (SELECT COUNT(*) FROM inscrire i
                WHERE i.id_evenement = e.id_evenement AND i.statut = 'CONFIRMEE') AS places_prises,
               (SELECT COUNT(*) FROM inscrire i
                WHERE i.id_evenement = e.id_evenement AND i.id_utilisateur = ? AND i.statut = 'CONFIRMEE') AS inscrit
        FROM evenement e
        LEFT JOIN lier l ON l.id_evenement = e.id_evenement
        LEFT JOIN jeux j ON j.id_jeu = l.id_jeu
        LEFT JOIN derouler d ON d.id_evenement = e.id_evenement
        LEFT JOIN salle s ON s.id_salle = d.id_salle
        WHERE e.statut = 'PUBLIE' AND e.date_debut >= ? AND e.date_debut < ?
        GROUP BY e.id_evenement, e.titre, e.description, e.type_evenement,
                 e.date_debut, e.date_fin, e.nombre_place
        ORDER BY e.date_debut";
$requeteEvenements = mysqli_prepare($conn, $sqlEvenements);
$debutSql = $dateDebut->format('Y-m-d H:i:s');
$finSql = $dateFin->format('Y-m-d H:i:s');
mysqli_stmt_bind_param($requeteEvenements, 'iss', $idUtilisateur, $debutSql, $finSql);
mysqli_stmt_execute($requeteEvenements);
$evenementsBruts = mysqli_fetch_all(mysqli_stmt_get_result($requeteEvenements), MYSQLI_ASSOC);
mysqli_stmt_close($requeteEvenements);

// Préparer les informations que le planning JavaScript va afficher.
$evenements = [];
foreach ($evenementsBruts as $evenement) {
    $capacite = (int) $evenement['nombre_place'];
    $evenements[] = [
        'id' => (int) $evenement['id_evenement'],
        'titre' => $evenement['titre'],
        'description' => $evenement['description'],
        'type' => $evenement['type_evenement'],
        'debut' => str_replace(' ', 'T', substr($evenement['date_debut'], 0, 16)),
        'fin' => str_replace(' ', 'T', substr($evenement['date_fin'], 0, 16)),
        'capacite' => $capacite,
        'places_disponibles' => max(0, $capacite - (int) $evenement['places_prises']),
        'inscrit' => (int) $evenement['inscrit'] > 0,
        'salle' => $evenement['salles'] ?? '',
        'jeux' => $evenement['jeux'] ?? '',
    ];
}

// Choisir un message simple à afficher après une réservation.
$messages = [
    'inscrit' => 'Votre place est réservée. Retrouvez-la dans Mes inscriptions.',
    'deja-inscrit' => 'Vous êtes déjà inscrit à cet événement.',
    'complet' => 'Désolé, il ne reste plus de place pour cet événement.',
    'indisponible' => 'Cet événement n’est plus disponible à la réservation.',
    'erreur' => 'La demande n’a pas pu être vérifiée. Rechargez la page.',
];
$cleMessage = $_GET['message'] ?? '';
$message = '';
if (is_string($cleMessage)) {
    $message = $messages[$cleMessage] ?? '';
}
$jetonCsrf = jeton_csrf();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101522">
    <meta name="description" content="Consultez le planning des animations jeux vidéo de l’hôtel.">
    <title>Planning de l'hôtel</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<header class="entete">
    <div class="contenu-entete">
        <a class="logo" href="index.php">
            <img src="logo-hotel.png" alt="">
            <span>Les événements de l’hôtel<small>LE PROGRAMME DE L’HÔTEL</small></span>
        </a>
        <?php if ($utilisateur === null): ?>
            <nav class="navigation-compte" aria-label="Compte">
                <a href="connexion.php">Connexion</a>
                <a href="inscrire.php">Inscription</a>
            </nav>
        <?php else: ?>
            <nav class="navigation-compte" aria-label="Compte">
                <?php if ($utilisateur['role'] === 'ADMIN'): ?>
                    <a href="admin.php">Gestion des événements</a>
                <?php else: ?>
                    <a href="mes-inscriptions.php">Mes inscriptions</a>
                <?php endif; ?>
                <a href="deconnexion.php">Déconnexion</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="page-principale">
    <section class="introduction" aria-labelledby="titre-page">
        <p class="petit-titre">Jouez, découvrez, partagez</p>
        <h1 id="titre-page">Le planning de l'hôtel</h1>
        <p class="texte-introduction">Retrouvez les horaires, les jeux et les salles des animations de la semaine.</p>
        <a class="lien-principal" href="#planning">Nos événements</a>
    </section>

    <section class="planning" id="planning" aria-labelledby="titre-planning">
        <?php if ($message !== ''): ?>
            <p class="message-info" role="status"><?= h($message) ?></p>
        <?php endif; ?>
        <div class="titre-planning">
            <p class="petit-titre">À l’hôtel cette semaine</p>
            <h2 id="titre-planning">Planning de la semaine</h2>
            <p>Choisissez une journée pour consulter le programme.</p>
        </div>
        <nav class="choix-jour" id="choix-jour" aria-label="Choisir le jour du planning" role="tablist"></nav>
        <div class="jour-selectionne">
            <div>
                <span class="texte-jour">Au programme</span>
                <h3 id="date-selectionnee"></h3>
            </div>
            <span class="compteur-evenements" id="compteur-evenements" aria-live="polite"></span>
        </div>
        <div class="liste-evenements" id="liste-evenements" role="tabpanel" tabindex="0" aria-live="polite"></div>
    </section>
</main>

<script id="donnees-evenements" type="application/json"><?= json_encode(
    ['evenements' => $evenements, 'role' => $utilisateur['role'] ?? '', 'jeton' => $jetonCsrf],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?></script>
<script src="planning.js?v=<?= filemtime(__DIR__ . '/planning.js') ?>" defer></script>
</body>
</html>
