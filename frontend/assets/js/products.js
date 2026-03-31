// products.js – Subir/Tomar foto (una sola), carga de proveedores, cálculo de precios, y guardado con path


(function () {
  // ===== Utilidades de API =====
  function safeApiBase() {
    try { if (typeof API_BASE !== 'undefined' && API_BASE) return API_BASE; } catch (e) {}
    return '../backend/api';
  }

  async function apiRequest(endpoint, method = 'GET', body) {
    const opts = { method, credentials: 'include', headers: {} };
    if (body && !(body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    } else if (body instanceof FormData) {
      opts.body = body;
    }
    const res = await fetch(`${safeApiBase()}/${endpoint}`, opts);

    // Intentar leer JSON; si no es JSON, tomar texto crudo para depuración
    let data = {};
    try {
      data = await res.json();
    } catch (e) {
      const txt = await res.text().catch(() => '');
      data = { error: txt || `HTTP ${res.status}` };
    }

    if (!res.ok || data.error) {
      console.error('API request error', endpoint, method, data);
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  }

  // ===== Auth / navbar =====
  const user = (typeof requireAuth === 'function') ? requireAuth() : { role: 'ADMIN' };
  if (typeof populateNav === 'function') populateNav();

  // ===== Elementos base =====
  const tableBody = document.querySelector('#productTable tbody');
  const modalEl = document.getElementById('productModal');
  const modal = new bootstrap.Modal(modalEl);
  const modalTitle = document.getElementById('productModalLabel');
  const form = document.getElementById('productForm');

  const btnAdd = document.getElementById('btnAddProduct');
  const btnImport = document.getElementById('btnImport');
  const importInput = document.getElementById('importFileInput');
  const btnQuickPrice = document.getElementById('btnQuickPrice');
  const btnSearch = document.getElementById('btnSearchProduct');
  const classificationFilter = document.getElementById('classificationFilter');

  const btnViewTable = document.getElementById('btnViewTable');
  const btnViewBoxes = document.getElementById('btnViewBoxes');
  const tableView = document.getElementById('tableView');
  const boxView = document.getElementById('boxView');
  const boxSearch = document.getElementById('boxSearch');
  const boxCategories = document.getElementById('boxCategories');
  const boxGrid = document.getElementById('boxGrid');
  const costSummaryValue = document.getElementById('costSummaryValue');

  let productsCache = [];
  let currentView = localStorage.getItem('productsView') || 'table';
  let boxSelectedCategory = '';
  let boxSearchTerm = '';

  // Imagen UI
  const uploadInput = document.getElementById('fileUploadHidden');
  const btnUploadLabel = document.getElementById('btnUploadLabel');
  const btnCaptureImage = document.getElementById('btnCaptureImage');
  const btnClearImage = document.getElementById('btnClearImage');
  const imgPreview = document.getElementById('productImagePreview');
  const imgPathHidden = document.getElementById('productImagePath');

  // Cámara modal
  const cameraModalEl = document.getElementById('cameraModal');
  const cameraModal = new bootstrap.Modal(cameraModalEl);
  const cameraVideo = document.getElementById('cameraVideo');
  const cameraCanvas = document.getElementById('cameraCanvas');
  const btnTakeSnapshot = document.getElementById('btnTakeSnapshot');
  const btnUseSnapshot = document.getElementById('btnUseSnapshot');

  let cameraStream = null;
  let snapshotBlob = null;

  // ===== Helpers =====
  function parseNumber(str) {
    if (str == null) return 0;
    let s = String(str).trim();
    if (!s) return 0;

    // Eliminar moneda y espacios
    s = s.replace(/[^0-9,\.\-]/g, '');

    // Si hay ambos separadores, determinar cuál es decimal
    const lastDot = s.lastIndexOf('.');
    const lastComma = s.lastIndexOf(',');
    if (lastDot !== -1 && lastComma !== -1) {
      if (lastDot > lastComma) {
        // punto decimal; coma miles
        s = s.replace(/,/g, '');
      } else {
        // coma decimal; punto miles
        s = s.replace(/\./g, '').replace(/,/g, '.');
      }
    } else {
      // solo un tipo de separador
      if (s.indexOf(',') !== -1 && s.indexOf('.') === -1) {
        s = s.replace(/,/g, '.');
      } else {
        s = s.replace(/,/g, '');
      }
    }

    const n = parseFloat(s);
    return Number.isFinite(n) ? n : 0;
  }

  function formatMoney(val) {
    const n = parseNumber(val);
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }

  function numberOrZero(inputEl) {
    return parseFloat((inputEl.value || '0').replace(',', '.')) || 0;
  }

  function getBoxFilteredProducts() {
    const term = (boxSearchTerm || '').trim().toLowerCase();
    return (productsCache || []).filter(p => {
      if (boxSelectedCategory && p.classification !== boxSelectedCategory) return false;
      if (!term) return true;
      const haystack = `${p.name||''} ${p.code||''} ${p.barcode||''} ${p.description||''}`.toLowerCase();
      return haystack.includes(term);
    });
  }

  function updateCostSummary(products, categoryLabel = '') {
    if (!costSummaryValue) return;
    const total = (products || []).reduce((sum, p) => {
      const price = parseNumber(p.purchase_price);
      const stock = parseNumber(p.stock);
      return sum + price * stock;
    }, 0);

    const labelEl = document.getElementById('costSummaryCategory');
    if (labelEl) {
      labelEl.textContent = `Categoría: ${categoryLabel || 'Todas'}`;
    }

    costSummaryValue.textContent = `$${formatMoney(total)}`;

    const countEl = document.getElementById('costSummaryCount');
    if (countEl) {
      const count = (products || []).length;
      countEl.textContent = `Productos registrados: ${count}`;
    }
  }

  function renderCategories(products) {
    if (!boxCategories) return;
    const categories = Array.from(new Set((products || []).map(p => p.classification).filter(Boolean))).sort();
    if (boxSelectedCategory && !categories.includes(boxSelectedCategory)) {
      boxSelectedCategory = '';
    }
    boxCategories.innerHTML = '';

    const createCatButton = (label, value) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'list-group-item list-group-item-action';
      if (boxSelectedCategory === value) btn.classList.add('active');
      btn.textContent = label;
      btn.addEventListener('click', () => {
        boxSelectedCategory = value;
        applyBoxFilters();
      });
      return btn;
    };

    boxCategories.appendChild(createCatButton('Todas', ''));
    categories.forEach(cat => boxCategories.appendChild(createCatButton(cat, cat)));
  }

  function renderBoxGrid(products) {
    if (!boxGrid) return;
    boxGrid.innerHTML = '';

    (products || []).forEach(prod => {
      const stock = Number.parseInt(prod.stock, 10) || 0;
      const minStock = Number.parseInt(prod.min_stock, 10) || 0;
      const maxStock = Number.parseInt(prod.max_stock, 10) || 0;

      const card = document.createElement('div');
      card.className = 'col';

      const statusLabel = minStock > 0 && stock <= minStock
        ? '⚠️ Stock bajo'
        : (maxStock > 0 && stock >= maxStock ? '📦 Stock alto' : '✔️ Stock normal');

      const imgSrc = (() => {
        if (!prod.image_path) return '';
        const p = prod.image_path;
        return (p.startsWith('http') || p.startsWith('../backend')) ? p : '../backend/' + p;
      })();

      card.innerHTML = `
        <div class="card h-100">
          ${imgSrc ? `<img src="${imgSrc}" class="card-img-top" style="height: 210px; object-fit: contain; background: #f8f9fa;" alt="${prod.name || ''}"/>` : ''}
          <div class="card-body">
            <h5 class="card-title mb-1">${prod.name || ''}</h5>
            <p class="card-text mb-1"><strong>Código:</strong> ${prod.code || ''}</p>
            <p class="card-text mb-1"><strong>Código de barras:</strong> ${prod.barcode || ''}</p>
            <p class="card-text mb-1"><strong>Precio público:</strong> $${formatMoney(prod.public_price)}</p>
            <p class="card-text mb-1"><strong>Stock:</strong> ${stock} <small class="text-muted">${statusLabel}</small></p>
          </div>
          <div class="card-footer d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary w-100" data-action="edit">Editar</button>
            ${user?.role?.toUpperCase() === 'ADMIN' ? `<button type="button" class="btn btn-sm btn-secondary w-100" data-action="ficha">Ficha</button>` : ''}
            ${user?.role?.toUpperCase() === 'ADMIN' ? `<button type="button" class="btn btn-sm btn-danger w-100" data-action="delete">Eliminar</button>` : ''}
          </div>
        </div>
      `;

      const footer = card.querySelector('.card-footer');
      if (footer) {
        footer.addEventListener('click', (event) => {
          const action = event.target.getAttribute('data-action');
          if (!action) return;
          if (action === 'edit') return openEditModal(prod);
          if (action === 'ficha') {
            apiRequest(`product_card.php?id=${prod.id}`).then(res => {
              if (res.success && res.file) {
                window.open(`../backend/${res.file}`, '_blank');
              } else {
                alert(res.error || 'No se pudo generar la ficha');
              }
            });
          }
          if (action === 'delete') {
            if (!confirm('¿Eliminar producto?')) return;
            apiRequest(`products.php?id=${prod.id}`, 'DELETE').then(loadProducts);
          }
        });
      }

      boxGrid.appendChild(card);
    });
  }

  function applyBoxFilters() {
    const filtered = getBoxFilteredProducts();
    renderCategories(productsCache);
    renderBoxGrid(filtered);
    updateCostSummary(filtered, boxSelectedCategory || 'Todas');
  }

  function setView(view, save = true) {
    currentView = view;
    if (save) localStorage.setItem('productsView', view);

    if (tableView) tableView.classList.toggle('d-none', view !== 'table');
    if (boxView) boxView.classList.toggle('d-none', view !== 'boxes');

    if (btnViewTable) btnViewTable.classList.toggle('active', view === 'table');
    if (btnViewBoxes) btnViewBoxes.classList.toggle('active', view === 'boxes');

    if (view === 'boxes') {
      // En vista cajas, siempre cargar todos los productos para que el listado de categorías sea completo
      loadProducts(true);
      applyBoxFilters();
    }
  }

  function disableUpload(disabled) {
    uploadInput.disabled = disabled;
    if (btnUploadLabel) btnUploadLabel.classList.toggle('cursor-disabled', disabled);
  }

  function disableCapture(disabled) {
    if (btnCaptureImage) btnCaptureImage.disabled = disabled;
  }

  function showPreview(path) {
    if (path) {
      imgPreview.src = path.startsWith('http') ? path : `../backend/${path}`;
      imgPreview.classList.remove('d-none');
      btnClearImage.classList.remove('d-none');
    } else {
      imgPreview.src = '';
      imgPreview.classList.add('d-none');
      btnClearImage.classList.add('d-none');
    }
  }

  // Subida (soporta File o Blob de cámara, con nombre/extensión)
  async function uploadImageFile(fileOrBlob) {
    const fd = new FormData();

    if (fileOrBlob instanceof Blob && !(fileOrBlob instanceof File)) {
      const ext = (fileOrBlob.type && fileOrBlob.type.includes('png')) ? 'png' : 'jpg';
      fd.append('image', fileOrBlob, `camera.${ext}`);
      fd.append('file',  fileOrBlob, `camera.${ext}`); // compatibilidad
    } else {
      fd.append('image', fileOrBlob, fileOrBlob.name || 'upload.jpg');
      fd.append('file',  fileOrBlob, fileOrBlob.name || 'upload.jpg');
    }

    const res = await fetch(`${safeApiBase()}/upload_image.php`, {
      method: 'POST',
      body: fd,
      credentials: 'include'
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.error || !json.path) {
      throw new Error(json.error || 'No se pudo subir la imagen');
    }
    return json.path;
  }

  // ===== Proveedores =====
  function loadSuppliers() {
    fetch(`${safeApiBase()}/suppliers.php`, { credentials: 'include' })
      .then(r => r.json())
      .then(list => {
        const select = document.getElementById('productSupplier');
        if (!select) return;
        select.innerHTML = '<option value="">Seleccione proveedor</option>';
        (list || []).forEach(sp => {
          const opt = document.createElement('option');
          opt.value = sp.id;
          opt.textContent = sp.name;
          select.appendChild(opt);
        });
      })
      .catch(() => {});
  }

function populateClassificationFilter(products) {
    if (!classificationFilter) return;

    const current = classificationFilter.value;
    const categories = Array.from(new Set((products || []).map(p => (p.classification || '').trim()).filter(Boolean))).sort();

    classificationFilter.innerHTML = '';
    const addOption = (label, value) => {
      const opt = document.createElement('option');
      opt.value = value || '';
      opt.textContent = label;
      classificationFilter.appendChild(opt);
    };

    addOption('Todas', '');
    categories.forEach(c => addOption(c, c));

    if (current) {
      const exists = Array.from(classificationFilter.options).some(o => o.value === current);
      if (exists) classificationFilter.value = current;
    }
  }

  function loadProducts(forceAll = false) {
    const clsVal = classificationFilter ? classificationFilter.value : '';
    const endpoint = (!forceAll && clsVal)
      ? `products.php?classification=${encodeURIComponent(clsVal)}`
      : 'products.php';

    apiRequest(endpoint).then(products => {
      productsCache = products || [];
      populateClassificationFilter(productsCache);
      tableBody.innerHTML = '';

    (productsCache || []).forEach(prod => {
      const tr = document.createElement('tr');

      // ===== Normalización =====
      const stock = Number.parseInt(prod.stock, 10) || 0;
      const minStock = Number.parseInt(prod.min_stock, 10) || 0;
      const maxStock = Number.parseInt(prod.max_stock, 10) || 0;

      // ===== Render de fila =====
      tr.innerHTML = `
        <td>${prod.id}</td>
        <td>${prod.last_purchase || ''}</td>
        <td>${prod.code || ''}</td>
        <td>${prod.name || ''}</td>
        <td>${prod.description || ''}</td>
        <td>${prod.unit || ''}</td>
        <td>$${formatMoney(prod.purchase_price)}</td>
        <td>$${formatMoney(prod.min_price)}</td>
        <td>${prod.profit_margin != null
          ? (parseFloat(prod.profit_margin) * 100).toFixed(0) + '%'
          : ''}</td>
        <td>$${formatMoney(prod.public_price)}</td>
        <td>${prod.competitor_price != null
          ? '$' + formatMoney(prod.competitor_price)
          : ''}</td>
        <td>${(() => {
          if (!prod.image_path) return '';
          const p = prod.image_path;
          const src = (p.startsWith('http') || p.startsWith('../backend'))
            ? p
            : '../backend/' + p;
          return `<img src="${src}" alt="Imagen" class="product-thumbnail" />`;
        })()}</td>

        <!-- STOCK CON ICONO -->
        <td class="stock-cell"></td>

        <td></td>
      `;

      // ===== ICONOS + TEXTO EN STOCK (100% visible) =====
      const stockTd = tr.querySelector('.stock-cell');

      if (minStock > 0 && stock <= minStock) {
        stockTd.innerHTML = `⚠️ <strong>${stock}</strong><br><small>Stock bajo</small>`;
      } else if (maxStock > 0 && stock >= maxStock) {
        stockTd.innerHTML = `📦 <strong>${stock}</strong><br><small>Stock alto</small>`;
      } else {
        stockTd.innerHTML = `✔️ <strong>${stock}</strong><br><small>Stock normal</small>`;
      }

      // ===== Acciones ADMIN =====
      if (user?.role?.toUpperCase() === 'ADMIN') {
        const td = tr.lastElementChild;
        td.classList.add('actions-cell');

        const fichaBtn = document.createElement('button');
        fichaBtn.className = 'btn btn-sm btn-secondary me-1';
        fichaBtn.textContent = 'Ficha';
        fichaBtn.onclick = () => {
          apiRequest(`product_card.php?id=${prod.id}`).then(res => {
            if (res.success && res.file) {
              window.open(`../backend/${res.file}`, '_blank');
            } else {
              alert(res.error || 'No se pudo generar la ficha');
            }
          });
        };

        const editBtn = document.createElement('button');
        editBtn.className = 'btn btn-sm btn-primary me-1';
        editBtn.textContent = 'Editar';
        editBtn.onclick = () => openEditModal(prod);

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-sm btn-danger';
        delBtn.textContent = 'Eliminar';
        delBtn.onclick = () => {
          if (!confirm('¿Eliminar producto?')) return;
          apiRequest(`products.php?id=${prod.id}`, 'DELETE').then(loadProducts);
        };

        td.append(fichaBtn, editBtn, delBtn);
      }

      tableBody.appendChild(tr);
    });

    // Para que el resumen (costo total) siempre refleje filtros aplicados
    if (currentView === 'boxes') {
      applyBoxFilters();
    } else {
      updateCostSummary(productsCache, 'Todas');
    }
  });
}


  // ===== Nuevo producto =====
  if (btnAdd) {
    btnAdd.addEventListener('click', () => {
      modalTitle.textContent = 'Nuevo producto';
      form.reset();
      document.getElementById('productId').value = '';
      document.getElementById('productClassification').value = 'FERRETERIA';

      imgPathHidden.value = '';
      snapshotBlob = null;
      showPreview('');

      disableUpload(false);
      disableCapture(false);

      loadSuppliers();
      modal.show();
    });
  }

  // ===== Editar =====
  function openEditModal(prod) {
    modalTitle.textContent = 'Editar producto';
    form.reset();

    document.getElementById('productId').value = prod.id;
    document.getElementById('productCode').value = prod.code || '';
    document.getElementById('productBarcode').value = prod.barcode || '';
    document.getElementById('productName').value = prod.name || '';
    document.getElementById('productDescription').value = prod.description || '';
    document.getElementById('productUnit').value = prod.unit || '';
    document.getElementById('lastPurchase').value = prod.last_purchase || '';

    document.getElementById('purchasePrice').value = formatMoney(prod.purchase_price);
    document.getElementById('minPrice').value = formatMoney(prod.min_price);

    document.getElementById('profitMargin').value =
      prod.profit_margin != null ? (parseFloat(prod.profitMargin || prod.profit_margin)).toFixed(2) : '';

    document.getElementById('publicPrice').value = formatMoney(prod.public_price);
    document.getElementById('competitorPrice').value = formatMoney(prod.competitor_price);

    document.getElementById('stock').value = prod.stock ?? 0;
    document.getElementById('minStock').value = prod.min_stock ?? 0;
    document.getElementById('maxStock').value = prod.max_stock ?? 0;

    document.getElementById('productClassification').value = prod.classification || 'FERRETERIA';

    loadSuppliers();
    setTimeout(() => {
      const supplier = document.getElementById('productSupplier');
      supplier.value = prod.supplier_id || '';
    }, 100);

    imgPathHidden.value = prod.image_path || '';
    showPreview(prod.image_path || '');
    disableUpload(!!imgPathHidden.value);
    disableCapture(!!imgPathHidden.value);
    snapshotBlob = null;

    modal.show();
  }

  // ===== Subir archivo =====
  if (uploadInput) {
    uploadInput.addEventListener('change', async () => {
      const file = uploadInput.files[0];
      if (!file) return;
      try {
        const path = await uploadImageFile(file);
        imgPathHidden.value = path;
        showPreview(path);
        disableCapture(true);
        btnClearImage.classList.remove('d-none');
      } catch (e) {
        alert(e.message);
        uploadInput.value = '';
      }
    });
  }

  // ===== Cámara: CORRECCIÓN DEFINITIVA DEL DUPLICADO =====
  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(t => t.stop());
      cameraStream = null;
    }
    if (cameraVideo) {
      try { cameraVideo.pause(); } catch {}
      cameraVideo.srcObject = null;
    }
  }

  async function startCamera() {
    stopCamera();

    snapshotBlob = null;
    if (btnUseSnapshot) btnUseSnapshot.disabled = true;
    if (btnTakeSnapshot) btnTakeSnapshot.textContent = 'Capturar';

    // mostrar SOLO video
    cameraCanvas.classList.add('d-none');
    cameraCanvas.style.display = 'none';
    cameraVideo.classList.remove('d-none');
    cameraVideo.style.display = 'block';
    cameraVideo.style.visibility = 'visible';

    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' },
      audio: false
    });

    cameraVideo.srcObject = cameraStream;
    await cameraVideo.play().catch(() => {});
  }

  // al cerrar modal, siempre apaga cámara y resetea UI
  if (cameraModalEl) {
    cameraModalEl.addEventListener('hidden.bs.modal', () => {
      stopCamera();
      snapshotBlob = null;
      if (btnUseSnapshot) btnUseSnapshot.disabled = true;
      if (btnTakeSnapshot) btnTakeSnapshot.textContent = 'Capturar';

      cameraCanvas.classList.add('d-none');
      cameraCanvas.style.display = 'none';

      cameraVideo.classList.remove('d-none');
      cameraVideo.style.display = 'block';
      cameraVideo.style.visibility = 'visible';
    });
  }

  if (btnCaptureImage) {
    btnCaptureImage.addEventListener('click', async () => {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('La cámara no está disponible en este equipo/navegador.');
        return;
      }
      try {
        await startCamera();
        cameraModal.show();
      } catch {
        alert('No se pudo acceder a la cámara. Revisa permisos.');
      }
    });
  }

  //  ESTE ES EL BLOQUE CLAVE: oculta + detiene cámara ANTES de toBlob
  if (btnTakeSnapshot) {
    btnTakeSnapshot.addEventListener('click', async () => {
      const videoVisible = cameraVideo.style.display !== 'none' && !cameraVideo.classList.contains('d-none');

      // ===== CAPTURAR =====
      if (videoVisible) {
        if (!cameraVideo.videoWidth) return;

        // pintar frame
        cameraCanvas.width = cameraVideo.videoWidth;
        cameraCanvas.height = cameraVideo.videoHeight;
        const ctx = cameraCanvas.getContext('2d');
        ctx.drawImage(cameraVideo, 0, 0);

        //  1) UI INMEDIATA (evita duplicado)
        cameraVideo.classList.add('d-none');
        cameraVideo.style.display = 'none';
        cameraVideo.style.visibility = 'hidden';

        cameraCanvas.classList.remove('d-none');
        cameraCanvas.style.display = 'block';
        cameraCanvas.style.visibility = 'visible';

        //  2) APAGAR STREAM INMEDIATO (evita que siga pintando detrás)
        stopCamera();

        //  3) Generar blob (ya sin afectar UI)
        snapshotBlob = null;
        btnUseSnapshot.disabled = true;
        btnTakeSnapshot.textContent = 'Tomar nueva foto';

        cameraCanvas.toBlob((b) => {
          snapshotBlob = b;
          btnUseSnapshot.disabled = !snapshotBlob;
        }, 'image/jpeg', 0.9);

        return;
      }

      // ===== TOMAR NUEVA FOTO =====
      try {
        snapshotBlob = null;
        btnUseSnapshot.disabled = true;
        btnTakeSnapshot.textContent = 'Capturar';

        // ocultar canvas, mostrar video
        cameraCanvas.classList.add('d-none');
        cameraCanvas.style.display = 'none';
        cameraCanvas.style.visibility = 'hidden';

        cameraVideo.classList.remove('d-none');
        cameraVideo.style.display = 'block';
        cameraVideo.style.visibility = 'visible';

        await startCamera();
      } catch {
        alert('No se pudo reactivar la cámara.');
      }
    });
  }

  if (btnUseSnapshot) {
    btnUseSnapshot.addEventListener('click', async () => {
      if (!snapshotBlob) return;
      try {
        const path = await uploadImageFile(snapshotBlob);
        imgPathHidden.value = path;
        showPreview(path);

        disableUpload(true);
        btnClearImage.classList.remove('d-none');

        cameraModal.hide();
        stopCamera();
      } catch (e) {
        alert(e.message);
      }
    });
  }

  // Quitar imagen
  if (btnClearImage) {
    btnClearImage.addEventListener('click', () => {
      imgPathHidden.value = '';
      showPreview('');
      uploadInput.value = '';
      snapshotBlob = null;
      disableUpload(false);
      disableCapture(false);
    });
  }

  // ===== Cálculo automático =====
  const purchasePrice = document.getElementById('purchasePrice');
  const minPrice = document.getElementById('minPrice');
  const profitMargin = document.getElementById('profitMargin');
  const publicPrice = document.getElementById('publicPrice');
  const competitorPrice = document.getElementById('competitorPrice');

  [purchasePrice, minPrice, profitMargin, publicPrice, competitorPrice].forEach(el => {
    if (!el) return;
    el.addEventListener('blur', () => el.value = formatMoney(el.value));
  });

  function recalcFromMargin() {
    const compra = numberOrZero(purchasePrice);
    const margen = parseFloat((profitMargin.value || '0').replace(',', '.')) || 0;
    if (!compra || !margen) return;
    const pvp = compra * (1 + margen);
    const pmin = compra * (1 + margen * 0.8);
    publicPrice.value = pvp.toFixed(2);
    minPrice.value = pmin.toFixed(2);
  }

  function recalcMarginFromPublic() {
    const compra = numberOrZero(purchasePrice);
    const pvp = numberOrZero(publicPrice);
    if (!compra || !pvp) return;
    const margen = (pvp / compra - 1);
    profitMargin.value = margen.toFixed(2);
    const pmin = compra * (1 + margen * 0.8);
    minPrice.value = pmin.toFixed(2);
  }

  if (purchasePrice) purchasePrice.addEventListener('input', () => { if (profitMargin.value) recalcFromMargin(); });
  if (profitMargin) profitMargin.addEventListener('input', recalcFromMargin);
  if (publicPrice) publicPrice.addEventListener('input', () => { if (!profitMargin.value) recalcMarginFromPublic(); });

  // ===== Guardar producto =====
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('productId').value || null;

    const rawMarginStr = (profitMargin.value || '').replace(',', '.');
    const parsedMargin = rawMarginStr ? parseFloat(rawMarginStr) : null;

    const payload = {
      code: document.getElementById('productCode').value || null,
      barcode: document.getElementById('productBarcode').value || null,
      name: document.getElementById('productName').value,
      description: document.getElementById('productDescription').value || null,
      unit: document.getElementById('productUnit').value || null,
      last_purchase: document.getElementById('lastPurchase').value || null,
      purchase_price: numberOrZero(purchasePrice),
      min_price: numberOrZero(minPrice),
      profit_margin: parsedMargin,
      public_price: numberOrZero(publicPrice),
      competitor_price: numberOrZero(competitorPrice),
      stock: parseInt(document.getElementById('stock').value || '0'),
      min_stock: parseInt(document.getElementById('minStock').value || '0'),
      max_stock: parseInt(document.getElementById('maxStock').value || '0'),
      classification: document.getElementById('productClassification').value,
      supplier_id: (document.getElementById('productSupplier').value || '') ? parseInt(document.getElementById('productSupplier').value) : null,
      image_path: imgPathHidden.value || null
    };

    try {
      if (!confirm('¿Desea guardar este producto?')) return;
      if (id) await apiRequest(`products.php?id=${encodeURIComponent(id)}`, 'PUT', payload);
      else await apiRequest('products.php', 'POST', payload);
      modal.hide();
      loadProducts(currentView === 'boxes');
    } catch (err) {
      alert(err.message || 'Error al guardar');
    }
  });

  // ===== Importar CSV =====
  if (btnImport && importInput) {
    btnImport.addEventListener('click', () => importInput.click());
    importInput.addEventListener('change', () => {
      const file = importInput.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('csv', file);
      fetch(`${safeApiBase()}/import_products.php`, { method: 'POST', body: fd, credentials: 'include' })
        .then(r => r.json()).then(j => {
          if (j.success) { alert('Importación exitosa'); loadProducts(currentView === 'boxes'); }
          else alert(j.error || 'Error al importar');
        })
        .catch(() => alert('Error al importar'));
    });
  }

  // ===== Buscar / Editar rápido =====
  if (btnQuickPrice) {
    btnQuickPrice.addEventListener('click', () => {
      const search = prompt('Clave interna o código de barras:');
      if (!search) return;
      apiRequest('products.php').then(list => {
        const p = (list || []).find(x => (x.code && x.code.toLowerCase() === search.toLowerCase()) || (x.barcode && x.barcode === search));
        if (!p) return alert('No encontrado');
        const newPurchase = prompt(`Precio compra actual $${formatMoney(p.purchase_price)}. Nuevo (vacío si no cambia):`);
        const newSale = prompt(`Precio venta actual $${formatMoney(p.public_price)}. Nuevo (vacío si no cambia):`);
        const payload = {};
        if (newPurchase) payload.purchase_price = parseFloat(newPurchase) || 0;
        if (newSale) payload.public_price = parseFloat(newSale) || 0;
        if (!Object.keys(payload).length) return;
        apiRequest(`products.php?id=${p.id}`, 'PUT', payload).then(() => {
          alert('Actualizado'); loadProducts();
        });
      });
    });
  }

  if (btnSearch) {
    btnSearch.addEventListener('click', () => {
      const search = prompt('Clave interna o código de barras:');
      if (!search) return;
      apiRequest('products.php').then(list => {
        const p = (list || []).find(x => (x.code && x.code.toLowerCase() === search.toLowerCase()) || (x.barcode && x.barcode === search));
        if (!p) return alert('No encontrado');
        openEditModal(p);
      });
    });
  }

  // ===== Inicializar =====
  if (!user || !user.role || user.role.toUpperCase() !== 'ADMIN') {
    if (btnAdd) btnAdd.classList.add('d-none');
    if (btnQuickPrice) btnQuickPrice.classList.add('d-none');
    if (btnImport) btnImport.classList.add('d-none');
    if (btnSearch) btnSearch.classList.add('d-none');
  }

  if (classificationFilter) classificationFilter.addEventListener('change', () => {
    loadProducts(currentView === 'boxes');
  });

  if (btnViewTable) btnViewTable.addEventListener('click', () => setView('table'));
  if (btnViewBoxes) btnViewBoxes.addEventListener('click', () => setView('boxes'));
  if (boxSearch) boxSearch.addEventListener('input', () => {
    boxSearchTerm = boxSearch.value || '';
    if (currentView === 'boxes') applyBoxFilters();
  });

  loadSuppliers();
  if (currentView === 'boxes') {
    setView('boxes', false); // carga todos los productos para la vista de cajas
  } else {
    setView('table', false);
    loadProducts(false);
  }
})();
