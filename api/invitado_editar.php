<?php
header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once "../config/db.php";
require_once "../helpers/response.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Método no permitido", null, 405);
}

if (!isset($_SESSION['usuario'])) {
    jsonResponse(false, "No autenticado", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);

$id_usuario = $_SESSION['usuario']['id_usuario'];
$id_invitado = $input['id_invitado'] ?? null;
$nombre = trim($input['nombre'] ?? '');
$telefono = trim($input['telefono'] ?? '');
$pases = isset($input['pases']) ? (int)$input['pases'] : null;
$acompanantes_permitidos = isset($input['acompanantes_permitidos']) ? (int)$input['acompanantes_permitidos'] : null;
$numero_mesa = trim($input['numero_mesa'] ?? '');
$grupo_familiar = trim($input['grupo_familiar'] ?? '');

if (!$id_invitado || $nombre === '' || $pases === null || $acompanantes_permitidos === null) {
    jsonResponse(false, "Faltan campos obligatorios", null, 400);
}

if ($pases < 1) {
    jsonResponse(false, "Los pases deben ser mínimo 1", null, 400);
}

if ($acompanantes_permitidos < 0) {
    jsonResponse(false, "Acompañantes permitidos inválidos", null, 400);
}

$db = new DB();
$pdo = $db->connect();

try {
    $checkSql = "SELECT i.id_invitado
                 FROM invitados i
                 INNER JOIN eventos e ON e.id_evento = i.id_evento
                 WHERE i.id_invitado = :id_invitado
                 AND e.id_usuario = :id_usuario
                 LIMIT 1";

    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        'id_invitado' => $id_invitado,
        'id_usuario' => $id_usuario
    ]);

    $invitado = $checkStmt->fetch();

    if (!$invitado) {
        jsonResponse(false, "Invitado no encontrado o sin permisos", null, 404);
    }

    $sql = "UPDATE invitados
            SET nombre = :nombre,
                telefono = :telefono,
                pases = :pases,
                acompanantes_permitidos = :acompanantes_permitidos,
                numero_mesa = :numero_mesa,
                grupo_familiar = :grupo_familiar
            WHERE id_invitado = :id_invitado";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'nombre' => $nombre,
        'telefono' => $telefono !== '' ? $telefono : null,
        'pases' => $pases,
        'acompanantes_permitidos' => $acompanantes_permitidos,
        'numero_mesa' => $numero_mesa !== '' ? $numero_mesa : null,
        'grupo_familiar' => $grupo_familiar !== '' ? $grupo_familiar : null,
        'id_invitado' => $id_invitado
    ]);

    jsonResponse(true, "Invitado actualizado correctamente");

} catch (Exception $e) {
    jsonResponse(false, "Error al actualizar invitado", null, 500);
}