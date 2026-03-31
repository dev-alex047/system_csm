document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();

  function safeApiBase() {
    try {
      if (typeof API_BASE !== 'undefined' && API_BASE) return API_BASE;
    } catch (e) {}
    return '../backend/api';
  }

  async function apiRequestAuth(endpoint, method = 'GET', body) {
    const opts = { method, credentials: 'include', headers: {} };
    if (body && !(body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    } else if (body instanceof FormData) {
      opts.body = body;
    }
    const res = await fetch(`${safeApiBase()}/${endpoint}`, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  }

  const tableBody = document.querySelector('#supplierTable tbody');
  const modalEl = document.getElementById('supplierModal');
  const modal = new bootstrap.Modal(modalEl);
  const modalTitle = document.getElementById('supplierModalLabel');
  const form = document.getElementById('supplierForm');
  const btnAdd = document.getElementById('btnAddSupplier');

  const bankSelect = document.getElementById('supplierBank');
  const interbankGroup = document.getElementById('bankInterbankGroup');
  const banamexFields = document.getElementById('banamexFields');

  const categoriesWrap = document.getElementById('supplierCategories');

  const CATEGORY_OPTIONS = [
    { value: 'FERRETERIA', label: 'Ferretería' },
    { value: 'ACEROS', label: 'Aceros' },
    { value: 'MATERIALES PARA CONSTRUCCION', label: 'Materiales para construcción' },
    { value: 'RENTA DE MAQUINARIA', label: 'Renta de maquinaria' },
    { value: 'COMBUSTIBLE', label: 'Combustible' }
  ];

  function renderCategoryCheckboxes() {
    if (!categoriesWrap) return;
    categoriesWrap.innerHTML = '';
    CATEGORY_OPTIONS.forEach(opt => {
      const id = `cat_${opt.value.replace(/\s+/g, '_')}`;
      const div = document.createElement('div');
      div.className = 'form-check';
      div.innerHTML = `
        <input class="form-check-input" type="checkbox" id="${id}" value="${opt.value}">
        <label class="form-check-label" for="${id}">${opt.label}</label>
      `;
      categoriesWrap.appendChild(div);
    });
  }

  function getSelectedCategories() {
    return Array.from(categoriesWrap.querySelectorAll('input[type="checkbox"]:checked'))
      .map(cb => cb.value);
  }

  function setSelectedCategories(values) {
    const set = new Set((values || []).map(v => String(v).trim()).filter(Boolean));
    categoriesWrap.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.checked = set.has(cb.value);
    });
  }

  function handleBankChange() {
    const val = String(bankSelect.value || '').toUpperCase();
    if (!val) {
      banamexFields.style.display = 'none';
      interbankGroup.style.display = 'none';
      return;
    }
    if (val === 'BANAMEX') {
      banamexFields.style.display = '';
      interbankGroup.style.display = 'none';
    } else {
      banamexFields.style.display = 'none';
      interbankGroup.style.display = '';
    }
  }

  if (bankSelect) bankSelect.addEventListener('change', handleBankChange);

  if (!user.role || String(user.role).toUpperCase() !== 'ADMIN') {
    if (btnAdd) btnAdd.classList.add('d-none');
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function categoriesBadges(categoriesStr) {
    const cats = String(categoriesStr || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean);

    if (!cats.length) return '';

    return cats
      .map(c => `<span class="badge text-bg-secondary">${esc(c)}</span>`)
      .join('');
  }

  async function loadSuppliers() {
    try {
      const suppliers = await apiRequestAuth('suppliers.php');
      tableBody.innerHTML = '';

      (suppliers || []).forEach(sup => {
        const tr = document.createElement('tr');

        tr.innerHTML = `
          <td class="text-center">${esc(sup.id)}</td>
          <td>${esc(sup.name || '')}</td>
          <td class="text-center">${esc(sup.rfc || '')}</td>
          <td>${esc(sup.phone_number || '')}</td>
          <td>${esc(sup.mobile_number || '')}</td>
          <td>${esc(sup.email || '')}</td>
          <td>${esc(sup.company || '')}</td>
          <td class="text-center">${esc(sup.bank || '')}</td>
          <td class="categories-cell"><div class="supplier-categories">${categoriesBadges(sup.categories)}</div></td>
          <td class="text-center">${sup.registration_date ? new Date(sup.registration_date).toLocaleDateString() : ''}</td>
          <td class="text-center"></td>
        `;

        if (user.role && String(user.role).toUpperCase() === 'ADMIN') {
          const actionsTd = tr.lastElementChild;
          actionsTd.classList.add('actions-cell');

          const stack = document.createElement('div');
          stack.className = 'actions-stack';

          const editBtn = document.createElement('button');
          editBtn.className = 'btn btn-sm btn-success';
          editBtn.textContent = 'Editar';
          editBtn.addEventListener('click', () => openEditModal(sup));

          const delBtn = document.createElement('button');
          delBtn.className = 'btn btn-sm btn-danger';
          delBtn.textContent = 'Eliminar';
          delBtn.addEventListener('click', () => deleteSupplier(sup.id));

          const regBtn = document.createElement('button');
          regBtn.className = 'btn btn-sm btn-secondary';
          regBtn.textContent = 'Registro de compras';
          regBtn.addEventListener('click', () => {
            window.location.href = `purchases_new.html?supplier_id=${sup.id}`;
          });

          const shareBtn = document.createElement('button');
          shareBtn.className = 'btn btn-sm btn-outline-primary';
          shareBtn.textContent = 'Compartir';
          shareBtn.addEventListener('click', () => {
            apiRequestAuth(`supplier_card.php?id=${sup.id}`)
              .then(res => {
                if (res.success && res.file) window.open(`../backend/${res.file}`, '_blank');
                else alert(res.error || 'No se pudo generar la ficha');
              })
              .catch(e => alert(e.message || 'Error al generar la ficha'));
          });

          stack.append(editBtn, delBtn, regBtn, shareBtn);
          actionsTd.appendChild(stack);
        }

        tableBody.appendChild(tr);
      });
    } catch (e) {
      alert(e.message || 'No se pudieron cargar proveedores');
    }
  }

  if (btnAdd) {
    btnAdd.addEventListener('click', () => {
      modalTitle.textContent = 'Nuevo proveedor';
      form.reset();
      document.getElementById('supplierId').value = '';
      setSelectedCategories([]);
      bankSelect.value = '';
      handleBankChange();
      modal.show();
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = (document.getElementById('supplierId').value || '').trim();
    const bank = (bankSelect.value || '').trim();
    const bankUpper = bank.toUpperCase();
    const cats = getSelectedCategories();

    let interbank_key = (document.getElementById('interbankKey').value || '').trim() || null;
    let branch = (document.getElementById('branch').value || '').trim() || null;
    let account_number = (document.getElementById('accountNumber').value || '').trim() || null;

    if (!bankUpper) {
      interbank_key = null;
      branch = null;
      account_number = null;
    } else if (bankUpper === 'BANAMEX') {
      interbank_key = null;
    } else {
      branch = null;
      account_number = null;
    }

    const payload = {
      name: (document.getElementById('supplierName').value || '').trim(),
      contact_name: (document.getElementById('contactName').value || '').trim() || null,
      phone_number: (document.getElementById('phoneNumber').value || '').trim() || null,
      mobile_number: (document.getElementById('mobileNumber').value || '').trim() || null,
      email: (document.getElementById('email').value || '').trim() || null,
      company: (document.getElementById('company').value || '').trim() || null,
      address: (document.getElementById('address').value || '').trim() || null,
      rfc: (document.getElementById('rfc').value || '').trim() || null,
      legal_name: (document.getElementById('legalName').value || '').trim() || null,
      postal_code: (document.getElementById('postalCode').value || '').trim() || null,
      bank: bank || null,
      interbank_key,
      branch,
      account_number,
      account_holder: (document.getElementById('accountHolder').value || '').trim() || null,
      categories: cats.length ? cats.join(',') : null
    };

    if (!payload.name) {
      alert('El nombre del proveedor es obligatorio.');
      return;
    }

    try {
      if (!confirm('¿Desea guardar este proveedor?')) return;

      if (id) await apiRequestAuth(`suppliers.php?id=${encodeURIComponent(id)}`, 'PUT', payload);
      else await apiRequestAuth('suppliers.php', 'POST', payload);

      modal.hide();
      await loadSuppliers();
    } catch (err) {
      alert(err.message || 'Error al guardar proveedor');
    }
  });

  function openEditModal(sup) {
    modalTitle.textContent = 'Editar proveedor';
    form.reset();

    document.getElementById('supplierId').value = sup.id;
    document.getElementById('supplierName').value = sup.name || '';
    document.getElementById('contactName').value = sup.contact_name || '';
    document.getElementById('phoneNumber').value = sup.phone_number || '';
    document.getElementById('mobileNumber').value = sup.mobile_number || '';
    document.getElementById('email').value = sup.email || '';
    document.getElementById('company').value = sup.company || '';
    document.getElementById('address').value = sup.address || '';
    document.getElementById('rfc').value = sup.rfc || '';
    document.getElementById('legalName').value = sup.legal_name || '';
    document.getElementById('postalCode').value = sup.postal_code || '';

    bankSelect.value = sup.bank || '';
    document.getElementById('interbankKey').value = sup.interbank_key || '';
    document.getElementById('branch').value = sup.branch || '';
    document.getElementById('accountNumber').value = sup.account_number || '';
    document.getElementById('accountHolder').value = sup.account_holder || '';

    const cats = sup.categories ? String(sup.categories).split(',').map(s => s.trim()) : [];
    setSelectedCategories(cats);

    handleBankChange();
    modal.show();
  }

  async function deleteSupplier(id) {
    if (!confirm('¿Eliminar proveedor?')) return;
    try {
      await apiRequestAuth(`suppliers.php?id=${encodeURIComponent(id)}`, 'DELETE');
      await loadSuppliers();
    } catch (e) {
      alert(e.message || 'Error al eliminar proveedor');
    }
  }

  renderCategoryCheckboxes();
  handleBankChange();
  loadSuppliers();
});
