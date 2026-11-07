
$(function(){
    const modulos = {
        ventas:{nombre:'Ventas',items:[
            {id:'nota',nombre:'Ventas con nota',icono:'receipt',descripcion:'Registra notas foleadas, clientes, artículos y formas de pago.'},
            {id:'rapida',nombre:'Venta rápida',icono:'cart3',descripcion:'Registra ventas de mostrador sin datos de cliente.'}
        ]},
        caja:{nombre:'Caja y egresos',items:[
            {id:'arqueo',nombre:'Control de caja',icono:'wallet2',descripcion:'Apertura, movimientos de efectivo y cierre de caja.'},
            {id:'egresos',nombre:'Gastos y salidas',icono:'arrow-up-right-circle',descripcion:'Registra gastos y pagos en efectivo o transferencia.'}
        ]},
        cuentas:{nombre:'Cuentas',items:[
            {id:'cobrar',nombre:'Cuentas por cobrar',icono:'person-check',descripcion:'Consulta deudores, créditos, abonos y saldos pendientes.'},
            {id:'pagar',nombre:'Cuentas por pagar',icono:'credit-card',descripcion:'Control de compromisos y pagos a proveedores.'}
        ]},
        panel:{nombre:'Panel y reportes',items:[
            {id:'resumen',nombre:'Resumen general',icono:'grid-1x2',descripcion:'Indicadores de ingresos, egresos y movimientos recientes.'},
            {id:'reportes',nombre:'Reportes',icono:'bar-chart',descripcion:'Consulta reportes de ventas, egresos, clientes y caja.'}
        ]}
    };

    let moduloActual = null;
    let submoduloActual = null;

    function mostrarInicio(){
        moduloActual = null;
        submoduloActual = null;
        $('#vistaInicio').removeClass('d-none');
        $('#vistaModulo').addClass('d-none');
        $('#marcaSistema').removeClass('d-none');
        $('#navSubmodulos').addClass('d-none').empty();
        window.scrollTo(0,0);
    }

    function mostrarSubmodulo(id){
        if(!moduloActual || !modulos[moduloActual]) return;
        const item = modulos[moduloActual].items.find(x=>x.id===id);
        if(!item) return;
        submoduloActual = id;
        $('#categoriaModulo').text(modulos[moduloActual].nombre.toUpperCase());
        $('#tituloPantalla').text(item.nombre);
        $('#descripcionPantalla').text(item.descripcion);
        $('#iconoPantalla').attr('class','bi bi-'+item.icono);
        $('.tab-modulo').removeClass('active').removeAttr('aria-current');
        $('.tab-modulo').filter(function(){return $(this).data('submodulo')===id}).addClass('active').attr('aria-current','page');
        $('#contenidoModulo').html('<div class="estado-vacio"><i class="bi bi-ui-checks-grid"></i><h2 class="h5 mt-3">Área lista para desarrollar</h2><p class="texto-secundario">Aquí construiremos '+item.nombre.toLowerCase()+'.</p></div>');
    }

    function abrirModulo(id){
        if(!modulos[id]) return;
        moduloActual = id;
        $('#vistaInicio').addClass('d-none');
        $('#vistaModulo').removeClass('d-none');
        $('#marcaSistema').addClass('d-none');
        const $nav = $('#navSubmodulos').empty().removeClass('d-none');
        modulos[id].items.forEach(function(item){
            $('<button>',{type:'button',class:'tab-modulo',text:item.nombre}).attr('data-submodulo',item.id).appendTo($nav);
        });
        mostrarSubmodulo(modulos[id].items[0].id);
        window.scrollTo(0,0);
    }

    $(document).on('click','.js-abrir-modulo',function(){abrirModulo($(this).data('modulo'));});
    $(document).on('click','.tab-modulo',function(){mostrarSubmodulo($(this).data('submodulo'));});
    $('#btnInicio').on('click',mostrarInicio);
    $('#btnConfiguracion').on('click',function(){
        moduloActual = null;
        submoduloActual = null;
        $('#vistaInicio').addClass('d-none');
        $('#vistaModulo').removeClass('d-none');
        $('#marcaSistema').removeClass('d-none');
        $('#navSubmodulos').addClass('d-none').empty();
        $('#categoriaModulo').text('SISTEMA');
        $('#tituloPantalla').text('Configuración');
        $('#descripcionPantalla').text('Datos del negocio y preferencias generales.');
        $('#iconoPantalla').attr('class','bi bi-gear');
        $('#contenidoModulo').html('<div class="estado-vacio"><i class="bi bi-gear"></i><h2 class="h5 mt-3">Configuración</h2><p class="texto-secundario">Aquí configuraremos los datos del negocio y las preferencias del sistema.</p></div>');
        window.scrollTo(0,0);
    });
});
