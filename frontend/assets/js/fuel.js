document.addEventListener('DOMContentLoaded', async () => {
  try {
    requireAuth();
    populateNav();
  } catch (e) {
    console.error('Error de autenticación:', e);
    return;
  }

  const fuelTypes = [];
  const vehicles = [];
  let movements = [];

  // ===== Helpers =====
  const fmt = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const escapeHtml = (s) => {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  };

  // ===== Load Data =====
  async function loadFuelTypes() {
    try {
      const res = await apiRequest('fuel.php?action=types');
      if (Array.isArray(res)) return res;
      return res.data || res.types || [];
    } catch (e) {
      console.error('Error loading fuel types:', e);
      return [];
    }
  }

  async function loadVehicles() {
    try {
      const res = await apiRequest('fuel.php?action=vehicles');
      if (Array.isArray(res)) return res;
      return res.data || res.vehicles || [];
    } catch (e) {
      console.error('Error loading vehicles:', e);
      return [];
    }
  }

  async function loadMovements() {
    try {
      const res = await apiRequest('fuel.php?action=movements');
      if (Array.isArray(res)) return res;
      return res.data || res.movements || [];
    } catch (e) {
      console.error('Error loading movements:', e);
      return [];
    }
  }

  async function loadStock() {
    try {
      const res = await apiRequest('fuel.php?action=stock');
      if (Array.isArray(res)) return res;
      return res.data || res.stock || [];
    } catch (e) {
      console.error('Error loading stock:', e);
      return [];
    }
  }

  // ===== Populate selects =====
  function fillFuelTypeSelect(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    select.innerHTML = '<option value="">Seleccionar...</option>';
    fuelTypes.forEach(ft => {
      const opt = document.createElement('option');
      opt.value = ft.id || ft.type_id;
      opt.textContent = (ft.name || ft.type_name || '').toString().replace(/_/g, ' ');
      select.appendChild(opt);
    });
  }

  function fillVehicleSelect(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    select.innerHTML = '<option value="">Seleccionar...</option>';
    vehicles.forEach(v => {
      const opt = document.createElement('option');
      opt.value = v.id;
      opt.textContent = `${v.plate || ''} - ${v.model || ''}`;
      select.appendChild(opt);
    });
  }

  // ===== Render movements =====
  function renderMovements() {
    const tbody = document.getElementById('movementsTable');
    if (!tbody) return;
    
    if (!movements || movements.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Sin movimientos</td></tr>';
      return;
    }

    tbody.innerHTML = movements
      .sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0))
      .map(m => {
        const type = m.movement_type === 'ENTRADA' ? '<span class="badge bg-success">Entrada</span>' : '<span class="badge bg-danger">Salida</span>';
        const details = m.destination ? `${m.destination}` : (m.supplier || '');
        const total = (Number(m.liters || 0) * Number(m.unit_price || 0)).toFixed(2);
        
        return `
          <tr>
            <td>${m.date || ''}</td>
            <td>${type}</td>
            <td>${escapeHtml(m.fuel_type_name || m.fuel_type || '')}</td>
            <td class="text-end">${fmt(m.liters || 0)}</td>
            <td class="text-end">$ ${fmt(m.unit_price || 0)}</td>
            <td class="text-end">$ ${fmt(total)}</td>
            <td><small>${escapeHtml(details)}</small></td>
            <td>
              <button class="btn btn-sm btn-outline-danger" onclick="deleteMovement(${m.id})">Eliminar</button>
            </td>
          </tr>
        `;
      })
      .join('');
  }

  // ===== Render vehicles =====
  function renderVehicles() {
    const tbody = document.getElementById('vehiclesTable');
    if (!tbody) return;
    
    if (!vehicles || vehicles.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Sin vehículos</td></tr>';
      return;
    }

    tbody.innerHTML = vehicles.map(v => {
      const statusBadge = {
        'ACTIVO': '<span class="badge bg-success">Activo</span>',
        'MANTENIMIENTO': '<span class="badge bg-warning">Mantenimiento</span>',
        'INACTIVO': '<span class="badge bg-secondary">Inactivo</span>'
      }[v.status] || '<span class="badge bg-secondary">Desconocido</span>';

      return `
        <tr>
          <td><strong>${escapeHtml(v.plate || '')}</strong></td>
          <td>${escapeHtml(v.model || '')}</td>
          <td>${escapeHtml(v.fuel_type || '')}</td>
          <td class="text-end">${fmt(v.tank_capacity || 0)} L</td>
          <td>${statusBadge}</td>
          <td>${v.last_refuel_date || '-'}</td>
          <td>
            <div class="action-buttons">
              <button class="btn btn-sm btn-outline-primary" onclick="editVehicle(${v.id})">Editar</button>
              <button class="btn btn-sm btn-outline-danger" onclick="deleteVehicle(${v.id})">Eliminar</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  // ===== Render stock =====
  function renderStock(stock) {
    const container = document.getElementById('stockContainer');
    if (!container) return;

    if (!stock || stock.length === 0) {
      container.innerHTML = '<div class="col text-center text-muted">Sin datos de stock</div>';
      return;
    }

    const grouped = {};
    stock.forEach(s => {
      const key = `${s.fuel_type || 'N/A'}-${s.location || 'N/A'}`;
      if (!grouped[key]) grouped[key] = s;
    });

    container.innerHTML = Object.values(grouped).map(s => `
      <div class="col-md-4">
        <div class="stat-card">
          <div style="font-weight: bold; margin-bottom: 5px;">${escapeHtml(s.fuel_type || '')}</div>
          <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">${escapeHtml(s.location || 'N/A')}</div>
          <div class="stat-value">${fmt(s.liters_total || 0)}</div>
          <div class="stat-label">Litros</div>
        </div>
      </div>
    `).join('');
  }

  // ===== Modals and Forms =====
  window.updateExitFields = function() {
    const destination = document.getElementById('exitDestination').value;
    document.getElementById('exitVehicleField').style.display = destination === 'USO' ? 'block' : 'none';
    document.getElementById('exitCounterpartyField').style.display = destination === 'CAMBIO' ? 'block' : 'none';
    document.getElementById('exitPriceField').style.display = destination === 'VENTA' ? 'block' : 'none';
  };

  // ===== Save entry =====
  document.getElementById('btnSaveEntry').addEventListener('click', async () => {
    const fuelTypeId = document.getElementById('entryFuelType').value;
    const location = document.getElementById('entryLocation').value;
    const supplier = document.getElementById('entrySupplier').value.trim();
    const jerrycans = Number(document.getElementById('entryJerrycans').value || 0);
    const litersPerGarrafa = Number(document.getElementById('entryLitersPerGarrafa').value || 0);
    const price = Number(document.getElementById('entryPrice').value || 0);
    const date = document.getElementById('entryDate').value;
    const notes = document.getElementById('entryNotes').value.trim();

    if (!fuelTypeId || !location || !supplier || !jerrycans || !litersPerGarrafa || !price || !date) {
      alert('Completa todos los campos requeridos');
      return;
    }

    const liters = jerrycans * litersPerGarrafa;

    try {
      const res = await apiRequest('fuel.php', 'POST', {
        action: 'add_movement',
        movement_type: 'ENTRADA',
        fuel_type_id: fuelTypeId,
        location,
        liters,
        unit_price: price,
        supplier,
        notes,
        date
      });

      if (res.success) {
        alert('Entrada registrada');
        document.getElementById('formEntry').reset();
        const modalEl = document.getElementById('modalEntry');
        bootstrap.Modal.getInstance(modalEl).hide();
        movements = await loadMovements();
        const stock = await loadStock();
        renderMovements();
        renderStock(stock);
      } else {
        alert(res.error || 'Error al guardar');
      }
    } catch (e) {
      alert('Error: ' + (e.error || e.message));
    }
  });

  // ===== Save exit =====
  document.getElementById('btnSaveExit').addEventListener('click', async () => {
    const fuelTypeId = document.getElementById('exitFuelType').value;
    const location = document.getElementById('exitLocation').value;
    const liters = Number(document.getElementById('exitLiters').value || 0);
    const destination = document.getElementById('exitDestination').value;
    const price = Number(document.getElementById('exitPrice').value || 0);
    const vehicleId = document.getElementById('exitVehicle').value;
    const counterparty = document.getElementById('exitCounterparty').value.trim();
    const date = document.getElementById('exitDate').value;
    const accountTarget = document.getElementById('exitAccountTarget').value;
    const notes = document.getElementById('exitNotes').value.trim();

    if (!fuelTypeId || !location || !liters || !destination || !date || !accountTarget) {
      alert('Completa todos los campos requeridos');
      return;
    }

    if (destination === 'USO' && !vehicleId) {
      alert('Selecciona un vehículo');
      return;
    }

    if (destination === 'CAMBIO' && !counterparty) {
      alert('Especifica la contraparte');
      return;
    }

    if (destination === 'VENTA' && !price) {
      alert('Especifica el precio de venta');
      return;
    }

    try {
      const res = await apiRequest('fuel.php', 'POST', {
        action: 'add_movement',
        movement_type: 'SALIDA',
        fuel_type_id: fuelTypeId,
        location,
        liters,
        unit_price: price,
        destination,
        vehicle_id: vehicleId || null,
        counterparty,
        account_target: accountTarget,
        notes,
        date
      });

      if (res.success) {
        alert('Salida registrada');
        document.getElementById('formExit').reset();
        updateExitFields();
        const modalEl = document.getElementById('modalExit');
        bootstrap.Modal.getInstance(modalEl).hide();
        movements = await loadMovements();
        const stock = await loadStock();
        renderMovements();
        renderStock(stock);
      } else {
        alert(res.error || 'Error al guardar');
      }
    } catch (e) {
      alert('Error: ' + (e.error || e.message));
    }
  });

  // ===== Save vehicle =====
  document.getElementById('btnSaveVehicle').addEventListener('click', async () => {
    const plate = document.getElementById('vehiclePlate').value.trim();
    const model = document.getElementById('vehicleModel').value.trim();
    const fuelTypeId = document.getElementById('vehicleFuelType').value;
    const capacity = Number(document.getElementById('vehicleCapacity').value || 0);
    const status = document.getElementById('vehicleStatus').value;

    if (!plate || !model || !fuelTypeId || !capacity) {
      alert('Completa todos los campos requeridos');
      return;
    }

    try {
      const res = await apiRequest('fuel.php', 'POST', {
        action: 'add_vehicle',
        plate,
        model,
        fuel_type_id: fuelTypeId,
        tank_capacity: capacity,
        status
      });

      if (res.success) {
        alert('Vehículo registrado');
        document.getElementById('formVehicle').reset();
        const modalEl = document.getElementById('modalVehicle');
        bootstrap.Modal.getInstance(modalEl).hide();
        vehicles.length = 0;
        const newVehicles = await loadVehicles();
        vehicles.push(...newVehicles);
        fillVehicleSelect('exitVehicle');
        renderVehicles();
      } else {
        alert(res.error || 'Error al guardar');
      }
    } catch (e) {
      alert('Error: ' + (e.error || e.message));
    }
  });

  // ===== Delete functions =====
  window.deleteMovement = async function(id) {
    if (!confirm('¿Eliminar este movimiento?')) return;
    try {
      const res = await apiRequest('fuel.php', 'POST', { action: 'delete_movement', id });
      if (res.success) {
        movements = await loadMovements();
        const stock = await loadStock();
        renderMovements();
        renderStock(stock);
      } else {
        alert(res.error || 'Error al eliminar');
      }
    } catch (e) {
      alert('Error: ' + (e.error || e.message));
    }
  };

  window.deleteVehicle = async function(id) {
    if (!confirm('¿Eliminar este vehículo?')) return;
    try {
      const res = await apiRequest('fuel.php', 'POST', { action: 'delete_vehicle', id });
      if (res.success) {
        vehicles.length = 0;
        const newVehicles = await loadVehicles();
        vehicles.push(...newVehicles);
        renderVehicles();
        fillVehicleSelect('exitVehicle');
      } else {
        alert(res.error || 'Error al eliminar');
      }
    } catch (e) {
      alert('Error: ' + (e.error || e.message));
    }
  };

  window.editVehicle = async function(id) {
    const vehicle = vehicles.find(v => v.id === id);
    if (!vehicle) return alert('Vehículo no encontrado');
    document.getElementById('vehiclePlate').value = vehicle.plate || '';
    document.getElementById('vehicleModel').value = vehicle.model || '';
    document.getElementById('vehicleFuelType').value = vehicle.fuel_type_id || '';
    document.getElementById('vehicleCapacity').value = vehicle.tank_capacity || 0;
    document.getElementById('vehicleStatus').value = vehicle.status || 'ACTIVO';
    // TODO: Implement edit mode with hidden ID field
    const modalEl = document.getElementById('modalVehicle');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  // ===== Initialize =====
  async function initialize() {
    // Load fuel types
    const types = await loadFuelTypes();
    fuelTypes.push(...types);
    fillFuelTypeSelect('entryFuelType');
    fillFuelTypeSelect('exitFuelType');
    fillFuelTypeSelect('vehicleFuelType');

    // Load vehicles
    const vehList = await loadVehicles();
    vehicles.push(...vehList);
    fillVehicleSelect('exitVehicle');
    renderVehicles();

    // Load movements
    movements = await loadMovements();
    renderMovements();

    // Load stock
    const stock = await loadStock();
    renderStock(stock);

    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('entryDate').value = today;
    document.getElementById('exitDate').value = today;

    // Update stats
    const totalLiters = stock.reduce((sum, s) => sum + Number(s.liters_total || 0), 0);
    document.getElementById('totalLiters').textContent = fmt(totalLiters);
    document.getElementById('totalVehicles').textContent = vehicles.length;
    
    const currentMonth = new Date().toISOString().slice(0, 7);
    const entriesThisMonth = movements.filter(m => m.movement_type === 'ENTRADA' && m.date?.startsWith(currentMonth)).length;
    const exitsThisMonth = movements.filter(m => m.movement_type === 'SALIDA' && m.date?.startsWith(currentMonth)).length;
    document.getElementById('totalEntries').textContent = entriesThisMonth;
    document.getElementById('totalSales').textContent = exitsThisMonth;
  }

  initialize().catch(e => console.error('Initialization error:', e));

  // Aplicar tema oscuro si está activo
  const applyTheme = () => {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const navbar = document.querySelector('.navbar');
    const body = document.body;
    const cards = document.querySelectorAll('.card');
    const tables = document.querySelectorAll('.table');
    const inputs = document.querySelectorAll('input, textarea, select, .form-control, .form-select');
    const modals = document.querySelectorAll('.modal-content');
    
    if (isDark) {
      // Dark mode - Negro (#1a1a1a)
      body.style.backgroundColor = '#1a1a1a';
      body.style.color = '#e9ecef';
      
      if (navbar) {
        navbar.style.background = 'linear-gradient(135deg, #1a8f5e 0%, #0d6a47 100%)'; // Verde empresa
      }
      
      cards.forEach(card => {
        card.style.backgroundColor = '#2d2d2d';
        card.style.borderColor = '#495057';
        card.style.color = '#e9ecef';
      });
      
      tables.forEach(table => {
        table.style.color = '#e9ecef';
        const thead = table.querySelector('thead');
        if (thead) {
          const ths = thead.querySelectorAll('th');
          ths.forEach(th => {
            th.style.backgroundColor = '#1a8f5e';
            th.style.color = '#ffffff';
          });
        }
      });
      
      inputs.forEach(input => {
        input.style.backgroundColor = '#2d2d2d';
        input.style.color = '#e9ecef';
        input.style.borderColor = '#495057';
      });
      
      modals.forEach(modal => {
        modal.style.backgroundColor = '#2d2d2d';
        modal.style.color = '#e9ecef';
      });
      
    } else {
      // Light mode
      body.style.backgroundColor = '#f8f9fa';
      body.style.color = '#000';
      
      if (navbar) {
        navbar.style.background = 'linear-gradient(135deg, #1a8f5e 0%, #0d6a47 100%)'; // Verde empresa
      }
      
      cards.forEach(card => {
        card.style.backgroundColor = '#fff';
        card.style.borderColor = '#dee2e6';
        card.style.color = '#000';
      });
      
      tables.forEach(table => {
        table.style.color = '#000';
        const thead = table.querySelector('thead');
        if (thead) {
          const ths = thead.querySelectorAll('th');
          ths.forEach(th => {
            th.style.backgroundColor = '#1a8f5e';
            th.style.color = '#ffffff';
          });
        }
      });
      
      inputs.forEach(input => {
        input.style.backgroundColor = '#fff';
        input.style.color = '#000';
        input.style.borderColor = '#dee2e6';
      });
      
      modals.forEach(modal => {
        modal.style.backgroundColor = '#fff';
        modal.style.color = '#000';
      });
    }
  };
  
  applyTheme();
  
  // Escuchar cambios de tema usando MutationObserver
  const observer = new MutationObserver(() => {
    setTimeout(applyTheme, 50);
  });
  
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-bs-theme'],
    attributeOldValue: true
  });
  
  // También escuchar clics en botón de tema
  document.addEventListener('click', (e) => {
    if (e.target && (e.target.id === 'themeToggleBtn' || e.target.closest('[data-theme-toggle]'))) {
      setTimeout(applyTheme, 100);
    }
  });
  
  // Observar cambios en la clase del html también
  document.addEventListener('change', () => {
    setTimeout(applyTheme, 50);
  });
});
