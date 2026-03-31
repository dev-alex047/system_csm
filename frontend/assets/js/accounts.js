document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();
  // Solo admins
  const container = document.getElementById('accountsContent');
  if (!user || !user.role || user.role.toUpperCase() !== 'ADMIN') {
    container.innerHTML = '<div class="alert alert-danger">No tienes permiso para acceder a este módulo.</div>';
    return;
  }

  // Variables para datos
  let cashRegisters = [];
  let bankAccounts = [];

  // Formateo
  const formatCurrency = (n) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(parseFloat(n));

  // Cargar datos
  function loadData() {
    // Obtener cajas
    apiRequest('cash_registers.php').then(response => {
      // Handle response: {ok:true, data:[...]} o array directo
      cashRegisters = (response && response.data) ? response.data : (Array.isArray(response) ? response : []);
      render();
    });
    // Obtener cuentas bancarias
    apiRequest('bank_accounts.php').then(res => {
      bankAccounts = Array.isArray(res.accounts) ? res.accounts : [];
      render();
    });
  }

  // Renderizar página
  function render() {
    let html = '';
    // Sección de cajas
    html += '<h4>Cajas de efectivo</h4>';
    html += '<table class="table table-bordered table-sm">';
    html += '<thead><tr><th>ID</th><th>Nombre</th><th>Saldo inicial</th><th>Saldo actual</th><th>Acciones</th></tr></thead><tbody>';
    cashRegisters.forEach(reg => {
      html += `<tr>
        <td>${reg.id}</td>
        <td><input type="text" class="form-control form-control-sm" value="${reg.name}" data-cr-id="${reg.id}" data-field="name"></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm" value="${reg.saldo_inicial}" data-cr-id="${reg.id}" data-field="initial"></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm" value="${reg.balance}" data-cr-id="${reg.id}" data-field="balance"></td>
        <td><button class="btn btn-sm btn-primary" data-action="save-cr" data-id="${reg.id}">Guardar</button></td>
      </tr>`;
    });
    html += '</tbody></table>';
    // Formulario para nueva caja
    html += '<h5>Agregar nueva caja</h5>';
    html += '<div class="row g-3 mb-4">';
    html += '<div class="col-md-4"><input type="text" id="newCrName" class="form-control" placeholder="Nombre de la caja"></div>';
    html += '<div class="col-md-3"><input type="number" step="0.01" id="newCrInitial" class="form-control" placeholder="Saldo inicial"></div>';
    html += '<div class="col-md-3"><button id="btnAddCr" class="btn btn-success">Agregar</button></div>';
    html += '</div>';
    // Sección de cuentas bancarias
    html += '<h4>Cuentas bancarias</h4>';
    html += '<table class="table table-bordered table-sm">';
    html += '<thead><tr><th>ID</th><th>Nombre</th><th>Banco</th><th>No. Cuenta</th><th>Saldo</th><th>Acciones</th></tr></thead><tbody>';
    bankAccounts.forEach(acc => {
      html += `<tr>
        <td>${acc.id}</td>
        <td><input type="text" class="form-control form-control-sm" value="${acc.name}" data-bank-id="${acc.id}" data-field="name"></td>
        <td><input type="text" class="form-control form-control-sm" value="${acc.bank_name || ''}" data-bank-id="${acc.id}" data-field="bank_name"></td>
        <td><input type="text" class="form-control form-control-sm" value="${acc.account_number || ''}" data-bank-id="${acc.id}" data-field="account_number"></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm" value="${acc.balance}" data-bank-id="${acc.id}" data-field="balance"></td>
        <td><button class="btn btn-sm btn-primary" data-action="save-bank" data-id="${acc.id}">Guardar</button></td>
      </tr>`;
    });
    html += '</tbody></table>';
    // Formulario nueva cuenta bancaria
    html += '<h5>Agregar nueva cuenta bancaria</h5>';
    html += '<div class="row g-3">';
    html += '<div class="col-md-3"><input type="text" id="newBankName" class="form-control" placeholder="Nombre interno"></div>';
    html += '<div class="col-md-3"><input type="text" id="newBankBank" class="form-control" placeholder="Banco"></div>';
    html += '<div class="col-md-3"><input type="text" id="newBankAccountNumber" class="form-control" placeholder="Número de cuenta"></div>';
    html += '<div class="col-md-2"><input type="number" step="0.01" id="newBankBalance" class="form-control" placeholder="Saldo inicial"></div>';
    html += '<div class="col-md-1"><button id="btnAddBank" class="btn btn-success">Agregar</button></div>';
    html += '</div>';
    container.innerHTML = html;
    // Asignar eventos de guardar y agregar
    container.querySelectorAll('button[data-action="save-cr"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        // Recoger valores
        const name = container.querySelector(`input[data-cr-id="${id}"][data-field="name"]`).value;
        const initial = parseFloat(container.querySelector(`input[data-cr-id="${id}"][data-field="initial"]`).value) || 0;
        const balance = parseFloat(container.querySelector(`input[data-cr-id="${id}"][data-field="balance"]`).value) || 0;
        const payload = { id, name, initial_balance: initial, balance };
        apiRequest('cash_registers.php', 'PUT', payload).then(res => {
          if (res.success) {
            alert('Caja actualizada');
            loadData();
          } else {
            alert(res.error || 'Error al actualizar');
          }
        });
      });
    });
    container.querySelectorAll('button[data-action="save-bank"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const name = container.querySelector(`input[data-bank-id="${id}"][data-field="name"]`).value;
        const bankName = container.querySelector(`input[data-bank-id="${id}"][data-field="bank_name"]`).value;
        const accNum = container.querySelector(`input[data-bank-id="${id}"][data-field="account_number"]`).value;
        const balance = parseFloat(container.querySelector(`input[data-bank-id="${id}"][data-field="balance"]`).value) || 0;
        const payload = { id, name, bank_name: bankName, account_number: accNum, balance };
        apiRequest('bank_accounts.php', 'PUT', payload).then(res => {
          if (res.success) {
            alert('Cuenta bancaria actualizada');
            loadData();
          } else {
            alert(res.error || 'Error al actualizar');
          }
        });
      });
    });
    // Agregar nueva caja
    const btnAddCr = document.getElementById('btnAddCr');
    btnAddCr.addEventListener('click', () => {
      const name = document.getElementById('newCrName').value.trim();
      const initial = parseFloat(document.getElementById('newCrInitial').value) || 0;
      if (!name) {
        alert('Nombre requerido');
        return;
      }
      apiRequest('cash_registers.php', 'POST', { name, initial_balance: initial }).then(res => {
        if (res.success) {
          alert('Nueva caja creada');
          loadData();
        } else {
          alert(res.error || 'Error al crear');
        }
      });
    });
    // Agregar nueva cuenta bancaria
    const btnAddBank = document.getElementById('btnAddBank');
    btnAddBank.addEventListener('click', () => {
      const name = document.getElementById('newBankName').value.trim();
      const bankName = document.getElementById('newBankBank').value.trim();
      const accNum = document.getElementById('newBankAccountNumber').value.trim();
      const balance = parseFloat(document.getElementById('newBankBalance').value) || 0;
      if (!name) {
        alert('Nombre requerido');
        return;
      }
      apiRequest('bank_accounts.php', 'POST', { name, bank_name: bankName, account_number: accNum, balance }).then(res => {
        if (res.success) {
          alert('Nueva cuenta bancaria creada');
          loadData();
        } else {
          alert(res.error || 'Error al crear');
        }
      });
    });
  }

  loadData();
});