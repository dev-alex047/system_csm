// Main JS file for frontend
const API_BASE = '../backend/api';

function apiRequest(url, method = 'GET', data = null) {
  const options = { method, headers: {} };
  if (data) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(data);
  }
  // IMPORTANTE: incluir cookies de sesión; si no, se "caduca" el login en varias páginas.
  options.credentials = 'same-origin';

  return fetch(`${API_BASE}/${url}`, options).then(async (resp) => {
    if (resp.status === 401) {
      // Sesión backend no válida -> re-login
      localStorage.removeItem('user');
      window.location.href = 'login.html';
      throw new Error('401 Unauthorized');
    }

    if (!resp.ok) {
      const text = await resp.text();
      try {
        const err = JSON.parse(text);
        return Promise.reject(err);
      } catch {
        return Promise.reject({ error: text || `HTTP ${resp.status}` });
      }
    }
    return resp.json();
  });
}

function checkAuth() {
  // Check if user info exists in localStorage
  const user = localStorage.getItem('user');
  return user ? JSON.parse(user) : null;
}

function requireAuth(redirect = 'login.html') {
  const user = checkAuth();
  if (!user) {
    window.location.href = redirect;
  }
  return user;
}

function logout() {
  apiRequest('logout.php').finally(() => {
    localStorage.removeItem('user');
    window.location.href = 'login.html';
  });
}

function populateNav() {
  const user = checkAuth();
  const nav = document.getElementById('nav-user');
  if (nav && user) {
    nav.textContent = `${user.username} (${String(user.role).toUpperCase()})`;
  }
  // After updating nav user text, toggle admin-specific links
  toggleAdminNav();
  // Aplicar tema y colores guardados
  applyTheme();
  // Inicializar tema (oscuro/claro) y actualizar botón
  initTheme();
}

/**
 * Inicializa el tema claro/oscuro leyendo la preferencia de localStorage.
 * Actualiza el atributo data-bs-theme y la etiqueta del botón.
 */
function initTheme() {
  const saved = localStorage.getItem('theme');
  const root = document.documentElement;
  const current = saved || root.getAttribute('data-bs-theme') || 'light';
  root.setAttribute('data-bs-theme', current);
  updateThemeToggleLabel(current);
  applyTheme();
  
  const btn = document.getElementById('themeToggleBtn');
  if (btn) {
    btn.addEventListener('click', toggleTheme);
  }
  
  // Escuchar cambios de tema con MutationObserver
  const observer = new MutationObserver(() => {
    setTimeout(applyTheme, 50);
  });
  
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-bs-theme'],
    attributeOldValue: true
  });
}

/**
 * Cambia entre los modos claro y oscuro.
 */
function toggleTheme() {
  const root = document.documentElement;
  const current = root.getAttribute('data-bs-theme') || 'light';
  const next = current === 'light' ? 'dark' : 'light';
  root.setAttribute('data-bs-theme', next);
  localStorage.setItem('theme', next);
  updateThemeToggleLabel(next);
  // Aplicar tema inmediatamente
  applyTheme();
}

/**
 * Actualiza la etiqueta del botón de tema dependiendo del modo actual.
 */
function updateThemeToggleLabel(theme) {
  const btn = document.getElementById('themeToggleBtn');
  if (!btn) return;
  if (theme === 'dark') {
    btn.textContent = 'Modo claro';
  } else {
    btn.textContent = 'Modo oscuro';
  }
}

/**
 * Fetch customization settings from the backend and apply the primary and secondary
 * colors to the UI. También aplica dark mode completo a todos los elementos.
 */
function applyTheme() {
  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  
  // Aplicar a elementos globales
  const body = document.body;
  const navbars = document.querySelectorAll('nav.navbar');
  const cards = document.querySelectorAll('.card');
  const tables = document.querySelectorAll('.table');
  const inputs = document.querySelectorAll('input, textarea, select, .form-control, .form-select');
  const modals = document.querySelectorAll('.modal-content');
  
  if (isDark) {
    // Dark mode - Colores empresa
    body.style.backgroundColor = '#1a1a1a';
    body.style.color = '#e9ecef';
    
    navbars.forEach(nav => {
      nav.style.background = 'linear-gradient(135deg, #1a8f5e 0%, #0d6a47 100%)';
    });
    
    cards.forEach(card => {
      card.style.backgroundColor = '#2d2d2d';
      card.style.borderColor = '#495057';
      card.style.color = '#e9ecef';
    });
    
    tables.forEach(table => {
      table.style.color = '#e9ecef';
      table.style.borderColor = '#495057';
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
      modal.style.borderColor = '#495057';
    });
    
  } else {
    // Light mode
    body.style.backgroundColor = '#f8f9fa';
    body.style.color = '#000';
    
    navbars.forEach(nav => {
      nav.style.background = 'linear-gradient(135deg, #1a8f5e 0%, #0d6a47 100%)';
    });
    
    cards.forEach(card => {
      card.style.backgroundColor = '#fff';
      card.style.borderColor = '#dee2e6';
      card.style.color = '#000';
    });
    
    tables.forEach(table => {
      table.style.color = '#000';
      table.style.borderColor = '#dee2e6';
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
      modal.style.borderColor = '#dee2e6';
    });
  }
  
  // Fetch settings asynchronously; ignore errors
  apiRequest('settings.php')
    .then(settings => {
      // Guardar los colores en variables CSS para su uso global
      if (settings.primary_color) {
        document.documentElement.style.setProperty('--primary-color', settings.primary_color);
      }
      if (settings.secondary_color) {
        document.documentElement.style.setProperty('--secondary-color', settings.secondary_color);
      }
      // Aplicar tamaño de letra global si está definido
      if (settings.font_size) {
        document.documentElement.style.fontSize = settings.font_size;
      }
    })
    .catch(() => { /* ignore errors */ });
}

/**
 * Show or hide admin-specific navigation links based on the current user's role.
 * If the logged-in user has the ADMIN role, the Users link will be displayed.
 * Otherwise it will remain hidden. This function should be called on page load
 * after populateNav().
 */
function toggleAdminNav() {
  const user = checkAuth();
  const usersLink = document.getElementById('nav-users');
  if (usersLink) {
    if (user && user.role && user.role.toUpperCase() === 'ADMIN') {
      usersLink.style.display = '';
    } else {
      usersLink.style.display = 'none';
    }
  }
  // Also toggle the users tile on dashboard
  const usersTile = document.getElementById('tile-users');
  if (usersTile) {
    if (user && user.role && user.role.toUpperCase() === 'ADMIN') {
      usersTile.style.display = '';
    } else {
      usersTile.style.display = 'none';
    }
  }

  // Mostrar u ocultar azulejo de contabilidad
  const accountingTile = document.getElementById('tile-accounting');
  if (accountingTile) {
    if (user && user.role && user.role.toUpperCase() === 'ADMIN') {
      accountingTile.style.display = '';
    } else {
      accountingTile.style.display = 'none';
    }
  }

  // También mostrar u ocultar el enlace de contabilidad en la barra de navegación.
  const accountingNav = document.getElementById('nav-accounting');
  if (accountingNav) {
    if (user && user.role && user.role.toUpperCase() === 'ADMIN') {
      accountingNav.style.display = '';
    } else {
      accountingNav.style.display = 'none';
    }
  }
}