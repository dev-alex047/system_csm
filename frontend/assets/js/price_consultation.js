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
  let isLoading = false;

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

  // Cargar todos los productos al inicio
  async function loadAllProducts() {
    if (isLoading || allProducts.length > 0) {
      return allProducts;
    }
    
    isLoading = true;
    try {
      // Usar fetch directo sin apiRequest() para evitar redireccionamiento en 401
      // Llamar con ?public=1 para permitir acceso sin autenticación
      const response = await fetch('../backend/api/products.php?public=1', {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const res = await response.json();
      allProducts = Array.isArray(res) ? res : (res.data || res || []);
      
      if (!Array.isArray(allProducts)) {
        allProducts = [];
      }
      
      if (allProducts.length === 0) {
        showError('No hay productos disponibles para consultar');
      }
      
      isLoading = false;
      return allProducts;
    } catch (e) {
      isLoading = false;
      console.error('Error cargando productos:', e);
      showError('Error al cargar productos: ' + (e.message || 'Intenta nuevamente'));
      return [];
    }
  }

  // Normalizar búsqueda (sin acentos, minúsculas)
  function normalize(str) {
    return String(str || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  // Buscar productos
  function searchProducts(query) {
    if (query.length < 2) {
      autocompleteList.classList.remove('active');
      return [];
    }

    const normalized = normalize(query);
    const results = allProducts
      .filter(p => {
        const code = normalize(p.code || '');
        const name = normalize(p.name || '');
        const barcode = normalize(p.barcode || '');
        return code.includes(normalized) || name.includes(normalized) || barcode.includes(normalized);
      })
      .slice(0, 10); // Limitar a 10 resultados

    return results;
  }

  // Mostrar autocomplete
  function showAutocomplete(results) {
    selectedIndex = -1;
    if (results.length === 0) {
      autocompleteList.classList.remove('active');
      return;
    }

    autocompleteList.innerHTML = results
      .map((p, idx) => `
        <div class="autocomplete-item" data-index="${idx}">
          <div style="font-weight: 600; color:#000;">${escapeHtml(p.name || '')}</div>
          <div style="font-size: 0.85rem; color: #000; margin-top: 4px;">
            Código: ${escapeHtml(p.code || 'N/A')} | $ ${formatPrice(p.public_price || 0)}
          </div>
        </div>
      `)
      .join('');

    autocompleteList.classList.add('active');

    // Event listeners para items
    autocompleteList.querySelectorAll('.autocomplete-item').forEach((item, idx) => {
      item.addEventListener('click', () => {
        selectProduct(results[idx]);
      });
    });
  }

  // Mostrar producto seleccionado
  function displayProduct(product) {
    if (!product) return;

    // Obtener la ruta de imagen desde los distintos campos posibles que usa la base de datos
    const imageField = product.image_path || product.photo_path || product.image || product.photo || '';
    let photoUrl = null;
    if (imageField) {
      // Si ya viene con parte de la ruta (uploads/...), no duplicar
      if (imageField.includes('uploads/')) {
        photoUrl = `../backend/${imageField}`;
      } else {
        photoUrl = `../backend/uploads/products/${imageField}`;
      }
    }

    const description = product.description || product.content || '';
    const presentation = product.presentation || 'PIEZA';
    const publicPrice = parseFloat(product.public_price || 0);
    const priceFormatted = formatPrice(publicPrice);

    resultContainer.innerHTML = `
      <div class="product-result">
        <div class="product-header">
          <div>
            <h2 style="margin: 0; font-size: 1.5rem;">${escapeHtml(product.name || '')}</h2>
            <div style="font-size: 0.9rem; margin-top: 5px; opacity: 0.9;">
              Código: <strong>${escapeHtml(product.code || 'N/A')}</strong>
              ${product.barcode ? ` | Barcode: <strong>${escapeHtml(product.barcode)}</strong>` : ''}
            </div>
          </div>
          <button class="btn-print" data-description="${encodeURIComponent(description)}" onclick="printQuotation('${escapeHtml(product.name)}', ${publicPrice}, '${escapeHtml(product.code || '')}', '${photoUrl || ''}', decodeURIComponent(this.dataset.description))">
            <i class="bi bi-printer"></i> Imprimir
          </button>
        </div>
        <div class="product-body">
          <div class="product-image-container">
            ${photoUrl 
              ? `<img src="${photoUrl}" alt="${escapeHtml(product.name)}" class="product-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` 
              : ''
            }
            <div class="product-image empty" ${photoUrl ? 'style="display:none;"' : ''}>
              <i class="bi bi-image"></i>
            </div>
          </div>
          <div class="product-details">
            <div class="detail-row">
              <span class="detail-label">Presentación:</span>
              <span class="detail-value">${presentation}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Descripción:</span>
              <span class="detail-value">${escapeHtml(description)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Stock Actual:</span>
              <span class="detail-value">${Math.floor(product.stock || 0)} unidades</span>
            </div>
            <div class="price-highlight">
              <div class="label">Precio de Venta</div>
              <div class="value">$ ${priceFormatted}</div>
            </div>
          </div>
        </div>
      </div>
    `;

    autocompleteList.classList.remove('active');
  }

  // Seleccionar producto del autocomplete
  function selectProduct(product) {
    searchInput.value = product.name;
    displayProduct(product);
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
                   <img src="${photoUrl}" style="max-width: 220px; max-height: 220px; border: 1px solid #ddd; padding: 8px; border-radius: 8px;" onerror="this.style.display='none';">
                 </div>` 
              : ''
            }
            <div style="flex: 1;">
              <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 2px solid #e9ecef;">
                  <td style="padding: 10px; font-weight: bold; color: #1a8f5e;">Producto:</td>
                  <td style="padding: 10px; font-size: 1.1rem;">${productName}</td>
                </tr>
                <tr style="border-bottom: 1px solid #e9ecef;">
                  <td style="padding: 10px; font-weight: bold; color: #1a8f5e;">Código:</td>
                  <td style="padding: 10px;">${code}</td>
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
    
    debounceTimer = setTimeout(() => {
      const results = searchProducts(query);
      showAutocomplete(results);
    }, 300);
  });

  searchInput.addEventListener('keydown', (e) => {
    const items = autocompleteList.querySelectorAll('.autocomplete-item');
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
        const results = searchProducts(searchInput.value);
        selectProduct(results[index]);
      }
    }
  });

  // Actualizar selección visual
  function updateSelection(items) {
    items.forEach((item, idx) => {
      item.classList.toggle('selected', idx === selectedIndex);
    });
  }

  // Cerrar autocomplete al hacer click fuera
  document.addEventListener('click', (e) => {
    if (e.target !== searchInput && !autocompleteList.contains(e.target)) {
      autocompleteList.classList.remove('active');
    }
  });

  // Cargar productos al iniciar con manejo de errores
  loadAllProducts().catch(e => {
    console.error('Error iniciando:', e);
    showError('Error al inicializar el módulo');
  });

  // Cargar logos/marcas
  function loadLogos() {
    const logosCarousel = document.getElementById('logosCarousel');
    if (!logosCarousel) return;

    const logos = [
      { name: 'Marca A', file: 'marcaA.png' },
      { name: 'Marca B', file: 'marcaB.png' },
      { name: 'Marca C', file: 'marcaC.png' },
      { name: 'Marca D', file: 'marcaD.png' },
      { name: 'Marca E', file: 'marcaE.png' },
      { name: 'Marca F', file: 'marcaF.png' }
    ];

    logosCarousel.innerHTML = logos.map(logo => `
      <div class="logo-item">
        <img src="../backend/uploads/brands/${logo.file}" alt="${logo.name}" onerror="this.parentElement.style.display='none';">
        <div class="logo-item-name">${logo.name}</div>
      </div>
    `).join('');

    const items = logosCarousel.querySelectorAll('.logo-item img');
    let visibleCount = 0;
    items.forEach(img => {
      if (img.src && !img.src.includes('undefined')) visibleCount++;
    });

    if (visibleCount === 0) {
      logosCarousel.innerHTML = `
        <div style="text-align: center; color: white; padding: 20px; width: 100%;">
          <p>No hay logos disponibles aún.</p>
        </div>
      `;
    }
  }

  loadLogos();
});
