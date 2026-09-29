window.crearBotonesExportacion = function (titulo, excluirUltimaColumna = false) {
    const columnas = excluirUltimaColumna ? ":not(:last-child)" : ":visible";
    const exportOptions = { columns: columnas };

    return [
        {
            extend: "csvHtml5",
            text: "Descargar CSV",
            title: titulo,
            className: "btn btn-sm btn-outline-secondary",
            exportOptions
        },
        {
            extend: "excelHtml5",
            text: "Descargar Excel",
            title: titulo,
            className: "btn btn-sm btn-outline-success",
            exportOptions
        },
        {
            extend: "pdfHtml5",
            text: "Descargar PDF",
            title: titulo,
            className: "btn btn-sm btn-outline-danger",
            orientation: "landscape",
            pageSize: "A4",
            exportOptions
        }
    ];
};
