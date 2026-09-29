const API_PRODUCTOS = "../modules/productos/controller/ProductoController.php";
const permisosProducto = window.permisosProducto || {};
let tablaProductos;

const notificacionProducto = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true
});

document.addEventListener("DOMContentLoaded", () => {
    tablaProductos = new DataTable("#tablaProductos", {
        data: [],
        columns: [
            { data: "id" },
            { data: "nombre", render: DataTable.render.text() },
            {
                data: "descripcion",
                className: "descripcion-producto",
                render: DataTable.render.text()
            },
            { data: "presentacion", render: DataTable.render.text() },
            { data: "lote", render: DataTable.render.text() },
            { data: "cantidad" },
            {
                data: "precio",
                render: (valor, tipo) => {
                    if (tipo !== "display") return valor;
                    return Number(valor).toLocaleString("es-MX", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            },
            { data: "fecha_caducidad" },
            { data: "categoria", render: DataTable.render.text() },
            {
                data: "estatus",
                render: (valor, tipo) => {
                    if (tipo !== "display") return valor;
                    const estilos = {
                        "Activo": "text-bg-success",
                        "Rechazado": "text-bg-danger",
                        "Cancelado": "text-bg-secondary",
                        "Sin existencia": "text-bg-warning"
                    };
                    const clase = estilos[valor] || "text-bg-secondary";
                    const etiqueta = escaparHtml(valor);
                    return `<span class="badge ${clase}">${etiqueta}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (_dato, tipo, producto) => {
                    if (tipo !== "display") return "";
                    const id = Number(producto.id);
                    const botones = [];
                    if (permisosProducto.modificar) {
                        botones.push(`<button type="button" class="btn btn-sm btn-outline-primary" data-accion="editar" data-id="${id}">Editar</button>`);
                    }
                    if (permisosProducto.eliminar) {
                        botones.push(`<button type="button" class="btn btn-sm btn-outline-danger" data-accion="eliminar" data-id="${id}">Eliminar</button>`);
                    }
                    return `<div class="d-flex gap-2">${botones.join("")}</div>`;
                }
            }
        ],
        order: [[0, "desc"]],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        scrollX: true,
        layout: {
            topStart: {
                buttons: window.crearBotonesExportacion("Productos", true)
            }
        },
        language: {
            search: "Buscar:",
            searchPlaceholder: "Nombre, lote o categoría",
            lengthMenu: "Mostrar _MENU_ productos",
            info: "Mostrando _START_ a _END_ de _TOTAL_ productos",
            infoEmpty: "No hay productos para mostrar",
            infoFiltered: "(filtrado de _MAX_ productos)",
            zeroRecords: "No se encontraron productos",
            emptyTable: "Aún no hay productos registrados",
            paginate: {
                first: "Primera",
                last: "Última",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });

    document.getElementById("tablaProductos").addEventListener("click", manejarAccionProducto);
    const formulario = document.getElementById("formProducto");
    if (formulario) {
        formulario.addEventListener("submit", guardarProducto);
        document.getElementById("btnCancelarEdicion").addEventListener("click", limpiarFormularioProducto);
    }

    listarProductos();
});

async function listarProductos() {
    try {
        const respuesta = await solicitarJson(`${API_PRODUCTOS}?accion=listar`);
        tablaProductos.clear().rows.add(respuesta.data).draw();
    } catch (error) {
        mostrarErrorProducto(error);
    }
}

async function guardarProducto(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const id = document.getElementById("productoId").value;
    const accion = id ? "actualizar" : "crear";
    const datos = new FormData(formulario);

    try {
        const respuesta = await solicitarJson(`${API_PRODUCTOS}?accion=${accion}`, {
            method: "POST",
            body: datos
        });
        limpiarFormularioProducto();
        await listarProductos();
        if (respuesta.requiere_reabastecimiento) {
            await Swal.fire({
                icon: "warning",
                title: "Reabasto solicitado",
                text: "El producto inicia con menos de 10 piezas en AlmacenGeneral. Se generó una notificación simulada."
            });
        } else {
            notificacionProducto.fire({ icon: "success", title: respuesta.message });
        }
    } catch (error) {
        mostrarErrorProducto(error);
    }
}

async function manejarAccionProducto(evento) {
    const boton = evento.target.closest("[data-accion]");
    if (!boton) return;

    const { accion, id } = boton.dataset;
    if (accion === "editar") await editarProducto(id);
    if (accion === "eliminar") await eliminarProducto(id);
}

async function editarProducto(id) {
    try {
        const respuesta = await solicitarJson(`${API_PRODUCTOS}?accion=buscar&id=${encodeURIComponent(id)}`);
        const producto = respuesta.data;
        document.getElementById("productoId").value = producto.id;
        document.getElementById("nombre").value = producto.nombre;
        document.getElementById("descripcion").value = producto.descripcion;
        document.getElementById("presentacion").value = producto.presentacion;
        document.getElementById("lote").value = producto.lote;
        document.getElementById("cantidad").value = producto.cantidad;
        document.getElementById("precio").value = producto.precio;
        document.getElementById("fecha_caducidad").value = producto.fecha_caducidad;
        document.getElementById("categoria").value = producto.categoria;
        document.getElementById("estatus").value = producto.estatus;
        document.getElementById("formTitle").textContent = "Modificar producto";
        document.getElementById("formState").textContent = "EDITANDO PRODUCTO";
        document.getElementById("btnGuardarProducto").textContent = "Guardar cambios";
        document.getElementById("btnCancelarEdicion").classList.remove("d-none");
        window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
        mostrarErrorProducto(error);
    }
}

async function eliminarProducto(id) {
    const confirmacion = await Swal.fire({
        title: "¿Eliminar producto?",
        text: "Esta acción no se puede deshacer.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true
    });
    if (!confirmacion.isConfirmed) return;

    const datos = new FormData();
    datos.append("id", id);

    try {
        const respuesta = await solicitarJson(`${API_PRODUCTOS}?accion=eliminar`, {
            method: "POST",
            body: datos
        });
        await listarProductos();
        notificacionProducto.fire({ icon: "success", title: respuesta.message });
    } catch (error) {
        mostrarErrorProducto(error);
    }
}

function limpiarFormularioProducto() {
    const formulario = document.getElementById("formProducto");
    if (!formulario) return;

    formulario.reset();
    document.getElementById("productoId").value = "";
    document.getElementById("formTitle").textContent = "Registrar producto";
    document.getElementById("formState").textContent = "NUEVO PRODUCTO";
    document.getElementById("btnGuardarProducto").textContent = "Guardar producto";
    document.getElementById("btnCancelarEdicion").classList.add("d-none");
}

async function solicitarJson(url, opciones = {}) {
    const respuesta = await fetch(url, opciones);
    const texto = await respuesta.text();
    let datos;

    try {
        datos = JSON.parse(texto);
    } catch {
        throw new Error("El servidor devolvió una respuesta no válida.");
    }

    if (!respuesta.ok || !datos.success) {
        throw new Error(datos.message || "No se pudo completar la solicitud.");
    }
    return datos;
}

function escaparHtml(valor) {
    const elemento = document.createElement("span");
    elemento.textContent = valor ?? "";
    return elemento.innerHTML;
}

function mostrarErrorProducto(error) {
    Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: error.message || "Inténtalo de nuevo."
    });
}
