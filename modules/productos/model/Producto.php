<?php

/** Representa los datos que se guardan para un producto. */
class Producto {

    private $nombre;
    private $descripcion;
    private $presentacion;
    private $lote;
    private $cantidad;
    private $precio;
    private $fechaCaducidad;
    private $categoria;
    private $estatus;

    public function __construct($datos) {
        $this->nombre = $datos["nombre"];
        $this->descripcion = $datos["descripcion"];
        $this->presentacion = $datos["presentacion"];
        $this->lote = $datos["lote"];
        $this->cantidad = $datos["cantidad"];
        $this->precio = $datos["precio"];
        $this->fechaCaducidad = $datos["fecha_caducidad"];
        $this->categoria = $datos["categoria"];
        $this->estatus = $datos["estatus"];
    }

    /** Devuelve los campos con los nombres usados por la base de datos. */
    public function toArray() {
        return [
            "nombre" => $this->nombre,
            "descripcion" => $this->descripcion,
            "presentacion" => $this->presentacion,
            "lote" => $this->lote,
            "cantidad" => $this->cantidad,
            "precio" => $this->precio,
            "fecha_caducidad" => $this->fechaCaducidad,
            "categoria" => $this->categoria,
            "estatus" => $this->estatus
        ];
    }
}
