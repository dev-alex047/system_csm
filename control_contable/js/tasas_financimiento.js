
const stateTasas = {
    pagina: 1,
    paginas: 1,
    buscar: "",
    tasas: [],
    grupos: [],
    id: 0,
    version: "",
    token: "",
    activo: 1,
    guardando: false,
    peticion: 0,
    timer: null
};


function campoTasa(nombre, etiqueta, tipo, atributos, ancho) {
    const html =
        '<div class="col-md-' + (ancho || 6) + '">' +
            '<label class="form-label" for="tf_' + nombre + '">' +
                etiqueta +
            '</label>' +
            '<input type="' + (tipo || "number") + '"' +
                ' class="form-control"' +
                ' id="tf_' + nombre + '"' +
                ' name="' + nombre + '"' +
                ' ' + (atributos || "") + '>' +
        '</div>';

    return html;
}


function selectTasa(nombre, etiqueta, opciones, ancho) {
    let html =
        '<div class="col-md-' + (ancho || 6) + '">' +
            '<label class="form-label" for="tf_' + nombre + '">' +
                etiqueta +
            '</label>' +
            '<select class="form-select"' +
                ' id="tf_' + nombre + '"' +
                ' name="' + nombre + '">';

    opciones.forEach(function(opcion) {
        html += '<option value="' + opcion[0] + '">' +
            opcion[1] + '</option>';
    });

    html += '</select></div>';

    return html;
}


function seccionTasa(id, titulo, descripcion, campos) {
    return '<section class="tf-form-section d-none" data-section="' + id + '">' +
        '<div class="tf-form-heading">' +
            '<h3>' + titulo + '</h3>' +
            '<p>' + descripcion + '</p>' +
        '</div>' +
        '<div class="row g-3">' + campos + '</div>' +
    '</section>';
}


function htmlFormularioTasas() {
    const decimal = 'min="0" step="0.0001"';
    const entero = 'min="0" step="1"';

    let basico = "";

    basico += campoTasa(
        "monto_limite_inferior",
        "Límite inferior ($)",
        "number",
        'required min="0.01" step="0.01"'
    );

    basico += campoTasa(
        "monto_limite_superior",
        "Límite superior ($)",
        "number",
        'required min="0.01" step="0.01"'
    );

    basico += campoTasa(
        "numero_pagos",
        "Número de pagos",
        "number",
        'required min="1" max="1000" step="1"'
    );

    basico += selectTasa(
        "periodicidad_pago",
        "Periodicidad de pago",
        [
            ["DIARIO", "Diario"],
            ["SEMANAL", "Semanal"],
            ["CATORCENAL", "Catorcenal"],
            ["QUINCENAL", "Quincenal"],
            ["MENSUAL", "Mensual"],
            ["PERSONALIZADO", "Definir número de días"]
        ]
    );

    basico += '<div class="col-md-6 d-none" id="tfDiasBox">' +
        '<label for="tf_numero_dias" class="form-label">Días entre pagos</label>' +
        '<input type="number" id="tf_numero_dias" name="numero_dias"' +
        ' class="form-control" min="1" max="3650" step="1">' +
    '</div>';

    basico += campoTasa(
        "nombre",
        "Nombre del financiamiento (automático)",
        "text",
        'readonly maxlength="255"',
        12
    );

    basico += campoTasa(
        "vigente_desde",
        "Vigente desde (opcional)",
        "date"
    );

    basico += campoTasa(
        "vigente_hasta",
        "Vigente hasta (opcional)",
        "date"
    );

    let avanzado = "";

    avanzado += campoTasa(
        "tasa_interes",
        "Porcentaje de interés",
        "number",
        decimal
    );

    avanzado += selectTasa(
        "base_tasa_interes",
        "Base del interés",
        [
            ["", "Sin intereses"],
            ["TOTAL", "Total del crédito"],
            ["ANUAL", "Anual"],
            ["MENSUAL", "Mensual"],
            ["POR_PAGO", "Por pago"]
        ]
    );

    avanzado += campoTasa(
        "tasa_interes_moratorio",
        "Tasa de interés moratorio (%)",
        "number",
        decimal
    );

    avanzado += selectTasa(
        "base_tasa_moratoria",
        "Base del interés moratorio",
        [
            ["", "Sin interés moratorio"],
            ["DIARIA", "Diaria"],
            ["MENSUAL", "Mensual"],
            ["ANUAL", "Anual"],
            ["POR_PAGO", "Por pago"]
        ]
    );

    avanzado +=
        '<div class="col-12">' +
            '<label class="form-label">Tipo de interés moratorio</label>' +
            '<div class="tf-segmento" id="tfMoratorioPills">' +
                '<button type="button" class="active" data-valor="SIMPLE">Simple</button>' +
                '<button type="button" data-valor="COMPUESTO">Compuesto</button>' +
            '</div>' +
            '<input type="hidden" id="tf_tipo_interes_moratorio"' +
                ' name="tipo_interes_moratorio" value="SIMPLE">' +
        '</div>';

    avanzado += campoTasa(
        "cuotas_gracia",
        "Cuotas de gracia (solo intereses)",
        "number",
        entero
    );

    avanzado +=
        '<div class="col-12">' +
            '<label class="form-label">Tipo de amortización</label>' +
            '<div class="tf-segmento" id="tfAmortizacionPills">' +
                '<button type="button" class="active" data-valor="FLAT">Flat</button>' +
                '<button type="button" data-valor="AMERICANA">Americana</button>' +
                '<button type="button" data-valor="FRANCESA">Francés</button>' +
                '<button type="button" data-valor="DECRECIENTE">Decreciente</button>' +
            '</div>' +
            '<input type="hidden" id="tf_tipo_amortizacion"' +
                ' name="tipo_amortizacion" value="FLAT">' +
            '<p id="tfNotaAmortizacion" class="tf-note mt-3"></p>' +
        '</div>';

    avanzado += '<div class="col-md-6 tf-francesa">' +
        campoTasa(
            "porcentaje_capital_inicial",
            "Porcentaje inicial a capital",
            "number",
            'min="0" max="100" step="0.0001"',
            12
        ) +
    '</div>';

    avanzado += '<div class="col-md-6 tf-francesa">' +
        campoTasa(
            "porcentaje_capital_final",
            "Porcentaje final a capital",
            "number",
            'min="0" max="100" step="0.0001"',
            12
        ) +
    '</div>';

    avanzado += '<div class="col-md-6 tf-decreciente">' +
        campoTasa(
            "porcentaje_pago_inicial",
            "Variación inicial del pago (%)",
            "number",
            'min="0" max="100" step="0.0001"',
            12
        ) +
    '</div>';

    let condiciones = "";

    condiciones += selectTasa(
        "tipo_comision_apertura",
        "Tipo de comisión por apertura",
        [["MONTO", "Monto fijo ($)"], ["PORCENTAJE", "Porcentaje (%)"]]
    );

    condiciones += campoTasa(
        "comision_apertura",
        "Comisión por apertura",
        "number",
        decimal
    );

    condiciones += selectTasa(
        "requiere_aval",
        "¿Requiere aval?",
        [["0", "No"], ["1", "Sí"]]
    );

    condiciones += selectTasa(
        "requiere_beneficiario",
        "¿Requiere beneficiario?",
        [["0", "No"], ["1", "Sí"]]
    );

    condiciones +=
        '<div class="col-12">' +
            '<div class="form-check form-switch">' +
                '<input type="checkbox" id="tfTodosGrupos"' +
                    ' class="form-check-input" checked>' +
                '<label for="tfTodosGrupos" class="form-check-label">' +
                    'Disponible para todos los grupos de clientes' +
                '</label>' +
            '</div>' +
            '<input type="hidden" id="tf_aplica_todos_grupos"' +
                ' name="aplica_todos_grupos" value="1">' +
        '</div>' +
        '<div class="col-12 d-none" id="tfGrupoBox">' +
            '<label for="tf_grupo_cliente_id" class="form-label">' +
                'Grupo de clientes autorizado' +
            '</label>' +
            '<select class="form-select" id="tf_grupo_cliente_id"' +
                ' name="grupo_cliente_id">' +
                '<option value="">Selecciona un grupo</option>' +
            '</select>' +
        '</div>';

    condiciones += campoTasa(
        "renovacion_numero_pagos",
        "Ofrecer renovación desde el pago número",
        "number",
        entero
    );

    condiciones += selectTasa(
        "permite_creditos_activos",
        "¿Permitir otros créditos activos?",
        [["1", "Sí"], ["0", "No"]]
    );

    condiciones += campoTasa(
        "ciclos_limite_inferior",
        "Ciclo mínimo",
        "number",
        entero
    );

    condiciones += campoTasa(
        "ciclos_limite_superior",
        "Ciclo máximo",
        "number",
        entero
    );

    condiciones += selectTasa(
        "tipo_bono_apertura",
        "Tipo de bono por apertura",
        [["MONTO", "Monto fijo ($)"], ["PORCENTAJE", "Porcentaje (%)"]]
    );

    condiciones += campoTasa(
        "bono_apertura",
        "Bono por apertura de crédito",
        "number",
        decimal
    );

    condiciones += selectTasa(
        "tipo_bono_puntual",
        "Tipo de bono por pago puntual",
        [["MONTO", "Monto fijo ($)"], ["PORCENTAJE", "Porcentaje (%)"]]
    );

    condiciones += campoTasa(
        "bono_pago_puntual",
        "Bono por pago puntual",
        "number",
        decimal
    );

    condiciones += selectTasa(
        "tipo_bono_pago_normal",
        "Tipo de bono por pago impuntual",
        [["MONTO", "Monto fijo ($)"], ["PORCENTAJE", "Porcentaje (%)"]]
    );

    condiciones += campoTasa(
        "bono_pago_normal",
        "Bono por pago impuntual",
        "number",
        decimal
    );

    condiciones += campoTasa(
        "semaforo_amarillo_mora",
        "Semáforo amarillo de mora (pagos)",
        "number",
        entero,
        12
    );

    let contrato = "";

    contrato += campoTasa(
        "titulo_contrato",
        "Título del contrato",
        "text",
        'maxlength="255"',
        12
    );

    contrato +=
        '<div class="col-12">' +
            '<label for="tfVariableContrato" class="form-label">' +
                'Agregar variable al contrato' +
            '</label>' +
            '<select class="form-select" id="tfVariableContrato">' +
                '<option value="">Selecciona una variable...</option>' +
                '<option value="{{RazonSocial.Acreedor}}">Acreedor</option>' +
                '<option value="{{RazonSocial.Deudor}}">Deudor</option>' +
                '<option value="{{RazonSocial.Aval}}">Aval</option>' +
                '<option value="{{Domicilio.Deudor}}">Domicilio del deudor</option>' +
                '<option value="{{Financiamiento.Credito}}">Monto del crédito</option>' +
                '<option value="{{NumParcialidades.Credito}}">Número de pagos</option>' +
                '<option value="{{PrimerPago.Credito}}">Primer pago</option>' +
                '<option value="{{UltimoPago.Credito}}">Último pago</option>' +
                '<option value="{{Hoy.Otros}}">Fecha de hoy</option>' +
            '</select>' +
        '</div>' +
        '<div class="col-12">' +
            '<label for="tf_contrato_perfil" class="form-label">' +
                'Texto del contrato' +
            '</label>' +
            '<textarea id="tf_contrato_perfil"' +
                ' name="contrato_perfil"' +
                ' class="form-control"' +
                ' maxlength="100000" rows="14"' +
                ' placeholder="Redacta las cláusulas del contrato..."></textarea>' +
            '<small class="text-secondary">' +
                'Las variables se sustituirán al generar el contrato real.' +
            '</small>' +
        '</div>';

    let html = '<form id="tfFormulario" autocomplete="off" novalidate>';

    html += '<input type="hidden" name="id" id="tf_id">';
    html += '<input type="hidden" name="version" id="tf_version">';
    html += '<input type="hidden" name="solicitud_id" id="tf_solicitud_id">';

    html +=
        '<nav class="tf-pasos" aria-label="Secciones del financiamiento">' +
            '<button type="button" class="tf-paso active" data-paso="basico">' +
                '1. Financiamiento' +
            '</button>' +
            '<button type="button" class="tf-paso" data-paso="avanzado">' +
                '2. Intereses' +
            '</button>' +
            '<button type="button" class="tf-paso" data-paso="condiciones">' +
                '3. Condiciones y bonos' +
            '</button>' +
            '<button type="button" class="tf-paso" data-paso="contrato">' +
                '4. Contrato' +
            '</button>' +
        '</nav>';

    html += seccionTasa(
        "basico",
        "Financiamiento básico",
        "Configura los importes y las fechas de pago.",
        basico
    );

    html += seccionTasa(
        "avanzado",
        "Intereses y amortización",
        "Define intereses, moratorios y método de amortización.",
        avanzado
    );

    html += seccionTasa(
        "condiciones",
        "Condiciones y bonos",
        "Requisitos, grupos de clientes, comisiones e incentivos.",
        condiciones
    );

    html += seccionTasa(
        "contrato",
        "Contrato",
        "Configura el contrato que corresponde a este financiamiento.",
        contrato
    );

    html +=
        '<div class="tf-pie">' +
            '<button type="button" id="tfVolver" class="btn btn-outline-secondary">' +
                'Volver al catálogo' +
            '</button>' +
            '<div class="d-flex flex-wrap gap-2">' +
                '<button type="button" id="tfAnterior" class="btn btn-outline-success">' +
                    'Anterior' +
                '</button>' +
                '<button type="button" id="tfSiguiente" class="btn btn-outline-success">' +
                    'Siguiente' +
                '</button>' +
                '<button type="submit" id="tfGuardar" class="btn btn-success">' +
                    '<i class="bi bi-check2-circle me-2"></i>Guardar tasa' +
                '</button>' +
            '</div>' +
        '</div>';

    html += '</form>';

    return html;
}


function construirModalTasas() {
    if ($("#tfModal").length) {
        return;
    }

    const html =
        '<div class="modal fade" id="tfModal" tabindex="-1" aria-hidden="true">' +
            '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable tf-dialog">' +
                '<div class="modal-content tf-modal">' +
                    '<div class="modal-header">' +
                        '<div>' +
                            '<small class="tf-eyebrow">CATÁLOGO FINANCIERO</small>' +
                            '<h2 class="modal-title h4 fw-bold mb-1">' +
                                'Tasas de financiamiento' +
                            '</h2>' +
                            '<p class="small text-secondary mb-0">' +
                                'Plazos, intereses, comisiones y contratos' +
                            '</p>' +
                        '</div>' +
                        '<button type="button" class="btn-close"' +
                            ' data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
                    '</div>' +
                    '<div class="modal-body" id="tfBody">' +
                        '<div id="tfVistaLista">' +
                            '<div class="tf-barra">' +
                                '<div class="tf-buscar">' +
                                    '<i class="bi bi-search"></i>' +
                                    '<input type="search" id="tfBuscar"' +
                                        ' class="form-control" maxlength="100"' +
                                        ' placeholder="Buscar financiamiento por nombre...">' +
                                '</div>' +
                                '<button type="button" class="btn btn-success" id="tfNueva">' +
                                    '<i class="bi bi-plus-circle me-2"></i>Nueva tasa' +
                                '</button>' +
                            '</div>' +
                            '<p class="small text-secondary my-3" id="tfResumen"></p>' +
                            '<div id="tfTarjetas"></div>' +
                            '<div id="tfPaginacion"></div>' +
                        '</div>' +
                        '<div id="tfVistaFormulario" class="d-none">' +
                            '<div class="tf-titulo-form">' +
                                '<h3 id="tfTituloFormulario">Nueva tasa</h3>' +
                                '<p>Completa la configuración del financiamiento.</p>' +
                            '</div>' +
                            htmlFormularioTasas() +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

    $("body").append(html);
}


function valoresInicialesTasa() {
    return {
        monto_limite_inferior: "1.00",
        monto_limite_superior: "100000.00",
        numero_pagos: "1",
        periodicidad_pago: "SEMANAL",
        numero_dias: "0",
        tasa_interes: "0",
        base_tasa_interes: "",
        tasa_interes_moratorio: "0",
        base_tasa_moratoria: "",
        tipo_interes_moratorio: "SIMPLE",
        cuotas_gracia: "0",
        tipo_amortizacion: "FLAT",
        porcentaje_capital_inicial: "0",
        porcentaje_capital_final: "0",
        porcentaje_pago_inicial: "0",
        tipo_comision_apertura: "MONTO",
        comision_apertura: "0",
        requiere_aval: "0",
        requiere_beneficiario: "0",
        aplica_todos_grupos: "1",
        grupo_cliente_id: "",
        renovacion_numero_pagos: "0",
        permite_creditos_activos: "1",
        ciclos_limite_inferior: "0",
        ciclos_limite_superior: "99999",
        tipo_bono_apertura: "MONTO",
        bono_apertura: "0",
        tipo_bono_puntual: "MONTO",
        bono_pago_puntual: "0",
        tipo_bono_pago_normal: "MONTO",
        bono_pago_normal: "0",
        semaforo_amarillo_mora: "3",
        titulo_contrato: "CONTRATO DE APERTURA DE CRÉDITO SIMPLE",
        contrato_perfil: "",
        vigente_desde: "",
        vigente_hasta: ""
    };
}


function mostrarPasoTasa(paso) {
    const pasos = ["basico", "avanzado", "condiciones", "contrato"];

    let indice = typeof paso === "number"
        ? paso
        : pasos.indexOf(paso);

    indice = Math.max(0, Math.min(3, indice));

    $("#tfFormulario .tf-form-section").addClass("d-none");

    $("#tfFormulario .tf-form-section").filter(function() {
        return $(this).attr("data-section") === pasos[indice];
    }).removeClass("d-none");

    $("#tfFormulario .tf-paso")
        .removeClass("active")
        .attr("aria-selected", "false");

    $("#tfFormulario .tf-paso").filter(function() {
        return $(this).attr("data-paso") === pasos[indice];
    }).addClass("active").attr("aria-selected", "true");

    $("#tfAnterior").prop("disabled", indice === 0);
    $("#tfSiguiente").toggleClass("d-none", indice === 3);

    $("#tfBody").scrollTop(0);
}


function indicePasoTasa() {
    const paso = $("#tfFormulario .tf-paso.active").attr("data-paso");

    return ["basico", "avanzado", "condiciones", "contrato"]
        .indexOf(paso);
}


function sincronizarPlazoTasa() {
    const personalizado =
        $("#tf_periodicidad_pago").val() === "PERSONALIZADO";

    $("#tfDiasBox").toggleClass("d-none", !personalizado);

    $("#tf_numero_dias")
        .prop("disabled", !personalizado)
        .prop("required", personalizado);

    if (!personalizado) {
        $("#tf_numero_dias").val("0");
    } else if (Number($("#tf_numero_dias").val()) <= 0) {
        $("#tf_numero_dias").val("7");
    }
}


function sincronizarGruposTasa() {
    const todos = $("#tfTodosGrupos").prop("checked");

    $("#tf_aplica_todos_grupos").val(todos ? "1" : "0");
    $("#tfGrupoBox").toggleClass("d-none", todos);

    $("#tf_grupo_cliente_id").prop("required", !todos);

    if (todos) {
        $("#tf_grupo_cliente_id").val("");
    }
}


function seleccionarPillTasa(contenedor, valor, campo) {
    $(contenedor + " button").removeClass("active");

    $(contenedor + " button").filter(function() {
        return $(this).attr("data-valor") === valor;
    }).addClass("active");

    $(campo).val(valor);
}


function sincronizarAmortizacionTasa() {
    const tipo = $("#tf_tipo_amortizacion").val();

    $(".tf-francesa").toggleClass("d-none", tipo !== "FRANCESA");
    $(".tf-decreciente").toggleClass("d-none", tipo !== "DECRECIENTE");

    const notas = {
        FLAT: "Cuota constante con capital e intereses distribuidos según el método Flat.",
        AMERICANA: "Se pagan intereses periódicos y el capital se liquida al final.",
        FRANCESA: "La cuota suele permanecer constante mientras cambian capital e intereses.",
        DECRECIENTE: "En el modelo estándar se amortiza capital constante y disminuyen las cuotas."
    };

    $("#tfNotaAmortizacion").text(notas[tipo] || "");
}


function generarNombreTasa() {
    const inferior = Number($("#tf_monto_limite_inferior").val() || 0);
    const superior = Number($("#tf_monto_limite_superior").val() || 0);
    const pagos = Number($("#tf_numero_pagos").val() || 1);
    const periodicidad = $("#tf_periodicidad_pago").val();
    const interes = Number($("#tf_tasa_interes").val() || 0);
    const base = $("#tf_base_tasa_interes").val();
    const amortizacion = $("#tf_tipo_amortizacion").val() || "FLAT";

    const periodos = {
        DIARIO: "días",
        SEMANAL: "semanas",
        CATORCENAL: "catorcenas",
        QUINCENAL: "quincenas",
        MENSUAL: "meses"
    };

    let plazo = "";

    if (periodicidad === "PERSONALIZADO") {
        const dias = $("#tf_numero_dias").val() || "0";

        plazo = pagos === 1
            ? "a " + dias + " días"
            : "en " + pagos + " pagos cada " + dias + " días";
    } else {
        plazo = "en " + pagos + " " + (periodos[periodicidad] || "pagos");
    }

    const textoInteres = interes > 0
        ? "al " + interes + "% de interés (" + (base || "sin base") + ")"
        : "sin intereses";

    const tipo = amortizacion.charAt(0) +
        amortizacion.slice(1).toLowerCase();

    const nombre = monedaMXN(inferior) +
        " a " + monedaMXN(superior) +
        " " + plazo +
        " " + textoInteres +
        " | " + tipo;

    $("#tf_nombre").val(nombre);
}


function llenarFormularioTasa(datos) {
    const valores = Object.assign({}, valoresInicialesTasa(), datos || {});

    Object.keys(valores).forEach(function(campo) {
        const elemento = $("#tfFormulario [name='" + campo + "']");

        if (elemento.length) {
            elemento.val(valores[campo] == null ? "" : String(valores[campo]));
        }
    });

    $("#tfTodosGrupos").prop(
        "checked",
        Number(valores.aplica_todos_grupos) === 1
    );

    sincronizarGruposTasa();
    sincronizarPlazoTasa();

    seleccionarPillTasa(
        "#tfMoratorioPills",
        valores.tipo_interes_moratorio,
        "#tf_tipo_interes_moratorio"
    );

    seleccionarPillTasa(
        "#tfAmortizacionPills",
        valores.tipo_amortizacion,
        "#tf_tipo_amortizacion"
    );

    sincronizarAmortizacionTasa();

    if (Number(valores.aplica_todos_grupos) === 0) {
        $("#tf_grupo_cliente_id").val(String(valores.grupo_cliente_id));
    }

    if (!datos || !datos.nombre) {
        generarNombreTasa();
    }
}


function getGruposTasas() {
    return $.ajax({
        type: "POST",
        url: "php/index.php",
        dataType: "json",
        data: {
            action: "getCatalogosClientes"
        }
    }).done(function(r) {
        if (!r || r.status !== "ok") {
            Swal.fire(
                "Aviso",
                r && r.answer || "No fue posible consultar los grupos.",
                "warning"
            );
            return;
        }

        stateTasas.grupos = r.results &&
            Array.isArray(r.results.grupos)
                ? r.results.grupos
                : [];

        const select = $("#tf_grupo_cliente_id").empty();

        select.append(
            $("<option>", {
                value: "",
                text: "Selecciona un grupo"
            })
        );

        stateTasas.grupos.forEach(function(grupo) {
            select.append(
                $("<option>")
                    .val(grupo.id)
                    .text(grupo.nombre)
            );
        });

        const pendiente = $("#tfFormulario").data("grupo-pendiente");

        if (pendiente !== undefined && pendiente !== null) {
            select.val(String(pendiente));
        }
    }).fail(function(xhr) {
        Swal.fire(
            "Error",
            xhr.responseJSON && xhr.responseJSON.answer ||
                "No fue posible cargar los grupos de clientes.",
            "error"
        );
    });
}


function getTasasFinanciamientoUI() {
    const peticion = ++stateTasas.peticion;

    $("#tfTarjetas").html(
        '<div class="text-center py-5">' +
            '<div class="spinner-border text-success"></div>' +
            '<p class="text-secondary mt-3">Consultando tasas...</p>' +
        '</div>'
    );

    $.ajax({
        type: "POST",
        url: "php/index.php",
        dataType: "json",
        data: {
            action: "getTasasFinanciamiento",
            buscar: stateTasas.buscar,
            pagina: stateTasas.pagina,
            filtro_activo: "TODOS"
        }
    }).done(function(r) {
        if (peticion !== stateTasas.peticion) {
            return;
        }

        if (!r || r.status !== "ok") {
            $("#tfTarjetas").empty();

            Swal.fire(
                "Aviso",
                r && r.answer || "No se pudieron consultar las tasas.",
                "warning"
            );
            return;
        }

        const resultados = r.results || {};

        stateTasas.tasas = Array.isArray(resultados.tasas)
            ? resultados.tasas
            : [];

        stateTasas.pagina = Number(resultados.pagina || 1);
        stateTasas.paginas = Number(resultados.paginas || 1);

        $("#tfResumen").text(
            Number(resultados.total || 0) + " tasas registradas"
        );

        renderTasasFinanciamiento();

    }).fail(function(xhr) {
        if (peticion !== stateTasas.peticion) {
            return;
        }

        $("#tfTarjetas").empty();

        Swal.fire(
            "Error",
            xhr.responseJSON && xhr.responseJSON.answer ||
                "No fue posible conectar con PHP.",
            "error"
        );
    });
}


function datoTarjetaTasa(etiqueta, valor) {
    return $("<div>")
        .addClass("tf-dato")
        .append(
            $("<small>").text(etiqueta),
            $("<strong>").text(valor)
        );
}


function bonoTarjetaTasa(etiqueta, valor, tipo) {
    const importe = tipo === "PORCENTAJE"
        ? Number(valor || 0) + "%"
        : monedaMXN(valor);

    return $("<div>")
        .addClass("tf-bono")
        .append(
            $("<strong>").text(importe),
            $("<small>").text(etiqueta)
        );
}


function renderTasasFinanciamiento() {
    const listado = $("#tfTarjetas").empty();

    if (!stateTasas.tasas.length) {
        const vacio =
            '<div class="tf-vacio">' +
                '<i class="bi bi-journal-plus"></i>' +
                '<h4>Sin tasas de financiamiento</h4>' +
                '<p>Crea tu primera tasa para establecer plazos e intereses.</p>' +
                '<button type="button" id="tfNuevaVacia" class="btn btn-success">' +
                    '<i class="bi bi-plus-circle me-2"></i>Nueva tasa' +
                '</button>' +
            '</div>';

        listado.html(vacio);
    }

    stateTasas.tasas.forEach(function(tasa) {
        const tarjeta = $("<article>").addClass("tf-tarjeta");
        const acciones = $("<aside>").addClass("tf-acciones");

        acciones.append(
            $("<small>").addClass("tf-acciones-titulo").text("BONOS")
        );

        acciones.append(
            bonoTarjetaTasa(
                "Apertura de crédito",
                tasa.bono_apertura,
                tasa.tipo_bono_apertura
            )
        );

        acciones.append(
            bonoTarjetaTasa(
                "Pago puntual",
                tasa.bono_pago_puntual,
                tasa.tipo_bono_puntual
            )
        );

        acciones.append(
            bonoTarjetaTasa(
                "Pago impuntual",
                tasa.bono_pago_normal,
                tasa.tipo_bono_pago_normal
            )
        );

        acciones.append(
            $("<small>").addClass("tf-acciones-titulo").text("ACCIONES")
        );

        [
            ["contrato", "file-earmark-text", "Ver contrato"],
            ["editar", "pencil-square", "Editar"],
            ["eliminar", "trash3", "Eliminar"]
        ].forEach(function(item) {
            const boton = $("<button>", {
                type: "button"
            });

            boton.addClass(
                "tf-accion " +
                (item[0] === "eliminar" ? "tf-peligro" : "")
            );

            boton.attr({
                "data-accion-tasa": item[0],
                "data-id": tasa.id
            });

            boton.append(
                $("<i>").addClass("bi bi-" + item[1]),
                $("<span>").text(item[2])
            );

            acciones.append(boton);
        });

        const informacion = $("<div>").addClass("tf-informacion");
        const etiquetas = $("<div>").addClass("tf-chips");

        etiquetas.append(
            $("<span>").addClass("tf-chip").text("TASA #" + tasa.id)
        );

        const grupo = stateTasas.grupos.find(function(g) {
            return String(g.id) === String(tasa.grupo_cliente_id);
        });

        etiquetas.append(
            $("<span>").addClass("tf-chip").text(
                Number(tasa.aplica_todos_grupos) === 1
                    ? "Todos los grupos"
                    : grupo
                        ? grupo.nombre
                        : "Grupo #" + tasa.grupo_cliente_id
            )
        );

        informacion.append(etiquetas);

        informacion.append(
            $("<h3>").text(tasa.nombre || "Sin nombre")
        );

        const datos = $("<div>").addClass("tf-datos");

        datos.append(
            datoTarjetaTasa(
                "Financiamiento",
                monedaMXN(tasa.monto_limite_inferior) +
                " — " +
                monedaMXN(tasa.monto_limite_superior)
            )
        );

        datos.append(
            datoTarjetaTasa(
                "Interés",
                Number(tasa.tasa_interes || 0) + "% " +
                (tasa.base_tasa_interes || "")
            )
        );

        const plazo = tasa.periodicidad_pago === "PERSONALIZADO"
            ? tasa.numero_pagos + " pagos cada " +
              tasa.numero_dias + " días"
            : tasa.numero_pagos + " pagos · " +
              tasa.periodicidad_pago;

        datos.append(
            datoTarjetaTasa("Plazo", plazo)
        );

        datos.append(
            datoTarjetaTasa(
                "Amortización",
                tasa.tipo_amortizacion || "FLAT"
            )
        );

        informacion.append(datos);

        const detalles = $("<div>").addClass("tf-detalles");

        [
            Number(tasa.requiere_aval)
                ? "Requiere aval"
                : "Sin aval",

            Number(tasa.requiere_beneficiario)
                ? "Requiere beneficiario"
                : "Sin beneficiario",

            "Mora amarilla: " +
                (tasa.semaforo_amarillo_mora || 0) +
                " pagos",

            "Comisión de apertura: " +
                (
                    tasa.tipo_comision_apertura === "PORCENTAJE"
                        ? Number(tasa.comision_apertura || 0) + "%"
                        : monedaMXN(tasa.comision_apertura)
                )
        ].forEach(function(texto) {
            detalles.append(
                $("<span>").addClass("tf-chip").text(texto)
            );
        });

        informacion.append(detalles);

        tarjeta.append(acciones, informacion);
        listado.append(tarjeta);
    });

    const paginas = $("#tfPaginacion").empty();

    if (stateTasas.paginas > 1) {
        paginas.append(
            $("<button>", {
                type: "button",
                id: "tfPaginaAnterior",
                text: "Anterior",
                class: "btn btn-outline-secondary btn-sm"
            }).prop("disabled", stateTasas.pagina <= 1)
        );

        paginas.append(
            $("<span>")
                .addClass("small text-secondary")
                .text(
                    "Página " +
                    stateTasas.pagina +
                    " de " +
                    stateTasas.paginas
                )
        );

        paginas.append(
            $("<button>", {
                type: "button",
                id: "tfPaginaSiguiente",
                text: "Siguiente",
                class: "btn btn-outline-secondary btn-sm"
            }).prop(
                "disabled",
                stateTasas.pagina >= stateTasas.paginas
            )
        );
    }
}


function mostrarListadoTasas() {
    $("#tfVistaFormulario").addClass("d-none");
    $("#tfVistaLista").removeClass("d-none");

    $("#tfBody").scrollTop(0);

    getTasasFinanciamientoUI();
}


function abrirFormularioTasa(id) {
    id = Number(id);

    if (!Number.isSafeInteger(id) || id < 0 || stateTasas.guardando) {
        return;
    }

    stateTasas.id = id;
    stateTasas.version = "";
    stateTasas.activo = 1;

    if (id === 0) {
        stateTasas.token = generarIdSolicitudVenta();
    } else {
        stateTasas.token = "";
    }

    $("#tfVistaLista").addClass("d-none");
    $("#tfVistaFormulario").removeClass("d-none");

    $("#tfTituloFormulario").text(
        id > 0
            ? "Editar tasa de financiamiento"
            : "Nueva tasa de financiamiento"
    );

    $("#tf_id").val(id);
    $("#tf_version").val("");
    $("#tf_solicitud_id").val(stateTasas.token);

    $("#tfFormulario").removeData("grupo-pendiente");

    llenarFormularioTasa();

    mostrarPasoTasa(0);

    $("#tfGuardar")
        .prop("disabled", id > 0)
        .text(id > 0 ? "Cargando tasa..." : "Crear tasa");

    if (id === 0) {
        return;
    }

    $.ajax({
        type: "POST",
        url: "php/index.php",
        dataType: "json",
        data: {
            action: "getTasasFinanciamiento",
            id: id
        }
    }).done(function(r) {
        if (
            stateTasas.id !== id ||
            $("#tfVistaFormulario").hasClass("d-none")
        ) {
            return;
        }

        if (
            !r ||
            r.status !== "ok" ||
            !r.results ||
            !r.results.tasa
        ) {
            Swal.fire(
                "Aviso",
                r && r.answer || "No se pudo consultar la tasa.",
                "warning"
            );

            mostrarListadoTasas();
            return;
        }

        const tasa = r.results.tasa;

        stateTasas.version = String(tasa.fecha_actualizacion);
        stateTasas.activo = Number(tasa.activo);

        llenarFormularioTasa(tasa);

        $("#tfFormulario").data(
            "grupo-pendiente",
            tasa.grupo_cliente_id
        );

        $("#tf_id").val(id);
        $("#tf_version").val(stateTasas.version);

        $("#tfGuardar")
            .prop("disabled", false)
            .text("Guardar cambios");

    }).fail(function(xhr) {
        Swal.fire(
            "Error",
            xhr.responseJSON && xhr.responseJSON.answer ||
                "No fue posible consultar la tasa.",
            "error"
        );

        mostrarListadoTasas();
    });
}


function errorFormularioTasa(texto, selector) {
    if (selector) {
        const elemento = $(selector);
        const seccion = elemento.closest(".tf-form-section")
            .attr("data-section");

        if (seccion) {
            mostrarPasoTasa(seccion);
        }

        elemento.addClass("is-invalid");
    }

    Swal.fire({
        icon: "warning",
        title: "Revisa la tasa",
        text: texto,
        confirmButtonColor: "#086b47"
    });

    return false;
}


function validarFormularioTasa() {
    sincronizarGruposTasa();
    sincronizarPlazoTasa();

    const inferior = numeroNotaValido(
        $("#tf_monto_limite_inferior").val(),
        0.01,
        99999999.99
    );

    const superior = numeroNotaValido(
        $("#tf_monto_limite_superior").val(),
        0.01,
        99999999.99
    );

    if (
        inferior === null ||
        superior === null ||
        superior < inferior
    ) {
        return errorFormularioTasa(
            "El rango de importes no es válido.",
            "#tf_monto_limite_inferior"
        );
    }

    const pagos = Number($("#tf_numero_pagos").val());
    const gracia = Number($("#tf_cuotas_gracia").val());
    const renovacion = Number($("#tf_renovacion_numero_pagos").val());

    if (!Number.isInteger(pagos) || pagos < 1 || pagos > 1000) {
        return errorFormularioTasa(
            "Revisa el número de pagos.",
            "#tf_numero_pagos"
        );
    }

    if (!Number.isInteger(gracia) || gracia < 0 || gracia >= pagos) {
        return errorFormularioTasa(
            "Las cuotas de gracia deben ser menores que los pagos.",
            "#tf_cuotas_gracia"
        );
    }

    if (
        !Number.isInteger(renovacion) ||
        renovacion < 0 ||
        renovacion > pagos
    ) {
        return errorFormularioTasa(
            "Revisa el número de pago para renovación.",
            "#tf_renovacion_numero_pagos"
        );
    }

    if (
        $("#tf_periodicidad_pago").val() === "PERSONALIZADO" &&
        (
            !Number.isInteger(Number($("#tf_numero_dias").val())) ||
            Number($("#tf_numero_dias").val()) < 1
        )
    ) {
        return errorFormularioTasa(
            "Indica cada cuántos días debe pagar el cliente.",
            "#tf_numero_dias"
        );
    }

    if (
        Number($("#tf_tasa_interes").val()) > 0 &&
        !$("#tf_base_tasa_interes").val()
    ) {
        return errorFormularioTasa(
            "Selecciona la base del interés ordinario.",
            "#tf_base_tasa_interes"
        );
    }

    if (
        Number($("#tf_tasa_interes_moratorio").val()) > 0 &&
        !$("#tf_base_tasa_moratoria").val()
    ) {
        return errorFormularioTasa(
            "Selecciona la base del interés moratorio.",
            "#tf_base_tasa_moratoria"
        );
    }

    if (
        !$("#tfTodosGrupos").prop("checked") &&
        !$("#tf_grupo_cliente_id").val()
    ) {
        return errorFormularioTasa(
            "Selecciona un grupo de clientes.",
            "#tf_grupo_cliente_id"
        );
    }

    if (
        Number($("#tf_ciclos_limite_inferior").val()) >
        Number($("#tf_ciclos_limite_superior").val())
    ) {
        return errorFormularioTasa(
            "El ciclo mínimo no puede superar al máximo.",
            "#tf_ciclos_limite_inferior"
        );
    }

    if (
        $("#tf_vigente_desde").val() &&
        $("#tf_vigente_hasta").val() &&
        $("#tf_vigente_desde").val() > $("#tf_vigente_hasta").val()
    ) {
        return errorFormularioTasa(
            "Revisa las fechas de vigencia.",
            "#tf_vigente_hasta"
        );
    }

    const invalido = $("#tfFormulario").find(":invalid").first();

    if (invalido.length) {
        return errorFormularioTasa(
            "Completa los campos obligatorios y revisa los valores.",
            invalido
        );
    }

    return true;
}


function guardarTasaFinanciamientoUI() {
    if (stateTasas.guardando) {
        return;
    }

    if (stateTasas.id === 0) {
        generarNombreTasa();
    }

    if (!validarFormularioTasa()) {
        return;
    }

    const datos = {
        action: "guardarTasaFinanciamiento"
    };

    $("#tfFormulario").serializeArray().forEach(function(campo) {
        datos[campo.name] = campo.value;
    });

    datos.id = stateTasas.id;
    datos.version = stateTasas.version;
    datos.solicitud_id = stateTasas.token;
    datos.activo = stateTasas.activo;

    // Cuando los días están ocultos, ese input está deshabilitado.
    // Se envía cero de manera explícita.
    if (datos.periodicidad_pago !== "PERSONALIZADO") {
        datos.numero_dias = 0;
    }

    stateTasas.guardando = true;

    $("#tfGuardar")
        .prop("disabled", true)
        .text("Guardando...");

    $.ajax({
        type: "POST",
        url: "php/index.php",
        dataType: "json",
        data: datos
    }).done(function(r) {
        if (!r || r.status !== "ok") {
            Swal.fire(
                "Aviso",
                r && r.answer || "No fue posible guardar la tasa.",
                "warning"
            );
            return;
        }

        stateTasas.token = "";

        mostrarListadoTasas();

        Swal.fire({
            icon: "success",
            title: stateTasas.id > 0
                ? "Tasa actualizada"
                : "Tasa creada",
            text: r.answer || "La información se guardó correctamente.",
            confirmButtonColor: "#086b47"
        });

    }).fail(function(xhr) {
        const respuesta = xhr.responseJSON || {};

        Swal.fire({
            icon: respuesta.status === "warning"
                ? "warning"
                : "error",
            title: "No se pudo confirmar el guardado",
            text: (respuesta.answer ||
                "No fue posible completar la operación.") +
                " Conserva este formulario antes de reintentar.",
            confirmButtonColor: "#086b47"
        });

    }).always(function() {
        stateTasas.guardando = false;

        $("#tfGuardar")
            .prop("disabled", false)
            .text(
                stateTasas.id > 0
                    ? "Guardar cambios"
                    : "Crear tasa"
            );
    });
}


function verContratoTasa(id) {
    $.ajax({
        type: "POST",
        url: "php/index.php",
        dataType: "json",
        data: {
            action: "getTasasFinanciamiento",
            id: id
        }
    }).done(function(r) {
        if (!r || r.status !== "ok" || !r.results || !r.results.tasa) {
            Swal.fire(
                "Aviso",
                r && r.answer || "No se pudo consultar el contrato.",
                "warning"
            );
            return;
        }

        const tasa = r.results.tasa;

        const contenido = $("<div>").addClass("text-start");

        contenido.append(
            $("<h5>").text(
                tasa.titulo_contrato || "Contrato de financiamiento"
            )
        );

        contenido.append(
            $("<pre>")
                .addClass("tf-texto-contrato")
                .text(
                    tasa.contrato_perfil ||
                    "Esta tasa no tiene contrato configurado."
                )
        );

        Swal.fire({
            title: "Contrato de financiamiento",
            html: contenido[0],
            width: "850px",
            confirmButtonColor: "#086b47"
        });

    }).fail(function(xhr) {
        Swal.fire(
            "Error",
            xhr.responseJSON && xhr.responseJSON.answer ||
                "No fue posible consultar el contrato.",
            "error"
        );
    });
}


function eliminarTasaFinanciamientoUI(id) {
    const tasa = stateTasas.tasas.find(function(item) {
        return Number(item.id) === Number(id);
    });

    Swal.fire({
        title: "¿Eliminar esta tasa?",
        text: (tasa ? tasa.nombre + ". " : "") +
            "Se conservará en la base de datos, pero dejará de aparecer en el catálogo.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#b42318"
    }).then(function(confirmacion) {
        if (!confirmacion.isConfirmed) {
            return;
        }

        $.ajax({
            type: "POST",
            url: "php/index.php",
            dataType: "json",
            data: {
                action: "eliminarTasaFinanciamiento",
                id: id
            }
        }).done(function(r) {
            if (!r || r.status !== "ok") {
                Swal.fire(
                    "Aviso",
                    r && r.answer || "No se pudo eliminar la tasa.",
                    "warning"
                );
                return;
            }

            getTasasFinanciamientoUI();

            Swal.fire({
                icon: "success",
                title: "Tasa eliminada",
                text: "Se marcó eliminado = 1. El registro se conserva.",
                confirmButtonColor: "#086b47"
            });

        }).fail(function(xhr) {
            Swal.fire(
                "Error",
                xhr.responseJSON && xhr.responseJSON.answer ||
                    "No se pudo confirmar la eliminación.",
                "error"
            );
        });
    });
}


function bindTasasFinanciamiento() {
    $(document).on("click", "#btnTasasFinanciamiento", function() {
        if (!window.CSM_ES_ADMIN) {
            return;
        }

        construirModalTasas();

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById("tfModal")
        ).show();

        getGruposTasas().always(function() {
            getTasasFinanciamientoUI();
        });
    });

    $(document).on("input", "#tfBuscar", function() {
        stateTasas.buscar = $(this).val().trim();

        clearTimeout(stateTasas.timer);

        stateTasas.timer = setTimeout(function() {
            stateTasas.pagina = 1;
            getTasasFinanciamientoUI();
        }, 300);
    });

    $(document).on("click", "#tfNueva, #tfNuevaVacia", function() {
        abrirFormularioTasa(0);
    });

    $(document).on("click", "#tfVolver", function() {
        mostrarListadoTasas();
    });

    $(document).on("click", ".tf-paso", function() {
        mostrarPasoTasa($(this).attr("data-paso"));
    });

    $(document).on("click", "#tfAnterior", function() {
        mostrarPasoTasa(indicePasoTasa() - 1);
    });

    $(document).on("click", "#tfSiguiente", function() {
        mostrarPasoTasa(indicePasoTasa() + 1);
    });

    $(document).on("change", "#tfTodosGrupos", function() {
        sincronizarGruposTasa();
    });

    $(document).on("change", "#tf_periodicidad_pago", function() {
        sincronizarPlazoTasa();
        generarNombreTasa();
    });

    $(document).on("click", "#tfMoratorioPills button", function() {
        seleccionarPillTasa(
            "#tfMoratorioPills",
            $(this).attr("data-valor"),
            "#tf_tipo_interes_moratorio"
        );
    });

    $(document).on("click", "#tfAmortizacionPills button", function() {
        seleccionarPillTasa(
            "#tfAmortizacionPills",
            $(this).attr("data-valor"),
            "#tf_tipo_amortizacion"
        );

        sincronizarAmortizacionTasa();
        generarNombreTasa();
    });

    $(document).on(
        "input change",
        "#tf_monto_limite_inferior, " +
        "#tf_monto_limite_superior, " +
        "#tf_numero_pagos, " +
        "#tf_numero_dias, " +
        "#tf_tasa_interes, " +
        "#tf_base_tasa_interes",
        function() {
            generarNombreTasa();
        }
    );

    $(document).on(
        "input change",
        "#tfFormulario input, #tfFormulario select",
        function() {
            $(this).removeClass("is-invalid");
        }
    );

    $(document).on("change", "#tfVariableContrato", function() {
        const valor = $(this).val();

        if (!valor) {
            return;
        }

        const textarea = $("#tf_contrato_perfil")[0];
        const inicio = textarea.selectionStart || 0;
        const final = textarea.selectionEnd || inicio;

        textarea.value =
            textarea.value.slice(0, inicio) +
            valor +
            textarea.value.slice(final);

        textarea.focus();
        textarea.setSelectionRange(
            inicio + valor.length,
            inicio + valor.length
        );

        $(this).val("");
    });

    $(document).on("submit", "#tfFormulario", function(e) {
        e.preventDefault();

        guardarTasaFinanciamientoUI();
    });

    $(document).on("click", "#tfPaginaAnterior", function() {
        if (stateTasas.pagina > 1) {
            stateTasas.pagina--;
            getTasasFinanciamientoUI();
        }
    });

    $(document).on("click", "#tfPaginaSiguiente", function() {
        if (stateTasas.pagina < stateTasas.paginas) {
            stateTasas.pagina++;
            getTasasFinanciamientoUI();
        }
    });

    $(document).on("click", "[data-accion-tasa]", function() {
        const id = Number($(this).attr("data-id"));
        const accion = $(this).attr("data-accion-tasa");

        if (!Number.isSafeInteger(id) || id <= 0) {
            return;
        }

        if (accion === "editar") {
            abrirFormularioTasa(id);
        }

        if (accion === "contrato") {
            verContratoTasa(id);
        }

        if (accion === "eliminar") {
            eliminarTasaFinanciamientoUI(id);
        }
    });
}


$(document).ready(function() {
    bindTasasFinanciamiento();
});
