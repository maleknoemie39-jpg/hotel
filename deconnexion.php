<?php
declare(strict_types=1);

require_once __DIR__ . '/commun.php';

// Effacer la session puis revenir au planning public.
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
