<?php

/*
 * AUTOR: AngelTienda
 * Fecha de creación del archivo: 2026-09-26
 */

require_once __DIR__ . "/../implement/UsuarioDAOImpl.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

/** Devuelve la respuesta JSON con el código HTTP indicado y termina la petición. */
function responderUsuario($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Impide ejecutar una acción con un verbo HTTP distinto al esperado. */
function validarMetodoUsuario($metodo) {
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== $metodo) {
        header("Allow: " . $metodo);
        responderUsuario(["success" => false, "message" => "Método no permitido"], 405);
    }
}

/** Valida nombre, contraseña, rol y estado antes de guardar. */
function validarDatosUsuario($entrada, $esCreacion) {
    $username = trim($entrada["username"] ?? "");
    $nombre = trim($entrada["nombre"] ?? "");
    $password = $entrada["password"] ?? "";
    $rol = $entrada["rol"] ?? "";
    $activo = filter_var($entrada["activo"] ?? "1", FILTER_VALIDATE_INT);

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/D', $username)) {
        throw new InvalidArgumentException("El usuario debe tener de 3 a 50 caracteres: letras, números, punto, guion o guion bajo.");
    }
    if ($nombre === "" || mb_strlen($nombre) > 100) {
        throw new InvalidArgumentException("El nombre es obligatorio y no puede exceder 100 caracteres.");
    }
    if (!in_array($rol, ["ADMIN", "MODERADOR", "AYUDANTE"], true)) {
        throw new InvalidArgumentException("Selecciona un rol válido.");
    }
    if ($activo === false || !in_array($activo, [0, 1], true)) {
        throw new InvalidArgumentException("El estado activo debe ser válido.");
    }
    if (!is_string($password)) {
        throw new InvalidArgumentException("La contraseña no es válida.");
    }
    if ($esCreacion && strlen($password) < 6) {
        throw new InvalidArgumentException("La contraseña debe tener al menos 6 caracteres.");
    }
    if ($password !== "" && strlen($password) > 255) {
        throw new InvalidArgumentException("La contraseña excede el máximo permitido.");
    }

    return [
        "username" => $username,
        "nombre" => $nombre,
        "password" => $password,
        "rol" => $rol,
        "activo" => $activo
    ];
}

// Este controlador es administrativo y solo lo puede usar una sesión ADMIN.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION["usuario"])) {
    responderUsuario(["success" => false, "message" => "Inicia sesión para continuar"], 401);
}
if (($_SESSION["usuario"]["rol"] ?? "") !== "ADMIN") {
    responderUsuario(["success" => false, "message" => "Solo ADMIN puede administrar usuarios"], 403);
}

$dao = new UsuarioDAOImpl();
$accion = $_GET["accion"] ?? "";

try {
    switch ($accion) {
        // Lista los usuarios sin exponer sus contraseñas.
        case "listar":
            validarMetodoUsuario("GET");
            responderUsuario(["success" => true, "data" => $dao->listarUsuarios()]);

        // Devuelve los datos editables del usuario solicitado.
        case "buscar":
            validarMetodoUsuario("GET");
            $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responderUsuario(["success" => false, "message" => "ID de usuario no válido"], 422);
            }
            $usuario = $dao->obtenerUsuarioAdministracion($id);
            if (!$usuario) {
                responderUsuario(["success" => false, "message" => "Usuario no encontrado"], 404);
            }
            responderUsuario(["success" => true, "data" => $usuario]);

        // Valida, comprueba unicidad y crea un usuario con contraseña cifrada por el DAO.
        case "insertar":
            validarMetodoUsuario("POST");
            $datos = validarDatosUsuario($_POST, true);
            if ($dao->usernameExiste($datos["username"])) {
                responderUsuario(["success" => false, "message" => "Ese nombre de usuario ya está registrado"], 409);
            }
            if (!$dao->crearUsuario($datos)) {
                responderUsuario(["success" => false, "message" => "No se pudo crear el usuario"], 500);
            }
            responderUsuario(["success" => true, "message" => "Usuario creado correctamente"], 201);

        // Actualiza perfil y permisos; contraseña vacía conserva la actual.
        case "actualizar":
            validarMetodoUsuario("POST");
            $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responderUsuario(["success" => false, "message" => "ID de usuario no válido"], 422);
            }
            $actual = $dao->obtenerUsuarioAdministracion($id);
            if (!$actual) {
                responderUsuario(["success" => false, "message" => "Usuario no encontrado"], 404);
            }
            $datos = validarDatosUsuario($_POST, false);
            if ($dao->usernameExiste($datos["username"], $id)) {
                responderUsuario(["success" => false, "message" => "Ese nombre de usuario ya está registrado"], 409);
            }

            $esMiCuenta = (int) $id === (int) $_SESSION["usuario"]["id"];
            if ($esMiCuenta && ($datos["rol"] !== "ADMIN" || $datos["activo"] !== 1)) {
                responderUsuario(["success" => false, "message" => "No puedes quitarte tu propio acceso de administrador"], 409);
            }
            $quitaUltimoAdmin = $actual["rol"] === "ADMIN" && (int) $actual["activo"] === 1 &&
                ($datos["rol"] !== "ADMIN" || $datos["activo"] !== 1);
            if ($quitaUltimoAdmin && $dao->contarAdministradoresActivos() <= 1) {
                responderUsuario(["success" => false, "message" => "Debe permanecer al menos un ADMIN activo"], 409);
            }

            if (!$dao->actualizarUsuario($id, $datos)) {
                responderUsuario(["success" => false, "message" => "No se pudo actualizar el usuario"], 500);
            }
            responderUsuario(["success" => true, "message" => "Usuario actualizado correctamente"]);

        // Evita autoeliminación y protege al último administrador activo.
        case "eliminar":
            validarMetodoUsuario("POST");
            $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responderUsuario(["success" => false, "message" => "ID de usuario no válido"], 422);
            }
            if ((int) $id === (int) $_SESSION["usuario"]["id"]) {
                responderUsuario(["success" => false, "message" => "No puedes eliminar tu propia cuenta"], 409);
            }
            $usuario = $dao->obtenerUsuarioAdministracion($id);
            if (!$usuario) {
                responderUsuario(["success" => false, "message" => "Usuario no encontrado"], 404);
            }
            if ($usuario["rol"] === "ADMIN" && (int) $usuario["activo"] === 1 && $dao->contarAdministradoresActivos() <= 1) {
                responderUsuario(["success" => false, "message" => "Debe permanecer al menos un ADMIN activo"], 409);
            }
            if (!$dao->eliminarUsuario($id)) {
                responderUsuario(["success" => false, "message" => "No se pudo eliminar el usuario"], 500);
            }
            responderUsuario(["success" => true, "message" => "Usuario eliminado correctamente"]);

        default:
            responderUsuario(["success" => false, "message" => "Acción no válida"], 400);
    }
} catch (InvalidArgumentException $error) {
    responderUsuario(["success" => false, "message" => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    responderUsuario(["success" => false, "message" => "Ocurrió un error al procesar usuarios"], 500);
}