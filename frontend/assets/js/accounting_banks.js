document.addEventListener('DOMContentLoaded', () => {
  requireAuth();
  populateNav();

  const tbody = document.querySelector('#accountsTable tbody');
  const btnNew = document.getElementById('btnNewAccount');
  const btnReload = document.getElementById('btnReloadAccounts');
  const selCategory = document.getElementById('accountsCategory');
  const selActive = document.getElementById('accountsActive');

  const modalEl = document.getElementById('accModal');
  const modal = new bootstrap.Modal(modalEl);

  const inpId = document.getElementById('accId');
  const inpName = document.getElementById('accName');
  const inpInitial = document.getElementById('accInitial');
  const selCat = document.getElementById('accCategory');
  const chkActive = document.getElementById('accActive');
  const btnSave = document.getElementById('btnSaveAccount');

  const fmtMoney = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const escapeHtml = (s) => String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');



  function render(list) {
    tbody.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Sin cuentas.</td></tr>';
      return;
    }
    list.forEach(a => {
      const tr = document.createElement('tr');
      const kind = String(a.kind || a.type || 'CASH').toLowerCase();
      const kindLabel = kind === 'bank' ? 'BANCO' : 'EFECTIVO';
      const isActive = Number(a.is_active) === 1;
      const current = Number(a.current_balance || a.balance || 0);
      const initial = Number(a.initial_balance || a.saldo_inicial || 0);
      
      tr.innerHTML = `
        <td>${a.id}</td>
        <td>${escapeHtml(a.name)}</td>
        <td><span class="badge ${kind === 'bank' ? 'bg-info' : 'bg-warning text-dark'}">${kindLabel}</span></td>
        <td class="text-end">$ ${fmtMoney(initial)}</td>
        <td>${isActive ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>'}</td>
        <td class="text-end">$ ${fmtMoney(current)}</td>
        <td>
          <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-primary btn-edit" data-id="${a.id}">Editar</button>
            <button class="btn btn-outline-${isActive ? 'warning' : 'success'} btn-toggle" data-id="${a.id}">
              ${isActive ? 'Desactivar' : 'Activar'}
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.btn-edit').forEach(b => b.addEventListener('click', () => onEdit(Number(b.dataset.id))));
    tbody.querySelectorAll('.btn-toggle').forEach(b => b.addEventListener('click', () => onToggle(Number(b.dataset.id))));
  }

  async function load() {
    try {
      const qs = new URLSearchParams();
      const cat = selCategory?.value;
      const act = selActive?.value;
      if (cat) qs.set('kind', cat.toLowerCase() === 'banco' ? 'bank' : 'cash');
      if (act !== undefined && act !== '') qs.set('is_active', act);
      
      const res = await apiRequest(`cash_registers.php?${qs.toString()}`);
      // Handle response: array directo o {ok:true, data:[...]}
      const list = Array.isArray(res) ? res : (res?.data || []);
      render(list);
    } catch (e) {
      console.error(e);
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${escapeHtml(e.error || e.message || 'Error desconocido')}</td></tr>`;
    }
  }

  function openNew() {
    inpId.value = '';
    inpName.value = '';
    inpInitial.value = '0';
    selCat.value = 'CAJA';
    chkActive.checked = true;
    document.getElementById('accModalTitle').textContent = 'Nueva cuenta';
    modal.show();
  }

  async function onEdit(id) {
    try {
      const res = await apiRequest('cash_registers.php');
      // Handle response: array directo o {ok:true, data:[...]}
      const list = Array.isArray(res) ? res : (res?.data || []);
      const item = list.find(x => Number(x.id) === Number(id));
      if (!item) return alert('No se encontró la cuenta.');
      inpId.value = item.id;
      inpName.value = item.name || '';
      inpInitial.value = Number(item.initial_balance || item.saldo_inicial || 0);
      const kind = String(item.kind || item.type || 'CASH').toLowerCase();
      selCat.value = kind === 'bank' ? 'BANCO' : 'CAJA';
      chkActive.checked = Number(item.is_active) === 1;
      document.getElementById('accModalTitle').textContent = 'Editar cuenta';
      modal.show();
    } catch (e) {
      alert(e.error || e.message);
    }
  }

  async function onToggle(id) {
    if (!confirm('¿Cambiar estado de la cuenta?')) return;
    try {
      await apiRequest('cash_registers.php', 'POST', { action: 'toggle', id });
      await load();
    } catch (e) {
      alert(e.error || e.message);
    }
  }

  async function onSave() {
    const name = inpName.value.trim();
    if (!name) return alert('Nombre requerido.');
    try {
      btnSave.disabled = true;
      const payload = {
        action: inpId.value ? 'update' : 'create',
        id: inpId.value ? Number(inpId.value) : undefined,
        name,
        kind: selCat.value === 'BANCO' ? 'bank' : 'cash',
        initial_balance: Number(inpInitial.value || 0),
        is_active: chkActive.checked ? 1 : 0,
      };
      await apiRequest('cash_registers.php', 'POST', payload);
      modal.hide();
      await load();
    } catch (e) {
      alert(e.error || e.message);
    } finally {
      btnSave.disabled = false;
    }
  }

  if (btnNew) btnNew.addEventListener('click', openNew);
  if (btnReload) btnReload.addEventListener('click', load);
  if (btnSave) btnSave.addEventListener('click', onSave);
  if (selCategory) selCategory.addEventListener('change', load);
  if (selActive) selActive.addEventListener('change', load);

  load().catch(err => {
    console.error(err);
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${escapeHtml(err.error || err.message || 'Error desconocido')}</td></tr>`;
  });
});
