/*
 * AUTOR: AngelTienda
 * Fecha de creación del archivo: 2026-09-26
 */

const URL_USUARIOS = "../modules/usuarios/controller/UsuarioController.php";
let tablaUsuarios;
let usuariosActuales = [];

const avisoUsuario = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true
});

document.addEventListener("DOMContentLoaded", () => {
    tablaUsuarios = new DataTable("#tablaUsuarios", {
        data: [],
        columns: [
            { data: "id" },
            { data: "username", render: DataTable.render.text() },
            { data: "nombre", render: DataTable.render.text() },
            {
                data: "rol",
                render: (rol, tipo) => {
                    if (tipo !== "display") return rol;
                    const estilo = {
                        ADMIN: "text-bg-primary",
                        MODERADOR: "text-bg-success",
                        AYUDANTE: "text-bg-secondary"
                    }[rol] || "text-bg-secondary";
                    return `<span class="badge ${estilo}">${escaparTexto(rol)}</span>`;
                }
            },
            {
                data: "activo",
                render: (activo, tipo) => {
                    if (tipo !== "display") return activo;
                    const habilitado = Number(activo) === 1;
                    return `<span class="badge ${habilitado ? "text-bg-success" : "text-bg-secondary"}">${habilitado ? "Activo" : "Inactivo"}</span>`;
                }
            },
            { data: "fecha_registro", defaultContent: "" },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-end",
                render: (_dato, tipo, usuario) => {
                    if (tipo !== "display") return "";
                    const id = Number(usuario.id);
                    return `<div class="d-inline-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success" data-action="editar" data-id="${id}">Editar</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="eliminar" data-id="${id}">Eliminar</button>
                    </div>`;
                }
            }
        ],
        order: [[0, "desc"]],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        scrollX: true,
        language: {
            search: "Buscar:",
            searchPlaceholder: "Usuario o nombre",
            lengthMenu: "Mostrar _MENU_ usuarios",
            info: "Mostrando _START_ a _END_ de _TOTAL_ usuarios",
            infoEmpty: "No hay usuarios para mostrar",
            infoFiltered: "(filtrado de _MAX_ usuarios)",
            zeroRecords: "No se encontraron usuarios",
            emptyTable: "Aún no hay usuarios registrados",
            paginate: { first: "Primera", last: "Última", next: "Siguiente", previous: "Anterior" }
        }
    });

    document.getElementById("formUsuario").addEventListener("submit", guardarUsuario);
    document.getElementById("btnCancelar").addEventListener("click", limpiarFormulario);
    document.querySelector("#tablaUsuarios tbody").addEventListener("click", manejarAccion);
    document.getElementById("password").required = true;
    listarUsuarios();
});

async function listarUsuarios() {
    try {
        const respuesta = await solicitarJson(`${URL_USUARIOS}?accion=listar`);
        usuariosActuales = respuesta.data;
        tablaUsuarios.clear().rows.add(usuariosActuales).draw();
    } catch (error) {
        mostrarError(error);
    }
}

// Envía el formulario para crear o actualizar según exista un ID.
async function guardarUsuario(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const id = document.getElementById("id").value;
    const accion = id ? "actualizar" : "insertar";
    const datos = new FormData(formulario);

    try {
        const respuesta = await solicitarJson(`${URL_USUARIOS}?accion=${accion}`, {
            method: "POST",
            body: datos
        });
        limpiarFormulario();
        await listarUsuarios();
        avisoUsuario.fire({ icon: "success", title: respuesta.message });
    } catch (error) {
        mostrarError(error);
    }
}

function manejarAccion(evento) {
    const boton = evento.target.closest("[data-action]");
    if (!boton) return;

    const { action, id } = boton.dataset;
    if (action === "editar") editarUsuario(id);
    if (action === "eliminar") eliminarUsuario(id);
}

// Carga los datos del usuario; no rellena la contraseña anterior.
function editarUsuario(id) {
    const usuario = usuariosActuales.find((item) => Number(item.id) === Number(id));
    if (!usuario) {
        mostrarError(new Error("Usuario no encontrado."));
        return;
    }

    document.getElementById("id").value = usuario.id;
    document.getElementById("nombre").value = usuario.nombre;
    document.getElementById("username").value = usuario.username;
    document.getElementById("password").value = "";
    document.getElementById("password").required = false;
    document.getElementById("passwordHelp").textContent = "Déjala vacía para conservar la contraseña actual.";
    document.getElementById("rol").value = usuario.rol;
    document.getElementById("activo").value = String(usuario.activo);
    document.getElementById("formTitle").textContent = "Modificar usuario";
    document.getElementById("formState").textContent = "EDITANDO USUARIO";
    document.getElementById("btnGuardar").textContent = "Guardar cambios";
    document.getElementById("btnCancelar").classList.remove("d-none");
    window.scrollTo({ top: 0, behavior: "smooth" });
}

// Solicita confirmación antes de eliminar el acceso.
async function eliminarUsuario(id) {
    const usuario = usuariosActuales.find((item) => Number(item.id) === Number(id));
    if (!usuario) return;

    const confirmacion = await Swal.fire({
        title: "¿Eliminar usuario?",
        text: `Se eliminará el acceso de ${usuario.username}. Esta acción no se puede deshacer.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#bd3c42",
        reverseButtons: true
    });
    if (!confirmacion.isConfirmed) return;

    const datos = new FormData();
    datos.append("id", id);

    try {
        const respuesta = await solicitarJson(`${URL_USUARIOS}?accion=eliminar`, {
            method: "POST",
            body: datos
        });
        await listarUsuarios();
        avisoUsuario.fire({ icon: "success", title: respuesta.message });
    } catch (error) {
        mostrarError(error);
    }
}

function limpiarFormulario() {
    const formulario = document.getElementById("formUsuario");
    formulario.reset();
    document.getElementById("id").value = "";
    document.getElementById("password").required = true;
    document.getElementById("passwordHelp").textContent = "Mínimo 8 caracteres.";
    document.getElementById("formTitle").textContent = "Dar de alta usuario";
    document.getElementById("formState").textContent = "NUEVO USUARIO";
    document.getElementById("btnGuardar").textContent = "Crear usuario";
    document.getElementById("btnCancelar").classList.add("d-none");
}

// Convierte la respuesta del controlador en JSON y propaga errores HTTP.
async function solicitarJson(url, opciones = {}) {
    const respuesta = await fetch(url, opciones);
    const texto = await respuesta.text();
    let resultado;

    try {
        resultado = JSON.parse(texto);
    } catch {
        throw new Error("El servidor devolvió una respuesta no válida.");
    }
    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "No se pudo completar la solicitud.");
    }
    return resultado;
}

function escaparTexto(valor) {
    const elemento = document.createElement("span");
    elemento.textContent = valor ?? "";
    return elemento.innerHTML;
}

function mostrarError(error) {
    Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: error.message || "Inténtalo de nuevo."
    });
}
