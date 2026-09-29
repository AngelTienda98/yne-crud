<?php

/** Centraliza las reglas de acceso a operaciones según el rol. */
class Permission {

    /** Permite crear registros a administradores y moderadores. */
    public static function puedeCrear() {

        self::verificarSesion();

        $rol =
            $_SESSION["usuario"]["rol"];

        return in_array(
            $rol,
            [
                "ADMIN",
                "MODERADOR"
            ]
        );
    }


    /** Permite modificar registros a administradores y moderadores. */
    public static function puedeModificar() {

        self::verificarSesion();

        $rol =
            $_SESSION["usuario"]["rol"];

        return in_array(
            $rol,
            [
                "ADMIN",
                "MODERADOR"
            ]
        );
    }


    /** Reserva la eliminación de registros al administrador. */
    public static function puedeEliminar() {

        self::verificarSesion();

        $rol =
            $_SESSION["usuario"]["rol"];

        return $rol === "ADMIN";
    }


    /** Confirma que el usuario autenticado puede visualizar registros. */
    public static function puedeVisualizar() {

        self::verificarSesion();

        return true;
    }


    /** Permite ejecutar movimientos simulados a administradores y moderadores. */
    public static function puedeMoverUbicaciones() {

        self::verificarSesion();

        return in_array(
            $_SESSION["usuario"]["rol"],
            ["ADMIN", "MODERADOR"],
            true
        );
    }


    /** Permite al ayudante atender solicitudes de reabasto sin ampliar sus permisos de inventario. */
    public static function puedeAceptarReabastecimiento() {

        self::verificarSesion();

        return in_array(
            $_SESSION["usuario"]["rol"],
            ["ADMIN", "MODERADOR", "AYUDANTE"],
            true
        );
    }


    /** Detiene la petición con HTTP 401 si no hay un usuario en sesión. */
    private static function verificarSesion() {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (
            !isset($_SESSION["usuario"])
        ) {

            http_response_code(401);

            echo json_encode([
                "success" => false,
                "message" => "Sesión no válida"
            ]);

            exit;
        }
    }
}