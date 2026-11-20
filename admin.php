<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';
$utilisateur = exiger_role('ADMIN');
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreur = 'La page a expiré. Rechargez-la puis réessayez.';
    } else {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $debutSaisi = (string) ($_POST['date_debut'] ?? '');
        $finSaisie = (string) ($_POST['date_fin'] ?? '');
        $idSalle = filter_input(INPUT_POST, 'id_salle', FILTER_VALIDATE_INT);
        $idJeu = filter_input(INPUT_POST, 'id_jeu', FILTER_VALIDATE_INT);
        $debut = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $debutSaisi);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $finSaisie);

        if ($titre === '' || strlen($titre) > 100 || $type === '' || strlen($type) > 50 || $description === '') {
            $erreur = 'Renseignez le titre, le type et la description de l’événement.';
        } elseif (!$debut || $debut->format('Y-m-d\TH:i') !== $debutSaisi || !$fin || $fin->format('Y-m-d\TH:i') !== $finSaisie) {
            $erreur = 'Les dates et heures indiquées ne sont pas valides.';
        } elseif ($fin <= $debut || $debut < new DateTimeImmutable('now')) {
            $erreur = 'La fin doit être après le début et l’événement ne peut pas être dans le passé.';
        } elseif (
            $idSalle === false || $idSalle === null
            || $idJeu === false || $idJeu === null
        ) {
            $erreur = 'Choisissez une salle et un jeu valides.';
        } else {
            $requete = mysqli_prepare($conn, 'SELECT capacite FROM salle WHERE id_salle = ?');
            mysqli_stmt_bind_param($requete, 'i', $idSalle);
            mysqli_stmt_execute($requete);
            $salle = mysqli_fetch_assoc(mysqli_stmt_get_result($requete));
            mysqli_stmt_close($requete);

            $requete = mysqli_prepare($conn, 'SELECT id_jeu FROM jeux WHERE id_jeu = ?');
            mysqli_stmt_bind_param($requete, 'i', $idJeu);
            mysqli_stmt_execute($requete);
            $jeuExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($requete)) !== null;
            mysqli_stmt_close($requete);

            if ($salle === null || !$jeuExiste || (int) $salle['capacite'] < 1) {
                $erreur = 'Le jeu ou la salle sélectionné est invalide.';
            } else {
                $places = (int) $salle['capacite'];
                try {
                    mysqli_begin_transaction($conn);
                    $dateDebutSql = $debut->format('Y-m-d H:i:s');
                    $dateFinSql = $fin->format('Y-m-d H:i:s');
                    $requete = mysqli_prepare(
                        $conn,
                        "INSERT INTO evenement
                         (titre, type_evenement, description, date_debut, date_fin, nombre_place, statut, date_creation)
                         VALUES (?, ?, ?, ?, ?, ?, 'PUBLIE', NOW())"
                    );
                    mysqli_stmt_bind_param($requete, 'sssssi', $titre, $type, $description, $dateDebutSql, $dateFinSql, $places);
                    mysqli_stmt_execute($requete);
                    $idEvenement = mysqli_insert_id($conn);
                    mysqli_stmt_close($requete);

                    $requete = mysqli_prepare($conn, 'INSERT INTO derouler (id_evenement, id_salle) VALUES (?, ?)');
                    mysqli_stmt_bind_param($requete, 'ii', $idEvenement, $idSalle);
                    mysqli_stmt_execute($requete);
                    mysqli_stmt_close($requete);

                    $requete = mysqli_prepare($conn, 'INSERT INTO lier (id_evenement, id_jeu) VALUES (?, ?)');
                    mysqli_stmt_bind_param($requete, 'ii', $idEvenement, $idJeu);
                    mysqli_stmt_execute($requete);
                    mysqli_stmt_close($requete);

                    mysqli_commit($conn);
                    header('Location: admin.php?cree=oui');
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    mysqli_rollback($conn);
                    error_log($exception->getMessage());
                    $erreur = 'L’événement n’a pas pu être enregistré. Vérifiez la base de données puis réessayez.';
                }
            }
        }
    }
}

$salles = mysqli_query($conn, 'SELECT id_salle, nom, capacite FROM salle ORDER BY nom')->fetch_all(MYSQLI_ASSOC);
$jeux = mysqli_query($conn, 'SELECT id_jeu, titre FROM jeux ORDER BY titre')->fetch_all(MYSQLI_ASSOC);
$evenements = mysqli_query(
    $conn,
    "SELECT e.id_evenement, e.titre, e.date_debut, e.date_fin, e.nombre_place,
            s.nom AS salle, j.titre AS jeu
     FROM evenement e
     LEFT JOIN derouler d ON d.id_evenement = e.id_evenement
     LEFT JOIN salle s ON s.id_salle = d.id_salle
     LEFT JOIN lier l ON l.id_evenement = e.id_evenement
     LEFT JOIN jeux j ON j.id_jeu = l.id_jeu
     WHERE e.date_debut >= CURDATE()
     ORDER BY e.date_debut
"
)->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des événements — Hôtel Pixel</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<header class="entete">
    <div class="contenu-entete">
        <a class="logo" href="index.php"><img src="logo-hotel.png" alt=""><span>Les événements de l’hôtel<small>ESPACE ADMINISTRATEUR</small></span></a>
        <nav class="navigation-compte">
            <a href="index.php">Planning public</a>
            <a href="deconnexion.php">Déconnexion</a>
        </nav>
    </div>
</header>
<main class="page-principale">
    <section class="planning compte-page">
        <p class="petit-titre">Bonjour <?= h($utilisateur['prenom']) ?></p>
        <h1>Gestion des événements</h1>
        <?php if ($erreur !== ''): ?><p class="message-erreur" role="alert"><?= h($erreur) ?></p><?php endif; ?>
        <?php if (($_GET['cree'] ?? '') === 'oui'): ?><p class="message-info" role="status">L’événement a été publié dans le planning.</p><?php endif; ?>

        <form class="formulaire formulaire-evenement" method="post">
            <h2>Créer un événement</h2>
            <input type="hidden" name="jeton_csrf" value="<?= h(jeton_csrf()) ?>">
            <label for="titre">Nom de l’événement</label>
            <input id="titre" name="titre" maxlength="100" required>
            <label for="type">Type d’animation</label>
            <input id="type" name="type" maxlength="50" placeholder="Tournoi, atelier, soirée…" required>
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" required></textarea>
            <div class="champs-cotes">
                <div>
                    <label for="date_debut">Début</label>
                    <input id="date_debut" type="datetime-local" name="date_debut" required>
                </div>
                <div>
                    <label for="date_fin">Fin</label>
                    <input id="date_fin" type="datetime-local" name="date_fin" required>
                </div>
            </div>
            <div class="champs-cotes">
                <div>
                    <label for="id_salle">Salle</label>
                    <select id="id_salle" name="id_salle" required>
                        <option value="">Choisir une salle</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= (int) $salle['id_salle'] ?>" data-capacite="<?= (int) $salle['capacite'] ?>"><?= h($salle['nom']) ?> (<?= (int) $salle['capacite'] ?> places)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_jeu">Jeu</label>
                    <select id="id_jeu" name="id_jeu" required>
                        <option value="">Choisir un jeu</option>
                        <?php foreach ($jeux as $jeu): ?>
                            <option value="<?= (int) $jeu['id_jeu'] ?>"><?= h($jeu['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="nombre_place">Places attribuées selon la salle</label>
            <input id="nombre_place" type="number" value="" readonly>
            <button class="bouton" type="submit">Publier l’événement</button>
        </form>
    </section>

    <section class="planning">
        <div class="titre-planning"><p class="petit-titre">Programme à venir</p><h2>Événements publiés</h2></div>
        <?php if ($evenements === []): ?>
            <p>Aucun événement à venir.</p>
        <?php else: ?>
            <div class="liste-evenements">
                <?php foreach ($evenements as $evenement): ?>
                    <article class="carte-evenement">
                        <div class="details-evenement">
                            <h3><?= h($evenement['titre']) ?></h3>
                            <p><?= h(date('d/m/Y à H\hi', strtotime($evenement['date_debut']))) ?>
                                · <?= h($evenement['salle'] ?? 'Salle à préciser') ?>
                                · <?= h($evenement['jeu'] ?? 'Jeu à préciser') ?>
                                · <?= (int) $evenement['nombre_place'] ?> places</p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
    const choixSalle = document.querySelector("#id_salle");
    const champPlaces = document.querySelector("#nombre_place");

    choixSalle.addEventListener("change", () => {
        champPlaces.value = choixSalle.selectedOptions[0].dataset.capacite || "";
    });
</script>
</body>
</html>
