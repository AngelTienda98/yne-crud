<?php

class Database {

    private $host = "localhost";
    private $db = "ynecrud";
    private $user = "root";
    private $password = "";

    /** Crea y devuelve una conexión PDO a la base de datos configurada. */
    public function conectar() {
        try {

            $conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->db};charset=utf8",
                $this->user,
                $this->password
            );

            $conexion->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );
            return $conexion;
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
}