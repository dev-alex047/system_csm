// Script para página de cuentas por cobrar
document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();
  // Solo admin
  if (!user || !user.role || user.role.toUpperCase() !== 'ADMIN') {
    const div = document.getElementById('receivableList');
    if (div) div.innerHTML = '<div class="alert alert-danger">No tienes permiso para acceder a este módulo.</div>';
    return;
  }
  const startInput = document.getElementById('startDate');
  const endInput = document.getElementById('endDate');
  const btnFilter = document.getElementById('btnFilter');
  const listDiv = document.getElementById('receivableList');

  function formatCurrency(n) {
    const num = parseFloat(n);
    return isNaN(num) ? '$0.00' : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(num);
  }

  function load() {
    let endpoint = 'accounts_receivable.php';
    const params = [];
    if (startInput.value) params.push('start=' + encodeURIComponent(startInput.value));
    if (endInput.value) params.push('end=' + encodeURIComponent(endInput.value));
    if (params.length) endpoint += '?' + params.join('&');
    apiRequest(endpoint).then(res => {
      const records = res.records || [];
      if (!records.length) {
        listDiv.innerHTML = '<div class="alert alert-light border">Sin cuentas por cobrar en el intervalo seleccionado.</div>';
        return;
      }
      let html = '<table class="table table-striped table-bordered table-sm"><thead><tr>' +
        '<th>Fecha</th><th>Cliente</th><th>Total</th><th>Pendiente</th><th>Método</th><th>Vendedor</th><th>Ticket</th></tr></thead><tbody>';
      records.forEach(r => {
        const date = r.date ? new Date(r.date).toLocaleString() : '';
        const ticket = r.ticket_barcode ? r.ticket_barcode : '';
        html += '<tr>' +
          `<td>${date}</td>` +
          `<td>${r.client_name || ''}</td>` +
          `<td>${formatCurrency(r.total)}</td>` +
          `<td>${formatCurrency(r.pending)}</td>` +
          `<td>${r.payment_method || ''}</td>` +
          `<td>${r.user_name || ''}</td>` +
          `<td>${ticket}</td>` +
          '</tr>';
      });
      html += '</tbody></table>';
      listDiv.innerHTML = html;
    });
  }

  btnFilter.addEventListener('click', () => {
    load();
  });
  // Inicializar
  load();
});