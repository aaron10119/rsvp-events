<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, "Método no permitido", null, 405);
}

$codigo = trim($_GET['codigo'] ?? '');

if ($codigo === '') {
    jsonResponse(false, "Falta el parámetro codigo", null, 400);
}

$db = new DB();
$pdo = $db->connect();

$sql = "SELECT 
            i.id_invitado,
            i.id_evento,
            i.nombre,
            i.telefono,
            i.codigo_invitado,
            i.link_personalizado,
            i.pases,
            i.acompanantes_permitidos,
            i.numero_mesa,
            i.grupo_familiar,
            i.estado_invitado,
            i.cantidad_confirmada,
            i.mensaje_confirmacion,
            i.fecha_confirmacion,
            e.slug,
            e.nombre_evento
        FROM invitados i
        INNER JOIN eventos e ON e.id_evento = i.id_evento
        WHERE i.codigo_invitado = :codigo
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['codigo' => $codigo]);
$invitado = $stmt->fetch();

if (!$invitado) {
    jsonResponse(false, "Invitado no encontrado", null, 404);
}

$sqlAcompanantes = "SELECT id_acompanante, nombre
                    FROM acompanantes
                    WHERE id_invitado = :id_invitado
                    ORDER BY id_acompanante ASC";

$stmtAcompanantes = $pdo->prepare($sqlAcompanantes);
$stmtAcompanantes->execute([
    'id_invitado' => $invitado['id_invitado']
]);

$acompanantes = $stmtAcompanantes->fetchAll();

$invitado['acompanantes'] = $acompanantes;

jsonResponse(true, "Invitado encontrado", $invitado);