<?php

/*
 * AUTOR: AngelTienda
 * Fecha de creación del archivo: 2026-09-26
 */

require_once __DIR__ . "/../../modules/productos/implement/ProductoDAOImpl.php";
require_once __DIR__ . "/../../public/middleware/Permission.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

/** Envía una respuesta JSON con su código HTTP y cierra la petición. */
function responderApiProducto($datos, $codigo) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lee un objeto JSON y rechaza tipos de contenido o cuerpos mal formados. */
function leerEntradaJsonApiProducto() {
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";
    if (stripos($contentType, "application/json") !== 0) {
        responderApiProducto([
            "success" => false,
            "message" => "Envía el cuerpo con Content-Type: application/json"
        ], 415);
    }

    try {
        $entrada = json_decode(file_get_contents("php://input"), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        responderApiProducto([
            "success" => false,
            "message" => "El cuerpo no contiene JSON válido"
        ], 400);
    }

    $esLista = is_array($entrada) && (
        $entrada === [] || array_keys($entrada) === range(0, count($entrada) - 1)
    );
    if (!is_array($entrada) || $esLista) {
        responderApiProducto([
            "success" => false,
            "message" => "El cuerpo JSON debe ser un objeto con los datos del producto"
        ], 400);
    }

    return $entrada;
}

/** Valida el ID de la query string usado por GET, PUT y DELETE. */
function obtenerIdApiProducto() {
    $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        responderApiProducto([
            "success" => false,
            "message" => "Indica un ID de producto válido en la URL"
        ], 422);
    }

    return $id;
}

/** Valida y normaliza los campos antes de entregarlos al modelo/DAO. */
function validarDatosApiProducto(array $entrada) {
    $camposTexto = [
        "nombre" => 150,
        "descripcion" => 65535,
        "presentacion" => 100,
        "lote" => 80,
        "fecha_caducidad" => 10,
        "categoria" => 100,
        "estatus" => 30
    ];
    $datos = [];

    foreach ($camposTexto as $campo => $longitudMaxima) {
        $valor = $entrada[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === "") {
            throw new InvalidArgumentException("El campo {$campo} es obligatorio.");
        }

        $valor = trim($valor);
        if (mb_strlen($valor) > $longitudMaxima) {
            throw new InvalidArgumentException("El campo {$campo} excede su longitud máxima.");
        }
        $datos[$campo] = $valor;
    }

    $cantidadEntrada = $entrada["cantidad"] ?? null;
    if (
        !(is_int($cantidadEntrada) || (is_string($cantidadEntrada) && preg_match('/^\d+$/', $cantidadEntrada)))
    ) {
        throw new InvalidArgumentException("La cantidad debe ser un entero igual o mayor que cero.");
    }
    $cantidad = filter_var($cantidadEntrada, FILTER_VALIDATE_INT);
    if ($cantidad === false || $cantidad < 0 || $cantidad > 4294967295) {
        throw new InvalidArgumentException("La cantidad debe estar entre 0 y 4,294,967,295.");
    }
    $datos["cantidad"] = $cantidad;

    $precio = $entrada["precio"] ?? null;
    if (
        !(is_int($precio) || is_float($precio) || is_string($precio)) ||
        !is_numeric($precio) ||
        !is_finite((float) $precio) ||
        (float) $precio < 0 ||
        (float) $precio > 99999999.99
    ) {
        throw new InvalidArgumentException("Ingresa un precio válido y no negativo.");
    }
    $datos["precio"] = number_format((float) $precio, 2, ".", "");

    $fecha = DateTime::createFromFormat("!Y-m-d", $datos["fecha_caducidad"]);
    if (!$fecha || $fecha->format("Y-m-d") !== $datos["fecha_caducidad"]) {
        throw new InvalidArgumentException("Ingresa una fecha de caducidad válida.");
    }

    if (!in_array($datos["estatus"], ["Activo", "Rechazado", "Cancelado", "Sin existencia"], true)) {
        throw new InvalidArgumentException("El estatus seleccionado no es válido.");
    }

    return [
        "nombre" => $datos["nombre"],
        "descripcion" => $datos["descripcion"],
        "presentacion" => $datos["presentacion"],
        "lote" => $datos["lote"],
        "cantidad" => $datos["cantidad"],
        "precio" => $datos["precio"],
        "fecha_caducidad" => $datos["fecha_caducidad"],
        "categoria" => $datos["categoria"],
        "estatus" => $datos["estatus"]
    ];
}

try {
    // Enruta la operación REST según el verbo HTTP.
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    switch ($metodo) {
        // GET lista productos o devuelve uno si se indica ?id=.
        case "GET":
            if (!Permission::puedeVisualizar()) {
                responderApiProducto(["success" => false, "message" => "No tienes permiso para consultar productos"], 403);
            }

            $dao = new ProductoDAOImpl();
            if (isset($_GET["id"])) {
                $id = obtenerIdApiProducto();
                $producto = $dao->buscarPorId($id);
                if (!$producto) {
                    responderApiProducto(["success" => false, "message" => "Producto no encontrado"], 404);
                }
                responderApiProducto(["success" => true, "data" => $producto], 200);
            }

            responderApiProducto(["success" => true, "data" => $dao->listar()], 200);

        // POST crea un producto y su ubicación inicial; requiere ADMIN o MODERADOR.
        case "POST":
            if (!Permission::puedeCrear()) {
                responderApiProducto(["success" => false, "message" => "No tienes permiso para crear productos"], 403);
            }

            $datos = validarDatosApiProducto(leerEntradaJsonApiProducto());
            $resultado = (new ProductoDAOImpl())->crear(new Producto($datos));
            responderApiProducto([
                "success" => true,
                "message" => "Producto creado correctamente",
                "data" => [
                    "id" => $resultado["id"],
                    "nombre" => $datos["nombre"],
                    "cantidad" => $datos["cantidad"],
                    "requiere_reabastecimiento" => $resultado["requiere_reabastecimiento"]
                ]
            ], 201);

        // PUT reemplaza los datos editables del producto indicado por ID.
        case "PUT":
            if (!Permission::puedeModificar()) {
                responderApiProducto(["success" => false, "message" => "No tienes permiso para modificar productos"], 403);
            }

            $id = obtenerIdApiProducto();
            $dao = new ProductoDAOImpl();
            if (!$dao->buscarPorId($id)) {
                responderApiProducto(["success" => false, "message" => "Producto no encontrado"], 404);
            }
            $datos = validarDatosApiProducto(leerEntradaJsonApiProducto());
            if (!$dao->actualizar($id, new Producto($datos))) {
                responderApiProducto([
                    "success" => false,
                    "message" => "La cantidad no puede ser menor que las piezas guardadas en racks"
                ], 409);
            }

            responderApiProducto([
                "success" => true,
                "message" => "Producto actualizado correctamente",
                "data" => array_merge(["id" => $id], $datos)
            ], 200);

        // DELETE requiere permiso de ADMIN y conserva productos con historial.
        case "DELETE":
            if (!Permission::puedeEliminar()) {
                responderApiProducto(["success" => false, "message" => "No tienes permiso para eliminar productos"], 403);
            }

            $id = obtenerIdApiProducto();
            $resultado = (new ProductoDAOImpl())->eliminar($id);
            if ($resultado === "tiene_movimientos") {
                responderApiProducto([
                    "success" => false,
                    "message" => "No se puede eliminar un producto con historial de movimientos o recepciones"
                ], 409);
            }
            if (!$resultado) {
                responderApiProducto(["success" => false, "message" => "Producto no encontrado"], 404);
            }

            responderApiProducto([
                "success" => true,
                "message" => "Producto eliminado correctamente",
                "data" => ["id" => $id]
            ], 200);

        default:
            header("Allow: GET, POST, PUT, DELETE");
            responderApiProducto([
                "success" => false,
                "message" => "Método no permitido. Usa GET, POST, PUT o DELETE"
            ], 405);
    }
// Los errores de validación usan 422; fallos inesperados quedan en el log y responden 500.
} catch (InvalidArgumentException $error) {
    responderApiProducto([
        "success" => false,
        "message" => $error->getMessage()
    ], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    responderApiProducto([
        "success" => false,
        "message" => "Ocurrió un error al procesar la solicitud de productos"
    ], 500);
}
