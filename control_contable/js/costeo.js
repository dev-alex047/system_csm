
/* COSTEOS Y COTIZACIONES · Bootstrap 5.3.3 + jQuery */

const vehiculares = [
    ["Motocicleta", "Motocicleta"],
    ["VL", "VL · Vehículo ligero (2×2)"],
    ["C2", "C2 · Camión unitario (2×4)"],
    ["C3", "C3 · Camión unitario (2×6/8)"],
    ["C2R2", "C2R2 · Camión-remolque (6+8)"],
    ["C3R2", "C3R2 · Camión-remolque (10+8)"],
    ["C2R3", "C2R3 · Camión-remolque (6+12)"],
    ["C3R3", "C3R3 · Camión-remolque (10+12)"],
    ["T2S1", "T2S1 · Tractocamión (6+4)"],
    ["T2S2", "T2S2 · Tractocamión (6+8)"],
    ["T2S3", "T2S3 · Tractocamión (6+12)"],
    ["T3S1", "T3S1 · Tractocamión (10+4)"],
    ["T3S2", "T3S2 · Tractocamión (10+8)"],
    ["T3S3", "T3S3 · Tractocamión (10+12)"],
    ["T2S1R2", "T2S1R2 (6+4+8)"],
    ["T2S2R2", "T2S2R2 (6+8+8)"],
    ["T2S1R3", "T2S1R3 (6+4+12)"],
    ["T3S1R2", "T3S1R2 (10+4+8)"],
    ["T3S1R3", "T3S1R3 (10+4+12)"],
    ["T3S2R2", "T3S2R2 (10+8+8)"],
    ["T3S2R3", "T3S2R3 (10+8+12)"],
    ["T3S2R4", "T3S2R4 (10+8+16)"],
    ["T2S2S2", "T2S2S2 (6+8+8)"],
    ["T3S2S2", "T3S2S2 (10+8+8)"],
    ["T3S3S2", "T3S3S2 (10+12+8)"],
    ["OTROEVGP", "Especial: carga voluminosa / gran peso"],
    ["OTROSG", "Servicio de grúas"],
    ["GPLUTA", "Grúa pluma tipo A"],
    ["GPLUTB", "Grúa pluma tipo B"],
    ["GPLUTC", "Grúa pluma tipo C"],
    ["GPLUTD", "Grúa pluma tipo D"],
    ["GPLATA", "Grúa plataforma tipo A"],
    ["GPLATB", "Grúa plataforma tipo B"],
    ["GPLATC", "Grúa plataforma tipo C"],
    ["GPLATD", "Grúa plataforma tipo D"]
];

const opcionesUnidad = ["M3", "TON", "KG", "PZA", "BULTO", "VIAJE"];

const campo = (name, label, type = "text", required = true, extra = {}) => ({name, label, type, required, ...extra});
const select = (name, label, options, required = true, extra = {}) => campo(name, label, "select", required, {options, ...extra});
const numero = (name, label, step = "0.01", required = true, extra = {}) => campo(name, label, "number", required, {step, min: "0", ...extra});

const esquemas = {
    material: {
        titulo: "Materiales",
        coleccion: "materiales",
        columnas: [
            ["codigo", "Código"],
            ["nombre", "Material"],
            ["unidad", "Unidad"],
            ["costo_unitario", "Costo unitario"]
        ],
        campos: [
            campo("codigo", "Código", "text", true, {max: 50}),
            campo("nombre", "Nombre del material", "text", true, {max: 150}),
            select("unidad", "Unidad", opcionesUnidad),
            numero("costo_unitario", "Costo de adquisición por unidad ($)", "0.0001")
        ]
    },
    vehiculo: {
        titulo: "Vehículos",
        coleccion: "vehiculos",
        columnas: [
            ["nombre", "Vehículo"],
            ["tipo", "Propiedad"],
            ["capacidad", "Capacidad"],
            ["unidad_capacidad", "Unidad"],
            ["vin", "VIN"],
            ["configuracion_vehicular", "Configuración"],
            ["combustible", "Combustible"]
        ],
        campos: [
            campo("nombre", "Nombre identificador", "text", true, {max: 120}),
            select("tipo_vehiculo", "Modalidad", [["PROPIO", "Propio"], ["CONTRATADO", "Contratado"]]),
            select("unidad_capacidad", "Unidad de capacidad", opcionesUnidad),
            numero("capacidad", "Capacidad por viaje", "0.01", true, {min: "0.01"}),
            numero("rendimiento_km_litro", "Rendimiento (km/L)", "0.01"),
            numero("costo_fijo_viaje", "Costo fijo por viaje ($)"),
            campo("vin", "VIN (opcional, 17 caracteres)", "text", false, {
                max: 17,
                pattern: "[A-HJ-NPR-Za-hj-npr-z0-9]{17}",
                wide: true
            }),
            select("tipo_transporte", "Tipo de transporte", [
                ["TERRESTRE", "Terrestre"],
                ["MARITIMO", "Marítimo"],
                ["AEREO", "Aéreo"]
            ]),
            select("configuracion_vehicular", "Configuración vehicular", vehiculares, false, {wide: true}),
            numero("peso_bruto_kg", "Peso bruto (kg)", "0.01", false, {min: "0.01"}),
            numero("carga_util_kg", "Carga útil (kg)", "0.01", false, {min: "0.01"}),
            numero("tanque_litros", "Capacidad del tanque (L)", "0.01", false, {min: "0.01"}),
            select("combustible", "Combustible", [
                ["DIESEL", "Diésel"],
                ["GASOLINA", "Gasolina"],
                ["GAS_LP", "Gas LP"],
                ["ELECTRICO", "Eléctrico"],
                ["OTRO", "Otro"]
            ], false)
        ]
    },
    destino: {
    titulo: "Destinos",
    coleccion: "destinos",
    columnas: [
        ["nombre", "Destino"],
        ["municipio", "Municipio"],
        ["distancia_ida_km", "Distancia de ida (km)"],
        ["casetas_por_viaje", "Casetas por viaje ($)"]
    ],
    campos: [
        campo("nombre", "Destino o comunidad", "text", true, {max: 150, wide: true}),
        numero("distancia_ida_km", "Distancia de ida (km)"),
        numero("casetas_por_viaje", "Casetas por viaje ($)"),

        campo("calle", "Calle o vialidad", "text", false, {max: 150, wide: true}),
        campo("numero_exterior", "Número exterior", "text", false, {max: 20}),
        campo("numero_interior", "Número interior", "text", false, {max: 20}),
        campo("colonia", "Colonia o localidad", "text", false, {max: 120}),
        campo("municipio", "Municipio", "text", false, {max: 120}),
        campo("estado", "Estado", "text", false, {max: 100}),
        campo("codigo_postal", "Código postal", "text", false, {max: 10}),
        campo("referencias", "Referencias de entrega", "text", false, {max: 255, wide: true}),

        campo("latitud", "Latitud", "number", false, {
            step: "any", min: "-90", max: "90"
        }),
        campo("longitud", "Longitud", "number", false, {
            step: "any", min: "-180", max: "180"
        })
    ]
},
    lista: {
        titulo: "Listas de precios",
        coleccion: "listas",
        columnas: [["nombre", "Nombre de la lista"]],
        campos: [campo("nombre", "Nombre de lista", "text", true, {max: 100, wide: true})]
    },
    tarifa: {
        titulo: "Tarifas por material",
        coleccion: "tarifas",
        columnas: [
            ["material_nombre", "Material"],
            ["lista_nombre", "Lista"],
            ["recargo_pct", "Incremento sobre costo (%)"]
        ],
        campos: [
            select("material_id", "Material", []),
            select("lista_id", "Lista de precios", []),
            numero("recargo_pct", "Incremento (%)", "0.01", true, {max: "200"})
        ]
    }
};

const datos = {
    materiales: [],
    vehiculos: [],
    destinos: [],
    listas: [],
    tarifas: []
};

let tipoActual = "material";
let cotizacionVigente = null;
let consultaActual = 0;

const CLAVE_CARTO = "cb1_5cab_1_ef215a02384cb8ac2be62fe5";
const CLAVE_LOCATIONIQ = "pk.131467ff150f4f89b6d436411a5f8052";
const CENTRO_MAPA = [17.27, -97.68];

let temporizadorLugar = null;
let solicitudLugar = null;
let folioLugar = 0;
let mapaDestinoFormulario = null;
let marcadorDestinoFormulario = null;
let mapaDestinoLectura = null;

const mxn = valor => new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN"
}).format(Number(valor) || 0);

const api = parametros => $.ajax({
    url: "php/index.php",
    method: "POST",
    dataType: "json",
    data: parametros,
    cache: false
});

const errorApi = xhr => xhr.responseJSON?.answer ||
    "No fue posible completar la operación. Verifica la conexión.";

const activos = clave => (datos[clave] || []).filter(registro =>
    Number(registro.eliminado || 0) === 0 && Number(registro.activo) === 1
);

function consultarCatalogos(alTerminar) {
    const folioConsulta = ++consultaActual;

   api({action: "getCatalogosCosteo"}).done(function(r) {
        if (folioConsulta !== consultaActual) return;

        if (r.status !== "ok") {
            return Swal.fire("No disponible", r.answer || "No se pudieron consultar los catálogos.", "warning");
        }

        Object.keys(datos).forEach(function(clave) {
            datos[clave] = Array.isArray(r.results?.[clave]) ? r.results[clave] : [];
        });

        if (typeof alTerminar === "function") alTerminar();
    }).fail(function(xhr) {
        if (folioConsulta === consultaActual) {
            Swal.fire("Error", errorApi(xhr), "error");
        }
    });
}

function coordenadasDestino(registro) {
    const latitud = String(registro?.latitud ?? "").trim();
    const longitud = String(registro?.longitud ?? "").trim();

    if (!latitud || !longitud) return null;

    const lat = Number(latitud);
    const lng = Number(longitud);

    if (!Number.isFinite(lat) || !Number.isFinite(lng) ||
        lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        return null;
    }

    return [lat, lng];
}

function crearMapaCosteo(elemento, centro, zoom = 14) {
    if (!window.L) {
        throw new Error("No se cargó Leaflet.");
    }

    if (!CLAVE_CARTO.trim() || CLAVE_CARTO === "") {
        throw new Error("Primero configura tu clave de CARTO.");
    }

    const mapa = L.map(elemento, {
        zoomControl: false,
        scrollWheelZoom: false,
        preferCanvas: true
    }).setView(centro, zoom);

    const url = "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png?key=" +
        encodeURIComponent(CLAVE_CARTO.trim());

    L.tileLayer(url, {
        subdomains: "abcd",
        minZoom: 3,
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors, ' +
            '&copy; <a href="https://carto.com/attribution/" target="_blank">CARTO</a>'
    }).addTo(mapa);

    L.control.zoom({
        position: "bottomright"
    }).addTo(mapa);

    requestAnimationFrame(function() {
        mapa.invalidateSize();
    });

    return mapa;
}

function iconoDestinoCosteo() {
    return L.divIcon({
        className: "cc-icono-destino",
        html: '<div class="cc-pin-destino"><i class="bi bi-geo-alt-fill"></i></div>',
        iconSize: [42, 48],
        iconAnchor: [21, 47],
        popupAnchor: [0, -43]
    });
}

function colocarPinDestino(lat, lng, centrar = true) {
    if (!mapaDestinoFormulario || !Number.isFinite(lat) || !Number.isFinite(lng) ||
        lat < -90 || lat > 90 || lng < -180 || lng > 180) return;

    $("#ccf_latitud").val(lat.toFixed(7));
    $("#ccf_longitud").val(lng.toFixed(7));

    if (!marcadorDestinoFormulario) {
        marcadorDestinoFormulario = L.marker([lat, lng], {
            draggable: true,
            autoPan: true,
            icon: iconoDestinoCosteo()
        }).addTo(mapaDestinoFormulario);

        marcadorDestinoFormulario.on("dragend", function() {
            const posicion = this.getLatLng();
            colocarPinDestino(posicion.lat, posicion.lng, false);
        });
    } else {
        marcadorDestinoFormulario.setLatLng([lat, lng]);
    }

    if (centrar) {
        mapaDestinoFormulario.setView(
            [lat, lng],
            Math.max(mapaDestinoFormulario.getZoom(), 14)
        );
    }
}

function iniciarMapaDestino() {
    const actual = coordenadasDestino({
        latitud: $("#ccf_latitud").val(),
        longitud: $("#ccf_longitud").val()
    });

    try {
        mapaDestinoFormulario = crearMapaCosteo(
            "ccMapaDestino",
            actual || CENTRO_MAPA,
            actual ? 15 : 10
        );

        mapaDestinoFormulario.on("click", function(evento) {
            colocarPinDestino(evento.latlng.lat, evento.latlng.lng, false);
        });

        if (actual) {
            colocarPinDestino(actual[0], actual[1], false);
        }
    } catch (error) {
        Swal.fire("No se pudo abrir el mapa", error.message, "error");
    }
}

function renderAdminCosteo() {
    if (!window.CSM_ES_ADMIN) return;

    const existente = document.getElementById("ccModalCatalogos");

    if (existente) {
        bootstrap.Modal.getOrCreateInstance(existente).show();
        consultarCatalogos(mostrarTablaCosteo);
        return;
    }

    $("body").append(`
        <div class="modal fade" id="ccModalCatalogos" tabindex="-1"
             aria-labelledby="ccTituloModalCatalogos" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-bold mb-1" id="ccTituloModalCatalogos">
                                Administración de catálogos
                            </h2>
                            <p class="small text-secondary mb-0">
                                Configura materiales, vehículos, destinos y tarifas de venta.
                            </p>
                        </div>
                        <button type="button" class="btn-close"
                                data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" id="ccAdminContenido"></div>
                </div>
            </div>
        </div>
    `);

    $("#ccModalCatalogos").on("hide.bs.modal", function() {
    const enfocado = document.activeElement;

    if (enfocado && this.contains(enfocado)) {
        enfocado.blur();
    }
});

    $("#ccAdminContenido").html(`
        <section class="nota-bloque mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h3 class="h6 fw-bold mb-1">Catálogos de costeo</h3>
                    <p class="small text-secondary mb-0">
                        Los cambios actualizan las opciones para nuevas cotizaciones.
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2" id="ccPestanas" aria-label="Catálogos"></div>
        </section>
        <section class="nota-bloque mb-0">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3">
                <div>
                    <h3 class="h6 fw-bold mb-1" id="ccTitulo"></h3>
                    <p class="small text-secondary mb-0" id="ccConteo"></p>
                </div>
                <button type="button" class="btn btn-success" id="ccNuevo">
                    <i class="bi bi-plus-lg me-1"></i>Agregar registro
                </button>
            </div>
            <div class="row g-2 align-items-center mb-3">
                <div class="col-lg-8">
                    <label for="ccBuscar" class="visually-hidden">Buscar registros</label>
                    <input id="ccBuscar" class="form-control"
                           placeholder="Buscar por nombre, código o identificación..." autocomplete="off">
                </div>
                
            </div>
            <div class="table-responsive cc-tabla-contenedor">
                <table class="table table-hover align-middle mb-0" id="ccTabla">
                    <thead></thead><tbody></tbody>
                </table>
            </div>
        </section>
    `);

    Object.keys(esquemas).forEach(function(tipo) {
        $("<button>", {
            type: "button",
            class: "btn btn-sm btn-outline-success cc-tab",
            text: esquemas[tipo].titulo
        }).attr("data-tipo", tipo).appendTo("#ccPestanas");
    });

    elegirCatalogo(tipoActual);
    consultarCatalogos(mostrarTablaCosteo);

    $("#ccModalCatalogos").on("hidden.bs.modal", function() {
        if ($("#ccCotizador").length) actualizarOpcionesCotizador();
    });

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById("ccModalCatalogos")
    ).show();
}

function elegirCatalogo(tipo) {
    if (!esquemas[tipo]) return;

    tipoActual = tipo;

    $(".cc-tab").removeClass("active btn-success text-white").addClass("btn-outline-success");
    $(".cc-tab").filter(function(_, boton) {
        return boton.dataset.tipo === tipo;
    }).addClass("active btn-success text-white").removeClass("btn-outline-success");

    $("#ccBuscar").val("");
    mostrarTablaCosteo();
}

function mostrarTablaCosteo() {
    if (!$("#ccTabla").length) return;

    const config = esquemas[tipoActual];
    const buscar = $("#ccBuscar").val().toLocaleLowerCase("es-MX").trim();
    const filas = datos[config.coleccion] || [];

    const visibles = filas.map((registro, indice) => ({registro, indice})).filter(function(item) {
    return Number(item.registro.eliminado || 0) === 0 &&
        (!buscar || Object.values(item.registro).some(valor =>
            String(valor ?? "").toLocaleLowerCase("es-MX").includes(buscar)
        ));
});
    $("#ccTitulo").text(config.titulo);
    $("#ccConteo").text(`${visibles.length} registros disponibles`);

    const $thead = $("#ccTabla thead").empty();
    const $trh = $("<tr>");

    config.columnas.forEach(function(columna) {
        $("<th>").text(columna[1]).appendTo($trh);
    });

    $("<th>", {text: "Acciones"}).appendTo($trh);
    $thead.append($trh);

    const $body = $("#ccTabla tbody").empty();

    visibles.forEach(function(item) {
        const registro = item.registro;
        const indice = item.indice;
        const $tr = $("<tr>");

        config.columnas.forEach(function(columna) {
            const clave = columna[0];
            let valor = registro[clave] ?? "—";

            if (["costo_unitario", "casetas_por_viaje"].includes(clave) && valor !== "—") {
                valor = mxn(valor);
            }

            if (clave === "capacidad") {
                valor = `${registro.capacidad} ${registro.unidad_capacidad}`;
            }

            $("<td>", {text: String(valor)}).appendTo($tr);
        });

        const $acciones = $("<td>", {class: "text-nowrap"});

$("<button>", {
    type: "button",
    class: "btn btn-sm btn-outline-success me-2 cc-editar",
    html: '<i class="bi bi-pencil-square"></i>',
    title: "Editar",
    "aria-label": "Editar registro"
}).attr("data-indice", indice).appendTo($acciones);

if (tipoActual === "destino") {
    $("<button>", {
        type: "button",
        class: "btn btn-sm btn-outline-primary me-2 cc-ver-mapa",
        html: '<i class="bi bi-geo-alt"></i>',
        title: "Ver ubicación",
        "aria-label": "Ver ubicación en el mapa"
    }).attr("data-indice", indice).appendTo($acciones);
}

$("<button>", {
    type: "button",
    class: "btn btn-sm btn-outline-danger cc-estado",
    html: '<i class="bi bi-trash"></i>',
    title: "Eliminar",
    "aria-label": "Eliminar registro"
}).attr({
    "data-indice": indice,
    "data-operacion": "eliminar"
}).appendTo($acciones);

        $tr.append($acciones).appendTo($body);
    });

    if (!visibles.length) {
        $body.append($("<tr>").append($("<td>", {
            colspan: config.columnas.length + 1,
            class: "text-center text-secondary py-4",
            text: "No hay registros que coincidan."
        })));
    }
}

function opcionesCampo(field, $control, actual) {
    let opciones = field.options || [];

    if (field.name === "material_id") {
        opciones = activos("materiales").map(r => [String(r.id), `${r.codigo} · ${r.nombre}`]);
    }

    if (field.name === "lista_id") {
        opciones = activos("listas").map(r => [String(r.id), r.nombre]);
    }

    $control.append(new Option(field.required ? "Selecciona una opción" : "Sin especificar", ""));

    opciones.forEach(function(opcion) {
        $control.append(new Option(
            Array.isArray(opcion) ? opcion[1] : opcion,
            Array.isArray(opcion) ? opcion[0] : opcion
        ));
    });

    if (actual !== undefined && actual !== null && actual !== "") {
        if (!$control.find("option").filter((_, opcion) => opcion.value === String(actual)).length) {
            $control.append(new Option(String(actual) + " (no disponible)", String(actual)));
        }

        $control.val(String(actual));
    }
}

function abrirFormularioCosteo(indice = null) {
    const config = esquemas[tipoActual];
    const registro = indice === null ? null : datos[config.coleccion]?.[indice];

    if (indice !== null && !registro) return;

    const anterior = document.getElementById("ccModal");

if (anterior) return;

    $("body").append(`
        <div class="modal fade" id="ccModal" tabindex="-1"
             aria-labelledby="ccModalTitulo" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-bold mb-1" id="ccModalTitulo"></h2>
                            <p class="small text-secondary mb-0">Los campos marcados con * son obligatorios.</p>
                        </div>
                        <button type="button" class="btn-close"
                                data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form id="ccFormulario" class="cc-formulario-modal" novalidate>
                        <div class="modal-body">
                            <div class="row g-3" id="ccCampos"></div>
                            <p class="small text-secondary mt-3 mb-0" id="ccAyuda"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                    data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success" id="ccGuardar">
                                <i class="bi bi-check2 me-2"></i>Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `);

    $("#ccModalTitulo").text(
    (registro ? "Editar " : "Agregar ") +
    (tipoActual === "vehiculo" ? "vehículo" : config.titulo.toLocaleLowerCase("es-MX"))
);

    const $campos = $("#ccCampos");

    config.campos.forEach(function(field) {
        const $grupo = $("<div>", {
            class: field.wide ? "col-12" : "col-md-6"
        });

        const $label = $("<label>", {
            class: "form-label fw-semibold",
            for: "ccf_" + field.name,
            text: field.label + (field.required ? " *" : "")
        });

        const $control = field.type === "select"
            ? $("<select>", {class: "form-select"})
            : $("<input>", {class: "form-control", type: field.type});

        $control.attr({
            id: "ccf_" + field.name,
            name: field.name
        });

        if (field.required) $control.prop("required", true);
        if (field.max) $control.attr(field.type === "text" ? "maxlength" : "max", field.max);
        if (field.min) $control.attr("min", field.min);
        if (field.step) $control.attr("step", field.step);
        if (field.pattern) $control.attr("pattern", field.pattern);

        let actual = registro?.[field.name] ?? "";

        if (field.name === "tipo_vehiculo") actual = registro?.tipo ?? "PROPIO";
        if (field.name === "tipo_transporte" && !actual) actual = "TERRESTRE";
        if (field.name === "combustible" && !actual && !registro) actual = "DIESEL";
        if (field.name === "recargo_pct") actual = registro?.recargo_pct ?? "0.00";

        if (field.type === "select") {
            opcionesCampo(field, $control, actual);
        } else {
            $control.val(actual);
        }

        if (registro && tipoActual === "tarifa" && ["material_id", "lista_id"].includes(field.name)) {
            $control.prop("disabled", true);
        }

        $grupo.append($label, $control).appendTo($campos);
    });

    if (tipoActual === "destino") {
    $("#ccCampos").append(`
        <div class="col-12">
            <div class="cc-mapa-seccion">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">
                            <i class="bi bi-geo-alt text-success me-2"></i>Ubicación en el mapa
                        </h3>
                        <p class="small text-secondary mb-0">
                            Pulsa el mapa o arrastra el marcador hasta el punto de entrega.
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline-success btn-sm"
                            id="ccUsarGPS">
                        <i class="bi bi-crosshair me-1"></i>Usar mi ubicación
                    </button>
                </div>

                <div class="cc-zona-mapa">
    <div class="cc-buscador-flotante" id="ccBuscadorLugar">
        <div class="cc-buscador-control">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="text"
                   id="ccBuscarLugar"
                   class="cc-buscador-input"
                   placeholder="Buscar negocio, comunidad o dirección..."
                   aria-label="Buscar un lugar"
                   aria-autocomplete="list"
                   aria-controls="ccSugerenciasLugar"
                   aria-expanded="false"
                   autocomplete="off"
                   spellcheck="false">
        </div>

        <div id="ccSugerenciasLugar"
             class="cc-sugerencias-lugar d-none"
             role="listbox"
             aria-label="Lugares sugeridos"></div>
    </div>

    <div id="ccMapaDestino" class="cc-mapa"></div>
</div>

<div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
    <small class="text-secondary">
        <i class="bi bi-hand-index me-1"></i>
        También puedes seleccionar un punto directamente en el mapa.
    </small>

    <small>
        <a href="https://locationiq.com"
           target="_blank"
           rel="noopener noreferrer"
           class="cc-credito-busqueda">
            Search by LocationIQ.com
        </a>
    </small>
</div>

                <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
                    <small class="text-secondary">
                        Las coordenadas se actualizan al mover el marcador.
                    </small>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            id="ccQuitarPinDestino">
                        <i class="bi bi-x-circle me-1"></i>Quitar ubicación
                    </button>
                </div>
            </div>
        </div>
    `);

    
    //$("#ccAyuda").text("La dirección es opcional. La distancia de ida debe corresponder a la ruta real de entrega.");
}

    if (tipoActual === "vehiculo") {
        //$("#ccAyuda").text("El VIN es opcional. Actualmente, el cálculo automático de combustible corresponde a vehículos propios terrestres con diésel.");
    }

    if (tipoActual === "tarifa") {
        //$("#ccAyuda").text("Un incremento de 20% se aplica sobre el costo total calculado, no es margen sobre precio de venta.");
    }

    const elemento = document.getElementById("ccModal");
    const modal = bootstrap.Modal.getOrCreateInstance(elemento, {backdrop: "static"});
    const catalogos = document.getElementById("ccModalCatalogos");

    $(elemento).on("hide.bs.modal", function() {
    const enfocado = document.activeElement;

    if (enfocado && this.contains(enfocado)) {
        enfocado.blur();
    }
});

$(elemento).one("shown.bs.modal", function() {
    $(this).find(".modal-body input:enabled:visible").first().trigger("focus");
});

    $("#ccFormulario").data("indice", indice);

    if (tipoActual === "destino") {
        $(elemento).one("shown.bs.modal", iniciarMapaDestino);
    }

    $(elemento).one("hidden.bs.modal", function() {
        if (mapaDestinoFormulario) {
                folioLugar++;
    clearTimeout(temporizadorLugar);

    if (solicitudLugar) {
        solicitudLugar.abort();
        solicitudLugar = null;
    }
    mapaDestinoFormulario.remove();
    mapaDestinoFormulario = null;
    marcadorDestinoFormulario = null;
}
        modal.dispose();
        $(this).remove();

        if (catalogos && document.body.contains(catalogos)) {
            bootstrap.Modal.getOrCreateInstance(catalogos).show();
            consultarCatalogos(mostrarTablaCosteo);
        }
    });

    if (catalogos && $(catalogos).hasClass("show")) {
    $(catalogos).one("hidden.bs.modal", function() {
        if (elemento.isConnected) {
            modal.show();
        }
    });

    bootstrap.Modal.getOrCreateInstance(catalogos).hide();
} else {
    modal.show();
}
}

function guardarFormularioCosteo(e) {
    e.preventDefault();

    const form = e.currentTarget;
    if (!form.checkValidity()) return form.reportValidity();

    const tipo = tipoActual;
    const config = esquemas[tipo];
    const indice = $(form).data("indice");
    const original = indice === null ? null : datos[config.coleccion][indice];

    const parametros = {
        action: "guardarCatalogoCosteo",
        tipo: tipo,
        id: original?.id ?? "0"
    };

    config.campos.forEach(function(field) {
        parametros[field.name] = String(
            $(form).find(`[name="${field.name}"]`).val() ?? ""
        ).trim();
    });

    if (tipo === "vehiculo") {
        parametros.vin = parametros.vin.toUpperCase();

        if (parametros.peso_bruto_kg && parametros.carga_util_kg &&
            Number(parametros.carga_util_kg) > Number(parametros.peso_bruto_kg)) {
            return Swal.fire(
                "Revisa las capacidades",
                "La carga útil no puede superar el peso bruto.",
                "warning"
            );
        }

        if (parametros.tipo_vehiculo === "PROPIO" &&
            Number(parametros.rendimiento_km_litro) <= 0) {
            return Swal.fire("Falta rendimiento", "Indica el rendimiento del vehículo propio.", "warning");
        }
    }

    if (tipo === "tarifa") delete parametros.id;

    const $boton = $("#ccGuardar").prop("disabled", true).text("Guardando...");

    api(parametros).done(function(r) {
        if (r.status !== "ok") {
            return Swal.fire("Revisa el registro", r.answer || "No se pudo guardar.", "warning");
        }

        bootstrap.Modal.getInstance(document.getElementById("ccModal"))?.hide();
        consultarCatalogos(mostrarTablaCosteo);

        Swal.fire({
            toast: true,
            position: "top-end",
            icon: "success",
            title: r.answer || "Guardado",
            showConfirmButton: false,
            timer: 2200
        });
    }).fail(function(xhr) {
        Swal.fire("No se pudo guardar", errorApi(xhr), "error");
    }).always(function() {
        $boton.prop("disabled", false).html('<i class="bi bi-check2 me-2"></i>Guardar');
    });
}

function cambiarEstadoCosteo(indice) {
    const config = esquemas[tipoActual];
    const registro = datos[config.coleccion]?.[indice];

    if (!registro || Number(registro.eliminado || 0) === 1) return;

    Swal.fire({
        title: "¿Eliminar este registro?",
        text: "El registro dejará de estar disponible para nuevas cotizaciones.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#b42318"
    }).then(function(decision) {
        if (!decision.isConfirmed) return;

        const parametros = {
            action: "cambiarEstadoCatalogoCosteo",
            tipo: tipoActual,
            operacion: "eliminar"
        };

        if (tipoActual === "tarifa") {
            parametros.material_id = registro.material_id;
            parametros.lista_id = registro.lista_id;
        } else {
            parametros.id = registro.id;
        }

        api(parametros).done(function(r) {
            if (r.status !== "ok") {
                return Swal.fire("No disponible", r.answer || "No se pudo eliminar.", "warning");
            }

            consultarCatalogos(mostrarTablaCosteo);

            Swal.fire({
                icon: "success",
                title: "Registro eliminado",
                timer: 1700,
                showConfirmButton: false
            });
        }).fail(function(xhr) {
            Swal.fire("Error", errorApi(xhr), "error");
        });
    });
}

function renderCotizadorCosteoAnterior() {
    if (!window.CSM_ES_ADMIN) {
        return $("#contenidoModulo").html(
            '<div class="alert alert-warning">Esta sección es exclusiva de administración.</div>'
        );
    }

    cotizacionVigente = null;

    $("#contenidoModulo").html(`
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-outline-success" id="btnCatalogosDesdeCotizador">
                <i class="bi bi-sliders2 me-2"></i>Configurar materiales y transporte
            </button>
        </div>

        <form id="ccCotizador" class="row g-3" novalidate>
            <div class="col-xl-8">
                <section class="nota-bloque mb-3">
                    <div class="nota-seccion-titulo">
                        <i class="bi bi-truck"></i>
                        <div>
                            <h2>Datos de la entrega</h2>
                            <small>Selecciona material, destino, vehículo y lista de precios.</small>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ccCliente">Cliente</label>
                            <input class="form-control" name="cliente" id="ccCliente"
                                   maxlength="100" placeholder="Nombre para guardar la cotización">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ccCantidad">Cantidad *</label>
                            <input class="form-control" type="number" name="cantidad"
                                   id="ccCantidad" min="0.01" max="100000"
                                   step="0.01" value="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ccMaterial">Material *</label>
                            <select name="material_id" id="ccMaterial" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ccLista">Lista de precios *</label>
                            <select name="lista_id" id="ccLista" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ccVehiculo">Vehículo *</label>
                            <select name="vehiculo_id" id="ccVehiculo" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ccDestino">Destino *</label>
                            <select name="destino_id" id="ccDestino" class="form-select" required></select>
                        </div>
                    </div>
                    <p class="small text-secondary mt-3 mb-0" id="ccCondiciones">
                        El precio final incluye el material y la logística.
                        No se desglosa un flete adicional al cliente.
                    </p>
                </section>

                <section class="nota-bloque">
                    <div class="nota-seccion-titulo">
                        <i class="bi bi-calculator"></i>
                        <div>
                            <h2>Gastos de transporte</h2>
                            <small>Captura importes reales; los gastos por viaje se multiplican por los viajes necesarios.</small>
                        </div>
                    </div>
                    <div class="row g-3" id="ccGastos"></div>
                </section>
            </div>

            <div class="col-xl-4">
                <aside class="nota-bloque cc-resumen sticky-xl-top">
                    <h2 class="h5 fw-bold">Resultado de cotización</h2>
                    <p class="small text-secondary">Calculado en el servidor para evitar diferencias.</p>
                    <div id="ccResultado">
                        <p class="text-secondary">
                            Completa los datos y presiona <strong>Calcular precio</strong>.
                        </p>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mb-2" id="ccCalcular">
                        <i class="bi bi-calculator me-2"></i>Calcular precio
                    </button>
                    <button type="button" class="btn btn-outline-success w-100"
                            id="ccGuardarCotizacion" disabled>
                        <i class="bi bi-floppy me-2"></i>Guardar cotización
                    </button>
                    <p class="small text-secondary mb-0 mt-3">
                        Una cotización guardada no registra una venta ni mueve caja o inventario.
                    </p>
                </aside>
            </div>
        </form>
    `);

    const gastos = [
        ["precio_diesel_litro", "Precio del diésel ($/L)", "27.00"],
        ["chofer_por_viaje", "Chofer por viaje ($)", "0.00"],
        ["carga_por_viaje", "Carga por viaje ($)", "0.00"],
        ["descarga_por_viaje", "Descarga por viaje ($)", "0.00"],
        ["flete_contratado_por_viaje", "Flete contratado por viaje ($)", "0.00"],
        ["otros_gastos_total", "Otros gastos de toda la entrega ($)", "0.00"]
    ];

    gastos.forEach(function(gasto) {
        const clave = gasto[0];
        const etiqueta = gasto[1];
        const valor = gasto[2];
        const $box = $("<div>", {class: "col-md-6"});

        $box.append($("<label>", {
            class: "form-label",
            for: "cc_" + clave,
            text: etiqueta
        }));

        $box.append($("<input>", {
            id: "cc_" + clave,
            name: clave,
            type: "number",
            step: "0.01",
            min: "0",
            max: "1000000",
            value: valor,
            class: "form-control"
        }));

        $("#ccGastos").append($box);
    });

    consultarCatalogos(actualizarOpcionesCotizador);
}

function actualizarOpcionesCotizador() {
    if (!$("#ccCotizador").length) return;

    [
        ["ccMaterial", "materiales"],
        ["ccLista", "listas"],
        ["ccVehiculo", "vehiculos"],
        ["ccDestino", "destinos"]
    ].forEach(function(item) {
        const id = item[0];
        const clave = item[1];
        const $select = $("#" + id);
        const anterior = $select.val();

        $select.empty().append(new Option("Selecciona una opción", ""));

        activos(clave).forEach(function(registro) {
            $select.append(new Option(
                (registro.codigo ? registro.codigo + " · " : "") + registro.nombre,
                String(registro.id)
            ));
        });

        const disponible = anterior && $select.find("option").filter(function(_, opcion) {
            return opcion.value === anterior;
        }).length;

        $select.val(disponible ? anterior : "");
    });

    actualizarCondiciones();
}

function actualizarCondiciones() {
    if (!$("#ccCotizador").length) return;

    const vehiculo = activos("vehiculos").find(function(registro) {
        return String(registro.id) === $("#ccVehiculo").val();
    });

    if (!vehiculo) {
        $("#ccCondiciones").text(
            "El precio final incluye material y logística; no se muestra un flete adicional al cliente."
        );
        return;
    }

    const propio = vehiculo.tipo === "PROPIO";

    $("#cc_precio_diesel_litro, #cc_chofer_por_viaje")
        .prop("disabled", !propio)
        .val((_, valor) => propio ? valor : "0.00");

    $("#cc_flete_contratado_por_viaje")
        .prop("disabled", propio)
        .val((_, valor) => propio ? "0.00" : valor);

    $("#ccCondiciones").text(propio
        ? "Vehículo propio: se calcula combustible, casetas, chofer y costo fijo por viaje."
        : "Vehículo contratado: registra el flete por viaje sin duplicar combustible ni chofer incluidos."
    );
}

function calcularCotizacionCosteo(e) {
    e.preventDefault();

    const form = e.currentTarget;
    if (!form.checkValidity()) return form.reportValidity();

    const d = Object.fromEntries(new FormData(form).entries());

    ["precio_diesel_litro", "chofer_por_viaje", "flete_contratado_por_viaje"].forEach(function(clave) {
        if (!(clave in d)) d[clave] = "0.00";
    });

    const huellaFormulario = JSON.stringify(Array.from(new FormData(form).entries()));
    const $btn = $("#ccCalcular").prop("disabled", true).text("Calculando...");

    cotizacionVigente = null;
    $("#ccGuardarCotizacion").prop("disabled", true);

    api({action: "cotizarMaterial", datos: JSON.stringify(d)}).done(function(r) {
        if (!form.isConnected || JSON.stringify(Array.from(new FormData(form).entries())) !== huellaFormulario) return;
        if (r.status !== "ok") {
            return Swal.fire("Revisa el cálculo", r.answer || "No fue posible cotizar.", "warning");
        }

        let solicitudId = "";

        try {
            solicitudId = crearSolicitudCosteo();
        } catch (error) {
            return Swal.fire("Error", "No fue posible generar el identificador seguro de la cotización.", "error");
        }

        cotizacionVigente = {
            datos: d,
            resultado: r.results,
            solicitud_id: solicitudId
        };

        const v = r.results;
        const $resultado = $("#ccResultado").empty();

        [
            ["Cantidad", `${v.cantidad} ${v.unidad}`],
            ["Viajes necesarios", v.viajes],
            ["Costo de material", mxn(v.costo_material)],
            ["Costos de entrega", mxn(v.costos_entrega)],
            ["Costo total", mxn(v.costo_total)],
            ["Precio por unidad", mxn(v.precio_unitario)],
            ["Utilidad estimada", mxn(v.utilidad)]
        ].forEach(function(item) {
            $resultado.append(
                $("<div>", {class: "d-flex justify-content-between gap-3 border-bottom py-2 small"})
                    .append(
                        $("<span>", {text: item[0]}),
                        $("<strong>", {text: String(item[1])})
                    )
            );
        });

        $resultado.append(
            $("<div>", {class: "cc-total mt-3 mb-3"}).append(
                $("<small>", {text: "TOTAL DE VENTA (INCLUYE ENTREGA)"}),
                $("<strong>", {text: mxn(v.total_venta)})
            )
        );

        $("#ccGuardarCotizacion").prop("disabled", !d.cliente.trim());

        if (!d.cliente.trim()) {
            $resultado.append($("<p>", {
                class: "small text-secondary",
                text: "Indica un cliente para habilitar Guardar cotización."
            }));
        }
    }).fail(function(xhr) {
        Swal.fire("Error", errorApi(xhr), "error");
    }).always(function() {
        $btn.prop("disabled", false).html('<i class="bi bi-calculator me-2"></i>Calcular precio');
    });
}

function crearSolicitudCosteo() {
    if (!window.crypto?.getRandomValues) {
        throw new Error("Generador criptográfico no disponible.");
    }

    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);

    return Array.from(bytes, numero =>
        numero.toString(16).padStart(2, "0")
    ).join("");
}

function guardarCotizacionCosteo() {
    if (!cotizacionVigente || !cotizacionVigente.datos.cliente.trim()) return;

    const registro = {
        ...cotizacionVigente.datos,
        solicitud_id: cotizacionVigente.solicitud_id,
        total_esperado: String(cotizacionVigente.resultado.total_venta)
    };

    Swal.fire({
        title: "¿Guardar cotización?",
        text: "Se conservará el desglose de costos. No se registrará una venta.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Guardar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#086b47"
    }).then(function(r) {
        if (!r.isConfirmed) return;

        const $btn = $("#ccGuardarCotizacion").prop("disabled", true).text("Guardando...");

        api({
            action: "guardarCotizacionCosteo",
            datos: JSON.stringify(registro)
        }).done(function(res) {
            if (res.status !== "ok") {
                return Swal.fire("Aviso", res.answer || "No fue posible guardar.", "warning");
            }

            Swal.fire(
                "Cotización guardada",
                `Folio interno: ${res.results?.id}. ${res.answer}`,
                "success"
            );
        }).fail(function(xhr) {
            Swal.fire("Error", errorApi(xhr), "error");
        }).always(function() {
            $btn.prop("disabled", false).html('<i class="bi bi-floppy me-2"></i>Guardar cotización');
        });
    });
}


/* COSTEO COMERCIAL · nuevas pestañas y simuladores */
const ccGrupos = [
    ["PETREO","Pétreos"],["BLOCK","Block ligero"],["BLOCK_PESADO","Block pesado"],
    ["TABIQUE_LIGERO","Tabique ligero"],["LADRILLO","Ladrillo"],["CEMENTO","Cemento, mortero y cal"],["OTRO","Otro"]
];
const ccUnidadesCobro = [["VIAJE","Por viaje"],["M3","Por m³"],["MILLAR","Por millar"],["TON","Por tonelada"],["PZA","Por pieza"],["ENTREGA","Por entrega"]];
esquemas.material = {
    titulo:"Materiales",coleccion:"materiales",
    columnas:[["codigo","Código"],["nombre","Material"],["grupo_flete","Grupo"],["unidad","Unidad base"],["costo_unitario","Costo base"]],
    campos:[campo("codigo","Código","text",true,{max:50}),campo("nombre","Nombre del material","text",true,{max:150}),
        select("grupo_flete","Grupo",ccGrupos),select("unidad","Unidad base",["M3","PZA","KG","TON","BULTO","VIAJE"]),
        numero("costo_unitario","Costo por unidad base ($)","0.00000001"),numero("producto_id","ID del producto relacionado (opcional)","1",false,{min:"1"}),
        numero("peso_unitario_kg","Kg por unidad base (opcional)","0.0001",false)]
};
esquemas.presentacion = {
    titulo:"Presentaciones",coleccion:"presentaciones",
    columnas:[["material_nombre","Material"],["codigo","Código"],["nombre","Presentación"],["factor_numerador","Numerador"],["factor_denominador","Denominador"]],
    campos:[select("material_id","Material",[]),campo("codigo","Código","text",true,{max:25}),campo("nombre","Nombre","text",true,{max:100}),
        numero("factor_numerador","Numerador","1",true,{min:"1"}),numero("factor_denominador","Denominador","1",true,{min:"1"}),
        ...[["permite_entrada","Permite entrada"],["permite_venta","Permite venta"],["permite_mezcla","Permite mezcla"],["cantidad_entera","Cantidad entera"]].map(([k,v])=>select(k,v,[["1","Sí"],["0","No"]]))]
};
datos.presentaciones = [];
let ccVistaComercial = "precios", ccVersionComercial = 0, ccTimerComercial = null, ccPeticionComercial = null;
let ccPreciosActuales = [], ccFletesActuales = [], ccPublicando = false;
const ccComercialApi = (operacion,d={}) => api({action:"operarCosteoComercial",operacion:operacion,datos:JSON.stringify(d)});

function ccOpciones($select, filas, placeholder="Selecciona", anterior=null) {
    const valor = anterior === null ? $select.val() : anterior;
    $select.empty();
    if (placeholder !== null) $select.append(new Option(placeholder,""));
    filas.forEach(([id,nombre])=>$select.append(new Option(nombre,String(id))));
    if (valor !== null && $select.find("option").filter((_,o)=>o.value===String(valor)).length) $select.val(String(valor));
}

function ccCampo(id,etiqueta,tipo="select",extra="") {
    return `<div class="csc-field"><label for="${id}" class="form-label">${etiqueta}</label>${tipo==="select" ? `<select id="${id}" class="form-select" ${extra}></select>` : `<input id="${id}" class="form-control" type="${tipo}" ${extra}>`}</div>`;
}

function renderCotizadorCosteo(vista=ccVistaComercial) {
    if (!window.CSM_ES_ADMIN) return;
    ccVistaComercial = vista;
    const version = ++ccVersionComercial;
    clearTimeout(ccTimerComercial);
    if (ccPeticionComercial) {ccPeticionComercial.abort(); ccPeticionComercial=null;}
    const pestanas = [["precios","tags","Precios"],["fletes","geo-alt","Fletes por comunidad"],["cotizar","calculator","Cotizar entrega"],["traslado","truck","Costeo de traslado"]];
    const barra = `<div class="csc-nav mb-3">${pestanas.map(([id,icono,nombre])=>`<button type="button" data-vista="${id}" class="btn btn-sm ${id===vista?"btn-success":"btn-outline-secondary"} csc-tab" aria-pressed="${id===vista}"><i class="bi bi-${icono} me-1"></i>${nombre}</button>`).join("")}</div>`;
    if (vista === "traslado") {
        renderCotizadorCosteoAnterior();
        $("#contenidoModulo").prepend(barra);
        return;
    }
    $("#contenidoModulo").html(`${barra}<div id="ccComercial"><div class="d-flex justify-content-end mb-3 gap-2 flex-wrap"><button class="btn btn-sm btn-outline-success" type="button" id="btnCatalogosDesdeCotizador"><i class="bi bi-sliders2 me-1"></i>Catálogos</button><button class="btn btn-sm btn-outline-secondary csc-recargar" type="button"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</button></div><div id="cscPanel" class="text-secondary py-3">Cargando materiales y precios…</div></div>`);
    consultarCatalogos(function() {
        if (version!==ccVersionComercial || !$("#cscPanel").length) return;
        ccComercialApi("consultar").done(function(r) {
            if (version!==ccVersionComercial || !$("#cscPanel").length) return;
            if (r.status!=="ok") return $("#cscPanel").text(r.answer);
            ccPreciosActuales = r.results.precios || [];
            ccFletesActuales = r.results.fletes || [];
            $("#cscPanel").removeClass("text-secondary py-3");
            if (vista==="precios") ccRenderPrecios();
            else if (vista==="fletes") ccRenderFletes();
            else ccRenderCotizar();
        }).fail(function(xhr) {
            if (version===ccVersionComercial && $("#cscPanel").length) $("#cscPanel").text(errorApi(xhr));
        });
    });
}

function ccLlenarSelects(prefijo) {
    ccOpciones($("#"+prefijo+"Material"),activos("materiales").map(m=>[m.id,m.nombre]));
    ccOpciones($("#"+prefijo+"Lista"),activos("listas").map(m=>[m.id,m.nombre]));
    ccOpciones($("#"+prefijo+"Destino"),[[0,"Recoger en patio"],...activos("destinos").map(m=>[m.id,m.nombre])],null,"0");
    ccOpciones($("#"+prefijo+"Vehiculo"),[[0,"Sin asignar"],...activos("vehiculos").map(m=>[m.id,`${m.nombre} · ${m.capacidad} ${m.unidad_capacidad}`])],null,"0");
}

function ccPresentaciones(prefijo) {
    const material = $("#"+prefijo+"Material").val();
    ccOpciones($("#"+prefijo+"Presentacion"),activos("presentaciones").filter(p=>String(p.material_id)===String(material) && Number(p.permite_venta)===1).map(p=>[p.id,p.nombre]));
}

function ccRenderPrecios() {
    $("#cscPanel").html(`<div class="csc-layout"><section class="csc-card"><div class="csc-heading"><i class="bi bi-tags"></i><div><h2>Publicar precio</h2><p>Configura una presentación, una lista y su precio vigente.</p></div></div><form id="cscPrecioForm"><div class="csc-fields">
        ${ccCampo("cspMaterial","Material")}${ccCampo("cspPresentacion","Presentación")}${ccCampo("cspLista","Lista de precios")}${ccCampo("cspDestino","Precio aplicable en")}
        ${ccCampo("cspRecargo","Incremento sobre costo (%)","number",'min="0" max="200" step="0.0001" value="0" required')}${ccCampo("cspAdicional","Incremento fijo ($)","number",'min="0" step="0.01" value="0" required')}
        ${ccCampo("cspManual","Precio manual (opcional)","number",'min="0.01" step="0.01" placeholder="Automático"')}${ccCampo("cspObs","Motivo u observaciones","text",'maxlength="255"')}
        </div><div class="csc-pres-tools"><button type="button" id="cspGenerar" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-circle me-1"></i>Generar equivalencias del material</button></div>
        <div class="csc-estimate" aria-live="polite"><div><small>Costo de la presentación</small><strong id="cspCosto">—</strong></div><div><small>Precio calculado</small><strong id="cspCalculado">—</strong></div><div><small>Precio que se publicará</small><strong id="cspFinal">—</strong></div></div>
        <button type="submit" class="btn btn-success w-100" id="cspPublicar"><i class="bi bi-check2-circle me-2"></i>Publicar nueva versión</button></form></section>
        <section class="csc-card"><div class="csc-heading"><i class="bi bi-clock-history"></i><div><h2>Precios vigentes</h2><p>Selecciona un registro para consultar su historial.</p></div></div><input id="cscBuscarPrecios" class="form-control mb-3" placeholder="Buscar material, lista o destino" aria-label="Buscar precio"><div class="csc-table table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Material / presentación</th><th>Lista / destino</th><th class="text-end">Precio</th><th></th></tr></thead><tbody id="cscPreciosBody"></tbody></table></div></section></div>`);
    ccLlenarSelects("csp");
    $("#cspMaterial,#cspPresentacion,#cspLista").prop("required",true);
    ccPresentaciones("csp"); ccActualizarPrecio(); ccTablaComercial("precios");
}

function ccActualizarPrecio() {
    const material = activos("materiales").find(m=>String(m.id)===$("#cspMaterial").val());
    const p = activos("presentaciones").find(m=>String(m.id)===$("#cspPresentacion").val());
    const lista = $("#cspLista").val();
    const valido = material && p && lista && Number(p.factor_denominador)>0 && Number(material.costo_unitario)>0;
    const costo = valido ? Number(material.costo_unitario)*Number(p.factor_numerador)/Number(p.factor_denominador) : 0;
    const calculado = costo*(1+Number($("#cspRecargo").val())/100)+Number($("#cspAdicional").val());
    const final = $("#cspManual").val()==="" ? calculado : Number($("#cspManual").val());
    $("#cspCosto").text(valido?mxn(costo):"—"); $("#cspCalculado").text(valido?mxn(calculado):"—"); $("#cspFinal").text(valido?mxn(final):"—");
    $("#cspPublicar").prop("disabled",ccPublicando || !valido || final<=0 || !Number.isFinite(final));
    $("#cspGenerar").prop("disabled",!material);
}

function ccRecargoSugerido() {
    const t=activos("tarifas").find(r=>String(r.material_id)===$("#cspMaterial").val() && String(r.lista_id)===$("#cspLista").val());
    $("#cspRecargo").val(t?.recargo_pct ?? "0"); ccActualizarPrecio();
}

function ccRenderFletes() {
    $("#cscPanel").html(`<div class="csc-layout"><section class="csc-card"><div class="csc-heading"><i class="bi bi-truck"></i><div><h2>Tarifa de distribución</h2><p>Define cuánto se cobra y cómo se compone cada tarifa.</p></div></div><form id="cscFleteForm"><div class="csc-fields">
        ${ccCampo("csfDestino","Comunidad")}${ccCampo("csfGrupo","Grupo de material")}${ccCampo("csfPresentacion","Presentación")}${ccCampo("csfUnidad","Forma de cobro")}</div><div class="d-flex justify-content-between align-items-center mt-3 mb-2 gap-2"><h3 class="h6 mb-0">Conceptos incluidos</h3><button type="button" class="btn btn-sm btn-outline-success" id="csfAgregar"><i class="bi bi-plus-lg me-1"></i>Agregar</button></div><div id="csfConceptos"></div>
        <div class="csc-estimate csc-estimate-single" aria-live="polite"><div><small>Tarifa total por unidad de cobro</small><strong id="csfTotal">$0.00</strong></div></div><div class="mt-3">${ccCampo("csfObs","Observaciones","text",'maxlength="255" placeholder="Aclaraciones de la tarifa"')}</div>
        <button class="btn btn-success w-100 mt-3" type="submit"><i class="bi bi-check2-circle me-2"></i>Publicar tarifa</button></form></section><section class="csc-card"><div class="csc-heading"><i class="bi bi-map"></i><div><h2>Fletes vigentes</h2><p>Las tarifas no configuradas no se consideran gratuitas.</p></div></div><input id="cscBuscarFletes" class="form-control mb-3" placeholder="Buscar comunidad o grupo" aria-label="Buscar flete"><div class="csc-table table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Comunidad / grupo</th><th>Presentación / cobro</th><th class="text-end">Tarifa</th><th></th></tr></thead><tbody id="cscFletesBody"></tbody></table></div></section></div>`);
    ccOpciones($("#csfDestino"),activos("destinos").map(m=>[m.id,m.nombre]));
    ccOpciones($("#csfGrupo"),ccGrupos); ccOpciones($("#csfUnidad"),ccUnidadesCobro);
    $("#csfDestino,#csfGrupo,#csfPresentacion,#csfUnidad").prop("required",true);
    ccOpcionesFlete(); ccAgregarConcepto(); ccTablaComercial("fletes");
}

function ccOpcionesFlete() {
    const grupo=$("#csfGrupo").val();
    const ids=new Set(activos("materiales").filter(m=>m.grupo_flete===grupo).map(m=>String(m.id)));
    const unicos=new Map(activos("presentaciones").filter(p=>ids.has(String(p.material_id)) && Number(p.permite_venta)===1).map(p=>[p.codigo,p.nombre]));
    ccOpciones($("#csfPresentacion"),[["TODAS","Todas las presentaciones del grupo"],...unicos.entries()],null);
}

function ccAgregarConcepto(nombre="",cantidad="1",precio="") {
    if ($("#csfConceptos .csc-concepto").length>=30) return;
    const $r=$(`<div class="csc-concepto"><div><label class="form-label">Concepto</label><input class="form-control csf-nombre" maxlength="100" placeholder="Ej. Flete, ayudante" required></div><div><label class="form-label">Cantidad</label><input class="form-control csf-cantidad" type="number" min="0.01" max="1000" step="0.01" required></div><div><label class="form-label">Precio ($)</label><input class="form-control csf-precio" type="number" min="0" max="1000000" step="0.01" required></div><div class="csc-importe"><small>Importe</small><strong>$0.00</strong></div><button type="button" class="btn btn-sm btn-outline-danger csf-quitar" aria-label="Quitar concepto"><i class="bi bi-x-lg"></i></button></div>`);
    $r.find(".csf-nombre").val(nombre); $r.find(".csf-cantidad").val(cantidad); $r.find(".csf-precio").val(precio);
    $("#csfConceptos").append($r); ccTotalFlete();
}

function ccTotalFlete() {
    let total=0;
    $("#csfConceptos .csc-concepto").each(function() {
        const n=Math.round(Number($(this).find(".csf-cantidad").val())*100), p=Math.round(Number($(this).find(".csf-precio").val())*100);
        const importe=Math.floor((Math.max(0,n||0)*Math.max(0,p||0)+50)/100);
        total+=importe; $(this).find(".csc-importe strong").text(mxn(importe/100));
    });
    $("#csfTotal").text(mxn(total/100));
}

function ccTablaComercial(tipo) {
    const esPrecio=tipo==="precios", filas=esPrecio?ccPreciosActuales:ccFletesActuales;
    const filtro=String($(esPrecio?"#cscBuscarPrecios":"#cscBuscarFletes").val()||"").toLocaleLowerCase("es-MX");
    const $body=$(esPrecio?"#cscPreciosBody":"#cscFletesBody").empty();
    filas.filter(r=>[r.material,r.presentacion,r.lista,r.destino,r.grupo_flete,r.unidad_cobro].some(v=>String(v??"").toLocaleLowerCase("es-MX").includes(filtro))).forEach(function(r) {
        const celda=(a,b)=>$("<td>").append($("<strong>",{class:"d-block",text:a}),$("<small>",{class:"text-secondary",text:b}));
        const $tr=$("<tr>").append(celda(esPrecio?r.material:r.destino,esPrecio?r.presentacion:r.grupo_flete),celda(esPrecio?r.lista:r.presentacion,esPrecio?r.destino:r.unidad_cobro),$("<td>",{class:"text-end fw-semibold text-success",text:mxn(esPrecio?r.precio:r.importe)}));
        $tr.append($("<td>").append($("<button>",{type:"button",class:"btn btn-sm btn-outline-success csc-historial",title:"Ver historial", "aria-label":"Ver historial",html:'<i class="bi bi-clock-history"></i>'}).attr({"data-id":r.id,"data-tipo":esPrecio?"precio":"flete"}))).appendTo($body);
    });
    if (!$body.children().length) $body.append($("<tr>").append($("<td>",{colspan:4,class:"text-center text-secondary py-4",text:"No hay precios o tarifas que coincidan."})));
}

function ccRenderCotizar() {
    $("#cscPanel").html(`<div class="csc-layout"><section class="csc-card"><div class="csc-heading"><i class="bi bi-cart3"></i><div><h2>Armar cotización</h2><p>Elige la presentación y el destino. Los precios se consultan en el servidor.</p></div></div><form id="cscCotizarForm"><div class="csc-fields">${ccCampo("csqMaterial","Material principal")}${ccCampo("csqPresentacion","Presentación")}${ccCampo("csqLista","Lista de precios")}${ccCampo("csqCantidad","Cantidad de presentaciones","number",'value="1" min="0.01" max="100000" step="0.01" required')}${ccCampo("csqDestino","Destino")}${ccCampo("csqVehiculo","Vehículo (para flete por viaje)")}</div><div id="csqMezclaWrap" class="d-none mt-3"><div class="form-check form-switch"><input type="checkbox" id="csqMezcla" class="form-check-input"><label for="csqMezcla" class="form-check-label fw-semibold">Combinar materiales en la misma carga</label></div><div id="csqComponentesWrap" class="d-none"><p class="small text-secondary mt-2 mb-1">Captura los m³ de cada material por una sola carga. Se repetirán por la cantidad de cargas.</p><div id="csqComponentes"></div><div class="csc-capacity"><div id="csqBarra" class="csc-capacity-fill"></div></div><div class="small text-secondary mt-1" id="csqCapacidad" aria-live="polite"></div><button type="button" id="csqAgregar" class="btn btn-sm btn-outline-success mt-2"><i class="bi bi-plus-lg me-1"></i>Otro material</button></div></div><button type="submit" class="btn btn-success w-100 mt-3"><i class="bi bi-calculator me-2"></i>Calcular precio</button></form></section><aside class="csc-card csc-resumen"><div class="csc-heading"><i class="bi bi-receipt"></i><div><h2>Resultado de cotización</h2><p>Precio final con entrega cuando corresponda.</p></div></div><div id="csqResultado" aria-live="polite" class="text-secondary">Completa los datos para calcular.</div><p class="small text-secondary mb-0 mt-3">Vista previa comercial. No registra una venta, no mueve inventario ni guarda una cotización definitiva.</p></aside></div>`);
    ccLlenarSelects("csq"); $("#csqMaterial,#csqPresentacion,#csqLista").prop("required",true); ccPresentaciones("csq");
}

function ccPresentacionCotizada() {return activos("presentaciones").find(p=>String(p.id)===$("#csqPresentacion").val());}
function ccCambiarPresentacionCotizada() {
    const p=ccPresentacionCotizada();
    $("#csqCantidad").attr({step:p && Number(p.cantidad_entera)?"1":"0.01",min:p && Number(p.cantidad_entera)?"1":"0.01"});
    $("#csqMezclaWrap").toggleClass("d-none",!p || !Number(p.permite_mezcla));
    $("#csqMezcla").prop("checked",false); $("#csqComponentesWrap").addClass("d-none"); $("#csqComponentes").empty();
    ccProgramarCotizacion();
}

function ccAgregarComponente(id="",cantidad="") {
    if ($("#csqComponentes .csc-componente").length>=10) return;
    const $r=$(`<div class="csc-componente"><div><label class="form-label">Material</label><select class="form-select csq-mat"></select></div><div><label class="form-label">m³ por carga</label><input class="form-control csq-vol" type="number" min="0.01" step="0.01"></div><button type="button" class="btn btn-sm btn-outline-danger csq-quitar" aria-label="Quitar material"><i class="bi bi-x-lg"></i></button></div>`);
    ccOpciones($r.find(".csq-mat"),activos("materiales").filter(m=>m.grupo_flete==="PETREO" && m.unidad==="M3").map(m=>[m.id,m.nombre]),"Material",id);
    $r.find(".csq-vol").val(cantidad); $("#csqComponentes").append($r); ccCapacidad();
}

function ccCapacidad() {
    const p=ccPresentacionCotizada();
    const cap=p?Number(p.factor_numerador)/Number(p.factor_denominador):0;
    let volumen=0; $("#csqComponentes .csq-vol").each(function(){volumen+=Number(this.value)||0;});
    const exceso=volumen>cap+0.000001;
    $("#csqBarra").css("width",Math.min(100,cap?100*volumen/cap:0)+"%").toggleClass("csc-exceso",exceso);
    $("#csqCapacidad").text(`${volumen.toFixed(2)} de ${cap.toFixed(2)} m³ · ${exceso?"Excede capacidad":Math.abs(cap-volumen)<0.000001?"Carga completa":"Falta completar"}`).toggleClass("text-danger",exceso);
    return cap>0 && Math.abs(cap-volumen)<0.000001;
}

function ccProgramarCotizacion(inmediata=false) {
    clearTimeout(ccTimerComercial);
    if (ccPeticionComercial) {ccPeticionComercial.abort(); ccPeticionComercial=null;}
    if (!$("#cscCotizarForm").length) return;
    const $r=$("#csqResultado").empty().text("Completa los datos para calcular.");
    if (!$("#cscCotizarForm")[0].checkValidity()) return;
    const d={material_id:$("#csqMaterial").val(),presentacion_id:$("#csqPresentacion").val(),lista_id:$("#csqLista").val(),cantidad:$("#csqCantidad").val(),destino_id:$("#csqDestino").val(),vehiculo_id:$("#csqVehiculo").val(),componentes:[]};
    if ($("#csqMezcla").is(":checked")) {
        if (!ccCapacidad()) return $r.text("Completa el volumen de la carga mixta.");
        $("#csqComponentes .csc-componente").each(function(){d.componentes.push({material_id:$(this).find(".csq-mat").val(),cantidad:$(this).find(".csq-vol").val()});});
        if (d.componentes.length<2 || d.componentes.some(c=>!c.material_id || !c.cantidad)) return $r.text("Selecciona al menos dos materiales y su volumen.");
    }
    const version=ccVersionComercial;
    $r.text("Calculando…");
    ccTimerComercial=setTimeout(function() {
        const peticion=ccComercialApi("cotizar",d); ccPeticionComercial=peticion;
        peticion.done(function(r) {
            if (version!==ccVersionComercial || ccPeticionComercial!==peticion || !$r[0]?.isConnected) return;
            if (r.status!=="ok") return $r.text(r.answer);
            const v=r.results; $r.empty();
            v.componentes.forEach(c=>$r.append($("<div>",{class:"csc-result-row"}).append($("<div>").append($("<strong>",{class:"d-block",text:c.material}),$("<small>",{text:`${c.cantidad} ${c.unidad} · ${mxn(c.precio)}`})),$("<strong>",{text:mxn(c.importe)}))));
            $r.append($("<div>",{class:"csc-result-row"}).append($("<span>",{text:v.precio_destino?"Entrega incluida":"Flete adicional"}),$("<strong>",{text:v.precio_destino?"Incluido":mxn(v.flete)})));
            if (v.viajes!==null) $r.append($("<div>",{class:"csc-result-row"}).append($("<span>",{text:"Viajes"}),$("<strong>",{text:v.viajes})));
            $r.append($("<div>",{class:"csc-total"}).append($("<small>",{text:"TOTAL DE COTIZACIÓN"}),$("<strong>",{text:mxn(v.total)})));
            (v.avisos||[]).forEach(a=>$r.append($("<p>",{class:"small text-secondary mt-2 mb-0",text:a})));
        }).fail(function(xhr,estado){if (estado!=="abort" && version===ccVersionComercial && ccPeticionComercial===peticion && $r[0]?.isConnected) $r.text(errorApi(xhr));}).always(function(){if(ccPeticionComercial===peticion) ccPeticionComercial=null;});
    },inmediata?0:350);
}

function ccPublicar(operacion,d,$boton) {
    if (ccPublicando || $boton.prop("disabled")) return;
    ccPublicando=true;
    $boton.prop("disabled",true);
    const version=ccVersionComercial;
    ccComercialApi(operacion,d).done(function(r){
        if (r.status!=="ok") return Swal.fire("Revisa los datos",r.answer,"warning");
        if (version===ccVersionComercial && $("#ccComercial").length) renderCotizadorCosteo();
        Swal.fire({icon:"success",title:r.answer,timer:1800,showConfirmButton:false});
    }).fail(xhr=>Swal.fire("No se pudo guardar",errorApi(xhr),"error")).always(()=>{ccPublicando=false; $boton.prop("disabled",false); if($("#cscPrecioForm").length)ccActualizarPrecio();});
}

function bindComercialCosteo() {
    $(document).on("click",".csc-tab",function(){renderCotizadorCosteo(this.dataset.vista);});
    $(document).on("click",".csc-recargar",function(){renderCotizadorCosteo();});
    $(document).on("hidden.bs.modal","#ccModalCatalogos",function(){if($("#ccComercial").length) renderCotizadorCosteo();});
    $(document).on("change","#cspMaterial",function(){ccPresentaciones("csp");ccRecargoSugerido();});
    $(document).on("change","#cspLista",ccRecargoSugerido);
    $(document).on("input change","#cscPrecioForm :input",ccActualizarPrecio);
    $(document).on("input","#cscBuscarPrecios",()=>ccTablaComercial("precios"));
    $(document).on("input","#cscBuscarFletes",()=>ccTablaComercial("fletes"));
    $(document).on("submit","#cscPrecioForm",function(e){e.preventDefault();if(!this.reportValidity())return;ccPublicar("publicar_precio",{material_id:$("#cspMaterial").val(),presentacion_id:$("#cspPresentacion").val(),lista_id:$("#cspLista").val(),destino_id:$("#cspDestino").val(),recargo_pct:$("#cspRecargo").val(),adicional:$("#cspAdicional").val(),precio_manual:$("#cspManual").val(),observaciones:$("#cspObs").val()},$(this).find("button[type=submit]"));});
    $(document).on("click","#cspGenerar",function(){
        const m=activos("materiales").find(r=>String(r.id)===$("#cspMaterial").val()); if(!m)return;
        const $b=$(this);
        if(m.grupo_flete==="CEMENTO") Swal.fire({title:"Peso del bulto",input:"select",inputOptions:{25:"25 kg",50:"50 kg"},showCancelButton:true,inputValidator:v=>!v?"Selecciona el peso":undefined}).then(r=>{if(r.isConfirmed)ccPublicar("generar_presentaciones",{material_id:m.id,kg_bulto:r.value},$b);});
        else ccPublicar("generar_presentaciones",{material_id:m.id},$b);
    });
    $(document).on("change","#csfGrupo",ccOpcionesFlete);
    $(document).on("click","#csfAgregar",()=>ccAgregarConcepto());
    $(document).on("click",".csf-quitar",function(){if($("#csfConceptos .csc-concepto").length>1)$(this).closest(".csc-concepto").remove();ccTotalFlete();});
    $(document).on("input","#csfConceptos input",ccTotalFlete);
    $(document).on("submit","#cscFleteForm",function(e){e.preventDefault();if(!this.reportValidity())return;const conceptos=[];$("#csfConceptos .csc-concepto").each(function(){conceptos.push({concepto:$(this).find(".csf-nombre").val(),cantidad:$(this).find(".csf-cantidad").val(),precio:$(this).find(".csf-precio").val()});});ccPublicar("publicar_flete",{destino_id:$("#csfDestino").val(),grupo_flete:$("#csfGrupo").val(),presentacion:$("#csfPresentacion").val(),unidad_cobro:$("#csfUnidad").val(),observaciones:$("#csfObs").val(),conceptos},$(this).find("button[type=submit]"));});
    $(document).on("click",".csc-historial",function(){
        const tipo=this.dataset.tipo, id=this.dataset.id;
        ccComercialApi("historial_"+tipo,{id}).done(function(r){if(r.status!=="ok")return Swal.fire("Aviso",r.answer,"warning");
            const $tabla=$("<div>",{class:"table-responsive"}).append($("<table>",{class:"table table-sm text-start"}).append('<thead><tr><th>Vigencia</th><th>Importe</th><th>Estado</th><th>Observaciones</th></tr></thead>'));
            const $body=$("<tbody>").appendTo($tabla.find("table"));
            (r.results||[]).forEach(v=>$body.append($("<tr>").append($("<td>",{text:v.fecha_vigencia}),$("<td>",{text:mxn(tipo==="precio"?v.precio:v.importe)}),$("<td>",{text:Number(v.eliminado)?"Eliminado":Number(v.activo)?"Registrado":"Inactivo"}),$("<td>",{text:v.observaciones||"—"}))));
            Swal.fire({title:"Historial de "+tipo,html:$tabla[0],width:800,confirmButtonText:"Cerrar",confirmButtonColor:"#086b47"});
        }).fail(xhr=>Swal.fire("Error",errorApi(xhr),"error"));
    });
    $(document).on("change","#csqMaterial",function(){ccPresentaciones("csq");ccCambiarPresentacionCotizada();});
    $(document).on("change","#csqPresentacion",ccCambiarPresentacionCotizada);
    $(document).on("change","#csqMezcla",function(){const activo=this.checked,p=ccPresentacionCotizada();$("#csqComponentesWrap").toggleClass("d-none",!activo);$("#csqComponentes").empty();if(activo && p){const mitad=Number(p.factor_numerador)/Number(p.factor_denominador)/2;ccAgregarComponente($("#csqMaterial").val(),mitad.toFixed(2));ccAgregarComponente("",mitad.toFixed(2));}ccProgramarCotizacion();});
    $(document).on("click","#csqAgregar",function(){ccAgregarComponente();ccProgramarCotizacion();});
    $(document).on("click",".csq-quitar",function(){if($("#csqComponentes .csc-componente").length>2)$(this).closest(".csc-componente").remove();ccCapacidad();ccProgramarCotizacion();});
    $(document).on("input change","#csqCantidad,#csqLista,#csqDestino,#csqVehiculo,#csqComponentes :input",function(){ccCapacidad();ccProgramarCotizacion();});
    $(document).on("submit","#cscCotizarForm",function(e){e.preventDefault();ccProgramarCotizacion(true);});
}


function verMapaDestino(indice) {
    const registro = datos.destinos[indice];
    if (!registro) return;

    const posicion = coordenadasDestino(registro);

    if (!posicion) {
        Swal.fire({
            icon: "info",
            title: "Ubicación pendiente",
            text: "Este destino todavía no tiene coordenadas. Edita el registro para seleccionar su ubicación."
        });
        return;
    }

    if (document.getElementById("ccModalMapa")) return;

    $("body").append(`
        <div class="modal fade" id="ccModalMapa" tabindex="-1"
             aria-labelledby="ccTituloMapaDestino" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-bold mb-1" id="ccTituloMapaDestino">
                                Ubicación del destino
                            </h2>
                            <p class="text-secondary small mb-0" id="ccNombreMapaDestino"></p>
                        </div>
                        <button type="button" class="btn-close"
                                data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="ccMapaLectura" class="cc-mapa cc-mapa-consulta"></div>
                        <div class="cc-mapa-datos mt-3">
                            <strong>Dirección registrada</strong>
                            <p class="mb-2" id="ccDireccionMapaDestino"></p>
                            <strong>Coordenadas</strong>
                            <p class="mb-0" id="ccCoordenadasMapaDestino"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">Cerrar</button>
                        <a id="ccAbrirGoogleMaps" class="btn btn-success"
                           href="#" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-box-arrow-up-right me-2"></i>Ver en Google Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `);

    const direccion = [
        registro.calle,
        registro.numero_exterior,
        registro.numero_interior ? "Int. " + registro.numero_interior : "",
        registro.colonia,
        registro.municipio,
        registro.estado,
        registro.codigo_postal
    ].filter(Boolean).join(", ");

    $("#ccNombreMapaDestino").text(registro.nombre);
    $("#ccDireccionMapaDestino").text(direccion || "Sin dirección detallada.");
    $("#ccCoordenadasMapaDestino").text(
        posicion[0].toFixed(7) + ", " + posicion[1].toFixed(7)
    );

    $("#ccAbrirGoogleMaps").attr(
        "href",
        "https://www.google.com/maps/search/?api=1&query=" +
        encodeURIComponent(posicion[0] + "," + posicion[1])
    );

    const elemento = document.getElementById("ccModalMapa");
    const catalogos = document.getElementById("ccModalCatalogos");
    const modal = bootstrap.Modal.getOrCreateInstance(elemento);

    $(elemento).one("shown.bs.modal", function() {
        try {
            mapaDestinoLectura = crearMapaCosteo(
                "ccMapaLectura", posicion, 16
            );

            L.marker(posicion, {
                icon: iconoDestinoCosteo()
            }).addTo(mapaDestinoLectura);
        } catch (error) {
            Swal.fire("Error de mapa", error.message, "error");
        }
    });

    $(elemento).on("hide.bs.modal", function() {
        if (this.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    $(elemento).one("hidden.bs.modal", function() {
        if (mapaDestinoLectura) {
            mapaDestinoLectura.remove();
            mapaDestinoLectura = null;
        }

        modal.dispose();
        elemento.remove();

        if (catalogos && document.body.contains(catalogos)) {
            bootstrap.Modal.getOrCreateInstance(catalogos).show();
        }
    });

    if (catalogos && catalogos.classList.contains("show")) {
        $(catalogos).one("hidden.bs.modal", function() {
            if (elemento.isConnected) modal.show();
        });

        bootstrap.Modal.getOrCreateInstance(catalogos).hide();
    } else {
        modal.show();
    }
}

function bindCosteo() {
    bindComercialCosteo();

    $(document).on("click", ".cc-tab", function() {
        elegirCatalogo(this.dataset.tipo);
    });

    $(document).on("input", "#ccBuscar", mostrarTablaCosteo);

    $(document).on("click", "#ccNuevo", function() {
        abrirFormularioCosteo();
    });

    $(document).on("click", ".cc-editar", function() {
        abrirFormularioCosteo(Number(this.dataset.indice));
    });

    $(document).on("click", ".cc-estado", function() {
    cambiarEstadoCosteo(Number(this.dataset.indice));
});

    $(document).on("submit", "#ccFormulario", guardarFormularioCosteo);
    $(document).on("change", "#ccVehiculo", actualizarCondiciones);

    $(document).on("input change", "#ccCotizador :input", function() {
        cotizacionVigente = null;
        $("#ccGuardarCotizacion").prop("disabled", true);
        $("#ccResultado").html(
            '<p class="text-secondary">Los datos cambiaron. Calcula nuevamente para actualizar el precio.</p>'
        );
    });

    $(document).on("submit", "#ccCotizador", calcularCotizacionCosteo);
    $(document).on("click", "#ccGuardarCotizacion", guardarCotizacionCosteo);

        $(document).on("click", ".cc-ver-mapa", function() {
        verMapaDestino(Number(this.dataset.indice));
    });

    $(document).on("change", "#ccf_latitud, #ccf_longitud", function() {
        const posicion = coordenadasDestino({
            latitud: $("#ccf_latitud").val(),
            longitud: $("#ccf_longitud").val()
        });

        if (posicion && mapaDestinoFormulario) {
            colocarPinDestino(posicion[0], posicion[1]);
        }
    });

    $(document).on("click", "#ccQuitarPinDestino", function() {
        $("#ccf_latitud, #ccf_longitud").val("");

        if (marcadorDestinoFormulario && mapaDestinoFormulario) {
            mapaDestinoFormulario.removeLayer(marcadorDestinoFormulario);
            marcadorDestinoFormulario = null;
        }
    });

    $(document).on("click", "#ccUsarGPS", function() {
        if (!window.isSecureContext || !navigator.geolocation) {
            Swal.fire(
                "Ubicación no disponible",
                "Esta función requiere HTTPS, un navegador compatible y permiso de ubicación.",
                "warning"
            );
            return;
        }

        const $boton = $(this).prop("disabled", true);

        navigator.geolocation.getCurrentPosition(
            function(posicion) {
                const lat = posicion.coords.latitude;
                const lng = posicion.coords.longitude;

                colocarPinDestino(lat, lng);

                if (mapaDestinoFormulario) {
                    mapaDestinoFormulario.setView([lat, lng], 17);
                }

                $boton.prop("disabled", false);
            },
            function(error) {
                $boton.prop("disabled", false);

                Swal.fire(
                    "No se pudo obtener la ubicación",
                    error.code === 1
                        ? "No se concedió permiso para acceder a la ubicación."
                        : "Verifica la ubicación del dispositivo e inténtalo nuevamente.",
                    "warning"
                );
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    });

        $(document).on("input", "#ccBuscarLugar", function() {
        const texto = this.value.trim();
        const folio = ++folioLugar;

        clearTimeout(temporizadorLugar);

        if (solicitudLugar) {
            solicitudLugar.abort();
            solicitudLugar = null;
        }

        $("#ccSugerenciasLugar").empty().addClass("d-none");
        $(this).attr("aria-expanded", "false");

        if (texto.length < 3) return;

        temporizadorLugar = setTimeout(function() {
            if (folio !== folioLugar) return;
            buscarLugaresCosteo(texto, folio);
        }, 550);
    });

    $(document).on("click", ".cc-sugerencia-lugar", function() {
        const lugar = $(this).data("lugar");
        if (!lugar) return;

        const lat = Number(lugar.lat);
        const lng = Number(lugar.lon);

        if (!Number.isFinite(lat) || !Number.isFinite(lng) ||
            lat < -90 || lat > 90 || lng < -180 || lng > 180) return;

        const direccion = lugar.address || {};
        const nombre = lugar.display_place || lugar.display_name || "";

        //$("#ccf_nombre").val(nombre);
        $("#ccf_nombre").val(nombre.slice(0, 150));

        const camposDireccion = {
            calle: direccion.road || direccion.pedestrian || "",
            numero_exterior: direccion.house_number || "",
            numero_interior: "",
            colonia: direccion.suburb || direccion.neighbourhood ||
                direccion.quarter || "",
            municipio: direccion.city || direccion.town ||
                direccion.village || direccion.municipality ||
                direccion.county || "",
            estado: direccion.state || "",
            codigo_postal: direccion.postcode || ""
        };

        Object.entries(camposDireccion).forEach(function([campo, valor]) {
            $("#ccf_" + campo).val(valor);
        });

        $("#ccBuscarLugar").val(nombre);
        $("#ccSugerenciasLugar").empty().addClass("d-none");
        $("#ccBuscarLugar").attr("aria-expanded", "false");

        folioLugar++;
        clearTimeout(temporizadorLugar);

        colocarPinDestino(lat, lng);

        if (mapaDestinoFormulario) {
            mapaDestinoFormulario.setView([lat, lng], 17);
        }
    });

    $(document).on("click", ".cc-sugerencia-libre", function() {
        const nombre = $("#ccBuscarLugar").val().trim();

        if (!nombre) return;

        //$("#ccf_nombre").val(nombre);
        $("#ccf_nombre").val(nombre.slice(0, 150));
        $("#ccSugerenciasLugar").empty().addClass("d-none");
        $("#ccBuscarLugar").attr("aria-expanded", "false");

        folioLugar++;
        clearTimeout(temporizadorLugar);
    });

    $(document).on("keydown", "#ccBuscarLugar", function(e) {
        const $opciones = $("#ccSugerenciasLugar button:visible");

        if (e.key === "Escape") {
            $("#ccSugerenciasLugar").addClass("d-none");
            $(this).attr("aria-expanded", "false");
        }

        if (e.key === "ArrowDown" && $opciones.length) {
            e.preventDefault();
            $opciones.first().trigger("focus");
        }

        if (e.key === "Enter") {
            e.preventDefault();

            if ($("#ccSugerenciasLugar").hasClass("d-none")) {
                $("#ccf_nombre").val(this.value.trim());
            } else if ($opciones.length) {
                $opciones.first().trigger("click");
            }
        }
    });

    $(document).on("keydown", ".cc-sugerencias-lugar button", function(e) {
        const $opciones = $("#ccSugerenciasLugar button:visible");
        const indice = $opciones.index(this);

        if (e.key === "ArrowDown") {
            e.preventDefault();
            $opciones.eq(Math.min(indice + 1, $opciones.length - 1)).trigger("focus");
        } else if (e.key === "ArrowUp") {
            e.preventDefault();

            if (indice === 0) {
                $("#ccBuscarLugar").trigger("focus");
            } else {
                $opciones.eq(indice - 1).trigger("focus");
            }
        } else if (e.key === "Escape") {
            e.preventDefault();
            $("#ccSugerenciasLugar").addClass("d-none");
            $("#ccBuscarLugar").attr("aria-expanded", "false").trigger("focus");
        }
    });

    $(document).on("click", function(e) {
        if (!$(e.target).closest("#ccBuscadorLugar").length) {
            $("#ccSugerenciasLugar").addClass("d-none");
            $("#ccBuscarLugar").attr("aria-expanded", "false");
        }
    });
}

function buscarLugaresCosteo(texto, folio) {
    const $lista = $("#ccSugerenciasLugar").empty().removeClass("d-none");
    $("#ccBuscarLugar").attr("aria-expanded", "true");

    if (!CLAVE_LOCATIONIQ.trim() || CLAVE_LOCATIONIQ === "") {
        $lista.append($("<div>", {
            class: "cc-sugerencia-aviso",
            text: "Configura la clave de LocationIQ para activar las sugerencias."
        }));
        return;
    }

    $lista.append($("<div>", {
        class: "cc-sugerencia-aviso",
        text: "Buscando lugares..."
    }));

    solicitudLugar = $.ajax({
        url: "https://api.locationiq.com/v1/autocomplete",
        method: "GET",
        dataType: "json",
        timeout: 10000,
        data: {
            key: CLAVE_LOCATIONIQ.trim(),
            q: texto,
            countrycodes: "mx",
            "accept-language": "es",
            limit: 6
        }
    }).done(function(resultados) {
        if (folio !== folioLugar || !$("#ccBuscarLugar").length) return;

        $lista.empty();

        if (!Array.isArray(resultados) || !resultados.length) {
            $lista.append($("<div>", {
                class: "cc-sugerencia-aviso",
                text: "No se encontraron coincidencias."
            }));
        } else {
            resultados.forEach(function(lugar) {
                const nombre = lugar.display_place || lugar.display_name || "Lugar";
                const direccion = lugar.display_address || lugar.display_name || "";

                const $opcion = $("<button>", {
                    type: "button",
                    class: "cc-sugerencia-lugar",
                    role: "option"
                });

                $opcion.append(
                    $("<span>", {class: "cc-sugerencia-icono"}).append(
                        $("<i>", {class: "bi bi-geo-alt"})
                    ),
                    $("<span>", {class: "cc-sugerencia-info"}).append(
                        $("<strong>", {text: nombre}),
                        $("<small>", {text: direccion})
                    ),
                    $("<i>", {
                        class: "bi bi-arrow-up-left cc-sugerencia-flecha",
                        "aria-hidden": "true"
                    })
                );

                $opcion.data("lugar", lugar).appendTo($lista);
            });
        }

        $("<button>", {
            type: "button",
            class: "cc-sugerencia-libre",
            text: 'Usar "' + texto + '" como nombre manual'
        }).appendTo($lista);

    }).fail(function(xhr, estado) {
        if (estado === "abort" || folio !== folioLugar) return;

        $lista.empty().append($("<div>", {
            class: "cc-sugerencia-aviso",
            text: xhr.status === 429
                ? "Se alcanzó el límite de búsquedas. Inténtalo después."
                : "No se pudieron consultar lugares. Puedes capturarlo manualmente."
        }));

        $("<button>", {
            type: "button",
            class: "cc-sugerencia-libre",
            text: 'Usar "' + texto + '" como nombre manual'
        }).appendTo($lista);
    });
}