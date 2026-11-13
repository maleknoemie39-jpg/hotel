<?php
$host = "localhost";
$dbname = "hotel_pixel";
$username ="root";
$password = "";
$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Erreur connexion BDD");
}

mysqli_set_charset($conn, "utf8mb4");
?>