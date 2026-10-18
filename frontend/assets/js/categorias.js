
/* Sistema CSM · Categorías (pantalla independiente dentro de iframe) */
var categoriasEstado = {
    tipo: 1, buscar: '', pagina: 1, limite: 12, paginas: 1, total: 0,
    vista: 'tarjetas', resultados: [], secuencia: 0, formularioSecuencia: 0,
    quitarImagen: false, imagenOriginal: '', imagenTemporal: '', guardando: false, eliminando: false
};

function categoriasAPI(accion, campos) {
    var formData = new FormData();
    formData.append('action', accion);
    $.each(campos || {}, function (clave, valor) {
        if (valor instanceof File) formData.append(clave, valor, valor.name);
        else formData.append(clave, valor == null ? '' : valor);
    });
    return $.ajax({
        type: 'POST', url: 'php/index.php', data: formData, dataType: 'json',
        processData: false, contentType: false, xhrFields: { withCredentials: true }
    }).then(function (respuesta) {
        if (!respuesta || respuesta.status !== 'ok') throw new Error(respuesta && respuesta.answer ? respuesta.answer : 'Respuesta inválida del servidor.');
        return respuesta;
    }, function (xhr) {
        throw new Error(xhr.responseJSON && xhr.responseJSON.answer ? xhr.responseJSON.answer : 'No se pudo conectar con el servidor (HTTP ' + (xhr.status || 0) + ').');
    });
}

function categoriasEscapar(valor) {
    return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function categoriasImagen(ruta) {
    var p = String(ruta || '').trim();
    return /^uploads\/categorias\/[a-z0-9_-]+\.(jpg|jpeg|png|webp)$/i.test(p) ? 'php/' + p : '';
}

function categoriasPrevisualizar(ruta, temporal) {
    if (categoriasEstado.imagenTemporal) URL.revokeObjectURL(categoriasEstado.imagenTemporal);
    categoriasEstado.imagenTemporal = temporal ? ruta : '';
    $('#categoriaImagenPreview').attr('src', ruta || '').toggleClass('d-none', !ruta);
    $('#categoriaImagenVacia').toggleClass('d-none', !!ruta);
    $('#categoriaQuitarImagen').prop('disabled', !ruta);
}

function categoriasVistaEditor(abrir) {
    $('#categoriasPanelLista').toggleClass('d-none', abrir);
    $('#categoriasPanelFormulario').toggleClass('d-none', !abrir);
    $('#categoriasFormTitulo').text($('#categoriaId').val() ? 'Editar categoría' : 'Nueva categoría');
    window.scrollTo(0, 0);
}

function categoriasDibujar() {
    var e = categoriasEstado, tarjetas = '', filas = '';

    $.each(e.resultados, function (_, c) {
        var id = Number(c.id), nombre = categoriasEscapar(c.nombre || 'Sin nombre');
        var descripcion = categoriasEscapar(c.descripcion || 'Sin descripción');
        var src = categoriasImagen(c.imagen);
        var foto = src ? '<img src="' + categoriasEscapar(src) + '" alt="" loading="lazy">' : '<i class="bi bi-tags" aria-hidden="true"></i>';
        var activo = Number(c.activo) === 1;
        var estado = '<span class="csm-cat-status' + (activo ? '' : ' is-inactive') + '">' + (activo ? 'Activa' : 'Inactiva') + '</span>';
        var acciones = '<button type="button" class="btn btn-sm btn-outline-secondary" data-cat-action="edit" data-id="' + id + '" title="Editar" aria-label="Editar ' + nombre + '"><i class="bi bi-pencil"></i></button>' +
                      '<button type="button" class="btn btn-sm btn-outline-danger" data-cat-action="delete" data-id="' + id + '" title="Eliminar" aria-label="Eliminar ' + nombre + '"><i class="bi bi-trash"></i></button>';

        tarjetas += '<div class="col"><article class="csm-cat-card h-100">' +
            '<div class="d-flex gap-3"><div class="csm-cat-card-photo">' + foto + '</div>' +
            '<div class="flex-grow-1" style="min-width:0"><div class="csm-cat-card-title">' + nombre + '</div>' + estado + '</div></div>' +
            '<p class="csm-cat-description">' + descripcion + '</p>' +
            '<div class="d-flex justify-content-between align-items-center"><small class="text-body-secondary">#' + id + '</small><div class="d-flex gap-2">' + acciones + '</div></div>' +
            '</article></div>';

        filas += '<tr><td><div class="csm-cat-list-photo">' + foto + '</div></td>' +
            '<td><strong>' + nombre + '</strong></td><td><div class="csm-cat-list-description">' + descripcion + '</div></td>' +
            '<td>' + estado + '</td><td>' + categoriasEscapar(String(c.fecha_alta || '').slice(0, 10)) + '</td>' +
            '<td><div class="d-flex justify-content-end gap-2">' + acciones + '</div></td></tr>';
    });

    $('#categoriasTarjetas').html(tarjetas || '<div class="col-12"><div class="csm-cat-empty"><i class="bi bi-inboxes"></i><p class="mb-1">No se encontraron categorías.</p><small>Prueba con otro término o crea una nueva categoría.</small></div></div>');
    $('#categoriasTabla tbody').html(filas || '<tr><td colspan="6" class="text-center text-body-secondary py-5">No se encontraron categorías.</td></tr>');
    $('#categoriasTarjetas').toggleClass('d-none', e.vista !== 'tarjetas');
    $('#categoriasTablaWrap').toggleClass('d-none', e.vista !== 'lista');
    $('#categoriasVistaTarjetas').toggleClass('active', e.vista === 'tarjetas').attr('aria-pressed', e.vista === 'tarjetas');
    $('#categoriasVistaLista').toggleClass('active', e.vista === 'lista').attr('aria-pressed', e.vista === 'lista');

    var inicio = e.total ? (e.pagina - 1) * e.limite + 1 : 0;
    $('#categoriasConteo').text('Mostrando ' + inicio + '–' + Math.min(e.total, e.pagina * e.limite) + ' de ' + e.total + ' categorías');

    var numeros = Array.from(new Set([1, e.paginas, e.pagina - 1, e.pagina, e.pagina + 1].filter(function (n) { return n >= 1 && n <= e.paginas; }))).sort(function (a, b) { return a - b; });
    var html = '', anterior = 0;

    $.each(numeros, function (_, n) {
        if (n - anterior > 1) html += '<span class="px-1 text-body-secondary">…</span>';
        html += '<button type="button" class="btn btn-sm ' + (n === e.pagina ? 'csm-cat-btn' : 'btn-outline-secondary') + '" data-cat-page="' + n + '"' + (n === e.pagina ? ' aria-current="page"' : '') + '>' + n + '</button>';
        anterior = n;
    });

    $('#categoriasPaginacion').html(e.paginas > 1 ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-cat-page="' + (e.pagina - 1) + '" ' + (e.pagina === 1 ? 'disabled' : '') + ' aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>' + html + '<button type="button" class="btn btn-sm btn-outline-secondary" data-cat-page="' + (e.pagina + 1) + '" ' + (e.pagina === e.paginas ? 'disabled' : '') + ' aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button>' : '');
}

async function categoriasCargar(pagina) {
    var secuencia = ++categoriasEstado.secuencia, correcto = false;
    $('#categoriasCargando').removeClass('d-none');
    $('#categoriasBuscarBtn, #categoriasTipo, #categoriasLimite').prop('disabled', true);

    try {
        var r = await categoriasAPI('getCategorias', {
            tipo: categoriasEstado.tipo, buscar: categoriasEstado.buscar,
            pagina: pagina || 1, limite: categoriasEstado.limite
        });

        if (secuencia !== categoriasEstado.secuencia) return false;

        categoriasEstado.resultados = Array.isArray(r.results) ? r.results : [];
        categoriasEstado.pagina = Math.max(1, Number(r.pagina) || 1);
        categoriasEstado.paginas = Math.max(1, Number(r.paginas) || 1);
        categoriasEstado.total = Number(r.total) || 0;
        categoriasDibujar();
        correcto = true;
    } catch (error) {
        if (secuencia === categoriasEstado.secuencia) {
            categoriasEstado.resultados = [];
            categoriasEstado.total = 0;
            categoriasEstado.pagina = 1;
            categoriasEstado.paginas = 1;
            categoriasDibujar();
            Swal.fire('Error al consultar categorías', error.message, 'error');
        }
    } finally {
        if (secuencia === categoriasEstado.secuencia) {
            $('#categoriasCargando').addClass('d-none');
            $('#categoriasBuscarBtn, #categoriasTipo, #categoriasLimite').prop('disabled', false);
        }
    }
    return correcto;
}

async function categoriasFormulario(id) {
    var secuencia = ++categoriasEstado.formularioSecuencia;
    $('#categoriasNuevoBtn').prop('disabled', true);

    try {
        var c = id ? (await categoriasAPI('getCategoria', { id: id })).results : {};
        if (secuencia !== categoriasEstado.formularioSecuencia) return;
        if (id && (!c || Number(c.tipo) !== categoriasEstado.tipo)) throw new Error('No se encontró la categoría del tipo seleccionado.');

        $('#categoriasForm')[0].reset();
        $('#categoriaId').val(c.id || '');
        $('#categoriaTipoEtiqueta').val($('#categoriasTipo option:selected').text());
        $('#categoriaNombre').val(c.nombre || '');
        $('#categoriaDescripcion').val(c.descripcion || '');
        $('#categoriaActivo').prop('checked', !id || Number(c.activo) === 1);
        $('#categoriaArchivo').val('');

        categoriasEstado.quitarImagen = false;
        categoriasEstado.imagenOriginal = categoriasImagen(c.imagen);
        categoriasPrevisualizar(categoriasEstado.imagenOriginal, false);
        categoriasVistaEditor(true);
        $('#categoriaNombre').trigger('focus');
    } catch (error) {
        if (secuencia === categoriasEstado.formularioSecuencia) Swal.fire('No se pudo abrir la categoría', error.message, 'error');
    } finally {
        if (secuencia === categoriasEstado.formularioSecuencia) $('#categoriasNuevoBtn').prop('disabled', false);
    }
}

async function categoriasGuardar(event) {
    event.preventDefault();
    if (categoriasEstado.guardando) return;

    var id = $('#categoriaId').val();
    var nombre = $('#categoriaNombre').val().trim(), descripcion = $('#categoriaDescripcion').val().trim();
    var archivo = $('#categoriaArchivo')[0].files[0];

    if (!nombre || nombre.length > 120) return Swal.fire('Nombre requerido', 'Escribe un nombre de hasta 120 caracteres.', 'warning');
    if (descripcion.length > 5000) return Swal.fire('Descripción demasiado larga', 'Utiliza máximo 5000 caracteres.', 'warning');

    if (archivo && (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type) || archivo.size > 3145728)) {
        return Swal.fire('Imagen no válida', 'Utiliza JPG, PNG o WebP de máximo 3 MB.', 'warning');
    }

    categoriasEstado.guardando = true;
    $('#categoriaGuardarBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Guardando...');
    $('#categoriasCancelarBtn, #categoriasVolverBtn').prop('disabled', true);

    try {
        var datos = {
            id: id,
            tipo: categoriasEstado.tipo,
            nombre: nombre,
            descripcion: descripcion,
            activo: $('#categoriaActivo').is(':checked') ? 1 : 0,
            quitar_imagen: categoriasEstado.quitarImagen ? 1 : 0
        };

        if (archivo) datos.imagen = archivo;

        var r = await categoriasAPI('setCategoria', datos);
        categoriasVistaEditor(false);
        categoriasPrevisualizar('', false);
        await categoriasCargar(id ? categoriasEstado.pagina : 1);
        Swal.fire({ icon: 'success', title: r.answer || 'Categoría guardada', timer: 1600, showConfirmButton: false });
    } catch (error) {
        Swal.fire('No se pudo guardar', error.message, 'error');
    } finally {
        categoriasEstado.guardando = false;
        $('#categoriaGuardarBtn').prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Guardar categoría');
        $('#categoriasCancelarBtn, #categoriasVolverBtn').prop('disabled', false);
    }
}

async function categoriasEliminar(id) {
    if (categoriasEstado.eliminando) return;

    var c = categoriasEstado.resultados.find(function (item) { return Number(item.id) === Number(id); });
    if (!c) return;

    var confirmar = await Swal.fire({
        icon: 'warning', title: '¿Eliminar categoría?',
        text: 'Se ocultará «' + c.nombre + '» sin borrar su registro.',
        showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#c93c47'
    });

    if (!confirmar.isConfirmed) return;

    categoriasEstado.eliminando = true;
    try {
        var r = await categoriasAPI('eliminarCategoria', { id: id });
        await categoriasCargar(categoriasEstado.pagina);
        Swal.fire({ icon: 'success', title: r.answer || 'Categoría eliminada', timer: 1600, showConfirmButton: false });
    } catch (error) {
        Swal.fire('No se pudo eliminar', error.message, 'error');
    } finally {
        categoriasEstado.eliminando = false;
    }
}

function inicializarCategorias() {
    if (!$('#categoriasPanelLista').length || !$('#categoriasPanelFormulario').length) return;

    var usuario = null;
    try {
        usuario = typeof requireAuth === 'function' ? requireAuth() : JSON.parse(localStorage.getItem('user') || 'null');
    } catch (error) {
        usuario = null;
    }

    if (!usuario || String(usuario.role || '').toUpperCase() !== 'ADMIN') {
        $('main').html('<div class="alert alert-warning" role="alert">No tienes permisos para administrar categorías.</div>');
        return;
    }

    categoriasEstado.tipo = Number($('#categoriasTipo').val()) || 1;
    categoriasVistaEditor(false);

    $('#categoriasTipo').on('change', function () {
        categoriasEstado.tipo = Number(this.value) || 1;
        categoriasCargar(1);
    });

    $('#categoriasBuscarBtn').on('click', function () {
        categoriasEstado.buscar = $('#categoriasBuscar').val().trim();
        categoriasCargar(1);
    });

    $('#categoriasBuscar').on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#categoriasBuscarBtn').trigger('click');
        }
    });

    $('#categoriasLimite').on('change', function () {
        categoriasEstado.limite = Number(this.value) || 12;
        categoriasCargar(1);
    });

    $('#categoriasVistaTarjetas, #categoriasVistaLista').on('click', function () {
        categoriasEstado.vista = this.id === 'categoriasVistaLista' ? 'lista' : 'tarjetas';
        categoriasDibujar();
    });

    $('#categoriasPaginacion').on('click', '[data-cat-page]', function () {
        var pagina = Number($(this).attr('data-cat-page'));
        if (pagina >= 1 && pagina <= categoriasEstado.paginas && pagina !== categoriasEstado.pagina) categoriasCargar(pagina);
    });

    $('#categoriasNuevoBtn').on('click', function () {
        categoriasFormulario(0);
    });

    $('#categoriasTarjetas, #categoriasTabla').on('click', '[data-cat-action]', function () {
        var accion = $(this).attr('data-cat-action'), id = Number($(this).attr('data-id'));
        if (accion === 'edit') categoriasFormulario(id);
        else if (accion === 'delete') categoriasEliminar(id);
    });

    $('#categoriasVolverBtn, #categoriasCancelarBtn').on('click', function () {
        ++categoriasEstado.formularioSecuencia;
        categoriasVistaEditor(false);
        categoriasPrevisualizar('', false);
    });

    $('#categoriasForm').on('submit', categoriasGuardar);

    $('#categoriaArchivo').on('change', function () {
        var archivo = this.files[0];
        if (!archivo) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type) || archivo.size > 3145728) {
            this.value = '';
            Swal.fire('Imagen no válida', 'Selecciona una imagen JPG, PNG o WebP de máximo 3 MB.', 'warning');
            return;
        }

        categoriasEstado.quitarImagen = false;
        categoriasPrevisualizar(URL.createObjectURL(archivo), true);
    });

    $('#categoriaQuitarImagen').on('click', function () {
        $('#categoriaArchivo').val('');
        categoriasEstado.quitarImagen = true;
        categoriasPrevisualizar('', false);
    });

    $(window).on('pagehide', function () {
        ++categoriasEstado.secuencia;
        ++categoriasEstado.formularioSecuencia;
        categoriasPrevisualizar('', false);
    });

    categoriasCargar(1);
}

$(document).ready(inicializarCategorias);
