document.addEventListener('DOMContentLoaded', () => {
  // No requiere autenticación - accesible desde login
  const searchInput = document.getElementById('searchInput');
  const autocompleteList = document.getElementById('autocompleteList');
  const resultContainer = document.getElementById('resultContainer');
  const errorMessage = document.getElementById('errorMessage');
  // Validar que los elementos existan
  if (!searchInput || !autocompleteList || !resultContainer || !errorMessage) {
    console.error('Elementos de precio consultation no encontrados');
    return;
  }
  let allProducts = [];
  let selectedIndex = -1;
  let debounceTimer = null;
  let busquedaActual = 0;
  // Función para mostrar error
  function showError(msg) {
    if (!errorMessage) return;
    errorMessage.textContent = msg;
    errorMessage.style.display = 'block';
    setTimeout(() => {
      if (errorMessage) {
        errorMessage.style.display = 'none';
      }
    }, 5000);
  }
async function consultarAPI(action, datos = {}) {
    const response = await fetch('../backend/api/products.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ action, ...datos }),
        credentials: 'same-origin'
    });
    const json = await response.json();
    if (!response.ok || json.status !== 'ok') {
        throw new Error(json.answer || 'No fue posible consultar los productos');
    }
    return json.results;
}
 
  // Mostrar sugerencias y la ficha pública sin cambiar los endpoints ni la impresión PDF.
  const searchStatus = document.getElementById('searchStatus');
  function productImage(product) {
    const field = String(product?.image_path || product?.photo_path || product?.image || product?.photo || '').trim();
    if (!field || /^(?:javascript:|data:|\/\/)/i.test(field)) return '';
    if (/^https?:\/\//i.test(field) || field.startsWith('../backend/')) return field;
    if (field.startsWith('backend/')) return '../' + field;
    if (field.startsWith('uploads/')) return '../backend/' + field;
    return '../backend/uploads/products/' + field.replace(/^\/+/, '');
  }
  function hideAutocomplete() {
    autocompleteList.classList.remove('active');
    searchInput.setAttribute('aria-expanded', 'false');
  }
  function showAutocomplete(results) {
    selectedIndex = -1;
    if (!results.length) {
      autocompleteList.replaceChildren();
      hideAutocomplete();
      if (searchStatus) searchStatus.textContent = searchInput.value.trim().length >= 2 ? 'Sin coincidencias' : '';
      return;
    }
    if (searchStatus) searchStatus.textContent = `${results.length} sugerencia${results.length === 1 ? '' : 's'}`;
    autocompleteList.innerHTML = results.map((p, idx) => {
      const image = productImage(p);
      return `<div class="autocomplete-item" role="option" tabindex="-1" aria-selected="false" data-index="${idx}">
        ${image ? `<img class="pc-suggest-image" src="${escapeHtml(image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<span class="pc-suggest-image pc-suggest-placeholder"><i class="bi bi-box-seam" aria-hidden="true"></i></span>'}
        <span class="pc-suggest-info"><span class="pc-suggest-name">${escapeHtml(p.name || 'Producto')}</span><span class="pc-suggest-code">Código: ${escapeHtml(p.code || p.barcode || 'Sin código')}</span></span>
        <span class="pc-suggest-price">$${formatPrice(p.public_price)}</span>
      </div>`;
    }).join('');
    autocompleteList.classList.add('active');
    searchInput.setAttribute('aria-expanded', 'true');
    autocompleteList.querySelectorAll('.autocomplete-item').forEach((item, idx) => {
      item.addEventListener('click', () => selectProduct(results[idx]));
    });
  }
  function displayProduct(product) {
    if (!product) return;
    const photoUrl = productImage(product);
    const description = String(product.description || product.content || '').trim();
    const presentation = String(product.presentation || product.unit || 'PIEZA');
    const publicPrice = Number(product.public_price || 0);
    const stock = Math.max(0, Math.floor(Number(product.stock || 0)));
    resultContainer.innerHTML = `<article class="pc-product">
      <header class="pc-product-head">
        <div>
          <h2>${escapeHtml(product.name || 'Producto sin nombre')}</h2>
          <div class="pc-product-codes"><span>Código: <strong>${escapeHtml(product.code || 'N/A')}</strong></span>${product.barcode ? `<span>Barras: <strong>${escapeHtml(product.barcode)}</strong></span>` : ''}</div>
        </div>
        <button class="btn-print" type="button"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Descargar PDF</button>
      </header>
      <div class="pc-product-main">
      <div class="pc-product-visual">
        <div class="pc-photo">${photoUrl ? `<img src="${escapeHtml(photoUrl)}" alt="Imagen de ${escapeHtml(product.name || 'producto')}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.hidden=false;">` : ''}
          <span class="pc-photo-placeholder" ${photoUrl ? 'hidden' : ''}><i class="bi bi-image" aria-hidden="true"></i></span>
                </div>
       
      </div>
      <div class="pc-product-information">
       <div class="pc-facts">
  <div class="pc-fact">
    <span class="pc-fact-label">Presentación</span>
    <strong>${escapeHtml(presentation)}</strong>
  </div>
  <div class="pc-fact pc-fact-price">
    <span class="pc-fact-label">Precio de venta</span>
    <strong class="pc-price-number">$${formatPrice(publicPrice)}</strong>
  </div>
  <div class="pc-fact">
  <span class="pc-fact-label">Existencias registradas</span>
  <strong>${stock === 0 ? 'Sin existencias' : `${stock.toLocaleString('es-MX')} unidades`}</strong>
</div>
</div>

${description ? `
  <section class="pc-description" aria-label="Descripción del producto">
    <h3>Descripción del producto</h3>
    <p class="pc-description-text is-collapsed" id="pcDescriptionText">${escapeHtml(description)}</p>
    <button class="pc-description-toggle" type="button" aria-expanded="false" aria-controls="pcDescriptionText" hidden>
      <span>Ver más</span>
      <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
  </section>
` : ''}

</div>
</div>
</article>`;
    resultContainer.querySelector('.btn-print')?.addEventListener('click', () => {
      window.printQuotation(product.name || '', publicPrice, product.code || '', photoUrl, description);
    });
    const descriptionText = resultContainer.querySelector('.pc-description-text');
    const descriptionToggle = resultContainer.querySelector('.pc-description-toggle');
    if (descriptionText && descriptionToggle) {
      // El botón aparece únicamente cuando el texto excede las tres líneas.
      requestAnimationFrame(() => {
        descriptionToggle.hidden = descriptionText.scrollHeight <= descriptionText.clientHeight + 2;
      });
      descriptionToggle.addEventListener('click', () => {
        const expanded = descriptionToggle.getAttribute('aria-expanded') === 'true';
        descriptionToggle.setAttribute('aria-expanded', String(!expanded));
        descriptionText.classList.toggle('is-collapsed', expanded);
        descriptionToggle.querySelector('span').textContent = expanded ? 'Ver más' : 'Ver menos';
      });
    }
    hideAutocomplete();
  }
  // Seleccionar producto del autocomplete
  async function selectProduct(product) {
    if (!product) return;
    try {
        const detalle = await consultarAPI('getProductoPublico', { id: product.id });
        searchInput.value = detalle.name || '';
        displayProduct(detalle);
        if (searchStatus) searchStatus.textContent = '';
        searchInput.blur();
        if (window.matchMedia('(max-width: 767px)').matches) {
            resultContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } catch (error) {
        showError(error.message);
    }
}
  // Escapar HTML
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
  // Formatear precio para pantalla (usa punto decimal)
  function formatPrice(price) {
    const num = Number(price || 0);
    return num.toFixed(2);
  }
  // Formatear precio para PDF (usa punto decimal)
  function formatPriceForPdf(price) {
    const num = Number(price || 0);
    return num.toFixed(2);
  }
  // Convierte un texto de descripción en lista de puntos para PDF
  function descriptionToBulletList(desc) {
    if (!desc) return '';
    // Separar por saltos de línea y/o puntos para generar ítems
    const lines = desc
      .split(/\r?\n|\r|\./)
      .map(l => l.trim())
      .filter(Boolean);
    if (lines.length === 0) return '';
    return `<ul style="padding-left: 18px; margin: 0;">${lines
      .map(l => `<li style="margin-bottom: 6px;">${escapeHtml(l)}</li>`)
      .join('')}</ul>`;
  }
  // Imprimir cotización
  window.printQuotation = function(productName, price, code, photoUrl, description) {
    const today = new Date().toLocaleDateString('es-ES');
    const priceText = formatPriceForPdf(price);
    const descriptionHtml = descriptionToBulletList(description);
    const htmlContent = `
      <div style="font-family: Arial, sans-serif; padding: 20px; background: white; color: #333;">
        <div style="text-align: center; border-bottom: 3px solid #1a8f5e; padding-bottom: 20px; margin-bottom: 30px;">
          <h1 style="margin: 0; color: #1a8f5e;">COTIZACIÓN DE PRECIO</h1>
          <h3 style="margin: 10px 0 0 0; color: #0d6a47; font-size: 14px;">SISTEMA CSM</h3>
          <p style="margin: 10px 0 0 0; color: #666; font-size: 12px;">
            Generado: ${today}
          </p>
        </div>
        <div style="margin-bottom: 30px;">
          <div style="display: flex; gap: 20px;">
            ${photoUrl 
              ? `<div style="flex-shrink: 0;">
                   <img src="${escapeHtml(photoUrl)}" style="max-width: 220px; max-height: 220px; border: 1px solid #ddd; padding: 8px; border-radius: 8px;" onerror="this.style.display='none';">
                 </div>` 
              : ''
            }
            <div style="flex: 1;">
              <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 2px solid #e9ecef;">
                  <td style="padding: 10px; font-weight: bold; color: #1a8f5e;">Producto:</td>
                  <td style="padding: 10px; font-size: 1.1rem;">${escapeHtml(productName)}</td>
                </tr>
                <tr style="border-bottom: 1px solid #e9ecef;">
                  <td style="padding: 10px; font-weight: bold; color: #1a8f5e;">Código:</td>
                  <td style="padding: 10px;">${escapeHtml(code)}</td>
                </tr>
              </table>
            </div>
          </div>
        </div>
        <div style="background: linear-gradient(135deg, #1a8f5e 0%, #0d6a47 100%); color: white; padding: 30px; border-radius: 8px; text-align: center; margin-bottom: 30px;">
          <p style="margin: 0; font-size: 14px; opacity: 0.9;">Precio de Venta</p>
          <p style="margin: 10px 0 0 0; font-size: 2.5rem; font-weight: bold;">$ ${priceText}</p>
        </div>
        ${descriptionHtml ? `<div style="margin-bottom: 25px;">
          <h3 style="margin: 0 0 10px 0; color: #1a8f5e;">Detalles</h3>
          <div style="text-align: left; color: #333; font-size: 0.95rem;">${descriptionHtml}</div>
        </div>` : ''}
        <div style="text-align: center; color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
          <p style="margin: 0;">Este documento es una consulta de precio y no constituye una oferta vinculante.</p>
          <p style="margin: 5px 0 0 0; font-size: 10px; color: #aaa;">© ${new Date().getFullYear()} SISTEMA CSM. Todos los derechos reservados.</p>
        </div>
      </div>
    `;
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    const opt = {
      margin: 10,
      filename: `cotizacion-${Date.now()}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
    };
    html2pdf()
      .set(opt)
      .from(element)
      .save()
      .then(() => {
        document.body.removeChild(element);
      })
      .catch(err => {
        console.error('Error generando PDF:', err);
        showError('Error al generar el PDF');
        document.body.removeChild(element);
      });
  };
  // Event listeners
 searchInput.addEventListener('input', (e) => {
    clearTimeout(debounceTimer);
    const query = e.target.value.trim();
    const secuencia = ++busquedaActual;
    allProducts = [];
    hideAutocomplete();

    if (searchStatus) searchStatus.textContent = query.length < 2 ? '' : 'Buscando...';
    if (query.length < 2) return;

    debounceTimer = setTimeout(async () => {
        try {
            const results = await consultarAPI('buscarProductosPublicos', { buscar: query });
            if (secuencia !== busquedaActual) return;
            allProducts = Array.isArray(results) ? results : [];
            showAutocomplete(allProducts);
        } catch (error) {
            if (secuencia !== busquedaActual) return;
            if (searchStatus) searchStatus.textContent = '';
            showError(error.message);
        }
    }, 300);
});
  searchInput.addEventListener('keydown', (e) => {
    const items = autocompleteList.querySelectorAll('.autocomplete-item');
    if (e.key === 'Escape') { hideAutocomplete(); return; }
    if (items.length === 0) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
      updateSelection(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = Math.max(selectedIndex - 1, 0);
      updateSelection(items);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedIndex >= 0 && selectedIndex < items.length) {
        const index = parseInt(items[selectedIndex].dataset.index);
        selectProduct(allProducts[index]);
      }
    }
  });
  // Actualizar selección visual
  function updateSelection(items) {
    items.forEach((item, idx) => {
      item.classList.toggle('selected', idx === selectedIndex);
      item.setAttribute('aria-selected', String(idx === selectedIndex));
      if (idx === selectedIndex) item.scrollIntoView({ block: 'nearest' });
    });
  }
  // Cerrar autocomplete al hacer click fuera
  document.addEventListener('click', (e) => {
    if (e.target !== searchInput && !autocompleteList.contains(e.target)) {
      hideAutocomplete();
    }
  });
  // Cargar productos al iniciar con manejo de errores

});
