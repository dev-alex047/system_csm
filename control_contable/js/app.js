

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
state.ventaNota = crearBorradorVentaNota();
state.ventaRapida = crearBorradorVentaRapida();

// INICIALIZACIÓN
window.addEventListener("DOMContentLoaded", function() {
    bindNavigation();
    bindVentaNota();
    bindVentaRapida();
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
    if (modulo === "ventas" && submodulo === "nota") {
    renderVentaConNota();
    return;
}

if (modulo === "ventas" && submodulo === "rapida") {
    renderVentaRapida();
    return;
}
    if (modulo === "panel" && submodulo === "resumen") {
        getResumenInicial();
        return;
    }

    mostrarContenidoPendiente(item.nombre);
}


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
    return {forma_pago:"EFECTIVO",referencia:"",observaciones:"",items:[],siguienteId:1};
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
}

function renderVentaRapida() {
    const template = document.getElementById("tplVentaRapida");
    if (!template) {console.error("No existe tplVentaRapida");return;}

    $("#contenidoModulo").empty().append(template.content.cloneNode(true));
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
    $("#rapidaReferenciaBox").toggleClass("d-none",state.ventaRapida.forma_pago !== "TRANSFERENCIA");
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
    $resumen.append($('<p class="text-secondary mb-0"></p>').text("Esta es una vista previa. La venta todavía no se ha guardado en MySQL."));

    Swal.fire({title:"Revisar venta rápida",html:$resumen[0],confirmButtonText:"Volver a la venta",confirmButtonColor:"#086b47",width:"600px"});
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
            renderVentaConNota();
        }

        
Swal.fire({
    icon:"success",
    title:resultado.duplicada ? "Venta ya registrada" : "Venta guardada",
    html:"<strong>Folio:</strong> " + $("<div>").text(folio).html() +
         "<p class='mb-0 mt-2'>La operación quedó registrada en MySQL.</p>",
    showCancelButton:true,
    confirmButtonText:'<i class="bi bi-file-earmark-pdf me-2"></i>Generar nota PDF',
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
        Swal.fire("Error", data.answer || "No fue posible generar el PDF.", "error");
    });
}