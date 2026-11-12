<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class ApiError extends RuntimeException
{
    public int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        throw new ApiError('Méthode non autorisée.', 405);
    }

    $action = $_GET['action'] ?? '';
    if (!is_string($action)) {
        throw new ApiError('Action invalide.');
    }

    $pdo = database();

    if ($action === 'rooms') {
        $rooms = $pdo->query('SELECT id_salle, nom, capacite FROM salle ORDER BY nom')->fetchAll();
        jsonResponse(['rooms' => $rooms]);
    }

    if ($action === 'agenda') {
        $requestedWeek = $_GET['week'] ?? date('Y-m-d');
        if (!is_string($requestedWeek)) {
            throw new ApiError('La semaine demandée est invalide.');
        }

        $weekStart = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedWeek);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            !$weekStart
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            throw new ApiError('La semaine demandée est invalide.');
        }

        $statement = $pdo->prepare(
            "SELECT e.id_evenement, e.titre, e.description, e.type_evenement,
                    e.date_debut, e.date_fin, e.nombre_place,
                    GROUP_CONCAT(DISTINCT s.nom ORDER BY s.nom SEPARATOR ', ') AS salle,
                    COALESCE(GROUP_CONCAT(DISTINCT j.titre ORDER BY j.titre SEPARATOR ', '), '') AS jeux
             FROM evenement e
             INNER JOIN derouler d ON d.id_evenement = e.id_evenement
             INNER JOIN salle s ON s.id_salle = d.id_salle
             LEFT JOIN lier lj ON lj.id_evenement = e.id_evenement
             LEFT JOIN jeux j ON j.id_jeu = lj.id_jeu
             WHERE e.statut = 'PUBLIE'
               AND e.date_debut >= :week_start
               AND e.date_debut < DATE_ADD(:week_end, INTERVAL 1 DAY)
             GROUP BY e.id_evenement
             ORDER BY e.date_debut"
        );
        $weekEnd = $weekStart->modify('+6 days')->format('Y-m-d');
        $statement->execute([
            'week_start' => $weekStart->format('Y-m-d 00:00:00'),
            'week_end' => $weekEnd,
        ]);

        $events = array_map(static function (array $event): array {
            $capacity = (int) $event['nombre_place'];
            return [
                'id' => (int) $event['id_evenement'],
                'title' => $event['titre'],
                'description' => $event['description'],
                'type' => $event['type_evenement'],
                'start' => str_replace(' ', 'T', substr($event['date_debut'], 0, 16)),
                'end' => str_replace(' ', 'T', substr($event['date_fin'], 0, 16)),
                'capacity' => $capacity,
                'booked' => 0,
                'available' => $capacity,
                'room' => $event['salle'],
                'games' => $event['jeux'],
            ];
        }, $statement->fetchAll());

        jsonResponse(['events' => $events, 'week' => $weekStart->format('Y-m-d')]);
    }

    throw new ApiError('Cette ressource est introuvable.', 404);
} catch (ApiError $exception) {
    jsonResponse(['error' => $exception->getMessage()], $exception->httpStatus);
} catch (PDOException $exception) {
    error_log('Hotel Pixel database error: ' . $exception->getMessage());
    jsonResponse(['error' => 'Le planning est momentanément indisponible.'], 500);
} catch (Throwable $exception) {
    error_log('Hotel Pixel API error: ' . $exception->getMessage());
    jsonResponse(['error' => 'Une erreur est survenue. Réessayez plus tard.'], 500);
}
