document.addEventListener('DOMContentLoaded', () => {
  // Seguridad
  requireAuth();
  populateNav();

  const tbody = document.querySelector('#operationTable tbody');
  const searchInput = document.getElementById('searchInput');
  const suggestionsList = document.getElementById('barcodeList');
  const printBtn = document.getElementById('btnPrintReport');

  let operations = [];

  // Autocompletado
  apiRequest('products.php')
    .then(products => {
      (products || []).forEach(p => {
        const addOption = (value) => {
          if (value === null || value === undefined || value === '') return;
          const opt = document.createElement('option');
          opt.value = String(value);
          suggestionsList.appendChild(opt);
        };
        addOption(p.barcode);
        addOption(p.code);
        addOption(p.name);
        addOption(p.id);
      });
    })
    .catch(err => console.error('Error al cargar productos:', err));

  // Cargar reporte al entrar
  apiRequest('purchase_report.php')
    .then(data => {
      operations = Array.isArray(data) ? data : [];
      renderTable(operations);
    })
    .catch(err => {
      console.error('Error al cargar reporte:', err);
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center text-danger">
            No se pudo cargar el reporte
          </td>
        </tr>
      `;
    });

  function renderTable(list) {
    tbody.innerHTML = '';
    if (!list || !list.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center">Sin registros</td>
        </tr>
      `;
      return;
    }

    list.forEach(item => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${item.operation_date ? new Date(item.operation_date).toLocaleString() : ''}</td>
        <td>${item.user_name || ''}</td>
        <td>${item.product_id || ''}</td>
        <td>${item.barcode || ''}</td>
        <td>${item.product_name || ''}</td>
        <td>${item.quantity || ''}</td>
        <td>$${Number(item.purchase_price || 0).toFixed(2)}</td>
        <td>$${Number(item.public_price_at_purchase ?? item.public_price ?? 0).toFixed(2)}</td>
        <td>${item.supplier_name || ''}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  // Filtro en memoria
  searchInput.addEventListener('input', () => {
    const val = searchInput.value.trim().toLowerCase();
    if (!val) {
      renderTable(operations);
      return;
    }
    const filtered = operations.filter(op => {
      const barcode = String(op.barcode || '').toLowerCase();
      const code = String(op.code || '').toLowerCase();
      const name = String(op.product_name || '').toLowerCase();
      const idStr = String(op.product_id || '').toLowerCase();
      return barcode.includes(val) || code.includes(val) || name.includes(val) || idStr.includes(val);
    });
    renderTable(filtered);
  });

  // PDF of purchases
  if (printBtn) {
    printBtn.addEventListener('click', () => {
      const filterVal = searchInput.value.trim();
      const endpoint = filterVal
        ? `purchase_report_pdf.php?search=${encodeURIComponent(filterVal)}`
        : 'purchase_report_pdf.php';

      apiRequest(endpoint)
        .then(res => {
          if (res && res.success && res.file) {
            window.open(`../backend/${res.file}`, '_blank');
          } else {
            alert((res && (res.error || res.message)) || 'No se pudo generar el PDF');
          }
        })
        .catch(err => {
          console.error('Error PDF:', err);
          alert(err.message || 'Error al generar PDF. Revisa consola.');
        });
    });
  }

  // Accounting daily report (modal)
  const btnAccDaily = document.getElementById('btnAccountingDailyReport');
  const accModalEl = document.getElementById('accountingDailyModal');
  const accModal = accModalEl ? new bootstrap.Modal(accModalEl) : null;
  const reportDateInput = document.getElementById('reportDate');
  if (reportDateInput) {
    reportDateInput.value = new Date().toISOString().slice(0,10);
  }

  if (btnAccDaily && accModal) {
    btnAccDaily.addEventListener('click', () => accModal.show());
  }

  const btnGenerateAccountingDaily = document.getElementById('btnGenerateAccountingDaily');
  if (btnGenerateAccountingDaily) {
    btnGenerateAccountingDaily.addEventListener('click', async () => {
      const d = reportDateInput.value || new Date().toISOString().slice(0,10);
      btnGenerateAccountingDaily.disabled = true;
      btnGenerateAccountingDaily.textContent = 'Generando...';
      try {
        const res = await fetch('../backend/api/report_accounting_daily_pdf.php', {
          method: 'POST', credentials: 'include', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ date: d })
        });
        const json = await res.json();
        if (json && json.ok && json.file) {
          window.open('../backend/' + json.file, '_blank');
          accModal.hide();
        } else {
          alert((json && (json.error || json.message)) || 'Error generando reporte');
        }
      } catch (err) {
        console.error('Error generando reporte contable:', err);
        alert(err.message || 'Error al generar reporte');
      }
      btnGenerateAccountingDaily.disabled = false;
      btnGenerateAccountingDaily.textContent = 'Generar PDF';
    });
  }
});
