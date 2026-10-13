
document.addEventListener('DOMContentLoaded', () => {
    if (new URLSearchParams(window.location.search).get('modal') === '1') {
        document.body.classList.add('csm-commissions-embedded');
    }

    const user = typeof requireAuth === 'function' ? requireAuth() : null;
    if (!user) return;

    if (String(user.role || '').toUpperCase() !== 'ADMIN') {
        window.location.replace('dashboard.html');
        return;
    }

    // Configuración de la API y sesión
    const API_URL = '../backend/index.php';
    const SESSION = 1;
    const TIPO_TARIFA_PAGO = 1;
    const colorPrincipal = '#0d704d';

    // Elementos HTML
    const modalElement = document.getElementById('modalComision');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const form = document.getElementById('formComision');
    const idInput = document.getElementById('comisionId');
    const nombreInput = document.getElementById('comisionNombre');
    const porcentajeInput = document.getElementById('comisionPorcentaje');
    const montoInput = document.getElementById('comisionMontoExtra');
    const btnGuardar = document.getElementById('btnGuardarComision');
    const tabla = document.getElementById('comisionesTableBody');
    const listaMobile = document.getElementById('comisionesMobileList');
    const buscador = document.getElementById('buscarComision');
    const loading = document.getElementById('comisionesLoading');
    const contenido = document.getElementById('comisionesContenido');
    const vacio = document.getElementById('comisionesVacio');

    let comisiones = [];
    let guardando = false;

    // Formatos
    const formatoMoneda = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 4
    });

    const moneda = valor => formatoMoneda.format(Number(valor) || 0);
    const porcentaje = valor => `${(Number(valor) || 0).toFixed(4)}%`;
    const normalizar = valor => String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    function fechaLegible(fecha) {
        if (!fecha) return '—';
        const partes = String(fecha).slice(0, 10).split('-');
        return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : String(fecha);
    }

    function alerta(icon, title, text) {
        return Swal.fire({
            icon,
            title,
            text,
            confirmButtonText: 'Entendido',
            confirmButtonColor: colorPrincipal
        });
    }

    function notificar(texto) {
        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: texto,
            showConfirmButton: false,
            timer: 2400,
            timerProgressBar: true
        });
    }

    // Peticiones al index.php mediante AJAX y FormData
    async function enviar(action, campos = {}) {
        if (!SESSION) {
            throw new Error('No se encontró la sesión de la cuenta. Verifica el identificador utilizado por main.js.');
        }

        const data = new FormData();
        data.append('session', SESSION);
        data.append('action', action);

        Object.entries(campos).forEach(([clave, valor]) => {
            data.append(clave, valor == null ? '' : String(valor));
        });

        let respuesta;

        try {
            respuesta = await $.ajax({
                url: API_URL,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json',
                timeout: 20000
            });
        } catch (error) {
            const mensaje = error.responseJSON?.answer || error.responseJSON?.error ||
                (error.status === 0 ? 'No fue posible conectar con el servidor.' : `Error de comunicación (HTTP ${error.status}).`);

            throw new Error(mensaje);
        }

        if (!respuesta || respuesta.status !== 'ok') {
            throw new Error(respuesta?.answer || 'El servidor no pudo completar la operación.');
        }

        return respuesta;
    }

    // Estados del listado
    function mostrarEstado(estado, titulo = '', descripcion = '', accion = 'crear') {
        loading.hidden = estado !== 'cargando';
        loading.style.display = estado === 'cargando' ? 'flex' : 'none';
        contenido.hidden = estado !== 'datos';
        vacio.hidden = estado !== 'vacio';

        if (estado === 'vacio') {
            document.getElementById('comisionesVacioTitulo').textContent = titulo;
            document.getElementById('comisionesVacioTexto').textContent = descripcion;

            const btn = document.getElementById('btnAccionVacio');
            btn.dataset.accion = accion;
            btn.textContent = accion === 'reintentar' ? 'Reintentar' : (accion === 'limpiar' ? 'Limpiar búsqueda' : 'Agregar tarifa');
        }
    }

    // Actualizar indicadores
    function actualizarResumen() {
        const total = comisiones.length;
        const promedioPct = total ? comisiones.reduce((suma, item) => suma + Number(item.porcentaje || 0), 0) / total : 0;
        const promedioMonto = total ? comisiones.reduce((suma, item) => suma + Number(item.monto_extra || 0), 0) / total : 0;

        document.getElementById('resumenTotal').textContent = String(total);
        document.getElementById('resumenPorcentaje').textContent = porcentaje(promedioPct);
        document.getElementById('resumenMonto').textContent = moneda(promedioMonto);
        document.getElementById('contadorTarifas').textContent = String(total);
    }

    // Crear botones de edición y eliminación
    function crearBoton(accion, id, icono, texto) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `csm-commissions-icon-action${accion === 'eliminar' ? ' is-danger' : ''}`;
        btn.dataset.accion = accion;
        btn.dataset.id = String(id);
        btn.title = texto;
        btn.setAttribute('aria-label', texto);

        const icon = document.createElement('i');
        icon.className = `bi ${icono}`;
        icon.setAttribute('aria-hidden', 'true');
        btn.appendChild(icon);

        return btn;
    }

    // Nombre e icono de una tarifa
    function crearNombre(item) {
        const nombre = document.createElement('div');
        nombre.className = 'csm-commissions-name';
        nombre.innerHTML = '<span class="csm-commissions-name-icon"><i class="bi bi-credit-card" aria-hidden="true"></i></span><strong></strong>';
        nombre.querySelector('strong').textContent = item.nombre;
        return nombre;
    }

    // Generar tabla de escritorio
    function renderizarTabla(items) {
        const fragment = document.createDocumentFragment();

        items.forEach(item => {
            const tr = document.createElement('tr');
            const tdNombre = document.createElement('td');
            const tdPct = document.createElement('td');
            const tdMonto = document.createElement('td');
            const tdFecha = document.createElement('td');
            const tdAcciones = document.createElement('td');

            const spanPct = document.createElement('span');
            spanPct.className = 'csm-commissions-percent';
            spanPct.textContent = porcentaje(item.porcentaje);

            tdNombre.appendChild(crearNombre(item));
            tdPct.appendChild(spanPct);
            tdMonto.className = 'csm-commissions-amount';
            tdMonto.textContent = moneda(item.monto_extra);
            tdFecha.className = 'csm-commissions-date';
            tdFecha.textContent = fechaLegible(item.fecha_alta);
            tdAcciones.className = 'text-end';

            const acciones = document.createElement('div');
            acciones.className = 'csm-commissions-actions';
            acciones.append(
                crearBoton('editar', item.id, 'bi-pencil-square', 'Editar ' + item.nombre),
                crearBoton('eliminar', item.id, 'bi-trash3', 'Eliminar ' + item.nombre)
            );

            tdAcciones.appendChild(acciones);
            tr.append(tdNombre, tdPct, tdMonto, tdFecha, tdAcciones);
            fragment.appendChild(tr);
        });

        tabla.replaceChildren(fragment);
    }

    // Generar tarjetas para dispositivos móviles
    function renderizarMobile(items) {
        const fragment = document.createDocumentFragment();

        items.forEach(item => {
            const card = document.createElement('article');
            card.className = 'csm-commissions-mobile-card';
            card.innerHTML = '<div class="csm-commissions-mobile-card-header"></div><div class="csm-commissions-mobile-card-values"></div><div class="csm-commissions-mobile-card-footer"></div>';

            const cabecera = card.querySelector('.csm-commissions-mobile-card-header');
            const acciones = document.createElement('div');
            acciones.className = 'csm-commissions-actions';

            acciones.append(
                crearBoton('editar', item.id, 'bi-pencil-square', 'Editar ' + item.nombre),
                crearBoton('eliminar', item.id, 'bi-trash3', 'Eliminar ' + item.nombre)
            );

            cabecera.append(crearNombre(item), acciones);

            const valores = card.querySelector('.csm-commissions-mobile-card-values');

            const pct = document.createElement('div');
            pct.innerHTML = '<small>Porcentaje</small>';

            const pctValor = document.createElement('span');
            pctValor.className = 'csm-commissions-percent';
            pctValor.textContent = porcentaje(item.porcentaje);
            pct.appendChild(pctValor);

            const fijo = document.createElement('div');
            fijo.innerHTML = '<small>Cargo fijo</small>';

            const fijoValor = document.createElement('strong');
            fijoValor.textContent = moneda(item.monto_extra);
            fijo.appendChild(fijoValor);

            valores.append(pct, fijo);

            const pie = card.querySelector('.csm-commissions-mobile-card-footer');
            const fecha = document.createElement('span');
            fecha.className = 'csm-commissions-date';
            fecha.textContent = 'Registrada el ' + fechaLegible(item.fecha_alta);
            pie.appendChild(fecha);

            fragment.appendChild(card);
        });

        listaMobile.replaceChildren(fragment);
    }

    // Mostrar las comisiones obtenidas de PHP
    function renderizar() {
        const filtro = normalizar(buscador.value);
        const items = comisiones.filter(item => normalizar(item.nombre).includes(filtro));

        document.getElementById('textoResultados').textContent = `${items.length} de ${comisiones.length} tarifas`;

        actualizarResumen();

        if (items.length === 0) {
            mostrarEstado(
                'vacio',
                filtro ? 'Sin coincidencias' : 'No hay tarifas registradas',
                filtro ? 'Prueba con otro nombre para encontrar una tarifa.' : 'Agrega tu primera tarifa de pago para comenzar.',
                filtro ? 'limpiar' : 'crear'
            );
            return;
        }

        renderizarTabla(items);
        renderizarMobile(items);
        mostrarEstado('datos');
    }

    // Obtener comisiones desde la base de datos
    async function cargarComisiones() {
        mostrarEstado('cargando');

        const recargar = document.getElementById('btnRecargarComisiones');
        recargar.disabled = true;

        try {
            const respuesta = await enviar('getComisiones', {
                tipo: TIPO_TARIFA_PAGO
            });

            if (!Array.isArray(respuesta.results)) {
                throw new Error('La respuesta del listado no tiene el formato esperado.');
            }

            comisiones = respuesta.results
                .filter(item => Number(item.tipo) === TIPO_TARIFA_PAGO)
                .map(item => ({
                    id: Number(item.id),
                    nombre: String(item.nombre || ''),
                    tipo: Number(item.tipo),
                    porcentaje: String(item.porcentaje || '0'),
                    monto_extra: String(item.monto_extra || '0'),
                    fecha_alta: item.fecha_alta || ''
                }));

            renderizar();
        } catch (error) {
            mostrarEstado('vacio', 'No se pudo cargar el listado', error.message, 'reintentar');
            await alerta('error', 'Error al consultar comisiones', error.message);
        } finally {
            recargar.disabled = false;
        }
    }

    // Calcular ejemplo de comisión
    function actualizarEjemplo() {
        const pct = Number(porcentajeInput.value) || 0;
        const extra = Number(montoInput.value) || 0;
        const total = 1000 * pct / 100 + extra;

        document.getElementById('comisionEjemplo').textContent = moneda(Math.max(total, 0));
    }

    // Abrir formulario de nueva tarifa
    function abrirNueva() {
        form.reset();
        form.classList.remove('was-validated');
        idInput.value = '';
        porcentajeInput.value = '0.0000';
        montoInput.value = '0.0000';

        document.getElementById('modalComisionTitulo').textContent = 'Nueva tarifa de pago';
        document.getElementById('textoGuardarComision').textContent = 'Guardar tarifa';

        actualizarEjemplo();
        modal.show();
    }

    // Abrir formulario de edición
    function abrirEdicion(id) {
        const item = comisiones.find(registro => registro.id === id);

        if (!item) {
            return alerta('warning', 'Tarifa no encontrada', 'Actualiza el listado e intenta nuevamente.');
        }

        form.reset();
        form.classList.remove('was-validated');
        idInput.value = String(item.id);
        nombreInput.value = item.nombre;
        porcentajeInput.value = Number(item.porcentaje).toFixed(4);
        montoInput.value = Number(item.monto_extra).toFixed(4);

        document.getElementById('modalComisionTitulo').textContent = 'Editar tarifa de pago';
        document.getElementById('textoGuardarComision').textContent = 'Guardar cambios';

        actualizarEjemplo();
        modal.show();
    }

    // Guardar o editar comisión
    async function guardarComision(evento) {
        evento.preventDefault();

        if (guardando) return;

        form.classList.add('was-validated');

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const nombre = nombreInput.value.trim();
        const pct = Number(porcentajeInput.value);
        const extra = Number(montoInput.value);

        if (!nombre || new TextEncoder().encode(nombre).length > 150 ||
            !Number.isFinite(pct) || !Number.isFinite(extra) ||
            pct < 0 || pct > 100 || extra < 0 || extra > 9999999999) {
            await alerta('warning', 'Datos inválidos', 'Revisa el nombre, el porcentaje y el cargo fijo.');
            return;
        }

        const id = Number(idInput.value) || 0;
        const action = id > 0 ? 'updateComisiones' : 'setComisiones';

        const payload = {
            tipo: TIPO_TARIFA_PAGO,
            nombre,
            porcentaje: pct.toFixed(4),
            monto_extra: extra.toFixed(4)
        };

        if (id > 0) payload.id = id;

        guardando = true;
        btnGuardar.disabled = true;
        document.getElementById('textoGuardarComision').textContent = 'Guardando...';

        try {
            const respuesta = await enviar(action, payload);

            modal.hide();
            await notificar(respuesta.answer || 'Tarifa guardada correctamente');
            await cargarComisiones();
        } catch (error) {
            await alerta('error', 'No se pudo guardar la tarifa', error.message);
        } finally {
            guardando = false;
            btnGuardar.disabled = false;
            document.getElementById('textoGuardarComision').textContent = id > 0 ? 'Guardar cambios' : 'Guardar tarifa';
        }
    }

    // Eliminar comisión de manera lógica
    async function eliminarComision(id) {
        const item = comisiones.find(registro => registro.id === id);

        if (!item) {
            return alerta('warning', 'Tarifa no encontrada', 'Actualiza el listado e intenta nuevamente.');
        }

        const confirmacion = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar esta tarifa?',
            text: `Se eliminará del listado: ${item.nombre}.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#c24145',
            reverseButtons: true
        });

        if (!confirmacion.isConfirmed) return;

        document.querySelectorAll(`[data-accion="eliminar"][data-id="${id}"]`).forEach(btn => {
            btn.disabled = true;
        });

        try {
            const respuesta = await enviar('eliminarComisiones', { id });

            await notificar(respuesta.answer || 'Tarifa eliminada correctamente');
            await cargarComisiones();
        } catch (error) {
            await alerta('error', 'No se pudo eliminar la tarifa', error.message);

            document.querySelectorAll(`[data-accion="eliminar"][data-id="${id}"]`).forEach(btn => {
                btn.disabled = false;
            });
        }
    }

    // Eventos principales
    document.getElementById('btnNuevaComision').addEventListener('click', abrirNueva);
    document.getElementById('btnRecargarComisiones').addEventListener('click', cargarComisiones);

    document.getElementById('btnAccionVacio').addEventListener('click', event => {
        const accion = event.currentTarget.dataset.accion;

        if (accion === 'reintentar') {
            cargarComisiones();
        } else if (accion === 'limpiar') {
            buscador.value = '';
            renderizar();
        } else {
            abrirNueva();
        }
    });

    buscador.addEventListener('input', renderizar);
    porcentajeInput.addEventListener('input', actualizarEjemplo);
    montoInput.addEventListener('input', actualizarEjemplo);
    form.addEventListener('submit', guardarComision);

    // Detectar clics en editar y eliminar
    contenido.addEventListener('click', event => {
        const btn = event.target.closest('button[data-accion][data-id]');

        if (!btn || btn.disabled) return;

        const id = Number(btn.dataset.id);

        if (btn.dataset.accion === 'editar') abrirEdicion(id);
        if (btn.dataset.accion === 'eliminar') eliminarComision(id);
    });

    modalElement.addEventListener('shown.bs.modal', () => {
        nombreInput.focus();
    });

    // Primera carga
    if (!SESSION) {
        mostrarEstado(
            'vacio',
            'Falta configurar la sesión',
            'El identificador de sesión no se encontró. Revisa SESSION en assets/js/commissions.js.',
            'reintentar'
        );

        alerta(
            'warning',
            'Sesión sin configurar',
            'El frontend no encontró el valor que debe enviarse como session al index.php.'
        );
    } else {
        cargarComisiones();
    }
});
