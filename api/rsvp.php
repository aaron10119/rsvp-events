<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Método no permitido", null, 405);
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    jsonResponse(false, "JSON inválido", null, 400);
}

$id_evento = $input['id_evento'] ?? null;
$id_invitado = $input['id_invitado'] ?? null;
$asistencia = $input['asistencia'] ?? null; // si | no
$cantidad_confirmada = isset($input['cantidad_confirmada']) ? (int)$input['cantidad_confirmada'] : 0;
$mensaje = trim($input['mensaje'] ?? '');
$acompanantes = $input['acompanantes'] ?? [];

if (!$id_evento || !$id_invitado || !$asistencia) {
    jsonResponse(false, "Faltan campos obligatorios", null, 400);
}

if (!in_array($asistencia, ['si', 'no'], true)) {
    jsonResponse(false, "Valor de asistencia inválido", null, 400);
}

if (!is_array($acompanantes)) {
    jsonResponse(false, "El campo acompanantes debe ser un arreglo", null, 400);
}

$db = new DB();
$pdo = $db->connect();

try {
    $pdo->beginTransaction();

    $checkSql = "SELECT 
                    id_invitado,
                    pases,
                    acompanantes_permitidos
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
        if ($cantidad_confirmada < 1) {
            $pdo->rollBack();
            jsonResponse(false, "La cantidad confirmada debe ser mínimo 1", null, 400);
        }

        if ($cantidad_confirmada > (int)$invitado['pases']) {
            $pdo->rollBack();
            jsonResponse(false, "La cantidad excede los pases permitidos", null, 400);
        }

        $cantidadAcompanantes = count($acompanantes);

        if ($cantidadAcompanantes > (int)$invitado['acompanantes_permitidos']) {
            $pdo->rollBack();
            jsonResponse(false, "La cantidad de acompañantes excede lo permitido", null, 400);
        }

        // Opcional pero recomendable:
        // cantidad_confirmada = titular + acompañantes
        // Si quieres esta regla estricta, descomenta:
        /*
        if ($cantidad_confirmada !== ($cantidadAcompanantes + 1)) {
            $pdo->rollBack();
            jsonResponse(false, "La cantidad confirmada no coincide con titular + acompañantes", null, 400);
        }
        */
    } else {
        $cantidad_confirmada = 0;
        $acompanantes = [];
    }

    $deleteRsvpSql = "DELETE FROM rsvp_respuestas WHERE id_invitado = :id_invitado";
    $deleteRsvpStmt = $pdo->prepare($deleteRsvpSql);
    $deleteRsvpStmt->execute([
        'id_invitado' => $id_invitado
    ]);

    $insertRsvpSql = "INSERT INTO rsvp_respuestas
                        (id_evento, id_invitado, asistencia, cantidad_confirmada, mensaje, origen)
                      VALUES
                        (:id_evento, :id_invitado, :asistencia, :cantidad_confirmada, :mensaje, 'web')";
    $insertRsvpStmt = $pdo->prepare($insertRsvpSql);
    $insertRsvpStmt->execute([
        'id_evento' => $id_evento,
        'id_invitado' => $id_invitado,
        'asistencia' => $asistencia,
        'cantidad_confirmada' => $cantidad_confirmada,
        'mensaje' => $mensaje
    ]);

    $deleteAcompanantesSql = "DELETE FROM acompanantes WHERE id_invitado = :id_invitado";
    $deleteAcompanantesStmt = $pdo->prepare($deleteAcompanantesSql);
    $deleteAcompanantesStmt->execute([
        'id_invitado' => $id_invitado
    ]);

    if ($asistencia === 'si' && !empty($acompanantes)) {
        $insertAcompananteSql = "INSERT INTO acompanantes (id_invitado, nombre)
                                 VALUES (:id_invitado, :nombre)";
        $insertAcompananteStmt = $pdo->prepare($insertAcompananteSql);

        foreach ($acompanantes as $nombreAcompanante) {
            $nombreAcompanante = trim((string)$nombreAcompanante);

            if ($nombreAcompanante === '') {
                continue;
            }

            $insertAcompananteStmt->execute([
                'id_invitado' => $id_invitado,
                'nombre' => $nombreAcompanante
            ]);
        }
    }

    $nuevoEstado = $asistencia === 'si' ? 'confirmado' : 'rechazado';
    $fechaConfirmacion = date('Y-m-d H:i:s');

    $updateSql = "UPDATE invitados 
                  SET estado_invitado = :estado,
                      cantidad_confirmada = :cantidad_confirmada,
                      mensaje_confirmacion = :mensaje_confirmacion,
                      fecha_confirmacion = :fecha_confirmacion
                  WHERE id_invitado = :id_invitado";

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        'estado' => $nuevoEstado,
        'cantidad_confirmada' => $cantidad_confirmada,
        'mensaje_confirmacion' => $mensaje,
        'fecha_confirmacion' => $fechaConfirmacion,
        'id_invitado' => $id_invitado
    ]);

    $pdo->commit();

    jsonResponse(true, "RSVP guardado correctamente", [
        "estado_invitado" => $nuevoEstado,
        "cantidad_confirmada" => $cantidad_confirmada,
        "fecha_confirmacion" => $fechaConfirmacion
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, "Error al guardar RSVP", null, 500);
}