<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, "Método no permitido", null, 405);
}

$slug = $_GET['slug'] ?? null;

if (!$slug) {
    jsonResponse(false, "Falta el parámetro slug", null, 400);
}

$db = new DB();
$pdo = $db->connect();

$sql = "SELECT 
            e.id_evento,
            e.id_usuario,
            e.tipo_evento,
            e.nombre_evento,
            e.slug,
            e.paquete,
            e.fecha_evento,
            e.hora_evento,
            e.estatus
        FROM eventos e
        WHERE e.slug = :slug
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['slug' => $slug]);
$evento = $stmt->fetch();

if (!$evento) {
    jsonResponse(false, "Evento no encontrado", null, 404);
}

jsonResponse(true, "Evento encontrado", $evento);