<?php

/** Protege páginas y permite consultar al usuario autenticado. */
class AuthMiddleware {

    /** Redirige al login cuando no existe una sesión autenticada. */
    public static function verificar() {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (
            !isset($_SESSION["usuario"])
        ) {

            header(
                "Location: ../index.php"
            );

            exit;
        }
    }


    /** Devuelve los datos del usuario guardados en la sesión. */
    public static function usuario() {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION["usuario"] ?? null;
    }


    /** Comprueba si el usuario autenticado tiene el rol indicado. */
    public static function tieneRol($rol) {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (
            !isset($_SESSION["usuario"])
        ) {
            return false;
        }

        return
            $_SESSION["usuario"]["rol"]
            === $rol;
    }
}