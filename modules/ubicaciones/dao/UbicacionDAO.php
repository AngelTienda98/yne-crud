<?php

interface UbicacionDAO {

    public function listarUbicaciones();

    public function listarProductos();

    public function listarMontacarguistas();

    public function listarMovimientos($limite = 20);

    public function listarNotificacionesPendientes($limite = 10);

    public function aceptarReabastecimiento($notificacionId, $cantidad, $usuarioId, $usuarioNombre);

    public function obtenerResumenDashboard();

    public function mover($productoId, $origenId, $rack, $posicion, $nivel, $cantidad, array $montacarguistas, $usuarioId, $usuarioNombre);
}
