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
            e.tipo_evento,
            e.nombre_evento,
            e.slug,
            e.paquete,
            e.fecha_evento,
            e.hora_evento,
            i.id_invitacion,
            i.titulo,
            i.subtitulo,
            i.nombre_festejado,
            i.fecha_texto,
            i.hora_texto,
            i.direccion,
            i.maps_url,
            i.texto_principal,
            i.texto_secundario,
            i.dress_code,
            i.mesa_regalos,
            i.imagen_portada,
            i.musica_url,
            i.galeria_json,
            i.mostrar_rsvp,
            i.fecha_limite_rsvp,
            p.id_plantilla,
            p.nombre_plantilla,
            p.clave_plantilla
        FROM eventos e
        INNER JOIN invitaciones i ON i.id_evento = e.id_evento
        INNER JOIN plantillas p ON p.id_plantilla = i.id_plantilla
        WHERE e.slug = :slug
        AND e.estatus = 'publicado'
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['slug' => $slug]);
$invitacion = $stmt->fetch();

if (!$invitacion) {
    jsonResponse(false, "Invitación no encontrada o no publicada", null, 404);
}

if (!empty($invitacion['galeria_json'])) {
    $invitacion['galeria'] = json_decode($invitacion['galeria_json'], true);
    unset($invitacion['galeria_json']);
}

jsonResponse(true, "Invitación encontrada", $invitacion);