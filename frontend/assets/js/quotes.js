document.addEventListener('DOMContentLoaded', () => {
  // Mantener autenticación y barra (si se usan en main.js)
  const user = (typeof requireAuth === 'function') ? requireAuth() : { role: 'ADMIN' };
  if (typeof populateNav === 'function') populateNav();

  // Elementos de la tabla y totales
  const tableBody = document.querySelector('#quoteTable tbody');
  const subtotalCell = document.getElementById('subtotalCell');
  const ivaCell = document.getElementById('ivaCell');
  const totalCell = document.getElementById('totalCell');

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
  const barcodeMap = {};

  // Cargar productos desde el backend
  apiRequest('products.php').then(data => {
    products = Array.isArray(data) ? data : [];
    products.forEach(p => {
      const bar = (p.barcode || '').trim().toLowerCase();
      if (bar) barcodeMap[bar] = p;
    });
    updateDatalist('');
  });

  // Actualizar datalist según búsqueda
  function updateDatalist(filter) {
    const f = (filter || '').toLowerCase().trim();
    productList.innerHTML = '';
    let count = 0;
    products.forEach(p => {
      const keys = `${(p.name || '').toLowerCase()} ${(p.code || '').toLowerCase()} ${(p.barcode || '').toLowerCase()}`;
      if (f && !keys.includes(f)) return;
      const opt = document.createElement('option');
      opt.value = `${p.name || ''} | ${p.code || ''} | ${p.barcode || ''}`;
      productList.appendChild(opt);
      if (++count >= 50) return;
    });
  }

  // Buscar un producto por nombre, código o código de barras
  function findProduct(query) {
    const t = (query || '').toLowerCase().trim();
    if (!t) return null;
    // Exacto por código o barras
    let prod = products.find(p => (p.code || '').toLowerCase() === t || (p.barcode || '').toLowerCase() === t);
    if (prod) return prod;
    // Dividir por "|" (viene del datalist)
    const parts = query.split('|').map(s => s.trim().toLowerCase());
    if (parts.length >= 2) {
      const code = parts[1] || '';
      const bar  = parts[2] || '';
      prod = products.find(p => (p.code || '').toLowerCase() === code || (p.barcode || '').toLowerCase() === bar);
      if (prod) return prod;
      const name = parts[0] || '';
      prod = products.find(p => (p.name || '').toLowerCase() === name);
      if (prod) return prod;
    }
    // Parcial por nombre
    return products.find(p => (p.name || '').toLowerCase().includes(t));
  }

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
    overlay.style.position = 'fixed';
    overlay.style.top = 0;
    overlay.style.left = 0;
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.background = 'rgba(255, 255, 255, 0.5)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.zIndex = 4000;

    // Crear caja del modal
    const box = document.createElement('div');
    box.className = 'bg-light p-4 rounded';
    box.style.minWidth = '280px';
    box.innerHTML = `
      <h5>Editar precio de venta</h5>
      <p>Precio de compra: <strong>$${purchasePrice.toFixed(2)}</strong></p>
      <div class="mb-2">
        <label class="form-label">Margen (%)</label>
        <input type="number" step="5" id="marginInput" class="form-control"
               value="${((currentPrice / purchasePrice) - 1) * 100}">
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
      const newMargin = ((p / purchasePrice) - 1) * 100;
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
        alert('Precio inválido');
        return;
      }
      if (newPrice < purchasePrice) {
        alert('El precio de venta no puede ser menor al precio de compra.');
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
    const ivaRow = ivaCell.closest('tr');
    if (ivaRow) ivaRow.style.display = includeIva.checked ? '' : 'none';
  }

  // Lógica de búsqueda y escaneo
  function addFromSearch() {
    const query = searchProduct.value.trim();
    const qty   = parseFloat(quantityInput.value || 1);
    const prod  = findProduct(query);
    if (!prod) {
      alert('Producto no encontrado');
      return;
    }
    addRow(prod, qty);
    searchProduct.value = '';
  }

  searchProduct.addEventListener('input', () => updateDatalist(searchProduct.value));
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
  btnGenerate.addEventListener('click', () => {
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
});

  // Escáner (QuaggaJS)
  let scannerRunning = false;
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
        alert('No se pudo iniciar el escáner. Revisa los permisos de la cámara.');
        scannerOverlay.style.display = 'none';
        return;
      }
      scannerRunning = true;
      Quagga.start();
    });
    Quagga.onDetected(result => {
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
    });
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
  if (quoteDate && !quoteDate.value) {
    quoteDate.value = new Date().toISOString().split('T')[0];
  }
});
