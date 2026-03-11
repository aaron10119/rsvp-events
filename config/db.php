<?php
class DB {
    private $host = "localhost";
    private $dbname = "bd_events";
    private $user = "root";
    private $pass = "";

    public function connect() {
        try {
            $pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "ok" => false,
                "message" => "Error de conexión a la base de datos"
            ]);
            exit;
        }
    }
}