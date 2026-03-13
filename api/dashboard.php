<?php
header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once "../config/db.php";
require_once "../helpers/response.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, "Método no permitido", null, 405);
}

if (!isset($_SESSION['usuario'])) {
    jsonResponse(false, "No autenticado", null, 401);
}

$id_usuario = $_SESSION['usuario']['id_usuario'];

$db = new DB();
$pdo = $db->connect();

$sql = "SELECT 
            e.id_evento,
            e.nombre_evento,
            e.slug,
            e.fecha_evento,
            e.estatus,
            COUNT(i.id_invitado) AS total_invitados,
            SUM(CASE WHEN i.estado_invitado = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,
            SUM(CASE WHEN i.estado_invitado = 'rechazado' THEN 1 ELSE 0 END) AS rechazados,
            SUM(CASE WHEN i.estado_invitado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes
        FROM eventos e
        LEFT JOIN invitados i ON i.id_evento = e.id_evento
        WHERE e.id_usuario = :id_usuario
        GROUP BY e.id_evento
        ORDER BY e.id_evento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_usuario' => $id_usuario]);

$eventos = $stmt->fetchAll();

jsonResponse(true, "Dashboard cargado", $eventos);