const API_UBICACIONES = "../modules/ubicaciones/controller/UbicacionController.php";
let tablaUbicaciones;
let tablaMovimientos;
let tablaReabastos;
let productosUbicaciones = [];

const notificacionUbicacion = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2800,
    timerProgressBar: true
});

document.addEventListener("DOMContentLoaded", () => {
    tablaUbicaciones = new DataTable("#tablaUbicaciones", {
        data: [],
        columns: [
            { data: "producto", render: DataTable.render.text() },
            { data: "lote", render: DataTable.render.text() },
            { data: "rack", render: DataTable.render.text() },
            { data: "posicion", render: DataTable.render.text() },
            { data: "nivel", render: DataTable.render.text() },
            { data: "cantidad" },
            {
                data: "es_principal",
                render: (valor, tipo) => {
                    if (tipo !== "display") return valor;
                    return Number(valor) === 1
                        ? '<span class="badge text-bg-secondary">Almacén general</span>'
                        : '<span class="badge text-bg-primary">Rack</span>';
                }
            }
        ],
        order: [[0, "asc"]],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        scrollX: true,
        /* layout: {
            topStart: {
                buttons: window.crearBotonesExportacion("Historial-de-picking")
            }
        },*/
        language: idiomaTabla("ubicaciones")
    });

    tablaMovimientos = new DataTable("#tablaMovimientos", {
        data: [],
        columns: [
            { data: "realizado_en" },
            { data: "producto", render: DataTable.render.text() },
            { data: "cantidad" },
            { data: "origen", render: DataTable.render.text() },
            { data: "destino", render: DataTable.render.text() },
            { data: "montacarguistas", render: DataTable.render.text() },
            { data: "usuario_nombre", render: DataTable.render.text() },
            { data: "estatus", render: DataTable.render.text() }
        ],
        order: [[0, "desc"]],
        pageLength: 10,
        lengthMenu: [5, 10, 25],
        scrollX: true,
        /* layout: {
            topStart: {
                buttons: window.crearBotonesExportacion("Historial-de-picking")
            }
        },*/
        language: idiomaTabla("movimientos")
    });

    tablaReabastos = new DataTable("#tablaReabastos", {
        data: [],
        columns: [
            { data: "producto", render: DataTable.render.text() },
            { data: "stock_actual" },
            { data: "mensaje", render: DataTable.render.text() },
            { data: "creada_en" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (_dato, tipo, solicitud) => {
                    if (tipo !== "display" || !window.puedeAceptarReabasto) return "";
                    return `<button type="button" class="btn btn-sm btn-outline-primary" data-accion="aceptar-reabasto" data-id="${Number(solicitud.id)}" data-stock="${Number(solicitud.stock_actual)}">Recibir reabasto</button>`;
                }
            }
        ],
        order: [[3, "asc"]],
        pageLength: 5,
        lengthMenu: [5, 10, 25],
        scrollX: true,
        /* layout: {
            topStart: {
                buttons: window.crearBotonesExportacion("Historial-de-picking")
            }
        },*/
        language: idiomaTabla("reabastos")
    });

    const formulario = document.getElementById("formMovimiento");
    if (formulario) {
        formulario.addEventListener("submit", ejecutarMovimiento);
        document.getElementById("productoMovimiento").addEventListener("change", actualizarOrigenes);
        document.getElementById("origenMovimiento").addEventListener("change", actualizarStockOrigen);
    }
    document.getElementById("tablaReabastos").addEventListener("click", manejarAccionReabasto);

    cargarDatosUbicacion();
});

function idiomaTabla(tipo) {
    return {
        search: "Buscar:",
        searchPlaceholder: tipo === "ubicaciones" ? "Producto, lote o rack" : "Producto o montacarguista",
        lengthMenu: "Mostrar _MENU_ registros",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "No hay registros para mostrar",
        infoFiltered: "(filtrado de _MAX_ registros)",
        zeroRecords: "No se encontraron coincidencias",
        emptyTable: "Aún no hay datos registrados",
        paginate: { first: "Primera", last: "Última", next: "Siguiente", previous: "Anterior" }
    };
}

async function cargarDatosUbicacion() {
    try {
        const [catalogos, historial, reabastos] = await Promise.all([
            solicitarJson(`${API_UBICACIONES}?accion=catalogos`),
            solicitarJson(`${API_UBICACIONES}?accion=historial`),
            solicitarJson(`${API_UBICACIONES}?accion=reabastos`)
        ]);
        productosUbicaciones = catalogos.ubicaciones;
        tablaUbicaciones.clear().rows.add(catalogos.ubicaciones).draw();
        tablaMovimientos.clear().rows.add(historial.data).draw();
        tablaReabastos.clear().rows.add(reabastos.data).draw();
        llenarProductos(catalogos.productos);
        llenarMontacarguistas(catalogos.montacarguistas);
    } catch (error) {
        mostrarErrorUbicacion(error);
    }
}

function llenarProductos(productos) {
    const selector = document.getElementById("productoMovimiento");
    if (!selector) return;

    selector.replaceChildren(new Option("Selecciona un producto", ""));
    productos.forEach((producto) => {
        selector.add(new Option(`${producto.nombre} · lote ${producto.lote} · stock ${producto.cantidad}`, producto.id));
    });
    selector.disabled = productos.length === 0;
}

function llenarMontacarguistas(montacarguistas) {
    const selector = document.getElementById("montacarguistas");
    if (!selector) return;

    selector.replaceChildren();
    montacarguistas.forEach((persona) => selector.add(new Option(persona.nombre, persona.id)));
}

function actualizarOrigenes() {
    const productoId = document.getElementById("productoMovimiento").value;
    const selector = document.getElementById("origenMovimiento");
    const opciones = productosUbicaciones.filter((ubicacion) => String(ubicacion.producto_id) === productoId);

    selector.replaceChildren(new Option("Selecciona una ubicación", ""));
    opciones.forEach((ubicacion) => {
        const direccion = `${ubicacion.rack} / ${ubicacion.posicion} / ${ubicacion.nivel}`;
        selector.add(new Option(`${direccion} · ${ubicacion.cantidad} piezas`, ubicacion.id));
    });
    selector.disabled = opciones.length === 0;
    actualizarStockOrigen();
}

function actualizarStockOrigen() {
    const origenId = document.getElementById("origenMovimiento").value;
    const origen = productosUbicaciones.find((ubicacion) => String(ubicacion.id) === origenId);
    const cantidad = origen ? Number(origen.cantidad) : 0;
    const entrada = document.getElementById("cantidadMovimiento");
    entrada.max = cantidad;
    entrada.value = "";
    document.getElementById("stockOrigen").textContent = `Disponible: ${cantidad}`;
}

async function ejecutarMovimiento(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;

    const confirmacion = await Swal.fire({
        title: "¿Ejecutar movimiento?",
        text: "Se actualizarán las existencias de origen y destino y se registrará el picking simulado.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, mover",
        cancelButtonText: "Cancelar",
        reverseButtons: true
    });
    if (!confirmacion.isConfirmed) return;

    try {
        const respuesta = await solicitarJson(`${API_UBICACIONES}?accion=mover`, {
            method: "POST",
            body: new FormData(formulario)
        });
        formulario.reset();
        actualizarOrigenes();
        await cargarDatosUbicacion();

        if (respuesta.data.requiere_reabasto) {
            await Swal.fire({
                icon: "warning",
                title: "Reabasto solicitado",
                text: `El stock de AlmacenGeneral quedó en ${respuesta.data.stock_principal} piezas. Se generó una notificación simulada para solicitar más.`
            });
        } else {
            notificacionUbicacion.fire({ icon: "success", title: respuesta.message });
        }
    } catch (error) {
        mostrarErrorUbicacion(error);
    }
}

async function manejarAccionReabasto(evento) {
    const boton = evento.target.closest('[data-accion="aceptar-reabasto"]');
    if (!boton || !window.puedeAceptarReabasto) return;

    const minimo = Math.max(10 - Number(boton.dataset.stock), 1);
    const confirmacion = await Swal.fire({
        title: "Recibir reabasto",
        text: `Captura cuántas piezas recibiste. Debes ingresar al menos ${minimo} para dejar 10 en AlmacenGeneral.`,
        input: "number",
        inputValue: minimo,
        inputAttributes: { min: minimo, step: 1 },
        showCancelButton: true,
        confirmButtonText: "Registrar recepción",
        cancelButtonText: "Cancelar",
        inputValidator: (valor) => {
            const cantidad = Number(valor);
            if (!Number.isInteger(cantidad) || cantidad < minimo) {
                return `Ingresa un entero de ${minimo} o más piezas.`;
            }
        }
    });
    if (!confirmacion.isConfirmed) return;

    const datos = new FormData();
    datos.append("notificacion_id", boton.dataset.id);
    datos.append("cantidad", confirmacion.value);

    try {
        const respuesta = await solicitarJson(`${API_UBICACIONES}?accion=aceptar_reabasto`, {
            method: "POST",
            body: datos
        });
        await cargarDatosUbicacion();
        await Swal.fire({
            icon: "success",
            title: "Recepción registrada",
            text: `Se agregaron ${respuesta.data.cantidad_recibida} piezas de ${respuesta.data.producto}. AlmacenGeneral ahora tiene ${respuesta.data.stock_principal}.`
        });
    } catch (error) {
        mostrarErrorUbicacion(error);
    }
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

function mostrarErrorUbicacion(error) {
    Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: error.message || "Inténtalo de nuevo."
    });
}
