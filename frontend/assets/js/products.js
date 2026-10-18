
/* Sistema CSM - Productos e inventario */

var productosEstado = { pagina: 1, limite: 24, paginas: 1, total: 0, buscar: '', clasificacion: '', resultados: [], vista: 'boxes', secuencia: 0 };
var productosAdmin = false, productosModal = null, productosModalCamara = null, productosCamara = null, productosCaptura = null;

function productosAPI(action, campos, archivo) {
    var formData = new FormData();
    if (action) formData.append('action', action);

    $.each(campos || {}, function (campo, valor) {
        if (valor instanceof Blob) formData.append(campo, valor, valor instanceof File ? valor.name : 'captura.jpg');
        else formData.append(campo, valor == null ? '' : valor);
    });

    var base = typeof API_BASE !== 'undefined' && API_BASE ? API_BASE : '../backend/api';
    var endpoint = archivo || 'products.php';

    return $.ajax({
        type: 'POST',
        url: base.replace(/\/$/, '') + '/' + endpoint,
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        xhrFields: { withCredentials: true }
    }).then(function (data) {
        if (!data || data.error || data.status === 'error' || (endpoint === 'products.php' && data.status !== 'ok')) {
            throw new Error(data && (data.answer || data.error) || 'Respuesta inválida del servidor');
        }
        return data;
    }, function (xhr) {
        throw new Error(xhr.responseJSON && (xhr.responseJSON.answer || xhr.responseJSON.error) || 'Error de conexión (' + (xhr.status || 0) + ')');
    });
}

function productosEscapar(valor) {
    return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function productosNumero(valor) {
    var s = String(valor == null ? '' : valor).trim().replace(/[^0-9.,-]/g, '');
    if (!s) return null;
    if (s.includes(',') && s.includes('.')) s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    else if (s.includes(',')) s = /^-?\d{1,3}(,\d{3})+$/.test(s) ? s.replace(/,/g, '') : s.replace(',', '.');

    var n = Number(s);
    return Number.isFinite(n) ? n : NaN;
}

function productosMoneda(valor) {
    return new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(valor) || 0);
}

function productosFoto(ruta) {
    var p = String(ruta || '').trim();
    if (!p || /^(javascript:|data:|\/\/)/i.test(p)) return '';
    return /^https?:\/\//i.test(p) || p.startsWith('../backend/') ? p : '../backend/' + p.replace(/^\/+/, '');
}

function productosEstadoStock(p) {
    var cantidad = Number(p.stock) || 0, minimo = Number(p.min_stock) || 0, maximo = Number(p.max_stock) || 0;
    return {
        cantidad: cantidad,
        clase: minimo > 0 && cantidad <= minimo ? 'low' : maximo > 0 && cantidad >= maximo ? 'high' : 'normal',
        texto: minimo > 0 && cantidad <= minimo ? 'Stock bajo' : maximo > 0 && cantidad >= maximo ? 'Stock alto' : 'Disponible'
    };
}

function productosAcciones(p, tabla) {
    if (!productosAdmin) return '';

    return '<div class="dropdown ' + (tabla ? '' : 'csm-inventory-card-actions') + '"><button type="button" class="csm-inventory-more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de ' + productosEscapar(p.name) + '"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-action="edit"><i class="bi bi-pencil"></i> Editar</button></li><li><button class="dropdown-item" type="button" data-action="ficha"><i class="bi bi-file-earmark-text"></i> Ver ficha</button></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-action="delete"><i class="bi bi-trash"></i> Eliminar</button></li></ul></div>';
}

function productosDibujar() {
    var e = productosEstado, inicio = e.total ? (e.pagina - 1) * e.limite + 1 : 0;

    $('#boxView').toggleClass('d-none', e.vista !== 'boxes');
    $('#tableView').toggleClass('d-none', e.vista !== 'table');
    $('#btnViewBoxes').toggleClass('active', e.vista === 'boxes').attr('aria-pressed', e.vista === 'boxes');
    $('#btnViewTable').toggleClass('active', e.vista === 'table').attr('aria-pressed', e.vista === 'table');
    $('#inventoryResultCount').text('Mostrando ' + inicio + '–' + Math.min(e.pagina * e.limite, e.total) + ' de ' + e.total + ' productos');
    $('#inventoryActiveFilter').prop('hidden', !(e.buscar || e.clasificacion)).text('Filtros activos');

    var tarjetas = '', filas = '';

    $.each(e.resultados, function (_, p) {
        var s = productosEstadoStock(p), foto = productosFoto(p.image_path), id = Number(p.id);
        var imagen = foto ? '<img loading="lazy" src="' + productosEscapar(foto) + '" alt="' + productosEscapar(p.name) + '">' : '<div class="csm-inventory-product-placeholder"><i class="bi bi-box-seam"></i></div>';

        tarjetas += `<div class="col" data-id="${id}"><article class="csm-inventory-product h-100"><div class="csm-inventory-product-row"><div class="csm-inventory-product-media">${imagen}</div><div class="csm-inventory-product-content">${productosAcciones(p, false)}<div class="csm-inventory-product-top"><span class="csm-inventory-product-category">${productosEscapar(p.classification || 'Sin clasificación')}</span><h3 class="csm-inventory-product-name" title="${productosEscapar(p.name)}">${productosEscapar(p.name)}</h3><div class="csm-inventory-product-code" title="${productosEscapar(p.code || p.barcode || '')}">Código: ${productosEscapar(p.code || p.barcode || 'Sin código')}</div></div><div class="csm-inventory-product-meta"><div class="csm-inventory-product-price"><small>Precio de venta</small><strong>$${productosMoneda(p.public_price)}</strong></div><div class="csm-inventory-product-stock-group"><span class="csm-inventory-stock ${s.clase}"><i class="bi bi-box"></i> ${s.cantidad}</span><span class="csm-inventory-stock-caption">${s.texto}</span></div></div></div></div></article></div>`;

        filas += `<tr data-id="${id}"><td>${id}</td><td>${productosEscapar(p.last_purchase || '')}</td><td>${productosEscapar(p.code || '')}</td><td>${productosEscapar(p.name)}</td><td>${productosEscapar(p.description || '')}</td><td>${productosEscapar(p.unit || '')}</td><td>$${productosMoneda(p.purchase_price)}</td><td>$${productosMoneda(p.min_price)}</td><td>${p.profit_margin == null ? '' : (Number(p.profit_margin) * 100).toFixed(2) + '%'}</td><td>$${productosMoneda(p.public_price)}</td><td>${p.competitor_price == null ? '' : '$' + productosMoneda(p.competitor_price)}</td><td>${foto ? '<img src="' + productosEscapar(foto) + '" class="product-thumbnail" alt="" loading="lazy">' : ''}</td><td><strong>${s.cantidad}</strong><small class="d-block text-body-secondary">${s.texto}</small></td><td>${productosAcciones(p, true)}</td></tr>`;
    });

    $('#boxGrid').html(tarjetas || '<div class="col-12"><div class="csm-inventory-empty"><i class="bi bi-search"></i><h3>Sin resultados</h3><p>Prueba con otros filtros.</p></div></div>');
    $('#productTable tbody').html(filas || '<tr><td colspan="14" class="text-center py-4">No hay productos para mostrar.</td></tr>');

    var numeros = [1, e.paginas, e.pagina - 1, e.pagina, e.pagina + 1].filter(function (n) { return n >= 1 && n <= e.paginas; });
    numeros = Array.from(new Set(numeros)).sort(function (a, b) { return a - b; });
    var html = '', anterior = 0;

    $.each(numeros, function (_, n) {
        if (n - anterior > 1) html += '<span class="px-1 text-body-secondary">…</span>';
        html += '<button type="button" data-page="' + n + '" class="btn btn-sm ' + (n === e.pagina ? 'csm-inv-primary' : 'csm-inv-outline') + '"' + (n === e.pagina ? ' aria-current="page"' : '') + '>' + n + '</button>';
        anterior = n;
    });

    $('#productPagination').html(e.paginas > 1 ? '<button type="button" class="btn btn-sm csm-inv-outline" data-page="' + (e.pagina - 1) + '" ' + (e.pagina === 1 ? 'disabled' : '') + ' aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>' + html + '<button type="button" class="btn btn-sm csm-inv-outline" data-page="' + (e.pagina + 1) + '" ' + (e.pagina === e.paginas ? 'disabled' : '') + ' aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button>' : '');
}

async function productosCargar(pagina) {
    var seq = ++productosEstado.secuencia;
    $('#inventoryResultCount').text('Cargando productos...');
    $('#btnApplyProductFilters').prop('disabled', true);

    try {
        var r = await productosAPI('getProductos', {
            pagina: pagina || 1,
            limite: productosEstado.limite,
            buscar: productosEstado.buscar,
            clasificacion: productosEstado.clasificacion
        });

        if (seq !== productosEstado.secuencia) return;

        productosEstado.resultados = Array.isArray(r.results) ? r.results : [];
        productosEstado.total = Number(r.total) || 0;
        productosEstado.pagina = Math.max(1, Number(r.pagina) || 1);
        productosEstado.paginas = Math.max(1, Number(r.paginas) || 1);
        productosDibujar();
    } catch (err) {
        if (seq === productosEstado.secuencia) {
            $('#inventoryResultCount').text('No se pudieron cargar los productos');
            Swal.fire('Error', err.message, 'error');
        }
    } finally {
        if (seq === productosEstado.secuencia) $('#btnApplyProductFilters').prop('disabled', false);
    }
}

async function productosClasificaciones() {
    try {
        var r = await productosAPI('getClasificaciones'), actual = $('#classificationFilter').val() || '';

        $('#classificationFilter').empty().append(new Option('Todas las clasificaciones', ''));

        $.each(Array.isArray(r.results) ? r.results : [], function (_, categoria) {
            $('#classificationFilter').append(new Option(categoria, categoria));

            if (!$('#productClassification option').filter(function () { return this.value === categoria; }).length) {
                $('#productClassification').append(new Option(categoria, categoria));
            }
        });

        $('#classificationFilter').val(actual);
    } catch (err) {
        console.error('Clasificaciones:', err.message);
    }
}

async function productosProveedores(id) {
    var $select = $('#productSupplier');

    try {
        var r = await productosAPI('', {}, 'suppliers.php');
        var proveedores = Array.isArray(r) ? r : Array.isArray(r.results) ? r.results : [];

        $select.empty().append(new Option('Seleccione proveedor', ''));

        $.each(proveedores, function (_, p) {
            $select.append(new Option(p.name || p.nombre || 'Proveedor', String(p.id)));
        });

        $select.val(id ? String(id) : '');
    } catch (err) {
        $select.empty().append(new Option('No se pudieron cargar los proveedores', ''));

        if (id) $select.append(new Option('Proveedor actual (#' + id + ')', String(id))).val(String(id));
        console.warn('Proveedores:', err.message);
    }
}

function productosFotoPreview(ruta) {
    var src = productosFoto(ruta);

    $('#productImagePath').val(ruta || '');
    $('#productImagePreview').attr('src', src).toggleClass('d-none', !src);
    $('#btnClearImage').toggleClass('d-none', !src);
    $('#fileUploadHidden, #btnCaptureImage').prop('disabled', !!src);
}

async function productosEditar(id) {
    try {
        var p = id ? (await productosAPI('getProducto', { id: id })).results : {};
        if (id && !p) throw new Error('No se encontró el producto.');

        $('#productForm')[0].reset();
        $('#productId').val(p.id || '');
        $('#productModalLabel').text(id ? 'Editar producto' : 'Nuevo producto');

        var campos = {
            productCode: 'code', productBarcode: 'barcode', productName: 'name',
            productDescription: 'description', productUnit: 'unit',
            lastPurchase: 'last_purchase', stock: 'stock',
            minStock: 'min_stock', maxStock: 'max_stock'
        };

        $.each(campos, function (input, campo) {
            $('#' + input).val(p[campo] == null ? '' : p[campo]);
        });

        $.each({
            purchasePrice: 'purchase_price', minPrice: 'min_price',
            publicPrice: 'public_price', competitorPrice: 'competitor_price'
        }, function (input, campo) {
            $('#' + input).val(p[campo] == null ? '' : Number(p[campo]).toFixed(2));
        });

        $('#profitMargin').val(p.profit_margin == null ? '' : (Number(p.profit_margin) * 100).toFixed(2));
        $('#productClassification').val(p.classification || 'FERRETERIA');
        $('#fileUploadHidden').val('');
        productosFotoPreview(p.image_path || '');

        await productosProveedores(p.supplier_id || '');
        productosModal.show();
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

async function productosGuardar(event) {
    event.preventDefault();
    if (!productosAdmin) return;

    var valores = { id: $('#productId').val() || '' };

    $.each({
        productCode: 'code', productBarcode: 'barcode', productName: 'name',
        productDescription: 'description', productUnit: 'unit',
        lastPurchase: 'last_purchase', stock: 'stock',
        minStock: 'min_stock', maxStock: 'max_stock',
        productClassification: 'classification', productSupplier: 'supplier_id',
        productImagePath: 'image_path'
    }, function (input, campo) {
        valores[campo] = $('#' + input).val() || '';
    });

    if (!valores.name.trim()) {
        Swal.fire('Nombre requerido', 'Ingresa el nombre del producto.', 'warning');
        return;
    }

    $.each(['stock', 'min_stock', 'max_stock'], function (_, campo) {
        if (valores[campo] === '') valores[campo] = '0';
    });

    var error = '';

    $.each({
        purchasePrice: 'purchase_price', minPrice: 'min_price',
        publicPrice: 'public_price', competitorPrice: 'competitor_price'
    }, function (input, campo) {
        var texto = String($('#' + input).val() || '').trim(), n = productosNumero(texto);

        if (texto && (!Number.isFinite(n) || n < 0)) error = 'Precio inválido en ' + campo;
        valores[campo] = texto && Number.isFinite(n) ? n.toFixed(2) : '';
    });

    var porcentaje = String($('#profitMargin').val() || '').trim(), margen = productosNumero(porcentaje);

    if (porcentaje && (!Number.isFinite(margen) || margen < 0)) error = 'Porcentaje de margen inválido';
    valores.profit_margin = porcentaje && Number.isFinite(margen) ? (margen / 100).toFixed(2) : '';

    if (error) {
        Swal.fire('Datos inválidos', error, 'warning');
        return;
    }

    var confirmar = await Swal.fire({
        icon: 'question',
        title: valores.id ? '¿Guardar cambios?' : '¿Registrar producto?',
        showCancelButton: true, confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar', confirmButtonColor: '#0d6a47'
    });

    if (!confirmar.isConfirmed) return;

    var $boton = $('#productForm [type="submit"]').prop('disabled', true);

    try {
        await productosAPI('setProducto', valores);
        productosModal.hide();
        await Promise.all([productosCargar(productosEstado.pagina), productosClasificaciones()]);
        Swal.fire({ icon: 'success', title: 'Producto guardado', timer: 1400, showConfirmButton: false });
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    } finally {
        $boton.prop('disabled', false);
    }
}

async function productosBuscarClave(titulo) {
    var r = await Swal.fire({
        title: titulo, input: 'text', inputLabel: 'Clave o código de barras',
        inputPlaceholder: 'Escribe o escanea el código', showCancelButton: true,
        confirmButtonText: 'Buscar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6a47',
        inputValidator: function (v) { return !String(v || '').trim() ? 'Ingresa una clave.' : undefined; }
    });

    if (!r.isConfirmed) return null;

    try {
        return (await productosAPI('getProductoPorClave', { clave: r.value.trim() })).results;
    } catch (err) {
        Swal.fire('Producto no encontrado', err.message, 'warning');
        return null;
    }
}

async function productosPrecioRapido() {
    var p = await productosBuscarClave('Actualizar precio');
    if (!p) return;

    var r = await Swal.fire({
        title: 'Actualizar precios',
        html: '<div class="text-start"><label class="form-label">Precio de compra actual: $' + productosMoneda(p.purchase_price) + '</label><input id="quickPurchasePrice" class="swal2-input w-100 mx-0 mt-1 mb-3" inputmode="decimal" placeholder="Nuevo precio de compra"><label class="form-label">Precio de venta actual: $' + productosMoneda(p.public_price) + '</label><input id="quickSalePrice" class="swal2-input w-100 mx-0 mt-1" inputmode="decimal" placeholder="Nuevo precio de venta"></div>',
        showCancelButton: true, confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar', confirmButtonColor: '#0d6a47',
        preConfirm: function () {
            var compra = $('#quickPurchasePrice').val().trim(), venta = $('#quickSalePrice').val().trim(), datos = { id: p.id };

            if (!compra && !venta) {
                Swal.showValidationMessage('Ingresa al menos un precio.');
                return false;
            }

            for (var par of [['purchase_price', compra], ['public_price', venta]]) {
                if (!par[1]) continue;
                var n = productosNumero(par[1]);

                if (!Number.isFinite(n) || n < 0) {
                    Swal.showValidationMessage('Ingresa importes válidos.');
                    return false;
                }

                datos[par[0]] = n.toFixed(2);
            }

            return datos;
        }
    });

    if (!r.isConfirmed) return;

    try {
        await productosAPI('setProducto', r.value);
        await productosCargar(productosEstado.pagina);
        Swal.fire({ icon: 'success', title: 'Precios actualizados', timer: 1400, showConfirmButton: false });
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

async function productosAccionCard(event) {
    event.preventDefault();
    if (!productosAdmin) return;

    var accion = $(this).attr('data-action'), id = Number($(this).closest('[data-id]').attr('data-id'));
    var p = productosEstado.resultados.find(function (fila) { return Number(fila.id) === id; });

    if (!p) return;

    if (accion === 'edit') {
        await productosEditar(id);
        return;
    }

    if (accion === 'ficha') {
        var ventana = window.open('about:blank', '_blank');

        try {
            var r = await productosAPI('', { id: id }, 'product_card.php');
            if (!r.success || !r.file) throw new Error('No se pudo generar la ficha.');

            var ruta = new URL('../backend/' + r.file, window.location.href);
            var carpeta = new URL('../backend/', window.location.href);

            if (ruta.origin !== carpeta.origin || !ruta.pathname.startsWith(carpeta.pathname)) throw new Error('Ruta de ficha inválida.');

            if (ventana) ventana.location.href = ruta.href;
            else Swal.fire('Ficha generada', 'El navegador bloqueó la ventana emergente.', 'info');
        } catch (err) {
            if (ventana) ventana.close();
            Swal.fire('Error', err.message, 'error');
        }
        return;
    }

    if (accion === 'delete') {
        var conf = await Swal.fire({
            icon: 'warning', title: '¿Eliminar producto?', text: p.name,
            showCancelButton: true, confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar', confirmButtonColor: '#c93d45'
        });

        if (!conf.isConfirmed) return;

        try {
            await productosAPI('eliminarProducto', { id: id });
            await Promise.all([productosCargar(productosEstado.pagina), productosClasificaciones()]);
            Swal.fire({ icon: 'success', title: 'Producto eliminado', timer: 1400, showConfirmButton: false });
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    }
}

async function productosResumenInventario() {
    $('#costSummaryValue').text('Calculando...');

    try {
        var r = await productosAPI('getResumenInventario', {
            buscar: productosEstado.buscar,
            clasificacion: productosEstado.clasificacion
        });

        $('#costSummaryValue').text('$' + productosMoneda(r.results && r.results.valor));
        $('#costSummaryCount').text('Productos registrados: ' + (r.results && r.results.cantidad || 0));
        $('#costSummaryCategory').text('Clasificación: ' + (productosEstado.clasificacion || 'Todas'));
    } catch (err) {
        $('#costSummaryValue').text('No disponible');
        Swal.fire('Error', err.message, 'error');
    }
}

async function productosImportar(event) {
    var archivo = event.target.files && event.target.files[0];
    if (!archivo) return;

    try {
        var r = await productosAPI('', { csv: archivo }, 'import_products.php');
        if (!r.success) throw new Error(r.error || 'Error al importar');

        await Promise.all([productosCargar(1), productosClasificaciones()]);
        Swal.fire('Importación realizada', 'Productos importados correctamente.', 'success');
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    } finally {
        event.target.value = '';
    }
}

async function productosSubirImagen(event) {
    var archivo = event.target.files && event.target.files[0];
    if (!archivo) return;

    try {
        var r = await productosAPI('', { image: archivo, file: archivo }, 'upload_image.php');
        if (!r.path) throw new Error('No se recibió la ruta de la imagen');

        productosFotoPreview(r.path);
    } catch (err) {
        event.target.value = '';
        Swal.fire('Error al subir imagen', err.message, 'error');
    }
}

function productosCerrarCamara() {
    if (productosCamara) productosCamara.getTracks().forEach(function (t) { t.stop(); });
    productosCamara = null;

    var video = document.getElementById('cameraVideo');
    if (video) { video.pause(); video.srcObject = null; }
}

async function productosIniciarCamara() {
    productosCerrarCamara();
    productosCaptura = null;

    $('#btnTakeSnapshot').text('Capturar');
    $('#btnUseSnapshot').prop('disabled', true);
    $('#cameraCanvas').addClass('d-none');
    $('#cameraVideo').removeClass('d-none');

    productosCamara = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' }, audio: false
    });

    document.getElementById('cameraVideo').srcObject = productosCamara;
    await document.getElementById('cameraVideo').play();
}

async function productosTomarFoto() {
    if ($('#cameraVideo').hasClass('d-none')) {
        try {
            await productosIniciarCamara();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
        return;
    }

    var video = document.getElementById('cameraVideo'), canvas = document.getElementById('cameraCanvas');

    if (!video.videoWidth) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    $('#cameraVideo').addClass('d-none');
    $('#cameraCanvas').removeClass('d-none');
    productosCerrarCamara();

    productosCaptura = await new Promise(function (resolve) {
        canvas.toBlob(resolve, 'image/jpeg', 0.9);
    });

    $('#btnUseSnapshot').prop('disabled', !productosCaptura);
    $('#btnTakeSnapshot').text('Tomar nueva foto');
}

async function productosUsarFoto() {
    if (!productosCaptura) return;

    try {
        var r = await productosAPI('', { image: productosCaptura, file: productosCaptura }, 'upload_image.php');
        if (!r.path) throw new Error('No se recibió la ruta de la imagen');

        productosFotoPreview(r.path);
        productosModalCamara.hide();
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

function productosCalcularPrecio() {
    var compra = productosNumero($('#purchasePrice').val()), porcentaje = productosNumero($('#profitMargin').val());

    if (Number.isFinite(compra) && Number.isFinite(porcentaje)) {
        $('#publicPrice').val((compra * (1 + porcentaje / 100)).toFixed(2));
        $('#minPrice').val((compra * (1 + porcentaje / 100 * 0.8)).toFixed(2));
    }
}

function productosCalcularMargen() {
    var compra = productosNumero($('#purchasePrice').val()), venta = productosNumero($('#publicPrice').val());
    if (compra > 0 && Number.isFinite(venta)) $('#profitMargin').val(((venta / compra - 1) * 100).toFixed(2));
}

function inicializarProductos() {
    if (typeof bootstrap === 'undefined' || typeof Swal === 'undefined') {
        console.error('Se requieren Bootstrap y SweetAlert2.');
        return;
    }

    if (!document.getElementById('productModal') || !document.getElementById('cameraModal')) return;

    var usuario = typeof requireAuth === 'function' ? requireAuth() : { role: 'ADMIN' };
    productosAdmin = String(usuario && usuario.role || '').toUpperCase() === 'ADMIN';

    productosModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal'));
    productosModalCamara = bootstrap.Modal.getOrCreateInstance(document.getElementById('cameraModal'));

    try {
        productosEstado.vista = localStorage.getItem('productsViewV2') === 'table' ? 'table' : 'boxes';
    } catch (e) {}

    if (!productosAdmin) $('#productCreateMenu, #productOptionsMenu, #inventoryFinancialSection').remove();

    if (!$('#btnApplyProductFilters').length) {
        $('.csm-inventory-filter-body').append('<div class="row g-2 mt-2"><div class="col-12 col-md-8"><button id="btnApplyProductFilters" type="button" class="btn btn-primary csm-inv-primary w-100"><i class="bi bi-search"></i> Buscar productos</button></div><div class="col-12 col-md-4"><button id="btnResetProductFilters" type="button" class="btn csm-inv-outline w-100">Limpiar filtros</button></div></div>');
    }

    if (!$('#productPagination').length) {
        $('.csm-inventory-results').append('<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4"><label class="small d-flex align-items-center gap-2 mb-0">Por página <select id="productPageSize" class="form-select form-select-sm" style="width:auto"><option value="24">24</option><option value="48">48</option><option value="96">96</option></select></label><nav id="productPagination" class="d-flex align-items-center flex-wrap gap-2" aria-label="Paginación de productos"></nav></div>');
    }

    $('#btnShowMore').closest('.text-center').hide();

    $('#btnApplyProductFilters').on('click', function () {
        productosEstado.buscar = String($('#boxSearch').val() || '').trim();
        productosEstado.clasificacion = $('#classificationFilter').val() || '';
        productosCargar(1);
    });

    $('#btnResetProductFilters').on('click', function () {
        $('#boxSearch').val('');
        $('#classificationFilter').val('');
        productosEstado.buscar = '';
        productosEstado.clasificacion = '';
        productosCargar(1);
    });

    $('#boxSearch').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#btnApplyProductFilters').trigger('click');
        }
    });

    $('#productPagination').on('click', '[data-page]', function () {
        var pagina = Number(this.dataset.page);

        if (pagina >= 1 && pagina <= productosEstado.paginas && pagina !== productosEstado.pagina) {
            productosCargar(pagina);
            document.querySelector('.csm-inventory-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    $('#productPageSize').on('change', function () {
        productosEstado.limite = Number(this.value) || 24;
        productosCargar(1);
    });

    $('#btnViewBoxes, #btnViewTable').on('click', function () {
        productosEstado.vista = this.id === 'btnViewTable' ? 'table' : 'boxes';
        try { localStorage.setItem('productsViewV2', productosEstado.vista); } catch (e) {}
        productosDibujar();
    });

    $('#inventoryFinancialSection').on('show.bs.modal', productosResumenInventario);
    $('#btnAddProduct').on('click', function () { productosEditar(); });
    $('#boxGrid, #productTable').on('click', '[data-action]', productosAccionCard);
    $('#productForm').on('submit', productosGuardar);
    $('#purchasePrice, #profitMargin').on('input', productosCalcularPrecio);
    $('#publicPrice').on('input', productosCalcularMargen);

    $('#purchasePrice, #minPrice, #publicPrice, #competitorPrice').on('blur', function () {
        var n = productosNumero(this.value);
        if (n !== null && Number.isFinite(n)) this.value = n.toFixed(2);
    });

    $('#btnImport').on('click', function () { $('#importFileInput').trigger('click'); });
    $('#importFileInput').on('change', productosImportar);
    $('#fileUploadHidden').on('change', productosSubirImagen);
    $('#btnClearImage').on('click', function () { $('#fileUploadHidden').val(''); productosFotoPreview(''); });

    $('#btnCaptureImage').on('click', async function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            Swal.fire('Cámara no disponible', 'Necesitas HTTPS y permisos de cámara.', 'warning');
            return;
        }

        try {
            await productosIniciarCamara();
            productosModalCamara.show();
        } catch (err) {
            productosCerrarCamara();
            Swal.fire('Error de cámara', err.message, 'error');
        }
    });

    $('#btnTakeSnapshot').on('click', productosTomarFoto);
    $('#btnUseSnapshot').on('click', productosUsarFoto);

    $('#cameraModal').on('hidden.bs.modal', function () {
        productosCerrarCamara();
        productosCaptura = null;
        $('#cameraCanvas').addClass('d-none');
        $('#cameraVideo').removeClass('d-none');
    });

    $('#btnSearchProduct').on('click', async function () {
        var p = await productosBuscarClave('Buscar para editar');
        if (p) productosEditar(p.id);
    });

    $('#btnQuickPrice').on('click', productosPrecioRapido);

    productosClasificaciones();
    productosCargar(1);
}

$(document).ready(inicializarProductos);
