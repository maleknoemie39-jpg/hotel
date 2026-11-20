<?php
date_default_timezone_set('Europe/Paris');

// Informations utilisées pour se connecter à la base de données locale.
$host = "localhost";
$dbname = "hotel";
$username ="root";
$password = "";
$conn = mysqli_connect($host, $username, $password, $dbname);

// Arrêter la page si la connexion à la base de données échoue.
if (!$conn) {
    die("Erreur connexion BDD");
}

mysqli_set_charset($conn, "utf8mb4");
?>