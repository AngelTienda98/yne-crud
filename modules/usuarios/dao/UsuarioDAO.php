<?php

/*
 * AUTOR: AngelTienda
 * Fecha de creación del archivo: 2026-09-26
 */

interface UsuarioDAO {

    /** Lista los campos administrativos sin incluir contraseñas. */
    public function listarUsuarios();

    /** Obtiene un usuario para gestión, incluso si está inactivo. */
    public function obtenerUsuarioAdministracion($id);

    /** Cuenta administradores activos para proteger el último acceso admin. */
    public function contarAdministradoresActivos();

    /** Comprueba si un nombre de usuario ya existe, opcionalmente excluyendo un ID. */
    public function usernameExiste($username, $excluirId = null);

    /** Crea un usuario y guarda su contraseña cifrada. */
    public function crearUsuario($datos);

    /** Actualiza los datos del usuario y cambia la contraseña solo si se proporcionó. */
    public function actualizarUsuario($id, $datos);

    /** Elimina al usuario indicado. */
    public function eliminarUsuario($id);

    /** Busca un usuario activo por su nombre de usuario. */
    public function buscarPorUsername($username);

    /** Busca un usuario activo por su identificador. */
    public function buscarPorId($id);

}