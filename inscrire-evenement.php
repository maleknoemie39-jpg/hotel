<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$utilisateur = exiger_role('CLIENT');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Lire le numéro de l'événement envoyé par le formulaire.
$idEvenement = filter_input(INPUT_POST, 'id_evenement', FILTER_VALIDATE_INT);
if ($idEvenement === false || $idEvenement === null || $idEvenement < 1) {
    header('Location: index.php');
    exit;
}

if (!verifier_csrf()) {
    header('Location: index.php?message=erreur');
    exit;
}

// La transaction évite que deux réservations prennent la dernière place en même temps.
try {
    mysqli_begin_transaction($conn);

    // Verrouiller l'événement pendant la vérification des places.
    $requeteEvenement = mysqli_prepare(
        $conn,
        "SELECT nombre_place FROM evenement
         WHERE id_evenement = ? AND statut = 'PUBLIE' AND date_debut >= CURDATE()
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($requeteEvenement, 'i', $idEvenement);
    mysqli_stmt_execute($requeteEvenement);
    $evenement = mysqli_fetch_assoc(mysqli_stmt_get_result($requeteEvenement));
    mysqli_stmt_close($requeteEvenement);

    if ($evenement === null) {
        mysqli_rollback($conn);
        header('Location: index.php?message=indisponible');
        exit;
    }

    // Vérifier que le client n'a pas déjà réservé cet événement.
    $requeteInscription = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM inscrire
         WHERE id_evenement = ? AND id_utilisateur = ? AND statut = 'CONFIRMEE'"
    );
    mysqli_stmt_bind_param($requeteInscription, 'ii', $idEvenement, $utilisateur['id']);
    mysqli_stmt_execute($requeteInscription);
    $dejaInscrit = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($requeteInscription))['total'] > 0;
    mysqli_stmt_close($requeteInscription);

    if ($dejaInscrit) {
        mysqli_rollback($conn);
        header('Location: index.php?message=deja-inscrit');
        exit;
    }

    // Compter les réservations confirmées avant d'en ajouter une nouvelle.
    $requetePlaces = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM inscrire
         WHERE id_evenement = ? AND statut = 'CONFIRMEE'"
    );
    mysqli_stmt_bind_param($requetePlaces, 'i', $idEvenement);
    mysqli_stmt_execute($requetePlaces);
    $placesPrises = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($requetePlaces))['total'];
    mysqli_stmt_close($requetePlaces);

    if ($placesPrises >= (int) $evenement['nombre_place']) {
        mysqli_rollback($conn);
        header('Location: index.php?message=complet');
        exit;
    }

    // Enregistrer la réservation et la relier au client et à l'événement.
    $requeteReservation = mysqli_prepare(
        $conn,
        "INSERT INTO inscrire
         (id_utilisateur, id_evenement, date_inscription, statut, place_attribue)
         VALUES (?, ?, NOW(), 'CONFIRMEE', '1')
         ON DUPLICATE KEY UPDATE
            date_inscription = VALUES(date_inscription),
            statut = VALUES(statut),
            place_attribue = VALUES(place_attribue)"
    );
    mysqli_stmt_bind_param($requeteReservation, 'ii', $utilisateur['id'], $idEvenement);
    mysqli_stmt_execute($requeteReservation);
    mysqli_stmt_close($requeteReservation);

    mysqli_commit($conn);
    header('Location: index.php?message=inscrit');
    exit;
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($conn);
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Une erreur est survenue pendant l’inscription. Réessayez plus tard.');
}
