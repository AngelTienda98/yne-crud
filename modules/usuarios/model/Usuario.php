<?php

/** Representa un usuario; sus getters y setters leen y actualizan sus campos. */
class Usuario {

    private $id;
    private $username;
    private $password;
    private $nombre;
    private $rol;
    private $activo;

    /** Inicializa los datos del usuario, con valores por defecto opcionales. */
    public function __construct(
        $id = null,
        $username = "",
        $password = "",
        $nombre = "",
        $rol = "",
        $activo = 1
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
        $this->nombre = $nombre;
        $this->rol = $rol;
        $this->activo = $activo;
    }

    public function getId() {
        return $this->id;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getNombre() {
        return $this->nombre;
    }

    public function getRol() {
        return $this->rol;
    }

    public function getActivo() {
        return $this->activo;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setUsername($username) {
        $this->username = $username;
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    public function setNombre($nombre) {
        $this->nombre = $nombre;
    }

    public function setRol($rol) {
        $this->rol = $rol;
    }

    public function setActivo($activo) {
        $this->activo = $activo;
    }
}