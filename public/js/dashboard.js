document.addEventListener("DOMContentLoaded", () => {
    new DataTable("#tablaDashboardMovimientos", {
        layout: {
            topStart: {
                buttons: window.crearBotonesExportacion("Movimientos-recientes")
            }
        },
        pageLength: 8,
        lengthChange: false,
        language: {
            search: "Buscar:",
            searchPlaceholder: "Producto o montacarguista",
            info: "Mostrando _START_ a _END_ de _TOTAL_ movimientos",
            infoEmpty: "Todavía no hay movimientos registrados",
            infoFiltered: "(filtrado de _MAX_ movimientos)",
            zeroRecords: "No se encontraron movimientos",
            emptyTable: "Todavía no hay movimientos registrados",
            paginate: {
                first: "Primera",
                last: "Última",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });
});
