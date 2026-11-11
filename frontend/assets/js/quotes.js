document.addEventListener('DOMContentLoaded', () => {

  // Mantener autenticación y barra (si se usan en main.js)

  const user = (typeof requireAuth === 'function') ? requireAuth() : { role: 'ADMIN' };

  // La navegación ahora se crea en csm-portal.js; no ejecutar la barra antigua.



  // Elementos de la tabla y totales

  const tableBody = document.querySelector('#quoteTable tbody');

  const subtotalCell = document.getElementById('subtotalCell');

  const ivaCell = document.getElementById('ivaCell');

  const totalCell = document.getElementById('totalCell');
  const quoteTotalSummary = document.getElementById('quoteTotalSummary');
  const quoteEmptyState = document.getElementById('quoteEmptyState');



  // Controles y botones

  const btnAddRow = document.getElementById('btnAddRow');

  const btnGenerate = document.getElementById('btnGenerate');

  const searchProduct = document.getElementById('searchProduct');

  const productList = document.getElementById('productList');

  const btnScan = document.getElementById('btnScan');

  const quantityInput = document.getElementById('quantityInput');

  const btnAdd = document.getElementById('btnAdd');

  const scannerOverlay = document.getElementById('scannerOverlay');

  const scannerView = document.getElementById('scannerView');

  const btnCloseScanner = document.getElementById('btnCloseScanner');

  const includeIva = document.getElementById('includeIva');

  const quoteDate = document.getElementById('quoteDate');

  const quotePlace = document.getElementById('quotePlace');

  const quoteRecipient = document.getElementById('quoteRecipient');



// Datos en memoria
let products = [];

const URL_PRODUCTOS = "../backend/api/products.php";

function normalizarProducto(valor) {
    return String(valor ?? "").trim().toLowerCase();
}

function guardarProducto(producto) {
    if (!producto || !producto.id) return;

    const index = products.findIndex(p =>
        String(p.id) === String(producto.id)
    );

    if (index === -1) {
        products.push(producto);
    } else {
        products[index] = producto;
    }
}

async function consultarProductos(buscar = "", limite = 96) {
    const respuesta = await $.ajax({
        type: "POST",
        url: URL_PRODUCTOS,
        data: {
            action: "getProductos",
            buscar: buscar,
            pagina: 1,
            limite: limite
        },
        dataType: "json"
    });

    if (!respuesta || respuesta.status !== "ok") {
        throw new Error(
            respuesta?.answer || "No se pudo consultar el catálogo"
        );
    }

    const lista = Array.isArray(respuesta.results)
        ? respuesta.results
        : [];

    lista.forEach(guardarProducto);

    return lista;
}

async function consultarProductoPorClave(clave) {
    const respuesta = await $.ajax({
        type: "POST",
        url: URL_PRODUCTOS,
        data: {
            action: "getProductoPorClave",
            clave: clave
        },
        dataType: "json"
    });

    if (respuesta.status === "ok" && respuesta.results) {
        guardarProducto(respuesta.results);
        return respuesta.results;
    }

    if (Number(respuesta.code) === 404) {
        return null;
    }

    throw new Error(
        respuesta.answer || "No fue posible consultar el producto"
    );
}

function etiquetaProducto(p) {
    return `${p.name || ""} | ${p.code || "Sin código"} | ID:${p.id}`;
}

function updateDatalist(filter, lista = products) {
    const texto = normalizarProducto(filter);
    productList.innerHTML = "";

    lista.filter(p => {
        if (!texto) return true;

        return [p.name, p.code, p.barcode].some(valor =>
            normalizarProducto(valor).includes(texto)
        );
    }).slice(0, 50).forEach(p => {
        const option = document.createElement("option");
        option.value = etiquetaProducto(p);
        productList.appendChild(option);
    });
}

function findProduct(query) {
    const texto = normalizarProducto(query);
    if (!texto) return null;

    const coincidenciaId = String(query).match(
        /\|\s*ID:(\d+)\s*$/i
    );

    if (coincidenciaId) {
        return products.find(p =>
            String(p.id) === coincidenciaId[1]
        ) || null;
    }

    const porClave = products.find(p =>
        normalizarProducto(p.code) === texto ||
        normalizarProducto(p.barcode) === texto
    );

    if (porClave) return porClave;

    const exactos = products.filter(p =>
        normalizarProducto(p.name) === texto
    );

    if (exactos.length === 1) return exactos[0];

    const parciales = products.filter(p =>
        normalizarProducto(p.name).includes(texto)
    );

    return parciales.length === 1 ? parciales[0] : null;
}

// Carga inicial mediante POST
consultarProductos("", 96)
    .then(lista => {
        updateDatalist("");
        console.log("Productos iniciales:", lista.length);
    })
    .catch(error => {
        console.error("Error cargando productos:", error);

        Swal.fire({
            icon: "warning",
            title: "No se pudo cargar el catálogo",
            text: error.responseJSON?.answer ||
                error.message ||
                "Revisa la conexión con el servidor.",
            confirmButtonColor: "#086b47"
        });
    });


  // Añadir fila con producto y cantidad

  function addRow(product, qty = 1) {

    if (!product) return;

    const tr = document.createElement('tr');



    // Descripción (select)

    const tdDesc = document.createElement('td');

    const sel = document.createElement('select');

    sel.className = 'form-select';

    products.forEach(p => {

      const o = document.createElement('option');

      o.value = p.id;

      o.textContent = p.name;

      sel.appendChild(o);

    });

    sel.value = product.id;

    tdDesc.appendChild(sel);



    // Cantidad (step = 1 por defecto)

    const tdQty = document.createElement('td');

    const qtyInput = document.createElement('input');

    qtyInput.type = 'number';

    qtyInput.step = '1';    // incremento en unidades

    qtyInput.min  = '0';

    qtyInput.value= qty;

    qtyInput.className = 'form-control';

    tdQty.appendChild(qtyInput);



    // Unidad

    const tdUnit = document.createElement('td');

    tdUnit.className = 'unit-cell';



    // Precio

    const tdPrice = document.createElement('td');

    tdPrice.className = 'price-cell';



    // Importe

    const tdImp = document.createElement('td');

    tdImp.className = 'import-cell text-end';



    // Acciones (botón quitar y botón editar)

    const tdAcc = document.createElement('td');

    tdAcc.className = 'text-center';



    // Botón Quitar

    const delBtn = document.createElement('button');

    delBtn.className = 'btn btn-sm btn-danger me-1';

    delBtn.textContent = 'Quitar';

    delBtn.addEventListener('click', () => {

      tr.remove();

      updateTotals();

    });

    tdAcc.appendChild(delBtn);



    // Botón Editar precio

    const editBtn = document.createElement('button');

    editBtn.className = 'btn btn-sm btn-secondary';

    editBtn.textContent = 'Editar precio';

    editBtn.addEventListener('click', () => openPriceModal(tr));

    tdAcc.appendChild(editBtn);



    tr.append(tdDesc, tdQty, tdUnit, tdPrice, tdImp, tdAcc);

    tableBody.appendChild(tr);



    // Función para actualizar fila cuando cambie el producto

    function refresh() {

      const p = products.find(x => String(x.id) === String(sel.value));

      if (!p) return;

      tr.dataset.price = parseFloat(p.public_price || 0).toFixed(2);

      tr.dataset.purchasePrice = parseFloat(p.purchase_price || p.public_price || 0).toFixed(2);

      tdUnit.textContent  = p.unit || '';

      tdPrice.textContent = '$' + parseFloat(tr.dataset.price).toFixed(2);

      updateTotals();

    }



    sel.addEventListener('change', refresh);

    qtyInput.addEventListener('input', updateTotals);

    refresh();

  }



  // Mostrar modal para editar el precio (con margen)

  function openPriceModal(row) {

    const currentPrice  = parseFloat(row.dataset.price || 0);

    const purchasePrice = parseFloat(row.dataset.purchasePrice || 0);



    // Crear contenedor

    const overlay = document.createElement('div');
    overlay.className = 'csm-price-editor-overlay';

    overlay.style.position = 'fixed';

    overlay.style.top = 0;

    overlay.style.left = 0;

    overlay.style.width = '100%';

    overlay.style.height = '100%';

    // El fondo se define en csm-sales.css para respetar temas claro y oscuro.

    overlay.style.display = 'flex';

    overlay.style.alignItems = 'center';

    overlay.style.justifyContent = 'center';

    overlay.style.zIndex = 4000;



    // Crear caja del modal

    const box = document.createElement('div');

    box.className = 'csm-price-editor';

    box.style.minWidth = '280px';

    box.innerHTML = `

      <h5>Editar precio de venta</h5>

      <p>Precio de compra: <strong>$${purchasePrice.toFixed(2)}</strong></p>

      <div class="mb-2">

        <label class="form-label">Margen (%)</label>

        <input type="number" step="5" id="marginInput" class="form-control"

               value="${(purchasePrice > 0 ? ((currentPrice / purchasePrice) - 1) * 100 : 0)}">

      </div>

      <div class="mb-2">

        <label class="form-label">Precio de venta</label>

        <input type="number" step="1" id="priceInput" class="form-control"

               value="${currentPrice}">

      </div>

      <div class="text-end">

        <button class="btn btn-secondary me-2" id="cancelEdit">Cancelar</button>

        <button class="btn btn-primary" id="saveEdit">Guardar</button>

      </div>

    `;

    overlay.appendChild(box);

    document.body.appendChild(overlay);



    // Obtener referencias a los inputs

    const marginInput = box.querySelector('#marginInput');

    const priceInput  = box.querySelector('#priceInput');

    const saveBtn     = box.querySelector('#saveEdit');

    const cancelBtn   = box.querySelector('#cancelEdit');



    // Actualizar precio cuando cambie el margen

    marginInput.addEventListener('input', () => {

      const margin = parseFloat(marginInput.value || 0);

      const newPrice = purchasePrice * (1 + (margin / 100));

      priceInput.value = newPrice.toFixed(2);

    });



    // Actualizar margen cuando cambie el precio

    priceInput.addEventListener('input', () => {

      const p = parseFloat(priceInput.value || 0);

      const newMargin = (purchasePrice > 0 ? ((p / purchasePrice) - 1) * 100 : 0);

      marginInput.value = newMargin.toFixed(2);

    });



    // Cancelar: quitar modal

    cancelBtn.addEventListener('click', () => {

      document.body.removeChild(overlay);

    });



    // Guardar cambios

    saveBtn.addEventListener('click', () => {

      const newPrice = parseFloat(priceInput.value);

      if (isNaN(newPrice)) {

        //alert('Precio inválido');
        Swal.fire({
    icon: "warning",
    title: "Precio inválido",
    text: "Ingresa un precio de venta válido.",
    confirmButtonColor: "#086b47"
});

        return;

      }

      if (newPrice < purchasePrice) {

        //alert('El precio de venta no puede ser menor al precio de compra.');
        Swal.fire({
    icon: "warning",
    title: "Precio no permitido",
    text: "El precio de venta no puede ser menor al precio de compra.",
    confirmButtonColor: "#086b47"
});

        return;

      }

      // Actualizar dataset y precio mostrado

      row.dataset.price = newPrice.toFixed(2);

      row.querySelector('.price-cell').textContent = '$' + newPrice.toFixed(2);

      updateTotals();

      document.body.removeChild(overlay);

    });

  }



  // Recalcular totales

  function updateTotals() {

    let subtotal = 0;

    tableBody.querySelectorAll('tr').forEach(row => {

      const price = parseFloat(row.dataset.price || 0);

      const qty   = parseFloat(row.querySelector('input').value || 0);

      subtotal += price * qty;

      row.querySelector('.import-cell').textContent = '$' + (price * qty).toFixed(2);

    });

    subtotalCell.textContent = '$' + subtotal.toFixed(2);

    const iva = includeIva.checked ? subtotal * 0.16 : 0;

    ivaCell.textContent   = '$' + iva.toFixed(2);

    totalCell.textContent = '$' + (subtotal + iva).toFixed(2);
    if (quoteTotalSummary) quoteTotalSummary.textContent = 'TOTAL ' + totalCell.textContent;
    if (quoteEmptyState) quoteEmptyState.hidden = tableBody.children.length > 0;

    const ivaRow = ivaCell.closest('tr');

    if (ivaRow) ivaRow.style.display = includeIva.checked ? '' : 'none';

  }



  // Lógica de búsqueda y escaneo

async function addFromSearch() {
    const query = searchProduct.value.trim();
    const qty = Number(quantityInput.value);

    if (!query) {
        Swal.fire({
            icon: "warning",
            title: "Selecciona un producto",
            text: "Escribe el nombre o código del producto.",
            confirmButtonColor: "#086b47"
        });
        return;
    }

    if (!Number.isFinite(qty) || qty <= 0) {
        Swal.fire({
            icon: "warning",
            title: "Cantidad inválida",
            text: "Ingresa una cantidad mayor que cero.",
            confirmButtonColor: "#086b47"
        });
        return;
    }

    btnAdd.disabled = true;

    try {
        let producto = findProduct(query);

        if (!producto) {
            await consultarProductos(query);
            producto = findProduct(query);
        }

        if (!producto) {
            Swal.fire({
                icon: "warning",
                title: "Producto no seleccionado",
                text: "No se encontró una coincidencia única. Selecciona un producto de las sugerencias.",
                confirmButtonColor: "#086b47"
            });
            return;
        }

        addRow(producto, qty);

        searchProduct.value = "";
        updateDatalist("");

    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "Error de búsqueda",
            text: error.responseJSON?.answer ||
                error.message ||
                "No fue posible consultar los productos.",
            confirmButtonColor: "#086b47"
        });
    } finally {
        btnAdd.disabled = false;
    }
}



  
//searchProduct.addEventListener('input', () => updateDatalist(searchProduct.value));

let temporizadorBusqueda = null;
let versionBusqueda = 0;

searchProduct.addEventListener("input", function() {
    const texto = searchProduct.value.trim();

    clearTimeout(temporizadorBusqueda);
    updateDatalist(texto);

    const version = ++versionBusqueda;

    if (texto.length < 2) return;

    temporizadorBusqueda = setTimeout(async function() {
        try {
            const lista = await consultarProductos(texto);

            if (version !== versionBusqueda) return;

            updateDatalist(texto, lista);
        } catch (error) {
            console.error("Error buscando productos:", error);
        }
    }, 300);
});

  searchProduct.addEventListener('keydown', e => {

    if (e.key === 'Enter') {

      e.preventDefault();

      addFromSearch();

    }

  });

  btnAdd.addEventListener('click', addFromSearch);

  includeIva.addEventListener('change', updateTotals);



  // Botones estándar

  btnAddRow.addEventListener('click', () => {

    if (products.length > 0) addRow(products[0], 1);

  });

// Boton de pdf

btnGenerate.addEventListener("click", async function() {
    const items = [];

    tableBody.querySelectorAll("tr").forEach(row => {
        const productId = row.querySelector("select").value;
        const quantity = Number(row.querySelector("input").value);
        const price = Number(row.dataset.price);

        const producto = products.find(p =>
            String(p.id) === String(productId)
        );

        if (!producto || !Number.isFinite(quantity) || quantity <= 0 ||
            !Number.isFinite(price) || price < 0) {
            return;
        }

        const precioOriginal = Number(producto.public_price || 0);
        const precioPersonalizado = Math.abs(price - precioOriginal) >= 0.005;

        items.push({
            product_id: productId,
            quantity: quantity,
            price_type: precioPersonalizado ? "custom_price" : "public_price",
            custom_price: precioPersonalizado ? price : null
        });
    });

    if (!items.length || items.length !== tableBody.querySelectorAll("tr").length) {
        Swal.fire({
            icon: "warning",
            title: "Revisa la cotización",
            text: "Agrega productos y verifica sus cantidades y precios.",
            confirmButtonColor: "#086b47"
        });
        return;
    }

    // Abrir la pestaña durante el clic para evitar bloqueos del navegador
    const ventanaPdf = window.open("", "_blank");

    btnGenerate.disabled = true;

    Swal.fire({
        title: "Generando cotización",
        text: "Preparando el documento PDF...",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });

    try {
        const respuesta = await $.ajax({
            type: "POST",
            url: "../backend/api/quotes.php",
            data: {
                action: "generarCotizacion",
                items: JSON.stringify(items),
                date: quoteDate.value,
                place: quotePlace.value.trim(),
                recipient: quoteRecipient.value.trim(),
                iva: includeIva.checked ? "1" : "0"
            },
            dataType: "json"
        });

        if (respuesta.status !== "ok" ||
            !respuesta.results?.file) {
            throw new Error(
                respuesta.answer || "No fue posible generar el PDF"
            );
        }

        Swal.close();

        const fileUrl = "../backend/" + respuesta.results.file;

        if (ventanaPdf && !ventanaPdf.closed) {
            ventanaPdf.location.href = fileUrl;
        } else {
            window.location.href = fileUrl;
        }

    } catch (error) {
        Swal.close();

        if (ventanaPdf && !ventanaPdf.closed) {
            ventanaPdf.close();
        }

        Swal.fire({
            icon: "error",
            title: "No se pudo generar la cotización",
            text: error.responseJSON?.answer ||
                error.message ||
                "Ocurrió un error al generar el documento.",
            confirmButtonColor: "#086b47"
        });
    } finally {
        btnGenerate.disabled = false;
    }
});

  /*btnGenerate.addEventListener('click', () => {

  const items = [];

  tableBody.querySelectorAll('tr').forEach(row => {

    const productId = row.querySelector('select').value;

    const quantity = parseFloat(row.querySelector('input').value || 0);

    const price = parseFloat(row.dataset.price || 0); // precio modificado o default

    const selectedProduct = products.find(p => p.id == productId);



    let price_type = 'public_price';

    let custom_price = null;



    // Si el precio actual difiere del precio público, considera que es personalizado

    if (selectedProduct) {

      const defaultPrice = parseFloat(selectedProduct.public_price || 0);

      if (price !== defaultPrice) {

        price_type = 'custom_price';

        custom_price = price;

      }

    }



    if (quantity > 0) {

      items.push({

        product_id: productId,

        quantity: quantity,

        price_type: price_type,

        custom_price: custom_price

      });

    }

  });



  if (!items.length) {

    alert('Agrega al menos un producto');

    return;

  }



  const payload = {

    items,

    date: quoteDate.value,

    place: quotePlace.value,

    recipient: quoteRecipient.value,

    iva: includeIva.checked

  };



  apiRequest('quotes.php', 'POST', payload)

    .then(res => {

      if (res.success && res.file) {

        // Si la ruta devuelta no es absoluta, prepéndele "../backend/"

        const fileUrl = res.file.startsWith('http') ? res.file : '../backend/' + res.file;

        window.open(fileUrl, '_blank');

      } else {

        alert(res.error || 'No se pudo generar el PDF');

      }

    })

    .catch(err => alert(err.error || 'Error generando PDF'));

});*/



  // Escáner (QuaggaJS)

  let scannerRunning = false;

  async function detectarProductoCotizacion(result) {
    const codigo = result?.codeResult?.code || "";

    if (!codigo || !scannerRunning) return;

    scannerRunning = false;

    Quagga.stop();
    scannerOverlay.style.display = "none";

    try {
        const producto = await consultarProductoPorClave(codigo);

        if (!producto) {
            Swal.fire({
                icon: "warning",
                title: "Producto no encontrado",
                text: "Código detectado: " + codigo,
                confirmButtonColor: "#086b47"
            });
            return;
        }

        const qty = Number(quantityInput.value);

        if (!Number.isFinite(qty) || qty <= 0) {
            Swal.fire({
                icon: "warning",
                title: "Cantidad inválida",
                text: "Ingresa una cantidad mayor que cero.",
                confirmButtonColor: "#086b47"
            });
            return;
        }

        addRow(producto, qty);

    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "Error al consultar el código",
            text: error.responseJSON?.answer ||
                error.message ||
                "No fue posible consultar el producto.",
            confirmButtonColor: "#086b47"
        });
    }
}

  btnScan.addEventListener('click', () => {

    if (scannerRunning || typeof Quagga === 'undefined') return;

    scannerOverlay.style.display = 'flex';

    Quagga.init({

      inputStream: {

        name:'Live',

        type:'LiveStream',

        target: scannerView,

        constraints: { facingMode:'environment' }

      },

      decoder: { readers: ['ean_reader','ean_8_reader','code_128_reader'] },

      locate: true

    }, err => {

      if (err) {

        Swal.fire({
    icon: "error",
    title: "No se pudo iniciar el escáner",
    text: "Revisa los permisos de la cámara.",
    confirmButtonColor: "#086b47"
});

        scannerOverlay.style.display = 'none';

        return;

      }

      scannerRunning = true;

      Quagga.start();

    });

    /*Quagga.onDetected(result => {

      const code = result?.codeResult?.code || '';

      if (!code) return;

      // detener para que no siga detectando

      scannerRunning = false;

      Quagga.stop();

      scannerOverlay.style.display = 'none';

      const product = barcodeMap[code.trim().toLowerCase()];

      if (!product) {

        alert('Código detectado: ' + code + '\nProducto no encontrado');

        return;

      }

      const qty = parseFloat(quantityInput.value || 1);

      addRow(product, qty);

    });*/

    Quagga.offDetected(detectarProductoCotizacion);
Quagga.onDetected(detectarProductoCotizacion);

  });

  btnCloseScanner.addEventListener('click', () => {

    if (scannerRunning) {

      try { Quagga.stop(); } catch {}

      scannerRunning = false;

    }

    scannerOverlay.style.display = 'none';

  });

  scannerOverlay.addEventListener('click', e => {

    if (e.target === scannerOverlay) {

      if (scannerRunning) {

        try { Quagga.stop(); } catch {}

        scannerRunning = false;

      }

      scannerOverlay.style.display = 'none';

    }

  });



  // Establecer fecha de hoy si no se eligió ninguna

  updateTotals(); // Estado vacío, total inicial y total del encabezado.

  if (quoteDate && !quoteDate.value) {

    const now = new Date();
    const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    quoteDate.value = localDate.toISOString().slice(0, 10);

  }

});
