<?php

/*
 * AUTOR: AngelTienda
 * Fecha de creación del archivo: 2026-09-26
 */

require_once __DIR__ . "/../../../config/conexion.php";
require_once __DIR__ . "/../model/Usuario.php";
require_once __DIR__ . "/../dao/UsuarioDAO.php";

/** Implementa las consultas de usuarios usando PDO. */
class UsuarioDAOImpl implements UsuarioDAO {

    private $conexion;

    /** Abre la conexión a la base de datos para las consultas. */
    public function __construct() {

        $database = new Database();

        $this->conexion = $database->conectar();
    }

    /** Devuelve los campos visibles en la tabla administrativa. */
    public function listarUsuarios() {
        $stmt = $this->conexion->query(
            "SELECT id, username, nombre, rol, activo, fecha_registro
             FROM usuario ORDER BY id DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Busca por ID incluyendo usuarios inactivos, sin devolver el hash. */
    public function obtenerUsuarioAdministracion($id) {
        $stmt = $this->conexion->prepare(
            "SELECT id, username, nombre, rol, activo, fecha_registro
             FROM usuario WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Cuenta administradores activos para no dejar el sistema sin admin. */
    public function contarAdministradoresActivos() {
        return (int) $this->conexion->query(
            "SELECT COUNT(*) FROM usuario WHERE rol = 'ADMIN' AND activo = 1"
        )->fetchColumn();
    }

    /** Detecta duplicados de username al crear o editar. */
    public function usernameExiste($username, $excluirId = null) {
        $sql = "SELECT COUNT(*) FROM usuario WHERE username = :username";
        $datos = ["username" => $username];
        if ($excluirId !== null) {
            $sql .= " AND id <> :id";
            $datos["id"] = $excluirId;
        }

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($datos);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Inserta el usuario usando password_hash antes de persistir la contraseña. */
    public function crearUsuario($datos) {
        $stmt = $this->conexion->prepare(
            "INSERT INTO usuario (username, password, nombre, rol, activo, fecha_registro)
             VALUES (:username, :password, :nombre, :rol, :activo, NOW())"
        );

        return $stmt->execute([
            "username" => $datos["username"],
            "password" => password_hash($datos["password"], PASSWORD_DEFAULT),
            "nombre" => $datos["nombre"],
            "rol" => $datos["rol"],
            "activo" => $datos["activo"]
        ]);
    }

    /** Actualiza el perfil y solo reemplaza el hash si se envió contraseña nueva. */
    public function actualizarUsuario($id, $datos) {
        $campos = [
            "username = :username",
            "nombre = :nombre",
            "rol = :rol",
            "activo = :activo"
        ];
        $parametros = [
            "id" => $id,
            "username" => $datos["username"],
            "nombre" => $datos["nombre"],
            "rol" => $datos["rol"],
            "activo" => $datos["activo"]
        ];

        if ($datos["password"] !== "") {
            $campos[] = "password = :password";
            $parametros["password"] = password_hash($datos["password"], PASSWORD_DEFAULT);
        }

        $stmt = $this->conexion->prepare(
            "UPDATE usuario SET " . implode(", ", $campos) . " WHERE id = :id"
        );

        return $stmt->execute($parametros);
    }

    /** Elimina el registro por ID y devuelve si se afectó una fila. */
    public function eliminarUsuario($id) {
        $stmt = $this->conexion->prepare(
            "DELETE FROM usuario WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);

        return $stmt->rowCount() > 0;
    }

    /** Obtiene un usuario activo por nombre de usuario o devuelve null. */
    public function buscarPorUsername($username) {

        $sql = "
            SELECT *
            FROM usuario
            WHERE username = ?
            AND activo = 1
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        $stmt->execute([$username]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Usuario(
            $row["id"],
            $row["username"],
            $row["password"],
            $row["nombre"],
            $row["rol"],
            $row["activo"]
        );
    }

    /** Obtiene un usuario activo por ID o devuelve null. */
    public function buscarPorId($id) {

        $sql = "
            SELECT *
            FROM usuario
            WHERE id = ?
            AND activo = 1
        ";

        $stmt = $this->conexion->prepare($sql);

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Usuario(
            $row["id"],
            $row["username"],
            $row["password"],
            $row["nombre"],
            $row["rol"],
            $row["activo"]
        );
    }
}