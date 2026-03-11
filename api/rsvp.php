<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Método no permitido", null, 405);
}

$input = json_decode(file_get_contents("php://input"), true);

$id_evento = $input['id_evento'] ?? null;
$id_invitado = $input['id_invitado'] ?? null;
$asistencia = $input['asistencia'] ?? null; // si | no
$cantidad_confirmada = $input['cantidad_confirmada'] ?? 0;
$mensaje = trim($input['mensaje'] ?? '');

if (!$id_evento || !$id_invitado || !$asistencia) {
    jsonResponse(false, "Faltan campos obligatorios", null, 400);
}

if (!in_array($asistencia, ['si', 'no'])) {
    jsonResponse(false, "Valor de asistencia inválido", null, 400);
}

$db = new DB();
$pdo = $db->connect();

try {
    $pdo->beginTransaction();

    $checkSql = "SELECT id_invitado, pases 
                 FROM invitados 
                 WHERE id_invitado = :id_invitado 
                 AND id_evento = :id_evento
                 LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        'id_invitado' => $id_invitado,
        'id_evento' => $id_evento
    ]);
    $invitado = $checkStmt->fetch();

    if (!$invitado) {
        $pdo->rollBack();
        jsonResponse(false, "Invitado no válido para este evento", null, 404);
    }

    if ($asistencia === 'si') {
        $cantidad_confirmada = (int)$cantidad_confirmada;

        if ($cantidad_confirmada < 1) {
            $pdo->rollBack();
            jsonResponse(false, "La cantidad confirmada debe ser mínimo 1", null, 400);
        }

        if ($cantidad_confirmada > (int)$invitado['pases']) {
            $pdo->rollBack();
            jsonResponse(false, "La cantidad excede los pases permitidos", null, 400);
        }
    } else {
        $cantidad_confirmada = 0;
    }

    $deleteSql = "DELETE FROM rsvp_respuestas WHERE id_invitado = :id_invitado";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute(['id_invitado' => $id_invitado]);

    $insertSql = "INSERT INTO rsvp_respuestas
                    (id_evento, id_invitado, asistencia, cantidad_confirmada, mensaje, origen)
                  VALUES
                    (:id_evento, :id_invitado, :asistencia, :cantidad_confirmada, :mensaje, 'web')";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        'id_evento' => $id_evento,
        'id_invitado' => $id_invitado,
        'asistencia' => $asistencia,
        'cantidad_confirmada' => $cantidad_confirmada,
        'mensaje' => $mensaje
    ]);

    $nuevoEstado = $asistencia === 'si' ? 'confirmado' : 'rechazado';

    $updateSql = "UPDATE invitados 
                  SET estado_invitado = :estado
                  WHERE id_invitado = :id_invitado";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        'estado' => $nuevoEstado,
        'id_invitado' => $id_invitado
    ]);

    $pdo->commit();

    jsonResponse(true, "RSVP guardado correctamente", [
        "estado_invitado" => $nuevoEstado
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, "Error al guardar RSVP", null, 500);
}