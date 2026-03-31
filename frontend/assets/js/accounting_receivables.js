// accounting_receivables.js - Gestión de cuentas por cobrar

let receivablesData = [];
let cashRegisters = [];
let users = [];
let currentSettleId = null;

document.addEventListener('DOMContentLoaded', () => {
  checkLogin();
  setupTheme();
  setupEventListeners();
  loadData();
});

function setupEventListeners() {
  document.getElementById('btnReload').addEventListener('click', loadData);
  document.getElementById('filterClient').addEventListener('input', applyFilters);
  document.getElementById('filterStatus').addEventListener('change', applyFilters);
  document.getElementById('settleMethod').addEventListener('change', updateSettleUI);
  document.getElementById('btnDoSettle').addEventListener('click', doSettle);
  document.getElementById('btnPrintTicket').addEventListener('click', printTicket);
}

async function loadData() {
  try {
    // Load receivables
    const res = await fetch('../backend/api/receivables.php', { credentials: 'include' });
    if (res.status === 401) {
      localStorage.removeItem('user');
      window.location.href = 'login.html';
      return;
    }

    if (!res.ok) {
      const txt = await res.text().catch(()=>'');
      throw new Error(`Error ${res.status}: ${txt.slice(0,300)}`);
    }
    receivablesData = await res.json();

    // Load cash registers for payment form
    const regRes = await fetch('../backend/api/cash_registers.php', { credentials: 'include' });
    if (regRes.ok) {
      const data = await regRes.json();
      cashRegisters = Array.isArray(data) ? data : (data?.data || []);
    }

    // Load users for display
    const usersRes = await fetch('../backend/api/users.php', { credentials: 'include' });
    if (usersRes.ok) {
      const data = await usersRes.json();
      users = Array.isArray(data) ? data : (data?.data || []);
    }

    applyFilters();
    updateSummary();
  } catch (error) {
    console.error('Error loading data:', error);
    showAlert('Error al cargar datos: ' + error.message, 'danger');
  }
}

function applyFilters() {
  const clientFilter = document.getElementById('filterClient').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value;

  let filtered = receivablesData.filter(item => {
    const matchClient = !clientFilter || item.client_name.toLowerCase().includes(clientFilter);
    const matchStatus = !statusFilter || item.status === statusFilter;
    return matchClient && matchStatus;
  });

  renderTable(filtered);
}

function renderTable(data) {
  const tbody = document.querySelector('#arTable tbody');
  
  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No hay registros</td></tr>';
    return;
  }

  tbody.innerHTML = data.map(item => {
    const statusBadge = getStatusBadge(item.status);
    const user = users.find(u => u.id == item.user_id);
    // Prefer the last_operator_name (who actually settled/authorized the movement). Fallback to creator_name (from backend) or user's username.
    const userCreatorName = item.creator_name || (user ? (user.username || user.nombre || user.name || ('Usuario #' + item.user_id)) : ('Usuario #' + item.user_id));
    const userName = (item.last_operator_name && item.last_operator_name.trim() !== '') ? item.last_operator_name : userCreatorName;
    const totalAmount = parseFloat(item.total_amount) || 0;
    const paidAmount = parseFloat(item.paid_amount) || 0;
    const pendingAmount = totalAmount - paidAmount;

    return `<tr data-user="${userName}" data-id="${item.id}">
      <td>${new Date(item.created_at).toLocaleDateString('es-MX')}</td>
      <td><strong>${item.client_name}</strong></td>
      <td>${userName}</td>
      <td class="text-end">$${totalAmount.toFixed(2)}</td>
      <td class="text-end text-success">$${paidAmount.toFixed(2)}</td>
      <td class="text-end text-warning fw-bold">$${pendingAmount.toFixed(2)}</td>
      <td>${statusBadge}</td>
      <td>
        <button class="btn btn-sm btn-info" onclick="viewTicket(${item.sale_id})" title="Ver venta">🎟️</button>
        ${item.status === 'PENDIENTE' ? `<button class="btn btn-sm btn-success" onclick="openSettle(${item.id}, '${item.client_name}', ${pendingAmount})" title="Cobrar">💳</button>` : ''}
      </td>
    </tr>`;
  }).join('');

  // Add hover tooltips
  document.querySelectorAll('#arTable tbody tr').forEach(tr => {
    tr.addEventListener('mouseenter', (e) => {
      const user = tr.getAttribute('data-user');
      if (user) {
        e.target.title = `Por: ${user}`;
      }
    });
  });
}

function getStatusBadge(status) {
  const badges = {
    'PENDIENTE': '<span class="badge bg-warning text-dark">⏳ PENDIENTE</span>',
    'PAGADO': '<span class="badge bg-success">✓ PAGADO</span>',
    'CANCELADO': '<span class="badge bg-danger">✕ CANCELADO</span>'
  };
  return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
}

function updateSummary() {
  let totalAmount = 0;
  let paidAmount = 0;
  let pendingAmount = 0;

  receivablesData.forEach(item => {
    const total = parseFloat(item.total_amount) || 0;
    const paid = parseFloat(item.paid_amount) || 0;
    totalAmount += total;
    paidAmount += paid;
    pendingAmount += (total - paid);
  });

  document.getElementById('totalPending').textContent = '$' + pendingAmount.toFixed(2);
  document.getElementById('totalPaid').textContent = '$' + paidAmount.toFixed(2);
  document.getElementById('totalCount').textContent = receivablesData.length;
  
  const percentPaid = totalAmount > 0 ? ((paidAmount / totalAmount) * 100).toFixed(1) : 0;
  document.getElementById('percentPaid').textContent = percentPaid + '%';
}

function openSettle(receivableId, clientName, pendingAmount) {
  currentSettleId = receivableId;
  document.getElementById('settleDesc').textContent = `Cliente: ${clientName} | Pendiente: $${parseFloat(pendingAmount).toFixed(2)}`;
  document.getElementById('settleAmount').value = parseFloat(pendingAmount).toFixed(2);
  document.getElementById('settleReceivableId').value = receivableId;
  
  // Load accounts
  const select = document.getElementById('settleAccount');
  select.innerHTML = '';
  cashRegisters.forEach(caja => {
    const opt = document.createElement('option');
    opt.value = caja.id;
    opt.textContent = caja.nombre + ' (' + caja.tipo + ')';
    select.appendChild(opt);
  });

  const modal = new bootstrap.Modal(document.getElementById('settleModal'));
  modal.show();
}

function updateSettleUI() {
  const method = document.getElementById('settleMethod').value;
  document.getElementById('settleRefRow').style.display = method === 'TRANSFERENCIA' ? 'block' : 'none';
}

async function doSettle() {
  const receivableId = document.getElementById('settleReceivableId').value;
  const amount = parseFloat(document.getElementById('settleAmount').value);
  const method = document.getElementById('settleMethod').value;
  const accountId = document.getElementById('settleAccount').value;
  const ref = document.getElementById('settleRef').value;
  const note = document.getElementById('settleNote').value;

  if (!amount || amount <= 0) {
    showAlert('Ingresa cantidad válida', 'warning');
    return;
  }

  try {
    // Step 1: Record payment in receivables
    const payRes = await fetch('../backend/api/receivables.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'pay',
        id: receivableId,
        amount: amount
      })
    });

    if (!payRes.ok) throw new Error('Error al registrar pago');
    const payData = await payRes.json();
    if (!payData.ok) throw new Error(payData.message || 'Error al registrar pago');

    // Note: send cash/account data to receivables endpoint so BACKEND creates the account_move (avoids duplicates)
    const receivable = receivablesData.find(r => r.id == receivableId);

    const payRes2 = await fetch('../backend/api/receivables.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'pay',
        id: receivableId,
        amount: amount,
        cash_register_id: accountId,
        payment_method: method,
        reference: ref,
        note: note,
        sale_id: receivable.sale_id,
        ticket_number: receivable.ticket_number || ''
      })
    });
    if (!payRes2.ok) throw new Error('Error al registrar pago (backend)');
    const payData2 = await payRes2.json();
    if (!payData2.ok) throw new Error(payData2.message || 'Error al registrar pago (backend)');

    showAlert('Pago registrado correctamente', 'success');
    bootstrap.Modal.getInstance(document.getElementById('settleModal')).hide();
    loadData();
  } catch (error) {
    console.error('Error in doSettle:', error);
    showAlert(error.message, 'danger');
  }
}

async function viewTicket(saleId) {
  try {
    const res = await fetch('../backend/api/sales.php?action=detail&id=' + saleId, { credentials: 'include' });
    if (!res.ok) throw new Error('Error al cargar venta');
    const sale = await res.json();

    let html = `
      <div class="card mb-3">
        <div class="card-header bg-primary text-white">
          <h6 class="mb-0">Venta #${sale.id}</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <p><strong>Cliente:</strong> ${sale.cliente}</p>
              <p><strong>Teléfono:</strong> ${sale.telefono || 'N/A'}</p>
            </div>
            <div class="col-md-6">
              <p><strong>Fecha:</strong> ${new Date(sale.created_at).toLocaleDateString('es-MX')}</p>
              <p><strong>Vendedor:</strong> ${sale.usuario}</p>
            </div>
          </div>
          
          <h6 class="mt-3 mb-2">Artículos</h6>
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Producto</th>
                <th class="text-end">Cant</th>
                <th class="text-end">Precio</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              ${(sale.items || []).map(item => `
                <tr>
                  <td>${item.nombre_producto}</td>
                  <td class="text-end">${item.cantidad}</td>
                  <td class="text-end">$${parseFloat(item.precio_venta).toFixed(2)}</td>
                  <td class="text-end">$${(item.cantidad * item.precio_venta).toFixed(2)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          
          <div class="mt-3 p-3 bg-light rounded">
            <p class="mb-1"><strong>Subtotal:</strong> $${parseFloat(sale.subtotal).toFixed(2)}</p>
            <p class="mb-1"><strong>Descuento:</strong> -$${parseFloat(sale.descuento || 0).toFixed(2)}</p>
            <p class="mb-0"><strong>Total:</strong> $${parseFloat(sale.total).toFixed(2)}</p>
          </div>
        </div>
      </div>
    `;

    document.getElementById('ticketContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('ticketModal')).show();
  } catch (error) {
    console.error('Error viewing ticket:', error);
    showAlert('Error al cargar ticket: ' + error.message, 'danger');
  }
}

function printTicket() {
  window.print();
}

function setupTheme() {
  const isDark = localStorage.getItem('theme') === 'dark';
  const btn = document.getElementById('themeToggleBtn');
  const html = document.documentElement;
  
  if (isDark) html.setAttribute('data-bs-theme', 'dark');
  
  btn.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    btn.textContent = newTheme === 'dark' ? 'Modo claro' : 'Modo oscuro';
  });

  // Mostrar botón de solicitudes solo si es ADMIN
  const btnReq = document.getElementById('btnViewRequests');
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    if (user && user.role && user.role.toUpperCase() === 'ADMIN') {
      if (btnReq) btnReq.style.display = '';
    }
  } catch (e) { /* ignore */ }

  if (btnReq) {
    btnReq.addEventListener('click', () => {
      const rm = new bootstrap.Modal(document.getElementById('requestsModal'));
      // Load pending requests
      fetch('../backend/api/returns.php?action=list_requests', { credentials: 'include' }).then(r => r.json()).then(res => {
        const tbody = document.querySelector('#requestsTableAR tbody');
        tbody.innerHTML = '';
        (res.requests || []).forEach(rq => {
          const amount = rq.items ? (JSON.parse(rq.items).reduce((s,i)=>s + ((parseFloat(i.unit_price || i.price || 0) || 0) * (parseFloat(i.quantity || 0) || 0)),0)).toFixed(2) : '';
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${rq.id}</td>
            <td>${rq.sale_id}</td>
            <td>${rq.type}</td>
            <td>${rq.requester_name || rq.requester_id}</td>
            <td>$${amount}</td>
            <td>${rq.created_at}</td>
            <td>
              <button class="btn btn-sm btn-success btn-approve-request-ar" data-id="${rq.id}">Aprobar</button>
              <button class="btn btn-sm btn-danger btn-reject-request-ar" data-id="${rq.id}">Rechazar</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
        rm.show();
      }).catch(err => {
        alert('Error cargando solicitudes');
      });
    });

    // Delegated approve
    document.addEventListener('click', (e) => {
      if (e.target && e.target.classList && e.target.classList.contains('btn-approve-request-ar')) {
        const id = parseInt(e.target.dataset.id || 0);
        if (!id) return alert('Invalid request id');
        if (!confirm('Aprobar esta solicitud?')) return;
        fetch('../backend/api/returns.php', { method: 'POST', credentials: 'include', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'approve', request_id: id }) })
          .then(r => r.json()).then(res => {
            if (res.ticket) window.open('../backend/' + res.ticket, '_blank');
            alert('Solicitud aprobada');
            // Reload table
            loadData();
            const rm = bootstrap.Modal.getInstance(document.getElementById('requestsModal'));
            if (rm) rm.hide();
          }).catch(err => {
            alert('Error aprobando solicitud');
          });
      }

      if (e.target && e.target.classList && e.target.classList.contains('btn-reject-request-ar')) {
        const id = parseInt(e.target.dataset.id || 0);
        if (!id) return alert('Invalid request id');
        const reason = prompt('Motivo del rechazo (opcional):');
        if (reason === null) return; // cancelled
        if (!confirm('Confirmar rechazo de la solicitud?')) return;
        fetch('../backend/api/returns.php', { method: 'POST', credentials: 'include', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'reject', request_id: id, reason: reason }) })
          .then(r => r.json()).then(res => {
            if (res.ok) alert('Solicitud rechazada');
            else alert('Error: ' + (res.error || 'No se pudo rechazar'));
            loadData();
            const rm = bootstrap.Modal.getInstance(document.getElementById('requestsModal'));
            if (rm) rm.hide();
          }).catch(err => {
            alert('Error rechazando solicitud');
          });
      }
    });
  }
}

function showAlert(message, type = 'info') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
  alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
  
  const container = document.querySelector('.container');
  container.insertBefore(alertDiv, container.firstChild);
  
  setTimeout(() => alertDiv.remove(), 5000);
}

function checkLogin() {
  const raw = localStorage.getItem('user');
  if (!raw) {
    window.location.href = 'login.html';
    return;
  }
  try {
    const user = JSON.parse(raw);
    document.getElementById('nav-user').textContent = '👤 ' + (user.username || user.nombre || user.name || 'Usuario');
  } catch (e) {
    document.getElementById('nav-user').textContent = '👤 ' + raw;
  }
}

function logout() {
  localStorage.removeItem('usuario');
  localStorage.removeItem('usuarioId');
  localStorage.removeItem('user');
  window.location.href = 'login.html';
}
