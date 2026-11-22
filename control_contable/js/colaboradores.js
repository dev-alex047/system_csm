
const estadoColaboradores = {
    buscar: "",
    pagina: 1,
    lista: [],
    solicitud: 0
};

function bindColaboradores() {
    $("#coForm .form-label").each(function(i) {
    const $control = $(this).next("input, select, textarea");

    if ($control.length && !$control.attr("id")) {
        const id = "coCampo" + i;
        $control.attr("id", id);
        $(this).attr("for", id);
    }
});
    $(document).on("click", "#btnColaboradores", function() {
        if (!window.CSM_ES_ADMIN) return;

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById("coModal")
        ).show();
    });

    $("#coModal").on("shown.bs.modal", function() {
        mostrarListadoColaboradores();
        consultarColaboradores();
    });

    $(document).on("submit", "#coFormBuscar", function(e) {
        e.preventDefault();
        estadoColaboradores.buscar = $("#coBuscar").val().trim();
        estadoColaboradores.pagina = 1;
        consultarColaboradores();
    });

    $(document).on("click", "#coLimpiarBusqueda", function() {
        $("#coBuscar").val("");
        estadoColaboradores.buscar = "";
        estadoColaboradores.pagina = 1;
        consultarColaboradores();
    });

    $(document).on("click", "#coNuevo", function() {
        abrirFormularioColaborador();
    });

    $(document).on("click", ".js-co-editar", function() {
        const id = Number($(this).attr("data-id"));

        const registro = estadoColaboradores.lista.find(function(x) {
            return Number(x.id) === id;
        });

        if (registro) abrirFormularioColaborador(registro);
    });

    $(document).on("click", ".js-co-eliminar", function() {
        const id = Number($(this).attr("data-id"));

        const registro = estadoColaboradores.lista.find(function(x) {
            return Number(x.id) === id;
        });

        if (registro) confirmarEliminarColaborador(registro);
    });

    $(document).on("click", ".js-co-pagina", function() {
        const pagina = Number($(this).attr("data-pagina"));

        if (!Number.isSafeInteger(pagina) || pagina < 1) return;

        estadoColaboradores.pagina = pagina;
        consultarColaboradores();
    });

    $(document).on("click", "#coCancelar, #coVolver", function() {
        mostrarListadoColaboradores();
    });

    $(document).on("change", "#coForm [name='dias_trabajo']", function() {
        actualizarDiasColaboradores();
    });

    $(document).on("submit", "#coForm", function(e) {
        e.preventDefault();
        guardarColaboradorDesdeFormulario();
    });
}

function mostrarListadoColaboradores() {
    $("#coFormularioPanel").addClass("d-none");
    $("#coListadoPanel").removeClass("d-none");

    document.querySelector("#coModal .modal-body").scrollTop = 0;
}

function consultarColaboradores() {
    const solicitud = ++estadoColaboradores.solicitud;

    $("#coTabla").empty().append(
        $("<tr>").append(
            $("<td colspan='6' class='text-center text-secondary py-5'>")
                .text("Consultando colaboradores...")
        )
    );

    $("#coPaginacion").empty();
    $("#coResumen").text("Cargando...");

    $.ajax({
        url: "php/index.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "getColaboradores",
            buscar: estadoColaboradores.buscar,
            pagina: estadoColaboradores.pagina
        }
    }).done(function(r) {
        if (solicitud !== estadoColaboradores.solicitud) return;

        if (!r || r.status !== "ok") {
            errorColaboradores(
                r && r.answer || "No fue posible obtener el listado."
            );
            return;
        }

        estadoColaboradores.lista = Array.isArray(r.results.colaboradores)
            ? r.results.colaboradores : [];

        estadoColaboradores.pagina = Number(r.results.pagina) || 1;
        pintarColaboradores(r.results);

    }).fail(function(xhr) {
        if (solicitud !== estadoColaboradores.solicitud) return;

        errorColaboradores(
            xhr.responseJSON && xhr.responseJSON.answer ||
            "No fue posible conectar con el servidor."
        );
    });
}

function pintarColaboradores(datos) {
    const $tbody = $("#coTabla").empty();
    const lista = estadoColaboradores.lista;

    $("#coResumen").text(
        Number(datos.total || 0) + " colaboradores encontrados"
    );

    if (!lista.length) {
        $tbody.append(
            $("<tr>").append(
                $("<td colspan='6' class='text-center py-5'>").append(
                    $("<div class='co-vacio'>").append(
                        $("<i class='bi bi-people fs-1 text-success'>"),
                        $("<p class='fw-semibold mt-2 mb-1'>")
                            .text("Sin colaboradores para mostrar"),
                        $("<small class='text-secondary'>")
                            .text("Agrega uno nuevo o cambia los filtros de búsqueda.")
                    )
                )
            )
        );
    }

    lista.forEach(function(c) {
        const nombre = [c.nombre, c.apellidos]
            .filter(Boolean).join(" ");

        const $avatar = $("<span class='co-avatar'>")
            .text(inicialesColaborador(nombre));

        const $empleado = $("<div class='d-flex align-items-center gap-3'>")
            .append(
                $avatar,
                $("<div>").append(
                    $("<strong class='d-block'>").text(nombre),
                    $("<small class='text-secondary'>")
                        .text("ID " + c.id)
                )
            );

        const $acciones = $("<div class='d-flex gap-2'>").append(
            $("<button type='button' class='btn btn-sm btn-outline-success js-co-editar' title='Editar' aria-label='Editar colaborador'>")
                .attr("data-id", c.id)
                .append("<i class='bi bi-pencil-square'></i>"),

            $("<button type='button' class='btn btn-sm btn-outline-danger js-co-eliminar' title='Eliminar' aria-label='Eliminar colaborador'>")
                .attr("data-id", c.id)
                .append("<i class='bi bi-trash'></i>")
        );

        const dias = String(c.dias_trabajo || "")
            .split(",").filter(Boolean);

        const $tr = $("<tr>");

        $tr.append($("<td>").append($empleado));

        $tr.append($("<td>").append(
            $("<div>").text(
                c.correo_empresarial || c.correo || "Sin correo"
            ),
            $("<small class='text-secondary d-block'>").text(
                c.movil || c.telefono || "Sin teléfono"
            )
        ));

        $tr.append($("<td>").append(
            $("<strong class='d-block'>")
                .text(c.cargo || "Sin cargo"),
            $("<small class='text-secondary'>")
                .text(c.area || "Sin área")
        ));

        $tr.append($("<td>").append(
            $("<span class='co-chip'>")
                .text(dias.length + " días / semana"),
            $("<small class='text-secondary d-block mt-1'>")
                .text(c.tipo_pago || "Pago sin definir")
        ));

        $tr.append(
            $("<td class='text-nowrap'>").text(
                formatearFechaColaborador(c.fecha_ingreso)
            )
        );

        $tr.append($("<td>").append($acciones));
        $tbody.append($tr);
    });

    const pagina = Number(datos.pagina) || 1;
    const paginas = Number(datos.paginas) || 1;
    const $pag = $("#coPaginacion").empty();

    $pag.append(
        $("<button type='button' class='btn btn-outline-secondary btn-sm js-co-pagina'>")
            .attr("data-pagina", pagina - 1)
            .prop("disabled", pagina <= 1)
            .text("Anterior"),

        $("<span class='small text-secondary align-self-center'>")
            .text("Página " + pagina + " de " + paginas),

        $("<button type='button' class='btn btn-outline-secondary btn-sm js-co-pagina'>")
            .attr("data-pagina", pagina + 1)
            .prop("disabled", pagina >= paginas)
            .text("Siguiente")
    );
}

function inicialesColaborador(nombre) {
    const partes = String(nombre || "")
        .trim().split(/\s+/).filter(Boolean);

    return partes.slice(0, 2).map(function(x) {
        return x.charAt(0).toUpperCase();
    }).join("") || "?";
}

function formatearFechaColaborador(fecha) {
    const texto = String(fecha || "");

    if (!/^\d{4}-\d{2}-\d{2}/.test(texto)) return "Sin fecha";

    return texto.slice(0, 10).split("-").reverse().join("/");
}

function abrirFormularioColaborador(registro = null) {
    const $form = $("#coForm");

    $form[0].reset();
    $form.find(".is-invalid").removeClass("is-invalid");

    $("#coEmpleadoId").val(registro ? registro.id : 0);

    $("#coTituloFormulario").text(
        registro ? "Editar colaborador" : "Agregar colaborador"
    );

    if (registro) {
        $form.find("[name]")
            .not("[name='dias_trabajo']")
            .each(function() {
                if (
                    Object.prototype.hasOwnProperty.call(registro, this.name) &&
                    this.name !== "id"
                ) {
                    $(this).val(
                        registro[this.name] === null
                            ? ""
                            : registro[this.name]
                    );
                }
            });

        const dias = String(registro.dias_trabajo || "").split(",");

        $form.find("[name='dias_trabajo']").each(function() {
            this.checked = dias.includes(this.value);
        });
    } else {
        $form.find("[name='dias_trabajo']")
            .prop("checked", false);
    }

    actualizarDiasColaboradores();

    $("#coListadoPanel").addClass("d-none");
    $("#coFormularioPanel").removeClass("d-none");

    document.querySelector("#coModal .modal-body").scrollTop = 0;
    $("#coForm [name='nombre']").trigger("focus");
}

function actualizarDiasColaboradores() {
    const total = $("#coForm [name='dias_trabajo']:checked").length;

    $("#coDiasContador").text(
        total + " de 7 días seleccionados"
    );
}

function guardarColaboradorDesdeFormulario() {
    const $form = $("#coForm");

    if (!$form[0].reportValidity()) return;

    const dias = $form.find("[name='dias_trabajo']:checked")
        .map(function() {
            return this.value;
        }).get();

    if (!dias.length) {
        Swal.fire(
            "Revisa el horario",
            "Selecciona al menos un día de trabajo.",
            "warning"
        );
        return;
    }

    const datos = {
        action: "guardarColaborador",
        dias_trabajo: dias.join(",")
    };

    $form.find("[name]")
        .not("[name='dias_trabajo']")
        .each(function() {
            datos[this.name] = $(this).val();
        });

    const $boton = $("#coGuardar");
    $boton.prop("disabled", true);

    $.ajax({
        url: "php/index.php",
        type: "POST",
        dataType: "json",
        data: datos
    }).done(function(r) {
        if (!r || r.status !== "ok") {
            errorColaboradores(
                r && r.answer || "No fue posible guardar el colaborador."
            );
            return;
        }

        mostrarListadoColaboradores();
        consultarColaboradores();

        Swal.fire({
            icon: "success",
            title: "Guardado",
            text: r.answer,
            timer: 1800,
            showConfirmButton: false
        });

    }).fail(function(xhr) {
        errorColaboradores(
            xhr.responseJSON && xhr.responseJSON.answer ||
            "No fue posible guardar el colaborador."
        );
    }).always(function() {
        $boton.prop("disabled", false);
    });
}

function confirmarEliminarColaborador(registro) {
    Swal.fire({
        title: "¿Eliminar colaborador?",
        text: [registro.nombre, registro.apellidos]
            .filter(Boolean).join(" ") +
            " dejará de aparecer en el directorio.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#bb3340"
    }).then(function(confirmacion) {
        if (!confirmacion.isConfirmed) return;

        $.ajax({
            url: "php/index.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "eliminarColaborador",
                id: registro.id
            }
        }).done(function(r) {
            if (!r || r.status !== "ok") {
                errorColaboradores(
                    r && r.answer || "No fue posible eliminar."
                );
                return;
            }

            consultarColaboradores();

            Swal.fire({
                icon: "success",
                title: "Eliminado",
                text: r.answer,
                timer: 1600,
                showConfirmButton: false
            });

        }).fail(function(xhr) {
            errorColaboradores(
                xhr.responseJSON && xhr.responseJSON.answer ||
                "No fue posible eliminar."
            );
        });
    });
}

function errorColaboradores(mensaje) {
    Swal.fire({
        icon: "error",
        title: "Colaboradores",
        text: mensaje,
        confirmButtonColor: "#086b47"
    });
}
