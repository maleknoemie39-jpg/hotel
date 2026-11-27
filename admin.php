<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function admin_valeur_postee(string $nom): string
{
    $valeur = $_POST[$nom] ?? '';

    return is_string($valeur) ? trim($valeur) : '';
}

function admin_charger_evenement(mysqli $connexion, int $idEvenement): ?array
{
    $requete = mysqli_prepare(
        $connexion,
        'SELECT e.id_evenement, e.titre, e.type_evenement, e.description,
                e.date_debut, e.date_fin, e.nombre_place, e.statut,
                d.id_salle, l.id_jeu
         FROM evenement e
         LEFT JOIN derouler d ON d.id_evenement = e.id_evenement
         LEFT JOIN lier l ON l.id_evenement = e.id_evenement
         WHERE e.id_evenement = ?
         LIMIT 1'
    );
    mysqli_stmt_bind_param($requete, 'i', $idEvenement);
    mysqli_stmt_execute($requete);
    $evenement = mysqli_fetch_assoc(mysqli_stmt_get_result($requete));
    mysqli_stmt_close($requete);

    return $evenement ?: null;
}

$utilisateur = exiger_role('ADMIN');
$erreur = '';
$estEnvoi = $_SERVER['REQUEST_METHOD'] === 'POST';
$modeEdition = false;
$evenementEdition = null;
$idEditionFormulaire = 0;
$valeurs = [
    'titre' => '',
    'type' => '',
    'description' => '',
    'date_debut' => '',
    'date_fin' => '',
    'id_salle' => '',
    'id_jeu' => '',
    'statut' => 'PUBLIE',
];
$messagesSucces = [
    'cree' => 'L’événement a été créé.',
    'modifie' => 'L’événement a été modifié.',
    'supprime' => 'L’événement a été supprimé.',
];

foreach ($messagesSucces as $cle => $message) {
    if (($_GET[$cle] ?? '') === 'oui') {
        $messageSucces = $message;
        break;
    }
}

if (isset($_GET['modifier'])) {
    $idDemande = filter_input(INPUT_GET, 'modifier', FILTER_VALIDATE_INT);
    if ($idDemande === false || $idDemande === null || $idDemande < 1) {
        $erreur = 'L’identifiant de l’événement est invalide.';
    } else {
        $idEditionFormulaire = $idDemande;
        $evenementEdition = admin_charger_evenement($conn, $idDemande);
        if ($evenementEdition === null) {
            $erreur = 'Cet événement est introuvable.';
        } else {
            $modeEdition = true;
            $valeurs = [
                'titre' => $evenementEdition['titre'],
                'type' => $evenementEdition['type_evenement'],
                'description' => $evenementEdition['description'],
                'date_debut' => str_replace(' ', 'T', substr($evenementEdition['date_debut'], 0, 16)),
                'date_fin' => str_replace(' ', 'T', substr($evenementEdition['date_fin'], 0, 16)),
                'id_salle' => (string) ($evenementEdition['id_salle'] ?? ''),
                'id_jeu' => (string) ($evenementEdition['id_jeu'] ?? ''),
                'statut' => $evenementEdition['statut'] === 'Publié'
                    ? 'PUBLIE'
                    : $evenementEdition['statut'],
            ];
        }
    }
}

if ($estEnvoi && !verifier_csrf()) {
    $erreur = 'La page a expiré. Rechargez-la puis réessayez.';
}

if ($estEnvoi && $erreur === '') {
    $action = admin_valeur_postee('action');

    if ($action === 'supprimer') {
        $idEvenement = filter_input(INPUT_POST, 'id_evenement', FILTER_VALIDATE_INT);

        if ($idEvenement === false || $idEvenement === null || $idEvenement < 1) {
            $erreur = 'L’identifiant de l’événement est invalide.';
        } elseif (admin_charger_evenement($conn, $idEvenement) === null) {
            $erreur = 'Cet événement est introuvable.';
        } else {
            $requeteInscriptions = mysqli_prepare(
                $conn,
                'SELECT COUNT(*) AS total FROM inscrire WHERE id_evenement = ?'
            );
            mysqli_stmt_bind_param($requeteInscriptions, 'i', $idEvenement);
            mysqli_stmt_execute($requeteInscriptions);
            $inscriptions = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($requeteInscriptions))['total'];
            mysqli_stmt_close($requeteInscriptions);

            if ($inscriptions > 0) {
                $erreur = 'Cet événement ne peut pas être supprimé : des clients y sont déjà inscrits.';
            } else {
                $transactionOuverte = false;
                try {
                    mysqli_begin_transaction($conn);
                    $transactionOuverte = true;

                    foreach (['lier', 'derouler', 'gerer', 'evenement'] as $table) {
                        $requeteSuppression = mysqli_prepare(
                            $conn,
                            "DELETE FROM {$table} WHERE id_evenement = ?"
                        );
                        mysqli_stmt_bind_param($requeteSuppression, 'i', $idEvenement);
                        mysqli_stmt_execute($requeteSuppression);
                        mysqli_stmt_close($requeteSuppression);
                    }

                    mysqli_commit($conn);
                    header('Location: admin.php?supprime=oui');
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    if ($transactionOuverte) {
                        mysqli_rollback($conn);
                    }
                    error_log($exception->getMessage());
                    $erreur = 'L’événement n’a pas pu être supprimé. Réessayez.';
                }
            }
        }
    } elseif ($action === 'creer' || $action === 'modifier') {
        $valeurs = [
            'titre' => admin_valeur_postee('titre'),
            'type' => admin_valeur_postee('type'),
            'description' => admin_valeur_postee('description'),
            'date_debut' => admin_valeur_postee('date_debut'),
            'date_fin' => admin_valeur_postee('date_fin'),
            'id_salle' => admin_valeur_postee('id_salle'),
            'id_jeu' => admin_valeur_postee('id_jeu'),
            'statut' => admin_valeur_postee('statut'),
        ];
        $modeEdition = $action === 'modifier';
        $idEvenement = $modeEdition
            ? filter_input(INPUT_POST, 'id_evenement', FILTER_VALIDATE_INT)
            : null;
        if ($modeEdition && is_int($idEvenement) && $idEvenement > 0) {
            $idEditionFormulaire = $idEvenement;
            $evenementEdition = admin_charger_evenement($conn, $idEvenement);
        }
        $idSalle = filter_var($valeurs['id_salle'], FILTER_VALIDATE_INT);
        $idJeu = filter_var($valeurs['id_jeu'], FILTER_VALIDATE_INT);
        $debut = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valeurs['date_debut']);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valeurs['date_fin']);
        $statutsAutorises = ['PUBLIE', 'En attente', 'Brouillon'];

        if ($modeEdition && ($idEvenement === false || $idEvenement === null || $idEvenement < 1)) {
            $erreur = 'L’identifiant de l’événement est invalide.';
        } elseif ($modeEdition && $evenementEdition === null) {
            $erreur = 'Cet événement est introuvable.';
        } elseif ($valeurs['titre'] === '' || strlen($valeurs['titre']) > 100
            || $valeurs['type'] === '' || strlen($valeurs['type']) > 50
            || $valeurs['description'] === ''
            || !in_array($valeurs['statut'], $statutsAutorises, true)
        ) {
            $erreur = 'Renseignez le titre, le type, la description et le statut.';
        } elseif (
            $debut === false || $fin === false
            || $debut->format('Y-m-d\TH:i') !== $valeurs['date_debut']
            || $fin->format('Y-m-d\TH:i') !== $valeurs['date_fin']
        ) {
            $erreur = 'Les dates et heures indiquées ne sont pas valides.';
        } elseif ($fin <= $debut) {
            $erreur = 'La date de fin doit être postérieure à la date de début.';
        } elseif (
            $debut < new DateTimeImmutable('now')
            && (!$modeEdition || substr($evenementEdition['date_debut'], 0, 16) !== str_replace('T', ' ', $valeurs['date_debut']))
        ) {
            $erreur = 'Un nouvel événement ne peut pas être planifié dans le passé.';
        } elseif ($idSalle === false || $idJeu === false) {
            $erreur = 'Choisissez une salle et un jeu valides.';
        } else {
            $requeteSalleJeu = mysqli_prepare(
                $conn,
                'SELECT s.capacite
                 FROM salle s JOIN jeux j ON j.id_jeu = ?
                 WHERE s.id_salle = ?'
            );
            mysqli_stmt_bind_param($requeteSalleJeu, 'ii', $idJeu, $idSalle);
            mysqli_stmt_execute($requeteSalleJeu);
            $salle = mysqli_fetch_assoc(mysqli_stmt_get_result($requeteSalleJeu));
            mysqli_stmt_close($requeteSalleJeu);

            if ($salle === null || (int) $salle['capacite'] < 1) {
                $erreur = 'Le jeu ou la salle sélectionné est invalide.';
            }
        }

        if ($erreur === '') {
            $places = (int) $salle['capacite'];
            if ($modeEdition) {
                $requeteInscriptions = mysqli_prepare(
                    $conn,
                    'SELECT COUNT(*) AS total FROM inscrire WHERE id_evenement = ?'
                );
                mysqli_stmt_bind_param($requeteInscriptions, 'i', $idEvenement);
                mysqli_stmt_execute($requeteInscriptions);
                $inscriptions = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($requeteInscriptions))['total'];
                mysqli_stmt_close($requeteInscriptions);

                if ($inscriptions > $places) {
                    $erreur = 'La capacité de cette salle est inférieure au nombre de clients déjà inscrits.';
                }
            }
        }

        if ($erreur === '') {
            $debutSql = $debut->format('Y-m-d H:i:s');
            $finSql = $fin->format('Y-m-d H:i:s');
            $transactionOuverte = false;

            try {
                mysqli_begin_transaction($conn);
                $transactionOuverte = true;

                if ($modeEdition) {
                    $requeteEvenement = mysqli_prepare(
                        $conn,
                        'UPDATE evenement
                         SET titre = ?, type_evenement = ?, description = ?,
                             date_debut = ?, date_fin = ?, nombre_place = ?, statut = ?
                         WHERE id_evenement = ?'
                    );
                    mysqli_stmt_bind_param(
                        $requeteEvenement,
                        'sssssisi',
                        $valeurs['titre'],
                        $valeurs['type'],
                        $valeurs['description'],
                        $debutSql,
                        $finSql,
                        $places,
                        $valeurs['statut'],
                        $idEvenement
                    );
                    mysqli_stmt_execute($requeteEvenement);
                    mysqli_stmt_close($requeteEvenement);

                    foreach (['derouler', 'lier'] as $table) {
                        $requeteSuppression = mysqli_prepare(
                            $conn,
                            "DELETE FROM {$table} WHERE id_evenement = ?"
                        );
                        mysqli_stmt_bind_param($requeteSuppression, 'i', $idEvenement);
                        mysqli_stmt_execute($requeteSuppression);
                        mysqli_stmt_close($requeteSuppression);
                    }
                } else {
                    $requeteEvenement = mysqli_prepare(
                        $conn,
                        'INSERT INTO evenement
                         (titre, type_evenement, description, date_debut, date_fin,
                          nombre_place, statut, date_creation)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    mysqli_stmt_bind_param(
                        $requeteEvenement,
                        'sssssis',
                        $valeurs['titre'],
                        $valeurs['type'],
                        $valeurs['description'],
                        $debutSql,
                        $finSql,
                        $places,
                        $valeurs['statut']
                    );
                    mysqli_stmt_execute($requeteEvenement);
                    $idEvenement = mysqli_insert_id($conn);
                    mysqli_stmt_close($requeteEvenement);

                    $requeteResponsable = mysqli_prepare(
                        $conn,
                        'INSERT INTO gerer (id_utilisateur, id_evenement) VALUES (?, ?)'
                    );
                    mysqli_stmt_bind_param($requeteResponsable, 'ii', $utilisateur['id'], $idEvenement);
                    mysqli_stmt_execute($requeteResponsable);
                    mysqli_stmt_close($requeteResponsable);
                }

                $requeteSalle = mysqli_prepare(
                    $conn,
                    'INSERT INTO derouler (id_evenement, id_salle) VALUES (?, ?)'
                );
                mysqli_stmt_bind_param($requeteSalle, 'ii', $idEvenement, $idSalle);
                mysqli_stmt_execute($requeteSalle);
                mysqli_stmt_close($requeteSalle);

                $requeteJeu = mysqli_prepare(
                    $conn,
                    'INSERT INTO lier (id_evenement, id_jeu) VALUES (?, ?)'
                );
                mysqli_stmt_bind_param($requeteJeu, 'ii', $idEvenement, $idJeu);
                mysqli_stmt_execute($requeteJeu);
                mysqli_stmt_close($requeteJeu);

                mysqli_commit($conn);
                header('Location: admin.php?' . ($modeEdition ? 'modifie' : 'cree') . '=oui');
                exit;
            } catch (mysqli_sql_exception $exception) {
                if ($transactionOuverte) {
                    mysqli_rollback($conn);
                }
                error_log($exception->getMessage());
                $erreur = 'L’événement n’a pas pu être enregistré. Vérifiez la base de données puis réessayez.';
            }
        }
    } else {
        $erreur = 'Action non reconnue.';
    }
}

$salles = mysqli_query($conn, 'SELECT id_salle, nom, capacite FROM salle ORDER BY nom')->fetch_all(MYSQLI_ASSOC);
$jeux = mysqli_query($conn, 'SELECT id_jeu, titre FROM jeux ORDER BY titre')->fetch_all(MYSQLI_ASSOC);
$libellesStatut = [
    'PUBLIE' => 'Publié',
    'Publié' => 'Publié',
    'En attente' => 'En attente',
    'EN_ATTENTE' => 'En attente',
    'Brouillon' => 'Brouillon',
    'BROUILLON' => 'Brouillon',
];
$classesStatut = [
    'PUBLIE' => 'publie',
    'Publié' => 'publie',
    'En attente' => 'attente',
    'EN_ATTENTE' => 'attente',
    'Brouillon' => 'brouillon',
    'BROUILLON' => 'brouillon',
];
$requeteListeEvenements = mysqli_prepare(
    $conn,
    "SELECT e.id_evenement, e.titre, e.date_debut, e.date_fin, e.statut,
            GROUP_CONCAT(DISTINCT s.nom ORDER BY s.nom SEPARATOR ', ') AS salle,
            GROUP_CONCAT(DISTINCT j.titre ORDER BY j.titre SEPARATOR ', ') AS jeu
     FROM evenement e
     LEFT JOIN derouler d ON d.id_evenement = e.id_evenement
     LEFT JOIN salle s ON s.id_salle = d.id_salle
     LEFT JOIN lier l ON l.id_evenement = e.id_evenement
     LEFT JOIN jeux j ON j.id_jeu = l.id_jeu
     GROUP BY e.id_evenement, e.titre, e.date_debut, e.date_fin, e.statut
     ORDER BY e.date_debut DESC"
);
mysqli_stmt_execute($requeteListeEvenements);
$evenements = mysqli_fetch_all(mysqli_stmt_get_result($requeteListeEvenements), MYSQLI_ASSOC);
mysqli_stmt_close($requeteListeEvenements);
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
        <a class="logo" href="index.php">
            <img src="logo-hotel.png" alt="">
            <span>Les événements de l’hôtel<small>ESPACE ADMINISTRATEUR</small></span>
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
        <h1>Gestion des événements</h1>
        <?php if ($erreur !== ''): ?>
            <p class="message-erreur" role="alert"><?= h($erreur) ?></p>
        <?php endif; ?>
        <?php if (isset($messageSucces)): ?>
            <p class="message-info" role="status"><?= h($messageSucces) ?></p>
        <?php endif; ?>

        <form id="formulaire-evenement" class="formulaire formulaire-evenement" method="post">
            <h2><?= $modeEdition ? 'Modifier un événement' : 'Créer un événement' ?></h2>
            <input type="hidden" name="jeton_csrf" value="<?= h(jeton_csrf()) ?>">
            <input type="hidden" name="action" value="<?= $modeEdition ? 'modifier' : 'creer' ?>">
            <?php if ($modeEdition): ?>
                <input type="hidden" name="id_evenement" value="<?= $idEditionFormulaire ?>">
            <?php endif; ?>
            <label for="titre">Nom de l’événement</label>
            <input id="titre" name="titre" maxlength="100" value="<?= h($valeurs['titre']) ?>" required>
            <label for="type">Type d’animation</label>
            <input id="type" name="type" maxlength="50" placeholder="Tournoi, atelier, soirée…" value="<?= h($valeurs['type']) ?>" required>
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" required><?= h($valeurs['description']) ?></textarea>
            <div class="champs-cotes">
                <div>
                    <label for="date_debut">Début</label>
                    <input id="date_debut" type="datetime-local" name="date_debut" value="<?= h($valeurs['date_debut']) ?>" required>
                </div>
                <div>
                    <label for="date_fin">Fin</label>
                    <input id="date_fin" type="datetime-local" name="date_fin" value="<?= h($valeurs['date_fin']) ?>" required>
                </div>
            </div>
            <div class="champs-cotes">
                <div>
                    <label for="id_salle">Salle</label>
                    <select id="id_salle" name="id_salle" required>
                        <option value="">Choisir une salle</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= (int) $salle['id_salle'] ?>" <?= $valeurs['id_salle'] === (string) $salle['id_salle'] ? 'selected' : '' ?>>
                                <?= h($salle['nom']) ?> (<?= (int) $salle['capacite'] ?> places)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_jeu">Jeu</label>
                    <select id="id_jeu" name="id_jeu" required>
                        <option value="">Choisir un jeu</option>
                        <?php foreach ($jeux as $jeu): ?>
                            <option value="<?= (int) $jeu['id_jeu'] ?>" <?= $valeurs['id_jeu'] === (string) $jeu['id_jeu'] ? 'selected' : '' ?>>
                                <?= h($jeu['titre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="statut">Statut</label>
            <select id="statut" name="statut" required>
                <option value="PUBLIE" <?= $valeurs['statut'] === 'PUBLIE' ? 'selected' : '' ?>>Publié</option>
                <option value="En attente" <?= $valeurs['statut'] === 'En attente' ? 'selected' : '' ?>>En attente</option>
                <option value="Brouillon" <?= $valeurs['statut'] === 'Brouillon' ? 'selected' : '' ?>>Brouillon</option>
            </select>
            <div class="actions-formulaire">
                <button class="bouton" type="submit">
                    <?= $modeEdition ? 'Enregistrer les modifications' : 'Créer l’événement' ?>
                </button>
                <?php if ($modeEdition): ?>
                    <a class="bouton bouton-secondaire" href="admin.php">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="planning">
        <div class="titre-planning">
            <p class="petit-titre">Programme à venir</p>
            <h2>Tous les événements</h2>
        </div>
        <?php if ($evenements === []): ?>
            <p class="aucun-evenement">Aucun événement enregistré.</p>
        <?php else: ?>
            <div class="liste-evenements">
                <?php foreach ($evenements as $evenement): ?>
                    <article class="carte-evenement">
                        <div class="details-evenement">
                            <h3><?= h($evenement['titre']) ?></h3>
                                <span class="etiquette statut-<?= h($classesStatut[$evenement['statut']] ?? 'inconnu') ?>">
                                    <?= h($libellesStatut[$evenement['statut']] ?? $evenement['statut']) ?>
                                </span>
                            <p>
                                <?= h(date('d/m/Y à H\hi', strtotime($evenement['date_debut']))) ?>
                                · <?= h($evenement['salle'] ?? 'Salle à préciser') ?>
                                · <?= h($evenement['jeu'] ?? 'Jeu à préciser') ?>
                            </p>
                        </div>
                        <div class="actions-evenement">
                            <a class="bouton bouton-secondaire" href="admin.php?modifier=<?= (int) $evenement['id_evenement'] ?>#formulaire-evenement">
                                Modifier
                            </a>
                            <form method="post" onsubmit="return confirm('Supprimer cet événement ?');">
                                <input type="hidden" name="jeton_csrf" value="<?= h(jeton_csrf()) ?>">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_evenement" value="<?= (int) $evenement['id_evenement'] ?>">
                                <button class="bouton bouton-danger" type="submit">Supprimer</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
