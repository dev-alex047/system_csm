// accounting_payables.js - Gestión de cuentas por pagar

let payablesData = [];
let cashRegisters = [];
let users = [];
let currentPayId = null;

document.addEventListener('DOMContentLoaded', () => {
  checkLogin();
  setupTheme();
  setupEventListeners();
  loadData();
});

function setupEventListeners() {
  document.getElementById('btnReload').addEventListener('click', loadData);
  document.getElementById('filterSupplier').addEventListener('input', applyFilters);
  document.getElementById('filterStatus').addEventListener('change', applyFilters);
  document.getElementById('payMethod').addEventListener('change', updatePayUI);
  document.getElementById('btnDoPay').addEventListener('click', doPay);
  document.getElementById('btnPrintTicket').addEventListener('click', printTicket);
}

async function loadData() {
  try {
    // Load payables
    const res = await fetch('../backend/api/payables.php', { credentials: 'include' });
    if (res.status === 401) {
      localStorage.removeItem('user');
      window.location.href = 'login.html';
      return;
    }

    if (!res.ok) {
      const txt = await res.text().catch(()=>'');
      throw new Error(`Error ${res.status}: ${txt.slice(0,300)}`);
    }
    payablesData = await res.json();

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
  const supplierFilter = document.getElementById('filterSupplier').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value;

  let filtered = payablesData.filter(item => {
    const matchSupplier = !supplierFilter || item.supplier_name.toLowerCase().includes(supplierFilter);
    const matchStatus = !statusFilter || item.status === statusFilter;
    return matchSupplier && matchStatus;
  });

  renderTable(filtered);
}

function renderTable(data) {
  const tbody = document.querySelector('#apTable tbody');
  
  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No hay registros</td></tr>';
    return;
  }

  tbody.innerHTML = data.map(item => {
    const statusBadge = getStatusBadge(item.status);
    const user = users.find(u => u.id == item.user_id);
    // Prefer last_operator_name, then creator_name (from backend), then user's username
    const userCreatorName = item.creator_name || (user ? (user.username || user.nombre || user.name || ('Usuario #' + item.user_id)) : ('Usuario #' + item.user_id));
    const userName = (item.last_operator_name && item.last_operator_name.trim() !== '') ? item.last_operator_name : userCreatorName;
    const totalAmount = parseFloat(item.total_amount) || 0;
    const paidAmount = parseFloat(item.paid_amount) || 0;
    const pendingAmount = totalAmount - paidAmount;

    return `<tr data-user="${userName}" data-id="${item.id}">
      <td>${new Date(item.created_at).toLocaleDateString('es-MX')}</td>
      <td><strong>${item.supplier_name}</strong></td>
      <td>${userName}</td>
      <td class="text-end">$${totalAmount.toFixed(2)}</td>
      <td class="text-end text-success">$${paidAmount.toFixed(2)}</td>
      <td class="text-end text-warning fw-bold">$${pendingAmount.toFixed(2)}</td>
      <td>${statusBadge}</td>
      <td>
        <button class="btn btn-sm btn-info" onclick="viewTicket(${item.purchase_id})" title="Ver compra">🎟️</button>
        ${item.status === 'PENDIENTE' ? `<button class="btn btn-sm btn-warning" onclick="openPay(${item.id}, '${item.supplier_name}', ${pendingAmount})" title="Pagar">💳</button>` : ''}
      </td>
    </tr>`;
  }).join('');

  // Add hover tooltips
  document.querySelectorAll('#apTable tbody tr').forEach(tr => {
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

  payablesData.forEach(item => {
    const total = parseFloat(item.total_amount) || 0;
    const paid = parseFloat(item.paid_amount) || 0;
    totalAmount += total;
    paidAmount += paid;
    pendingAmount += (total - paid);
  });

  document.getElementById('totalPending').textContent = '$' + pendingAmount.toFixed(2);
  document.getElementById('totalPaid').textContent = '$' + paidAmount.toFixed(2);
  document.getElementById('totalCount').textContent = payablesData.length;
  
  const percentPaid = totalAmount > 0 ? ((paidAmount / totalAmount) * 100).toFixed(1) : 0;
  document.getElementById('percentPaid').textContent = percentPaid + '%';
}

function openPay(payableId, supplierName, pendingAmount) {
  currentPayId = payableId;
  document.getElementById('payDesc').textContent = `Proveedor: ${supplierName} | Pendiente: $${parseFloat(pendingAmount).toFixed(2)}`;
  document.getElementById('payAmount').value = parseFloat(pendingAmount).toFixed(2);
  document.getElementById('payPayableId').value = payableId;
  
  // Load accounts
  const select = document.getElementById('payAccount');
  select.innerHTML = '';
  cashRegisters.forEach(caja => {
    const opt = document.createElement('option');
    opt.value = caja.id;
    opt.textContent = caja.nombre + ' (' + caja.tipo + ')';
    select.appendChild(opt);
  });

  const modal = new bootstrap.Modal(document.getElementById('payModal'));
  modal.show();
}

function updatePayUI() {
  const method = document.getElementById('payMethod').value;
  document.getElementById('payRefRow').style.display = method === 'TRANSFERENCIA' ? 'block' : 'none';
}

async function doPay() {
  const payableId = document.getElementById('payPayableId').value;
  const amount = parseFloat(document.getElementById('payAmount').value);
  const method = document.getElementById('payMethod').value;
  const accountId = document.getElementById('payAccount').value;
  const ref = document.getElementById('payRef').value;
  const note = document.getElementById('payNote').value;

  if (!amount || amount <= 0) {
    showAlert('Ingresa cantidad válida', 'warning');
    return;
  }

  try {
    // Find payable to include purchase info
    const payable = payablesData.find(p => p.id == payableId);

    // Record payment in payables and pass cash/account info so backend creeates the move (avoids duplicated entries)
    const payRes = await fetch('../backend/api/payables.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'pay',
        id: payableId,
        amount: amount,
        cash_register_id: accountId,
        payment_method: method,
        reference: ref,
        note: note,
        purchase_id: payable.purchase_id,
        ticket_number: payable.ticket_number || ''
      })
    });

    if (!payRes.ok) throw new Error('Error al registrar pago');
    const payData = await payRes.json();
    if (!payData.ok) throw new Error(payData.message || 'Error al registrar pago');

    showAlert('Pago registrado correctamente', 'success');
    bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
    loadData();
  } catch (error) {
    console.error('Error in doPay:', error);
    showAlert(error.message, 'danger');
  }
}

async function viewTicket(purchaseId) {
  try {
    const res = await fetch('../backend/api/purchases.php?action=detail&id=' + purchaseId, { credentials: 'include' });
    if (!res.ok) throw new Error('Error al cargar compra');
    const purchase = await res.json();

    let html = `
      <div class="card mb-3">
        <div class="card-header bg-warning text-dark">
          <h6 class="mb-0">Compra #${purchase.id}</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <p><strong>Proveedor:</strong> ${purchase.proveedor}</p>
              <p><strong>Teléfono:</strong> ${purchase.telefono || 'N/A'}</p>
            </div>
            <div class="col-md-6">
              <p><strong>Fecha:</strong> ${new Date(purchase.created_at).toLocaleDateString('es-MX')}</p>
              <p><strong>Comprador:</strong> ${purchase.usuario}</p>
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
              ${(purchase.items || []).map(item => `
                <tr>
                  <td>${item.nombre_producto}</td>
                  <td class="text-end">${item.cantidad}</td>
                  <td class="text-end">$${parseFloat(item.precio_compra).toFixed(2)}</td>
                  <td class="text-end">$${(item.cantidad * item.precio_compra).toFixed(2)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          
          <div class="mt-3 p-3 bg-light rounded">
            <p class="mb-1"><strong>Subtotal:</strong> $${parseFloat(purchase.subtotal).toFixed(2)}</p>
            <p class="mb-1"><strong>IVA:</strong> +$${parseFloat(purchase.iva || 0).toFixed(2)}</p>
            <p class="mb-0"><strong>Total:</strong> $${parseFloat(purchase.total).toFixed(2)}</p>
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
    // Fallback: if parsing fails, display raw string
    document.getElementById('nav-user').textContent = '👤 ' + raw;
  }
}

function logout() {
  localStorage.removeItem('usuario');
  localStorage.removeItem('usuarioId');
  localStorage.removeItem('user');
  window.location.href = 'login.html';
}
