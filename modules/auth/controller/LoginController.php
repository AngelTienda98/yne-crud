<?php

require_once __DIR__ . "/../service/AuthService.php";

header("Content-Type: application/json");

$authService = new AuthService();

$accion = $_GET["accion"] ?? "";

switch ($accion) {

    // Valida los campos y devuelve el resultado de la autenticación en JSON.
    case "login":

        $username = $_POST["username"] ?? "";
        $password = $_POST["password"] ?? "";

        if (
            empty($username) ||
            empty($password)
        ) {

            echo json_encode([
                "success" => false,
                "message" => "Completa todos los campos"
            ]);

            exit;
        }

        echo json_encode(
            $authService->login(
                $username,
                $password
            )
        );

        break;

    // Cierra la sesión y confirma la operación en JSON.
    case "logout":

        $authService->logout();

        echo json_encode([
            "success" => true
        ]);

        break;


    // Responde con error cuando se solicita una acción desconocida.
    default:

        echo json_encode([
            "success" => false,
            "message" => "Acción no válida"
        ]);
}