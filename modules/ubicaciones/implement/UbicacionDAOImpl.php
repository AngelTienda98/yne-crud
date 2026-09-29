<?php

require_once __DIR__ . "/../../../config/conexion.php";
require_once __DIR__ . "/../dao/UbicacionDAO.php";

/** Gestiona existencias por ubicación, movimientos y datos del dashboard. */
class UbicacionDAOImpl implements UbicacionDAO {

    private $conexion;

    public function __construct() {
        $this->conexion = (new Database())->conectar();
    }

    public function listarUbicaciones() {
        $stmt = $this->conexion->query(
            "SELECT u.id, u.producto_id, p.nombre AS producto, p.lote, u.rack,
                    u.posicion, u.nivel, u.cantidad, u.es_principal
             FROM ubicaciones u
             INNER JOIN producto p ON p.id = u.producto_id
             WHERE u.cantidad > 0
             ORDER BY p.nombre, u.es_principal DESC, u.rack, u.posicion, u.nivel"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarProductos() {
        $stmt = $this->conexion->query(
            "SELECT id, nombre, lote, cantidad FROM producto ORDER BY nombre"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarMontacarguistas() {
        $stmt = $this->conexion->query(
            "SELECT id, nombre FROM montacarguistas WHERE activo = 1 ORDER BY nombre"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarMovimientos($limite = 20) {
        $stmt = $this->conexion->prepare(
            "SELECT m.id, m.producto_id, p.nombre AS producto, m.cantidad,
                    m.estatus, m.realizado_en, m.usuario_nombre,
                    CONCAT(origen.rack, ' / ', origen.posicion, ' / ', origen.nivel) AS origen,
                    CONCAT(destino.rack, ' / ', destino.posicion, ' / ', destino.nivel) AS destino,
                    COALESCE(GROUP_CONCAT(DISTINCT mt.nombre ORDER BY mt.nombre SEPARATOR ', '), '') AS montacarguistas
             FROM movimientos_ubicacion m
             INNER JOIN producto p ON p.id = m.producto_id
             INNER JOIN ubicaciones origen ON origen.id = m.ubicacion_origen_id
             INNER JOIN ubicaciones destino ON destino.id = m.ubicacion_destino_id
             LEFT JOIN movimiento_montacarguista mm ON mm.movimiento_id = m.id
             LEFT JOIN montacarguistas mt ON mt.id = mm.montacarguista_id
             GROUP BY m.id
             ORDER BY m.realizado_en DESC
             LIMIT :limite"
        );
        $stmt->bindValue(":limite", (int) $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarNotificacionesPendientes($limite = 10) {
        $stmt = $this->conexion->prepare(
            "SELECT n.id, n.producto_id, p.nombre AS producto, n.stock_disponible,
                    u.cantidad AS stock_actual, n.mensaje, n.creada_en
             FROM notificaciones_reabastecimiento n
             INNER JOIN producto p ON p.id = n.producto_id
             INNER JOIN ubicaciones u ON u.id = n.ubicacion_id
             WHERE n.estado = 'Pendiente'
             ORDER BY n.creada_en DESC
             LIMIT :limite"
        );
        $stmt->bindValue(":limite", (int) $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aceptarReabastecimiento($notificacionId, $cantidad, $usuarioId, $usuarioNombre) {
        if ($cantidad < 1) {
            throw new DomainException("La cantidad recibida debe ser mayor que cero.");
        }

        $this->conexion->beginTransaction();
        try {
            $consulta = $this->conexion->prepare(
                "SELECT n.id, n.producto_id, n.ubicacion_id, n.estado, p.nombre,
                        p.cantidad AS stock_total, u.cantidad AS stock_principal
                 FROM notificaciones_reabastecimiento n
                 INNER JOIN producto p ON p.id = n.producto_id
                 INNER JOIN ubicaciones u ON u.id = n.ubicacion_id AND u.es_principal = 1
                 WHERE n.id = :id FOR UPDATE"
            );
            $consulta->execute(["id" => $notificacionId]);
            $notificacion = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$notificacion) {
                throw new DomainException("La solicitud de reabasto no existe.");
            }
            if ($notificacion["estado"] !== "Pendiente") {
                throw new DomainException("Esta solicitud de reabasto ya fue atendida.");
            }
            $minimoParaReabastecer = max(10 - (int) $notificacion["stock_principal"], 1);
            if ($cantidad < $minimoParaReabastecer) {
                throw new DomainException(
                    "Debes recibir al menos {$minimoParaReabastecer} piezas para alcanzar el mínimo de 10."
                );
            }
            if ((int) $notificacion["stock_total"] + $cantidad > 4294967295) {
                throw new DomainException("La cantidad excede la capacidad máxima del inventario.");
            }

            $sumarUbicacion = $this->conexion->prepare(
                "UPDATE ubicaciones SET cantidad = cantidad + :cantidad WHERE id = :id AND es_principal = 1"
            );
            $sumarUbicacion->execute([
                "cantidad" => $cantidad,
                "id" => $notificacion["ubicacion_id"]
            ]);

            $sumarProducto = $this->conexion->prepare(
                "UPDATE producto SET cantidad = cantidad + :cantidad WHERE id = :id"
            );
            $sumarProducto->execute([
                "cantidad" => $cantidad,
                "id" => $notificacion["producto_id"]
            ]);

            $recepcion = $this->conexion->prepare(
                "INSERT INTO recepciones_reabastecimiento
                    (notificacion_id, producto_id, ubicacion_id, cantidad, usuario_id, usuario_nombre)
                 VALUES (:notificacion_id, :producto_id, :ubicacion_id, :cantidad, :usuario_id, :usuario_nombre)"
            );
            $recepcion->execute([
                "notificacion_id" => $notificacion["id"],
                "producto_id" => $notificacion["producto_id"],
                "ubicacion_id" => $notificacion["ubicacion_id"],
                "cantidad" => $cantidad,
                "usuario_id" => $usuarioId,
                "usuario_nombre" => mb_substr($usuarioNombre, 0, 100)
            ]);

            $atender = $this->conexion->prepare(
                "UPDATE notificaciones_reabastecimiento SET estado = 'Atendida' WHERE id = :id"
            );
            $atender->execute(["id" => $notificacion["id"]]);

            $this->conexion->commit();
            return [
                "producto" => $notificacion["nombre"],
                "cantidad_recibida" => $cantidad,
                "stock_principal" => (int) $notificacion["stock_principal"] + $cantidad,
                "stock_total" => (int) $notificacion["stock_total"] + $cantidad
            ];
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    public function obtenerResumenDashboard() {
        $producto = $this->conexion->query(
            "SELECT COUNT(*) AS total_productos,
                    COALESCE(SUM(cantidad), 0) AS stock_total,
                    SUM(estatus = 'Activo') AS productos_activos
             FROM producto"
        )->fetch(PDO::FETCH_ASSOC);

        $principal = $this->conexion->query(
            "SELECT COALESCE(SUM(cantidad), 0) AS stock_principal,
                    SUM(cantidad < 10) AS productos_bajo_minimo
             FROM ubicaciones WHERE es_principal = 1"
        )->fetch(PDO::FETCH_ASSOC);

        $ubicaciones = $this->conexion->query(
            "SELECT COUNT(*) FROM ubicaciones WHERE es_principal = 0 AND cantidad > 0"
        )->fetchColumn();
        $movimientos = $this->conexion->query(
            "SELECT COUNT(*) FROM movimientos_ubicacion WHERE DATE(realizado_en) = CURRENT_DATE()"
        )->fetchColumn();
        $notificaciones = $this->conexion->query(
            "SELECT COUNT(*) FROM notificaciones_reabastecimiento WHERE estado = 'Pendiente'"
        )->fetchColumn();

        return [
            "total_productos" => (int) $producto["total_productos"],
            "productos_activos" => (int) $producto["productos_activos"],
            "stock_total" => (int) $producto["stock_total"],
            "stock_principal" => (int) $principal["stock_principal"],
            "productos_bajo_minimo" => (int) $principal["productos_bajo_minimo"],
            "ubicaciones_activas" => (int) $ubicaciones,
            "movimientos_hoy" => (int) $movimientos,
            "notificaciones_pendientes" => (int) $notificaciones
        ];
    }

    public function mover($productoId, $origenId, $rack, $posicion, $nivel, $cantidad, array $montacarguistas, $usuarioId, $usuarioNombre) {
        if ($cantidad < 1 || count($montacarguistas) < 1) {
            throw new DomainException("Indica una cantidad positiva y al menos un montacarguista.");
        }

        $this->conexion->beginTransaction();
        try {
            $productoStmt = $this->conexion->prepare(
                "SELECT id, nombre FROM producto WHERE id = :id FOR UPDATE"
            );
            $productoStmt->execute(["id" => $productoId]);
            $producto = $productoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$producto) {
                throw new DomainException("El producto seleccionado no existe.");
            }

            $origenStmt = $this->conexion->prepare(
                "SELECT id, rack, posicion, nivel, cantidad, es_principal
                 FROM ubicaciones WHERE id = :id AND producto_id = :producto_id FOR UPDATE"
            );
            $origenStmt->execute(["id" => $origenId, "producto_id" => $productoId]);
            $origen = $origenStmt->fetch(PDO::FETCH_ASSOC);
            if (!$origen) {
                throw new DomainException("La ubicación de origen no corresponde al producto.");
            }
            if ((int) $origen["cantidad"] < $cantidad) {
                throw new DomainException("La cantidad solicitada supera las existencias en la ubicación de origen.");
            }
            if (
                $origen["rack"] === $rack &&
                $origen["posicion"] === $posicion &&
                $origen["nivel"] === $nivel
            ) {
                throw new DomainException("La ubicación de destino debe ser diferente al origen.");
            }

            $montacarguistas = array_values(array_unique(array_map("intval", $montacarguistas)));
            $marcadores = implode(",", array_fill(0, count($montacarguistas), "?"));
            $personalStmt = $this->conexion->prepare(
                "SELECT COUNT(*) FROM montacarguistas WHERE activo = 1 AND id IN ({$marcadores})"
            );
            $personalStmt->execute($montacarguistas);
            if ((int) $personalStmt->fetchColumn() !== count($montacarguistas)) {
                throw new DomainException("Uno o más montacarguistas seleccionados no están disponibles.");
            }

            $restar = $this->conexion->prepare(
                "UPDATE ubicaciones SET cantidad = cantidad - :cantidad WHERE id = :id AND cantidad >= :cantidad"
            );
            $restar->execute(["cantidad" => $cantidad, "id" => $origenId]);
            if ($restar->rowCount() !== 1) {
                throw new DomainException("No hay existencias suficientes para completar el movimiento.");
            }

            $destinoStmt = $this->conexion->prepare(
                "SELECT id FROM ubicaciones
                 WHERE producto_id = :producto_id AND rack = :rack AND posicion = :posicion AND nivel = :nivel
                 FOR UPDATE"
            );
            $destinoStmt->execute([
                "producto_id" => $productoId,
                "rack" => $rack,
                "posicion" => $posicion,
                "nivel" => $nivel
            ]);
            $destinoId = $destinoStmt->fetchColumn();

            if ($destinoId) {
                $sumar = $this->conexion->prepare(
                    "UPDATE ubicaciones SET cantidad = cantidad + :cantidad WHERE id = :id"
                );
                $sumar->execute(["cantidad" => $cantidad, "id" => $destinoId]);
            } else {
                $insertarDestino = $this->conexion->prepare(
                    "INSERT INTO ubicaciones (producto_id, rack, posicion, nivel, cantidad, es_principal)
                     VALUES (:producto_id, :rack, :posicion, :nivel, :cantidad, 0)"
                );
                $insertarDestino->execute([
                    "producto_id" => $productoId,
                    "rack" => $rack,
                    "posicion" => $posicion,
                    "nivel" => $nivel,
                    "cantidad" => $cantidad
                ]);
                $destinoId = (int) $this->conexion->lastInsertId();
            }

            $movimientoStmt = $this->conexion->prepare(
                "INSERT INTO movimientos_ubicacion
                    (producto_id, ubicacion_origen_id, ubicacion_destino_id, usuario_id, usuario_nombre, cantidad)
                 VALUES (:producto_id, :origen_id, :destino_id, :usuario_id, :usuario_nombre, :cantidad)"
            );
            $movimientoStmt->execute([
                "producto_id" => $productoId,
                "origen_id" => $origenId,
                "destino_id" => $destinoId,
                "usuario_id" => $usuarioId,
                "usuario_nombre" => $usuarioNombre,
                "cantidad" => $cantidad
            ]);
            $movimientoId = (int) $this->conexion->lastInsertId();

            $asignar = $this->conexion->prepare(
                "INSERT INTO movimiento_montacarguista (movimiento_id, montacarguista_id)
                 VALUES (:movimiento_id, :montacarguista_id)"
            );
            foreach ($montacarguistas as $montacarguistaId) {
                $asignar->execute([
                    "movimiento_id" => $movimientoId,
                    "montacarguista_id" => $montacarguistaId
                ]);
            }

            $stockStmt = $this->conexion->prepare(
                "SELECT id, cantidad FROM ubicaciones WHERE producto_id = :producto_id AND es_principal = 1 FOR UPDATE"
            );
            $stockStmt->execute(["producto_id" => $productoId]);
            $principal = $stockStmt->fetch(PDO::FETCH_ASSOC);
            $stockPrincipal = $principal ? (int) $principal["cantidad"] : 0;
            $requiereReabasto = $stockPrincipal < 10;

            if ($requiereReabasto) {
                $pendiente = $this->conexion->prepare(
                    "SELECT id FROM notificaciones_reabastecimiento
                     WHERE producto_id = :producto_id AND estado = 'Pendiente' LIMIT 1 FOR UPDATE"
                );
                $pendiente->execute(["producto_id" => $productoId]);
                if (!$pendiente->fetchColumn()) {
                    $notificacion = $this->conexion->prepare(
                        "INSERT INTO notificaciones_reabastecimiento
                            (producto_id, ubicacion_id, movimiento_id, stock_disponible, mensaje)
                         VALUES (:producto_id, :ubicacion_id, :movimiento_id, :stock, :mensaje)"
                    );
                    $notificacion->execute([
                        "producto_id" => $productoId,
                        "ubicacion_id" => $principal["id"],
                        "movimiento_id" => $movimientoId,
                        "stock" => $stockPrincipal,
                        "mensaje" => "Solicitar reabasto: {$producto["nombre"]} tiene {$stockPrincipal} piezas en AlmacenGeneral."
                    ]);
                }
            } else {
                $resolver = $this->conexion->prepare(
                    "UPDATE notificaciones_reabastecimiento
                     SET estado = 'Atendida' WHERE producto_id = :producto_id AND estado = 'Pendiente'"
                );
                $resolver->execute(["producto_id" => $productoId]);
            }

            $this->conexion->commit();
            return [
                "id" => $movimientoId,
                "stock_principal" => $stockPrincipal,
                "requiere_reabasto" => $requiereReabasto
            ];
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }
}
