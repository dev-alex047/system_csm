document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();
  if (user.role && user.role.toUpperCase() !== 'ADMIN') {
    alert('No tienes permisos para modificar ajustes');
    window.location.href = 'dashboard.html';
    return;
  }
  const companyName = document.getElementById('companyName');
  const primaryColor = document.getElementById('primaryColor');
  const secondaryColor = document.getElementById('secondaryColor');
  // Nuevos campos: tamaño de letra y modo oscuro automático
  const fontSizeSelect = document.getElementById('fontSize');
  const darkModeCheckbox = document.getElementById('darkModeAuto');
  const settingsForm = document.getElementById('settingsForm');
  const logoPreview = document.getElementById('logoPreview');
  const logoInput = document.getElementById('logoInput');
  const btnUploadLogo = document.getElementById('btnUploadLogo');
  function loadSettings() {
    apiRequest('settings.php').then(data => {
      companyName.value = data.company_name;
      primaryColor.value = data.primary_color;
      secondaryColor.value = data.secondary_color;
      // Cargar tamaño de letra y modo oscuro automático
      if (data.font_size) {
        // Asignar valor si coincide con una opción del select; de lo contrario dejar por defecto
        const option = Array.from(fontSizeSelect.options).find(opt => opt.value === data.font_size);
        if (option) fontSizeSelect.value = option.value;
      }
      if (typeof data.dark_mode_auto !== 'undefined') {
        darkModeCheckbox.checked = data.dark_mode_auto ? true : false;
      }
      if (data.logo_path) {
        logoPreview.src = '../backend/' + data.logo_path;
      }
      // Apply colors to previews
      updatePreviews(data.company_name, data.primary_color, data.secondary_color);
    });
  }
  loadSettings();
  settingsForm.addEventListener('submit', e => {
    e.preventDefault();
    const payload = {
      company_name: companyName.value,
      primary_color: primaryColor.value,
      secondary_color: secondaryColor.value,
      font_size: fontSizeSelect.value,
      dark_mode_auto: darkModeCheckbox.checked
    };
    apiRequest('settings.php', 'POST', payload).then(() => alert('Configuración guardada'));
    // Update previews after saving
    updatePreviews(companyName.value, primaryColor.value, secondaryColor.value);
  });
  btnUploadLogo.addEventListener('click', () => {
    const file = logoInput.files[0];
    if (!file) {
      alert('Selecciona una imagen');
      return;
    }
    const formData = new FormData();
    formData.append('logo', file);
    fetch(`${API_BASE}/upload_logo.php`, {
      method: 'POST',
      body: formData
    }).then(resp => resp.json()).then(result => {
      if (result.success) {
        logoPreview.src = '../backend/' + result.logo_path;
        alert('Logo actualizado');
      } else {
        alert(result.error || 'Error al subir el logo');
      }
    });
  });

  /**
   * Update preview cards with current company name and colors
   */
  function updatePreviews(name, primary, secondary) {
    // Default fallback values
    const compName = name || 'Sistema CSM';
    const primaryColorVal = primary || '#0d6efd';
    const secondaryColorVal = secondary || '#6c757d';
    // Page preview
    const prevPage = document.getElementById('previewPage');
    if (prevPage) {
      const header = prevPage.querySelector('div:first-child');
      const body = prevPage.querySelector('div:nth-child(2)');
      header.style.backgroundColor = primaryColorVal;
      header.textContent = compName;
      body.style.backgroundColor = secondaryColorVal;
    }
    // Ticket preview
    const prevTicket = document.getElementById('previewTicket');
    if (prevTicket) {
      const header = prevTicket.querySelector('div:first-child');
      const body = prevTicket.querySelector('div:nth-child(2)');
      header.style.backgroundColor = primaryColorVal;
      body.style.backgroundColor = secondaryColorVal;
    }
    // Quote preview
    const prevQuote = document.getElementById('previewQuote');
    if (prevQuote) {
      const header = prevQuote.querySelector('div:first-child');
      const body = prevQuote.querySelector('div:nth-child(2)');
      header.style.backgroundColor = primaryColorVal;
      body.style.backgroundColor = secondaryColorVal;
    }
  }
});