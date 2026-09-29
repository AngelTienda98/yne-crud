<?php

require_once __DIR__ . "/middleware/AuthMiddleware.php";
require_once __DIR__ . "/middleware/Permission.php";

AuthMiddleware::verificar();
$usuario = AuthMiddleware::usuario();
$puedeMover = Permission::puedeMoverUbicaciones();
$puedeAceptarReabasto = Permission::puedeAceptarReabastecimiento();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ubicaciones y picking | Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/3.1.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="css/ubicaciones/ubicaciones.css" rel="stylesheet">
</head>
<body>
<main class="container py-4 py-lg-5">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <p class="small text-uppercase fw-semibold text-secondary mb-1">Almacén · picking simulado</p>
            <h1 class="h2 mb-1">Ubicaciones</h1>
            <p class="text-secondary mb-0">
                <?= htmlspecialchars($usuario["nombre"], ENT_QUOTES, "UTF-8") ?>
                <span class="badge text-bg-primary ms-1"><?= htmlspecialchars($usuario["rol"], ENT_QUOTES, "UTF-8") ?></span>
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Volver al dashboard</a>
    </header>

    <?php if ($puedeMover): ?>
        <section class="mb-5" aria-labelledby="movimientoTitulo">
            <div class="mb-3">
                <h2 class="h4 mb-1" id="movimientoTitulo">Asignar movimiento</h2>
                <p class="text-secondary mb-0">El movimiento se registra al confirmar y actualiza el saldo de ambas ubicaciones.</p>
            </div>
            <form id="formMovimiento" class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="productoMovimiento">Producto</label>
                    <select class="form-select" id="productoMovimiento" name="producto_id" required>
                        <option value="">Selecciona un producto</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="origenMovimiento">Ubicación de origen</label>
                    <select class="form-select" id="origenMovimiento" name="ubicacion_origen_id" required disabled>
                        <option value="">Selecciona una ubicación</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="cantidadMovimiento">Cantidad</label>
                    <input class="form-control" type="number" id="cantidadMovimiento" name="cantidad" min="1" step="1" required>
                    <div class="form-text" id="stockOrigen">Disponible: 0</div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="rackDestino">Rack destino</label>
                    <input class="form-control" id="rackDestino" name="rack" maxlength="100" placeholder="Ej. R-01" required>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="posicionDestino">Posición</label>
                    <input class="form-control" id="posicionDestino" name="posicion" maxlength="100" placeholder="Ej. P-03" required>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="nivelDestino">Nivel</label>
                    <input class="form-control" id="nivelDestino" name="nivel" maxlength="100" placeholder="Ej. N-02" required>
                </div>
                <div class="col-md-6 col-xl-5">
                    <label class="form-label" for="montacarguistas">Montacarguistas asignados</label>
                    <select class="form-select" id="montacarguistas" name="montacarguistas[]" multiple size="3" required></select>
                    <div class="form-text">Puedes asignar a más de un montacarguista.</div>
                </div>
                <div class="col-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary" id="btnMover">Confirmar movimiento simulado</button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <div class="alert alert-light border mb-4" role="status">Vista de consulta. Los movimientos se asignan por el área responsable del almacén.</div>
    <?php endif; ?>

    <section class="mb-5" aria-labelledby="reabastosTitulo">
        <div class="mb-3">
            <h2 class="h4 mb-1" id="reabastosTitulo">Solicitudes de reabasto</h2>
            <p class="text-secondary mb-0">Al aceptar, registra las piezas recibidas en AlmacenGeneral y actualiza el stock.</p>
        </div>
        <div class="table-responsive">
            <table id="tablaReabastos" class="table table-striped table-hover align-middle w-100">
                <thead><tr>
                    <th>Producto</th><th>Stock actual</th><th>Solicitud</th><th>Fecha</th><th>Acción</th>
                </tr></thead>
            </table>
        </div>
    </section>

    <section class="mb-5" aria-labelledby="ubicacionesTitulo">
        <div class="mb-3">
            <h2 class="h4 mb-1" id="ubicacionesTitulo">Existencias por ubicación</h2>
            <p class="text-secondary mb-0">La cantidad muestra las piezas actualmente asignadas a cada posición.</p>
        </div>
        <div class="table-responsive">
            <table id="tablaUbicaciones" class="table table-striped table-hover align-middle w-100">
                <thead><tr>
                    <th>Producto</th><th>Lote</th><th>Rack</th><th>Posición</th><th>Nivel</th><th>Cantidad</th><th>Tipo</th>
                </tr></thead>
            </table>
        </div>
    </section>

    <section aria-labelledby="historialTitulo">
        <div class="mb-3">
            <h2 class="h4 mb-1" id="historialTitulo">Historial de picking</h2>
            <p class="text-secondary mb-0">Movimientos ejecutados y personal asignado.</p>
        </div>
        <div class="table-responsive">
            <table id="tablaMovimientos" class="table table-striped table-hover align-middle w-100">
                <thead><tr>
                    <th>Fecha</th><th>Producto</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Montacarguistas</th><th>Asignó</th><th>Estado</th>
                </tr></thead>
            </table>
        </div>
    </section>
</main>
<script>
window.puedeMoverUbicaciones = <?= json_encode($puedeMover) ?>;
window.puedeAceptarReabasto = <?= json_encode($puedeAceptarReabasto) ?>;
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
<script src="js/ubicaciones/ubicaciones.js"></script>
</body>
</html>
