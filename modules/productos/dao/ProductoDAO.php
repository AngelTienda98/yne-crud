<?php

interface ProductoDAO {

    public function listar();

    public function buscarPorId($id);

    public function crear(Producto $producto);

    public function actualizar($id, Producto $producto);

    public function eliminar($id);
}
