<?php

require_once __DIR__ . "/middleware/AuthMiddleware.php";
require_once __DIR__ . "/../modules/ubicaciones/implement/UbicacionDAOImpl.php";

AuthMiddleware::verificar();

// Los datos se leen de la sesión validada por el middleware.
$usuario =
    AuthMiddleware::usuario();
$ubicacionDAO = new UbicacionDAOImpl();
$resumen = $ubicacionDAO->obtenerResumenDashboard();
$movimientos = $ubicacionDAO->listarMovimientos(8);
$notificaciones = $ubicacionDAO->listarNotificacionesPendientes(5);

?>

<!DOCTYPE html>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/3.1.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
<main class="container py-4 py-lg-5">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <p class="small text-uppercase fw-semibold text-secondary mb-1">Operación de almacén</p>
            <h1 class="h2 mb-1">Dashboard</h1>
            <p class="text-secondary mb-0">
                Bienvenido, <?= htmlspecialchars($usuario["nombre"], ENT_QUOTES, "UTF-8") ?>
                <span class="badge text-bg-primary ms-1"><?= htmlspecialchars($usuario["rol"], ENT_QUOTES, "UTF-8") ?></span>
            </p>
        </div>
        <nav class="d-flex flex-wrap gap-2" aria-label="Módulos">
            <a href="productos.php" class="btn btn-primary">Productos</a>
            <a href="ubicaciones.php" class="btn btn-outline-primary">Ubicaciones y picking</a>
            <?php if ($usuario["rol"] === "ADMIN"): ?>
                <a href="usuarios.php" class="btn btn-outline-secondary">Usuarios</a>
            <?php endif; ?>
            <button type="button" onclick="logout()" class="btn btn-outline-danger">Cerrar sesión</button>
        </nav>
    </header>

    <section class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-5" aria-label="Resumen del inventario">
        <div class="col"><article class="p-3 h-100 border rounded-2 bg-white"><p class="small text-secondary mb-2">Productos</p><p class="h3 mb-0"><?= number_format($resumen["total_productos"]) ?></p><p class="small text-secondary mb-0"><?= number_format($resumen["productos_activos"]) ?> activos</p></article></div>
        <div class="col"><article class="p-3 h-100 border rounded-2 bg-white"><p class="small text-secondary mb-2">Stock total</p><p class="h3 mb-0"><?= number_format($resumen["stock_total"]) ?></p><p class="small text-secondary mb-0">piezas en inventario</p></article></div>
        <div class="col"><article class="p-3 h-100 border rounded-2 bg-white"><p class="small text-secondary mb-2">Stock principal</p><p class="h3 mb-0"><?= number_format($resumen["stock_principal"]) ?></p><p class="small text-secondary mb-0">piezas en AlmacenGeneral</p></article></div>
        <div class="col"><article class="p-3 h-100 border rounded-2 bg-white"><p class="small text-secondary mb-2">Ubicaciones ocupadas</p><p class="h3 mb-0"><?= number_format($resumen["ubicaciones_activas"]) ?></p><p class="small text-secondary mb-0">racks con existencias</p></article></div>
        <div class="col"><article class="p-3 h-100 border rounded-2 bg-white"><p class="small text-secondary mb-2">Movimientos de hoy</p><p class="h3 mb-0"><?= number_format($resumen["movimientos_hoy"]) ?></p><p class="small text-secondary mb-0">picking simulado</p></article></div>
    </section>

    <section class="mb-5" aria-labelledby="reabastoTitulo">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h4 mb-1" id="reabastoTitulo">Solicitudes de reabasto</h2>
                <p class="text-secondary mb-0">El aviso se genera cuando AlmacenGeneral baja de 10 piezas.</p>
            </div>
            <span class="badge text-bg-<?= $resumen["notificaciones_pendientes"] > 0 ? "warning" : "success" ?>">
                <?= number_format($resumen["notificaciones_pendientes"]) ?> pendientes
            </span>
        </div>
        <?php if ($notificaciones): ?>
            <div class="list-group">
                <?php foreach ($notificaciones as $notificacion): ?>
                    <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <strong><?= htmlspecialchars($notificacion["producto"], ENT_QUOTES, "UTF-8") ?></strong>
                            <p class="mb-0 small text-secondary"><?= htmlspecialchars($notificacion["mensaje"], ENT_QUOTES, "UTF-8") ?></p>
                        </div>
                        <span class="small text-secondary"><?= htmlspecialchars($notificacion["creada_en"], ENT_QUOTES, "UTF-8") ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="p-3 mb-0 border rounded-2 text-secondary">No hay solicitudes de reabasto pendientes.</p>
        <?php endif; ?>
        <?php if ($resumen["productos_bajo_minimo"] > 0): ?>
            <p class="small text-warning-emphasis mt-2 mb-0"><?= number_format($resumen["productos_bajo_minimo"]) ?> productos tienen menos de 10 piezas en stock principal.</p>
        <?php endif; ?>
    </section>

    <section aria-labelledby="movimientosTitulo">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h4 mb-1" id="movimientosTitulo">Movimientos recientes</h2>
                <p class="text-secondary mb-0">Últimos movimientos de producto y personal asignado.</p>
            </div>
            <a href="ubicaciones.php" class="btn btn-sm btn-outline-primary">Ver ubicaciones</a>
        </div>
        <div class="table-responsive">
            <table id="tablaDashboardMovimientos" class="table table-striped align-middle">
                <thead><tr><th>Fecha</th><th>Producto</th><th>Piezas</th><th>Origen</th><th>Destino</th><th>Montacarguistas</th><th>Asignó</th></tr></thead>
                <tbody>
                <?php if ($movimientos): ?>
                    <?php foreach ($movimientos as $movimiento): ?>
                        <tr>
                            <td><?= htmlspecialchars($movimiento["realizado_en"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($movimiento["producto"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= number_format((int) $movimiento["cantidad"]) ?></td>
                            <td><?= htmlspecialchars($movimiento["origen"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($movimiento["destino"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($movimiento["montacarguistas"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($movimiento["usuario_nombre"], ENT_QUOTES, "UTF-8") ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

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
<script src="js/dashboard.js"></script>

<script>

// Pide confirmación antes de cerrar la sesión y volver al login.
async function logout() {

    const result = await Swal.fire({

        title: "¿Cerrar sesión?",

        icon: "question",

        showCancelButton: true,

        confirmButtonText: "Sí, salir",

        cancelButtonText: "Cancelar"

    });

    if (!result.isConfirmed) {
        return;
    }

    await fetch(
        "../modules/auth/controller/LoginController.php?accion=logout"
    );

    window.location.href =
        "../index.php";
}

</script>

</body>

</html>