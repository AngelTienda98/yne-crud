<?php

require_once __DIR__ . "/../../usuarios/implement/UsuarioDAOImpl.php";

/** Coordina la autenticación del usuario y el ciclo de su sesión. */
class AuthService {

    private $usuarioDAO;

    /** Prepara el DAO usado para consultar usuarios. */
    public function __construct() {

        $this->usuarioDAO =
            new UsuarioDAOImpl();
    }

    /** Verifica las credenciales y guarda los datos del usuario en sesión. */
    public function login($username, $password) {

        $usuario =
            $this->usuarioDAO
                ->buscarPorUsername($username);

        if ($usuario === null) {

            return [
                "success" => false,
                "message" => "Usuario o contraseña incorrectos"
            ];
        }

        if (
            !password_verify(
                $password,
                $usuario->getPassword()
            )
        ) {

            return [
                "success" => false,
                "message" => "Usuario o contraseña incorrectos"
            ];
        }

        session_start();

        session_regenerate_id(true);

        $_SESSION["usuario"] = [

            "id" =>
                $usuario->getId(),

            "username" =>
                $usuario->getUsername(),

            "nombre" =>
                $usuario->getNombre(),

            "rol" =>
                $usuario->getRol()

        ];

        return [
            "success" => true,
            "message" => "Login correcto",
            "rol" => $usuario->getRol()
        ];
    }

    /** Limpia y destruye la sesión actual. */
    public function logout() {

        session_start();

        $_SESSION = [];

        session_destroy();

        return true;
    }
}