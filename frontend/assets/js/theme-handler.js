// Inicialización global del botón de tema
(function() {
  function iniciarThemeHandler() {
    const btn = document.getElementById('themeToggleBtn');

    // En login no existe el botón, por eso no hace nada
    if (!btn) {
      return;
    }

    const root = document.documentElement;
    const savedTheme = localStorage.getItem('theme');
    const currentTheme = savedTheme || root.getAttribute('data-bs-theme') || 'light';

    root.setAttribute('data-bs-theme', currentTheme);

    if (typeof updateThemeToggleLabel === 'function') {
      updateThemeToggleLabel(currentTheme);
    } else {
      btn.textContent = currentTheme === 'dark' ? 'Modo claro' : 'Modo oscuro';
    }

    if (typeof applyTheme === 'function') {
      applyTheme();
    }

    if (typeof toggleTheme === 'function') {
      // Quita el evento anterior y deja solamente uno
      btn.removeEventListener('click', toggleTheme);
      btn.addEventListener('click', toggleTheme);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarThemeHandler);
  } else {
    iniciarThemeHandler();
  }
})();