// Script para la lista de cuentas bancarias
document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();
  const listDiv = document.getElementById('bankAccountsList');
  if (!user || !user.role || user.role.toUpperCase() !== 'ADMIN') {
    listDiv.innerHTML = '<div class="alert alert-danger">No tienes permiso para acceder a este módulo.</div>';
    return;
  }
  function formatCurrency(n) {
    const num = parseFloat(n);
    return isNaN(num) ? '$0.00' : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(num);
  }
  function load() {
    apiRequest('bank_accounts.php').then(res => {
      const accounts = res.accounts || [];
      if (!accounts.length) {
        listDiv.innerHTML = '<div class="alert alert-light border">No hay cuentas bancarias registradas.</div>';
        return;
      }
      let html = '<table class="table table-striped table-bordered table-sm"><thead><tr>' +
        '<th>Cuenta</th><th>Banco</th><th>Número</th><th>Saldo</th><th>Acciones</th></tr></thead><tbody>';
      accounts.forEach(acc => {
        html += '<tr>' +
          `<td>${acc.name || ''}</td>` +
          `<td>${acc.bank_name || ''}</td>` +
          `<td>${acc.account_number || ''}</td>` +
          `<td>${formatCurrency(acc.balance)}</td>` +
          `<td><a href="bank_account_detail.html?id=${acc.id}" class="btn btn-sm btn-outline-info">Ver movimientos</a></td>` +
          '</tr>';
      });
      html += '</tbody></table>';
      listDiv.innerHTML = html;
    });
  }
  load();
});