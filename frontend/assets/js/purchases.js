document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();

  const params = new URLSearchParams(window.location.search);
  const supplierId = params.get('supplier_id');
  if (!supplierId) {
    alert('Proveedor no especificado');
    window.location.href = 'suppliers.html';
    return;
  }

  // Elementos base
  const supplierInput = document.getElementById('purchaseSupplier');
  const dateInput = document.getElementById('purchaseDate');
  const productSelect = document.getElementById('itemProduct');
  const presentationSelect = document.getElementById('itemPresentation');
  const quantityInput = document.getElementById('itemQuantity');
  const priceInput = document.getElementById('itemUnitPrice');

  // NUEVOS (agregados en purchases_new.html)
  const publicPriceInput = document.getElementById('itemPublicPrice');
  const marginInput = document.getElementById('itemProfitMargin');

  const itemsTableBody = document.querySelector('#itemsTable tbody');
  const itemsTotalCell = document.getElementById('itemsTotal');
  const btnAddItem = document.getElementById('btnAddItem');
  const btnFinalize = document.getElementById('btnFinalizePurchase');
  const vatSelect = document.getElementById('purchaseVat');
  const paymentSelect = document.getElementById('purchasePayment');
  const notesInput = document.getElementById('purchaseNotes');
  const receiptInput = document.getElementById('purchaseReceipt');
  const folioInput = document.getElementById('purchaseFolio');
  const accountSelect = document.getElementById('purchaseAccount');
  const bankOpInput = document.getElementById('purchaseBankOperation');

  // Contenedor de detalles de pago (en el mismo form)
  const paymentDetails = document.getElementById('purchasePaymentDetails');

  if (!publicPriceInput || !marginInput) {
    alert('Faltan inputs: itemPublicPrice y/o itemProfitMargin en purchases_new.html');
    return;
  }

  let items = [];
  let receiptPath = null;

  // ===== Helpers =====
  const n = (v) => {
    const x = parseFloat(String(v ?? '').replace(',', '.'));
    return Number.isFinite(x) ? x : 0;
  };

  // margen = (venta - compra) / compra
  const calcMargin = (buy, sell) => {
    buy = n(buy);
    sell = n(sell);
    if (buy <= 0) return 0;
    return (sell - buy) / buy;
  };

  // venta = compra * (1 + margen)
  const calcSell = (buy, margin) => {
    buy = n(buy);
    margin = n(margin);
    if (buy <= 0) return 0;
    return buy * (1 + margin);
  };

  const fmt2 = (v) => (Number.isFinite(v) ? v.toFixed(2) : '0.00');
  const fmtMargin = (m) => (Number.isFinite(m) ? m.toFixed(3) : '0.000'); // 0.180

  let currentProduct = null;

  // Fecha actual por defecto
  const today = new Date().toISOString().slice(0, 10);
  dateInput.value = today;

  // --- Cajas/Cuentas para salida (efectivo/transferencia)
  let cashRegisters = [];

  function normalizePaymentMethod(v) {
    const t = (v || '').toString().toLowerCase();
    if (t.includes('efect')) return 'cash';
    if (t.includes('transfer')) return 'bank';
    if (t.includes('pend')) return 'pending';
    if (t.includes('cuenta')) return 'cuenta';
    return 'cash';
  }

  function renderAccountOptions(kind) {
    if (!accountSelect) return;
    accountSelect.innerHTML = '';

    // Filtrado estricto por tipo + nombres base
    const CASH_NAMES = ['OFICINA', 'FERRETERIA'];
    const BANK_NAMES = ['BANAMEX', 'BANORTE'];

    const filtered = cashRegisters.filter(r => {
      const type = (r.type || 'CASH').toString().toUpperCase();
      const name = (r.name || '').toString().trim().toUpperCase();
      if (kind === 'cash') return type === 'CASH' && CASH_NAMES.includes(name);
      if (kind === 'bank') return type === 'BANK' && BANK_NAMES.includes(name);
      return false;
    });

    filtered.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.name;
      accountSelect.appendChild(opt);
    });
  }

  function updatePaymentDetailsUI() {
    if (!paymentDetails) return;
    const kind = normalizePaymentMethod(paymentSelect.value);

    // NUNCA ocultamos todo el bloque para no perder el Folio.
    paymentDetails.classList.remove('d-none');

    // Caja/Cuenta: solo para efectivo/transferencia. Ocultamos para PENDIENTE y CUENTA (no debe elegir caja)
    const accountCol = accountSelect?.closest('.col-md-4');
    const hideAccount = (kind === 'pending' || kind === 'cuenta');
    accountCol?.classList.toggle('d-none', hideAccount);

    if (!hideAccount) {
      renderAccountOptions(kind);
    }

    // Banco: pedir número de operación
    if (bankOpInput) {
      bankOpInput.closest('.col-md-4')?.classList.toggle('d-none', kind !== 'bank');
    }
  }

  async function loadAccountsForPayment() {
    const method = (paymentSelect.value || '').toLowerCase();
    const kind = method === 'efectivo' ? 'CASH' : (method === 'transferencia' ? 'BANK' : '');
    const endpoint = kind ? `cash_registers.php?kind=${encodeURIComponent(kind)}` : 'cash_registers.php';
    try {
      const response = await apiRequest(endpoint);
      // Handle response: {ok:true, data:[...]} o array directo
      cashRegisters = (response && response.data) ? response.data : (Array.isArray(response) ? response : (response?.registers || []));
      if (!Array.isArray(cashRegisters)) {
        console.warn('cashRegisters no es un array:', cashRegisters);
        cashRegisters = [];
      }
    } catch (e) {
      console.error('Error cargando cuentas:', e);
      cashRegisters = [];
    }
    updatePaymentDetailsUI();
  }

  loadAccountsForPayment();

  paymentSelect.addEventListener('change', () => {
    loadAccountsForPayment();
  });

  // Cargar proveedor
  apiRequest(`suppliers.php?id=${supplierId}`).then(sp => {
    supplierInput.value = sp.name;
  });

  // Cargar productos del proveedor
  apiRequest(`supplier_products.php?id=${supplierId}`).then(list => {
    productSelect.innerHTML = '';
    (list || []).forEach(prod => {
      const opt = document.createElement('option');
      opt.value = prod.id;
      opt.textContent = `${prod.code || ''} - ${prod.name}`;
      productSelect.appendChild(opt);
    });
    if (productSelect.value) loadSelectedProduct();
  });

  async function loadSelectedProduct() {
    const prodId = parseInt(productSelect.value);
    if (!prodId) return;

    try {
      const prod = await apiRequest(`products.php?id=${prodId}`);
      currentProduct = prod;

      const vigenteVenta = n(prod.public_price);
      let vigenteMargen = n(prod.profit_margin);

      // si no hay margen guardado, calcularlo con compra vs venta si es posible
      if (!vigenteMargen && n(prod.purchase_price) > 0 && vigenteVenta > 0) {
        vigenteMargen = calcMargin(n(prod.purchase_price), vigenteVenta);
      }

      // Mostrar vigentes
      publicPriceInput.value = vigenteVenta > 0 ? fmt2(vigenteVenta) : '';
      marginInput.value = vigenteMargen ? fmtMargin(vigenteMargen) : '';

      // Si ya hay precio compra escrito, recalcular venta con margen vigente
      const buy = n(priceInput.value);
      if (buy > 0 && vigenteMargen) {
        publicPriceInput.value = fmt2(calcSell(buy, vigenteMargen));
      }
    } catch (e) {
      console.error('No se pudo cargar producto:', e);
      currentProduct = null;
      publicPriceInput.value = '';
      marginInput.value = '';
    }
  }

  productSelect.addEventListener('change', () => {
    loadSelectedProduct();
  });

  // Si cambia compra -> recalcula venta usando margen
  priceInput.addEventListener('input', () => {
    const buy = n(priceInput.value);
    const margin = n(marginInput.value);
    if (buy > 0 && margin) {
      publicPriceInput.value = fmt2(calcSell(buy, margin));
    } else if (buy > 0 && !margin) {
      // si hay venta, calcula margen
      const sell = n(publicPriceInput.value);
      if (sell > 0) marginInput.value = fmtMargin(calcMargin(buy, sell));
    }
  });

  // Si cambia margen -> recalcula venta
  marginInput.addEventListener('input', () => {
    const buy = n(priceInput.value);
    const margin = n(marginInput.value);
    if (buy > 0 && margin) {
      publicPriceInput.value = fmt2(calcSell(buy, margin));
    }
  });

  // Si cambia venta -> recalcula margen
  publicPriceInput.addEventListener('input', () => {
    const buy = n(priceInput.value);
    const sell = n(publicPriceInput.value);
    if (buy > 0 && sell > 0) {
      marginInput.value = fmtMargin(calcMargin(buy, sell));
    }
  });

  // Subida de recibo
  receiptInput.addEventListener('change', () => {
    const file = receiptInput.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    fetch(`${API_BASE}/upload_image.php`, {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.path) {
          receiptPath = data.path.replace('/backend', '..');
        } else {
          alert(data.error || 'Error al subir factura');
        }
      })
      .catch(() => alert('Error al subir factura'));
  });

  // Añadir item
  btnAddItem.addEventListener('click', (ev) => {
    ev.preventDefault();

    const prodId = parseInt(productSelect.value);
    const presentation = presentationSelect.value;
    const qty = n(quantityInput.value);
    const unitPrice = n(priceInput.value);

    // snapshot del momento:
    const publicPriceAtBuy = n(publicPriceInput.value);
    const marginAtBuy = n(marginInput.value);

    if (!prodId || qty <= 0 || unitPrice <= 0) {
      alert('Complete todos los campos del item');
      return;
    }

    let finalPublic = publicPriceAtBuy;
    let finalMargin = marginAtBuy;

    if (finalPublic <= 0) {
      if (finalMargin) finalPublic = calcSell(unitPrice, finalMargin);
      else if (currentProduct && n(currentProduct.public_price) > 0) finalPublic = n(currentProduct.public_price);
      else finalPublic = 0;
    }

    if (!finalMargin && unitPrice > 0 && finalPublic > 0) {
      finalMargin = calcMargin(unitPrice, finalPublic);
    }

    const importe = qty * unitPrice;

    // Guardar snapshot histórico en el item:
    items.push({
      product_id: prodId,
      presentation,
      quantity: qty,
      unit_price: unitPrice,
      public_price: finalPublic,
      profit_margin: finalMargin
    });

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${productSelect.options[productSelect.selectedIndex].text}</td>
      <td>${presentation}</td>
      <td>${qty}</td>
      <td>$${unitPrice.toFixed(2)}</td>
      <td>$${importe.toFixed(2)}</td>
      <td><button class="btn btn-sm btn-danger">X</button></td>
    `;

    tr.querySelector('button').addEventListener('click', () => {
      const index = Array.from(itemsTableBody.children).indexOf(tr);
      items.splice(index, 1);
      tr.remove();
      updateTotal();
    });

    itemsTableBody.appendChild(tr);
    updateTotal();

    quantityInput.value = '';
    priceInput.value = '';
  });

  function updateTotal() {
    let total = 0;
    itemsTableBody.querySelectorAll('tr').forEach(tr => {
      const qty = n(tr.children[2].textContent);
      const up = n(tr.children[3].textContent.replace('$', ''));
      total += qty * up;
    });
    itemsTotalCell.textContent = '$' + total.toFixed(2);
  }

  // Finalizar compra
  btnFinalize.addEventListener('click', () => {
    if (items.length === 0) {
      alert('Agregue al menos un producto');
      return;
    }

    const payload = {
      supplier_id: parseInt(supplierId),
      date: dateInput.value,
      consider_vat: parseInt(vatSelect.value),
      // folio/cuenta/banco se añaden abajo al normalizar
      folio: (folioInput && folioInput.value ? folioInput.value.trim() : ''),
      cash_register_id: (accountSelect && accountSelect.value ? parseInt(accountSelect.value, 10) : null),
      bank_operation_number: (bankOpInput && bankOpInput.value ? bankOpInput.value.trim() : ''),
      receipt_path: receiptPath,
      created_by_user_id: user.id,
      received_by_user_id: user.id,
      notes: notesInput.value,
      items
    };

    // Validación: si es Efectivo o Transferencia debe elegir Caja/Cuenta.
    const kind = normalizePaymentMethod(paymentSelect.value);
    if ((kind === 'cash' || kind === 'bank') && !payload.cash_register_id) {
      alert('Seleccione la caja o cuenta de donde saldrá el dinero.');
      return;
    }
    if (kind === 'bank' && !payload.bank_operation_number) {
      alert('Capture el número de operación/transferencia.');
      return;
    }

    // Normalize canonical payment_method and attach to payload (force correct values)
    let canonicalPaymentMethod = 'PENDIENTE';
    if (kind === 'cash') canonicalPaymentMethod = 'EFECTIVO';
    else if (kind === 'bank') canonicalPaymentMethod = 'TRANSFERENCIA';
    else if (kind === 'cuenta') canonicalPaymentMethod = 'CUENTA';

    // Attach normalized payment method and log everything
    payload.payment_method = canonicalPaymentMethod;
    payload.folio = (folioInput && folioInput.value ? folioInput.value.trim() : payload.folio);

    // IMPORTANT: If this is a CUENTA (credit) ensure we DO NOT send any cash_register or bank op
    if (canonicalPaymentMethod === 'CUENTA') {
      payload.cash_register_id = null;
      payload.bank_operation_number = null;
    }


    // If payment is CUENTA (on credit), confirm with the user
    if (canonicalPaymentMethod === 'CUENTA') {
      if (!confirm('Forma de pago: CUENTA. Se registrará una cuenta por pagar. ¿Desea continuar?')) return;
    } else {
      if (!confirm('¿Desea finalizar esta entrada?')) return;
    }

    apiRequest('purchases.php', 'POST', payload).then(res => {
      if (res.success) {
        alert('Compra registrada correctamente');
        window.location.href = 'suppliers.html';
      } else {
        alert(res.error || 'Error al registrar');
      }
    });
  });
});
