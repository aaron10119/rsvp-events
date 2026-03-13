<?php
header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once "../../helpers/response.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Método no permitido", null, 405);
}

session_unset();
session_destroy();

jsonResponse(true, "Sesión cerrada correctamente");