<?php

require_once __DIR__ . "/../implement/UbicacionDAOImpl.php";
require_once __DIR__ . "/../../../public/middleware/AuthMiddleware.php";
require_once __DIR__ . "/../../../public/middleware/Permission.php";

header("Content-Type: application/json; charset=utf-8");

function responderUbicacion($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function validarMetodoUbicacion($metodo) {
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== $metodo) {
        header("Allow: " . $metodo);
        responderUbicacion(["success" => false, "message" => "Método no permitido"], 405);
    }
}

$accion = $_GET["accion"] ?? "";

try {
    $dao = new UbicacionDAOImpl();

    switch ($accion) {
        case "catalogos":
            Permission::puedeVisualizar();
            validarMetodoUbicacion("GET");
            responderUbicacion([
                "success" => true,
                "productos" => $dao->listarProductos(),
                "ubicaciones" => $dao->listarUbicaciones(),
                "montacarguistas" => $dao->listarMontacarguistas()
            ]);

        case "listar":
            Permission::puedeVisualizar();
            validarMetodoUbicacion("GET");
            responderUbicacion(["success" => true, "data" => $dao->listarUbicaciones()]);

        case "historial":
            Permission::puedeVisualizar();
            validarMetodoUbicacion("GET");
            responderUbicacion(["success" => true, "data" => $dao->listarMovimientos()]);

        case "reabastos":
            Permission::puedeVisualizar();
            validarMetodoUbicacion("GET");
            responderUbicacion([
                "success" => true,
                "data" => $dao->listarNotificacionesPendientes()
            ]);

        case "aceptar_reabasto":
            if (!Permission::puedeAceptarReabastecimiento()) {
                responderUbicacion(["success" => false, "message" => "No tienes permiso para recibir reabasto"], 403);
            }
            validarMetodoUbicacion("POST");

            $notificacionId = filter_var($_POST["notificacion_id"] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($_POST["cantidad"] ?? null, FILTER_VALIDATE_INT);
            if ($notificacionId === false || $notificacionId < 1 || $cantidad === false || $cantidad < 1) {
                responderUbicacion(["success" => false, "message" => "Indica una solicitud válida y una cantidad mayor que cero"], 422);
            }

            $usuario = AuthMiddleware::usuario();
            $recepcion = $dao->aceptarReabastecimiento(
                $notificacionId,
                $cantidad,
                $usuario["id"] ?? null,
                $usuario["nombre"] ?? $usuario["username"] ?? "Usuario"
            );
            responderUbicacion([
                "success" => true,
                "message" => "Reabasto recibido y registrado",
                "data" => $recepcion
            ]);

        case "mover":
            if (!Permission::puedeMoverUbicaciones()) {
                responderUbicacion(["success" => false, "message" => "Solo el administrador o el moderador pueden mover productos"], 403);
            }
            validarMetodoUbicacion("POST");

            $productoId = filter_var($_POST["producto_id"] ?? null, FILTER_VALIDATE_INT);
            $origenId = filter_var($_POST["ubicacion_origen_id"] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($_POST["cantidad"] ?? null, FILTER_VALIDATE_INT);
            $rack = trim($_POST["rack"] ?? "");
            $posicion = trim($_POST["posicion"] ?? "");
            $nivel = trim($_POST["nivel"] ?? "");
            $montacarguistas = $_POST["montacarguistas"] ?? [];

            if (
                $productoId === false || $productoId < 1 ||
                $origenId === false || $origenId < 1 ||
                $cantidad === false || $cantidad < 1 ||
                $rack === "" || $posicion === "" || $nivel === "" ||
                !is_array($montacarguistas) || count($montacarguistas) === 0
            ) {
                responderUbicacion(["success" => false, "message" => "Completa todos los datos y asigna al menos un montacarguista"], 422);
            }

            foreach (["rack" => $rack, "posicion" => $posicion, "nivel" => $nivel] as $campo => $valor) {
                if (mb_strlen($valor) > 100) {
                    responderUbicacion(["success" => false, "message" => "El campo {$campo} no puede exceder 100 caracteres"], 422);
                }
            }

            $usuario = AuthMiddleware::usuario();
            $resultado = $dao->mover(
                $productoId,
                $origenId,
                $rack,
                $posicion,
                $nivel,
                $cantidad,
                $montacarguistas,
                $usuario["id"] ?? null,
                $usuario["nombre"] ?? $usuario["username"] ?? "Usuario"
            );

            responderUbicacion([
                "success" => true,
                "message" => "Movimiento simulado y registrado correctamente",
                "data" => $resultado
            ]);

        default:
            responderUbicacion(["success" => false, "message" => "Acción no válida"], 400);
    }
} catch (DomainException $error) {
    responderUbicacion(["success" => false, "message" => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    responderUbicacion(["success" => false, "message" => "Ocurrió un error al procesar el movimiento"], 500);
}
