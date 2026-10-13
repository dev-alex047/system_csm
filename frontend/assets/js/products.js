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
  // La navegación superior la construye csm-portal.js; no cargar el menú antiguo.

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
  let currentView = localStorage.getItem('productsViewV2') || 'boxes';
  let visibleLimit = 24;
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
  const isAdmin = String(user?.role || '').toUpperCase() === 'ADMIN';
  const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));

  function safeImageSrc(path) {
    const p = String(path || '').trim();
    if (!p || /^(javascript:|data:|\/\/)/i.test(p)) return '';
    if (/^https?:\/\//i.test(p) || p.startsWith('../backend/')) return p;
    return '../backend/' + p.replace(/^\/+/, '');
  }

  function getBoxFilteredProducts() {
    const term = (boxSearchTerm || '').trim().toLowerCase();
    const category = classificationFilter?.value || '';
    return productsCache.filter(p => {
      if (category && String(p.classification || '').trim() !== category) return false;
      if (!term) return true;
      return `${p.name || ''} ${p.code || ''} ${p.barcode || ''} ${p.description || ''}`.toLowerCase().includes(term);
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
    const categories = [...new Set((products || []).map(p => String(p.classification || '').trim()).filter(Boolean))].sort();
    const selected = classificationFilter?.value || '';
    boxCategories.replaceChildren();
    [['Todas', ''], ...categories.map(x => [x, x])].forEach(([name, value]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = `csm-inventory-chip ${selected === value ? 'active' : ''}`;
      btn.textContent = name;
      btn.setAttribute('aria-pressed', String(selected === value));
      btn.addEventListener('click', () => {
        if (classificationFilter) classificationFilter.value = value;
        visibleLimit = 24;
        applyBoxFilters();
      });
      boxCategories.appendChild(btn);
    });
  }

  function openProductFicha(prod) {
    apiRequest(`product_card.php?id=${encodeURIComponent(prod.id)}`).then(res => {
      if (res.success && res.file) window.open(`../backend/${res.file}`, '_blank', 'noopener');
      else alert(res.error || 'No se pudo generar la ficha');
    }).catch(error => alert(error.message));
  }

  async function deleteProduct(prod) {
  const result = await Swal.fire({
    icon: 'warning',
    title: '¿Eliminar producto?',
    text: `Se eliminará "${prod.name || 'este producto'}".`,
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc3545',
    reverseButtons: true
  });

  if (!result.isConfirmed) return;

  try {
    await apiRequest(`products.php?id=${encodeURIComponent(prod.id)}`, 'DELETE');
    await Swal.fire({ icon: 'success', title: 'Producto eliminado', timer: 1500, showConfirmButton: false });
    loadProducts();
  } catch (error) {
    await Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: error.message });
  }
}

  function renderBoxGrid(products) {
    if (!boxGrid) return;
    boxGrid.replaceChildren();
    const showMore = document.getElementById('btnShowMore');
    const fragment = document.createDocumentFragment();
    products.slice(0, visibleLimit).forEach(prod => {
      const stock = Number.parseInt(prod.stock, 10) || 0;
      const minStock = Number.parseInt(prod.min_stock, 10) || 0;
      const maxStock = Number.parseInt(prod.max_stock, 10) || 0;
      const lowStock = minStock > 0 && stock <= minStock;
      const highStock = !lowStock && maxStock > 0 && stock >= maxStock;
      const statusText = lowStock ? 'Stock bajo' : (highStock ? 'Stock alto' : 'Disponible');
      const statusClass = lowStock ? 'low' : (highStock ? 'high' : 'normal');
      const image = safeImageSrc(prod.image_path);
      const card = document.createElement('div');
      card.className = 'col';
      card.innerHTML = `<article class="csm-inventory-product h-100">
        <div class="csm-inventory-product-media">
          ${image ? `<img loading="lazy" src="${escapeHTML(image)}" alt="${escapeHTML(prod.name)}">` : '<div class="csm-inventory-product-placeholder"><i class="bi bi-box-seam" aria-hidden="true"></i></div>'}
          ${isAdmin ? `<div class="dropdown csm-inventory-card-actions"><button class="csm-inventory-more" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de ${escapeHTML(prod.name)}" title="Acciones"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-action="edit"><i class="bi bi-pencil"></i> Editar</button></li><li><button class="dropdown-item" type="button" data-action="ficha"><i class="bi bi-file-earmark-text"></i> Ver ficha</button></li><li><button class="dropdown-item text-danger" type="button" data-action="delete"><i class="bi bi-trash"></i> Eliminar</button></li></ul></div>` : ''}
        </div>
        <div class="csm-inventory-product-content">
          <span class="csm-inventory-product-category">${escapeHTML(prod.classification || 'Sin categoría')}</span>
          <h3 class="csm-inventory-product-name" title="${escapeHTML(prod.name)}">${escapeHTML(prod.name || 'Sin nombre')}</h3>
          <div class="csm-inventory-product-code">Código: ${escapeHTML(prod.code || prod.barcode || 'Sin código')}</div>
          <div class="csm-inventory-product-bottom"><div><small>Precio de venta</small><strong>$${formatMoney(prod.public_price)}</strong></div><span class="csm-inventory-stock ${statusClass}" title="${statusText}"><i class="bi bi-box" aria-hidden="true"></i> ${stock}</span></div>
          <span class="csm-inventory-stock-caption">${statusText}</span>
        </div>
      </article>`;
      card.addEventListener('click', event => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (action === 'edit' && isAdmin) openEditModal(prod);
        if (action === 'ficha' && isAdmin) openProductFicha(prod);
        if (action === 'delete' && isAdmin) deleteProduct(prod);
      });
      fragment.appendChild(card);
    });
    boxGrid.appendChild(fragment);
    if (!products.length) boxGrid.innerHTML = '<div class="col-12"><div class="csm-inventory-empty"><i class="bi bi-search"></i><h3>Sin resultados</h3><p>Prueba con otro nombre, código o categoría.</p></div></div>';
    if (showMore) {
      showMore.classList.toggle('d-none', products.length <= visibleLimit);
      showMore.textContent = `Mostrar más productos (${Math.min(visibleLimit, products.length)} de ${products.length})`;
    }
  }

  function applyBoxFilters() {
    const filtered = getBoxFilteredProducts();
    renderCategories(productsCache);
    renderBoxGrid(filtered);
    if (currentView === 'table') renderTable(filtered);
    updateCostSummary(filtered, classificationFilter?.value || 'Todas');
    const count = document.getElementById('inventoryResultCount');
    if (count) count.textContent = `${filtered.length} producto${filtered.length === 1 ? '' : 's'} encontrado${filtered.length === 1 ? '' : 's'}`;
    const badge = document.getElementById('inventoryActiveFilter');
    if (badge) { badge.hidden = !(classificationFilter?.value || boxSearchTerm); badge.textContent = 'Filtros activos'; }
  }

  function setView(view, save = true) {
    currentView = view === 'table' ? 'table' : 'boxes';
    if (save) localStorage.setItem('productsViewV2', currentView);
    tableView?.classList.toggle('d-none', currentView !== 'table');
    boxView?.classList.toggle('d-none', currentView !== 'boxes');
    btnViewTable?.classList.toggle('active', currentView === 'table');
    btnViewBoxes?.classList.toggle('active', currentView === 'boxes');
    btnViewTable?.setAttribute('aria-pressed', String(currentView === 'table'));
    btnViewBoxes?.setAttribute('aria-pressed', String(currentView === 'boxes'));
    applyBoxFilters();
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
    addOption('Todas las categorías', '');
    categories.forEach(c => addOption(c, c));
    if (current) {
      const exists = Array.from(classificationFilter.options).some(o => o.value === current);
      if (exists) classificationFilter.value = current;
    }
  }

  function renderTable(filtered) {
    if (!tableBody) return;
    tableBody.replaceChildren();
    (filtered || []).forEach(prod => {
      const tr = document.createElement('tr');
      // ===== Normalización =====
      const stock = Number.parseInt(prod.stock, 10) || 0;
      const minStock = Number.parseInt(prod.min_stock, 10) || 0;
      const maxStock = Number.parseInt(prod.max_stock, 10) || 0;
      // ===== Render de fila =====
      tr.innerHTML = `
        <td>${escapeHTML(prod.id)}</td>
        <td>${escapeHTML(prod.last_purchase)}</td>
        <td>${escapeHTML(prod.code)}</td>
        <td>${escapeHTML(prod.name)}</td>
        <td>${escapeHTML(prod.description)}</td>
        <td>${escapeHTML(prod.unit)}</td>
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
          const src = safeImageSrc(p);
          return src ? `<img src="${escapeHTML(src)}" alt="Imagen" class="product-thumbnail" loading="lazy">` : '';
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
      if (isAdmin) {
        const td = tr.lastElementChild;
        td.classList.add('actions-cell');
        td.innerHTML = `<div class="dropdown"><button type="button" class="csm-inventory-more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de ${escapeHTML(prod.name)}"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-action="edit">Editar</button></li><li><button class="dropdown-item" type="button" data-action="ficha">Ver ficha</button></li><li><button class="dropdown-item text-danger" type="button" data-action="delete">Eliminar</button></li></ul></div>`;
        td.addEventListener('click', e => {
          const action = e.target.closest('[data-action]')?.dataset.action;
          if (action === 'edit') openEditModal(prod);
          if (action === 'ficha') openProductFicha(prod);
          if (action === 'delete') deleteProduct(prod);
        });
      }
      tableBody.appendChild(tr);
    });
  }

  function loadProducts() {
    apiRequest('products.php').then(products => {
      productsCache = Array.isArray(products) ? products : [];
      populateClassificationFilter(productsCache);
      applyBoxFilters();
    }).catch(error => {
      console.error('No se pudo cargar el inventario:', error);
      const count = document.getElementById('inventoryResultCount');
      if (count) count.textContent = 'No se pudieron cargar los productos. Intenta recargar la página.';
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
async function buscarProductoPorClave(titulo) {
  const result = await Swal.fire({
    title: titulo,
    input: 'text',
    inputLabel: 'Clave interna o código de barras',
    inputPlaceholder: 'Escribe o escanea el código',
    showCancelButton: true,
    confirmButtonText: 'Buscar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#0d6a47',
    inputValidator: value => !String(value || '').trim() ? 'Ingresa una clave o código de barras' : undefined
  });

  if (!result.isConfirmed) return null;

  try {
    const list = await apiRequest('products.php');
    const search = result.value.trim().toLowerCase();
    const producto = Array.isArray(list) ? list.find(p =>
      String(p.code || '').trim().toLowerCase() === search ||
      String(p.barcode || '').trim().toLowerCase() === search
    ) : null;

    if (!producto) await Swal.fire({ icon: 'warning', title: 'Producto no encontrado', text: 'Verifica el código e inténtalo nuevamente.', confirmButtonColor: '#0d6a47' });
    return producto || null;
  } catch (error) {
    await Swal.fire({ icon: 'error', title: 'Error al buscar', text: error.message, confirmButtonColor: '#0d6a47' });
    return null;
  }
}

if (btnSearch) {
  btnSearch.addEventListener('click', async () => {
    const producto = await buscarProductoPorClave('Buscar producto');
    if (producto) openEditModal(producto);
  });
}

if (btnQuickPrice) {
  btnQuickPrice.addEventListener('click', async () => {
    const producto = await buscarProductoPorClave('Actualizar precio');
    if (!producto) return;

    const result = await Swal.fire({
      title: 'Actualizar precios',
      html: `
        <div class="text-start">
          <p class="text-body-secondary small">Ingresa únicamente los precios que deseas modificar.</p>
          <label class="form-label">Precio de compra actual: $${formatMoney(producto.purchase_price)}</label>
          <input id="quickPurchasePrice" class="swal2-input w-100 mx-0 mt-1 mb-3" type="text" inputmode="decimal" placeholder="Nuevo precio de compra">
          <label class="form-label">Precio de venta actual: $${formatMoney(producto.public_price)}</label>
          <input id="quickSalePrice" class="swal2-input w-100 mx-0 mt-1" type="text" inputmode="decimal" placeholder="Nuevo precio de venta">
        </div>`,
      showCancelButton: true,
      confirmButtonText: 'Guardar cambios',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#0d6a47',
      focusConfirm: false,
      preConfirm: () => {
        const compra = document.getElementById('quickPurchasePrice').value.trim();
        const venta = document.getElementById('quickSalePrice').value.trim();
        const payload = {};
        const precioValido = /^\d+(?:[.,]\d{1,2})?$/;

        if (!compra && !venta) {
          Swal.showValidationMessage('Ingresa al menos un precio.');
          return false;
        }
        if ((compra && !precioValido.test(compra)) || (venta && !precioValido.test(venta))) {
          Swal.showValidationMessage('Ingresa precios válidos, con máximo dos decimales.');
          return false;
        }

        if (compra) payload.purchase_price = parseNumber(compra);
        if (venta) payload.public_price = parseNumber(venta);
        return payload;
      }
    });

    if (!result.isConfirmed) return;

    try {
      await apiRequest(`products.php?id=${encodeURIComponent(producto.id)}`, 'PUT', result.value);
      await Swal.fire({ icon: 'success', title: 'Precios actualizados', timer: 1600, showConfirmButton: false });
      loadProducts();
    } catch (error) {
      await Swal.fire({ icon: 'error', title: 'No se pudo actualizar', text: error.message, confirmButtonColor: '#0d6a47' });
    }
  });
}

  // ===== Inicializar =====
  if (!isAdmin) {
    document.getElementById('productCreateMenu')?.remove();
    document.getElementById('productOptionsMenu')?.remove();
    document.getElementById('inventoryFinancialSection')?.remove();
  }
  classificationFilter?.addEventListener('change', () => {
    boxSelectedCategory = classificationFilter.value;
    visibleLimit = 24;
    applyBoxFilters();
  });
  btnViewTable?.addEventListener('click', () => setView('table'));
  btnViewBoxes?.addEventListener('click', () => setView('boxes'));
  boxSearch?.addEventListener('input', () => {
    boxSearchTerm = boxSearch.value || '';
    visibleLimit = 24;
    applyBoxFilters();
  });
  document.getElementById('btnResetFilters')?.addEventListener('click', () => {
    if (boxSearch) boxSearch.value = '';
    if (classificationFilter) classificationFilter.value = '';
    boxSearchTerm = '';
    boxSelectedCategory = '';
    visibleLimit = 24;
    applyBoxFilters();
  });
  document.getElementById('btnShowMore')?.addEventListener('click', () => {
    visibleLimit += 24;
    renderBoxGrid(getBoxFilteredProducts());
  });
  loadSuppliers();
  setView(currentView, false);
  loadProducts();
})();
