

// CONFIGURACIÓN DE MÓDULOS
const modulos = {
    ventas: {
        nombre: "Ventas",
        items: [
            {id:"nota",nombre:"Ventas con nota",icono:"receipt",descripcion:"Registra notas foleadas, clientes, artículos y formas de pago."},
            {id:"rapida",nombre:"Venta rápida",icono:"cart3",descripcion:"Registra ventas de mostrador sin datos de cliente."}
        ]
    },
    caja: {
        nombre: "Caja y egresos",
        items: [
            {id:"arqueo",nombre:"Control de caja",icono:"wallet2",descripcion:"Apertura, movimientos de efectivo y cierre de caja."},
            {id:"egresos",nombre:"Gastos y salidas",icono:"arrow-up-right-circle",descripcion:"Registra gastos y pagos en efectivo o transferencia."}
        ]
    },
    cuentas: {
        nombre: "Cuentas",
        items: [
            {id:"cobrar",nombre:"Cuentas por cobrar",icono:"person-check",descripcion:"Consulta créditos, abonos y saldos pendientes."},
            {id:"pagar",nombre:"Cuentas por pagar",icono:"credit-card",descripcion:"Control de compromisos y pagos a proveedores."}
        ]
    },
    panel: {
        nombre: "Panel y reportes",
        items: [
            {id:"resumen",nombre:"Resumen general",icono:"grid-1x2",descripcion:"Indicadores de ingresos, egresos y movimientos recientes."},
            {id:"reportes",nombre:"Reportes",icono:"bar-chart",descripcion:"Reportes de ventas, egresos, clientes y caja."}
        ]
    }
};

const state = {
    moduloActual: null,
    submoduloActual: null,
    versionVista: 0
};

// INICIALIZACIÓN
window.addEventListener("DOMContentLoaded", function() {
    bindNavigation();
    mostrarInicio();
});

// EVENTOS
function bindNavigation() {
    $(document).on("click", ".js-abrir-modulo", function() {
        abrirModulo($(this).data("modulo"));
    });

    $(document).on("click", ".tab-modulo", function() {
        mostrarSubmodulo($(this).data("submodulo"));
    });

    $("#btnInicio").on("click", mostrarInicio);
    $("#btnConfiguracion").on("click", mostrarConfiguracion);
}

// NAVEGACIÓN
function mostrarInicio() {
    state.moduloActual = null;
    state.submoduloActual = null;
    state.versionVista++;

    $("#vistaInicio").removeClass("d-none");
    $("#vistaModulo").addClass("d-none");
    $("#marcaSistema").removeClass("d-none");
    $("#navSubmodulos").addClass("d-none").empty();
    window.scrollTo(0, 0);
}

function abrirModulo(id) {
    if (!Object.prototype.hasOwnProperty.call(modulos, id)) return;

    state.moduloActual = id;
    $("#vistaInicio").addClass("d-none");
    $("#vistaModulo").removeClass("d-none");
    $("#marcaSistema").addClass("d-none");

    const $nav = $("#navSubmodulos").empty().removeClass("d-none");

    modulos[id].items.forEach(function(item) {
        $("<button>", {type:"button",class:"tab-modulo",text:item.nombre})
            .attr("data-submodulo", item.id).appendTo($nav);
    });

    mostrarSubmodulo(modulos[id].items[0].id);
    window.scrollTo(0, 0);
}

function mostrarSubmodulo(id) {
    if (!state.moduloActual || !modulos[state.moduloActual]) return;

    const item = modulos[state.moduloActual].items.find(function(x) {
        return x.id === id;
    });
    if (!item) return;

    state.submoduloActual = id;
    state.versionVista++;

    $("#categoriaModulo").text(modulos[state.moduloActual].nombre.toUpperCase());
    $("#tituloPantalla").text(item.nombre);
    $("#descripcionPantalla").text(item.descripcion);
    $("#iconoPantalla").attr("class", "bi bi-" + item.icono);

    $(".tab-modulo").removeClass("active").removeAttr("aria-current");
    $(".tab-modulo").filter(function() {
        return $(this).data("submodulo") === id;
    }).addClass("active").attr("aria-current", "page");

    cargarContenidoModulo(state.moduloActual, id, item);
}

function mostrarConfiguracion() {
    state.moduloActual = null;
    state.submoduloActual = null;
    state.versionVista++;

    $("#vistaInicio").addClass("d-none");
    $("#vistaModulo").removeClass("d-none");
    $("#marcaSistema").removeClass("d-none");
    $("#navSubmodulos").addClass("d-none").empty();
    $("#categoriaModulo").text("SISTEMA");
    $("#tituloPantalla").text("Configuración");
    $("#descripcionPantalla").text("Datos del negocio y preferencias generales.");
    $("#iconoPantalla").attr("class", "bi bi-gear");

    mostrarContenidoPendiente("Configuración");
    window.scrollTo(0, 0);
}

// CONTENIDO DE MÓDULOS
function cargarContenidoModulo(modulo, submodulo, item) {
    if (modulo === "panel" && submodulo === "resumen") {
        getResumenInicial();
        return;
    }

    mostrarContenidoPendiente(item.nombre);
}

function mostrarContenidoPendiente(nombre) {
    $("#contenidoModulo").html('<div class="estado-vacio"><i class="bi bi-ui-checks-grid"></i><h2 class="h5 mt-3">Área lista para desarrollar</h2><p class="texto-secundario mb-0"></p></div>');
    $("#contenidoModulo .estado-vacio p").text("Aquí construiremos " + nombre.toLowerCase() + ".");
}

// PANEL GENERAL - MYSQL
function getResumenInicial() {
    const version = state.versionVista;

    $("#contenidoModulo").html('<div class="text-center py-5"><div class="spinner-border text-success"></div><p class="text-secondary mt-3">Consultando información...</p></div>');

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: {action:"getResumenInicial"},
        dataType: "json"
    }).done(function(data) {
        if (version !== state.versionVista) return;

        if (!data || data.status !== "ok") {
            mostrarErrorConsulta(data ? data.answer : "No fue posible consultar la información.");
            return;
        }

        renderResumenInicial(Array.isArray(data.results) ? data.results : []);

    }).fail(function(xhr) {
        if (version !== state.versionVista) return;
        const respuesta = xhr.responseJSON || {};
        mostrarErrorConsulta(respuesta.answer || "No fue posible consultar la base de datos.");
    });
}

function renderResumenInicial(tablas) {
    const indicadores = [
        {tabla:"sales",titulo:"Ventas registradas",icono:"bi-receipt"},
        {tabla:"cash_flows",titulo:"Movimientos de caja",icono:"bi-wallet2"},
        {tabla:"receivables",titulo:"Registros por cobrar",icono:"bi-person-check"},
        {tabla:"payables",titulo:"Registros por pagar",icono:"bi-credit-card"}
    ];

    const $contenedor = $('<div class="row g-3"></div>');

    indicadores.forEach(function(indicador) {
        const tabla = tablas.find(function(t) {return t.nombre === indicador.tabla;});
        const total = tabla && tabla.existe ? tabla.total : "—";
        const $col = $('<div class="col-sm-6 col-xl-3"></div>');
        const $card = $('<div class="border rounded-4 p-4 bg-white h-100"></div>');

        $card.append($('<i>').addClass('bi ' + indicador.icono + ' fs-3 text-success'));
        $card.append($('<p class="small text-secondary mt-3 mb-1"></p>').text(indicador.titulo));
        $card.append($('<h3 class="fw-bold mb-0"></h3>').text(total));
        $col.append($card);
        $contenedor.append($col);
    });

    const $detalle = $('<div class="mt-4"></div>');
    $detalle.append('<h2 class="h6 fw-bold mb-3">Estructura de las tablas existentes</h2>');

    tablas.forEach(function(tabla) {
        const $details = $('<details class="border rounded-3 p-3 mb-2"></details>');
        const estado = tabla.existe ? tabla.motor + " · " + tabla.total + " registros" : "No encontrada";

        $details.append($('<summary class="fw-semibold"></summary>').text(tabla.nombre + " — " + estado));

        if (tabla.existe) {
            const columnas = (tabla.columnas || []).map(function(c) {
                return c.nombre + " (" + c.tipo + ")";
            }).join("\n");
            $details.append($('<pre class="small mt-3 mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>').text(columnas));
        }
        $detalle.append($details);
    });

    $("#contenidoModulo").empty()
        .append('<p class="text-secondary small mb-3">Cantidad de registros existentes. Estos indicadores todavía no representan importes monetarios.</p>')
        .append($contenedor, $detalle);
}

function mostrarErrorConsulta(mensaje) {
    $("#contenidoModulo").html('<div class="alert alert-danger mb-0">No fue posible cargar la información.</div>');
    Swal.fire({icon:"error",title:"Error de consulta",text:mensaje,confirmButtonColor:"#086b47"});
}
