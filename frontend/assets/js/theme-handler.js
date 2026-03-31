// Sistema global de temas
(function() {
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  const htmlElement = document.documentElement;
  
  // Cargar tema guardado
  const savedTheme = localStorage.getItem('theme') || 'light';
  if (savedTheme === 'dark') {
    htmlElement.setAttribute('data-bs-theme', 'dark');
    if (themeToggleBtn) themeToggleBtn.textContent = 'Modo claro';
  }
  
  // Cambiar tema
  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', function() {
      const currentTheme = htmlElement.getAttribute('data-bs-theme') || 'light';
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      
      htmlElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      themeToggleBtn.textContent = newTheme === 'dark' ? 'Modo claro' : 'Modo oscuro';
    });
  }
})();
