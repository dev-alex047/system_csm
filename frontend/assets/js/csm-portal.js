(() => {
  const modules = {
    ventas: {
      title: 'Ventas', icon: 'bi-receipt-cutoff',
      description: 'Cotizaciones, operaciones de venta y atención después de la compra.',
      items: [
        { title: 'Nueva venta', icon: 'bi-cart-check', href: 'sales.html', description: 'Registrar productos y cobrar una venta.' },
        { title: 'Cotizaciones', icon: 'bi-file-earmark-text', href: 'quotes.html', description: 'Preparar cotizaciones y generar su PDF.' },
        { title: 'Devoluciones y cambios', icon: 'bi-arrow-repeat', href: 'sales.html', description: 'Disponible dentro de la pantalla de Ventas.' },
        { title: 'Pedidos', icon: 'bi-bag-check', description: 'Gestión de pedidos por desarrollar.' }
      ]
    },
    compras: {
      title: 'Compras', icon: 'bi-bag',
      description: 'Proveedores, adquisición y recepción de mercancía.',
      items: [
        { title: 'Proveedores', icon: 'bi-truck', href: 'suppliers.html', description: 'Consultar, registrar y administrar proveedores.' },
        { title: 'Entrada de mercancía', icon: 'bi-box-arrow-in-down', href: 'suppliers.html', description: 'Iniciar desde un proveedor; la captura actual requiere su ID.' },
        { title: 'Reporte de compras', icon: 'bi-clipboard-data', href: 'reports.html', description: 'Consultar las operaciones de compra registradas.' },
        { title: 'Órdenes de compra', icon: 'bi-file-text', description: 'Solicitudes y seguimiento de compra por desarrollar.' }
      ]
    },
    almacen: {
      title: 'Almacén', icon: 'bi-box-seam',
      description: 'Productos, existencias y control de movimientos.',
      items: [
        { title: 'Productos e inventario', icon: 'bi-boxes', href: 'products.html', description: 'Catálogo de productos, precios y existencias.' },
        { title: 'Actualización de precios', icon: 'bi-tags', href: 'products.html', description: 'Disponible dentro de la pantalla de Productos.' },
        { title: 'Movimientos de almacén', icon: 'bi-arrow-left-right', description: 'Entradas, salidas y ajustes por desarrollar.' },
        { title: 'Traspasos y mermas', icon: 'bi-arrow-down-up', description: 'Control entre ubicaciones por desarrollar.' }
      ]
    },
    finanzas: {
      title: 'Finanzas', icon: 'bi-wallet2', adminOnly: true,
      description: 'Control de efectivo, bancos, pagos, cobros y cortes.',
      items: [
        { title: 'Cajas y bancos', icon: 'bi-bank', href: 'accounting_banks.html', description: 'Administrar las cuentas de efectivo y bancos.' },
        { title: 'Cuentas por cobrar', icon: 'bi-cash-coin', href: 'accounting_receivables.html', description: 'Consultar adeudos y registrar abonos.' },
        { title: 'Cuentas por pagar', icon: 'bi-credit-card', href: 'accounting_payables.html', description: 'Administrar compromisos con proveedores.' },
        { title: 'Cortes de caja', icon: 'bi-calculator', href: 'accounting.html', description: 'Disponible dentro del módulo actual de Contabilidad.' },
        { title: 'Combustible', icon: 'bi-fuel-pump', href: 'fuel.html', description: 'Control actual de entradas, salidas y vehículos.' },
        { title: 'Reportes financieros', icon: 'bi-graph-up-arrow', href: 'accounting.html', description: 'Disponibles en la pantalla contable actual.' }
      ]
    },
    contabilidad: {
      title: 'Contabilidad', icon: 'bi-journal-text', adminOnly: true,
      description: 'Registro contable, activos y análisis financiero.',
      items: [
        { title: 'Contabilidad general', icon: 'bi-journal-check', href: 'accounting.html', description: 'Acceder a los movimientos y herramientas actuales.' },
        { title: 'Resumen general', icon: 'bi-pie-chart', href: 'accounting.html', description: 'Disponible en la pantalla contable actual.' },
        { title: 'Activos fijos', icon: 'bi-building', href: 'accounting.html', description: 'Disponible en la pantalla contable actual.' },
        { title: 'Nóminas', icon: 'bi-person-badge', description: 'Administración de nóminas por desarrollar.' }
      ]
    }
  };
  document.addEventListener('DOMContentLoaded', () => {
    let user;
    try {
      user = (typeof requireAuth === 'function') ? requireAuth() : null;
    } catch (error) {
      localStorage.removeItem('user');
      location.replace('login.html');
      return;
    }
    if (!user) return;
    const isAdmin = String(user.role || '').toUpperCase() === 'ADMIN';
    const page = document.body.dataset.csmPage;
    const moduleKey = new URLSearchParams(location.search).get('modulo');
    const section = page === 'module' ? modules[moduleKey] : null;
    if (page === 'module' && (moduleKey === 'ventas' || moduleKey === 'almacen')) {
      location.replace(moduleKey === 'ventas' ? 'sales.html' : 'products.html');
      return;
    }
    if (page === 'module' && (!section || (section.adminOnly && !isAdmin))) {
      location.replace('dashboard.html');
      return;
    }
    renderHeader((page === 'sales' || page === 'quotes') ? 'Ventas' : (page === 'products' ? 'Almacén' : (section ? section.title : 'Sistema CSM')), user, isAdmin, page);
    if (page === 'dashboard') {
      document.title = 'Inicio | Sistema CSM';
      const grid = document.getElementById('csmModuleGrid');
      Object.entries(modules).forEach(([key, item]) => {
        if (item.adminOnly && !isAdmin) return;
        const col = document.createElement('div');
        col.className = 'col-6 col-md-6 col-lg-4';
        col.innerHTML = `
          <a class="csm-tile" href="${key === 'ventas' ? 'sales.html' : (key === 'almacen' ? 'products.html' : `modulo.html?modulo=${key}`)}">
            <span class="csm-tile-icon"><i class="bi ${item.icon}" aria-hidden="true"></i></span>
            <h2 class="csm-tile-title">${item.title}</h2>
            <span class="csm-tile-desc">${item.description}</span>
          </a>`;
        grid.appendChild(col);
      });
    }
    // Navegación de Almacén: se renderiza dentro del mismo encabezado que Ventas.
    if (page === 'products') {
      document.title = 'Productos e inventario | Sistema CSM';
      const nav = document.getElementById('csmSubnav');
      if (nav) nav.innerHTML = `
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="products.html"><i class="bi bi-boxes" aria-hidden="true"></i>Productos e inventario</a></li>
        <li class="nav-item"><button type="button" class="nav-link" id="csmOpenPrices"><i class="bi bi-tags" aria-hidden="true"></i>Actualizar precios</button></li>
        <li class="nav-item"><button type="button" class="nav-link" disabled title="Próximamente"><i class="bi bi-arrow-left-right" aria-hidden="true"></i>Movimientos</button></li>
        <li class="nav-item"><button type="button" class="nav-link" disabled title="Próximamente"><i class="bi bi-arrow-down-up" aria-hidden="true"></i>Traspasos y mermas</button></li>`;
      const prices = document.getElementById('csmOpenPrices');
      if (prices) {
        if (!isAdmin) prices.disabled = true;
        prices.addEventListener('click', () => document.getElementById('btnQuickPrice')?.click());
      }
    }
    // Navegación de Ventas COMPARTIDA: en el header, nunca debajo.
    // Es la misma en Ventas, Cotizaciones y futuros submódulos.
    if (page === 'sales' || page === 'quotes') {
      document.title = `${page === 'sales' ? 'Ventas' : 'Cotizaciones'} | Sistema CSM`;
      const nav = document.getElementById('csmSubnav');
      if (nav) {
        nav.innerHTML = `
          <li class="nav-item"><a class="nav-link ${page === 'sales' ? 'active' : ''}" ${page === 'sales' ? 'aria-current="page"' : ''} href="sales.html"><i class="bi bi-cart-check" aria-hidden="true"></i>Nueva venta</a></li>
          <li class="nav-item"><a class="nav-link ${page === 'quotes' ? 'active' : ''}" ${page === 'quotes' ? 'aria-current="page"' : ''} href="quotes.html"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Cotizaciones</a></li>
          <li class="nav-item"><button class="nav-link" type="button" id="csmOpenReturn"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Devoluciones y cambios</button></li>
          <li class="nav-item"><button class="nav-link" type="button" disabled title="Módulo por desarrollar"><i class="bi bi-bag-check" aria-hidden="true"></i>Pedidos</button></li>`;
        document.getElementById('csmOpenReturn').addEventListener('click', () => {
          if (page === 'sales') {
            document.getElementById('btnReturn')?.click();
          } else {
            location.assign('sales.html?devoluciones=1');
          }
        });
      }
      // Desde Cotizaciones abrir el modal original de Ventas tras cargar sales.js.
      if (page === 'sales' && new URLSearchParams(location.search).get('devoluciones') === '1') {
        window.addEventListener('load', () => {
          document.getElementById('btnReturn')?.click();
          const cleanUrl = new URL(location.href);
          cleanUrl.searchParams.delete('devoluciones');
          history.replaceState(null, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
        }, { once: true });
      }
    }
    if (page === 'module') {
      document.title = `${section.title} | Sistema CSM`;
      document.getElementById('csmModuleTitle').textContent = section.title;
      document.getElementById('csmModuleDescription').textContent = section.description;
      const pills = document.getElementById('csmSubnav');
      const cards = document.getElementById('csmSubmoduleGrid');
      pills.innerHTML = '<li class="nav-item"><a class="nav-link active" href="#mainContent" aria-current="page"><i class="bi bi-grid-1x2" aria-hidden="true"></i>Resumen</a></li>';
      section.items.forEach(item => {
        const li = document.createElement('li');
        li.className = 'nav-item';
        li.innerHTML = item.href
          ? `<a class="nav-link" href="${item.href}"><i class="bi ${item.icon}" aria-hidden="true"></i>${item.title}</a>`
          : `<button class="nav-link" type="button" disabled title="Próximamente"><i class="bi ${item.icon}" aria-hidden="true"></i>${item.title}</button>`;
        pills.appendChild(li);
        const col = document.createElement('div');
        col.className = 'col-12 col-md-6 col-lg-4';
        const tag = item.href ? 'a' : 'div';
        const href = item.href ? ` href="${item.href}"` : '';
        col.innerHTML = `
          <${tag} class="csm-subtile ${item.href ? '' : 'csm-disabled'}"${href}>
            <span class="csm-tile-icon"><i class="bi ${item.icon}" aria-hidden="true"></i></span>
            <h2 class="csm-tile-title">${item.title}</h2>
            <span class="csm-tile-desc">${item.description}</span>
            ${item.href ? '' : '<span class="csm-coming">Próximamente</span>'}
          </${tag}>`;
        cards.appendChild(col);
      });
    }
  });

  function renderHeader(title, user, isAdmin, page) {
    const header = document.getElementById('csmHeader');
    const navigationInHeader = page === 'sales' || page === 'quotes' || page === 'products' || page === 'module';
    header.className = navigationInHeader ? 'csm-header csm-header-with-nav' : 'csm-header';
    // En el dashboard, se conserva el nombre del sistema.
    // Dentro de un módulo, la navegación REEMPLAZA el nombre/ícono central.
    const headerCenter = navigationInHeader
      ? `<nav class="csm-subnav csm-subnav-in-header" aria-label="Navegación de ${title}">
           <ul class="nav nav-pills flex-nowrap" id="csmSubnav"></ul>
         </nav>`
     : `<div class="csm-header-brand">
     <img src="../backend/uploads/logo.png"
          class="csm-header-logo"
          alt="Logo del sistema">
     <span>${title}</span>
   </div>`;
    header.innerHTML = `
      <div class="csm-header-action">
        <a class="csm-icon-btn" href="dashboard.html" aria-label="Volver al inicio" title="Inicio">
          <i class="bi bi-house-door" aria-hidden="true"></i>
        </a>
      </div>
      ${headerCenter}
      <div class="csm-header-menu dropdown">
        <button class="csm-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
          aria-label="Abrir opciones" title="Opciones">
          <i class="bi bi-gear" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end csm-settings-menu">
          <span class="csm-menu-user" id="csmCurrentUser"></span>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item csm-admin-only" href="settings.html"><i class="bi bi-sliders" aria-hidden="true"></i>Configuración</a>
          <a class="dropdown-item csm-admin-only" href="users.html"><i class="bi bi-people" aria-hidden="true"></i>Usuarios</a>
         <button class="dropdown-item csm-admin-only" type="button" id="csmOpenCommissions"><i class="bi bi-percent" aria-hidden="true"></i>Comisiones</button>          <button class="dropdown-item" type="button" id="csmThemeBtn"><i class="bi bi-moon-stars" aria-hidden="true"></i><span id="csmThemeText">Modo oscuro</span></button>
          <div class="dropdown-divider"></div>
          <button class="dropdown-item" type="button" id="csmLogoutBtn"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Cerrar sesión</button>
        </div>
      </div>`;
    document.getElementById('csmCurrentUser').textContent =
      `${user.username || 'Usuario'} · ${String(user.role || 'USUARIO').toUpperCase()}`;
    if (!isAdmin) {
      header.querySelectorAll('.csm-admin-only').forEach(el => el.remove());
    }

        // Abrir el módulo de Comisiones dentro de un modal
    const btnComisiones = document.getElementById('csmOpenCommissions');

    if (btnComisiones && page === 'commissions') {
        btnComisiones.remove();
    } else if (btnComisiones) {
        btnComisiones.addEventListener('click', () => {
            let modalElement = document.getElementById('csmCommissionsModal');

            if (!modalElement) {
                document.body.insertAdjacentHTML('beforeend', `
                    <div class="modal fade csm-commissions-window" id="csmCommissionsModal" tabindex="-1" aria-labelledby="csmCommissionsModalTitle" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="csmCommissionsModalTitle">
                                        <i class="bi bi-percent me-2"></i>Comisiones
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body p-0">
                                    <iframe title="Administración de tarifas de pago"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                modalElement = document.getElementById('csmCommissionsModal');
            }

            const iframe = modalElement.querySelector('iframe');

            if (!iframe.getAttribute('src')) {
                iframe.src = 'comisiones.html?modal=1';
            }

            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    }
    const themeBtn = document.getElementById('csmThemeBtn');
    const updateThemeText = () => {
      const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      document.getElementById('csmThemeText').textContent = dark ? 'Modo claro' : 'Modo oscuro';
      themeBtn.querySelector('i').className = `bi ${dark ? 'bi-sun' : 'bi-moon-stars'}`;
    };
    updateThemeText();
    themeBtn.addEventListener('click', () => {
      const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      const next = dark ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem('theme', next);
      updateThemeText();
    });
    document.getElementById('csmLogoutBtn').addEventListener('click', () => {
      if (typeof logout === 'function') logout();
    });
  }
})();
