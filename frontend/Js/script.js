//© Zero - Código libre no comercial


// Cargar el SVG y animar los corazones
const BASE_RELPATH = window.location.pathname.includes('/frontend/') ? '' : 'frontend/';
const STICKER_IMAGES = ['Img/sticker1.svg', 'Img/sticker2.svg'];

function setupSticker() {
  const sticker = document.getElementById('sticker');
  if (!sticker) return;

  const saved = localStorage.getItem('stickerSelected');
  let src = saved;
  if (!src || !STICKER_IMAGES.includes(src)) {
    // Elegir aleatorio y guardar
    src = STICKER_IMAGES[Math.floor(Math.random() * STICKER_IMAGES.length)];
    localStorage.setItem('stickerSelected', src);
  }
  sticker.src = BASE_RELPATH + src;
  sticker.alt = 'Sticker';
}

fetch(BASE_RELPATH + 'Img/treelove.svg')
  .then(res => res.text())
  .then(svgText => {
    const container = document.getElementById('tree-container');
    container.innerHTML = svgText;
    const svg = container.querySelector('svg');
    if (!svg) return;

    // Animación de "dibujo" para todos los paths
    const allPaths = Array.from(svg.querySelectorAll('path'));
    allPaths.forEach(path => {
      path.style.stroke = '#222';
      path.style.strokeWidth = '2.5';
      path.style.fillOpacity = '0';
      const length = path.getTotalLength();
      path.style.strokeDasharray = length;
      path.style.strokeDashoffset = length;
      path.style.transition = 'none';
    });

    // Forzar reflow y luego animar
    setTimeout(() => {
      allPaths.forEach((path, i) => {
        path.style.transition = `stroke-dashoffset 1.2s cubic-bezier(.77,0,.18,1) ${i * 0.08}s, fill-opacity 0.5s ${0.9 + i * 0.08}s`;
        path.style.strokeDashoffset = 0;
        setTimeout(() => {
          path.style.fillOpacity = '1';
          path.style.stroke = '';
          path.style.strokeWidth = '';
        }, 1200 + i * 80);
      });

      // Después de la animación de dibujo, mueve y agranda el SVG
      const totalDuration = 1200 + (allPaths.length - 1) * 80 + 500;
      setTimeout(() => {
        svg.classList.add('move-and-scale');
        // Mostrar texto con efecto typing
        setTimeout(() => {
          showDedicationText();
          // Mostrar petalos flotando
          startFloatingObjects();
          // Mostrar cuenta regresiva
          showCountdown();
          // Iniciar música de fondo
          playBackgroundMusic();
        }, 1200); //Tiempo para agrandar el SVG
      }, totalDuration);
    }, 50);

      // Mejorar animación de corazones (ondulación / latido)
      const style = document.createElement('style');
      style.textContent = `
        @keyframes heartBeat {
          0%, 100% { transform: scale(1); }
          25% { transform: scale(1.12); }
          50% { transform: scale(0.98); }
          75% { transform: scale(1.08); }
        }
        .animated-heart {
          animation: heartBeat 2.6s ease-in-out infinite;
          transform-origin: center;
        }
      `;
      document.head.appendChild(style);
    });
function getURLParam(name) {
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}

function showDedicationText() { //seguidores
  let text = getURLParam('text');
  if (!text) {
    text = `Para mi Ixtaccihuatl...:\n\nGracias por cada instante compartido, por tu amor paciente y sincero, por darme la oportunidad de conocer un corazon tan puro y construir contigo una historia juntos. \n\nCada día a tu lado florece mi cariño, crece sin medida ni final, y me enseña el valor de amar de verdad.\n\nNo tendre como demostrarte el amor que te tengo, ya que ni los gestos ni las palabras son suficientes, pero espero que mi vida sea suficiente para poderte demostra el amor que te tengo y tendre.`;  } else {
    text = decodeURIComponent(text).replace(/\\n/g, '\n');
  }
  const container = document.getElementById('dedication-text');
  container.classList.add('typing');
  let i = 0;
  function type() {
    if (i <= text.length) {
      container.textContent = text.slice(0, i);
      i++;
      setTimeout(type, text[i - 2] === '\n' ? 350 : 45);
    } else {
      // Al terminar el typing, mostrar la firma animada
      setTimeout(showSignature, 600);
    }
  }
  type();
}

// Firma manuscrita animada
function showSignature() {
  // Cambia para buscar la firma dentro del contenedor de dedicatoria
  const dedication = document.getElementById('dedication-text');
  let signature = dedication.querySelector('#signature');
  if (!signature) {
    signature = document.createElement('div');
    signature.id = 'signature';
    signature.className = 'signature';
    dedication.appendChild(signature);
  }
  let firma = getURLParam('firma');
  signature.textContent = firma ? decodeURIComponent(firma) : "Con amor, tu sonso novio Francisco...";
  signature.classList.add('visible');
}



// Controlador de objetos flotantes
function startFloatingObjects() {
  const container = document.getElementById('floating-objects');
  let count = 0;
  function spawn() {
    let el = document.createElement('div');
    el.className = 'floating-petal';
    // Posición inicial
    el.style.left = `${Math.random() * 90 + 2}%`;
    el.style.top = `${100 + Math.random() * 10}%`;
    el.style.opacity = 0.7 + Math.random() * 0.3;
    container.appendChild(el);

    // Animación flotante
    const duration = 6000 + Math.random() * 4000;
    const drift = (Math.random() - 0.5) * 60;
    setTimeout(() => {
      el.style.transition = `transform ${duration}ms linear, opacity 1.2s`;
      el.style.transform = `translate(${drift}px, -110vh) scale(${0.8 + Math.random() * 0.6}) rotate(${Math.random() * 360}deg)`;
      el.style.opacity = 0.2;
    }, 30);

    // Eliminar después de animar
    setTimeout(() => {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, duration + 2000);

    // Generar más objetos
    if (count++ < 32) setTimeout(spawn, 350 + Math.random() * 500);
    else setTimeout(spawn, 1200 + Math.random() * 1200);
  }
  spawn();
}

// --- Cuenta regresiva principal (ya existente) ---
function showCountdown() {
  const container = document.getElementById('countdown');
  let startParam = getURLParam('start');
  let eventParam = getURLParam('event');

  function parseLocalDate(param, fallback) {
    if (param) {
      const [y, m, d] = param.split('-').map(Number);
      return new Date(y, m - 1, d);
    }
    return fallback;
  }

  let startDate = parseLocalDate(startParam, new Date(2025, 4, 12));
  let eventDate = parseLocalDate(eventParam, new Date(2026, 4, 12));

  function update() {
    const now = new Date();

    // Días juntos
    let diff = now - startDate;
    let days = Math.floor(diff / (1000 * 60 * 60 * 24));

    // Cuenta regresiva aniversario
    let eventDiff = eventDate - now;
    let eventDays = Math.max(0, Math.floor(eventDiff / (1000 * 60 * 60 * 24)));
    let eventHours = Math.max(0, Math.floor((eventDiff / (1000 * 60 * 60)) % 24));
    let eventMinutes = Math.max(0, Math.floor((eventDiff / (1000 * 60)) % 60));
    let eventSeconds = Math.max(0, Math.floor((eventDiff / 1000) % 60));

    container.innerHTML =
      `Llevamos juntos: <b>${days}</b> días<br>` +
      `Nuestro aniversario: <b>${eventDays}d ${eventHours}h ${eventMinutes}m ${eventSeconds}s</b>`;
    container.classList.add('visible');
  }

  update();
  setInterval(update, 1000);
}

// --- Nueva función: cuenta de días sin vernos y próxima cita ---
function showNoSeeCountdown() {
  const container = document.getElementById('nosee-countdown');
  if (!container) return;

  const lastSeenDefault = new Date(2025, 9, 13); // 13 oct 2025 (mes 9 = octubre)
  let nextMeetDate = localStorage.getItem('nextMeetDate');

  function update() {
    const now = new Date();

    if (nextMeetDate) {
      // --- Modo: cuenta regresiva hasta vernos ---
      const meetDate = new Date(nextMeetDate);
      const diff = meetDate - now;

      if (diff <= 0) {
        // Llegó la fecha: borrar próxima cita y reiniciar
        localStorage.removeItem('nextMeetDate');
        container.innerHTML = `<b>¡Ya nos vimos! ❤️</b>`;
        setTimeout(() => showNoSeeCountdown(), 2000);
        return;
      }

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const minutes = Math.floor((diff / (1000 * 60)) % 60);
      const seconds = Math.floor((diff / 1000) % 60);

      container.innerHTML = `
        <p>Faltan para vernos: <b>${days}d ${hours}h ${minutes}m ${seconds}s</b></p>
      `;
    } else {
      // --- Modo: días sin vernos ---
      const diff = now - lastSeenDefault;
      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const minutes = Math.floor((diff / (1000 * 60)) % 60);
      const seconds = Math.floor((diff / 1000) % 60);

      container.innerHTML = `
        <p>Días sin vernos: <b>${days}d ${hours}h ${minutes}m ${seconds}s</b></p>
      `;
    }
  }

  update();
  setInterval(update, 1000);
}

// --- Botón de corazón  para fijar nueva fecha ---
function setupHeartButton() {
  let btn = document.getElementById('heart-btn');
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'heart-btn';
    btn.classList.add('action-btn');
    btn.innerHTML = '❤️';
    btn.style.position = 'fixed';
    btn.style.bottom = '18px';
    btn.style.left = '18px';
    btn.style.background = 'rgba(255,255,255,0.9)';
    btn.style.border = 'none';
    btn.style.borderRadius = '50%';
    btn.style.fontSize = '1.6em';
    btn.style.padding = '10px 12px';
    btn.style.cursor = 'pointer';
    btn.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
    btn.title = 'Registrar próxima vez que se verán 💕';
    document.body.appendChild(btn);
  }

  btn.onclick = () => {
    const input = prompt('¿Cuándo se verán? (formato: AAAA-MM-DD)');
    if (!input) return;

    const [y, m, d] = input.split('-').map(Number);
    const newDate = new Date(y, m - 1, d);
    if (isNaN(newDate.getTime())) {
      alert('Fecha inválida. Usa formato correcto: AAAA-MM-DD');
      return;
    }

    localStorage.setItem('nextMeetDate', newDate.toISOString());
    alert('Fecha guardada 💞');
    showNoSeeCountdown();
  };
}

function setupMobileActions() {
  if (window.innerWidth > 700) return;

  const container = document.getElementById('mobile-actions');
  if (!container) return;

  // Asegura que los botones existan y sean visibles
  const heartBtn = document.getElementById('heart-btn');
  const musicBtn = document.getElementById('music-btn');

  // En caso de que JS no haya corrido, no seguimos.
  if (!heartBtn || !musicBtn) return;

  let continueBtn = document.getElementById('continue-btn');
  if (!continueBtn) {
    // Si no existe, no lo creadora (ya debe estar en el HTML)
    return;
  }

  // Vaciar el contenedor antes de rearmarlo
  container.innerHTML = '';
  container.style.display = 'flex';
  container.style.position = 'relative';
  container.style.zIndex = '50';

  const row1 = document.createElement('div');
  row1.className = 'actions-row';
  const row2 = document.createElement('div');
  row2.className = 'actions-row';

  function resetButton(btn) {
    if (!btn) return;
    btn.style.position = 'relative';
    btn.style.bottom = '';
    btn.style.left = '';
    btn.style.right = '';
    btn.style.transform = '';
    btn.style.width = '';
    btn.style.maxWidth = '';
  }

  if (heartBtn) {
    resetButton(heartBtn);
    row1.appendChild(heartBtn);
  }

  if (musicBtn) {
    resetButton(musicBtn);
    row2.appendChild(musicBtn);
  }

  if (continueBtn) {
    resetButton(continueBtn);
    row2.appendChild(continueBtn);
  }

  container.appendChild(row1);
  container.appendChild(row2);
}

// --- Mantenimiento de tus funciones auxiliares ---
function getURLParam(name) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(name);
}

// --- Música de fondo (sin tocar) ---
function playBackgroundMusic() {
  const audio = document.getElementById('bg-music');
  if (!audio) return;

  let musicaParam = getURLParam('musica');
  if (musicaParam) {
    musicaParam = decodeURIComponent(musicaParam).replace(/[^\w\d .\-]/g, '');
    audio.src = BASE_RELPATH + 'Music/' + musicaParam;
  }

  let youtubeParam = getURLParam('youtube');
  if (youtubeParam) {
    let helpMsg = document.getElementById('yt-help-msg');
    if (!helpMsg) {
      helpMsg = document.createElement('div');
      helpMsg.id = 'yt-help-msg';
      helpMsg.style.position = 'fixed';
      helpMsg.style.right = '18px';
      helpMsg.style.bottom = '180px';
      helpMsg.style.background = 'rgba(255,255,255,0.95)';
      helpMsg.style.color = '#e60026';
      helpMsg.style.padding = '10px 16px';
      helpMsg.style.borderRadius = '12px';
      helpMsg.style.boxShadow = '0 2px 8px #e6002633';
      helpMsg.style.fontSize = '1.05em';
      helpMsg.style.zIndex = 100;
      helpMsg.innerHTML = 'Para usar música de YouTube, descarga el audio (por ejemplo, usando y2mate, 4K Video Downloader, etc.), colócalo en la carpeta <b>Music</b> y usa la URL así:<br><br><code>?musica=nombre.mp3</code>';
      document.body.appendChild(helpMsg);
      setTimeout(() => { if(helpMsg) helpMsg.remove(); }, 15000);
    }
  }

  let btn = document.getElementById('music-btn');
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'music-btn';
    btn.classList.add('action-btn');
    btn.textContent = '🔊 Música';
    btn.style.position = 'fixed';
    btn.style.bottom = '18px';
    btn.style.right = '18px';
    btn.style.zIndex = 99;
    btn.style.background = 'rgba(255,255,255,0.85)';
    btn.style.border = 'none';
    btn.style.borderRadius = '24px';
    btn.style.padding = '10px 18px';
    btn.style.fontSize = '1.1em';
    btn.style.cursor = 'pointer';
    document.body.appendChild(btn);
  }
  audio.volume = 0.7;
  audio.loop = true;
  audio.play().then(() => {
    btn.textContent = '🔊';
  }).catch(() => {
    btn.textContent = '▶️';
  });
  btn.onclick = () => {
    if (audio.paused) {
      audio.play();
      btn.textContent = '🔊';
    } else {
      audio.pause();
      btn.textContent = '🔈';
    }
  };
}

// --- Inicialización ---
window.addEventListener('DOMContentLoaded', () => {
  setupSticker();
  showCountdown();
  showNoSeeCountdown();
  setupHeartButton();
  playBackgroundMusic();
});
