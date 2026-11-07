

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


$(document).on("submit", "#formFiltroResumen", function(e) {
    e.preventDefault();
    const inicio = $("#resumenFechaInicio").val();
    const fin = $("#resumenFechaFin").val();
    if (!inicio || !fin) return;
    getResumenInicial(inicio, fin);
});

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

function getResumenInicial(inicio = "", fin = "") {
    const version = state.versionVista;
    const parametros = {action:"getResumenInicial"};
    if (inicio && fin) {
        parametros.fecha_inicio = inicio;
        parametros.fecha_fin = fin;
    }

    if (!$("#formFiltroResumen").length) {
        $("#contenidoModulo").html('<div class="text-center py-5"><div class="spinner-border text-success"></div><p class="text-secondary mt-3">Cargando resumen...</p></div>');
    } else {
        $("#btnFiltrarResumen").prop("disabled", true).text("Consultando...");
    }

    $.ajax({
        type:"POST",
        url:"php/index.php",
        data:parametros,
        dataType:"json"
    }).done(function(data) {
        if (version !== state.versionVista) return;
        if (!data || data.status !== "ok") {
    mostrarErrorConsulta(data && data.answer ? data.answer : "No fue posible consultar el resumen.");
    return;
}
        renderResumenInicial(data.results);
    }).fail(function(xhr) {
        if (version !== state.versionVista) return;
        const respuesta = xhr.responseJSON || {};
        Swal.fire("Error",respuesta.answer || "No fue posible conectar con PHP.","error");
    }).always(function() {
        if (version === state.versionVista) $("#btnFiltrarResumen").prop("disabled",false).text("Consultar");
    });
}

function monedaMXN(valor) {
    return new Intl.NumberFormat("es-MX",{style:"currency",currency:"MXN"}).format(Number(valor) || 0);
}

function renderResumenInicial(datos) {
    const html = `
        <form id="formFiltroResumen" class="d-flex flex-wrap gap-3 align-items-end mb-4">
            <div><label for="resumenFechaInicio" class="form-label small fw-semibold">Desde</label><input type="date" id="resumenFechaInicio" class="form-control" required></div>
            <div><label for="resumenFechaFin" class="form-label small fw-semibold">Hasta</label><input type="date" id="resumenFechaFin" class="form-control" required></div>
            <button type="submit" id="btnFiltrarResumen" class="btn btn-success"><i class="bi bi-search me-2"></i>Consultar</button>
        </form>
        <div class="row g-3 mb-4" id="tarjetasResumen"></div>
        <div class="row g-3">
            <div class="col-xl-6"><div class="border rounded-4 p-3 p-md-4 h-100"><h3 class="h6 fw-bold mb-3">Ventas recientes</h3><div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th></tr></thead><tbody id="tablaVentasResumen"></tbody></table></div></div></div>
            <div class="col-xl-6"><div class="border rounded-4 p-3 p-md-4 h-100"><h3 class="h6 fw-bold mb-3">Movimientos contables</h3><div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Estado</th><th class="text-end">Monto</th></tr></thead><tbody id="tablaMovimientosResumen"></tbody></table></div></div></div>
        </div>
        <p class="text-secondary small mt-3 mb-0">Las ventas representan importes registrados, no necesariamente cobrados. Los saldos de caja y cuentas son actuales. Las salidas provienen de movimientos contables marcados como pagados.</p>
    `;
    $("#contenidoModulo").html(html);
    $("#resumenFechaInicio").val(datos.periodo.inicio);
    $("#resumenFechaFin").val(datos.periodo.fin);

    const tarjetas = [
        {titulo:"Ventas del periodo",valor:datos.ventas.importe,detalle:datos.ventas.cantidad+" ventas registradas",icono:"bi-receipt"},
        {titulo:"Saldo registrado en cajas",valor:datos.caja.saldo,detalle:datos.caja.cantidad+" cajas activas",icono:"bi-wallet2"},
        {titulo:"Salidas pagadas del periodo",valor:datos.salidas.importe,detalle:datos.salidas.cantidad+" movimientos",icono:"bi-arrow-up-right"},
        {titulo:"Cuentas por cobrar",valor:datos.cobrar.saldo,detalle:datos.cobrar.cantidad+" cuentas pendientes",icono:"bi-person-check"},
        {titulo:"Cuentas por pagar",valor:datos.pagar.saldo,detalle:datos.pagar.cantidad+" cuentas pendientes",icono:"bi-credit-card"}
    ];

    tarjetas.forEach(function(item) {
        const $col = $('<div class="col-sm-6 col-xl-4"></div>');
        const $card = $('<div class="border bg-white rounded-4 p-4 h-100"></div>');
        $card.append($('<i>').addClass('bi '+item.icono+' fs-3 text-success'));
        $card.append($('<p class="text-secondary small mt-3 mb-1"></p>').text(item.titulo));
        $card.append($('<h3 class="h4 fw-bold mb-1"></h3>').text(monedaMXN(item.valor)));
        $card.append($('<small class="text-secondary"></small>').text(item.detalle));
        $("#tarjetasResumen").append($col.append($card));
    });

    if (!datos.ultimas_ventas.length) {
        $("#tablaVentasResumen").append('<tr><td colspan="3" class="text-center text-secondary py-4">Sin ventas en este periodo</td></tr>');
    }
    datos.ultimas_ventas.forEach(function(venta) {
        const $fila = $("<tr></tr>");
        $fila.append($("<td></td>").text(venta.created_at));
        $fila.append($("<td></td>").text(venta.client_name || "Mostrador"));
        $fila.append($('<td class="text-end fw-semibold"></td>').text(monedaMXN(venta.total_amount)));
        $("#tablaVentasResumen").append($fila);
    });

    if (!datos.movimientos.length) {
        $("#tablaMovimientosResumen").append('<tr><td colspan="4" class="text-center text-secondary py-4">Sin movimientos en este periodo</td></tr>');
    }
    datos.movimientos.forEach(function(movimiento) {
        const $fila = $("<tr></tr>");
        $fila.append($("<td></td>").text(movimiento.date));
        $fila.append($("<td></td>").text(movimiento.type+" · "+movimiento.origin));
        $fila.append($("<td></td>").text(movimiento.status));
        $fila.append($('<td class="text-end fw-semibold"></td>').text(monedaMXN(movimiento.amount)));
        $("#tablaMovimientosResumen").append($fila);
    });
}



function mostrarErrorConsulta(mensaje) {
    $("#contenidoModulo").html('<div class="alert alert-danger mb-0">No fue posible cargar la información.</div>');
    Swal.fire({icon:"error",title:"Error de consulta",text:mensaje,confirmButtonColor:"#086b47"});
}
