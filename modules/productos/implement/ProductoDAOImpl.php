<?php

require_once __DIR__ . "/../../../config/conexion.php";
require_once __DIR__ . "/../model/Producto.php";
require_once __DIR__ . "/../dao/ProductoDAO.php";

/** Implementa las operaciones de productos con consultas preparadas PDO. */
class ProductoDAOImpl implements ProductoDAO {

    private $conexion;

    public function __construct() {
        $this->conexion = (new Database())->conectar();
    }

    public function listar() {
        $stmt = $this->conexion->query(
            "SELECT * FROM producto ORDER BY id DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId($id) {
        $stmt = $this->conexion->prepare(
            "SELECT * FROM producto WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(Producto $producto) {
        $datos = $producto->toArray();
        $cantidad = (int) $datos["cantidad"];

        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare(
                "INSERT INTO producto
                    (nombre, descripcion, presentacion, lote, cantidad, precio, fecha_caducidad, categoria, estatus)
                 VALUES
                    (:nombre, :descripcion, :presentacion, :lote, :cantidad, :precio, :fecha_caducidad, :categoria, :estatus)"
            );
            $stmt->execute($datos);
            $productoId = (int) $this->conexion->lastInsertId();

            $ubicacion = $this->conexion->prepare(
                "INSERT INTO ubicaciones (producto_id, rack, posicion, nivel, cantidad, es_principal)
                 VALUES (:producto_id, 'AlmacenGeneral', 'General', 'General', :cantidad, 1)"
            );
            $ubicacion->execute([
                "producto_id" => $productoId,
                "cantidad" => $cantidad
            ]);

            if ($cantidad < 10) {
                $this->crearNotificacionReabastecimiento(
                    $productoId,
                    (int) $this->conexion->lastInsertId(),
                    $cantidad,
                    null,
                    $datos["nombre"]
                );
            }

            $this->conexion->commit();
            return ["id" => $productoId, "requiere_reabastecimiento" => $cantidad < 10];
        } catch (Throwable $error) {
            $this->conexion->rollBack();
            throw $error;
        }
    }

    public function actualizar($id, Producto $producto) {
        $datos = $producto->toArray();
        $this->conexion->beginTransaction();
        try {
            $actual = $this->conexion->prepare(
                "SELECT cantidad FROM producto WHERE id = :id FOR UPDATE"
            );
            $actual->execute(["id" => $id]);
            if (!$actual->fetch(PDO::FETCH_ASSOC)) {
                $this->conexion->rollBack();
                return false;
            }

            $ubicaciones = $this->conexion->prepare(
                "SELECT id, cantidad FROM ubicaciones
                 WHERE producto_id = :id AND es_principal = 1 FOR UPDATE"
            );
            $ubicaciones->execute(["id" => $id]);
            $principal = $ubicaciones->fetch(PDO::FETCH_ASSOC);

            $otras = $this->conexion->prepare(
                "SELECT COALESCE(SUM(cantidad), 0) FROM ubicaciones
                 WHERE producto_id = :id AND es_principal = 0"
            );
            $otras->execute(["id" => $id]);
            $cantidadEnRacks = (int) $otras->fetchColumn();
            $cantidadTotal = (int) $datos["cantidad"];

            if ($cantidadTotal < $cantidadEnRacks) {
                $this->conexion->rollBack();
                return false;
            }

            $cantidadPrincipal = $cantidadTotal - $cantidadEnRacks;
            $stmt = $this->conexion->prepare(
                "UPDATE producto SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    presentacion = :presentacion,
                    lote = :lote,
                    cantidad = :cantidad,
                    precio = :precio,
                    fecha_caducidad = :fecha_caducidad,
                    categoria = :categoria,
                    estatus = :estatus
                 WHERE id = :id"
            );
            $stmt->execute(array_merge($datos, ["id" => $id]));

            if ($principal) {
                $actualizarUbicacion = $this->conexion->prepare(
                    "UPDATE ubicaciones SET cantidad = :cantidad WHERE id = :id"
                );
                $actualizarUbicacion->execute([
                    "cantidad" => $cantidadPrincipal,
                    "id" => $principal["id"]
                ]);
                $ubicacionId = (int) $principal["id"];
            } else {
                $crearUbicacion = $this->conexion->prepare(
                    "INSERT INTO ubicaciones (producto_id, rack, posicion, nivel, cantidad, es_principal)
                     VALUES (:producto_id, 'AlmacenGeneral', 'General', 'General', :cantidad, 1)"
                );
                $crearUbicacion->execute([
                    "producto_id" => $id,
                    "cantidad" => $cantidadPrincipal
                ]);
                $ubicacionId = (int) $this->conexion->lastInsertId();
            }

            if ($cantidadPrincipal < 10) {
                $this->crearNotificacionReabastecimiento(
                    $id,
                    $ubicacionId,
                    $cantidadPrincipal,
                    null,
                    $datos["nombre"]
                );
            } else {
                $resolver = $this->conexion->prepare(
                    "UPDATE notificaciones_reabastecimiento
                     SET estado = 'Atendida' WHERE producto_id = :producto_id AND estado = 'Pendiente'"
                );
                $resolver->execute(["producto_id" => $id]);
            }

            $this->conexion->commit();
            return true;
        } catch (Throwable $error) {
            $this->conexion->rollBack();
            throw $error;
        }
    }

    public function eliminar($id) {
        $existe = $this->conexion->prepare(
            "SELECT id FROM producto WHERE id = :id"
        );
        $existe->execute(["id" => $id]);
        if (!$existe->fetchColumn()) {
            return false;
        }

        $movimientos = $this->conexion->prepare(
            "SELECT COUNT(*) FROM movimientos_ubicacion WHERE producto_id = :id"
        );
        $movimientos->execute(["id" => $id]);
        if ((int) $movimientos->fetchColumn() > 0) {
            return "tiene_movimientos";
        }

        $recepciones = $this->conexion->prepare(
            "SELECT COUNT(*) FROM recepciones_reabastecimiento WHERE producto_id = :id"
        );
        $recepciones->execute(["id" => $id]);
        if ((int) $recepciones->fetchColumn() > 0) {
            return "tiene_movimientos";
        }

        $stmt = $this->conexion->prepare(
            "DELETE FROM producto WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);

        return $stmt->rowCount() > 0;
    }

    private function crearNotificacionReabastecimiento($productoId, $ubicacionId, $cantidad, $movimientoId, $nombre) {
        $pendiente = $this->conexion->prepare(
            "SELECT id FROM notificaciones_reabastecimiento
             WHERE producto_id = :producto_id AND estado = 'Pendiente' LIMIT 1"
        );
        $pendiente->execute(["producto_id" => $productoId]);
        if ($pendiente->fetchColumn()) {
            return;
        }

        $insertar = $this->conexion->prepare(
            "INSERT INTO notificaciones_reabastecimiento
                (producto_id, ubicacion_id, movimiento_id, stock_disponible, mensaje)
             VALUES (:producto_id, :ubicacion_id, :movimiento_id, :stock, :mensaje)"
        );
        $insertar->execute([
            "producto_id" => $productoId,
            "ubicacion_id" => $ubicacionId,
            "movimiento_id" => $movimientoId,
            "stock" => $cantidad,
            "mensaje" => "Solicitar reabasto: {$nombre} tiene {$cantidad} piezas en AlmacenGeneral."
        ]);
    }
}
