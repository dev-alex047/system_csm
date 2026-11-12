

// CONFIGURACIÓN DE MÓDULOS
const modulos = {
   
ventas: {
    nombre: "Ventas",
    items: [
        {id:"listado",nombre:"Listado",icono:"list-ul",descripcion:"Consulta tus ventas registradas, notas y comprobantes."},
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
state.ventaNota = crearBorradorVentaNota();
state.ventaRapida = crearBorradorVentaRapida();
state.filtrosVentas = {buscar:"",tipo:"TODAS",inicio:"",fin:""};
state.peticionVentas = 0;

// INICIALIZACIÓN
window.addEventListener("DOMContentLoaded", function() {
    bindNavigation();
    bindVentaNota();
    bindVentaRapida();
    bindCajaEgresos();
    bindCuentas();
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


$(document).on("submit", "#formBuscarVentas", function(e) {
    e.preventDefault();
    const buscar = $("#ventasBuscar").val().trim();
    const tipo = $("#ventasTipo").val();
    const inicio = $("#ventasDesde").val();
    const fin = $("#ventasHasta").val();

    if (inicio && fin && inicio > fin) {
        return Swal.fire("Aviso", "La fecha inicial no puede ser mayor que la final.", "warning");
    }

    state.filtrosVentas = {buscar:buscar,tipo:tipo,inicio:inicio,fin:fin};
    consultarListadoVentas(1);
});

$(document).on("click", "#btnLimpiarFiltrosVentas", function() {
    state.filtrosVentas = {buscar:"",tipo:"TODAS",inicio:"",fin:""};
    $("#formBuscarVentas")[0].reset();
    consultarListadoVentas(1);
});

$(document).on("click", ".js-pagina-ventas", function() {
    consultarListadoVentas(Number($(this).attr("data-pagina")));
});

$(document).on("click", ".js-nueva-venta", function() {
    const tipo = $(this).attr("data-tipo");
    if (tipo === "nota" || tipo === "rapida") mostrarSubmodulo(tipo);
});

$(document).on("click", "#btnVolverListadoVentas", function() {
    mostrarSubmodulo("listado");
});

$(document).on("click", ".js-pdf-venta", function() {
    const id = Number($(this).attr("data-id"));
    if (Number.isInteger(id) && id > 0) generarPdfNotaVenta(id);
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
    if (modulo === "ventas" && submodulo === "listado") {
    renderListadoVentas();
    return;
}
    if (modulo === "ventas" && submodulo === "nota") {
    renderVentaConNota();
    return;
}

if (modulo === "ventas" && submodulo === "rapida") {
    renderVentaRapida();
    return;
}
if (modulo === "caja" && submodulo === "arqueo") { renderControlCaja(); return; }
if (modulo === "caja" && submodulo === "egresos") { renderEgresos(); return; }
    if (modulo === "panel" && submodulo === "resumen") {
        getResumenInicial();
        return;
    }


if (modulo === "cuentas" && submodulo === "cobrar") { renderCuentas("COBRAR"); return; }
if (modulo === "cuentas" && submodulo === "pagar") { renderCuentas("PAGAR"); return; }


    mostrarContenidoPendiente(item.nombre);
}


//if (modulo === "cuentas" && submodulo === "cobrar") { renderCuentas("COBRAR"); return; }
//if (modulo === "cuentas" && submodulo === "pagar") { renderCuentas("PAGAR"); return; }



/* VENTAS CON NOTA */
function crearBorradorVentaNota() {
    
    return {solicitud_id: generarIdSolicitudVenta(),
caja_id: "",
        folio:"",cliente:"",direccion:"",telefono:"",vendedor:"",observaciones:"",forma_pago:"EFECTIVO",referencia:"",abono:"",metodo_abono:"EFECTIVO",referencia_abono:"",items:[],siguienteId:1};
}

function bindVentaNota() {
    $(document).on("click","#btnAgregarItemNota",function(){agregarItemVentaNota();});

    $(document).on("click",".btnEliminarItemNota",function(){
        const id = Number($(this).closest("tr").attr("data-item-id"));
        state.ventaNota.items = state.ventaNota.items.filter(function(item){return item.id !== id;});
        $(this).closest("tr").remove();
        if (!state.ventaNota.items.length) agregarItemVentaNota();
        actualizarTotalesVentaNota();
    });

    $(document).on("input change","#formVentaNota [data-campo-nota]",function(){
        const campo = $(this).attr("data-campo-nota");
        state.ventaNota[campo] = this.value;
        $(this).removeClass("is-invalid");
        if (campo === "forma_pago" || campo === "metodo_abono") alternarCamposPagoNota();
        actualizarTotalesVentaNota();
    });

    $(document).on("input","#detalleVentaNota [data-item-campo]",function(){
        const id = Number($(this).closest("tr").attr("data-item-id"));
        const item = state.ventaNota.items.find(function(fila){return fila.id === id;});
        if (!item) return;
        item[$(this).attr("data-item-campo")] = this.value;
        $(this).removeClass("is-invalid");
        actualizarTotalesVentaNota();
    });

    $(document).on("submit","#formVentaNota",function(e){
        e.preventDefault();
        revisarVentaNota();
    });

    $(document).on("click","#btnLimpiarVentaNota",function(){
        Swal.fire({title:"¿Descartar este borrador?",text:"Se perderán los datos capturados.",icon:"question",showCancelButton:true,confirmButtonText:"Descartar",cancelButtonText:"Continuar editando",confirmButtonColor:"#086b47"}).then(function(r){
            if (r.isConfirmed) {
                state.ventaNota = crearBorradorVentaNota();
                renderVentaConNota();
            }
        });
    });
}

function renderVentaConNota() {
    const template = document.getElementById("tplVentaNota");
    if (!template) {console.error("No existe tplVentaNota en index.html");return;}
    $("#contenidoModulo").empty().append(template.content.cloneNode(true));
    $("#contenidoModulo").prepend('<div class="mb-3"><button id="btnVolverListadoVentas" type="button" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Volver al listado</button></div>');

    $("#formVentaNota [data-campo-nota]").each(function(){
        $(this).val(state.ventaNota[$(this).attr("data-campo-nota")]);
    });

    if (!state.ventaNota.items.length) agregarItemVentaNota();
    else state.ventaNota.items.forEach(insertarFilaVentaNota);
    alternarCamposPagoNota();
    actualizarTotalesVentaNota();
    getCajasVenta();
}

function agregarItemVentaNota() {
    const item = {id:state.ventaNota.siguienteId++,origen:"MANUAL",product_id:null,codigo:"",nombre:"",cantidad:"1",precio:""};
    state.ventaNota.items.push(item);
    insertarFilaVentaNota(item);
    actualizarTotalesVentaNota();
    $("#detalleVentaNota tr:last [data-item-campo='nombre']").trigger("focus");
}

function insertarFilaVentaNota(item) {
    const template = document.getElementById("tplFilaVentaNota");
    const fila = template.content.firstElementChild.cloneNode(true);
    $(fila).attr("data-item-id",item.id);
    $(fila).find("[data-item-campo]").each(function(){
        $(this).val(item[$(this).attr("data-item-campo")]);
    });
    $("#detalleVentaNota").append(fila);
}

function numeroNotaValido(texto,minimo,maximo) {
    const valor = String(texto).trim();
    if (!/^\d+(?:\.\d{1,2})?$/.test(valor)) return null;
    const numero = Number(valor);
    return Number.isFinite(numero) && numero >= minimo && numero <= maximo ? numero : null;
}

function importeItemVentaNota(item) {
    const cantidad = numeroNotaValido(item.cantidad,0.01,100000);
    const precio = numeroNotaValido(item.precio,0,1000000);
    if (cantidad === null || precio === null) return null;
    return Math.round(cantidad * Math.round(precio * 100));
}

function obtenerImportesVentaNota() {
    let totalCentavos = 0;
    state.ventaNota.items.forEach(function(item){
        const importe = importeItemVentaNota(item);
        if (importe !== null) totalCentavos += importe;
    });

    const forma = state.ventaNota.forma_pago;
    let abonadoCentavos = 0;
    if (forma === "EFECTIVO" || forma === "TRANSFERENCIA") abonadoCentavos = totalCentavos;

    if (forma === "PARCIALIDADES") {
        const abono = numeroNotaValido(state.ventaNota.abono,0,99999999.99);
        abonadoCentavos = abono === null ? 0 : Math.min(Math.round(abono * 100),totalCentavos);
    }

    return {totalCentavos:totalCentavos,abonadoCentavos:abonadoCentavos,saldoCentavos:totalCentavos-abonadoCentavos};
}

function alternarCamposPagoNota() {
    const parcial = state.ventaNota.forma_pago === "PARCIALIDADES";
    $("#notaReferenciaBox").toggleClass("d-none",state.ventaNota.forma_pago !== "TRANSFERENCIA");
    $("#notaParcialidadBox").toggleClass("d-none",!parcial);
    $("#notaRefAbonoBox").toggleClass("d-none",!parcial || state.ventaNota.metodo_abono !== "TRANSFERENCIA");


const pagaEfectivo = state.ventaNota.forma_pago === "EFECTIVO" ||
    (state.ventaNota.forma_pago === "PARCIALIDADES" && state.ventaNota.metodo_abono === "EFECTIVO");

$("#notaCajaBox").toggleClass("d-none", !pagaEfectivo);

}

function actualizarTotalesVentaNota() {
    if (!$("#formVentaNota").length) return;
    $("#detalleVentaNota tr").each(function(){
        const id = Number($(this).attr("data-item-id"));
        const item = state.ventaNota.items.find(function(fila){return fila.id === id;});
        const importe = item ? importeItemVentaNota(item) : null;
        $(this).find(".nota-importe-fila").text(importe === null ? "—" : monedaMXN(importe / 100));
    });

    const total = obtenerImportesVentaNota();
    $("#notaTotal").text(monedaMXN(total.totalCentavos / 100));
    $("#notaAbonado").text(monedaMXN(total.abonadoCentavos / 100));
    $("#notaSaldo").text(monedaMXN(total.saldoCentavos / 100));
}

function errorVentaNota(mensaje,selector) {
    if (selector) $(selector).addClass("is-invalid").trigger("focus");
    Swal.fire({icon:"warning",title:"Revisa la nota",text:mensaje,confirmButtonColor:"#086b47"});
    return false;
}

function validarVentaNota() {
    const venta = state.ventaNota;

    if (venta.folio.trim().length > 50) return errorVentaNota("El folio puede tener hasta 50 caracteres.","#notaFolio");
    if (!venta.cliente.trim()) return errorVentaNota("Captura el nombre del cliente.","#notaCliente");
    if (!venta.vendedor.trim()) return errorVentaNota("Captura el nombre del vendedor.","#notaVendedor");
    if (!venta.items.length) return errorVentaNota("Agrega al menos un artículo.");

    for (let i = 0; i < venta.items.length; i++) {
        const item = venta.items[i];
        const fila = '#detalleVentaNota tr[data-item-id="' + item.id + '"]';
        if (!item.nombre.trim()) return errorVentaNota("El artículo " + (i+1) + " necesita una descripción.",fila+' [data-item-campo="nombre"]');
        if (numeroNotaValido(item.cantidad,0.01,100000) === null) return errorVentaNota("Revisa la cantidad del artículo " + (i+1) + ".",fila+' [data-item-campo="cantidad"]');
        if (numeroNotaValido(item.precio,0,1000000) === null) return errorVentaNota("Revisa el precio del artículo " + (i+1) + ".",fila+' [data-item-campo="precio"]');
    }

    const importes = obtenerImportesVentaNota();
    if (importes.totalCentavos <= 0 || importes.totalCentavos > 9999999999) return errorVentaNota("El total debe ser mayor que cero y no exceder $99,999,999.99.");

    if (!["EFECTIVO","TRANSFERENCIA","CREDITO","PARCIALIDADES"].includes(venta.forma_pago)) return errorVentaNota("Selecciona una forma de pago válida.","#notaFormaPago");

    if (venta.forma_pago === "PARCIALIDADES") {
        const abono = numeroNotaValido(venta.abono,0.01,99999999.99);
        if (abono === null || Math.round(abono * 100) >= importes.totalCentavos) return errorVentaNota("El abono debe ser mayor que cero y menor que el total. Para pagar todo selecciona efectivo o transferencia.","#notaAbono");
        if (!["EFECTIVO","TRANSFERENCIA"].includes(venta.metodo_abono)) return errorVentaNota("Selecciona cómo se recibió el abono.","#notaMetodoAbono");
    }


const requiereCaja = venta.forma_pago === "EFECTIVO" ||
    (venta.forma_pago === "PARCIALIDADES" && venta.metodo_abono === "EFECTIVO");

if (requiereCaja && (!Number.isInteger(Number(venta.caja_id)) || Number(venta.caja_id) <= 0)) {
    return errorVentaNota("Selecciona la caja donde recibirás el efectivo.", "#notaCaja");
}


    return true;
}

function revisarVentaNota() {
    if (!validarVentaNota()) return;

    const venta = state.ventaNota;
    const importes = obtenerImportesVentaNota();
    const $resumen = $('<div class="text-start small"></div>');

    $resumen.append($('<p></p>').text("Cliente: "+venta.cliente.trim()+" · Vendedor: "+venta.vendedor.trim()));
    $resumen.append($('<p></p>').text("Folio: "+(venta.folio.trim() || "Automático al guardar")+" · Pago: "+venta.forma_pago));

    const $lista = $('<div class="border rounded-3 p-3 mb-3"></div>');
    venta.items.forEach(function(item){
        $lista.append($('<div class="py-1"></div>').text(item.cantidad+" × "+item.nombre.trim()+" · "+monedaMXN(importeItemVentaNota(item)/100)));
    });

    $resumen.append($lista);
    $resumen.append($('<p class="fw-bold mb-1"></p>').text("Total: "+monedaMXN(importes.totalCentavos/100)));
    $resumen.append($('<p class="mb-1"></p>').text("Recibido: "+monedaMXN(importes.abonadoCentavos/100)));
    $resumen.append($('<p></p>').text("Saldo pendiente: "+monedaMXN(importes.saldoCentavos/100)));


Swal.fire({
    title:"Confirmar venta",
    html:$resumen[0],
    icon:"question",
    showCancelButton:true,
    confirmButtonText:"Guardar venta",
    cancelButtonText:"Seguir editando",
    confirmButtonColor:"#086b47",
    width:"630px"
}).then(function(resultado) {
    if (resultado.isConfirmed) guardarVentaNota();
});

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


/* =========================
   VENTAS RÁPIDAS
========================= */


function crearBorradorVentaRapida() {
    return {
        solicitud_id:generarIdSolicitudVenta(),
        caja_id:"",
        forma_pago:"EFECTIVO",
        referencia:"",
        observaciones:"",
        items:[],
        siguienteId:1
    };
}


function bindVentaRapida() {
    $(document).on("click","#btnAgregarItemRapida",function(){
        agregarItemVentaRapida();
    });

    $(document).on("click",".btnEliminarItemRapida",function(){
        const id = Number($(this).closest("tr").attr("data-item-id"));
        state.ventaRapida.items = state.ventaRapida.items.filter(function(item){return item.id !== id;});
        $(this).closest("tr").remove();
        if (!state.ventaRapida.items.length) agregarItemVentaRapida();
        actualizarTotalesVentaRapida();
    });

    $(document).on("input","#detalleVentaRapida [data-item-campo]",function(){
        const id = Number($(this).closest("tr").attr("data-item-id"));
        const item = state.ventaRapida.items.find(function(x){return x.id === id;});
        if (!item) return;
        item[$(this).attr("data-item-campo")] = this.value;
        $(this).removeClass("is-invalid");
        actualizarTotalesVentaRapida();
    });

    $(document).on("change","input[name='metodoVentaRapida']",function(){
        state.ventaRapida.forma_pago = this.value;
        alternarReferenciaVentaRapida();
        actualizarTotalesVentaRapida();
    });

    $(document).on("input","#formVentaRapida [data-campo-rapida]",function(){
        state.ventaRapida[$(this).attr("data-campo-rapida")] = this.value;
    });

    $(document).on("submit","#formVentaRapida",function(e){
        e.preventDefault();
        revisarVentaRapida();
    });

    $(document).on("click","#btnLimpiarVentaRapida",function(){
        Swal.fire({title:"¿Iniciar una nueva venta?",text:"Se descartará la captura actual.",icon:"question",showCancelButton:true,confirmButtonText:"Sí, limpiar",cancelButtonText:"Cancelar",confirmButtonColor:"#086b47"}).then(function(r){
            if (r.isConfirmed) {
                state.ventaRapida = crearBorradorVentaRapida();
                if (state.moduloActual === "ventas" && state.submoduloActual === "rapida") renderVentaRapida();
            }
        });
    });


$(document).on("change","#rapidaCaja",function(){
    state.ventaRapida.caja_id = this.value;
    $(this).removeClass("is-invalid");
});

}

function renderVentaRapida() {
    const template = document.getElementById("tplVentaRapida");
    if (!template) {console.error("No existe tplVentaRapida");return;}

    $("#contenidoModulo").empty().append(template.content.cloneNode(true));
    $("#contenidoModulo").prepend('<div class="mb-3"><button id="btnVolverListadoVentas" type="button" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Volver al listado</button></div>');
    $("#formVentaRapida [data-campo-rapida]").each(function(){
        $(this).val(state.ventaRapida[$(this).attr("data-campo-rapida")]);
    });

    $("input[name='metodoVentaRapida']").filter(function(){
        return this.value === state.ventaRapida.forma_pago;
    }).prop("checked",true);

    if (!state.ventaRapida.items.length) agregarItemVentaRapida();
    else state.ventaRapida.items.forEach(insertarFilaVentaRapida);

    alternarReferenciaVentaRapida();
    actualizarTotalesVentaRapida();
    getCajasVentaRapida();
}

function agregarItemVentaRapida() {
    const item = {id:state.ventaRapida.siguienteId++,origen:"MANUAL",product_id:null,codigo:"",nombre:"",cantidad:"1",precio:""};
    state.ventaRapida.items.push(item);
    insertarFilaVentaRapida(item);
    actualizarTotalesVentaRapida();
    $("#detalleVentaRapida tr:last [data-item-campo='nombre']").trigger("focus");
}

function insertarFilaVentaRapida(item) {
    const template = document.getElementById("tplFilaVentaNota");
    if (!template) {console.error("No existe tplFilaVentaNota");return;}
    const fila = template.content.firstElementChild.cloneNode(true);

    $(fila).attr("data-item-id",item.id);
    $(fila).find("[data-item-campo]").each(function(){
        $(this).val(item[$(this).attr("data-item-campo")]);
    });
    $(fila).find(".btnEliminarItemNota").removeClass("btnEliminarItemNota").addClass("btnEliminarItemRapida");
    $("#detalleVentaRapida").append(fila);
}


function alternarReferenciaVentaRapida() {
    const efectivo = state.ventaRapida.forma_pago === "EFECTIVO";
    $("#rapidaCajaBox").toggleClass("d-none",!efectivo);
    $("#rapidaReferenciaBox").toggleClass("d-none",efectivo);
}


function obtenerTotalVentaRapida() {
    let centavos = 0;
    state.ventaRapida.items.forEach(function(item){
        const importe = importeItemVentaNota(item);
        if (importe !== null) centavos += importe;
    });
    return centavos;
}

function actualizarTotalesVentaRapida() {
    if (!$("#formVentaRapida").length) return;

    $("#detalleVentaRapida tr").each(function(){
        const id = Number($(this).attr("data-item-id"));
        const item = state.ventaRapida.items.find(function(x){return x.id === id;});
        const importe = item ? importeItemVentaNota(item) : null;
        $(this).find(".nota-importe-fila").text(importe === null ? "—" : monedaMXN(importe / 100));
    });

    const total = obtenerTotalVentaRapida();
    const cantidad = state.ventaRapida.items.filter(function(x){return x.nombre.trim() !== "";}).length;

    $("#rapidaTotal").text(monedaMXN(total / 100));
    $("#rapidaCantidadItems").text(cantidad + (cantidad === 1 ? " artículo" : " artículos"));
    $("#rapidaItemsResumen").text(cantidad);
    $("#rapidaMetodoResumen").text(state.ventaRapida.forma_pago === "EFECTIVO" ? "Efectivo" : "Transferencia");
}

function errorVentaRapida(mensaje,selector) {
    if (selector) $(selector).addClass("is-invalid").trigger("focus");
    Swal.fire({icon:"warning",title:"Revisa la venta",text:mensaje,confirmButtonColor:"#086b47"});
    return false;
}

function validarVentaRapida() {
    const venta = state.ventaRapida;
    if (!venta.items.length) return errorVentaRapida("Agrega al menos un artículo.");

    for (let i = 0; i < venta.items.length; i++) {
        const item = venta.items[i];
        const fila = '#detalleVentaRapida tr[data-item-id="' + item.id + '"]';
        if (!item.nombre.trim()) return errorVentaRapida("Captura la descripción del artículo " + (i+1) + ".",fila+' [data-item-campo="nombre"]');
        if (numeroNotaValido(item.cantidad,0.01,100000) === null) return errorVentaRapida("Cantidad inválida en el artículo " + (i+1) + ".",fila+' [data-item-campo="cantidad"]');
        if (numeroNotaValido(item.precio,0,1000000) === null) return errorVentaRapida("Precio inválido en el artículo " + (i+1) + ".",fila+' [data-item-campo="precio"]');
    }

    const total = obtenerTotalVentaRapida();
    if (total <= 0 || total > 9999999999) return errorVentaRapida("El total debe ser mayor que cero y no exceder $99,999,999.99.");
    
if (!["EFECTIVO","TRANSFERENCIA"].includes(venta.forma_pago)) return errorVentaRapida("Selecciona efectivo o transferencia.");
if (venta.forma_pago==="EFECTIVO" && (!Number.isInteger(Number(venta.caja_id)) || Number(venta.caja_id)<=0)) {
    return errorVentaRapida("Selecciona una caja para recibir el efectivo.","#rapidaCaja");
}
return true;


}

function revisarVentaRapida() {
    if (!validarVentaRapida()) return;

    const venta = state.ventaRapida;
    const $resumen = $('<div class="text-start small"></div>');
    const $lista = $('<div class="border rounded-3 p-3 my-3"></div>');

    venta.items.forEach(function(item){
        $lista.append($('<div class="py-1"></div>').text(item.cantidad+" × "+item.nombre.trim()+" · "+monedaMXN(importeItemVentaNota(item)/100)));
    });

    $resumen.append($('<p class="mb-2"></p>').text("Forma de cobro: "+(venta.forma_pago === "EFECTIVO" ? "Efectivo" : "Transferencia")));
    if (venta.forma_pago === "TRANSFERENCIA" && venta.referencia.trim()) {
        $resumen.append($('<p></p>').text("Referencia: "+venta.referencia.trim()));
    }
    $resumen.append($lista);
    $resumen.append($('<h3 class="h5 fw-bold text-success"></h3>').text("Total: "+monedaMXN(obtenerTotalVentaRapida()/100)));
    //$resumen.append($('<p class="text-secondary mb-0"></p>').text("Esta es una vista previa. La venta todavía no se ha guardado en MySQL."));

Swal.fire({
    title:"Confirmar venta rápida",
    html:$resumen[0],
    icon:"question",
    showCancelButton:true,
    confirmButtonText:"Guardar y cobrar",
    cancelButtonText:"Seguir editando",
    confirmButtonColor:"#086b47",
    width:"600px"
}).then(function(resultado) {
    if (resultado.isConfirmed) guardarVentaRapida();
});

}


function guardarVentaRapida() {
    if (state.guardandoVentaRapida) return;
    if (!validarVentaRapida()) return;

    const venta = state.ventaRapida;
    const datos = {
        solicitud_id:venta.solicitud_id,
        caja_id:venta.caja_id,
        forma_pago:venta.forma_pago,
        referencia:venta.forma_pago === "TRANSFERENCIA" ? venta.referencia : "",
        observaciones:venta.observaciones,
        items:venta.items.map(function(item){
            return {
                origen:"MANUAL",
                codigo:item.codigo,
                nombre:item.nombre,
                cantidad:item.cantidad,
                precio:item.precio
            };
        })
    };

    state.guardandoVentaRapida = true;

    Swal.fire({
        title:"Registrando venta",
        text:"Guardando artículos y movimiento de cobro...",
        allowOutsideClick:false,
        allowEscapeKey:false,
        showConfirmButton:false,
        didOpen:function(){Swal.showLoading();}
    });

    $.ajax({
        type:"POST",
        url:"php/index.php",
        data:{
            action:"guardarVentaRapida",
            venta:JSON.stringify(datos)
        },
        dataType:"json"
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire({
                icon:"warning",
                title:"No se pudo guardar",
                text:data && data.answer ? data.answer : "No fue posible registrar la venta.",
                confirmButtonColor:"#086b47"
            });
            return;
        }

        const resultado = data.results || {};
        const referencia = resultado.referencia || "Registrada";
        const total = resultado.total || "0.00";

        state.ventaRapida = crearBorradorVentaRapida();

        if (state.moduloActual === "ventas" && state.submoduloActual === "rapida") {
            state.filtrosVentas = {buscar:"",tipo:"TODAS",inicio:"",fin:""};
    mostrarSubmodulo("listado");
        }

        Swal.fire({
            icon:"success",
            title:resultado.duplicada ? "Venta ya registrada" : "Venta guardada",
            html:"<p class='mb-2'>Referencia interna: <strong id='referenciaVentaRapida'></strong></p>" +
                 "<p class='mb-0'>Total cobrado: <strong id='totalVentaRapida'></strong></p>",
            //confirmButtonText:"Nueva venta",
            confirmButtonText:"Cerrar",
            confirmButtonColor:"#086b47",
            didOpen:function(){
                $("#referenciaVentaRapida").text(referencia);
                $("#totalVentaRapida").text(monedaMXN(total));
            }
        });
    }).fail(function(xhr) {
        const data = xhr.responseJSON || {};

        Swal.fire({
            icon:data.status === "warning" ? "warning" : "error",
            title:"No se pudo confirmar la venta",
            text:(data.answer || "No fue posible completar la petición.") +
                 " Si necesitas reintentar, conserva este borrador para evitar duplicados.",
            confirmButtonColor:"#086b47"
        });
    }).always(function(){
        state.guardandoVentaRapida = false;
    });
}


function generarIdSolicitudVenta() {
    if (!window.crypto || !window.crypto.getRandomValues) {
        throw new Error("Se necesita un navegador con generación criptográfica de identificadores.");
    }
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes).map(function(b) {
        return b.toString(16).padStart(2, "0");
    }).join("");
}



function getCajasVenta() {
    const $select = $("#notaCaja");
    if (!$select.length) return;

    $select.empty().append($("<option>",{value:"",text:"Consultando cajas..."})).prop("disabled",true);

    $.ajax({
        type:"POST",
        url:"php/index.php",
        data:{action:"getCajasVenta"},
        dataType:"json"
    }).done(function(data) {
        if (!$("#notaCaja").length) return;

        const $caja = $("#notaCaja").empty();
        $caja.append($("<option>",{value:"",text:"Selecciona una caja"}));

        if (!data || data.status !== "ok") {
            Swal.fire("Aviso",data && data.answer ? data.answer : "No fue posible consultar las cajas.","warning");
            return;
        }

        const cajas = Array.isArray(data.results) ? data.results : [];

        cajas.forEach(function(item) {
            $caja.append($("<option>",{value:String(item.id),text:item.name+" · Caja #"+item.id}));
        });

        if (cajas.some(function(item){return String(item.id) === String(state.ventaNota.caja_id);})) {
            $caja.val(String(state.ventaNota.caja_id));
        } else {
            state.ventaNota.caja_id = "";
        }

        if (!cajas.length) $caja.find("option:first").text("No hay cajas activas");
    }).fail(function(xhr) {
        const data = xhr.responseJSON || {};
        Swal.fire("Error",data.answer || "No fue posible consultar las cajas.","error");
    }).always(function(){
        $("#notaCaja").prop("disabled",false);
    });
}


function guardarVentaNota() {
    if (state.guardandoVentaNota) return;
    if (!validarVentaNota()) return;

    const venta = state.ventaNota;
    const datos = {
        solicitud_id:venta.solicitud_id,
        folio:venta.folio,
        cliente:venta.cliente,
        direccion:venta.direccion,
        telefono:venta.telefono,
        vendedor:venta.vendedor,
        observaciones:venta.observaciones,
        forma_pago:venta.forma_pago,
        referencia:venta.referencia,
        abono:venta.abono,
        metodo_abono:venta.metodo_abono,
        referencia_abono:venta.referencia_abono,
        caja_id:venta.caja_id,
        items:venta.items.map(function(item){
            return {
                origen:"MANUAL",
                codigo:item.codigo,
                nombre:item.nombre,
                cantidad:item.cantidad,
                precio:item.precio
            };
        })
    };

    state.guardandoVentaNota = true;

    Swal.fire({
        title:"Registrando venta",
        text:"Guardando nota, artículos y movimientos...",
        allowOutsideClick:false,
        allowEscapeKey:false,
        showConfirmButton:false,
        didOpen:function(){Swal.showLoading();}
    });

    $.ajax({
        type:"POST",
        url:"php/index.php",
        data:{
            action:"guardarVentaNota",
            venta:JSON.stringify(datos)
        },
        dataType:"json"
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire("Aviso",data && data.answer ? data.answer : "No fue posible guardar la venta.","warning");
            return;
        }

        const resultado = data.results || {};
        const folio = resultado.folio || "Sin folio";

        state.ventaNota = crearBorradorVentaNota();

        if (state.moduloActual === "ventas" && state.submoduloActual === "nota") {
              state.filtrosVentas = {buscar:"",tipo:"TODAS",inicio:"",fin:""};
    mostrarSubmodulo("listado");
        }

        
Swal.fire({
    icon:"success",
    title:resultado.duplicada ? "Venta ya registrada" : "Venta guardada",
    html:"<strong>Folio:</strong> " + $("<div>").text(folio).html() +
         "<p class='mb-0 mt-2'>La operación quedó registrada en MySQL.</p>",
    showCancelButton:true,
    confirmButtonText:'<i class="bi bi-file-earmark-pdf me-2"></i>Generar nota PDF',
    //confirmButtonText:'<i class="bi bi-files me-2"></i>Elegir notas PDF',
    cancelButtonText:"Cerrar",
    confirmButtonColor:"#086b47",
    reverseButtons:true
}).then(function(r) {
    if (r.isConfirmed) generarPdfNotaVenta(resultado.id);
});

        
    }).fail(function(xhr) {
        const data = xhr.responseJSON || {};
        Swal.fire({
            icon:data.status === "warning" ? "warning" : "error",
            title:"No se pudo confirmar la venta",
            text:(data.answer || "No fue posible completar la petición.") +
                 " Puedes reintentar desde el mismo borrador para evitar registros duplicados.",
            confirmButtonColor:"#086b47"
        });
    }).always(function(){
        state.guardandoVentaNota = false;
    });
}


function generarPdfNotaVenta_ikd(idVenta) {
    if (!Number.isInteger(Number(idVenta)) || Number(idVenta)<=0) return;

    const html = `
        <div class="text-start">
            <label class="form-label fw-semibold">¿Qué documentos deseas generar?</label>
            <div class="d-grid gap-2 mb-3">
                <label class="border rounded-3 p-3">
                    <input class="form-check-input me-2" type="radio" name="formatoDocumento" value="COMPACTA" checked>
                    <strong>Nota compacta</strong>
                    <small class="d-block text-secondary ms-4">Formato original · 140 × 216 mm</small>
                </label>
                <label class="border rounded-3 p-3">
                    <input class="form-check-input me-2" type="radio" name="formatoDocumento" value="CORPORATIVA">
                    <strong>Nota corporativa</strong>
                    <small class="d-block text-secondary ms-4">Hoja A4 · encabezado, tabla, marca de agua y firma</small>
                </label>
                <label class="border rounded-3 p-3">
                    <input class="form-check-input me-2" type="radio" name="formatoDocumento" value="AMBAS">
                    <strong>Ambas notas</strong>
                    <small class="d-block text-secondary ms-4">Dos archivos PDF de la misma venta</small>
                </label>
            </div>
            <label class="form-label fw-semibold" for="firmaTipoDocumento">Firma de autorización</label>
            <select class="form-select mb-2" id="firmaTipoDocumento">
                <option value="PREDEFINIDA">Utilizar firma existente</option>
                <option value="DIBUJADA">Firmar ahora en pantalla</option>
            </select>
            <div id="firmaPredeterminadaDoc" class="rounded-3 border bg-light text-center p-2">
                <img src="recursos/firma.png" alt="Firma existente del negocio" style="max-height:72px;max-width:100%;object-fit:contain">
            </div>
            <div id="firmaDibujadaDoc" class="d-none">
                <label for="firmaNombreDocumento" class="form-label mt-2">Nombre de quien firma *</label>
                <input class="form-control mb-2" id="firmaNombreDocumento" maxlength="100" placeholder="Nombre completo">
                <canvas id="firmaCanvasDocumento" width="620" height="170" style="width:100%;height:auto;display:block;background:white;border:1px solid #ccd9d0;border-radius:12px;touch-action:none"></canvas>
                <button id="btnLimpiarFirmaDocumento" type="button" class="btn btn-sm btn-outline-secondary mt-2">
                    <i class="bi bi-eraser me-1"></i>Limpiar y volver a firmar
                </button>
            </div>
            <p class="small text-secondary mt-3 mb-0">Se conservará la primera firma registrada en esta venta. Los documentos no generan nuevos cobros.</p>
        </div>`;

    let trazos=0;
    Swal.fire({
        title:'Documentos de la venta',
        html:html,
        width:'660px',
        showCancelButton:true,
        confirmButtonText:'Guardar firma y generar',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#086b47',
        didOpen:function(){
            const canvas=document.getElementById('firmaCanvasDocumento');
            const ctx=canvas.getContext('2d');
            const limpiar=function(){
                ctx.fillStyle='#fff';
                ctx.fillRect(0,0,canvas.width,canvas.height);
                trazos=0;
            };

            limpiar();
            ctx.strokeStyle='#173d2a';
            ctx.lineWidth=2.7;
            ctx.lineCap='round';
            ctx.lineJoin='round';

            let activo=false;
            const coords=function(e){
                const r=canvas.getBoundingClientRect();
                return {
                    x:(e.clientX-r.left)*canvas.width/r.width,
                    y:(e.clientY-r.top)*canvas.height/r.height
                };
            };

            canvas.addEventListener('pointerdown',function(e){
                e.preventDefault();
                activo=true;
                canvas.setPointerCapture(e.pointerId);
                const p=coords(e);
                ctx.beginPath();
                ctx.moveTo(p.x,p.y);
                ctx.lineTo(p.x+.1,p.y+.1);
                ctx.stroke();
                trazos++;
            });

            canvas.addEventListener('pointermove',function(e){
                if(!activo)return;
                e.preventDefault();
                const p=coords(e);
                ctx.lineTo(p.x,p.y);
                ctx.stroke();
            });

            ['pointerup','pointercancel','lostpointercapture'].forEach(function(ev){
                canvas.addEventListener(ev,function(){activo=false;});
            });

            document.getElementById('btnLimpiarFirmaDocumento').addEventListener('click',limpiar);
            document.getElementById('firmaTipoDocumento').addEventListener('change',function(){
                const manual=this.value==='DIBUJADA';
                document.getElementById('firmaDibujadaDoc').classList.toggle('d-none',!manual);
                document.getElementById('firmaPredeterminadaDoc').classList.toggle('d-none',manual);
            });
        },
        preConfirm:function(){
            const formato=document.querySelector('input[name="formatoDocumento"]:checked').value;
            const firma_tipo=document.getElementById('firmaTipoDocumento').value;
            const firmante=document.getElementById('firmaNombreDocumento').value.trim();

            if(firma_tipo==='DIBUJADA' && (!trazos || !firmante)) {
                Swal.showValidationMessage('Escribe el nombre y dibuja la firma antes de guardar.');
                return false;
            }

            return {
                formato:formato,
                firma_tipo:firma_tipo,
                firmante:firmante,
                firma_png:firma_tipo==='DIBUJADA'
                    ? document.getElementById('firmaCanvasDocumento').toDataURL('image/png')
                    : ''
            };
        }
    }).then(function(r){
        if(r.isConfirmed && r.value) enviarDocumentosNotaVenta(idVenta,r.value);
    });
}

function enviarDocumentosNotaVenta(idVenta,opciones) {
    Swal.fire({
        title:'Generando documentos',
        text:'Preparando los archivos PDF...',
        allowOutsideClick:false,
        allowEscapeKey:false,
        showConfirmButton:false,
        didOpen:()=>Swal.showLoading()
    });

    $.ajax({
        url:'php/index.php',
        type:'POST',
        dataType:'json',
        data:Object.assign({action:'generarDocumentosNotaVenta',id:idVenta},opciones)
    }).done(function(r){
        if(!r || r.status!=='ok') {
            return Swal.fire('Aviso',r && r.answer||'No se generaron los documentos.','warning');
        }

        const $lista=$('<div class="d-grid gap-2 mt-3"></div>');
        const carpeta=new URL('pdf_generados/notas_venta/',window.location.href);

        (r.results.archivos||[]).forEach(function(a){
            const url=new URL(a.archivo,window.location.href);
            if(url.origin!==carpeta.origin || !url.pathname.startsWith(carpeta.pathname)) return;

            const nombre=a.tipo==='COMPACTA'?'Nota compacta':'Nota corporativa';

            $lista.append(
                $('<a class="btn btn-success py-2" target="_blank" rel="noopener noreferrer"></a>')
                    .attr('href',url.href)
                    .text('Ver e imprimir '+nombre)
            );
            $lista.append(
                $('<a class="btn btn-outline-success btn-sm"></a>')
                    .attr({href:url.href,download:a.nombre})
                    .text('Descargar '+nombre)
            );
        });

        if(!$lista.children().length) {
            return Swal.fire('Error','PHP no devolvió archivos válidos.','error');
        }

        Swal.fire({
            icon:'success',
            title:'Documentos listos',
            html:$lista[0],
            confirmButtonText:'Cerrar',
            confirmButtonColor:'#086b47'
        });
    }).fail(function(xhr){
        Swal.fire(
            'No se generaron los documentos',
            (xhr.responseJSON&&xhr.responseJSON.answer)||'Error de comunicación con PHP.',
            'error'
        );
    });
}


function generarPdfNotaVenta(idVenta) {
    if (!idVenta || Number(idVenta) <= 0) {
        Swal.fire("Aviso", "No se recibió un ID de venta válido.", "warning");
        return;
    }

    Swal.fire({
        title: "Generando nota",
        text: "Preparando PDF de la nota de venta...",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: {
            action: "generarPdfNotaVenta",
            id: idVenta
        },
        dataType: "json"
    
}).done(function(data) {
    if (!data || data.status !== "ok") {
        Swal.fire("Aviso", data && data.answer ? data.answer : "No fue posible generar la nota.", "warning");
        return;
    }

    const resultado = data.results || {};

    if (!resultado.archivo) {
        Swal.fire("Error", "PHP no devolvió la ubicación del PDF.", "error");
        return;
    }

    const url = new URL(resultado.archivo, window.location.href);
    const carpeta = new URL("pdf_generados/notas_venta/", window.location.href);

    if (url.origin !== carpeta.origin || !url.pathname.startsWith(carpeta.pathname)) {
        Swal.fire("Error", "La ruta del PDF no es válida.", "error");
        return;
    }

    const $acciones = $('<div class="d-flex flex-column gap-2 mt-3"></div>');

    $("<a>", {
        class: "btn btn-success py-2",
        text: "Ver e imprimir nota"
    }).attr({
        href: url.href,
        target: "_blank",
        rel: "noopener noreferrer"
    }).appendTo($acciones);

    $("<a>", {
        class: "btn btn-outline-success py-2",
        text: "Descargar PDF"
    }).attr({
        href: url.href,
        download: resultado.nombre || "nota_venta.pdf"
    }).appendTo($acciones);

    Swal.fire({
        icon: "success",
        title: "Nota generada",
        html: $acciones[0],
        confirmButtonText: "Cerrar",
        confirmButtonColor: "#086b47"
    });

    
}).fail(function(xhr) {
    const data = xhr.responseJSON || {};
    const debug = data.debug || {};

    console.error("ERROR PDF:", xhr.status, data, xhr.responseText);

    const detalle = debug.mensaje
        ? "\n\nDetalle: " + debug.mensaje +
          "\nArchivo: " + debug.archivo +
          "\nLínea: " + debug.linea
        : "";

    Swal.fire({
        icon: "error",
        title: "Error al generar PDF",
        text: (data.answer || "No fue posible generar la nota.") + detalle,
        confirmButtonColor: "#086b47"
    });
});

}

state.cajaId = 0;
state.peticionCaja = 0;
state.guardandoEgreso = false;
state.tokenEgreso = generarIdSolicitudVenta();

function bindCajaEgresos() {
    //$(document).on("change","#cajaSeleccionada",function(){state.cajaId = Number(this.value)||0; consultarControlCaja();});

$(document).on("change", "#cajaSeleccionada", function() {
    state.cajaId = Number(this.value) || 0;
    $(".js-denominacion-caja").val("");
    actualizarDenominacionesCaja();
    consultarControlCaja();
});

    $(document).on("submit","#formAperturaCaja",function(e){
        e.preventDefault();
        operarCaja("abrirCaja",{caja_id:state.cajaId,fondo:$("#fondoCaja").val()},"¿Confirmar apertura de caja?");
    });
    
    $(document).on("change","#egresoMetodo",alternarMetodoEgreso);
    $(document).on("submit","#formEgreso",function(e){e.preventDefault(); confirmarEgreso();});
    $(document).on("input", ".js-denominacion-caja", actualizarDenominacionesCaja);
}

function renderControlCaja() {
    const tpl = document.getElementById("tplControlCaja");
    if (!tpl) return console.error("Falta tplControlCaja");
    //$("#contenidoModulo").empty().append(tpl.content.cloneNode(true));
    //consultarControlCaja();
    $("#contenidoModulo").empty().append(tpl.content.cloneNode(true));
renderDenominacionesCaja();
consultarControlCaja();
}

function consultarControlCaja() {
    const version = state.versionVista, solicitud = ++state.peticionCaja;
    $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:{action:"getControlCaja",caja_id:state.cajaId}}).done(function(r){
        if (version !== state.versionVista || solicitud !== state.peticionCaja) return;
        if (!r || r.status !== "ok") return Swal.fire("Aviso",r && r.answer || "No se pudo consultar la caja.","warning");
        const d = r.results, turno = d.turno;
        state.cajaId = Number(d.caja_id)||0;
        const $sel = $("#cajaSeleccionada").empty();
        d.cajas.forEach(function(c){$("<option>").val(c.id).text(c.name+" · #"+c.id).appendTo($sel);});
        $sel.val(String(state.cajaId));
        $("#sinCajas").toggleClass("d-none",d.cajas.length>0);
        $("#panelAbrirCaja").toggleClass("d-none",!state.cajaId || !!(turno && turno.status==="ABIERTO"));
        $("#panelCerrarCaja").toggleClass("d-none",!turno || turno.status!=="ABIERTO");
        const caja = d.cajas.find(function(c){return Number(c.id)===state.cajaId;});
        $("#fondoCaja").val(caja ? Number(caja.current_balance).toFixed(2) : "0.00");
        const datos = turno ? [
            ["Fondo inicial",turno.opening_balance],
            ["Entradas en efectivo",turno.ingresos],
            ["Salidas en efectivo",turno.salidas],
            ["Efectivo esperado",turno.esperado]
        ] : [["Saldo registrado",caja ? caja.current_balance : 0]];
        const $res = $("#cajaResumen").empty();
        datos.forEach(function(f){
            const $card = $('<div class="col-sm-6 col-lg-3"></div>');
            $card.append($('<div class="border bg-white rounded-4 p-3 h-100"></div>')
                .append($('<small class="text-secondary"></small>').text(f[0]))
                .append($('<h3 class="h5 fw-bold mt-2 mb-0"></h3>').text(monedaMXN(f[1]))));
            $res.append($card);
        });
        $("#cajaEsperado").text(monedaMXN(turno ? turno.esperado : 0)).attr("data-valor",turno ? turno.esperado : 0);
        actualizarDenominacionesCaja();
        $("#cajaEstado").text(!turno ? "Sin turnos registrados" : turno.status==="ABIERTO" ? "Turno #"+turno.id+" abierto desde "+turno.opened_at : "Último turno cerrado · "+turno.closed_at);
        $("#cajaUltimoCierre").text(turno && turno.status==="CERRADO" ? "Último arqueo: "+monedaMXN(turno.counted_balance)+" · Diferencia: "+monedaMXN(turno.difference_amount) : "");
        
        
const $ultimoDesglose = $("#cajaUltimoDesglose").empty();

if (turno && turno.status === "CERRADO" && turno.denomination_breakdown) {
    try {
        const cantidades = JSON.parse(turno.denomination_breakdown);

        DENOMINACIONES_CAJA.forEach(function(d) {
            const cantidad = Number(cantidades[String(d.centavos)] || 0);
            if (cantidad <= 0) return;

            const $col = $('<div class="col-6 col-md-4 col-xl-3"></div>');
            const $card = $('<div class="border rounded-3 bg-white p-2 small"></div>');

            $card.text(d.nombre + " × " + cantidad + " = " + monedaMXN(d.centavos * cantidad / 100));
            $col.append($card);
            $ultimoDesglose.append($col);
        });
    } catch (error) {
        console.error("No se pudo mostrar el desglose del arqueo:", error);
    }
}

        
        const $tbody = $("#tablaFlujosCaja").empty();
        if (!d.movimientos.length) $tbody.append('<tr><td colspan="4" class="text-center text-secondary py-3">Sin movimientos en este turno</td></tr>');
        d.movimientos.forEach(function(m){
            const $tr = $("<tr>");
            $tr.append($("<td>").text(m.created_at),$("<td>").text(m.description),$("<td>").text(m.type==="IN"?"Entrada":"Salida"),$('<td class="text-end fw-semibold">').text(monedaMXN(m.amount)));
            $tbody.append($tr);
        });
    }).fail(function(x){
        if (version===state.versionVista) Swal.fire("Error",x.responseJSON && x.responseJSON.answer || "Error de conexión.","error");
    });
}

function operarCaja(action,data,titulo) {
    if (!data.caja_id || (action==="abrirCaja" && numeroNotaValido(data.fondo,0,99999999.99)===null)) return Swal.fire("Aviso","Revisa la caja y el monto.","warning");
    Swal.fire({title:titulo,icon:"question",showCancelButton:true,confirmButtonText:"Confirmar",cancelButtonText:"Cancelar",confirmButtonColor:"#086b47"}).then(function(r){
        if (!r.isConfirmed) return;
        $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:Object.assign({action:action},data)}).done(function(resp){
            if (!resp || resp.status!=="ok") return Swal.fire("Aviso",resp && resp.answer || "Operación no realizada.","warning");
            if (state.moduloActual==="caja" && state.submoduloActual==="arqueo") consultarControlCaja();
            Swal.fire("Correcto",resp.answer,"success");
        }).fail(function(x){Swal.fire("Error",x.responseJSON && x.responseJSON.answer || "Error al guardar.","error");});
    });
}

function renderEgresos() {
    const tpl = document.getElementById("tplEgresos");
    if (!tpl) return console.error("Falta tplEgresos");
    $("#contenidoModulo").empty().append(tpl.content.cloneNode(true));
    alternarMetodoEgreso();
    consultarEgresos();
}

function alternarMetodoEgreso() {
    const efectivo = $("#egresoMetodo").val()==="EFECTIVO";
    $("#egresoCajaBox").toggleClass("d-none",!efectivo);
    $("#egresoReferenciaBox").toggleClass("d-none",efectivo);
}

function consultarEgresos() {
    const version = state.versionVista;
    $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:{action:"getEgresos"}}).done(function(r){
        if (version!==state.versionVista) return;
        if (!r || r.status!=="ok") return Swal.fire("Aviso",r && r.answer || "No se pudieron consultar egresos.","warning");
        const $caja = $("#egresoCaja").empty().append('<option value="">Selecciona una caja abierta</option>');
        r.results.cajas.forEach(function(c){$("<option>").val(c.id).text(c.name+" · #"+c.id).appendTo($caja);});
        const $tabla = $("#tablaEgresos").empty();
        if (!r.results.egresos.length) $tabla.append('<tr><td colspan="6" class="text-center text-secondary py-3">Sin egresos registrados</td></tr>');
        r.results.egresos.forEach(function(m){
            const $tr = $("<tr>");
            [m.date,m.ticket_number,m.user_reference_name,m.expense_category,m.origin,monedaMXN(m.amount)].forEach(function(v){$tr.append($("<td>").text(v||"—"));});
            $tabla.append($tr);
        });
    }).fail(function(x){
        if (version===state.versionVista) Swal.fire("Error",x.responseJSON && x.responseJSON.answer || "Error de conexión.","error");
    });
}

function confirmarEgreso() {
    if (state.guardandoEgreso) return;
    const d = {
        action:"guardarEgreso",solicitud_id:state.tokenEgreso,
        beneficiario:$("#egresoBeneficiario").val().trim(),categoria:$("#egresoCategoria").val(),
        metodo:$("#egresoMetodo").val(),caja_id:$("#egresoCaja").val(),
        monto:$("#egresoMonto").val(),referencia:$("#egresoReferencia").val().trim(),
        notas:$("#egresoNotas").val().trim()
    };
    if (!d.beneficiario || !d.categoria || numeroNotaValido(d.monto,0.01,99999999.99)===null || (d.metodo==="EFECTIVO" && !d.caja_id)) {
        return Swal.fire("Aviso","Completa beneficiario, categoría, importe y caja si pagas en efectivo.","warning");
    }
    Swal.fire({title:"¿Registrar gasto de "+monedaMXN(d.monto)+"?",text:d.beneficiario+" · "+d.metodo,icon:"question",showCancelButton:true,confirmButtonText:"Registrar gasto",cancelButtonText:"Cancelar",confirmButtonColor:"#086b47"}).then(function(c){
        if (!c.isConfirmed || state.guardandoEgreso) return;
        state.guardandoEgreso = true;
        $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:d}).done(function(r){
            if (!r || r.status!=="ok") return Swal.fire("Aviso",r && r.answer || "No se registró el gasto.","warning");
            state.tokenEgreso = generarIdSolicitudVenta();
            if ($("#formEgreso").length) {$("#formEgreso")[0].reset(); alternarMetodoEgreso(); consultarEgresos();}
            Swal.fire("Gasto registrado","Folio "+r.results.folio,"success");
        }).fail(function(x){
            Swal.fire("Error",x.responseJSON && x.responseJSON.answer || "No se pudo confirmar el registro. Puedes reintentar sin duplicar.","error");
        }).always(function(){state.guardandoEgreso=false;});
    });
}


function getCajasVentaRapida() {
    const $select = $("#rapidaCaja");
    if (!$select.length) return;

    $select.empty().append($("<option>",{value:"",text:"Consultando cajas..."})).prop("disabled",true);

    $.ajax({
        type:"POST",
        url:"php/index.php",
        data:{action:"getCajasVenta"},
        dataType:"json"
    }).done(function(data) {
        if (!$("#rapidaCaja").length) return;

        const $caja = $("#rapidaCaja").empty();
        $caja.append($("<option>",{value:"",text:"Selecciona una caja"}));

        if (!data || data.status !== "ok") {
            Swal.fire("Aviso",data && data.answer ? data.answer : "No fue posible consultar las cajas.","warning");
            return;
        }

        const cajas = Array.isArray(data.results) ? data.results : [];

        cajas.forEach(function(item){
            $caja.append($("<option>",{
                value:String(item.id),
                text:item.name+" · Caja #"+item.id
            }));
        });

        const seleccionada = String(state.ventaRapida.caja_id);

        if (cajas.some(function(item){return String(item.id) === seleccionada;})) {
            $caja.val(seleccionada);
        } else {
            state.ventaRapida.caja_id = "";
        }

        if (!cajas.length) $caja.find("option:first").text("No hay cajas activas");
    }).fail(function(xhr) {
        const data = xhr.responseJSON || {};
        Swal.fire("Error",data.answer || "No fue posible consultar las cajas.","error");
    }).always(function(){
        $("#rapidaCaja").prop("disabled",false);
    });
}


state.tipoCuentaActual = null;
state.peticionCuentas = 0;
state.guardandoCuenta = false;
state.guardandoAbono = false;
state.tokenNuevaCuenta = null;
state.tokenAbonoCuenta = null;
state.abonoCuentaIdentidad = "";
state.vistaCuentas = "CREDITOS";

function bindCuentas() {
    $(document).on("click","#btnNuevaCuenta",function(){$("#panelNuevaCuenta").removeClass("d-none");$("#cuentaNombre").trigger("focus");});
    $(document).on("click","#btnCancelarCuentaNueva",function(){$("#panelNuevaCuenta").addClass("d-none");});
    //$(document).on("submit","#formFiltroCuentas",function(e){e.preventDefault();consultarCuentas();});
    $(document).on("submit","#formFiltroCuentas",function(e){e.preventDefault();consultarVistaCuentas();});
    $(document).on("submit","#formCuentaNueva",function(e){e.preventDefault();guardarCuentaNueva();});
    $(document).on("click",".btnCuentaHistorial",function(){verDetalleCuenta(Number($(this).attr("data-id")));});
    $(document).on("click",".btnCuentaAbonar",function(){abrirAbonoCuenta(Number($(this).attr("data-id")),Number($(this).attr("data-saldo")));});
    $(document).on("change","#abonoMetodo",function(){
        const efectivo = $(this).val()==="EFECTIVO";
        $("#abonoCajaBox").toggleClass("d-none",!efectivo);
        $("#abonoReferenciaBox").toggleClass("d-none",efectivo);
    });


$(document).on("click",".btnCuentaReporte",function(){
    if (state.tipoCuentaActual!=="COBRAR") return;
    const id=Number($(this).attr("data-id"));
    mostrarConcentradoCxc(id);
});



$(document).on("click",".js-vista-cuentas",function(){mostrarVistaCuentas($(this).attr("data-vista"));});
$(document).on("click",".js-filtro-cuentas",function(){
    const filtro=$(this).attr("data-filtro");
    $("#cuentasEstado").val(filtro);
    $(".js-filtro-cuentas").removeClass("active").attr("aria-pressed","false");
    $(this).addClass("active").attr("aria-pressed","true");
    consultarCuentas();
});
$(document).on("click",".btnPersonaCreditos",function(){mostrarVistaCuentas("CREDITOS",$(this).attr("data-nombre"));});
$(document).on("click",".btnMovimientoCuenta",function(){verDetalleCuenta(Number($(this).attr("data-id")));});

}

function renderCuentas(tipo) {
    state.tipoCuentaActual = tipo;
    const tpl = document.getElementById("tplCuentas");
    if (!tpl) return console.error("No existe tplCuentas");
    $("#contenidoModulo").empty().append(tpl.content.cloneNode(true));
    $("#tituloNuevaCuenta").text(tipo==="COBRAR" ? "Registrar cuenta por cobrar" : "Registrar cuenta por pagar");
    $("#cuentaNombreLabel").text(tipo==="COBRAR" ? "Cliente *" : "Proveedor *");
    $("#cuentasBuscar").attr("placeholder",tipo==="COBRAR" ? "Nombre del cliente, folio o ID" : "Proveedor, factura o ID");
    if (!state.tokenNuevaCuenta) state.tokenNuevaCuenta = generarIdSolicitudVenta();
    //consultarCuentas();
    mostrarVistaCuentas("CREDITOS");
}


function mostrarVistaCuentas(vista,buscar) {
    if (!["PERSONAS","CREDITOS","MOVIMIENTOS"].includes(vista)) return;
    state.vistaCuentas = vista;
    state.peticionCuentas++;
    const cobrar = state.tipoCuentaActual==="COBRAR";
    $("#cuentasVistaPersonas").text(cobrar ? "Deudores" : "Proveedores");
    $("#cuentasVistaCreditos").text(cobrar ? "Créditos" : "Deudas");
    $("#cuentasVistaMovimientos").text(cobrar ? "Abonos" : "Pagos");
    $(".js-vista-cuentas").each(function(){
        const activo = $(this).attr("data-vista")===vista;
        $(this).toggleClass("active",activo).attr("aria-selected",activo ? "true":"false");
    });
    $("#tituloListadoCuentas").text(vista==="PERSONAS" ? (cobrar?"Resumen de deudores":"Resumen de proveedores") : vista==="CREDITOS" ? (cobrar?"Créditos registrados":"Deudas registradas") : (cobrar?"Historial de abonos":"Historial de pagos"));
    $("#pillsEstadoCuenta").toggleClass("d-none",vista!=="CREDITOS");
    $("#cuentasKpis").toggleClass("d-none",vista!=="CREDITOS");
    $("#cuentasRangoFechas").toggleClass("d-none",vista!=="MOVIMIENTOS");
    $("#cuentasFiltroHoy").text(cobrar ? "Cobrar hoy":"Pagar hoy");
    $("#cuentasBuscar").val(buscar||"");
    //$("#cuentasEstado").val("PENDIENTE");
    const filtroInicial = buscar ? "TODAS" : "PENDIENTE";
$("#cuentasEstado").val(filtroInicial);
    $(".js-filtro-cuentas").removeClass("active").attr("aria-pressed","false");
    //$(".js-filtro-cuentas[data-filtro='PENDIENTE']").addClass("active").attr("aria-pressed","true");
    $(".js-filtro-cuentas").filter(function(){return $(this).attr("data-filtro")===filtroInicial;}).addClass("active").attr("aria-pressed","true");
    const columnas = vista==="PERSONAS" ? [cobrar?"Deudor":"Proveedor","Cuentas","Total","Abonado","Deuda actual","Vencidas","Próximo pago","Acciones"] : vista==="CREDITOS" ? ["No.",cobrar?"Cliente":"Proveedor","Documento","Vencimiento","Total","Abonado","Saldo","Estado","Acciones"] : ["No.",cobrar?"Cliente":"Proveedor","Cuenta","Fecha","Origen","Método","Referencia","Importe","Acciones"];
    const $fila = $("<tr>");
    columnas.forEach(function(c){$fila.append($("<th>").text(c));});
    $("#cabeceraCuentas").empty().append($fila);
    consultarVistaCuentas();
}

function consultarVistaCuentas() {
    if (state.vistaCuentas==="CREDITOS") return consultarCuentas();
    const vista=state.vistaCuentas, tipo=state.tipoCuentaActual;
    const version=state.versionVista, solicitud=++state.peticionCuentas;
    const datos={action:vista==="PERSONAS" ? "getPersonasCuentas" : "getAbonosCuentas",tipo:tipo,buscar:$("#cuentasBuscar").val()||""};
    //if(vista==="MOVIMIENTOS") {datos.fecha_inicio=$("#cuentasFechaInicio").val();datos.fecha_fin=$("#cuentasFechaFin").val();}
    
if (vista==="MOVIMIENTOS") {
    const inicio=$("#cuentasFechaInicio").val(), fin=$("#cuentasFechaFin").val();
    if ((inicio&&!fin)||(!inicio&&fin)||(inicio&&fin&&inicio>fin)) {
        return Swal.fire("Revisa las fechas","Indica ambas fechas en un rango válido, o deja ambas vacías.","warning");
    }
    datos.fecha_inicio=inicio;
    datos.fecha_fin=fin;
}

    $("#tablaCuentas").html('<tr><td colspan="9" class="text-center text-secondary py-4">Consultando...</td></tr>');
    $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:datos}).done(function(r){
        if(version!==state.versionVista || solicitud!==state.peticionCuentas || tipo!==state.tipoCuentaActual || vista!==state.vistaCuentas) return;
        if(!r || r.status!=="ok") {$("#tablaCuentas").empty();return Swal.fire("Aviso",r && r.answer || "No fue posible consultar la información.","warning");}
        const registros=vista==="PERSONAS" ? (r.results.personas||[]) : (r.results.movimientos||[]);
        const $tabla=$("#tablaCuentas").empty();
        if(!registros.length) return $tabla.append('<tr><td colspan="9" class="text-center text-secondary py-4">No hay registros con estos filtros.</td></tr>');
        registros.forEach(function(x){
            const $tr=$("<tr>");
            const datosFila=vista==="PERSONAS" ? [x.nombre,x.creditos,monedaMXN(x.total),monedaMXN(x.abonado),monedaMXN(x.pendiente),x.vencidos,x.proximo_pago||"Sin fecha"] : [x.origen==="INICIAL" ? "Inicial" : x.id,x.nombre,"#"+x.account_id,x.created_at,x.origen==="INICIAL"?"Anticipo inicial":(tipo==="COBRAR"?"Abono":"Pago"),x.method,x.reference||"—",monedaMXN(x.amount)];
            datosFila.forEach(function(valor){$tr.append($("<td>").text(valor));});
            const $boton=vista==="PERSONAS" ? $('<button type="button" class="btn btn-outline-success btn-sm btnPersonaCreditos">Ver cuentas</button>').attr("data-nombre",x.nombre) : $('<button type="button" class="btn btn-outline-success btn-sm btnMovimientoCuenta">Ver cuenta</button>').attr("data-id",x.account_id);
            $tabla.append($tr.append($("<td>").append($boton)));
        });
    }).fail(function(xhr){
        if(version===state.versionVista && solicitud===state.peticionCuentas && tipo===state.tipoCuentaActual && vista===state.vistaCuentas) Swal.fire("Error",xhr.responseJSON && xhr.responseJSON.answer || "No fue posible conectar con PHP.","error");
    });
}


function consultarCuentas() {
    const version = state.versionVista, solicitud = ++state.peticionCuentas, tipo = state.tipoCuentaActual;
    const buscar = $("#cuentasBuscar").val() || "", estado = $("#cuentasEstado").val() || "TODAS";
    $("#tablaCuentas").html('<tr><td colspan="9" class="text-center py-4 text-secondary">Consultando cuentas...</td></tr>');
    $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:{action:"getCuentas",tipo:tipo,buscar:buscar,estado:estado}}).done(function(r){
        if (version!==state.versionVista || solicitud!==state.peticionCuentas || tipo!==state.tipoCuentaActual) return;
        if (!r || r.status!=="ok") return Swal.fire("Aviso",r && r.answer || "No se pudieron consultar las cuentas.","warning");
        const resumen = r.results.resumen || {}, registros = r.results.cuentas || [];
        const tarjetas = [
            {titulo:"Cuentas registradas",valor:Number(resumen.total)||0,icono:"bi-journal-text",moneda:false},
            {titulo:"Saldo pendiente",valor:resumen.pendiente,icono:"bi-wallet2",moneda:true},
            {titulo:"Importe vencido",valor:resumen.vencido,icono:"bi-exclamation-circle",moneda:true}
        ];
        const $kpis = $("#cuentasKpis").empty();
        tarjetas.forEach(function(t){
            const $contenido = $('<div class="border bg-white rounded-4 p-3 h-100"></div>');
            $contenido.append($('<div class="d-flex justify-content-between align-items-center gap-2"></div>')
                .append($("<small>").addClass("text-secondary").text(t.titulo))
                .append($("<i>").addClass("bi "+t.icono+" text-success")));
            $contenido.append($('<h3 class="h4 fw-bold mt-2 mb-0"></h3>').text(t.moneda ? monedaMXN(t.valor) : t.valor));
            $kpis.append($('<div class="col-md-4"></div>').append($contenido));
        });
        const $tabla = $("#tablaCuentas").empty();
        if (!registros.length) $tabla.append('<tr><td colspan="9" class="text-center text-secondary py-4">No hay cuentas con estos filtros.</td></tr>');
        const hoy = new Date(), hoyTexto = hoy.getFullYear()+"-"+String(hoy.getMonth()+1).padStart(2,"0")+"-"+String(hoy.getDate()).padStart(2,"0");
        registros.forEach(function(c){
            const vencida = c.status==="PENDIENTE" && c.due_date && c.due_date<hoyTexto && Number(c.pending_amount)>0;
            const estadoTexto = vencida ? "Vencida" : c.status==="PENDIENTE" ? "Pendiente" : c.status==="PAGADO" ? "Pagada" : "Cancelada";
            const clase = vencida ? "text-bg-danger" : c.status==="PAGADO" ? "text-bg-success" : c.status==="CANCELADO" ? "text-bg-secondary" : "text-bg-warning";
            const $tr = $("<tr>");
            [c.id,c.nombre,c.document_reference||"—",c.due_date||"Sin fecha",monedaMXN(c.total_amount),monedaMXN(c.paid_amount),monedaMXN(c.pending_amount)].forEach(function(v){$tr.append($("<td>").text(v));});
            $tr.append($("<td>").append($('<span class="badge"></span>').addClass(clase).text(estadoTexto)));
            //const $acciones = $('<td class="text-nowrap"></td>');
            //$acciones.append($('<button type="button" class="btn btn-outline-secondary btn-sm btnCuentaHistorial me-1"><i class="bi bi-clock-history"></i> Historial</button>').attr("data-id",c.id));
            //if (c.status==="PENDIENTE" && Number(c.pending_amount)>0) $acciones.append($('<button type="button" class="btn btn-success btn-sm btnCuentaAbonar"><i class="bi bi-cash-coin"></i> '+(tipo==="COBRAR"?"Cobrar":"Pagar")+'</button>').attr("data-id",c.id).attr("data-saldo",c.pending_amount));
            
const $acciones = $('<td class="text-nowrap"></td>');
const $dropdown = $('<div class="dropdown"></div>');
const $toggle = $('<button type="button" class="btn btn-outline-success btn-sm dropdown-toggle px-3" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false"><i class="bi bi-three-dots-vertical me-1"></i>Acciones</button>');
const $menu = $('<ul class="dropdown-menu dropdown-menu-end shadow-sm border rounded-3 p-2"></ul>');

const agregarAccion = function(texto, icono, clase, datos) {
    const $btn = $('<button type="button" class="dropdown-item rounded-2 py-2"></button>');
    $btn.addClass(clase);
    $btn.append($('<i class="bi me-2"></i>').addClass("bi-"+icono));
    $btn.append(document.createTextNode(texto));
    Object.keys(datos).forEach(function(k){
        $btn.attr("data-"+k,datos[k]);
    });
    $menu.append($('<li></li>').append($btn));
};

agregarAccion("Ver historial","clock-history","btnCuentaHistorial",{id:c.id});

if (c.status==="PENDIENTE" && Number(c.pending_amount)>0) {
    agregarAccion(
        tipo==="COBRAR" ? "Registrar cobro" : "Registrar pago",
        "cash-coin",
        "btnCuentaAbonar",
        {id:c.id,saldo:c.pending_amount}
    );

    if (tipo==="COBRAR") {
        agregarAccion(
            "Generar concentrado PDF",
            "file-earmark-pdf",
            "btnCuentaReporte",
            {id:c.id}
        );
    }
}

$dropdown.append($toggle,$menu);
$acciones.append($dropdown);

            
            $tabla.append($tr.append($acciones));
        });
    }).fail(function(xhr){
        if (version===state.versionVista && solicitud===state.peticionCuentas) Swal.fire("Error",xhr.responseJSON && xhr.responseJSON.answer || "Error de conexión.","error");
    });
}



function guardarCuentaNueva() {
    if (state.guardandoCuenta) return;
    const d = {
        action:"crearCuenta",tipo:state.tipoCuentaActual,solicitud_id:state.tokenNuevaCuenta,
        nombre:$("#cuentaNombre").val().trim(),
        referencia:$("#cuentaReferencia").val().trim(),
        concepto:$("#cuentaConcepto").val().trim(),
        monto:$("#cuentaMonto").val(),
        vencimiento:$("#cuentaVencimiento").val()
    };
    if (!d.nombre || !d.concepto || numeroNotaValido(d.monto,0.01,99999999.99)===null) return Swal.fire("Aviso","Completa el nombre, concepto e importe.","warning");
    Swal.fire({title:"¿Registrar esta cuenta?",text:d.nombre+" · "+monedaMXN(d.monto),icon:"question",showCancelButton:true,confirmButtonText:"Registrar",cancelButtonText:"Cancelar",confirmButtonColor:"#086b47"}).then(function(c){
        if (!c.isConfirmed || state.guardandoCuenta) return;
        state.guardandoCuenta = true;
        $("#btnGuardarCuenta").prop("disabled",true);
        $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:d}).done(function(r){
            if (!r || r.status!=="ok") return Swal.fire("Aviso",r && r.answer || "No se registró la cuenta.","warning");
            state.tokenNuevaCuenta = generarIdSolicitudVenta();
            if ($("#formCuentaNueva").length){$("#formCuentaNueva")[0].reset();$("#panelNuevaCuenta").addClass("d-none");}
            //if (state.moduloActual==="cuentas" && state.tipoCuentaActual===d.tipo) consultarCuentas();
            if (state.moduloActual==="cuentas" && state.tipoCuentaActual===d.tipo) consultarVistaCuentas();
            Swal.fire("Cuenta registrada",r.answer,"success");
        }).fail(function(xhr){Swal.fire("Error",xhr.responseJSON && xhr.responseJSON.answer || "No se pudo confirmar la operación. Reintenta sin modificar los datos.","error");})
        .always(function(){state.guardandoCuenta=false;$("#btnGuardarCuenta").prop("disabled",false);});
    });
}


function verDetalleCuenta(id) {
    const tipo = state.tipoCuentaActual;
    $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:{action:"getDetalleCuenta",tipo:tipo,id:id}}).done(function(r){
        if (!r || r.status!=="ok") return Swal.fire("Aviso",r && r.answer || "No se encontró la cuenta.","warning");
        const c = r.results.cuenta, movimientos = r.results.historial || [];
        const $contenido = $('<div class="text-start"></div>');
        $contenido.append($('<h5 class="fw-bold mb-2"></h5>').text(c.nombre));
        $contenido.append($('<p class="text-secondary small"></p>').text("Documento: "+(c.document_reference||"Sin referencia")+" · Vencimiento: "+(c.due_date||"No establecido")));
        $contenido.append($('<p class="small mb-3"></p>').text(c.description||""));
        const $saldos = $('<div class="row g-2 mb-3"></div>');
        [["Total",c.total_amount],["Pagado",c.paid_amount],["Pendiente",c.pending_amount]].forEach(function(a){

$saldos.append(
    $('<div class="col-sm-4"></div>').append(
        $('<div class="border rounded-3 p-2"></div>').append(
            $('<small class="d-block text-secondary"></small>').text(a[0]),
            $('<strong></strong>').text(monedaMXN(a[1]))
        )
    )
);

        });
        $contenido.append($saldos,$('<h6 class="fw-bold"></h6>').text(tipo==="COBRAR"?"Historial de abonos":"Historial de pagos"));
        const $tabla = $('<table class="table table-sm table-hover small"></table>');
        $tabla.append('<thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th class="text-end">Importe</th></tr></thead>');
        const $tbody = $("<tbody></tbody>");
        if (!movimientos.length) $tbody.append('<tr><td colspan="4" class="text-center text-secondary">Todavía no hay pagos registrados.</td></tr>');
        movimientos.forEach(function(m){
            const $fila = $("<tr>");
            [m.created_at,m.method,m.reference||"—",monedaMXN(m.amount)].forEach(function(v){$fila.append($("<td>").text(v));});
            $tbody.append($fila);
        });
        $tabla.append($tbody);
        $contenido.append($('<div class="table-responsive"></div>').append($tabla));
        Swal.fire({title:"Detalle de cuenta #"+id,html:$contenido[0],width:"780px",confirmButtonText:"Cerrar",confirmButtonColor:"#086b47"});
    }).fail(function(xhr){Swal.fire("Error",xhr.responseJSON && xhr.responseJSON.answer || "No se pudo consultar el historial.","error");});
}


function abrirAbonoCuenta(id,saldo) {
    if (!id || saldo<=0 || state.guardandoAbono) return;
    const tipo = state.tipoCuentaActual, identidad = tipo+"-"+id;
    if (state.abonoCuentaIdentidad!==identidad) {
        state.abonoCuentaIdentidad = identidad;
        state.tokenAbonoCuenta = generarIdSolicitudVenta();
    }
    if (!state.tokenAbonoCuenta) state.tokenAbonoCuenta = generarIdSolicitudVenta();

    const tpl = document.getElementById("tplAbonoCuenta");
    if (!tpl) return console.error("Falta tplAbonoCuenta");
    const contenido = tpl.content.firstElementChild.cloneNode(true);
    $(contenido).find("#abonoSaldoDisponible").text(monedaMXN(saldo));

    Swal.fire({
        title:tipo==="COBRAR" ? "Registrar cobro" : "Registrar pago",
        html:contenido,width:"600px",showCancelButton:true,
        confirmButtonText:tipo==="COBRAR" ? "Confirmar cobro" : "Confirmar pago",
        cancelButtonText:"Cancelar",confirmButtonColor:"#086b47",
        didOpen:function(){
            $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:{action:"getCajasVenta"}}).done(function(r){
                const $select = $("#abonoCaja");
                if (!$select.length) return;
                $select.empty().append($("<option>",{value:"",text:"Selecciona una caja abierta"}));
                if (!r || r.status!=="ok") return;
                (r.results||[]).forEach(function(c){$select.append($("<option>").val(c.id).text(c.name+" · #"+c.id));});
            }).fail(function(){if ($("#abonoCaja").length) $("#abonoCaja").html('<option value="">No se pudieron consultar las cajas</option>');});
        },
        preConfirm:function(){
            const monto = $("#abonoMonto").val(), metodo = $("#abonoMetodo").val();
            const cajaId = $("#abonoCaja").val(), referencia = $("#abonoReferencia").val().trim();
            const cantidad = numeroNotaValido(monto,0.01,99999999.99);
            if (cantidad===null || Math.round(cantidad*100)>Math.round(saldo*100)) {Swal.showValidationMessage("El importe debe ser mayor que cero y no superar el saldo.");return false;}
            if (metodo==="EFECTIVO" && !cajaId) {Swal.showValidationMessage("Selecciona una caja con turno abierto.");return false;}
            if (metodo==="TRANSFERENCIA" && !referencia) {Swal.showValidationMessage("Captura la referencia bancaria.");return false;}
            return {action:"registrarAbonoCuenta",tipo:tipo,id:id,solicitud_id:state.tokenAbonoCuenta,monto:monto,metodo:metodo,caja_id:metodo==="EFECTIVO"?cajaId:"",referencia:metodo==="TRANSFERENCIA"?referencia:"",notas:$("#abonoNotas").val().trim()};
        }
    }).then(function(c){
        if (!c.isConfirmed || !c.value || state.guardandoAbono) return;
        state.guardandoAbono = true;
        $.ajax({url:"php/index.php",type:"POST",dataType:"json",data:c.value}).done(function(r){
            if (!r || r.status!=="ok") return Swal.fire("Aviso",r && r.answer || "No se registró el movimiento.","warning");
            state.tokenAbonoCuenta = null;
            state.abonoCuentaIdentidad = "";
            //if (state.moduloActual==="cuentas" && state.tipoCuentaActual===tipo) consultarCuentas();
            if (state.moduloActual==="cuentas" && state.tipoCuentaActual===tipo) consultarVistaCuentas();
            Swal.fire({icon:"success",title:tipo==="COBRAR"?"Cobro registrado":"Pago registrado",text:r.results && r.results.duplicado ? "Esta operación ya estaba registrada; no se duplicó." : "Saldo pendiente: "+monedaMXN(r.results.saldo),confirmButtonColor:"#086b47"});
        }).fail(function(xhr){
            Swal.fire("Error",xhr.responseJSON && xhr.responseJSON.answer || "No fue posible confirmar el movimiento. Puedes reintentar sin duplicarlo.","error");
        }).always(function(){state.guardandoAbono=false;});
    });
}



function mostrarConcentradoCxc(id) {
    if (!Number.isInteger(id) || id<=0) return;

    Swal.fire({
        title:'Concentrado de cuentas por cobrar',
        html:`
            <div class="text-start">
                <p class="small text-secondary mb-3">Selecciona las cuentas que deseas incluir en el documento.</p>
                <label class="border rounded-3 p-3 d-block mb-2">
                    <input type="radio" name="scopeCxc" value="0" class="form-check-input me-2" checked>
                    <strong>Solo este crédito</strong>
                    <small class="d-block ms-4 text-secondary">Reporte individual del saldo pendiente.</small>
                </label>
                <label class="border rounded-3 p-3 d-block">
                    <input type="radio" name="scopeCxc" value="1" class="form-check-input me-2">
                    <strong>Concentrado completo del cliente</strong>
                    <small class="d-block ms-4 text-secondary">Incluye todas sus cuentas pendientes registradas.</small>
                </label>
                <div class="alert alert-warning small mt-3 mb-0">
                    Si el cliente no tiene ID registrado, las cuentas se agrupan por nombre exacto. Verifica que correspondan a la misma persona.
                </div>
            </div>`,
        width:'570px',
        showCancelButton:true,
        confirmButtonText:'Generar PDF',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#086b47',
        preConfirm:function(){
            return document.querySelector('input[name="scopeCxc"]:checked').value;
        }
    }).then(function(resultado){
        if (!resultado.isConfirmed) return;

        Swal.fire({
            title:'Generando concentrado',
            text:'Preparando las notas pendientes...',
            allowOutsideClick:false,
            allowEscapeKey:false,
            showConfirmButton:false,
            didOpen:function(){Swal.showLoading();}
        });

        $.ajax({
            type:'POST',
            url:'php/index.php',
            dataType:'json',
            data:{
                action:'generarConcentradoCxc',
                id:id,
                agrupar:resultado.value
            }
        }).done(function(r){
            if (!r || r.status!=='ok') {
                return Swal.fire('Aviso',r && r.answer || 'No se pudo generar el reporte.','warning');
            }

            const a=r.results||{};
            if (!a.archivo) {
                return Swal.fire('Error','No se recibió el archivo PDF.','error');
            }

            const carpeta=new URL('pdf_generados/cuentas_cobrar/',window.location.href);
            const url=new URL(a.archivo,window.location.href);

            if (url.origin!==carpeta.origin || !url.pathname.startsWith(carpeta.pathname)) {
                return Swal.fire('Error','La ruta del PDF no es válida.','error');
            }

            const $contenido=$('<div class="text-center"></div>');
            $contenido.append(
                $('<p class="mb-3"></p>').text(
                    a.cuentas+' cuenta(s) pendientes · '+monedaMXN(a.pendiente)
                )
            );

            $contenido.append(
                $('<a class="btn btn-success d-block mb-2" target="_blank" rel="noopener noreferrer"></a>')
                    .attr('href',url.href)
                    .text('Ver e imprimir concentrado')
            );

            $contenido.append(
                $('<a class="btn btn-outline-success d-block"></a>')
                    .attr({href:url.href,download:a.nombre})
                    .text('Descargar PDF')
            );

            Swal.fire({
                icon:'success',
                title:'Concentrado listo',
                html:$contenido[0],
                confirmButtonText:'Cerrar',
                confirmButtonColor:'#086b47'
            });
        }).fail(function(xhr){
            Swal.fire(
                'Error',
                xhr.responseJSON && xhr.responseJSON.answer || 'No se pudo generar el PDF.',
                'error'
            );
        });
    });
}



const DENOMINACIONES_CAJA = [
    {centavos:100000, nombre:"$1,000", tipo:"Billete"},
    {centavos:50000, nombre:"$500", tipo:"Billete"},
    {centavos:20000, nombre:"$200", tipo:"Billete"},
    {centavos:10000, nombre:"$100", tipo:"Billete"},
    {centavos:5000, nombre:"$50", tipo:"Billete"},
    {centavos:2000, nombre:"$20", tipo:"Billete"},
    {centavos:1000, nombre:"$10", tipo:"Moneda"},
    {centavos:500, nombre:"$5", tipo:"Moneda"},
    {centavos:200, nombre:"$2", tipo:"Moneda"},
    {centavos:100, nombre:"$1", tipo:"Moneda"},
    {centavos:50, nombre:"$0.50", tipo:"Moneda"}
];

function renderDenominacionesCaja() {
    const $contenedor = $("#denominacionesCaja").empty();

    DENOMINACIONES_CAJA.forEach(function(d) {
        const $col = $('<div class="col-6 col-md-4 col-xl-3"></div>');
        const $card = $('<div class="denom-caja-card"></div>');
        const $label = $('<label class="form-label small fw-semibold mb-2"></label>')
            .text(d.tipo + " " + d.nombre);

        const $input = $('<input type="number" class="form-control js-denominacion-caja" min="0" max="100000" step="1" inputmode="numeric" placeholder="0">')
            .attr("data-centavos", d.centavos)
            .attr("aria-label", d.tipo + " de " + d.nombre);

        const $subtotal = $('<small class="denom-subtotal"></small>').text("$0.00");

        $card.append($label, $input, $subtotal);
        $col.append($card);
        $contenedor.append($col);
    });

    actualizarDenominacionesCaja();
}

function actualizarDenominacionesCaja() {
    let total = 0;
    let valido = true;
    const desglose = {};

    $("#denominacionesCaja .js-denominacion-caja").each(function() {
        const centavos = Number($(this).attr("data-centavos"));
        const valor = this.value.trim();
        const correcto = valor === "" || (/^(0|[1-9][0-9]{0,5})$/.test(valor) && Number(valor) <= 100000);

        $(this).toggleClass("is-invalid", !correcto);

        if (!correcto) {
            valido = false;
            $(this).siblings(".denom-subtotal").text("Cantidad inválida");
            return;
        }

        const cantidad = valor === "" ? 0 : Number(valor);
        const subtotal = centavos * cantidad;

        desglose[String(centavos)] = cantidad;
        total += subtotal;

        $(this).siblings(".denom-subtotal").text(monedaMXN(subtotal / 100));
    });

    if (total > 9999999999) valido = false;

    $("#contadoCaja").val(valido ? (total / 100).toFixed(2) : "");

    const esperado = Math.round(Number($("#cajaEsperado").attr("data-valor") || 0) * 100);
    const diferencia = total - esperado;

    $("#diferenciaArqueoCaja")
        .text(valido ? monedaMXN(diferencia / 100) : "Revisa las cantidades")
        .toggleClass("text-danger", !valido || diferencia < 0)
        .toggleClass("text-success", valido && diferencia >= 0);

    return {valido:valido, total:total, desglose:desglose};
}



$(document).on("submit", "#formCierreCaja", function(e) {
    e.preventDefault();

    const arqueo = actualizarDenominacionesCaja();

    if (!arqueo.valido) {
        Swal.fire("Aviso", "Revisa las cantidades de billetes y monedas.", "warning");
        return;
    }

    if (!state.cajaId) {
        Swal.fire("Aviso", "Selecciona una caja válida.", "warning");
        return;
    }

    const contado = (arqueo.total / 100).toFixed(2);
    const esperado = Math.round(Number($("#cajaEsperado").attr("data-valor") || 0) * 100);
    const diferencia = (arqueo.total - esperado) / 100;

    operarCaja("cerrarCaja", {
        caja_id:state.cajaId,
        contado:contado,
        denominaciones:JSON.stringify(arqueo.desglose)
    }, "¿Cerrar turno? Diferencia: " + monedaMXN(diferencia));
});



function renderListadoVentas() {
    const tpl = document.getElementById("tplListadoVentas");
    $("#contenidoModulo").empty().append(tpl.content.cloneNode(true));
    $("#ventasBuscar").val(state.filtrosVentas.buscar);
    $("#ventasTipo").val(state.filtrosVentas.tipo);
    $("#ventasDesde").val(state.filtrosVentas.inicio);
    $("#ventasHasta").val(state.filtrosVentas.fin);
    consultarListadoVentas(1);
}

function consultarListadoVentas(pagina) {
    const version = state.versionVista, solicitud = ++state.peticionVentas;
    const parametros = Object.assign({action:"getVentas",pagina:pagina},state.filtrosVentas);

    $("#ventasResultado").text("Consultando ventas...");
    $("#tablaListadoVentas").html('<tr><td colspan="6" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Cargando historial...</td></tr>');
    $("#ventasPaginacion").empty();

    $.ajax({
        url:"php/index.php",type:"POST",dataType:"json",data:parametros
    }).done(function(r) {
        if (version !== state.versionVista || solicitud !== state.peticionVentas) return;

        if (!r || r.status !== "ok") {
            $("#tablaListadoVentas").html('<tr><td colspan="6" class="text-center text-danger py-4">No se pudo cargar el historial.</td></tr>');
            $("#ventasResultado").text("Consulta no disponible");
            return Swal.fire("Aviso",r && r.answer || "No fue posible consultar las ventas.","warning");
        }

        const datos = r.results, $tabla = $("#tablaListadoVentas").empty();
        $("#ventasResultado").text(datos.total + (Number(datos.total) === 1 ? " venta encontrada" : " ventas encontradas"));

        if (!datos.ventas.length) {
            $tabla.append('<tr><td colspan="6" class="text-center text-secondary py-5">No hay ventas que coincidan con la búsqueda.</td></tr>');
        }

        datos.ventas.forEach(function(v) {
            const esNota = v.sale_kind === "NOTA", pendientes = Number(v.pending_amount) > 0;
            const folio = (esNota ? v.note_folio : v.ticket_barcode) || ("#" + v.id);
            const $tr = $("<tr>");
            const $descripcion = $('<div class="d-grid gap-1" style="min-width:245px"></div>');

            $descripcion.append($("<strong>").text((esNota ? "Nota " : "Venta ") + folio));
            $descripcion.append($('<small class="text-secondary"></small>').text(v.created_at || ""));

            const $items = $('<div class="small mt-1"></div>');
            (v.items || []).slice(0,3).forEach(function(item) {
                $items.append($("<div>").text(item.quantity + " × " + (item.manual_name || "Artículo") + " · " + monedaMXN(item.price)));
            });

            if ((v.items || []).length > 3) {
                const $extra = $('<details class="text-secondary"></details>');
                $extra.append($("<summary>").text("Ver " + (v.items.length - 3) + " artículos más"));
                v.items.slice(3).forEach(function(item) {
                    $extra.append($("<div>").text(item.quantity + " × " + (item.manual_name || "Artículo") + " · " + monedaMXN(item.price)));
                });
                $items.append($extra);
            }

            $descripcion.append($items);

            const $pago = $('<div class="d-grid gap-2" style="min-width:130px"></div>');
            $pago.append($('<span class="small fw-semibold"></span>').text(v.payment_method || "—"));
            $pago.append($('<span class="badge rounded-pill align-self-start"></span>')
                .addClass(pendientes ? "text-bg-warning" : "bg-success-subtle text-success")
                .text(pendientes ? "Saldo pendiente" : "Liquidada"));

            const $menu = $('<ul class="dropdown-menu dropdown-menu-end shadow-sm"></ul>');

            if (esNota) {
                $menu.append($("<li>").append(
                    $('<button type="button" class="dropdown-item js-pdf-venta"><i class="bi bi-file-earmark-pdf me-2"></i>Obtener nota PDF</button>').attr("data-id",v.id)
                ));
            } else {
                $menu.append($("<li>").append(
                    '<span class="dropdown-item text-secondary disabled">Ticket 80 mm · Próximamente</span>'
                ));
            }

            const $acciones = $('<div class="dropdown"></div>');
            $acciones.append(
                '<button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Acciones</button>',
                $menu
            );

            $tr.append($("<td>").append($descripcion));
            $tr.append($("<td>").append($pago));
            $tr.append($("<td>").text(esNota ? (v.seller_name || "—") : "Mostrador"));
            $tr.append($("<td>").text(esNota ? (v.client_name || "—") : "Mostrador"));
            $tr.append($('<td class="text-end fw-bold text-nowrap"></td>').text(monedaMXN(v.total_amount)));
            $tr.append($("<td>").append($acciones));
            $tabla.append($tr);
        });

        const $pag = $("#ventasPaginacion").empty();

        if (Number(datos.paginas) > 1) {
            $pag.append($('<button type="button" class="btn btn-outline-secondary btn-sm js-pagina-ventas">Anterior</button>')
                .attr("data-pagina",Number(datos.pagina)-1)
                .prop("disabled",Number(datos.pagina)<=1));

            $pag.append($('<span class="small text-secondary px-2"></span>')
                .text("Página " + datos.pagina + " de " + datos.paginas));

            $pag.append($('<button type="button" class="btn btn-outline-secondary btn-sm js-pagina-ventas">Siguiente</button>')
                .attr("data-pagina",Number(datos.pagina)+1)
                .prop("disabled",Number(datos.pagina)>=Number(datos.paginas)));
        }

    }).fail(function(xhr) {
        if (version !== state.versionVista || solicitud !== state.peticionVentas) return;

        $("#ventasResultado").text("No se pudo consultar el historial");
        $("#tablaListadoVentas").html('<tr><td colspan="6" class="text-center text-danger py-4">Error al consultar las ventas.</td></tr>');
        Swal.fire("Error",xhr.responseJSON && xhr.responseJSON.answer || "Error de conexión.","error");
    });
}
