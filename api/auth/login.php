<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/db.php";
require_once "../../helpers/response.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Método no permitido", null, 405);
}

$input = json_decode(file_get_contents("php://input"), true);

$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (!$email || !$password) {
    jsonResponse(false, "Email y password son requeridos", null, 400);
}

$db = new DB();
$pdo = $db->connect();

$sql = "SELECT id_usuario, nombre, email, password_hash, activo 
        FROM usuarios 
        WHERE email = :email 
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['email' => $email]);

$user = $stmt->fetch();

if (!$user) {
    jsonResponse(false, "Usuario no encontrado", null, 404);
}

if (!$user['activo']) {
    jsonResponse(false, "Usuario inactivo", null, 403);
}

if (!password_verify($password, $user['password_hash'])) {
    jsonResponse(false, "Contraseña incorrecta", null, 401);
}

unset($user['password_hash']);

jsonResponse(true, "Login correcto", $user);