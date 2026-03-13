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
$id_evento = $input['id_evento'] ?? null;
$nombre = trim($input['nombre'] ?? '');
$telefono = trim($input['telefono'] ?? '');
$pases = isset($input['pases']) ? (int)$input['pases'] : 1;
$acompanantes_permitidos = isset($input['acompanantes_permitidos']) ? (int)$input['acompanantes_permitidos'] : 0;
$numero_mesa = trim($input['numero_mesa'] ?? '');
$grupo_familiar = trim($input['grupo_familiar'] ?? '');

if (!$id_evento || $nombre === '') {
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
    $checkSql = "SELECT id_evento, slug
                 FROM eventos
                 WHERE id_evento = :id_evento
                 AND id_usuario = :id_usuario
                 LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        'id_evento' => $id_evento,
        'id_usuario' => $id_usuario
    ]);
    $evento = $checkStmt->fetch();

    if (!$evento) {
        jsonResponse(false, "Evento no encontrado o sin permisos", null, 404);
    }

    // Generar código simple consecutivo por evento
    $countSql = "SELECT COUNT(*) AS total FROM invitados WHERE id_evento = :id_evento";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(['id_evento' => $id_evento]);
    $total = (int)$countStmt->fetch()['total'] + 1;

    $codigo_invitado = 'EV' . $id_evento . str_pad((string)$total, 3, '0', STR_PAD_LEFT);

    $link_personalizado = "http://localhost/events-api/frontend/invitado.html?codigo=" . urlencode($codigo_invitado);

    $sql = "INSERT INTO invitados (
                id_evento,
                nombre,
                telefono,
                codigo_invitado,
                link_personalizado,
                pases,
                acompanantes_permitidos,
                numero_mesa,
                grupo_familiar,
                estado_invitado,
                cantidad_confirmada
            ) VALUES (
                :id_evento,
                :nombre,
                :telefono,
                :codigo_invitado,
                :link_personalizado,
                :pases,
                :acompanantes_permitidos,
                :numero_mesa,
                :grupo_familiar,
                'pendiente',
                0
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id_evento' => $id_evento,
        'nombre' => $nombre,
        'telefono' => $telefono !== '' ? $telefono : null,
        'codigo_invitado' => $codigo_invitado,
        'link_personalizado' => $link_personalizado,
        'pases' => $pases,
        'acompanantes_permitidos' => $acompanantes_permitidos,
        'numero_mesa' => $numero_mesa !== '' ? $numero_mesa : null,
        'grupo_familiar' => $grupo_familiar !== '' ? $grupo_familiar : null
    ]);

    $id_invitado = $pdo->lastInsertId();

    jsonResponse(true, "Invitado agregado correctamente", [
        'id_invitado' => $id_invitado,
        'codigo_invitado' => $codigo_invitado,
        'link_personalizado' => $link_personalizado
    ]);

} catch (Exception $e) {
    jsonResponse(false, "Error al agregar invitado", null, 500);
}