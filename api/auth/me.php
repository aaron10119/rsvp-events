<?php
header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once "../../helpers/response.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, "Método no permitido", null, 405);
}

if (!isset($_SESSION['usuario'])) {
    jsonResponse(false, "No autenticado", null, 401);
}

jsonResponse(true, "Sesión activa", $_SESSION['usuario']);