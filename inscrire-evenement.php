<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

$utilisateur = exiger_role('CLIENT');
$idEvenement = filter_input(INPUT_POST, 'id_evenement', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $idEvenement === false || $idEvenement === null || $idEvenement < 1) {
    header('Location: index.php');
    exit;
}

if (!verifier_csrf()) {
    header('Location: index.php?message=erreur');
    exit;
}

try {
    mysqli_begin_transaction($conn);

    $requete = mysqli_prepare(
        $conn,
        "SELECT nombre_place FROM evenement
         WHERE id_evenement = ? AND statut = 'PUBLIE' AND date_debut >= CURDATE()
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($requete, 'i', $idEvenement);
    mysqli_stmt_execute($requete);
    $evenement = mysqli_fetch_assoc(mysqli_stmt_get_result($requete));
    mysqli_stmt_close($requete);

    if ($evenement === null) {
        mysqli_rollback($conn);
        header('Location: index.php?message=indisponible');
        exit;
    }

    $requete = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM concerne c
         JOIN inscription i ON i.id_inscription = c.id_inscription
         JOIN inscrire u ON u.id_inscription = i.id_inscription
         WHERE c.id_evenement = ? AND u.id_utilisateur = ? AND i.statut = 'CONFIRMEE'"
    );
    mysqli_stmt_bind_param($requete, 'ii', $idEvenement, $utilisateur['id']);
    mysqli_stmt_execute($requete);
    $dejaInscrit = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($requete))['total'] > 0;
    mysqli_stmt_close($requete);

    if ($dejaInscrit) {
        mysqli_rollback($conn);
        header('Location: index.php?message=deja-inscrit');
        exit;
    }

    $requete = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM concerne c
         JOIN inscription i ON i.id_inscription = c.id_inscription
         WHERE c.id_evenement = ? AND i.statut = 'CONFIRMEE'"
    );
    mysqli_stmt_bind_param($requete, 'i', $idEvenement);
    mysqli_stmt_execute($requete);
    $placesPrises = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($requete))['total'];
    mysqli_stmt_close($requete);

    if ($placesPrises >= (int) $evenement['nombre_place']) {
        mysqli_rollback($conn);
        header('Location: index.php?message=complet');
        exit;
    }

    $requete = mysqli_prepare(
        $conn,
        "INSERT INTO inscription (date_inscription, statut, place_attribue)
         VALUES (NOW(), 'CONFIRMEE', '1')"
    );
    mysqli_stmt_execute($requete);
    $idInscription = mysqli_insert_id($conn);
    mysqli_stmt_close($requete);

    $requete = mysqli_prepare($conn, 'INSERT INTO inscrire (id_utilisateur, id_inscription) VALUES (?, ?)');
    mysqli_stmt_bind_param($requete, 'ii', $utilisateur['id'], $idInscription);
    mysqli_stmt_execute($requete);
    mysqli_stmt_close($requete);

    $requete = mysqli_prepare($conn, 'INSERT INTO concerne (id_inscription, id_evenement) VALUES (?, ?)');
    mysqli_stmt_bind_param($requete, 'ii', $idInscription, $idEvenement);
    mysqli_stmt_execute($requete);
    mysqli_stmt_close($requete);

    mysqli_commit($conn);
    header('Location: index.php?message=inscrit');
    exit;
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($conn);
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Une erreur est survenue pendant l’inscription. Réessayez plus tard.');
}
