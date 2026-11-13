<?php
declare(strict_types=1);

require_once __DIR__ . '/initialisation.php';

$dateDebut = new DateTimeImmutable('today');
$dateFin = $dateDebut->modify('+7 days');

$requete = mysqli_prepare(
    $conn,
    "SELECT e.id_evenement, e.titre, e.description, e.type_evenement,
            e.date_debut, e.date_fin, e.nombre_place,
            GROUP_CONCAT(DISTINCT j.titre ORDER BY j.titre SEPARATOR ', ') AS jeux,
            GROUP_CONCAT(DISTINCT s.nom ORDER BY s.nom SEPARATOR ', ') AS salles
     FROM evenement e
     LEFT JOIN lier l ON l.id_evenement = e.id_evenement
     LEFT JOIN jeux j ON j.id_jeu = l.id_jeu
     LEFT JOIN derouler d ON d.id_evenement = e.id_evenement
     LEFT JOIN salle s ON s.id_salle = d.id_salle
     WHERE e.statut = 'PUBLIE'
       AND e.date_debut >= ?
       AND e.date_debut < ?
     GROUP BY e.id_evenement, e.titre, e.description,
              e.type_evenement, e.date_debut, e.date_fin,
              e.nombre_place
     ORDER BY e.date_debut ASC"
);

$dateDebutSql = $dateDebut->format('Y-m-d H:i:s');
$dateFinSql = $dateFin->format('Y-m-d H:i:s');
mysqli_stmt_bind_param($requete, 'ss', $dateDebutSql, $dateFinSql);
mysqli_stmt_execute($requete);
$resultat = mysqli_stmt_get_result($requete);
$evenementsBruts = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
mysqli_stmt_close($requete);

$evenements = array_map(static function (array $evenement): array {

    $capacite = (int) $evenement['nombre_place'];
    return [
        'id' => (int) $evenement['id_evenement'],
        'titre' => $evenement['titre'],
        'description' => $evenement['description'],
        'type' => $evenement['type_evenement'],
        'debut' => str_replace(' ', 'T', substr($evenement['date_debut'], 0, 16)),
        'fin' => str_replace(' ', 'T', substr($evenement['date_fin'], 0, 16)),
        'capacite' => $capacite,
        'places_disponibles' => $capacite,
        'salle' => $evenement['salles'] ?? '',
        'jeux' => $evenement['jeux'] ?? '',
    ];

}, $evenementsBruts);

?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="theme-color" content="#101522">

    <meta name="description"
          content="Consultez le planning des animations jeux vidéo de l’hôtel.">

    <title>Planning de l'hôtel</title>

    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

<header class="entete">

    <div class="contenu-entete">

        <a class="logo" href="index.php">
            <span>
                Les événements de l’hôtel
                <small>LE PROGRAMME DE L’HÔTEL</small>
            </span>
        </a>

    </div>

</header>


<main class="page-principale">

    <section class="introduction" aria-labelledby="titre-page">

        <p class="petit-titre">Jouez, découvrez, partagez</p>

        <h1 id="titre-page">Le planning de l'hôtel</h1>

        <p class="texte-introduction">
            Retrouvez les horaires, les jeux et les salles des animations de la semaine.
        </p>

        <a class="lien-principal" href="#planning">
            Nos événements
        </a>

    </section>


    <section class="planning" id="planning" aria-labelledby="titre-planning">

        <div class="titre-planning">

            <div>
                <p class="petit-titre">À l’hôtel cette semaine</p>

                <h2 id="titre-planning">Planning de la semaine</h2>

                <p>Choisissez une journée pour consulter le programme.</p>
            </div>

        </div>


        <nav
            class="choix-jour"
            id="choix-jour"
            aria-label="Choisir le jour du planning"
            role="tablist">
        </nav>


        <div class="jour-selectionne">

            <div>
                <span class="texte-jour">Au programme</span>

                <h3 id="date-selectionnee"></h3>
            </div>

            <span class="compteur-evenements" id="nombre-evenements"></span>

        </div>


        <div
            class="liste-evenements"
            id="liste-evenements"
            aria-live="polite">
        </div>

    </section>

</main>


<script id="donnees-evenements" type="application/json"><?= json_encode(
    $evenements,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?></script>
<script src="planning.js" defer></script>

</body>
</html>
