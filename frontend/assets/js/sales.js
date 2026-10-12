// Lógica del módulo de ventas
document.addEventListener('DOMContentLoaded', () => {
  // Verificar autenticación y poblar navegación
  const user = requireAuth();
  populateNav();
  document.getElementById('ventaUsuario').textContent = `Venta por ${user.username} (${user.role})`;

  // Elementos del DOM
  const saleTableBody = document.querySelector('#saleTable tbody');
  const saleSubtotalCell = document.getElementById('saleSubtotalCell');
  const saleIvaCell      = document.getElementById('saleIvaCell');
  const saleTotalCell    = document.getElementById('saleTotalCell');
  const ventaTotal       = document.getElementById('ventaTotal');
  const btnBuscarProducto= document.getElementById('btnBuscarProducto');
  const btnScanBarcode   = document.getElementById('btnScanBarcode');
  const btnCobrar        = document.getElementById('btnCobrar');
  const metodoPagoSelect = document.getElementById('metodoPago');
  const fieldMontoRecibido = document.getElementById('fieldMontoRecibido');
  const fieldReferencia    = document.getElementById('fieldReferencia');
  const fieldCaja          = document.getElementById('fieldCaja');
  const montoRecibidoInput = document.getElementById('montoRecibido');
  const referenciaInput    = document.getElementById('referenciaBancaria');
  const cajaSelect         = document.getElementById('cajaSelect');
  const cambioInfo         = document.getElementById('cambioInfo');
  const cambioMostrar      = document.getElementById('cambioMostrar');
  const confirmarCobroBtn  = document.getElementById('confirmarCobro');
  const clienteNombre      = document.getElementById('clienteNombre');
  const clienteDireccion   = document.getElementById('clienteDireccion');
  const clienteTelefono    = document.getElementById('clienteTelefono');
  const applyIvaSaleCheckbox = document.getElementById('applyIvaSale');

  // Datos en memoria
  let products = [];
  let barcodeMap = {};
  let codeMap = {};

  // Cargar productos para autocompletado
  apiRequest('products.php').then(data => {
    products = data || [];
    products.forEach(p => {
      if (p.barcode) barcodeMap[p.barcode] = p;
      if (p.code)    codeMap[p.code.toLowerCase()] = p;
      // Poblar datalist para autocompletado
      const optionCodigo = document.createElement('option');
      optionCodigo.value = p.code;
      document.getElementById('codigoList').appendChild(optionCodigo);
      const optionClave = document.createElement('option');
      optionClave.value = p.code;
      document.getElementById('claveList').appendChild(optionClave);
      const optionBarcode = document.createElement('option');
      optionBarcode.value = p.barcode;
      document.getElementById('barcodeList').appendChild(optionBarcode);
      const optionName = document.createElement('option');
      optionName.value = p.name;
      const nameListEl = document.getElementById('nameList');
      if (nameListEl) nameListEl.appendChild(optionName);
    });
  });

  // Cargar clientes para autocompletado (historial de nombres)
  const clientsList = document.getElementById('clientsList');
  if (clientsList) {
    apiRequest('clients.php')
      .then(res => {
        const list = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
        clientsList.innerHTML = '';
        list.forEach(n => {
          const opt = document.createElement('option');
          opt.value = String(n);
          clientsList.appendChild(opt);
        });
      })
      .catch(() => { /* autocompletado es opcional */ });
  }

  // Verificar stock disponible
  function checkStock(product, quantity) {
    const stock = parseFloat(product.stock ?? 0);
    const minStock = parseFloat(product.min_stock ?? 0);
    if (stock <= 0) {
      Swal.fire({ icon: 'warning', title: 'Sin existencias', text: `No hay stock suficiente para el producto: ${product.name}.` });
      return false;
    }
    // Avisar si el stock está en el mínimo
    if (stock <= minStock) {
      Swal.fire({ icon: 'warning', title: 'Stock mínimo', text: `El stock de ${product.name} está al mínimo. Se recomienda reabastecer.` });
    }
    if (quantity > stock) {
      Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: `No hay suficiente stock para el producto: ${product.name}. Stock disponible: ${stock}` });
      return false;
    }
    return true;
  }

  // Actualizar subtotales, IVA y total
  function updateTotals() {
    let subtotal = 0;
    let ivaTotal = 0;

    saleTableBody.querySelectorAll('tr').forEach(row => {
      const qtyInput   = row.querySelector('.qty-input');
      const priceInput = row.querySelector('.price-input');
      const qty   = parseFloat(qtyInput.value || 0);
      const price = parseFloat(priceInput.value || 0);
      const amount = qty * price;
      subtotal += amount;
      row.querySelector('.import-cell').textContent = '$' + amount.toFixed(2);
    });

    // IVA solo si el checkbox global está marcado
    if (applyIvaSaleCheckbox.checked) {
      ivaTotal = subtotal * 0.16;
    }

    const total = subtotal + ivaTotal;
    saleSubtotalCell.textContent = '$' + subtotal.toFixed(2);
    saleIvaCell.textContent      = '$' + ivaTotal.toFixed(2);
    saleTotalCell.textContent    = '$' + total.toFixed(2);
    ventaTotal.textContent       = 'TOTAL $' + total.toFixed(2);

    return { subtotal, iva: ivaTotal, total };
  }

  // Función para añadir una fila al detalle de venta
  function addRow(product, quantity = 1) {
    if (!product || !checkStock(product, quantity)) return;

    const tr = document.createElement('tr');

    // Índice
    const tdIndex = document.createElement('td');
    tdIndex.className = 'row-index';
    tr.appendChild(tdIndex);

    // Cantidad
    const tdQty = document.createElement('td');
    const qtyInput = document.createElement('input');
    qtyInput.type  = 'number';
    qtyInput.step  = '0.01';
    qtyInput.min   = '0.01';
    qtyInput.value = quantity;
    qtyInput.className = 'form-control qty-input';
    tdQty.appendChild(qtyInput);
    tr.appendChild(tdQty);

    // Descripción
    const tdDesc = document.createElement('td');
    tdDesc.textContent = product.name;
    tr.appendChild(tdDesc);

    // Empaque (solo lectura)
    const tdUnit = document.createElement('td');
    tdUnit.textContent = (product.unit || '').toString();
    tr.appendChild(tdUnit);

    // Precio unitario
    const tdPrice = document.createElement('td');
    const priceInput = document.createElement('input');
    priceInput.type = 'number';
    priceInput.step = '0.01';
    priceInput.min  = '0';
    priceInput.value = parseFloat(product.public_price).toFixed(2);
    priceInput.className = 'form-control price-input';
    tdPrice.appendChild(priceInput);
    tr.appendChild(tdPrice);

    // Importe (se actualiza en updateTotals)
    const tdImport = document.createElement('td');
    tdImport.className = 'import-cell';
    tr.appendChild(tdImport);

    // Acción (solo botón quitar; IVA global aplicado desde applyIvaSale)
    const tdRemove = document.createElement('td');
    const btnRemove = document.createElement('button');
    btnRemove.className = 'btn btn-sm btn-danger';
    btnRemove.textContent = 'Quitar';
    btnRemove.addEventListener('click', () => {
      tr.remove();
      updateTotals();
    });
    tdRemove.appendChild(btnRemove);
    tr.appendChild(tdRemove);

    tr.dataset.productId = product.id;
    saleTableBody.appendChild(tr);

    qtyInput.addEventListener('input', updateTotals);
    priceInput.addEventListener('input', updateTotals);
    updateTotals();
  }

  async function buscarProducto() {
    const codigo  = document.getElementById('buscarCodigo').value.trim();
    const clave   = document.getElementById('buscarClave').value.trim();
    const barcode = document.getElementById('buscarBarcode').value.trim();
    const nombre  = (document.getElementById('buscarNombre') || {}).value?.trim() || '';
    let product = null;
    if (barcode && barcodeMap[barcode]) {
      product = barcodeMap[barcode];
    } else if (codigo && codeMap[codigo.toLowerCase()]) {
      product = codeMap[codigo.toLowerCase()];
    } else if (clave && codeMap[clave.toLowerCase()]) {
      product = codeMap[clave.toLowerCase()];
    } else if (nombre) {
      product = products.find(pp => pp.name && pp.name.toLowerCase().includes(nombre.toLowerCase()));
    }
    if (!product) {
      Swal.fire({ icon: 'warning', title: 'Producto no encontrado', text: 'No encontramos un producto que coincida con tu búsqueda.' });
      return;
    }
    const { isConfirmed, value: qtyStr } = await Swal.fire({
    icon: 'question',
    title: 'Cantidad del producto',
    text: 'Indica cuántas unidades deseas agregar a la venta.',
    input: 'number',
    inputValue: 1,
    inputAttributes: {
        min: '0.01',
        step: '0.01'
    },
    showCancelButton: true,
    confirmButtonText: 'Agregar producto',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#0d704d',
    reverseButtons: true,
    inputValidator: value => {
        if (!value || !Number.isFinite(Number(value)) || Number(value) <= 0) {
            return 'Ingresa una cantidad válida mayor que cero.';
        }
    }
});

if (!isConfirmed) return;

    const qty = parseFloat(qtyStr);
    if (isNaN(qty) || qty <= 0) {
      Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Ingresa una cantidad mayor que cero.' });
      return;
    }
    addRow(product, qty);
    document.getElementById('buscarCodigo').value  = '';
    document.getElementById('buscarClave').value   = '';
    document.getElementById('buscarBarcode').value = '';
    const bnom = document.getElementById('buscarNombre'); if (bnom) bnom.value = '';
  }

  function iniciarEscaner() {
    if (typeof Quagga === 'undefined') {
      Swal.fire({ icon: 'error', title: 'Escáner no disponible', text: 'No se puede iniciar el escáner en este navegador.' });
      return;
    }
    const scannerContainer = document.getElementById('scannerContainer');
    Quagga.init({
      inputStream: {
        name:'Live',
        type:'LiveStream',
        target: scannerContainer,
        constraints: { facingMode:'environment' }
      },
      decoder: { readers: ['ean_reader','ean_8_reader','code_128_reader'] }
    }, err => {
      if (err) {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Error del escáner', text: 'No se pudo iniciar el escáner. Revisa los permisos de la cámara.' });
        return;
      }
      Quagga.start();
    });
    Quagga.onDetected(result => {
      const code = result.codeResult.code;
      document.getElementById('buscarBarcode').value = code;
      Quagga.stop();
      const scannerModal = bootstrap.Modal.getInstance(document.getElementById('scannerModal'));
      scannerModal.hide();
      buscarProducto();
    });
  }

  // Botones de búsqueda y escaneo
  btnBuscarProducto.addEventListener('click', buscarProducto);
  btnScanBarcode.addEventListener('click', () => {
    const scannerModal = new bootstrap.Modal(document.getElementById('scannerModal'));
    scannerModal.show();
    iniciarEscaner();
  });

  // Cargar cajas en el modal de cobro
  function loadCajas(kind) {
    // Reutiliza la versión que llena el selector principal de cajas
    const url = kind ? `cash_registers.php?kind=${encodeURIComponent(kind)}` : 'cash_registers.php';
    apiRequest(url).then(response => {
      cajaSelect.innerHTML = '';
      const list = (response && response.data) ? response.data : (Array.isArray(response) ? response : []);
      const allowNames = (kind === 'bank') ? ['BANAMEX', 'BANORTE'] : ['OFICINA', 'FERRETERIA'];
      const filtered = (Array.isArray(list) ? list : []).filter(caja => {
        const name = (caja.name || '').toString().trim().toUpperCase();
        return allowNames.includes(name);
      });
      filtered.forEach(caja => {
        const opt = document.createElement('option');
        opt.value = caja.id;
        opt.textContent = caja.name;
        cajaSelect.appendChild(opt);
      });
    }).catch(err => {
      console.error('Error al cargar cajas:', err);
      cajaSelect.innerHTML = '<option disabled>Error cargando cajas</option>';
    });
  }

  // Nueva: cargar cajas en un selector dado (para devoluciones)
  function loadCajasFor(kind, targetSelect) {
    const url = kind ? `cash_registers.php?kind=${encodeURIComponent(kind)}` : 'cash_registers.php';
    apiRequest(url).then(response => {
      targetSelect.innerHTML = '';
      const list = (response && response.data) ? response.data : (Array.isArray(response) ? response : []);
      const allowNames = (kind === 'bank') ? ['BANAMEX', 'BANORTE'] : ['OFICINA', 'FERRETERIA'];
      const filtered = (Array.isArray(list) ? list : []).filter(caja => {
        const name = (caja.name || '').toString().trim().toUpperCase();
        return allowNames.includes(name);
      });
      filtered.forEach(caja => {
        const opt = document.createElement('option');
        opt.value = caja.id;
        opt.textContent = caja.name;
        targetSelect.appendChild(opt);
      });
    }).catch(err => {
      console.error('Error al cargar cajas:', err);
      targetSelect.innerHTML = '<option disabled>Error cargando cajas</option>';
    });
  }

  // Mostrar modal de cobro solo si hay productos
  btnCobrar.addEventListener('click', () => {
    if (saleTableBody.querySelectorAll('tr').length === 0) {
      Swal.fire({
        icon: 'warning',
        iconColor: '#0d704d',
        title: 'Atención',
        text: 'Agrega al menos un producto',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#0d704d'
      });
      return;
    }
    const totals = updateTotals();
    montoRecibidoInput.value = totals.total.toFixed(2);
    cambioMostrar.value = '';

    // Preseleccionar "Efectivo" y configurar campos/ cuentas ANTES de mostrar
    metodoPagoSelect.value = 'Efectivo';
    fieldMontoRecibido.style.display = '';
    fieldReferencia.style.display    = 'none';
    fieldCaja.style.display          = '';
    cambioInfo.style.display         = '';
    loadCajas('cash');

    const cobroModal = new bootstrap.Modal(document.getElementById('cobroModal'));
    cobroModal.show();
  });

  // Cambiar campos según método de pago
  metodoPagoSelect.addEventListener('change', () => {
    const metodo = metodoPagoSelect.value;
    if (metodo === 'Efectivo') {
      fieldMontoRecibido.style.display = '';
      fieldReferencia.style.display    = 'none';
      fieldCaja.style.display          = '';
      cambioInfo.style.display         = '';
      loadCajas('cash');
    } else if (metodo === 'Transferencia') {
      fieldMontoRecibido.style.display = 'none';
      fieldReferencia.style.display    = '';
      fieldCaja.style.display          = '';
      cambioInfo.style.display         = 'none';
      loadCajas('bank');
    } else if (metodo === 'Cuenta') {
      fieldMontoRecibido.style.display = 'none';
      fieldReferencia.style.display    = 'none';
      fieldCaja.style.display          = 'none';
      cambioInfo.style.display         = 'none';
    } else {
      fieldMontoRecibido.style.display = 'none';
      fieldReferencia.style.display    = 'none';
      fieldCaja.style.display          = 'none';
      cambioInfo.style.display         = 'none';
    }
  });

  // Calcular cambio al modificar el monto recibido
  montoRecibidoInput.addEventListener('input', () => {
    const totals = updateTotals();
    const recibido = parseFloat(montoRecibidoInput.value || 0);
    const cambio   = recibido - totals.total;
    if (!isNaN(cambio)) {
      cambioMostrar.value = '$' + cambio.toFixed(2);
    }
  });

  // Confirmar cobro: verificar stock y enviar datos al backend
  confirmarCobroBtn.addEventListener('click', () => {
    const metodo = metodoPagoSelect.value;
    const totals = updateTotals();
    let recibido   = null;
    let referencia = null;
    let cajaId     = null;

    if (metodo === 'Efectivo') {
      recibido = parseFloat(montoRecibidoInput.value || 0);
      if (recibido < totals.total) {
        Swal.fire({ icon: 'warning', title: 'Monto insuficiente', text: 'El monto recibido es menor que el total de la venta.' });
        return;
      }
      cajaId = cajaSelect.value;
    } else if (metodo === 'Transferencia') {
      referencia = referenciaInput.value.trim();
      if (!referencia) {
        Swal.fire({ icon: 'warning', title: 'Referencia requerida', text: 'Debes ingresar una referencia bancaria.' });
        return;
      }
      cajaId = cajaSelect.value;
    }

    // Verificar stock total por producto antes de enviar
    const agregados = {};
    saleTableBody.querySelectorAll('tr').forEach(row => {
      const pid  = row.dataset.productId;
      const qty  = parseFloat(row.querySelector('.qty-input').value || 0);
      agregados[pid] = (agregados[pid] || 0) + qty;
    });
    for (const pid in agregados) {
      const product = products.find(p => p.id == pid || p.id == parseInt(pid));
      if (product && product.stock !== undefined && agregados[pid] > product.stock) {
        Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: `No hay suficiente stock para el producto: ${product.name}. Stock disponible: ${product.stock}` });
        return;
      }
    }

    // Construir items
    const items = [];
    saleTableBody.querySelectorAll('tr').forEach(row => {
      const productId = row.dataset.productId;
      const qty       = parseFloat(row.querySelector('.qty-input').value || 0);
      const price     = parseFloat(row.querySelector('.price-input').value || 0);
      items.push({ product_id: productId, quantity: qty, price });
    });

    // Preparar payload para backend
    const payload = {
      items,
      client_name   : clienteNombre.value.trim()    || null,
      client_address: clienteDireccion.value.trim() || null,
      client_phone  : clienteTelefono.value.trim()  || null,
      payment_method: metodo.toLowerCase(),
      bank_reference: referencia || null,
      cash_register_id: cajaId || null,
      apply_iva: applyIvaSaleCheckbox.checked  // Agregar este campo
    };
    if (metodo === 'Efectivo') {
      payload.cash_received = recibido;
    }

    // Enviar venta
    apiRequest('sales.php', 'POST', payload)
      .then(result => {
        if (result.change !== undefined && result.change !== null) {
          Swal.fire({ icon: 'success', title: 'Venta registrada', text: 'Cambio: $' + parseFloat(result.change).toFixed(2) });
        }
        if (result.ticket) {
          window.open('../backend/' + result.ticket, '_blank');
        }
        // Limpiar la vista y ocultar modal
        saleTableBody.innerHTML = '';
        saleSubtotalCell.textContent = '$0.00';
        saleIvaCell.textContent      = '$0.00';
        saleTotalCell.textContent    = '$0.00';
        ventaTotal.textContent       = 'TOTAL $0.00';
        clienteNombre.value = '';
        clienteDireccion.value = '';
        clienteTelefono.value = '';
        const cobroModal = bootstrap.Modal.getInstance(document.getElementById('cobroModal'));
        cobroModal.hide();
      })
      .catch(err => {
        Swal.fire({ icon: 'error', title: 'Error al registrar la venta', text: err.error || 'No fue posible completar la operación.' });
      });
  });

  // Recalcular totales si se cambia el checkbox global de IVA
  applyIvaSaleCheckbox.addEventListener('change', updateTotals);

  // ===== Devoluciones / Cambios =====
  const btnReturn = document.getElementById('btnReturn');
  const btnLoadReturnSale = document.getElementById('btnLoadReturnSale');
  const btnConfirmReturn = document.getElementById('btnConfirmReturn');
  const returnSaleIdInput = document.getElementById('returnSaleId');
  const returnSaleInfo = document.getElementById('returnSaleInfo');
  const returnSaleFolio = document.getElementById('returnSaleFolio');
  const returnSaleClient = document.getElementById('returnSaleClient');
  const returnItemsTable = document.querySelector('#returnItemsTable tbody');
  const returnAmountEl = document.getElementById('returnAmount');
  const returnTypeSelect = document.getElementById('returnType');
  const returnPaymentMethod = document.getElementById('returnPaymentMethod');
  const returnCashSelect = document.getElementById('returnCashSelect');

  btnReturn.addEventListener('click', () => {
    const rm = new bootstrap.Modal(document.getElementById('returnModal'));
    // reset
    returnSaleIdInput.value = '';
    returnItemsTable.innerHTML = '';
    document.getElementById('returnNewItemsList').innerHTML = '';
    document.getElementById('returnProductSelect').innerHTML = '';
    document.getElementById('returnProductQty').value = '1';
    document.getElementById('returnProductPrice').value = '0.00';
    returnSaleInfo.style.display = 'none';
    returnAmountEl.textContent = '$0.00';

    populateReturnProductsSelect();
    // Preload cajas para el selector de devoluciones
    returnPaymentMethod.value = 'efectivo';
    loadCajasFor('cash', returnCashSelect);

    // Mostrar botón de solicitudes si es ADMIN
    const btnViewRequests = document.getElementById('btnViewRequests');
    if (user && user.role && user.role.toUpperCase() === 'ADMIN') {
      btnViewRequests.style.display = '';
    } else {
      btnViewRequests.style.display = 'none';
    }

    rm.show();
  });

  // Mostrar/ocultar sección new items según tipo
  returnTypeSelect.addEventListener('change', () => {
    if (returnTypeSelect.value === 'CAMB') {
      document.getElementById('returnNewItems').style.display = '';
    } else {
      document.getElementById('returnNewItems').style.display = 'none';
      document.getElementById('returnNewItemsList').innerHTML = '';
    }
    updateReturnTotals();
  });

  // Populate product select for changes
  function populateReturnProductsSelect() {
    const sel = document.getElementById('returnProductSelect');
    sel.innerHTML = '';
    products.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.name + ' (stk:' + (p.stock ?? 0) + ')';
      sel.appendChild(opt);
    });
    // set price to first product's price
    const first = products[0];
    if (first) document.getElementById('returnProductPrice').value = parseFloat(first.public_price || 0).toFixed(2);
  }

  // Add new item to change list
  document.getElementById('btnAddReturnNewItem').addEventListener('click', (e) => {
    e.preventDefault();
    const pid = parseInt(document.getElementById('returnProductSelect').value || 0);
    const qty = parseFloat(document.getElementById('returnProductQty').value || 0);
    const price = parseFloat(document.getElementById('returnProductPrice').value || 0);
    if (!pid || qty <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Selecciona un producto y una cantidad válida.' });
    const prod = products.find(p => p.id == pid);
    if (!prod) return Swal.fire({ icon: 'warning', title: 'Producto no encontrado', text: 'El producto seleccionado no está disponible.' });
    if (qty > (prod.stock ?? 0)) return Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: 'No hay suficiente stock para ese producto.' });

    const list = document.getElementById('returnNewItemsList');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center mb-1';
    row.innerHTML = `<div class="flex-grow-1">${prod.name}</div><div>$${price.toFixed(2)}</div><div>x ${qty}</div><button class="btn btn-sm btn-danger btn-remove-return-item">Quitar</button>`;
    row.dataset.pid = pid;
    row.dataset.qty = qty;
    row.dataset.price = price;
    list.appendChild(row);

    // Adjust stock locally to prevent adding more than available
    prod.stock = (prod.stock ?? 0) - qty;
    populateReturnProductsSelect();
    updateReturnTotals();
  });

  // View pending requests (ADMIN)
  document.getElementById('btnViewRequests').addEventListener('click', () => {
    const rm = new bootstrap.Modal(document.getElementById('returnRequestsModal'));
    // Load pending requests
    apiRequest('returns.php?action=list_requests').then(res => {
      const tbody = document.querySelector('#requestsTable tbody');
      tbody.innerHTML = '';
      (res.requests || []).forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.id}</td>
          <td>${r.sale_id}</td>
          <td>${r.type}</td>
          <td>${r.requester_name || r.requester_id}</td>
          <td>${r.items ? JSON.parse(r.items).reduce((s,i)=>s + (parseFloat(i.unit_price||i.price||0) * parseFloat(i.quantity||0)),0).toFixed(2) : ''}</td>
          <td>${r.created_at}</td>
          <td>
          <button class="btn btn-sm btn-success btn-approve-request" data-id="${r.id}">Aprobar</button>
          <button class="btn btn-sm btn-danger btn-reject-request" data-id="${r.id}">Rechazar</button>
        </td>
        `;
        tbody.appendChild(tr);
      });
      rm.show();
    }).catch(err => {
      Swal.fire({
        icon: 'error',
        title: 'Error al cargar solicitudes',
        text: err.error || 'No fue posible obtener las solicitudes.'
    });
    });
  });

  // Approve request handler (delegated)
 
  // Aprobar y rechazar solicitudes de devoluciones
  document.addEventListener('click', async (e) => {

    const btn = e.target.closest('.btn-approve-request, .btn-reject-request');
    if (!btn) return;

    const id = parseInt(btn.dataset.id || 0, 10);
    const row = btn.closest('tr');

    if (!id) {
      await Swal.fire({
        icon: 'error',
        title: 'Solicitud inválida',
        text: 'El identificador de la solicitud no es válido.'
      });
      return;
    }

    // ===== APROBAR SOLICITUD =====
    if (btn.classList.contains('btn-approve-request')) {

      const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: '¿Aprobar solicitud?',
        text: '¿Estás seguro de que deseas aprobar esta devolución?',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d704d',
        reverseButtons: true
      });

      if (!isConfirmed) return;

      btn.disabled = true;

      try {
        const res = await apiRequest('returns.php', 'POST', {
          action: 'approve',
          request_id: id
        });

        if (res?.ok === false || res?.error) {
          throw new Error(res.error || 'No se pudo aprobar la solicitud.');
        }

        if (res.ticket) {
          window.open('../backend/' + res.ticket, '_blank');
        }

        await Swal.fire({
          icon: 'success',
          title: 'Solicitud aprobada',
          text: 'Folio de devolución: ' + (res.refund_id || 'No disponible'),
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#0d704d'
        });

        row?.remove();

      } catch (err) {
        await Swal.fire({
          icon: 'error',
          title: 'Error al aprobar',
          text: err.error || err.message || 'No se pudo aprobar la solicitud.'
        });

        btn.disabled = false;
      }

      return;
    }

    // ===== RECHAZAR SOLICITUD =====
    if (btn.classList.contains('btn-reject-request')) {

      const { isConfirmed, value: reason } = await Swal.fire({
        icon: 'warning',
        title: '¿Rechazar solicitud?',
        text: 'Puedes indicar el motivo del rechazo.',
        input: 'textarea',
        inputPlaceholder: 'Motivo del rechazo (opcional)',
        showCancelButton: true,
        confirmButtonText: 'Sí, rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d704d',
        reverseButtons: true
      });

      if (!isConfirmed) return;

      btn.disabled = true;

      try {
        const res = await apiRequest('returns.php', 'POST', {
          action: 'reject',
          request_id: id,
          reason: reason || ''
        });

        if (!res?.ok) {
          throw new Error(res?.error || 'No se pudo rechazar la solicitud.');
        }

        await Swal.fire({
          icon: 'success',
          title: 'Solicitud rechazada',
          text: 'La solicitud fue rechazada correctamente.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#0d704d'
        });

        row?.remove();

      } catch (err) {
        await Swal.fire({
          icon: 'error',
          title: 'Error al rechazar',
          text: err.error || err.message || 'No se pudo rechazar la solicitud.'
        });

        btn.disabled = false;
      }
    }

  });


  // Remove new item
  document.addEventListener('click', (e) => {
    if (e.target && e.target.classList && e.target.classList.contains('btn-remove-return-item')) {
      const row = e.target.closest('div');
      const pid = parseInt(row.dataset.pid || 0);
      const qty = parseFloat(row.dataset.qty || 0);
      row.remove();
      // restore stock locally
      const prod = products.find(p => p.id == pid);
      if (prod) prod.stock = (prod.stock ?? 0) + qty;
      populateReturnProductsSelect();
      updateReturnTotals();
    }
  });

  // Update totals including new items
  function updateReturnTotals() {
    let total = 0;
    document.querySelectorAll('.return-qty').forEach(inp => {
      const qty = parseFloat(inp.value || 0);
      const price = parseFloat(inp.dataset.price || 0);
      const sub = qty * price;
      inp.closest('tr').querySelector('.subtotal-cell').textContent = '$' + sub.toFixed(2);
      total += sub;
    });

    // Sum new items values (these reduce the refund amount)
    let newTotal = 0;
    document.querySelectorAll('#returnNewItemsList > div').forEach(div => {
      const q = parseFloat(div.dataset.qty || 0);
      const p = parseFloat(div.dataset.price || 0);
      newTotal += q * p;
    });

    const amountToReturn = total - newTotal;
    returnAmountEl.textContent = '$' + amountToReturn.toFixed(2);

    // Visual cue: if negative, customer owes money
    if (amountToReturn < 0) {
      returnAmountEl.style.color = 'darkgreen';
    } else if (amountToReturn > 0) {
      returnAmountEl.style.color = 'crimson';
    } else {
      returnAmountEl.style.color = '';
    }

    return amountToReturn;
  }

  btnLoadReturnSale.addEventListener('click', () => {
    const idOrFolio = returnSaleIdInput.value.trim();
    if (!idOrFolio) return Swal.fire({
    icon: 'warning',
    title: 'Folio requerido',
    text: 'Ingresa el ID o folio de la venta que deseas consultar.',
    confirmButtonColor: '#0d704d'
});

    // Intentar como id numérico primero
    let url = 'sales.php?id=' + encodeURIComponent(idOrFolio);
    apiRequest(url).then(res => {
      const sale = res.sale;
      if (!sale) return Swal.fire({
    icon: 'warning',
    title: 'Venta no encontrada',
    text: 'No encontramos ninguna venta con el folio proporcionado.',
    confirmButtonColor: '#0d704d'
});
      // Poblar info
      returnSaleFolio.textContent = sale.ticket_barcode || ('#' + sale.id);
      returnSaleClient.textContent = sale.client_name || '';
      returnItemsTable.innerHTML = '';
      sale.items.forEach(it => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${it.product_name || ''}</td>
          <td class="text-end">${it.quantity}</td>
          <td class="text-end"><input type="number" min="0" step="0.01" max="${it.quantity}" value="0" class="form-control form-control-sm return-qty" data-pid="${it.product_id}" data-sid="${it.id}" data-price="${parseFloat(it.price || it.unit_price || 0).toFixed(2)}"></td>
          <td class="text-end">$${parseFloat(it.price || it.unit_price || 0).toFixed(2)}</td>
          <td class="text-end subtotal-cell">$0.00</td>
        `;
        returnItemsTable.appendChild(tr);
      });

      returnSaleInfo.style.display = '';
      // Guardar id numérico para enviar en el payload
      returnSaleIdInput.value = String(sale.id);
      updateReturnTotals();
    }).catch(err => {
      Swal.fire({
    icon: 'error',
    title: 'Error al consultar la venta',
    text: err.error || 'No fue posible obtener la información de la venta.',
    confirmButtonColor: '#0d704d'
});
    });
  });

  // Cambiar cajas según método (para el selector de devoluciones)
  returnPaymentMethod.addEventListener('change', () => {
    const pm = returnPaymentMethod.value;
    if (pm === 'transferencia') {
      // cargar cajas bancarias en el selector de devoluciones
      loadCajasFor('bank', returnCashSelect);
      return;
    }
    if (pm === 'efectivo') {
      loadCajasFor('cash', returnCashSelect);
      return;
    }
    // cuenta
    returnCashSelect.innerHTML = '';
  });

  // Delegación para inputs de qty
  document.addEventListener('input', (e) => {
    if (e.target && e.target.classList && e.target.classList.contains('return-qty')) {
      updateReturnTotals();
    }
  });

  btnConfirmReturn.addEventListener('click', () => {
if (returnItemsTable.querySelectorAll('tr').length === 0) return Swal.fire({
    icon: 'warning',
    title: 'Venta requerida',
    text: 'Primero debes cargar una venta para realizar una devolución.',
    confirmButtonColor: '#0d704d'
});    const items = [];
    document.querySelectorAll('.return-qty').forEach(inp => {
      const qty = parseFloat(inp.value || 0);
      if (qty <= 0) return;
      items.push({ sale_item_id: parseInt(inp.dataset.sid), product_id: parseInt(inp.dataset.pid), quantity: qty, unit_price: parseFloat(inp.dataset.price) });
    });
if (items.length === 0) return Swal.fire({
    icon: 'warning',
    title: 'Selecciona un producto',
    text: 'Debes seleccionar al menos un artículo y especificar la cantidad a devolver.',
    confirmButtonColor: '#0d704d'
});
    // Construir new_items desde la lista de productos añadidos (si hay)
    const new_items = [];
    document.querySelectorAll('#returnNewItemsList > div').forEach(div => {
      new_items.push({ product_id: parseInt(div.dataset.pid), quantity: parseFloat(div.dataset.qty), unit_price: parseFloat(div.dataset.price) });
    });

    // Si es REEMBOLSO y el usuario NO es ADMIN, crear una solicitud en lugar de procesarla directamente
    if (returnTypeSelect.value === 'REMB' && (!user || !user.role || user.role.toUpperCase() !== 'ADMIN')) {
      const reqPayload = {
        action: 'request',
        sale_id: returnSaleIdInput.value.trim(),
        type: 'REMB',
        items,
        new_items,
        payment_method: returnPaymentMethod.value,
        cash_register_id: returnCashSelect.value || null,
        bank_reference: null,
        note: ''
      };
      apiRequest('returns.php', 'POST', reqPayload).then(res => {
       Swal.fire({
    icon: 'success',
    title: 'Solicitud enviada',
    text: 'Solicitud #' + (res.request_id || '') + ' creada correctamente. Un administrador deberá aprobarla.',
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#0d704d'
});
        const rm = bootstrap.Modal.getInstance(document.getElementById('returnModal'));
        if (rm) rm.hide();
      }).catch(err => {
        Swal.fire({
    icon: 'error',
    title: 'Error al crear la solicitud',
    text: err.error || 'No fue posible registrar la solicitud de devolución.',
    confirmButtonColor: '#0d704d'
});
      });
      return;
    }

    const payload = {
      sale_id: returnSaleIdInput.value.trim(),
      type: returnTypeSelect.value,
      items,
      new_items: new_items,
      payment_method: returnPaymentMethod.value,
      cash_register_id: returnCashSelect.value || null,
      bank_reference: null,
      note: ''
    };

    apiRequest('returns.php', 'POST', payload).then(res => {
      if (res.ticket) {
        window.open('../backend/' + res.ticket, '_blank');
      }
      Swal.fire({
    icon: 'success',
    title: 'Devolución registrada',
    text: 'La devolución se registró correctamente. Folio: ' + (res.refund_id || 'No disponible'),
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#0d704d'
});
      const rm = bootstrap.Modal.getInstance(document.getElementById('returnModal'));
      if (rm) rm.hide();
    }).catch(err => {
    Swal.fire({
        icon: 'error',
        title: 'Error al procesar la devolución',
        text: err?.error || err?.message || 'No fue posible completar la devolución.',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#0d704d'
    });
});
  });

});
