// Script para detalle de una cuenta bancaria
document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();
  if (!user || !user.role || user.role.toUpperCase() !== 'ADMIN') {
    const summaryDiv = document.getElementById('accountSummary');
    if (summaryDiv) summaryDiv.innerHTML = '<div class="alert alert-danger">No tienes permiso para acceder a este módulo.</div>';
    return;
  }
  const urlParams = new URLSearchParams(window.location.search);
  const id = urlParams.get('id');
  if (!id) {
    document.body.innerHTML = '<div class="container my-4"><div class="alert alert-danger">ID de cuenta no especificado.</div></div>';
    return;
  }
  const startInput = document.getElementById('startDate');
  const endInput = document.getElementById('endDate');
  const btnFilter = document.getElementById('btnFilter');
  const summaryDiv = document.getElementById('accountSummary');
  const movesDiv = document.getElementById('accountMoves');
  const nameHeading = document.getElementById('accountName');

  function formatCurrency(n) {
    const num = parseFloat(n);
    return isNaN(num) ? '$0.00' : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(num);
  }

  function load() {
    let endpoint = 'bank_accounts.php?id=' + encodeURIComponent(id);
    const params = [];
    if (startInput.value) params.push('start=' + encodeURIComponent(startInput.value));
    if (endInput.value) params.push('end=' + encodeURIComponent(endInput.value));
    if (params.length) endpoint += '&' + params.join('&');
    apiRequest(endpoint).then(res => {
      const acc = res.account;
      const moves = res.moves || [];
      if (acc) {
        nameHeading.textContent = acc.name || 'Cuenta bancaria';
        // Resumen (ingresos, egresos, saldo)
        const incomes = parseFloat(res.incomes || 0);
        const expenses = parseFloat(res.expenses || 0);
        const net = incomes - expenses;
        summaryDiv.innerHTML = '<div class="row g-3">' +
          `<div class="col-md-4"><div class="alert alert-success">Ingresos: ${formatCurrency(incomes)}</div></div>` +
          `<div class="col-md-4"><div class="alert alert-danger">Egresos: ${formatCurrency(expenses)}</div></div>` +
          `<div class="col-md-4"><div class="alert alert-info">Neto: ${formatCurrency(net)}</div></div>` +
          '</div>';
      }
      if (!moves.length) {
        movesDiv.innerHTML = '<div class="alert alert-light border">Sin movimientos en el intervalo seleccionado.</div>';
        return;
      }
      let html = '<table class="table table-striped table-bordered table-sm"><thead><tr>' +
        '<th>Fecha</th><th>Tipo</th><th>Monto</th><th>Descripción</th><th>Usuario</th></tr></thead><tbody>';
      moves.forEach(m => {
        const dateStr = m.date ? new Date(m.date).toLocaleString() : '';
        html += '<tr>' +
          `<td>${dateStr}</td>` +
          `<td>${m.type}</td>` +
          `<td>${formatCurrency(m.amount)}</td>` +
          `<td>${m.description || ''}</td>` +
          `<td>${m.user_name || ''}</td>` +
          '</tr>';
      });
      html += '</tbody></table>';
      movesDiv.innerHTML = html;
    });
  }

  btnFilter.addEventListener('click', () => {
    load();
  });
  load();
});