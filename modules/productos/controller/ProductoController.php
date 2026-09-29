<?php

require_once __DIR__ . "/../implement/ProductoDAOImpl.php";
require_once __DIR__ . "/../../../public/middleware/Permission.php";

header("Content-Type: application/json; charset=utf-8");

function responderProducto($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function verificarMetodoProducto($metodo) {
    if ($_SERVER["REQUEST_METHOD"] !== $metodo) {
        header("Allow: " . $metodo);
        responderProducto([
            "success" => false,
            "message" => "Método no permitido"
        ], 405);
    }
}

function obtenerDatosProducto() {
    $campos = [
        "nombre",
        "descripcion",
        "presentacion",
        "lote",
        "cantidad",
        "precio",
        "fecha_caducidad",
        "categoria",
        "estatus"
    ];
    $datos = [];

    foreach ($campos as $campo) {
        $valor = $_POST[$campo] ?? "";
        if (!is_string($valor) || trim($valor) === "") {
            responderProducto([
                "success" => false,
                "message" => "Completa todos los campos del producto"
            ], 422);
        }
        $datos[$campo] = trim($valor);
    }

    $cantidad = filter_var($datos["cantidad"], FILTER_VALIDATE_INT);
    if ($cantidad === false || $cantidad < 0) {
        responderProducto([
            "success" => false,
            "message" => "La cantidad debe ser un entero igual o mayor que cero"
        ], 422);
    }
    $datos["cantidad"] = $cantidad;

    if (!is_numeric($datos["precio"]) || (float) $datos["precio"] < 0 || (float) $datos["precio"] > 99999999.99) {
        responderProducto([
            "success" => false,
            "message" => "Ingresa un precio válido y no negativo"
        ], 422);
    }
    $datos["precio"] = number_format((float) $datos["precio"], 2, ".", "");

    $fecha = DateTime::createFromFormat("!Y-m-d", $datos["fecha_caducidad"]);
    if (!$fecha || $fecha->format("Y-m-d") !== $datos["fecha_caducidad"]) {
        responderProducto([
            "success" => false,
            "message" => "Ingresa una fecha de caducidad válida"
        ], 422);
    }

    $estatusPermitidos = ["Activo", "Rechazado", "Cancelado", "Sin existencia"];
    if (!in_array($datos["estatus"], $estatusPermitidos, true)) {
        responderProducto([
            "success" => false,
            "message" => "El estatus seleccionado no es válido"
        ], 422);
    }

    return $datos;
}

$accion = $_GET["accion"] ?? "";

try {
    switch ($accion) {
        case "listar":
            if (!Permission::puedeVisualizar()) {
                responderProducto(["success" => false, "message" => "No tienes permiso para visualizar productos"], 403);
            }
            verificarMetodoProducto("GET");
            $dao = new ProductoDAOImpl();
            responderProducto(["success" => true, "data" => $dao->listar()]);

        case "buscar":
            if (!Permission::puedeVisualizar()) {
                responderProducto(["success" => false, "message" => "No tienes permiso para visualizar productos"], 403);
            }
            verificarMetodoProducto("GET");
            $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responderProducto(["success" => false, "message" => "Identificador de producto no válido"], 422);
            }
            $producto = (new ProductoDAOImpl())->buscarPorId($id);
            if (!$producto) {
                responderProducto(["success" => false, "message" => "Producto no encontrado"], 404);
            }
            responderProducto(["success" => true, "data" => $producto]);

        case "crear":
            if (!Permission::puedeCrear()) {
                responderProducto(["success" => false, "message" => "No tienes permiso para crear productos"], 403);
            }
            verificarMetodoProducto("POST");
            $creado = (new ProductoDAOImpl())->crear(new Producto(obtenerDatosProducto()));
            responderProducto([
                "success" => (bool) $creado,
                "message" => "Producto creado correctamente",
                "requiere_reabastecimiento" => $creado["requiere_reabastecimiento"] ?? false
            ]);

        case "actualizar":
            if (!Permission::puedeModificar()) {
                responderProducto(["success" => false, "message" => "No tienes permiso para modificar productos"], 403);
            }
            verificarMetodoProducto("POST");
            $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responderProducto(["success" => false, "message" => "Identificador de producto no válido"], 422);
            }
            $dao = new ProductoDAOImpl();
            if (!$dao->buscarPorId($id)) {
                responderProducto(["success" => false, "message" => "Producto no encontrado"], 404);
            }
            $actualizado = $dao->actualizar($id, new Producto(obtenerDatosProducto()));
            if (!$actualizado) {
                responderProducto(["success" => false, "message" => "No se pudo actualizar el producto"], 404);
            }
            responderProducto(["success" => true, "message" => "Producto actualizado correctamente"]);

        case "eliminar":
            if (!Permission::puedeEliminar()) {
                responderProducto(["success" => false, "message" => "No tienes permiso para eliminar productos"], 403);
            }
            verificarMetodoProducto("POST");
            $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responderProducto(["success" => false, "message" => "Identificador de producto no válido"], 422);
            }
            $dao = new ProductoDAOImpl();
            $eliminado = $dao->eliminar($id);
            if ($eliminado === "tiene_movimientos") {
                responderProducto([
                    "success" => false,
                    "message" => "No se puede eliminar un producto con historial de movimientos"
                ], 409);
            }
            if (!$eliminado) {
                responderProducto(["success" => false, "message" => "Producto no encontrado"], 404);
            }
            responderProducto(["success" => true, "message" => "Producto eliminado correctamente"]);

        default:
            responderProducto(["success" => false, "message" => "Acción no válida"], 400);
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    responderProducto(["success" => false, "message" => "Ocurrió un error al procesar productos"], 500);
}
