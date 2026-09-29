<?php

require_once __DIR__ . "/middleware/AuthMiddleware.php";
require_once __DIR__ . "/middleware/Permission.php";

AuthMiddleware::verificar();

$usuario = AuthMiddleware::usuario();
$permisosProducto = [
    "crear" => Permission::puedeCrear(),
    "modificar" => Permission::puedeModificar(),
    "eliminar" => Permission::puedeEliminar(),
    "visualizar" => Permission::puedeVisualizar()
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Productos | Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/3.1.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="css/productos/productos.css" rel="stylesheet">
</head>
<body>
<main class="container py-4 py-lg-5">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-1">Inventario</p>
            <h1 class="h2 mb-1">Productos</h1>
            <p class="text-secondary mb-0">
                <?= htmlspecialchars($usuario["nombre"], ENT_QUOTES, "UTF-8") ?>
                <span class="badge text-bg-primary ms-1"><?= htmlspecialchars($usuario["rol"], ENT_QUOTES, "UTF-8") ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="ubicaciones.php" class="btn btn-outline-primary">Ubicaciones</a>
            <a href="dashboard.php" class="btn btn-outline-secondary">Volver al inicio</a>
        </div>
    </header>

    <?php if ($permisosProducto["crear"] || $permisosProducto["modificar"]): ?>
        <section class="mb-5" aria-labelledby="formTitle">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h4 mb-1" id="formTitle">Registrar producto</h2>
                    <p class="text-secondary mb-0">Completa los datos del producto.</p>
                </div>
                <span class="badge text-bg-light border text-secondary" id="formState">NUEVO PRODUCTO</span>
            </div>

            <form id="formProducto" class="row g-3">
                <input type="hidden" id="productoId" name="id">

                <div class="col-md-6 col-lg-4">
                    <label for="nombre" class="form-label">Nombre del producto</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="150" required>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label for="presentacion" class="form-label">Presentación</label>
                    <input type="text" class="form-control" id="presentacion" name="presentacion" maxlength="100" placeholder="Ej. Caja con 12 piezas" required>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label for="lote" class="form-label">Lote</label>
                    <input type="text" class="form-control" id="lote" name="lote" maxlength="80" required>
                </div>
                <div class="col-12">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="2" required></textarea>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="cantidad" class="form-label">Cantidad</label>
                    <input type="number" class="form-control" id="cantidad" name="cantidad" min="0" step="1" required>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="precio" class="form-label">Precio</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="precio" name="precio" min="0" max="99999999.99" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="fecha_caducidad" class="form-label">Fecha de caducidad</label>
                    <input type="date" class="form-control" id="fecha_caducidad" name="fecha_caducidad" required>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="categoria" class="form-label">Categoría</label>
                    <input type="text" class="form-control" id="categoria" name="categoria" maxlength="100" required>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="estatus" class="form-label">Estatus</label>
                    <select class="form-select" id="estatus" name="estatus" required>
                        <option value="Activo">Activo</option>
                        <option value="Rechazado">Rechazado</option>
                        <option value="Cancelado">Cancelado</option>
                        <option value="Sin existencia">Sin existencia</option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" id="btnGuardarProducto">Guardar producto</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="btnCancelarEdicion">Cancelar edición</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section aria-labelledby="listTitle">
        <div class="mb-3">
            <h2 class="h4 mb-1" id="listTitle">Inventario registrado</h2>
            <p class="text-secondary mb-0">Consulta los productos y su disponibilidad.</p>
        </div>
        <div class="table-responsive">
            <table id="tablaProductos" class="table table-striped table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Presentación</th>
                        <th>Lote</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Caducidad</th>
                        <th>Categoría</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </section>
</main>

<script>
window.permisosProducto = <?= json_encode($permisosProducto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/vfs_fonts.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.1.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.html5.min.js"></script>
<script src="js/shared/datatable-export.js"></script>
<script src="js/productos/productos.js"></script>
</body>
</html>
