const URL = "../modules/usuarios/controller/UsuarioController.php";
let tablaUsuarios;

document.addEventListener("DOMContentLoaded", () => {
    tablaUsuarios = new DataTable("#tablaUsuarios", {
        columns: [
            { data: "id" },
            { data: "nombre", render: DataTable.render.text() },
            { data: "email", render: DataTable.render.text() },
            { data: "edad" },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-end",
                render: (_data, type, usuario) => {
                    if (type !== "display") return "";
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
        language: {
            search: "Buscar:",
            searchPlaceholder: "Nombre o correo",
            lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando _START_ a _END_ de _TOTAL_ usuarios",
            infoEmpty: "No hay usuarios para mostrar",
            infoFiltered: "(filtrado de _MAX_ usuarios)",
            zeroRecords: "No se encontraron coincidencias",
            emptyTable: "Aún no hay usuarios registrados",
            paginate: {
                first: "Primera",
                last: "Última",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });

    document.getElementById("formUsuario").addEventListener("submit", guardarUsuario);
    document.getElementById("btnCancelar").addEventListener("click", limpiarFormulario);
    document.querySelector("#tablaUsuarios tbody").addEventListener("click", manejarAccionTabla);
    listarUsuarios();
});

const notificacion = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2800,
    timerProgressBar: true
});

async function listarUsuarios() {
    try {
        const usuarios = await solicitarJson(`${URL}?accion=listar`);
        tablaUsuarios.clear().rows.add(usuarios).draw();
    } catch (error) {
        mostrarError(error);
    }
}

async function guardarUsuario(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const id = document.getElementById("id").value;
    const accion = id ? "actualizar" : "insertar";

    try {
        const resultado = await solicitarJson(`${URL}?accion=${accion}`, {
            method: "POST",
            body: new FormData(form)
        });
        if (!resultado.success) throw new Error("No se pudo guardar el usuario.");

        limpiarFormulario();
        await listarUsuarios();
        notificacion.fire({ icon: "success", title: id ? "Usuario actualizado" : "Usuario creado" });
    } catch (error) {
        mostrarError(error);
    }
}

async function manejarAccionTabla(event) {
    const boton = event.target.closest("[data-action]");
    if (!boton) return;

    const { action, id } = boton.dataset;
    if (action === "editar") await editar(id);
    if (action === "eliminar") await eliminar(id);
}

async function editar(id) {
    try {
        const usuario = await solicitarJson(`${URL}?accion=buscar&id=${encodeURIComponent(id)}`);
        if (usuario.error) throw new Error(usuario.error);

        document.getElementById("id").value = usuario.id;
        document.getElementById("nombre").value = usuario.nombre;
        document.getElementById("email").value = usuario.email;
        document.getElementById("edad").value = usuario.edad;
        document.getElementById("btnGuardar").textContent = "Actualizar usuario";
        document.getElementById("btnCancelar").classList.remove("d-none");
        document.getElementById("formState").textContent = "EDITANDO REGISTRO";
        document.getElementById("nombre").focus();
        window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
        mostrarError(error);
    }
}

async function eliminar(id) {
    const confirmacion = await Swal.fire({
        title: "¿Eliminar usuario?",
        text: "Esta acción no se puede deshacer.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#bd3c42",
        cancelButtonColor: "#66736d",
        reverseButtons: true
    });
    if (!confirmacion.isConfirmed) return;

    const formData = new FormData();
    formData.append("id", id);

    try {
        const resultado = await solicitarJson(`${URL}?accion=eliminar`, {
            method: "POST",
            body: formData
        });
        if (!resultado.success) throw new Error("No se pudo eliminar el usuario.");

        await listarUsuarios();
        notificacion.fire({ icon: "success", title: "Usuario eliminado" });
    } catch (error) {
        mostrarError(error);
    }
}

function limpiarFormulario() {
    document.getElementById("formUsuario").reset();
    document.getElementById("id").value = "";
    document.getElementById("btnGuardar").textContent = "Guardar usuario";
    document.getElementById("btnCancelar").classList.add("d-none");
    document.getElementById("formState").textContent = "NUEVO REGISTRO";
}

async function solicitarJson(url, options = {}) {
    const response = await fetch(url, options);
    const text = await response.text();
    let resultado;

    try {
        resultado = JSON.parse(text);
    } catch {
        throw new Error("El servidor devolvió una respuesta no válida.");
    }

    if (!response.ok) {
        throw new Error(resultado.error || "Ocurrió un error en la solicitud.");
    }
    return resultado;
}

function mostrarError(error) {
    Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: error.message || "Inténtalo de nuevo."
    });
}