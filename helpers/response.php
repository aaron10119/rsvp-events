<?php
function jsonResponse($ok, $message = "", $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        "ok" => $ok,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}