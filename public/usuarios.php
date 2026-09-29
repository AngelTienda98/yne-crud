<?php

/*
 * AUTOR: AngelTienda
 * Fecha de creación del archivo: 2026-09-26
 */

require_once __DIR__ . "/middleware/AuthMiddleware.php";

AuthMiddleware::verificar();
$usuario = AuthMiddleware::usuario();
// La página de administración se reserva a ADMIN; el controlador también lo verifica.
if (($usuario["rol"] ?? "") !== "ADMIN") {
    header("Location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios | Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="css/usuarios/usuario.css" rel="stylesheet">
</head>
<body>
<main class="container py-4 py-lg-5">
    <header class="page-heading mb-4">
        <div>
            <p class="eyebrow mb-1">Administración del sistema</p>
            <h1 class="h2 mb-1">Usuarios</h1>
            <p class="text-secondary mb-0">
                Sesión: <?= htmlspecialchars($usuario["nombre"], ENT_QUOTES, "UTF-8") ?>
                <span class="badge text-bg-primary ms-1">ADMIN</span>
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Volver al dashboard</a>
    </header>

    <section class="surface p-3 p-lg-4 mb-5" aria-labelledby="formTitle">
        <div class="section-heading mb-3">
            <div>
                <p class="eyebrow mb-1" id="formState">NUEVO USUARIO</p>
                <h2 class="h4 mb-0" id="formTitle">Dar de alta usuario</h2>
            </div>
        </div>
        <form id="formUsuario" class="row g-3" autocomplete="off">
            <input type="hidden" id="id" name="id">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre completo</label>
                <input class="form-control" type="text" id="nombre" name="nombre" maxlength="100" required>
            </div>
            <div class="col-md-6">
                <label for="username" class="form-label">Nombre de usuario</label>
                <input class="form-control" type="text" id="username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" required>
            </div>
            <div class="col-md-6">
                <label for="password" class="form-label">Contraseña</label>
                <input class="form-control" type="password" id="password" name="password" minlength="6" maxlength="255" autocomplete="new-password">
                <div class="form-text" id="passwordHelp">Mínimo 6 caracteres.</div>
            </div>
            <div class="col-md-3 col-sm-6">
                <label for="rol" class="form-label">Rol y permisos</label>
                <select class="form-select" id="rol" name="rol" required>
                    <option value="AYUDANTE">AYUDANTE · solo consulta y reabasto</option>
                    <option value="MODERADOR">MODERADOR · crear y modificar</option>
                    <option value="ADMIN">ADMIN · administración completa</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label for="activo" class="form-label">Estado</label>
                <select class="form-select" id="activo" name="activo" required>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-success" type="submit" id="btnGuardar">Crear usuario</button>
                <button class="btn btn-outline-secondary d-none" type="button" id="btnCancelar">Cancelar edición</button>
            </div>
        </form>
    </section>

    <section aria-labelledby="listTitle">
        <div class="section-heading mb-3">
            <div>
                <p class="eyebrow mb-1">Accesos registrados</p>
                <h2 class="h4 mb-0" id="listTitle">Administrar usuarios</h2>
            </div>
            <span class="directory-mark" aria-hidden="true"></span>
        </div>
        <div class="table-responsive">
            <table id="tablaUsuarios" class="table table-striped table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha de alta</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="js/usuarios/admin-usuarios.js"></script>
</body>
</html>
