// accounting.js — (mejora UI) tabla con alto fijo + scroll interno SIN mover barras/títulos
// ✅ NO toca lógica de contabilidad, SOLO controla el tamaño/scroll de la tabla.
// ✅ No “baja” la barra/título (como te pasó), porque YA NO reubica nodos del DOM.
// ✅ Mantiene visible lo de arriba (navbar + encabezados/filtros) y solo la tabla hace scroll.

document.addEventListener('DOMContentLoaded', () => {
  requireAuth();
  populateNav();

  // ======= UI FIX: alto fijo de tabla sin mover elementos =======
  (function applyFixedTableLayoutNoReflow() {
    const table = document.getElementById('acctTable');
    if (!table) return;

    // Inyectar estilos una sola vez
    if (!document.getElementById('acct-fixed-layout-style')) {
      const st = document.createElement('style');
      st.id = 'acct-fixed-layout-style';
      st.textContent = `
        html, body { height: 100%; }

        /* Evita doble scroll: el scroll principal queda en el wrap de la tabla */
        body { overflow: hidden; }

        /* Wrap donde estará el scroll interno */
        .acct-table-wrap {
          overflow: auto !important;
          width: 100%;
        }

        /* Header sticky de la tabla */
        .acct-table-wrap thead th {
          position: sticky;
          top: 0;
          z-index: 3;
        }

        /* Que la tabla no deje margen abajo y se vea limpia */
        .acct-table-wrap table { margin-bottom: 0; }
      `;
      document.head.appendChild(st);
    }

    // Asegurar que la tabla esté dentro de un contenedor scrolleable
    // Preferimos .table-responsive si existe, porque Bootstrap ya lo usa.
    const wrap =
      table.closest('.table-responsive') ||
      table.parentElement;

    if (!wrap) return;

    wrap.classList.add('acct-table-wrap');

    function computeWrapHeight() {
      // Navbar (si existe)
      const nav = document.querySelector('.navbar');
      const navH = nav ? nav.getBoundingClientRect().height : 0;

      // Top visible antes de la tabla: usamos la posición del WRAP, no movemos nada.
      const rect = wrap.getBoundingClientRect();
      const topY = rect.top; // ya incluye lo que esté arriba

      // Margen inferior para que no pegue al borde
      const bottomPad = 16;

      // Alto disponible real
      const available = Math.max(180, window.innerHeight - topY - bottomPad);

      // Ajustar alto del área de scroll
      wrap.style.maxHeight = `${Math.floor(available)}px`;
      wrap.style.height = `${Math.floor(available)}px`;
      wrap.style.overflowY = 'auto';
      wrap.style.overflowX = 'auto';
    }

    // Calcular al cargar
    computeWrapHeight();

    // Recalcular al redimensionar / cambiar orientación
    window.addEventListener('resize', computeWrapHeight);

    // Si Bootstrap cambia alturas por colapsos/modales, recalcula un poco después
    setTimeout(computeWrapHeight, 150);
    setTimeout(computeWrapHeight, 400);
  })();
  // ======= FIN UI FIX =======


  // ===== DOM =====
  const dateFrom = document.getElementById('acctStart');
  const dateTo   = document.getElementById('acctEnd');
  const btnFilter = document.getElementById('btnFilterMoves');

  const balancesDate = document.getElementById('acctBalancesDate');

  const saldoName1 = document.getElementById('saldoName1');
  const saldoName2 = document.getElementById('saldoName2');
  const saldoName3 = document.getElementById('saldoName3');
  const saldoName4 = document.getElementById('saldoName4');

  const balValue1 = document.getElementById('saldoCaja1');
  const balValue2 = document.getElementById('saldoCaja2');
  const balValue3 = document.getElementById('saldoCaja3');
  const balValue4 = document.getElementById('saldoCaja4');

  const tbody = document.querySelector('#acctTable tbody');

  // Filtros actor
  const partyInput = document.getElementById('acctPartyInput');
  const placeInput = document.getElementById('acctPlaceInput');
  const partyList = document.getElementById('partyList');
  const userList = document.getElementById('userList');

  // Botones navegación
  const btnAR = document.getElementById('btnAR');
  const btnAP = document.getElementById('btnAP');
  const btnBANK = document.getElementById('btnBANK');
  const btnManageAccounts = document.getElementById('btnManageAccounts');
  const btnAddEntry = document.getElementById('btnAddEntry');
  const btnAddExit = document.getElementById('btnAddExit');

  // Modales
  const settleModalEl = document.getElementById('settleModal');
  const settleModal = settleModalEl ? new bootstrap.Modal(settleModalEl) : null;

  const settleModalTitle = document.getElementById('settleModalTitle');
  const settleInfo = document.getElementById('settleInfo');
  const settleMethod = document.getElementById('settleMethod');
  const settleAccount = document.getElementById('settleAccount');
  const settleRefWrap = document.getElementById('settleRefWrap');
  const settleReference = document.getElementById('settleReference');
  const settleNote = document.getElementById('settleNote');
  const btnSettleConfirm = document.getElementById('btnSettleConfirm');

  const accountsModalEl = document.getElementById('accountsModal');
  const accountsModal = accountsModalEl ? new bootstrap.Modal(accountsModalEl) : null;
  const accountsTbody = document.querySelector('#accountsTable tbody');
  const accountsError = document.getElementById('accountsError');

  const newAcctName = document.getElementById('newAcctName');
  const newAcctKind = document.getElementById('newAcctKind');
  const newAcctBalance = document.getElementById('newAcctBalance');
  const btnCreateAccount = document.getElementById('btnCreateAccount');

  // Modales ENTRADA/SALIDA
  const addEntryModalEl = document.getElementById('addEntryModal');
  const addEntryModal = addEntryModalEl ? new bootstrap.Modal(addEntryModalEl) : null;
  const entryDescription = document.getElementById('entryDescription');
  const entryAmount = document.getElementById('entryAmount');
  const entryCashRegister = document.getElementById('entryCashRegister');
  const entryReference = document.getElementById('entryReference');
  const entryNote = document.getElementById('entryNote');
  const btnConfirmEntry = document.getElementById('btnConfirmEntry');

  const addExitModalEl = document.getElementById('addExitModal');
  const addExitModal = addExitModalEl ? new bootstrap.Modal(addExitModalEl) : null;
  const exitDescription = document.getElementById('exitDescription');
  const exitAmount = document.getElementById('exitAmount');
  const exitCashRegister = document.getElementById('exitCashRegister');
  const exitReference = document.getElementById('exitReference');
  const exitNote = document.getElementById('exitNote');
  const btnConfirmExit = document.getElementById('btnConfirmExit');

  // ===== Const =====
  const API_BASE = '../backend/api/';

  // ===== State =====
  let allRows = [];
  let currentMoveForSettle = null;

  // ===== Utils =====
  const fmtMoney = (n) => Number(n || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  const escapeHtml = (s) => String(s ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  function statusBadge(status) {
    const s = String(status || '').toUpperCase();
    if (s === 'PAGADO') return `<span class="badge bg-success">PAGADO</span>`;
    if (s === 'CANCELADO') return `<span class="badge bg-danger">CANCELADO</span>`;
    if (s === 'PENDIENTE') return `<span class="badge bg-warning text-dark">PENDIENTE</span>`;
    return `<span class="badge bg-secondary">${escapeHtml(status || '—')}</span>`;
  }

  async function fetchJsonLoose(url, opts = {}) {
    const resp = await fetch(url, { credentials: 'include', ...opts });
    const text = await resp.text();

    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Respuesta NO JSON (primeros 400 chars):\n', text.slice(0, 400));
      throw new Error('El servidor devolvió HTML/texto, no JSON.');
    }

    if (Array.isArray(json)) return { ok: true, data: json };
    if (json?.success === true || json?.ok === true) return { ok: true, ...json };
    if (!resp.ok) return { ok: false, ...json };
    return { ok: true, ...json };
  }

  // ===== Filtros actor: extracción de “A nombre de” desde note =====
  function extractPartyFromNote(note) {
    const t = String(note || '').trim();
    if (!t) return '';
    const m1 = t.match(/CLIENTE\s*:\s*([^\n\r,;]+)/i);
    if (m1 && m1[1]) return m1[1].trim();
    const m2 = t.match(/PROVEEDOR\s*:\s*([^\n\r,;]+)/i);
    if (m2 && m2[1]) return m2[1].trim();
    return '';
  }

  function rebuildPartyDatalist(rows) {
    if (!partyList) return;
    const set = new Set();
    (rows || []).forEach(r => {
      const p = extractPartyFromNote(r.note);
      if (p) set.add(p);
    });
    partyList.innerHTML = '';
    [...set].sort((a,b) => a.localeCompare(b)).forEach(name => {
      const opt = document.createElement('option');
      opt.value = name;
      partyList.appendChild(opt);
    });
  }

  async function rebuildUsersDatalist() {
    if (!userList) return;
    try {
      const res = await fetchJsonLoose(`${API_BASE}users.php`);
      const users = Array.isArray(res.data) ? res.data : [];
      userList.innerHTML = '';
      users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = String(u.username || '');
        userList.appendChild(opt);
      });
    } catch (e) {
      console.warn('No se pudo cargar users para autocompletar:', e);
    }
  }

  function applyActorFilters(rows) {
    const party = String(partyInput?.value || '').trim().toLowerCase();
    const user = String(placeInput?.value || '').trim().toLowerCase();

    return (rows || []).filter(r => {
      let ok = true;
      if (party) {
        const p = extractPartyFromNote(r.note).toLowerCase();
        ok = ok && (p.includes(party) || String(r.note || '').toLowerCase().includes(party));
      }
      if (user) {
        ok = ok && String(r.user_name || '').toLowerCase().includes(user);
      }
      return ok;
    });
  }

  // ===== Render =====
  function renderRows(rows) {
    tbody.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="11" class="text-center">Sin movimientos para los filtros seleccionados.</td></tr>`;
      return;
    }

    rows.forEach(r => {
      const date = r.move_date ? new Date(r.move_date).toLocaleString() : '';
      const type = escapeHtml(r.move_type || '');
      const origin = escapeHtml(r.origin || '');
      const account = escapeHtml(r.account_name || '');
      const ref = escapeHtml(r.reference || '');
      const amount = Number(r.amount || 0);
      const amountStr = (amount < 0 ? '-' : '') + '$' + fmtMoney(Math.abs(amount));
      const user = escapeHtml(r.user_name || '');
      const updated = r.updated_at ? new Date(r.updated_at).toLocaleString() : '';

      const status = String(r.status || '').toUpperCase();
      const canSettle = status === 'PENDIENTE';
      const canCancel = status === 'PAGADO' || status === 'PENDIENTE';
      const canRefund = status === 'PAGADO';

      const actionsHtml = `
        <div class="btn-group btn-group-sm" role="group">
          <button class="btn btn-outline-success" data-action="settle" ${canSettle ? '' : 'disabled'}>Saldar</button>
          <button class="btn btn-outline-warning" data-action="refund" ${canRefund ? '' : 'disabled'}>Reemb.</button>
          <button class="btn btn-outline-danger" data-action="cancel" ${canCancel ? '' : 'disabled'}>Cancelar</button>
        </div>
      `;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(date)}</td>
        <td>${type}</td>
        <td>${origin}</td>
        <td>${account}</td>
        <td>${ref}</td>
        <td class="text-end">${escapeHtml(amountStr)}</td>
        <td>${user}</td>
        <td>${statusBadge(r.status)}</td>
        <td>${escapeHtml(updated)}</td>
        <td class="text-center">—</td>
        <td>${actionsHtml}</td>
      `;

      tr.querySelectorAll('button[data-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const action = btn.getAttribute('data-action');
          if (action === 'settle') return openSettleModal(r);
          if (action === 'cancel') return cancelMove(r);
          if (action === 'refund') return refundMove(r);
        });
      });

      tbody.appendChild(tr);
    });
  }

  // ===== Saldos (4 cuadros + prioridad BANAMEX) =====
  function pickBalanceSlots(list) {
    const arr = Array.isArray(list) ? list.slice() : [];

    const first3 = arr.slice(0, 3);
    const hasBanamexInFirst3 = first3.some(x => /banamex/i.test(String(x?.name || '')));

    let fourth = arr[3] || null;

    if (!hasBanamexInFirst3) {
      const banamex = arr.find(x => /banamex/i.test(String(x?.name || '')));
      if (banamex) fourth = banamex;
    }

    const uniq = [];
    const seen = new Set();
    [...first3, fourth].forEach(x => {
      if (!x) return;
      const key = String(x.id ?? x.name ?? '');
      if (seen.has(key)) return;
      seen.add(key);
      uniq.push(x);
    });

    for (const x of arr) {
      if (uniq.length >= 4) break;
      const key = String(x.id ?? x.name ?? '');
      if (seen.has(key)) continue;
      seen.add(key);
      uniq.push(x);
    }

    while (uniq.length < 4) uniq.push(null);
    return uniq.slice(0, 4);
  }

  function setBalancesUI(list) {
    const [a1, a2, a3, a4] = pickBalanceSlots(list);

    if (saldoName1) saldoName1.textContent = a1?.name ? String(a1.name) : 'CUENTA 1';
    if (saldoName2) saldoName2.textContent = a2?.name ? String(a2.name) : 'CUENTA 2';
    if (saldoName3) saldoName3.textContent = a3?.name ? String(a3.name) : 'CUENTA 3';
    if (saldoName4) saldoName4.textContent = a4?.name ? String(a4.name) : 'BANAMEX';

    balValue1.textContent = (a1?.balance === undefined) ? '$ -' : `$ ${fmtMoney(a1.balance)}`;
    balValue2.textContent = (a2?.balance === undefined) ? '$ -' : `$ ${fmtMoney(a2.balance)}`;
    balValue3.textContent = (a3?.balance === undefined) ? '$ -' : `$ ${fmtMoney(a3.balance)}`;
    balValue4.textContent = (a4?.balance === undefined) ? '$ -' : `$ ${fmtMoney(a4.balance)}`;
  }

  // ===== Movimientos =====
  async function loadMovements() {
    try {
      const start = dateFrom?.value || '';
      const end   = dateTo?.value || '';

      const qs = new URLSearchParams();
      if (start) qs.set('start', start);
      if (end) qs.set('end', end);
      qs.set('include_balances', '1');

      const res = await fetchJsonLoose(`${API_BASE}account_moves.php?${qs.toString()}`);
      if (!res.ok) throw new Error(res?.message || 'Error al cargar movimientos');

      const rows = Array.isArray(res.data) ? res.data : [];
      allRows = rows;

      if (dateFrom && !dateFrom.value && res.start) dateFrom.value = res.start;
      if (dateTo && !dateTo.value && res.end) dateTo.value = res.end;

      rebuildPartyDatalist(rows);

      const filtered = applyActorFilters(rows);
      renderRows(filtered);

      balancesDate.textContent = (dateTo?.value)
        ? dateTo.value.split('-').reverse().join('/')
        : new Date().toLocaleDateString();

      setBalancesUI(res.balances || []);

    } catch (err) {
      console.error('Error cargando movimientos:', err);
      tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger">Error al cargar movimientos (revisa consola).</td></tr>`;
    }
  }

  function reRenderFromCurrent() {
    const filtered = applyActorFilters(allRows);
    renderRows(filtered);
  }

  // ===== Saldar / Cancelar / Reembolsar =====
  async function loadAccountsByMethod(method) {
    const kind = (String(method || '').toUpperCase() === 'TRANSFERENCIA') ? 'bank' : 'cash';
    const res = await fetchJsonLoose(`${API_BASE}cash_registers.php?kind=${encodeURIComponent(kind)}&active=1`);
    if (!res.ok) throw new Error(res?.message || 'No se pudieron cargar cuentas');
    return Array.isArray(res.data) ? res.data : [];
  }

  async function loadAllCashRegisters() {
    const res = await fetchJsonLoose(`${API_BASE}cash_registers.php?active=1`);
    if (!res.ok) throw new Error(res?.message || 'No se pudieron cargar cajas');
    return Array.isArray(res.data) ? res.data : [];
  }

  function fillCashRegisterSelect(selectEl, list) {
    if (!selectEl) return;
    selectEl.innerHTML = '<option value="">-- Seleccionar caja/cuenta --</option>';
    list.forEach(a => {
      const opt = document.createElement('option');
      opt.value = String(a.id);
      opt.textContent = `${a.name || ''}`;
      selectEl.appendChild(opt);
    });
  }

  function fillAccountSelect(list) {
    if (!settleAccount) return;
    settleAccount.innerHTML = '';
    list.forEach(a => {
      const opt = document.createElement('option');
      opt.value = String(a.id);
      opt.textContent = `${a.name}`;
      settleAccount.appendChild(opt);
    });
    if (list.length === 0) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'No hay cuentas disponibles';
      settleAccount.appendChild(opt);
    }
  }

  async function refreshSettleAccountsByMethod() {
    const method = String(settleMethod?.value || 'EFECTIVO').toUpperCase();
    const accounts = await loadAccountsByMethod(method);
    fillAccountSelect(accounts);

    if (settleRefWrap) settleRefWrap.style.display = (method === 'TRANSFERENCIA') ? '' : 'none';
    if (settleReference) {
      if (method !== 'TRANSFERENCIA') settleReference.value = '';
    }
  }

  async function openSettleModal(move) {
    currentMoveForSettle = move;
    if (!settleModal) return;

    const ref = move.reference || '';
    const amount = Number(move.amount || 0);
    const label = (ref === 'VENTA') ? 'Cobrar venta' : (ref === 'COMPRA' ? 'Pagar compra' : 'Saldar movimiento');

    if (settleModalTitle) settleModalTitle.textContent = label;
    if (settleInfo) settleInfo.textContent = `Movimiento #${move.id} — ${ref} — $${fmtMoney(Math.abs(amount))}`;

    const inferred = (String(move.origin || '').toUpperCase() === 'BANCO') ? 'TRANSFERENCIA' : 'EFECTIVO';
    if (settleMethod) settleMethod.value = inferred;

    if (settleNote) settleNote.value = '';
    if (settleReference) settleReference.value = '';

    await refreshSettleAccountsByMethod();
    settleModal.show();
  }

  async function settleCurrentMove() {
    if (!currentMoveForSettle) return;

    const cash_register_id = Number(settleAccount?.value || 0);
    if (!cash_register_id) {
      alert('Selecciona una caja/cuenta válida.');
      return;
    }

    const payload = {
      action: 'settle',
      move_id: Number(currentMoveForSettle.id),
      cash_register_id,
      reference_code: (settleReference && settleRefWrap && settleRefWrap.style.display !== 'none')
        ? String(settleReference.value || '').trim()
        : '',
      note: String(settleNote?.value || '').trim(),
    };

    const res = await fetchJsonLoose(`${API_BASE}account_move_actions.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(res?.message || 'No se pudo saldar');

    settleModal?.hide();
    await loadMovements();
  }

  async function cancelMove(move) {
    if (!confirm(`¿Cancelar el movimiento #${move.id}?`)) return;

    const res = await fetchJsonLoose(`${API_BASE}account_move_actions.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'cancel', move_id: Number(move.id) })
    });
    if (!res.ok) throw new Error(res?.message || 'No se pudo cancelar');

    await loadMovements();
  }

  async function refundMove(move) {
    if (!confirm(`¿Generar reembolso/devolución para el movimiento #${move.id}?`)) return;

    try {
      const response = await fetchJsonLoose(`${API_BASE}account_move_actions.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'refund',
          move_id: Number(move.id)
        })
      });

      if (response.ok) {
        alert('Reembolso realizado con éxito');
      } else {
        alert('Error al realizar el reembolso: ' + (response.error || 'Desconocido'));
      }

      await loadMovements();
    } catch (error) {
      console.error('Error en el proceso de reembolso:', error);
      alert('Hubo un error al intentar realizar el reembolso. Por favor, inténtelo nuevamente.');
    }
  }

  // ===== Administración de cuentas (modal) =====
  async function loadAllAccounts() {
    const res = await fetchJsonLoose(`${API_BASE}cash_registers.php`);
    if (!res.ok) throw new Error(res?.message || 'No se pudieron cargar cuentas');
    return Array.isArray(res.data) ? res.data : [];
  }

  function showAccountsError(msg) {
    if (!accountsError) return;
    accountsError.textContent = msg;
    accountsError.classList.remove('d-none');
  }
  function clearAccountsError() {
    if (!accountsError) return;
    accountsError.textContent = '';
    accountsError.classList.add('d-none');
  }

  function renderAccountsTable(list) {
    if (!accountsTbody) return;
    accountsTbody.innerHTML = '';

    if (!Array.isArray(list) || list.length === 0) {
      accountsTbody.innerHTML = `<tr><td colspan="6" class="text-center">No hay cuentas</td></tr>`;
      return;
    }

    list.forEach(a => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(a.id)}</td>
        <td><input class="form-control form-control-sm" data-f="name" value="${escapeHtml(a.name)}"></td>
        <td>
          <select class="form-select form-select-sm" data-f="kind">
            <option value="cash" ${a.kind === 'cash' ? 'selected' : ''}>cash</option>
            <option value="bank" ${a.kind === 'bank' ? 'selected' : ''}>bank</option>
          </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm text-end" data-f="current_balance" value="${escapeHtml(a.current_balance)}"></td>
        <td class="text-center">
          <input type="checkbox" data-f="is_active" ${Number(a.is_active) === 1 ? 'checked' : ''}>
        </td>
        <td>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-primary" data-act="save">Guardar</button>
            <button class="btn btn-outline-secondary" data-act="toggle">${Number(a.is_active) === 1 ? 'Desactivar' : 'Activar'}</button>
          </div>
        </td>
      `;

      tr.querySelector('[data-act="save"]').addEventListener('click', async () => {
        clearAccountsError();
        try {
          const name = tr.querySelector('[data-f="name"]').value.trim();
          const kind = tr.querySelector('[data-f="kind"]').value;
          const current_balance = Number(tr.querySelector('[data-f="current_balance"]').value || 0);
          const is_active = tr.querySelector('[data-f="is_active"]').checked ? 1 : 0;

          if (!name) {
            showAccountsError('El nombre no puede ir vacío.');
            return;
          }

          const res = await fetchJsonLoose(`${API_BASE}cash_registers.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update',
              id: Number(a.id),
              name,
              kind,
              current_balance,
              is_active
            })
          });
          if (!res.ok) throw new Error(res?.message || 'No se pudo guardar');

          await refreshAccountsModal();
          await loadMovements();
        } catch (e) {
          console.error(e);
          showAccountsError(e.message || 'Error al guardar');
        }
      });

      tr.querySelector('[data-act="toggle"]').addEventListener('click', async () => {
        clearAccountsError();
        try {
          const is_active = Number(a.is_active) === 1 ? 0 : 1;
          const res = await fetchJsonLoose(`${API_BASE}cash_registers.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update',
              id: Number(a.id),
              name: a.name,
              kind: a.kind,
              current_balance: Number(a.current_balance || 0),
              is_active
            })
          });
          if (!res.ok) throw new Error(res?.message || 'No se pudo actualizar');

          await refreshAccountsModal();
          await loadMovements();
        } catch (e) {
          console.error(e);
          showAccountsError(e.message || 'Error al actualizar');
        }
      });

      accountsTbody.appendChild(tr);
    });
  }

  async function refreshAccountsModal() {
    clearAccountsError();
    const list = await loadAllAccounts();
    renderAccountsTable(list);
  }

  function openAccountsModal() {
    if (!accountsModal) return;
    refreshAccountsModal().catch(err => {
      console.error(err);
      showAccountsError(err.message || 'Error cargando cuentas');
    });
    accountsModal.show();
  }

  async function createAccount() {
    clearAccountsError();
    try {
      const name = String(newAcctName?.value || '').trim();
      const kind = String(newAcctKind?.value || 'cash');
      const current_balance = Number(newAcctBalance?.value || 0);

      if (!name) {
        showAccountsError('Captura un nombre para la cuenta.');
        return;
      }

      const res = await fetchJsonLoose(`${API_BASE}cash_registers.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create',
          name,
          kind,
          current_balance
        })
      });
      if (!res.ok) throw new Error(res?.message || 'No se pudo crear');

      newAcctName.value = '';
      newAcctBalance.value = '0';

      await refreshAccountsModal();
      await loadMovements();
    } catch (e) {
      console.error(e);
      showAccountsError(e.message || 'Error al crear');
    }
  }

  // ===== Eventos =====
  if (btnFilter) btnFilter.addEventListener('click', loadMovements);

  if (partyInput) partyInput.addEventListener('input', reRenderFromCurrent);
  if (placeInput) placeInput.addEventListener('input', reRenderFromCurrent);

  if (settleMethod) settleMethod.addEventListener('change', () => {
    refreshSettleAccountsByMethod().catch(console.error);
  });

  if (btnSettleConfirm) btnSettleConfirm.addEventListener('click', () => {
    settleCurrentMove().catch(err => {
      console.error(err);
      alert(err?.message || 'Error al saldar');
    });
  });

  if (btnAR) btnAR.addEventListener('click', () => (window.location.href = 'accounting_receivables.html'));
  if (btnAP) btnAP.addEventListener('click', () => (window.location.href = 'accounting_payables.html'));
  if (btnBANK) btnBANK.addEventListener('click', () => (window.location.href = 'accounting_banks.html'));

  if (btnManageAccounts) btnManageAccounts.addEventListener('click', openAccountsModal);
  if (btnCreateAccount) btnCreateAccount.addEventListener('click', () => createAccount());

  // ===== ENTRADA/SALIDA =====
  if (btnAddEntry) btnAddEntry.addEventListener('click', async () => {
    entryDescription.value = '';
    entryAmount.value = '';
    entryCashRegister.value = '';
    entryReference.value = '';
    entryNote.value = '';
    try {
      const accounts = await loadAllCashRegisters();
      fillCashRegisterSelect(entryCashRegister, accounts);
    } catch (err) {
      console.error('Error cargando cajas:', err);
    }
    addEntryModal?.show();
  });

  if (btnAddExit) btnAddExit.addEventListener('click', async () => {
    exitDescription.value = '';
    exitAmount.value = '';
    exitCashRegister.value = '';
    exitReference.value = '';
    exitNote.value = '';
    try {
      const accounts = await loadAllCashRegisters();
      fillCashRegisterSelect(exitCashRegister, accounts);
    } catch (err) {
      console.error('Error cargando cajas:', err);
    }
    addExitModal?.show();
  });

  if (btnConfirmEntry) btnConfirmEntry.addEventListener('click', async () => {
    const desc = String(entryDescription.value || '').trim();
    const amt = parseFloat(entryAmount.value || 0);
    const cashId = parseInt(entryCashRegister.value || 0);
    const ref = String(entryReference.value || '').trim();
    const note = String(entryNote.value || '').trim();

    if (!desc || amt <= 0 || cashId <= 0) {
      alert('Completa todos los campos requeridos');
      return;
    }

    try {
      const res = await fetchJsonLoose(`${API_BASE}account_operations.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'entry',
          description: desc,
          amount: amt,
          cash_register_id: cashId,
          reference: ref,
          note: note
        })
      });

      if (res.ok) {
        alert('✅ Entrada registrada correctamente');
        addEntryModal?.hide();
        await loadMovements();
      } else {
        alert('❌ Error: ' + (res.error || 'Desconocido'));
      }
    } catch (err) {
      console.error(err);
      alert('Error al registrar entrada: ' + err.message);
    }
  });

  if (btnConfirmExit) btnConfirmExit.addEventListener('click', async () => {
    const desc = String(exitDescription.value || '').trim();
    const amt = parseFloat(exitAmount.value || 0);
    const cashId = parseInt(exitCashRegister.value || 0);
    const ref = String(exitReference.value || '').trim();
    const note = String(exitNote.value || '').trim();

    if (!desc || amt <= 0 || cashId <= 0) {
      alert('Completa todos los campos requeridos');
      return;
    }

    try {
      const res = await fetchJsonLoose(`${API_BASE}account_operations.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'exit',
          description: desc,
          amount: amt,
          cash_register_id: cashId,
          reference: ref,
          note: note
        })
      });

      if (res.ok) {
        alert('✅ Salida registrada correctamente');
        addExitModal?.hide();
        await loadMovements();
      } else {
        alert('❌ Error: ' + (res.error || 'Desconocido'));
      }
    } catch (err) {
      console.error(err);
      alert('Error al registrar salida: ' + err.message);
    }
  });

 // Reporte diario contabilidad (botón 'Diario' en la pantalla de Contabilidad)
const btnDailyReport = document.getElementById('btnDaily');
const accountingDailyModalEl = document.getElementById('accountingDailyModal');
const accountingDailyModal = accountingDailyModalEl ? new bootstrap.Modal(accountingDailyModalEl) : null;
const accountingReportDate = document.getElementById('reportDate');

if (accountingReportDate) accountingReportDate.value = new Date().toISOString().slice(0,10);

if (btnDailyReport && accountingDailyModal) {
  btnDailyReport.addEventListener('click', () => {
    accountingDailyModal.show();

    // Asegura que el modal quede al frente (z-index/backdrop)
    setTimeout(() => {
      try {
        accountingDailyModalEl?.focus();
        const dialog = accountingDailyModalEl?.querySelector('.modal-dialog');
        dialog?.scrollIntoView({ block: 'center', inline: 'nearest' });
      } catch {}
    }, 50);
  });
}

const btnGenerateAccountingDaily = document.getElementById('btnGenerateAccountingDaily');

async function readJsonSafe(response) {
  const text = await response.text();
  if (!text) return { ok: false, error: 'Respuesta vacía del servidor' };

  try {
    return JSON.parse(text);
  } catch (e) {
    // Devuelve el texto para depurar (muchas veces es HTML de error)
    return { ok: false, error: 'Respuesta no es JSON', raw: text.slice(0, 600) };
  }
}

if (btnGenerateAccountingDaily) {
  btnGenerateAccountingDaily.addEventListener('click', async () => {
    const d = accountingReportDate?.value || new Date().toISOString().slice(0,10);

    btnGenerateAccountingDaily.disabled = true;
    const oldText = btnGenerateAccountingDaily.textContent;
    btnGenerateAccountingDaily.textContent = 'Generando...';

    try {
      const res = await fetch(`${API_BASE}report_accounting_daily_pdf.php`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: d })
      });

      const json = await readJsonSafe(res);

      if (res.ok && json && json.ok && json.file) {
        window.open('../backend/' + json.file, '_blank');
        accountingDailyModal?.hide();
      } else {
        console.error('Error reporte contable:', json);
        alert((json && (json.error || json.message)) || `Error generando reporte contable (HTTP ${res.status})`);
        if (json?.raw) console.warn('Respuesta cruda (primeros chars):\n', json.raw);
      }

    } catch (err) {
      console.error('Error generando reporte contable:', err);
      alert(err.message || 'Error al generar reporte');
    } finally {
      btnGenerateAccountingDaily.disabled = false;
      btnGenerateAccountingDaily.textContent = oldText || 'Generar PDF';
    }
  });
}


  // ===== Init =====
  rebuildUsersDatalist().catch(console.error);
  loadMovements();
});
