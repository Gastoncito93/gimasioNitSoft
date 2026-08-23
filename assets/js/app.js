const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
const fmtMoney = n => (Number(n || 0)).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = dStr => {
  if (!dStr) return '-';
  const raw = String(dStr).trim().split(' ')[0];
  const parts = raw.split('-');
  if (parts.length === 3 && parts[0].length === 4) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return dStr;
};
const escapeHtml = str => String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m]);

let _toastTimer = null;
function showToast(msg, isError = false) {
  const t = $('#toast');
  if (!t) return;
  if (_toastTimer) clearTimeout(_toastTimer);
  t.textContent = (isError ? '⚠️ ' : '✅ ') + msg;
  t.className = isError ? 'toast-err' : 'toast-ok';
  t.style.display = 'flex';
  _toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
}

async function api(action, data = {}, method = 'POST') {
  try {
    let r;
    if (method === 'GET') {
      r = await fetch(`?ajax=${action}&` + new URLSearchParams(data));
    } else {
      r = await fetch(`?ajax=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams(data)
      });
    }
    if (r.status === 401) { window.location.href = 'login.php'; return { ok: false }; }
    return await r.json();
  } catch (err) {
    showToast('Error de comunicación con el servidor', true);
    return { ok: false, msg: err.message };
  }
}

let _currentPage = 'dashboard';
let _navHistory = [];

let _modalZIndexBase = 10000;

function openModal(id) {
  const el = typeof id === 'string' ? document.getElementById(id) : id;
  if (!el) {
    console.warn('Modal no encontrado:', id);
    return;
  }
  _modalZIndexBase += 20;
  el.style.setProperty('z-index', String(_modalZIndexBase), 'important');
  el.style.setProperty('display', 'flex', 'important');
  el.style.setProperty('visibility', 'visible', 'important');
  el.style.setProperty('opacity', '1', 'important');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  const el = typeof id === 'string' ? document.getElementById(id) : id;
  if (el) {
    el.style.setProperty('display', 'none', 'important');
    el.style.visibility = '';
    el.style.opacity = '';
  }
  const anyOpen = Array.from(document.querySelectorAll('.modal-backdrop, .simulator-overlay')).some(m => m.style.display === 'flex');
  if (!anyOpen) {
    document.body.style.overflow = '';
    _modalZIndexBase = 10000;
  }
  if (window.location.hash && (window.location.hash.substring(1) === id || window.location.hash.startsWith('#modal-'))) {
    try {
      history.replaceState({ type: 'page', page: _currentPage }, '', '#' + _currentPage);
    } catch (e) {}
  }
}

function goBack() {
  // 1. Si hay un modal abierto, cerrarlo primero
  const openModalEl = $$('.modal-backdrop, .simulator-overlay').find(m => m.style.display === 'flex');
  if (openModalEl) {
    openModalEl.style.display = 'none';
    const anyStillOpen = $$('.modal-backdrop, .simulator-overlay').some(m => m.style.display === 'flex');
    if (!anyStillOpen) document.body.style.overflow = '';
    return;
  }

  // 2. Si el menú lateral móvil está abierto, cerrarlo
  const sb = $('.sidebar');
  if (sb && sb.classList.contains('open')) {
    closeMobileSidebar();
    return;
  }

  // 3. Si está en modo directo de simulación móvil, volver a pantalla normal
  if (typeof _liveDeviceActive !== 'undefined' && _liveDeviceActive) {
    setLiveDeviceMode('fullscreen');
    return;
  }

  // 4. Navegar a la pantalla previa en el historial interno
  if (_navHistory.length > 0) {
    const prevPage = _navHistory.pop();
    setPage(prevPage, false);
    try {
      history.replaceState({ type: 'page', page: prevPage }, '', '#' + prevPage);
    } catch(e) {}
  } else {
    setPage('dashboard', true);
  }
}

function systemConfirm({ title = '¿Confirmar acción?', message = 'Esta acción no se puede deshacer.', confirmText = 'Sí, Continuar', cancelText = 'Cancelar', icon = '🗑️', isDanger = true } = {}) {
  return new Promise((resolve) => {
    const modal = $('#modal-confirm');
    if (!modal) {
      resolve(window.confirm(message.replace(/<[^>]*>?/gm, '')));
      return;
    }
    $('#confirm-modal-title').textContent = title;
    $('#confirm-modal-msg').innerHTML = message;
    $('#confirm-modal-icon').textContent = icon;

    const iconBox = $('#confirm-modal-icon');
    if (isDanger) {
      iconBox.style.background = 'rgba(239, 68, 68, 0.15)';
      iconBox.style.borderColor = 'rgba(239, 68, 68, 0.3)';
      iconBox.style.color = '#ef4444';
    } else {
      iconBox.style.background = 'rgba(59, 130, 246, 0.15)';
      iconBox.style.borderColor = 'rgba(59, 130, 246, 0.3)';
      iconBox.style.color = '#3b82f6';
    }

    const btnConfirm = $('#confirm-modal-btn');
    const btnCancel = $('#confirm-modal-cancel');
    btnConfirm.textContent = confirmText;
    btnConfirm.className = isDanger ? 'btn btn-danger' : 'btn btn-primary';
    btnCancel.textContent = cancelText;

    const cleanup = (result) => {
      closeModal('modal-confirm');
      btnConfirm.onclick = null;
      btnCancel.onclick = null;
      resolve(result);
    };

    btnConfirm.onclick = () => cleanup(true);
    btnCancel.onclick = () => cleanup(false);

    openModal('modal-confirm');
  });
}

function togglePasswordVisibility(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.innerHTML = '🙈';
    btn.setAttribute('title', 'Ocultar contraseña');
  } else {
    inp.type = 'password';
    btn.innerHTML = '👁️';
    btn.setAttribute('title', 'Mostrar contraseña');
  }
}

/* ===== SIMULADOR DE DISPOSITIVOS RESPONSIVE & LIVE MODE ===== */
const DEVICE_PRESETS = {
  'iphone-15':  { name: 'iPhone 14/15 Pro', w: 393, h: 852, notch: true, type: 'phone' },
  'iphone-se':  { name: 'iPhone SE Compacto', w: 375, h: 667, notch: false, type: 'phone' },
  'galaxy-s23': { name: 'Samsung Galaxy S23', w: 412, h: 915, notch: true, type: 'phone' },
  'iphone-max': { name: 'iPhone 15 Pro Max', w: 430, h: 932, notch: true, type: 'phone' },
  'ipad-mini':  { name: 'iPad Mini / Tablet 8"', w: 768, h: 1024, notch: false, type: 'tablet' },
  'ipad-pro':   { name: 'iPad Pro 11"', w: 834, h: 1194, notch: false, type: 'tablet' },
  'laptop':     { name: 'Laptop Compacta 13"', w: 1024, h: 680, notch: false, type: 'laptop' }
};

let _currentDeviceKey = 'iphone-15';
let _isLandscape = false;
let _simZoom = 1.0;
let _currentViewMode = 'fullscreen';
let _liveDeviceActive = false;

function updateDeviceTesterActiveStates() {
  // 1. Direct screen mode buttons
  $$('.btn-device-mode').forEach(btn => {
    const m = btn.dataset.deviceMode;
    if (m === _currentViewMode) {
      btn.classList.add('active', 'btn-primary');
      btn.classList.remove('btn-secondary');
    } else {
      btn.classList.remove('active', 'btn-primary');
      btn.classList.add('btn-secondary');
    }
  });

  // 2. Framed simulator buttons
  const isFrameModalOpen = ($('#modal-device-simulator')?.style.display === 'flex');
  $$('.btn-device-frame').forEach(btn => {
    const f = btn.dataset.deviceFrame;
    if (f === _currentDeviceKey && isFrameModalOpen) {
      btn.classList.add('active', 'btn-primary');
      btn.classList.remove('btn-secondary');
    } else {
      btn.classList.remove('active', 'btn-primary');
      btn.classList.add('btn-secondary');
    }
  });

  // 3. Floating toolbar sub-buttons
  $$('#live-device-bar .btn-live-sub').forEach(btn => {
    const m = btn.dataset.deviceMode;
    if (m === _currentViewMode) {
      btn.classList.add('btn-primary');
      btn.classList.remove('btn-secondary');
    } else {
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-secondary');
    }
  });
}

function openDeviceSimulator(presetKey = 'iphone-15') {
  _currentDeviceKey = presetKey || 'iphone-15';
  _isLandscape = false;
  _simZoom = 1.0;

  const simModal = $('#modal-device-simulator');
  if (!simModal) return;
  simModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  const sel = $('#sim-device-select');
  if (sel) sel.value = _currentDeviceKey;

  const frameRole = $('#sim-frame-role-select');
  if (frameRole) frameRole.value = CURRENT_USER.role || 'admin_general';

  const iframe = $('#sim-iframe');
  if (iframe && (!iframe.src || iframe.src === 'about:blank' || iframe.src.indexOf('index.php') === -1)) {
    iframe.src = window.location.href;
  }

  applyDeviceDimensions();
  updateDeviceTesterActiveStates();
  showToast(`📟 Simulador con marco: ${DEVICE_PRESETS[_currentDeviceKey]?.name || presetKey}`);
}

function closeDeviceSimulator() {
  const simModal = $('#modal-device-simulator');
  if (!simModal) return;
  simModal.style.display = 'none';
  document.body.style.overflow = '';
  updateDeviceTesterActiveStates();
}

function setSimulatorDevice(key) {
  if (!DEVICE_PRESETS[key]) return;
  _currentDeviceKey = key;
  applyDeviceDimensions();
  updateDeviceTesterActiveStates();
}

function toggleSimulatorOrientation() {
  _isLandscape = !_isLandscape;
  applyDeviceDimensions();
}

function setSimulatorZoom(scale) {
  _simZoom = scale;
  const wrapper = $('#sim-wrapper');
  if (wrapper) {
    wrapper.style.transform = `scale(${_simZoom})`;
  }
}

function reloadSimulatorIframe() {
  const iframe = $('#sim-iframe');
  if (iframe) {
    iframe.src = iframe.src || window.location.href;
  }
}

async function switchSimFrameRole(role) {
  await api('saas.simulation.set', { role: role, gimnasio_id: CURRENT_USER.gimnasio_id || 1 });
  reloadSimulatorIframe();
}

function applyDeviceDimensions() {
  const preset = DEVICE_PRESETS[_currentDeviceKey] || DEVICE_PRESETS['iphone-15'];
  const frame = $('#device-frame');
  const notch = $('#device-notch');
  const badge = $('#sim-res-badge');
  const oriBtn = $('#sim-orientation-lbl');
  const wrapper = $('#sim-wrapper');

  let width = _isLandscape ? preset.h : preset.w;
  let height = _isLandscape ? preset.w : preset.h;

  if (frame) {
    frame.style.width = width + 'px';
    frame.style.height = height + 'px';
    
    if (preset.type === 'phone') {
      frame.style.borderRadius = '40px';
      frame.style.borderWidth = '12px';
    } else if (preset.type === 'tablet') {
      frame.style.borderRadius = '28px';
      frame.style.borderWidth = '14px';
    } else {
      frame.style.borderRadius = '16px';
      frame.style.borderWidth = '10px';
    }
  }

  if (notch) {
    notch.style.display = (preset.notch && !_isLandscape) ? 'block' : 'none';
  }

  if (badge) {
    badge.textContent = `${width} × ${height} px (${_isLandscape ? 'Horizontal' : 'Vertical'})`;
  }

  if (oriBtn) {
    oriBtn.textContent = _isLandscape ? 'Vertical' : 'Horizontal';
  }

  // Auto-ajuste de escala para que entre en la pantalla sin desbordar
  if (wrapper) {
    const availH = window.innerHeight - 140;
    if (height > availH && _simZoom === 1.0) {
      const autoScale = Math.min(1.0, Math.max(0.60, availH / height)).toFixed(2);
      wrapper.style.transform = `scale(${autoScale})`;
    } else {
      wrapper.style.transform = `scale(${_simZoom})`;
    }
  }
}

/* ===== MODO MÓVIL DIRECTO EN PANTALLA (SIN IFRAMES) ===== */
function toggleLiveDeviceMode() {
  if (_liveDeviceActive) {
    setLiveDeviceMode('fullscreen');
  } else {
    setLiveDeviceMode('iphone');
  }
}

function setLiveDeviceMode(mode = 'iphone') {
  _currentViewMode = mode;
  document.body.classList.remove('force-live-phone', 'force-live-phone-se', 'force-live-tablet');
  const badge = $('#live-device-badge');
  const statusBadge = $('#tester-current-status-badge');
  const bar = $('#live-device-bar');

  if (mode === 'fullscreen' || mode === 'desktop') {
    _liveDeviceActive = false;
    if (bar) bar.style.display = 'none';
    if (statusBadge) {
      statusBadge.className = 'badge b-info';
      statusBadge.textContent = '🖥️ Modo Escritorio (100%)';
    }
    showToast('🖥️ Modo Escritorio (Pantalla Completa) activado');
  } else {
    _liveDeviceActive = true;
    if (mode === 'tablet') {
      document.body.classList.add('force-live-tablet');
      if (badge) badge.textContent = 'Tablet (768px)';
      if (statusBadge) {
        statusBadge.className = 'badge b-purple';
        statusBadge.textContent = '📟 Tablet Directo (768px)';
      }
      showToast('📟 Modo Tablet Directo (768px) activado');
    } else if (mode === 'se') {
      document.body.classList.add('force-live-phone-se');
      if (badge) badge.textContent = 'Compacto SE (360px)';
      if (statusBadge) {
        statusBadge.className = 'badge b-purple';
        statusBadge.textContent = '📱 Compacto SE Directo (360px)';
      }
      showToast('📱 Modo Compacto SE Directo (360px) activado');
    } else {
      document.body.classList.add('force-live-phone');
      if (badge) badge.textContent = 'iPhone 15 Pro (393px)';
      if (statusBadge) {
        statusBadge.className = 'badge b-purple';
        statusBadge.textContent = '📱 iPhone 15 Pro Directo (393px)';
      }
      showToast('📱 Modo iPhone 15 Directo (393px) activado');
    }
    if (bar) bar.style.display = 'flex';
  }

  updateDeviceTesterActiveStates();
  setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 120);
}

function disableLiveDeviceMode() {
  setLiveDeviceMode('fullscreen');
}

async function quickSwitchRole(role) {
  showToast(`🔄 Cambiando perspectiva a: ${role.toUpperCase()}...`);
  const r = await api('saas.simulation.set', {
    role: role,
    gimnasio_id: CURRENT_USER.gimnasio_id || 1
  });

  if (r.ok) {
    showToast(`✅ Vista actualizada: ${role.toUpperCase()}`);
    setTimeout(() => {
      window.location.reload();
    }, 250);
  } else {
    showToast(r.msg || 'No se pudo cambiar de rol', true);
  }
}

/* ===== SIMULACIÓN MULTI-ROL (SUPERADMIN PERSPECTIVAS) ===== */
let _simOptionsCache = null;
let _selectedSimRole = 'dueno';

async function openSimulationModal() {
  const { ok, data } = await api('saas.simulation.options', {}, 'GET');
  if (!ok || !data) return;
  _simOptionsCache = data;

  const currentRole = data.simulated_role || (data.current_role === 'admin_general' ? 'dueno' : data.current_role);
  selectSimRole(currentRole);

  // Llenar Gimnasios
  const selGym = $('#sim-select-gym');
  if (selGym && data.gyms) {
    selGym.innerHTML = '';
    data.gyms.forEach(g => {
      const opt = document.createElement('option');
      opt.value = g.id;
      opt.textContent = `🏢 ${g.nombre} (Dueño: ${g.dueno_usuario || 'Sin asignar'})`;
      selGym.appendChild(opt);
    });
    if (data.simulated_gym_id) selGym.value = data.simulated_gym_id;
    else if (data.gyms[0]) selGym.value = data.gyms[0].id;
  }

  onSimGymChange(selGym ? selGym.value : (data.gyms[0]?.id || 1));
  openModal('modal-simulation-switcher');
}

function selectSimRole(role) {
  _selectedSimRole = role;
  $$('.sim-role-btn').forEach(btn => {
    if (btn.dataset.simRole === role) {
      btn.className = 'btn btn-primary sim-role-btn';
      btn.style.border = '2px solid #60a5fa';
    } else {
      btn.className = 'btn btn-secondary sim-role-btn';
      btn.style.border = '1px solid var(--border)';
    }
  });

  const grpGym = $('#sim-gym-group');
  const grpCoach = $('#sim-coach-group');
  const grpAlu = $('#sim-alumno-group');

  if (role === 'admin_general') {
    if (grpGym) grpGym.style.display = 'none';
    if (grpCoach) grpCoach.style.display = 'none';
    if (grpAlu) grpAlu.style.display = 'none';
  } else if (role === 'dueno') {
    if (grpGym) grpGym.style.display = 'block';
    if (grpCoach) grpCoach.style.display = 'none';
    if (grpAlu) grpAlu.style.display = 'none';
  } else if (role === 'coach') {
    if (grpGym) grpGym.style.display = 'block';
    if (grpCoach) grpCoach.style.display = 'block';
    if (grpAlu) grpAlu.style.display = 'none';
  } else if (role === 'alumno') {
    if (grpGym) grpGym.style.display = 'block';
    if (grpCoach) grpCoach.style.display = 'none';
    if (grpAlu) grpAlu.style.display = 'block';
  }
}

function onSimGymChange(gymId) {
  if (!_simOptionsCache) return;
  const gId = parseInt(gymId);

  // Coaches
  const selCoach = $('#sim-select-coach');
  if (selCoach) {
    selCoach.innerHTML = '';
    const coaches = (_simOptionsCache.coaches || []).filter(c => c.gimnasio_id == gId);
    if (coaches.length) {
      coaches.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = `🏋️ ${c.nombre}`;
        selCoach.appendChild(opt);
      });
      if (_simOptionsCache.simulated_profesor_id) selCoach.value = _simOptionsCache.simulated_profesor_id;
    } else {
      selCoach.innerHTML = '<option value="0">(Sin coaches registrados en esta sede)</option>';
    }
  }

  // Alumnos
  const selAlu = $('#sim-select-alumno');
  if (selAlu) {
    selAlu.innerHTML = '';
    const alumnos = (_simOptionsCache.alumnos || []).filter(a => a.gimnasio_id == gId);
    if (alumnos.length) {
      alumnos.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = `👤 ${a.nombre} (${a.plan ? a.plan.toUpperCase() : '3X'} - ${a.estado.toUpperCase()})`;
        selAlu.appendChild(opt);
      });
      if (_simOptionsCache.simulated_alumno_id) selAlu.value = _simOptionsCache.simulated_alumno_id;
    } else {
      selAlu.innerHTML = '<option value="0">(Sin socios registrados en esta sede)</option>';
    }
  }
}

async function applyRoleSimulation() {
  const role = _selectedSimRole;
  const gymId = $('#sim-select-gym')?.value || 0;
  const coachId = $('#sim-select-coach')?.value || 0;
  const aluId = $('#sim-select-alumno')?.value || 0;

  const r = await api('saas.simulation.set', {
    role: role,
    gimnasio_id: gymId,
    profesor_id: coachId,
    alumno_id: aluId
  });

  if (r.ok) {
    showToast(r.msg || 'Cambiando vista...');
    closeModal('modal-simulation-switcher');
    window.location.reload();
  } else {
    showToast(r.msg || 'Error al cambiar simulación', true);
  }
}

async function exitSimulationMode() {
  // 1. Limpieza de almacenamiento local y de sesión
  try {
    localStorage.removeItem('simulationMode');
    localStorage.removeItem('simulatedRole');
    localStorage.removeItem('simulatedDevice');
    sessionStorage.removeItem('simulationMode');
    sessionStorage.removeItem('simulatedRole');
  } catch(e) {}

  // 2. Desactivar modo móvil directo en pantalla y remover clases CSS
  if (typeof setLiveDeviceMode === 'function') {
    setLiveDeviceMode('fullscreen');
  }
  document.body.classList.remove('force-live-phone', 'force-live-phone-se', 'force-live-tablet');
  const liveBar = $('#live-device-bar');
  if (liveBar) liveBar.style.display = 'none';
  
  // 3. Cerrar simulador con marco
  if (typeof closeDeviceSimulator === 'function') {
    closeDeviceSimulator();
  }
  
  // 4. Cerrar selectores y modales de simulación
  if (typeof closeModal === 'function') {
    closeModal('modal-simulation-switcher');
    closeModal('modal-simulation-selector');
    closeModal('modal-device-simulator');
  }

  showToast('🔄 Volviendo al SuperAdmin...');

  // 5. Llamar API backend de salida
  try {
    await api('saas.simulation.exit', {});
  } catch(err) {}

  // 6. Redirigir limpiamente para restaurar 100% de la UI y sesión de SuperAdmin
  window.location.href = 'index.php?exit_simulation=1';
}

window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const isLiveActive = typeof _liveDeviceActive !== 'undefined' && _liveDeviceActive;
    const simModal = $('#modal-device-simulator');
    const isModalSimOpen = simModal && simModal.style.display === 'flex';
    const simSwitcher = $('#modal-simulation-switcher');
    const isSwitcherOpen = simSwitcher && simSwitcher.style.display === 'flex';
    
    if (isLiveActive || isModalSimOpen || isSwitcherOpen || CURRENT_USER.is_simulating) {
      exitSimulationMode();
      return;
    }

    // Si hay un modal abierto, cerrarlo
    const anyModal = $$('.modal-backdrop').find(m => m.style.display === 'flex');
    if (anyModal) {
      anyModal.style.display = 'none';
      const anyStillOpen = $$('.modal-backdrop, .simulator-overlay').some(m => m.style.display === 'flex');
      if (!anyStillOpen) document.body.style.overflow = '';
      return;
    }

    // Si no está en el dashboard, volver a la página previa
    if (_currentPage !== 'dashboard') {
      goBack();
    }
  }
});

function toggleMobileSidebar() {
  const sb = $('.sidebar');
  const bd = $('#sidebar-backdrop');
  if (!sb) return;
  const isOpen = sb.classList.toggle('open');
  if (bd) {
    if (isOpen) {
      bd.classList.add('active');
      document.body.style.overflow = 'hidden';
    } else {
      bd.classList.remove('active');
      document.body.style.overflow = '';
    }
  }
}

function closeMobileSidebar() {
  const sb = $('.sidebar');
  const bd = $('#sidebar-backdrop');
  if (sb) sb.classList.remove('open');
  if (bd) bd.classList.remove('active');
  document.body.style.overflow = '';
}

function setPage(pageId, pushToHistory = true) {
  if (!pageId) pageId = 'dashboard';
  closeMobileSidebar();

  // Cerrar modales si se cambia de página principal
  $$('.modal-backdrop, .simulator-overlay').forEach(m => m.style.display = 'none');
  document.body.style.overflow = '';

  $$('.nav a').forEach(a => a.classList.toggle('active', a.dataset.page === pageId));
  $$('main > section').forEach(s => s.style.display = 'none');
  const target = $('#page-' + pageId);
  if (target) target.style.display = 'block';

  // Manejar historial de navegación
  if (_currentPage !== pageId) {
    if (pushToHistory) {
      _navHistory.push(_currentPage);
      try {
        history.pushState({ type: 'page', page: pageId }, '', '#' + pageId);
      } catch (e) {}
    }
    _currentPage = pageId;
  }

  if (pageId === 'dashboard') loadDashboard();
  if (pageId === 'saas-gimnasios') loadSaasGimnasios();
  if (pageId === 'saas-pagos') loadSaasPagos();
  if (pageId === 'alumnos' || pageId === 'coach-alumnos') { loadAlumnos(); loadProfesOptions(); }
  if (pageId === 'profesores') loadProfesores();
  if (pageId === 'rutinas') { loadRutinas(); loadAlumnosOptions(); }
  if (pageId === 'nutricion') { loadNutricion(); loadAlumnosOptions(); }
  if (pageId === 'coach-ingresos') { loadCoachIngresos(); loadAlumnosOptions(); }
  if (pageId === 'pagos') { loadPagos(); loadAlumnosOptions(); loadProfesOptions(); }
  if (pageId === 'reportes') loadReportes();
  if (pageId === 'config') { loadConfig(); loadGymData(); }
  if (pageId === 'usuarios') { loadUsuarios(); loadProfesOptions(); loadAlumnosOptions(); }
  if (['mi-membresia', 'mi-rutina', 'mi-nutricion', 'mis-pagos'].includes(pageId)) loadAlumnoPortal();
}

$$('.nav a').forEach(a => a.addEventListener('click', e => {
  if (a.dataset.page) {
    e.preventDefault();
    setPage(a.dataset.page);
  }
}));

/* ===== AUDIT SWITCHER (SUPERADMIN) ===== */
async function switchAuditGym(gymId) {
  gymId = Number(gymId) || 0;
  const r = await api('saas.switch_audit', { gimnasio_id: gymId });
  if (r.ok) {
    const gymNombre = r.data?.gimnasio_nombre || (gymId === 0 ? 'Todas las Sedes (Global SaaS)' : `Sede ID ${gymId}`);
    showToast(gymId === 0 ? '🌐 Modo Global SaaS activado' : `🏢 Auditando: ${gymNombre}`);

    // Sincronizar selectores en la interfaz
    if ($('#sel-audit-gym')) $('#sel-audit-gym').value = gymId;
    if ($('#superadmin-gym-switcher')) $('#superadmin-gym-switcher').value = gymId;

    // Actualizar badge de sede activa en topbar y navbar
    $$('.topbar-title .badge.b-purple, .topbar .mobile-only .badge.b-purple').forEach(b => {
      b.textContent = gymId === 0 ? '🌐 Global SaaS' : `🏢 ${gymNombre}`;
    });

    // Recargar todas las fuentes de datos y listas para el nuevo gimnasio activo
    const tasks = [
      typeof loadDashboard === 'function' ? loadDashboard() : Promise.resolve(),
      typeof loadAlumnos === 'function' ? loadAlumnos() : Promise.resolve(),
      typeof loadProfesores === 'function' ? loadProfesores() : Promise.resolve(),
      typeof loadPagos === 'function' ? loadPagos() : Promise.resolve(),
      typeof loadRutinas === 'function' ? loadRutinas() : Promise.resolve(),
      typeof loadReportes === 'function' ? loadReportes() : Promise.resolve(),
      typeof loadUsuarios === 'function' ? loadUsuarios() : Promise.resolve(),
      typeof loadNutricion === 'function' ? loadNutricion() : Promise.resolve(),
      typeof loadSaasGimnasios === 'function' ? loadSaasGimnasios() : Promise.resolve(),
      typeof loadSaasPagos === 'function' ? loadSaasPagos() : Promise.resolve(),
      typeof loadAlumnosOptions === 'function' ? loadAlumnosOptions() : Promise.resolve(),
      typeof loadProfesOptions === 'function' ? loadProfesOptions() : Promise.resolve()
    ];
    await Promise.allSettled(tasks);
  } else {
    showToast(r.msg || 'Error al cambiar gimnasio en auditoría', true);
  }
}

/* ===== INVITACIONES MULTI-TENANT ===== */
async function openInviteModal() {
  const { ok, data } = await api('invitaciones.get_links', {}, 'GET');
  if (!ok) return;
  $('#inv-link-alumno').value = data.link_alumno;
  if ($('#inv-link-coach')) $('#inv-link-coach').value = data.link_coach;

  const isCoach = CURRENT_USER.role === 'coach' || !!data.is_coach;
  const grpCoach = $('#inv-grp-coach');
  const modalTitle = $('#inv-modal-title');
  const modalSub = $('#inv-modal-subtitle');
  const coachBadge = $('#inv-coach-badge');

  if (isCoach) {
    if (modalTitle) modalTitle.textContent = '🔗 Tu Enlace de Registro para Alumnos';
    if (modalSub) modalSub.innerHTML = 'Compartí este enlace con tus alumnos. Al registrarse, <b>quedarán automáticamente asignados a tu lista a cargo</b> y computarán para tus honorarios y comisiones.';
    if (grpCoach) grpCoach.style.display = 'none';
    if (coachBadge) {
      coachBadge.style.display = 'inline-block';
      coachBadge.textContent = `🏋️‍♂️ Coach: ${data.coach_nombre || CURRENT_USER.nombre || 'Auto-Asignación'}`;
    }
  } else {
    if (modalTitle) modalTitle.textContent = '🔗 Enlaces de Registro Directo';
    if (modalSub) modalSub.textContent = 'Compartí estos enlaces con tus socios o profesores para que se registren directamente en tu gimnasio sin necesidad de seleccionar la sede manualmente:';
    if (grpCoach) grpCoach.style.display = 'block';
    if (coachBadge) coachBadge.style.display = 'none';
  }

  openModal('modal-invite');
}

function copyLink(inputId) {
  const input = $('#' + inputId);
  input.select();
  navigator.clipboard.writeText(input.value);
  showToast('¡Enlace copiado al portapapeles!');
}

function shareWhatsAppInvite() {
  const link = $('#inv-link-alumno').value;
  const isCoach = CURRENT_USER.role === 'coach';
  const text = isCoach
    ? `¡Hola! Podés registrarte en nuestro gimnasio e iniciar tu entrenamiento con mi seguimiento ingresando en este enlace directo: ${link}`
    : `¡Hola! Podés registrarte en nuestro gimnasio ingresando en este enlace directo: ${link}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

/* ===== DASHBOARD ===== */
async function loadDashboard() {
  const { ok, data } = await api('dashboard.kpis', {}, 'GET');
  if (!ok) return;

  if (data.role === 'admin_general' || data.role === 'dueno') {
    const curAudit = data.effective_gym_id || 0;
    _saasGymsCache = data.all_gyms || [];

    // Poblar Switcher de SuperAdmin y Hub de Escritorios
    const selTop = $('#sel-audit-gym');
    const sw = $('#superadmin-gym-switcher');
    [selTop, sw].forEach(selectEl => {
      if (selectEl && data.all_gyms) {
        selectEl.innerHTML = '<option value="0">🌐 Todas las Sedes (Global SaaS)</option>';
        data.all_gyms.forEach(g => {
          const opt = document.createElement('option');
          opt.value = g.id;
          opt.textContent = `🏢 ${g.nombre} (${(g.suscripcion_estado || 'activo').toUpperCase()})`;
          if (g.id == curAudit) opt.selected = true;
          selectEl.appendChild(opt);
        });
      }
    });

    const deskBadge = $('#saas-active-desk-badge');
    if (deskBadge) {
      if (curAudit == 0) {
        deskBadge.className = 'badge b-info';
        deskBadge.textContent = '🌐 Vista Global (Todas las Sedes)';
      } else {
        const activeGym = (data.all_gyms || []).find(g => g.id == curAudit);
        const gName = activeGym ? activeGym.nombre : `Sede #${curAudit}`;
        const dUser = activeGym && activeGym.dueno_usuario ? activeGym.dueno_usuario : 'Dueño';
        deskBadge.className = 'badge b-ok pulse';
        deskBadge.innerHTML = `🏢 Escritorio Activo: <b>${gName}</b> (Dueño: ${dUser})`;
      }
    }

    // SI ES SUPERADMIN EN VISTA GLOBAL SAAS: MOSTRAR ESTADÍSTICA DE COBROS A DUEÑOS
    if (data.is_super && curAudit == 0) {
      if ($('#lbl-kpi-1')) $('#lbl-kpi-1').textContent = '💵 Mi Facturación SaaS (Mes)';
      if ($('#kpi-alumnos')) $('#kpi-alumnos').textContent = '$ ' + fmtMoney(data.saas?.ingresos_mes || 0);
      if ($('#sub-kpi-1')) $('#sub-kpi-1').innerHTML = `Acumulado Anual: $ <b>${fmtMoney(data.saas?.ingresos_anio || 0)}</b>`;

      if ($('#lbl-kpi-2')) $('#lbl-kpi-2').textContent = '🏢 Sedes & Dueños Habilitados';
      const kpiProfSaaS = $('#kpi-profes') || $('#kpi-profesores');
      if (kpiProfSaaS) kpiProfSaaS.textContent = `${data.saas?.gyms_activos || 0} Activas`;
      if ($('#sub-kpi-2')) $('#sub-kpi-2').innerHTML = `<span class="badge b-warn">${data.saas?.gyms_proximos || 0} por vencer</span> <span class="badge b-bad">${data.saas?.gyms_vencidos || 0} suspendidas</span>`;

      if ($('#lbl-kpi-3')) $('#lbl-kpi-3').textContent = '💰 Recaudación SaaS Hoy';
      if ($('#rec-hoy')) $('#rec-hoy').textContent = fmtMoney(data.saas?.ingresos_hoy || 0);
      if ($('#sub-kpi-3')) $('#sub-kpi-3').innerHTML = `Potencial Mensual: $ <b>${fmtMoney(data.saas?.potencial_mes || 0)}</b>`;

      if ($('#lbl-kpi-4')) $('#lbl-kpi-4').textContent = '📊 Tasa de Cobranza a Dueños';
      if ($('#kpi-mes')) $('#kpi-mes').textContent = `${data.saas?.cobranza_pct || 100}%`;
      if ($('#sub-kpi-4')) $('#sub-kpi-4').innerHTML = `${data.saas?.gyms_activos || 0} de ${data.saas?.total_gyms || 0} sedes al día`;

      if ($('#dash-chart-title')) $('#dash-chart-title').textContent = '🎯 Cumplimiento de Suscripciones SaaS (Dueños)';
      if ($('#dash-chart-subtitle')) $('#dash-chart-subtitle').textContent = `Estado de las ${data.saas?.total_gyms || 0} sedes registradas`;
      
      if ($('#dash-charts-container')) $('#dash-charts-container').style.display = 'none';
      if ($('#dash-saas-chart-box')) $('#dash-saas-chart-box').style.display = 'flex';

      requestAnimationFrame(() => {
        const cSaas = $('#chart-saas');
        if (cSaas) {
          drawDonut(cSaas, [
            { label: 'Dueños al Día', value: data.saas?.gyms_activos || 0, color: '#10b981' },
            { label: 'Por Vencer', value: data.saas?.gyms_proximos || 0, color: '#f59e0b' },
            { label: 'Vencidas / Suspendidas', value: data.saas?.gyms_vencidos || 0, color: '#ef4444' }
          ], `${data.saas?.total_gyms || 0}`, `${data.saas?.gyms_activos || 0} AL DÍA`);
        }
      });

      const sLeg = $('#dash-saas-chart-legend');
      if (sLeg) {
        sLeg.innerHTML = `
          <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <span style="font-weight:800;font-size:13px;color:var(--t1)">🏢 Sedes Totales: ${data.saas?.total_gyms || 0}</span>
              <span class="badge b-info">${data.saas?.cobranza_pct || 100}% Cobrado</span>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge b-ok">🟢 ${data.saas?.gyms_activos || 0} al día</span>
              <span class="badge b-warn">🟡 ${data.saas?.gyms_proximos || 0} por vencer</span>
              <span class="badge b-bad">🔴 ${data.saas?.gyms_vencidos || 0} mora</span>
            </div>
            <p style="font-size:11.5px;color:var(--t2);margin:0">Facturación SaaS recaudada: <b style="color:var(--ok)">$ ${fmtMoney(data.saas?.ingresos_mes || 0)}</b></p>
          </div>
        `;
      }

      if ($('#dash-table-title')) $('#dash-table-title').textContent = '⚠️ Suscripciones de Dueños por Cobrar / Próximas a Vencer';
      renderSaasDueñosTabla(data.saas?.prox_vencimientos || []);

    } else {
      // SI ESTÁ AUDITANDO UN GIMNASIO ESPECÍFICO O ES UN DUEÑO
      if ($('#lbl-kpi-1')) $('#lbl-kpi-1').textContent = 'Total Alumnos';
      if ($('#kpi-alumnos')) $('#kpi-alumnos').textContent = data.totales?.alumnos || 0;
      if ($('#sub-kpi-1')) $('#sub-kpi-1').innerHTML = `<span class="badge b-ok">${data.totales?.alumnos_pagaron || 0} al día</span> <span class="badge b-bad">${data.totales?.alumnos_deudores || 0} con deuda</span>`;

      if ($('#lbl-kpi-2')) $('#lbl-kpi-2').textContent = 'Coaches & Profes';
      const kpiProfEl = $('#kpi-profes') || $('#kpi-profesores');
      if (kpiProfEl) kpiProfEl.textContent = data.totales?.profesores || 0;
      if ($('#sub-kpi-2')) {
        const profPagados = data.totales?.profesores_pagados || 0;
        const profTotal = data.totales?.profesores || 0;
        const profDeuda = Math.max(0, profTotal - profPagados);
        $('#sub-kpi-2').innerHTML = `<span class="badge b-purple">${profPagados} al día</span> <span class="badge ${profDeuda > 0 ? 'b-warn' : 'b-ok'}">${profDeuda} con deuda</span>`;
      }

      if ($('#lbl-kpi-3')) $('#lbl-kpi-3').textContent = 'Recaudación de Hoy';
      if ($('#rec-hoy')) $('#rec-hoy').textContent = fmtMoney(data.recaudacion?.dia || 0);
      if ($('#sub-kpi-3')) $('#sub-kpi-3').innerHTML = `Semana: $ <b>${fmtMoney(data.recaudacion?.semana || 0)}</b>`;

      if ($('#lbl-kpi-4')) $('#lbl-kpi-4').textContent = 'Ingresos del Mes (Cobranzas)';
      if ($('#kpi-mes')) $('#kpi-mes').textContent = fmtMoney(data.totales?.ingresos_mes || 0);
      if ($('#sub-kpi-4')) $('#sub-kpi-4').innerHTML = `Liquidado a Coaches: $ <b>${fmtMoney(data.totales?.liquidado_coaches || 0)}</b>`;

      if ($('#dash-chart-title')) $('#dash-chart-title').textContent = '🎯 Estado de Cobranzas & Equipo';
      if ($('#dash-chart-subtitle')) {
        const aluTot = data.desglose?.alumnos_total || 0;
        const profTot = data.desglose?.profesores_total || 0;
        $('#dash-chart-subtitle').textContent = `Resumen de ${aluTot} ${aluTot === 1 ? 'Alumno' : 'Alumnos'} y ${profTot} ${profTot === 1 ? 'Coach' : 'Coaches'}`;
      }

      if ($('#dash-saas-chart-box')) $('#dash-saas-chart-box').style.display = 'none';
      if ($('#dash-charts-container')) $('#dash-charts-container').style.display = 'grid';

      const aluTot = data.desglose?.alumnos_total || 0;
      const aluPag = data.desglose?.alumnos_pagaron || 0;
      const aluDeud = data.desglose?.alumnos_deudores || 0;

      const profTot = data.desglose?.profesores_total || 0;
      const profPag = data.desglose?.profesores_pagaron || 0;
      const profDeud = data.desglose?.profesores_deuda || 0;

      // Actualizar Badges y Contadores Numéricos
      if ($('#dash-alu-tot-badge')) $('#dash-alu-tot-badge').textContent = `${aluTot} ${aluTot === 1 ? 'Alumno' : 'Alumnos'}`;
      if ($('#dash-alu-pagaron')) $('#dash-alu-pagaron').textContent = aluPag;
      if ($('#dash-alu-deben')) $('#dash-alu-deben').textContent = aluDeud;
      if ($('#dash-alu-deuda')) $('#dash-alu-deuda').textContent = aluDeud;

      if ($('#dash-prof-tot-badge')) $('#dash-prof-tot-badge').textContent = `${profTot} ${profTot === 1 ? 'Coach' : 'Coaches'}`;
      if ($('#dash-prof-liquidados')) $('#dash-prof-liquidados').textContent = profPag;
      if ($('#dash-prof-pagados')) $('#dash-prof-pagados').textContent = profPag;
      if ($('#dash-prof-pendientes')) $('#dash-prof-pendientes').textContent = profDeud;
      if ($('#dash-prof-deuda')) $('#dash-prof-deuda').textContent = profDeud;

      requestAnimationFrame(() => {
        // 1. Gráfico de Alumnos
        const cAlu = $('#chart-alumnos');
        if (cAlu) {
          drawDonut(cAlu, [
            { label: 'Alumnos al Día', value: aluPag, color: '#10b981' },
            { label: 'Alumnos con Deuda', value: aluDeud, color: '#ef4444' }
          ], `${aluTot}`, `${aluPag} AL DÍA`);
        }

        // 2. Gráfico de Coaches
        const cProf = $('#chart-profes') || $('#chart-coaches');
        if (cProf) {
          drawDonut(cProf, [
            { label: 'Coaches Pagados', value: profPag, color: '#8b5cf6' },
            { label: 'Coaches con Deuda', value: profDeud, color: '#f97316' }
          ], `${profTot}`, `${profPag} PAGADOS`);
        }
      });

      if ($('#dash-table-title')) $('#dash-table-title').textContent = '⚠️ Próximos Vencimientos de Cuotas (5 días)';
      renderProximosTabla(data.prox_vencimientos || []);
    }

    const gymGrid = $('#superadmin-gyms-grid');
    if (gymGrid && data.all_gyms) {
      gymGrid.innerHTML = '';

      // 1. Tarjeta de Vista Global
      const isGlobal = curAudit == 0;
      const globalCard = document.createElement('div');
      globalCard.style.background = isGlobal ? 'linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(30, 58, 138, 0.55))' : 'rgba(255, 255, 255, 0.03)';
      globalCard.style.border = isGlobal ? '2px solid #3b82f6' : '1px solid var(--border)';
      globalCard.style.borderRadius = 'var(--r)';
      globalCard.style.padding = '18px 20px';
      globalCard.style.display = 'flex';
      globalCard.style.flexDirection = 'column';
      globalCard.style.justifyContent = 'space-between';
      globalCard.style.gap = '14px';
      globalCard.style.cursor = 'pointer';
      globalCard.style.transition = 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)';
      globalCard.onmouseenter = () => { globalCard.style.transform = 'translateY(-3px)'; globalCard.style.boxShadow = '0 10px 24px rgba(0,0,0,0.4)'; };
      globalCard.onmouseleave = () => { globalCard.style.transform = 'none'; globalCard.style.boxShadow = 'none'; };
      globalCard.onclick = (e) => {
        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
          switchAuditGym(0);
        }
      };

      globalCard.innerHTML = `
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:28px">🌐</span>
            ${isGlobal ? '<span class="badge b-ok" style="font-size:11px;padding:3px 9px;font-weight:800">ACTIVO AHORA</span>' : '<span class="badge b-info" style="font-size:11px;padding:3px 9px">Consolidado</span>'}
          </div>
          <h3 style="font-size:16px;font-weight:800;margin-top:10px;color:var(--t1);line-height:1.3">Vista Global SaaS (Tus Ganancias)</h3>
          <p style="font-size:12.5px;color:var(--t2);margin-top:4px;line-height:1.4">Facturación de plataforma cobrada a los dueños.</p>
        </div>
        <div>
          <button type="button" class="btn ${isGlobal ? 'btn-secondary' : 'btn-primary'} btn-sm" style="width:100%;font-weight:700" onclick="switchAuditGym(0)">
            ${isGlobal ? '✅ Viendo Global SaaS' : '👁️ Seleccionar Vista Global'}
          </button>
        </div>
      `;
      gymGrid.appendChild(globalCard);

      // 2. Tarjetas de cada Gimnasio
      data.all_gyms.forEach(g => {
        const isSel = g.id == curAudit;
        let bClass = 'b-ok';
        let bText = 'Al Día';
        if (g.suscripcion_estado === 'proximo') { bClass = 'b-warn'; bText = 'Próximo'; }
        if (g.suscripcion_estado === 'vencido' || g.suscripcion_estado === 'suspendido') { bClass = 'b-bad'; bText = 'Suspendido'; }

        const card = document.createElement('div');
        card.style.background = isSel ? 'linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(6, 78, 59, 0.5))' : 'rgba(255, 255, 255, 0.03)';
        card.style.border = isSel ? '2px solid #10b981' : '1px solid var(--border)';
        card.style.borderRadius = 'var(--r)';
        card.style.padding = '18px 20px';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.justifyContent = 'space-between';
        card.style.gap = '14px';
        card.style.cursor = 'pointer';
        card.style.transition = 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)';
        card.onmouseenter = () => { card.style.transform = 'translateY(-3px)'; card.style.boxShadow = '0 10px 24px rgba(0,0,0,0.4)'; };
        card.onmouseleave = () => { card.style.transform = 'none'; card.style.boxShadow = 'none'; };
        card.onclick = (e) => {
          if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
            switchAuditGym(g.id);
          }
        };

        card.innerHTML = `
          <div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
              <span style="font-size:28px">🏢</span>
              <span class="badge ${bClass}" style="font-size:11px;padding:3px 9px;font-weight:800">${bText}</span>
            </div>
            <h3 style="font-size:16px;font-weight:800;margin-top:10px;color:var(--t1)">${g.nombre}</h3>
            <div style="font-size:12.5px;color:var(--t2);margin-top:6px;line-height:1.5">
              <div>👤 <b>Dueño:</b> <span style="color:#60a5fa">${g.dueno_usuario || 'Sin asignar'}</span></div>
              <div>👥 <b>Socios:</b> ${g.total_alumnos || 0} | 🏋️ <b>Coaches:</b> ${g.total_profes || 0}</div>
              <div>💵 <b>Cuota SaaS:</b> $ ${fmtMoney(g.suscripcion_monto || 45000)}/mes</div>
            </div>
          </div>
          <div style="display:flex;gap:6px">
            <button type="button" class="btn ${isSel ? 'btn-success' : 'btn-primary'} btn-sm" style="flex:1;font-weight:700" onclick="switchAuditGym(${g.id})">
              ${isSel ? '✅ Sede Seleccionada' : '👁️ Seleccionar Sede'}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" title="Editar Sede" onclick="event.stopPropagation(); editGymById(${g.id})">✏️</button>
          </div>
        `;
        gymGrid.appendChild(card);
      });

      // 3. Tarjeta de Nuevo Gimnasio (Más compacta y discreta)
      const newCard = document.createElement('div');
      newCard.style.border = '1.5px dashed #475569';
      newCard.style.borderRadius = 'var(--r)';
      newCard.style.padding = '12px 14px';
      newCard.style.display = 'flex';
      newCard.style.flexDirection = 'column';
      newCard.style.alignItems = 'center';
      newCard.style.justifyContent = 'center';
      newCard.style.cursor = 'pointer';
      newCard.style.minHeight = '100px';
      newCard.style.textAlign = 'center';
      newCard.style.gap = '4px';
      newCard.style.background = 'rgba(255, 255, 255, 0.015)';
      newCard.style.transition = 'var(--tr)';
      newCard.onmouseenter = () => { newCard.style.borderColor = '#94a3b8'; newCard.style.background = 'rgba(255, 255, 255, 0.04)'; };
      newCard.onmouseleave = () => { newCard.style.borderColor = '#475569'; newCard.style.background = 'rgba(255, 255, 255, 0.015)'; };
      newCard.onclick = () => openGymModal();
      newCard.innerHTML = `
        <div style="font-size:20px;line-height:1">➕</div>
        <strong style="font-size:12px;color:#cbd5e1;font-weight:600">Crear Nuevo Gimnasio & Dueño</strong>
        <p style="font-size:10.5px;color:var(--t-mut);margin:0">Dar de alta sede</p>
      `;
      gymGrid.appendChild(newCard);
    }
  } else if (data.role === 'coach') {
    const tot = data.totales || {};
    const liquidado = Number(tot.liquidado_mes || 0);
    const ganancia = Number(tot.ganancia_mes || 0);
    const recaudado = Number(tot.recaudado_alumnos || 0);
    const saldoPend = Number(tot.saldo_pendiente || 0);

    // 1. Dashboard KPI Cards (4 Cards)
    if ($('#coach-kpi-liquidado')) $('#coach-kpi-liquidado').textContent = fmtMoney(liquidado);
    if ($('#coach-kpi-liquidado-sub')) {
      $('#coach-kpi-liquidado-sub').textContent = liquidado > 0 ? `✅ $ ${fmtMoney(liquidado)} abonado por sede` : 'Sin liquidaciones este mes';
      $('#coach-kpi-liquidado-sub').className = liquidado > 0 ? 'badge b-ok' : 'badge b-warn';
    }

    if ($('#coach-kpi-ganancia')) $('#coach-kpi-ganancia').textContent = fmtMoney(ganancia);
    if ($('#coach-kpi-ganancia-sub')) {
      if (saldoPend > 0) {
        $('#coach-kpi-ganancia-sub').innerHTML = `<span class="badge b-warn">Saldo pendiente: $ ${fmtMoney(saldoPend)}</span>`;
      } else {
        $('#coach-kpi-ganancia-sub').innerHTML = `<span class="badge b-ok">✅ 100% Liquidado al Día</span>`;
      }
    }

    if ($('#coach-kpi-recaudado')) $('#coach-kpi-recaudado').textContent = fmtMoney(recaudado);
    if ($('#coach-kpi-asist')) $('#coach-kpi-asist').textContent = `${tot.alumnos_pagaron || 0} socio${tot.alumnos_pagaron === 1 ? '' : 's'} abonaron este mes`;

    if ($('#coach-kpi-alumnos')) $('#coach-kpi-alumnos').textContent = tot.alumnos || 0;
    if ($('#coach-kpi-activos')) $('#coach-kpi-activos').textContent = `${tot.alumnos_activos || 0} activos`;
    if ($('#coach-kpi-vencidos')) $('#coach-kpi-vencidos').textContent = `${tot.alumnos_vencidos || 0} vencidos`;

    // 2. Tabla de liquidaciones en Dashboard (#tbl-coach-dash-liq)
    renderCoachDashLiqTabla(data.liquidaciones_dueno || []);

    // 3. Actualizar pantalla de Mis Ganancias (#page-coach-ingresos)
    if ($('#coach-ingreso-liquidado')) $('#coach-ingreso-liquidado').textContent = '$ ' + fmtMoney(liquidado);
    if ($('#coach-cuota-mensual')) {
      $('#coach-cuota-mensual').textContent = liquidado > 0 ? `✅ $ ${fmtMoney(liquidado)} abonado por el dueño` : 'Sin liquidaciones este mes';
    }

    if ($('#coach-ingreso-ganancia')) $('#coach-ingreso-ganancia').textContent = '$ ' + fmtMoney(ganancia);
    if ($('#coach-ingreso-ganancia-sub')) {
      $('#coach-ingreso-ganancia-sub').innerHTML = saldoPend > 0 
        ? `<span class="badge b-warn">Pendiente de cobro: $ ${fmtMoney(saldoPend)}</span>` 
        : `<span class="badge b-ok">✅ Al Día (Liquidado al 100%)</span>`;
    }

    if ($('#coach-rec-mes')) $('#coach-rec-mes').textContent = '$ ' + fmtMoney(recaudado);
    if ($('#coach-rec-mes-sub')) $('#coach-rec-mes-sub').textContent = `${tot.alumnos_pagaron || 0} socio${tot.alumnos_pagaron === 1 ? '' : 's'} abonaron este mes`;

    const tipo = tot.tipo_remuneracion || 'sueldo_fijo';
    let esquemaTxt = 'Sueldo Fijo';
    let esquemaSub = `$ ${fmtMoney(tot.cuota_mensual || 0)} / mes`;
    if (tipo === 'porcentaje') {
      esquemaTxt = `${tot.porcentaje_comision || 0}% Comisión`;
      esquemaSub = 'Sobre cuotas de alumnos';
    } else if (tipo === 'monto_alumno') {
      esquemaTxt = `$ ${fmtMoney(tot.monto_por_alumno || 0)} / Alumno`;
      esquemaSub = 'Por socio que pague';
    }
    if ($('#coach-ingreso-esquema')) $('#coach-ingreso-esquema').textContent = esquemaTxt;
    if ($('#coach-ingreso-esquema-sub')) $('#coach-ingreso-esquema-sub').textContent = esquemaSub;

    // Días activos y asistencias
    if ($('#coach-dias-activos')) $('#coach-dias-activos').textContent = `${tot.dias_activos_mes || 0} Días`;
    if ($('#coach-total-clases')) $('#coach-total-clases').textContent = `${tot.total_clases_mes || 0} asistencias registradas`;
    if ($('#coach-stats-dias-val')) $('#coach-stats-dias-val').textContent = tot.dias_activos_mes || 0;
    if ($('#coach-stats-alumnos-val')) $('#coach-stats-alumnos-val').textContent = tot.total_clases_mes || 0;
    if ($('#coach-stats-prom-val')) {
      const dAct = Number(tot.dias_activos_mes || 0);
      const tClas = Number(tot.total_clases_mes || 0);
      $('#coach-stats-prom-val').textContent = dAct > 0 ? (tClas / dAct).toFixed(1) : '0.0';
    }

    // Renderizar tablas de coach ingresos
    renderCoachLiqTabla(data.liquidaciones_dueno || []);
    renderCoachCobrosTabla(data.cobros_alumnos || []);

    renderProximosTabla(data.prox_vencimientos || []);
  } else if (data.role === 'alumno') {
    renderAlumnoPortal(data);
  }
}

// 1. Tabla & Cards de Cobranzas a Dueños de Gimnasios (Para SuperAdmin)
function renderSaasDueñosTabla(items) {
  const thead = $('#tbl-prox-thead');
  const tb = $('#tbl-prox tbody');
  const mobContainer = $('#dash-mobile-cards-container');
  
  if (thead) {
    thead.innerHTML = `<tr><th>Gimnasio & Dueño</th><th>Teléfono / WhatsApp</th><th>Pago Mensual</th><th>Vencimiento</th><th>Estado</th><th style="text-align:right">Acción</th></tr>`;
  }
  if (tb) tb.innerHTML = '';
  if (mobContainer) mobContainer.innerHTML = '';

  if (!items || !items.length) {
    const emptyMsg = '¡Excelente! Todos los dueños de gimnasios están al día 🎉';
    if (tb) tb.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--t-mut);padding:18px">${emptyMsg}</td></tr>`;
    if (mobContainer) mobContainer.innerHTML = `<div style="text-align:center;color:var(--t-mut);padding:24px 16px;background:rgba(255,255,255,0.02);border:1px dashed var(--border);border-radius:12px">${emptyMsg}</div>`;
    return;
  }

  items.forEach(g => {
    const isSusp = g.suscripcion_estado === 'suspendido';
    const isProx = g.suscripcion_estado === 'proximo';
    const badgeCls = isSusp ? 'b-bad' : (isProx ? 'b-warn pulse' : (g.suscripcion_estado === 'vencido' ? 'b-bad' : 'b-ok'));
    const badgeTxt = isSusp ? '⛔ SUSPENDIDO' : (isProx ? '⚠️ PRÓXIMO' : (g.suscripcion_estado === 'vencido' ? '🔴 VENCIDO' : '🟢 AL DÍA'));

    const telClean = (g.telefono || '').replace(/\D/g, '');
    const waUrl = telClean ? `https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(g.dueno_usuario || g.nombre)},%20te%20escribimos%20de%20GYM%20PRO%20SaaS%20respecto%20a%20la%20suscripci%C3%B3n%20mensual%20de%20tu%20gimnasio%20(${encodeURIComponent(g.nombre)}).` : '';
    const waBtn = telClean ? `<a href="${waUrl}" target="_blank" class="btn btn-sm btn-secondary" title="Cobrar por WhatsApp" style="padding:6px 10px;font-size:13px;border-radius:8px">💬 WhatsApp</a>` : '';

    const diasTxt = g.dias_restantes !== null ? (g.dias_restantes >= 0 ? `Quedan ${g.dias_restantes} días` : `Venció hace ${Math.abs(g.dias_restantes)} días`) : '';

    // 1. Fila para Desktop / Tablet
    if (tb) {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b>${g.nombre}</b><br><small style="color:#60a5fa">Dueño: ${g.dueno_usuario || 'Sin asignar'}</small></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span>${g.telefono || '-'}</span>
            ${waBtn}
          </div>
        </td>
        <td style="font-weight:700;color:#60a5fa">$ ${fmtMoney(g.suscripcion_monto || 45000)}</td>
        <td><b>${fmtDate(g.suscripcion_vencimiento)}</b><br><small style="color:var(--t-mut)">${diasTxt}</small></td>
        <td><span class="badge ${badgeCls}">${badgeTxt}</span></td>
        <td style="text-align:right;white-space:nowrap">
          <button class="btn btn-sm btn-success" style="font-weight:800;padding:8px 14px" onclick="openSaasPagoModal(${g.id})">💵 Cobrar SaaS</button>
        </td>
      `;
      tb.appendChild(tr);
    }

    // 2. Card Limpia para Teléfonos Móviles (< 768px)
    if (mobContainer) {
      const card = document.createElement('div');
      card.className = 'saas-sub-card-mobile';
      card.innerHTML = `
        <div class="saas-sub-card-header">
          <div>
            <div style="font-size:15.5px;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:6px">
              <span>🏢</span> <span>${g.nombre}</span>
            </div>
            <div style="font-size:12.5px;color:#60a5fa;margin-top:3px;font-weight:600">
              👤 Dueño: <b>${g.dueno_usuario || 'Sin asignar'}</b>
            </div>
          </div>
          <span class="badge ${badgeCls}" style="font-size:11px;padding:4px 9px;font-weight:800">${badgeTxt}</span>
        </div>

        <div class="saas-sub-card-body">
          <div class="saas-sub-row">
            <span class="saas-sub-label">📱 Teléfono / WhatsApp</span>
            <div class="saas-sub-val" style="display:flex;align-items:center;gap:6px">
              <span>${g.telefono || 'Sin registrar'}</span>
              ${telClean ? `<a href="${waUrl}" target="_blank" class="btn btn-xs btn-secondary" style="padding:4px 8px;font-size:11px;border-radius:6px">💬 WhatsApp</a>` : ''}
            </div>
          </div>

          <div class="saas-sub-row">
            <span class="saas-sub-label">💵 Pago Mensual</span>
            <span class="saas-sub-val" style="font-size:14px;font-weight:800;color:#60a5fa">$ ${fmtMoney(g.suscripcion_monto || 45000)} / mes</span>
          </div>

          <div class="saas-sub-row">
            <span class="saas-sub-label">📅 Vencimiento</span>
            <div class="saas-sub-val">
              <b style="color:var(--t1)">${fmtDate(g.suscripcion_vencimiento)}</b>
              ${diasTxt ? `<div style="font-size:11px;color:var(--t-mut);margin-top:1px">${diasTxt}</div>` : ''}
            </div>
          </div>

          <div class="saas-sub-row">
            <span class="saas-sub-label">⚡ Estado</span>
            <span class="saas-sub-val"><span class="badge ${badgeCls}">${badgeTxt}</span></span>
          </div>
        </div>

        <div class="saas-sub-card-footer">
          <button class="btn btn-success" onclick="openSaasPagoModal(${g.id})">
            💵 Cobrar SaaS ($ ${fmtMoney(g.suscripcion_monto || 45000)})
          </button>
        </div>
      `;
      mobContainer.appendChild(card);
    }
  });
}

// 2. Tabla & Cards de Próximos Vencimientos de Alumnos (Para Dueños, Auditoría de Sede y Coaches)
function renderProximosTabla(items) {
  const thead = $('#tbl-prox-thead');
  const tb = $('#tbl-prox tbody') || $('#coach-tbl-prox tbody');
  const mobContainer = $('#dash-mobile-cards-container') || $('#coach-mobile-cards-container');

  if (thead) {
    thead.innerHTML = `<tr><th>Alumno</th><th>Teléfono</th><th>Vence</th><th>Estado</th><th style="text-align:right">Acción</th></tr>`;
  }
  if (tb) tb.innerHTML = '';
  if (mobContainer) mobContainer.innerHTML = '';

  if (!items || !items.length) {
    const emptyMsg = 'No hay vencimientos de alumnos en los próximos 5 días 🎉';
    if (tb) tb.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--t-mut);padding:18px">${emptyMsg}</td></tr>`;
    if (mobContainer) mobContainer.innerHTML = `<div style="text-align:center;color:var(--t-mut);padding:24px 16px;background:rgba(255,255,255,0.02);border:1px dashed var(--border);border-radius:12px">${emptyMsg}</div>`;
    return;
  }

  items.forEach(r => {
    const telClean = (r.telefono || '').replace(/\D/g, '');
    const waUrl = telClean ? `https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(r.nombre)},%20te%20recordamos%20que%20tu%20cuota%20vence%20el%20${fmtDate(r.fecha_vencimiento)}.` : '';
    const waBtn = telClean ? `<a href="${waUrl}" target="_blank" class="btn btn-sm btn-secondary" title="Avisar por WhatsApp" style="padding:6px 10px;font-size:13px;border-radius:8px">💬 WhatsApp</a>` : '';

    // 1. Desktop Row
    if (tb) {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b>${r.nombre}</b></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span>${r.telefono || '-'}</span>
            ${waBtn}
          </div>
        </td>
        <td style="font-weight:700">${fmtDate(r.fecha_vencimiento)}</td>
        <td><span class="badge b-warn pulse">Próximo</span></td>
        <td style="text-align:right"><button class="btn btn-sm btn-primary" onclick="openPagoModal('alumno', ${r.id})">Cobrar</button></td>
      `;
      tb.appendChild(tr);
    }

    // 2. Mobile Card
    if (mobContainer) {
      const card = document.createElement('div');
      card.className = 'saas-sub-card-mobile';
      card.innerHTML = `
        <div class="saas-sub-card-header">
          <div style="font-size:15px;font-weight:800;color:var(--t1)">👤 ${r.nombre}</div>
          <span class="badge b-warn pulse" style="font-size:11px;padding:4px 9px">⚠️ Próximo</span>
        </div>
        <div class="saas-sub-card-body">
          <div class="saas-sub-row">
            <span class="saas-sub-label">📱 Teléfono</span>
            <div class="saas-sub-val" style="display:flex;align-items:center;gap:6px">
              <span>${r.telefono || 'Sin teléfono'}</span>
              ${telClean ? `<a href="${waUrl}" target="_blank" class="btn btn-xs btn-secondary" style="padding:4px 8px;font-size:11px;border-radius:6px">💬 WhatsApp</a>` : ''}
            </div>
          </div>
          <div class="saas-sub-row">
            <span class="saas-sub-label">📅 Vence el</span>
            <span class="saas-sub-val" style="font-weight:800;color:#f59e0b">${fmtDate(r.fecha_vencimiento)}</span>
          </div>
        </div>
        <div class="saas-sub-card-footer">
          <button class="btn btn-primary" onclick="openPagoModal('alumno', ${r.id})">
            💵 Registrar Cobro de Cuota
          </button>
        </div>
      `;
      mobContainer.appendChild(card);
    }
  });
}

/* ===== GENERADOR DE CÓDIGO QR VISUAL PARA CARNET ===== */
function generateQrSvg(dataString, size = 130) {
  let hash = 0;
  for (let i = 0; i < dataString.length; i++) hash = ((hash << 5) - hash) + dataString.charCodeAt(i);
  
  const modules = 21;
  const grid = Array.from({ length: modules }, () => Array(modules).fill(0));

  function drawFinder(r0, c0) {
    for (let r = 0; r < 7; r++) {
      for (let c = 0; c < 7; c++) {
        if (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4)) {
          grid[r0 + r][c0 + c] = 1;
        }
      }
    }
  }
  drawFinder(0, 0);
  drawFinder(0, 14);
  drawFinder(14, 0);

  for (let i = 8; i < 13; i++) {
    grid[6][i] = (i % 2 === 0) ? 1 : 0;
    grid[i][6] = (i % 2 === 0) ? 1 : 0;
  }

  let seed = Math.abs(hash) || 1234567;
  for (let r = 0; r < modules; r++) {
    for (let c = 0; c < modules; c++) {
      const inFinder1 = r < 8 && c < 8;
      const inFinder2 = r < 8 && c >= 13;
      const inFinder3 = r >= 13 && c < 8;
      const inTiming = (r === 6 && c >= 8 && c <= 12) || (c === 6 && r >= 8 && r <= 12);
      if (!inFinder1 && !inFinder2 && !inFinder3 && !inTiming) {
        seed = (seed * 9301 + 49297) % 233280;
        grid[r][c] = (seed / 233280 > 0.45) ? 1 : 0;
      }
    }
  }

  const cellSize = (size / modules).toFixed(2);
  let rects = '';
  for (let r = 0; r < modules; r++) {
    for (let c = 0; c < modules; c++) {
      if (grid[r][c] === 1) {
        rects += `<rect x="${(c * cellSize).toFixed(2)}" y="${(r * cellSize).toFixed(2)}" width="${cellSize}" height="${cellSize}" fill="#0f172a" />`;
      }
    }
  }

  return `
    <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="background:#ffffff;border-radius:10px;padding:6px;box-shadow:0 8px 20px rgba(0,0,0,0.35);display:block;margin:0 auto">
      ${rects}
    </svg>
  `;
}

/* ===== PORTAL DEL ALUMNO (DASHBOARD VS CARNET DIGITAL) ===== */
function renderAlumnoPortal(data) {
  const a = data.alumno || {};
  const isVencido = data.esta_vencido;
  const saldo = data.saldo_deuda;
  const diasRest = a.dias_restantes !== undefined ? a.dias_restantes : null;
  const diasTxt = diasRest !== null ? (diasRest >= 0 ? `Quedan ${diasRest} días` : `Venció hace ${Math.abs(diasRest)} días`) : 'Sin vencimiento';
  const isPlanPro = data.is_plan_pro !== undefined ? Boolean(data.is_plan_pro) : Boolean(CURRENT_USER.is_plan_pro);

  const targetTel = String(a.coach_tel || a.gym_tel || '5492664000000').replace(/\D/g, '');
  const targetLabel = a.coach_nombre ? 'Coach' : 'Gimnasio';

  const msgRutina = a.coach_nombre 
    ? 'Hola profe, ¿cómo está? Le escribo para consultarle si ya tiene lista mi rutina de entrenamiento por favor. Muchas gracias!'
    : 'Hola, ¿cómo están? Les escribo desde la app para consultar por mi rutina de entrenamiento por favor. Muchas gracias!';

  const msgNutri = a.coach_nombre
    ? 'Hola profe, ¿cómo está? Le escribo para consultarle sobre mi plan nutricional cuando pueda por favor. Muchas gracias!'
    : 'Hola, ¿cómo están? Les escribo desde la app para consultar por un plan nutricional por favor. Muchas gracias!';

  const msgGeneral = a.coach_nombre
    ? 'Hola profe, ¿cómo está? Le escribo desde la app del gimnasio.'
    : `Hola, ¿cómo están? Les escribo desde la app de ${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'el gimnasio'}.`;

  const msgDeuda = 'Hola, ¿cómo están? Les escribo desde la app para consultar cómo puedo regularizar el pago de mi cuota. Muchas gracias!';

  let debtBanner = '';
  if (saldo > 0 || isVencido) {
    debtBanner = `
      <div class="debt-banner">
        <div>
          <h3 style="color:#fca5a5;font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px">
            <span>🚨 AVISO DE PAGO PENDIENTE / CUOTA VENCIDA</span>
          </h3>
          <p style="color:#fecaca;font-size:13px;margin-top:4px">
            Tu membresía se encuentra en estado <b>${isVencido ? 'VENCIDA' : 'CON SALDO PENDIENTE'}</b>. Monto adeudado: <b>$ ${fmtMoney(saldo)}</b>.
          </p>
        </div>
        <a href="https://wa.me/${targetTel}?text=${encodeURIComponent(msgDeuda)}" target="_blank" class="btn btn-warn" style="font-weight:800">💬 Regularizar por WhatsApp</a>
      </div>
    `;
  }

  /* -------------------------------------------------------------
   * 1. DASHBOARD DEL ALUMNO (CENTRO DE ENTRENAMIENTO & PROGRESO)
   * ------------------------------------------------------------- */
  const dashHtml = `
    ${debtBanner}

    <!-- HERO BANNER MOTIVACIONAL -->
    <div class="student-hero-banner" style="background:linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.25));border:1px solid rgba(59, 130, 246, 0.35);border-radius:var(--r-lg);padding:24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
          <span class="badge b-purple" style="font-size:11.5px;font-weight:800">🔥 MI CENTRO DE ENTRENAMIENTO</span>
          <span class="badge b-info" style="font-size:11.5px;font-weight:800">🏢 SEDE: ${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'NITSOFT'}</span>
        </div>
        <h2 style="font-size:24px;font-weight:800;color:var(--t1);margin-top:4px">¡Hola, ${a.nombre || CURRENT_USER.name}! 💪</h2>
        <p style="color:var(--t2);font-size:13.5px;margin-top:4px">
          Pertenecés a: <b style="color:#38bdf8">${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'NITSOFT'}</b> • Coach Asignado: <b style="color:#a78bfa">${a.coach_nombre || 'Gimnasio General'}</b>
        </p>
      </div>
      <div class="student-hero-actions" style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" onclick="setPage('mi-membresia')" style="font-weight:800;box-shadow:0 8px 20px rgba(59, 130, 246, 0.4)">
          🪪 Ver Mi Carnet Digital & QR
        </button>
        <a href="https://wa.me/${targetTel}?text=${encodeURIComponent(msgGeneral)}" target="_blank" class="btn btn-secondary">
          💬 WhatsApp ${targetLabel}
        </a>
      </div>
    </div>

    <!-- TARJETAS DE ESTADÍSTICAS DEL ALUMNO (VENCIMIENTO Y CUOTA) -->
    <div class="grid g2 student-stats-grid" style="margin-bottom:16px">
      <div class="stat-card">
        <div class="stat-label">📅 Próx. Vencimiento</div>
        <div class="stat-value" style="font-size:18px;color:${isVencido ? '#ef4444' : '#60a5fa'}">${fmtDate(a.fecha_vencimiento)}</div>
        <div class="stat-sub">
          <span class="badge ${isVencido ? 'b-bad' : (diasRest !== null && diasRest <= 5 ? 'b-warn pulse' : 'b-ok')}" style="font-size:10.5px">
            ${isVencido ? '⛔ Vencido' : diasTxt}
          </span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">💳 Estado de Cuota</div>
        <div class="stat-value" style="font-size:18px;color:${isVencido ? '#ef4444' : '#10b981'}">${isVencido ? 'Pendiente' : 'Al Día'}</div>
        <div class="stat-sub">
          <span class="badge ${isVencido ? 'b-bad' : 'b-ok'}" style="font-size:10.5px">${isVencido ? '⛔ Con Mora' : '✅ Habilitado'}</span>
        </div>
      </div>
    </div>

    <!-- TARJETA DESTACADA: PLAN CONTRATADO & SEDE -->
    <div class="card" style="margin-bottom:18px;background:linear-gradient(135deg, var(--bg-card), rgba(59,130,246,0.04));border:1px solid var(--border);padding:16px 20px">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:42px;height:42px;border-radius:12px;background:rgba(139,92,246,0.14);display:flex;align-items:center;justify-content:center;font-size:22px">🏷️</div>
          <div>
            <div style="font-size:11px;color:var(--t-mut);font-weight:700;text-transform:uppercase;letter-spacing:0.5px">Plan Contratado</div>
            <div style="font-size:18px;font-weight:800;color:var(--t1)">Plan ${(a.plan || '3x').toUpperCase()}</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span class="badge b-purple" style="font-size:12.5px;font-weight:800;padding:6px 14px">$ ${fmtMoney(data.cuota)} / mes</span>
          <button class="btn btn-sm btn-secondary" onclick="setPage('mi-membresia')" style="font-weight:700">🪪 Ver Carnet Digital →</button>
        </div>
      </div>
      <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;align-items:center;font-size:12.5px;color:var(--t2);flex-wrap:wrap;gap:8px">
        <div>🎯 Actividades: <b style="color:var(--t1)">${a.actividades || 'Musculación, Funcional'}</b></div>
        <div>🏋️ Coach a Cargo: <b style="color:#a78bfa">${a.coach_nombre || 'Gimnasio General'}</b></div>
      </div>
    </div>

    <!-- ACCESO RÁPIDO A RUTINA Y NUTRICIÓN -->
    <div class="grid g2" style="margin-bottom:20px">
      <div class="card">
        <div class="card-header" style="justify-content:space-between">
          <div class="card-title" style="display:flex;align-items:center;gap:8px">
            <span>💪 Tu Rutina de Entrenamiento</span>
            <span class="badge ${data.rutina || data.programa_activo ? 'b-ok' : 'b-warn'}">${data.rutina || data.programa_activo ? 'Activa' : 'Pendiente'}</span>
          </div>
          <button class="btn btn-sm btn-primary" onclick="setPage('mi-rutina')">Ver Rutina Completa →</button>
        </div>
        <div>
          ${data.programa_activo ? `
            <div style="margin-bottom:10px">
              <strong style="color:var(--t1);font-size:16px">${data.programa_activo.titulo}</strong>
              <div style="font-size:12px;color:var(--t2);margin-top:2px">Nivel: <b>${data.programa_activo.nivel || 'Intermedio'}</b> • Frecuencia: <b>${data.programa_activo.dias ? data.programa_activo.dias.length + ' Días' : 'Semanal'}</b></div>
            </div>
            <div style="background:var(--bg-inp);padding:14px;border-radius:10px;font-size:13px;border:1px solid var(--border)">
              🎯 Enfoque: <b>${data.programa_activo.objetivo || 'Entrenamiento Personalizado'}</b>
            </div>
          ` : (data.rutina ? `
            <div style="margin-bottom:10px">
              <strong style="color:var(--t1);font-size:16px">${data.rutina.titulo}</strong>
              <div style="font-size:12px;color:var(--t2);margin-top:2px">Días: <b>${data.rutina.dias_semana || 'Lunes a Viernes'}</b> • Objetivo: <b>${data.rutina.objetivo || 'Ganancia'}</b></div>
            </div>
            <div style="background:var(--bg-inp);padding:14px;border-radius:10px;font-size:13px;max-height:140px;overflow-y:auto;line-height:1.6;white-space:pre-wrap;border:1px solid var(--border)">
              ${data.rutina.detalles}
            </div>
          ` : `
            <div style="text-align:center;padding:30px;color:var(--t-mut)">
              <div style="font-size:28px;margin-bottom:6px">📋</div>
              <p>Tu coach todavía no cargó una rutina personalizada.</p>
              <a href="https://wa.me/${targetTel}?text=${encodeURIComponent(msgRutina)}" target="_blank" class="btn btn-sm btn-secondary" style="margin-top:10px">💬 Solicitar Rutina a ${targetLabel}</a>
            </div>
          `)}
        </div>
      </div>

      ${isPlanPro ? `
      <div class="card">
        <div class="card-header" style="justify-content:space-between">
          <div class="card-title" style="display:flex;align-items:center;gap:8px">
            <span>🥗 Plan Nutricional</span>
            <span class="badge ${data.nutricion ? 'b-purple' : 'b-warn'}">${data.nutricion ? 'Asignado' : 'Sin plan'}</span>
          </div>
          <button class="btn btn-sm btn-secondary" onclick="setPage('mi-nutricion')">Ver Plan Completo →</button>
        </div>
        <div>
          ${data.nutricion ? `
            <div style="margin-bottom:10px">
              <strong style="color:var(--t1);font-size:16px">${data.nutricion.titulo}</strong>
              <div style="font-size:12px;color:var(--t2);margin-top:2px">Meta Energética: <b style="color:#38bdf8">${data.nutricion.calorias_aprox} kcal / día</b></div>
            </div>
            <div style="background:var(--bg-inp);padding:14px;border-radius:10px;font-size:13px;max-height:140px;overflow-y:auto;line-height:1.6;white-space:pre-wrap;border:1px solid var(--border)">
              ${data.nutricion.detalles}
            </div>
          ` : `
            <div style="text-align:center;padding:30px;color:var(--t-mut)">
              <div style="font-size:28px;margin-bottom:6px">🥗</div>
              <p>No tenés un plan de comidas activo actualmente.</p>
              <a href="https://wa.me/${targetTel}?text=${encodeURIComponent(msgNutri)}" target="_blank" class="btn btn-sm btn-secondary" style="margin-top:10px">💬 Consultar Plan a ${targetLabel}</a>
            </div>
          `}
        </div>
      </div>
      ` : `
      <div class="card" style="background:rgba(15,23,42,0.4);border:1px dashed var(--border)">
        <div class="card-header" style="justify-content:space-between">
          <div class="card-title" style="display:flex;align-items:center;gap:8px;color:var(--t-mut)">
            <span>🥗 Plan Nutricional</span>
            <span class="badge" style="background:#334155;color:#94a3b8;font-size:10px">NO DISPONIBLE</span>
          </div>
          <button class="btn btn-sm btn-secondary" onclick="setPage('mi-nutricion')" style="opacity:0.7">Ver Info →</button>
        </div>
        <div style="text-align:center;padding:26px 16px;color:var(--t-mut)">
          <div style="font-size:26px;margin-bottom:6px;opacity:0.6">🔒</div>
          <p style="font-size:13px;margin:0">Módulo no contratado en la sede actual.</p>
          <small style="color:var(--t2)">Tu gimnasio cuenta con el plan estándar de entrenamiento.</small>
        </div>
      </div>
      `}
    </div>
  `;

  const dashEl = $('#alu-portal-dashboard') || $('#alumno-dashboard-container');
  if (dashEl) dashEl.innerHTML = dashHtml;

  /* -------------------------------------------------------------
   * 2. MI CARNET DIGITAL (CREDENCIAL VIP CON CÓDIGO QR DE ACCESO)
   * ------------------------------------------------------------- */
  const qrSvg = generateQrSvg(`GYM_SOCIO_${a.id || 1}_${a.nombre || 'SOCIO'}_${a.fecha_vencimiento || '2026'}`, 145);

  const carnetHtml = `
    ${debtBanner}

    <div class="student-vip-card-wrapper">
      <!-- CREDENCIAL DIGITAL VIP PASS -->
      <div class="student-vip-card ${isVencido ? 'vencido' : ''}">
        
        <!-- CABECERA DE LA TARJETA CON LOGO & SEDE -->
        <div class="student-vip-header">
          <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:28px">🏋️</span>
            <div>
              <div class="student-vip-gym-name">${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'NITSOFT GYM'}</div>
              <div class="student-vip-gym-sub">Pase Digital Oficial de Socio</div>
            </div>
          </div>
          <span class="badge ${isVencido ? 'b-bad' : 'b-purple'}" style="font-size:11px;padding:6px 14px;font-weight:800;letter-spacing:0.5px">
            ${isVencido ? '⛔ CUOTA VENCIDA' : '⭐ MEMBRESÍA VIP'}
          </span>
        </div>

        <!-- CUERPO DE LA TARJETA: 2 COLUMNAS EN PC (IZQ DATOS, DER QR), 1 COLUMNA EN CELULAR -->
        <div class="student-vip-body-grid">
          <!-- COLUMNA IZQUIERDA: DATOS DEL SOCIO -->
          <div class="student-vip-info-col">
            <div class="student-vip-profile">
              <div class="student-vip-avatar">
                ${(a.nombre || CURRENT_USER.name).substring(0,1).toUpperCase()}
              </div>
              <div style="min-width:0;flex:1">
                <h2 class="student-vip-name">
                  ${a.nombre || CURRENT_USER.name}
                </h2>
                <div class="student-vip-socio-id">
                  SOCIO #SOC-${String(a.id || 1).padStart(5, '0')} ${a.dni ? `• DNI: ${a.dni}` : ''}
                </div>
              </div>
            </div>

            <!-- GRILLA DE PÍLDORAS INFORMATIVAS (2 COLUMNAS RESPONSIVAS) -->
            <div class="student-vip-pills">
              <div class="student-vip-pill">
                <span class="pill-label">Plan Contratado</span>
                <b class="pill-val">Plan ${(a.plan || '3x').toUpperCase()}</b>
                <small class="pill-sub" style="color:#38bdf8">$ ${fmtMoney(data.cuota)} / mes</small>
              </div>

              <div class="student-vip-pill">
                <span class="pill-label">Vencimiento Cuota</span>
                <b class="pill-val" style="color:${isVencido ? '#f87171' : '#60a5fa'}">${fmtDate(a.fecha_vencimiento)}</b>
                <small class="pill-sub" style="color:${isVencido ? '#ef4444' : '#34d399'}">${isVencido ? '⛔ Renovación Requerida' : diasTxt}</small>
              </div>

              <div class="student-vip-pill">
                <span class="pill-label">Entrenador / Coach</span>
                <b class="pill-val" style="color:#c084fc;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.coach_nombre || 'Gimnasio General'}</b>
                <small class="pill-sub">${a.coach_tel ? '📲 Enlace Activo' : 'Sede General'}</small>
              </div>

              <div class="student-vip-pill">
                <span class="pill-label">Actividades</span>
                <b class="pill-val" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.actividades || 'Musculación'}</b>
                <small class="pill-sub" style="color:#34d399">Habilitado</small>
              </div>
            </div>

            <!-- BARRA LUMINOSA DE ESTADO DE ACCESO -->
            <div>
              <span class="badge ${isVencido ? 'b-bad' : 'b-ok pulse'}" style="font-size:12px;padding:8px 16px;font-weight:900;letter-spacing:0.4px;border-radius:24px;display:inline-block">
                ${isVencido ? '⛔ ACCESO DENEGADO (PAGO PENDIENTE)' : '🟢 ACCESO HABILITADO / AL DÍA'}
              </span>
            </div>
          </div>

          <!-- COLUMNA DERECHA: CÓDIGO QR -->
          <div class="student-vip-qr-col">
            <div class="student-vip-qr-container">
              ${qrSvg}
              <div class="student-vip-qr-label">📱 ESCANEAR EN ACCESO</div>
            </div>
          </div>
        </div>

        <!-- FOOTER DE LA TARJETA -->
        <div class="student-vip-footer">
          <div style="display:flex;align-items:center;gap:6px;color:#94a3b8;font-size:12px">
            <span>🏢 Sede Oficial:</span>
            <b style="color:#f8fafc">${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'NITSOFT'}</b>
          </div>
          <div style="display:flex;align-items:center;gap:6px;color:#94a3b8;font-size:12px">
            <span>Estado de Ingreso:</span>
            <b style="color:${isVencido ? '#f87171' : '#34d399'}">${isVencido ? '⛔ Vencido' : '✅ Habilitado'}</b>
          </div>
        </div>

      </div>

      <!-- INSTRUCCIÓN DE USO EN RECEPCIÓN -->
      <div style="text-align:center;margin-top:18px;font-size:13px;color:var(--t2);padding:0 8px">
        💡 Mostrá este carnet en la recepción o molinete de <b>${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'tu gimnasio'}</b> para registrar tu ingreso.
      </div>
    </div>
  `;

  const carnetEl = $('#alu-portal-membresia') || $('#alumno-portal-carnet');
  if (carnetEl) carnetEl.innerHTML = carnetHtml;

  // Render Rutina del Alumno (Arquitectura Relacional con Bloques y Checkboxes)
  const rutinaEl = $('#alu-portal-rutina') || $('#alumno-portal-rutina');
  if (rutinaEl) {
    const prog = data.programa_activo;
    const checkins = data.rutina_checkins || [];
    let checkinsRows = '';
    let checkinsMobileCardsHtml = '';
    if (!checkins.length) {
      checkinsRows = '<tr><td colspan="5" style="text-align:center;color:var(--t-mut);padding:20px">Aún no registraste entrenamientos este mes. ¡Hacé check-in al terminar tu sesión!</td></tr>';
      checkinsMobileCardsHtml = `<div style="text-align:center;padding:24px;color:var(--t-mut);font-size:13px">Aún no registraste entrenamientos este mes. ¡Hacé check-in al terminar tu sesión!</div>`;
    } else {
      checkins.forEach(c => {
        const stars = '⭐'.repeat(c.nivel_esfuerzo || 3);
        const feedbackHtml = c.coach_feedback 
          ? `<div style="background:rgba(139,92,246,0.12);border-left:3px solid #a855f7;padding:6px 10px;border-radius:4px;font-size:12px;color:#c084fc"><b>💬 Coach:</b> ${c.coach_feedback}</div>` 
          : `<span style="color:var(--t-mut);font-size:11.5px">Esperando devolución...</span>`;

        checkinsRows += `
          <tr>
            <td><b>${fmtDate(c.fecha)}</b><br><small style="color:var(--t2)">${c.hora || ''}</small></td>
            <td><b style="color:var(--t1)">${c.rutina_nombre || 'Rutina'}</b></td>
            <td><span class="badge b-info">${c.duracion_min || 60} min</span><br><small title="Esfuerzo">${stars}</small></td>
            <td><span style="font-size:12.5px;color:var(--t2)">${c.observaciones || 'Sesión completada'}</span></td>
            <td>${feedbackHtml}</td>
          </tr>
        `;

        const mobileFeedbackHtml = c.coach_feedback 
          ? `<div style="background:rgba(139,92,246,0.12);border-left:3px solid #a855f7;padding:8px 10px;border-radius:6px;font-size:12px;color:#c084fc;margin-top:6px"><b>💬 Devolución del Coach:</b> ${c.coach_feedback}</div>` 
          : `<div style="font-size:11.5px;color:var(--t-mut);margin-top:4px">Esperando devolución del coach...</div>`;

        checkinsMobileCardsHtml += `
          <div class="mobile-card" style="background:var(--bg-inp);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:6px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
              <b style="font-size:14.5px;color:var(--t1)">${c.rutina_nombre || 'Sesión de Entrenamiento'}</b>
              <span class="badge b-info" style="font-size:10.5px;padding:2px 8px;font-weight:700">${c.duracion_min || 60} min</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--t2)">
              <span>📅 ${fmtDate(c.fecha)} ${c.hora ? '• ' + c.hora : ''}</span>
              <span title="Esfuerzo">${stars}</span>
            </div>
            ${c.observaciones ? `<div style="font-size:12px;color:var(--t2);background:rgba(255,255,255,0.03);padding:6px 10px;border-radius:6px">📝 ${c.observaciones}</div>` : ''}
            ${mobileFeedbackHtml}
          </div>
        `;
      });
    }

    const checkinsHistoryCardHtml = `
      <!-- Historial de Entrenamientos Realizados por el Alumno -->
      <div class="card student-checkins-card" style="margin-top:20px">
        <div class="card-header student-checkins-header" style="display:flex;flex-direction:column;align-items:center;text-align:center;justify-content:center;border-bottom:1px solid var(--border);padding-bottom:12px;margin-bottom:14px;gap:6px">
          <div style="display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:17px;font-weight:800;color:var(--t1)">📜 Historial de Entrenamientos</span>
            <span class="badge b-ok" style="font-size:11px;padding:3px 9px;font-weight:800">${checkins.length} Sesiones</span>
          </div>
          <p style="color:var(--t2);font-size:12px;margin:0;text-align:center">Registro de tus check-ins con devoluciones técnicas de tu coach.</p>
        </div>
        <div class="tbl-wrap desk-table-container">
          <table class="tbl" id="tbl-alumno-checkins-history">
            <thead><tr><th>Fecha / Hora</th><th>Rutina Entrenada</th><th>Duración / RPE</th><th>Mis Notas</th><th>Devolución de Mi Coach</th></tr></thead>
            <tbody>${checkinsRows}</tbody>
          </table>
        </div>
        <div class="mobile-cards-container" style="display:none;flex-direction:column;gap:10px">
          ${checkinsMobileCardsHtml}
        </div>
      </div>
    `;

    const statsHeaderHtml = `
      <!-- STATS DE DÍAS Y CONSTANCIA DEL ALUMNO -->
      <div class="grid g3" style="gap:12px;margin-bottom:16px">
        <div class="stat-card" style="padding:14px">
          <div class="stat-label">🗓️ Días Restantes de Cuota</div>
          <div class="stat-value" style="font-size:20px;color:${isVencido ? '#ef4444' : '#60a5fa'}">${data.dias_restantes !== null ? (data.dias_restantes >= 0 ? data.dias_restantes + ' Días' : 'Vencido') : '-'}</div>
          <div class="stat-sub">${isVencido ? 'Cuota a renovar' : 'Acceso habilitado'}</div>
        </div>
        <div class="stat-card" style="padding:14px">
          <div class="stat-label">🏋️ Días Entrenados este Mes</div>
          <div class="stat-value" style="font-size:20px;color:var(--ok)">${data.total_checkins_mes || 0} Sesiones</div>
          <div class="stat-sub">${data.asistencias_mes || 0} asistencias registradas</div>
        </div>
        <div class="stat-card" style="padding:14px">
          <div class="stat-label">🔥 Racha de Entrenamientos</div>
          <div class="stat-value" style="font-size:20px;color:#f59e0b">${data.racha_dias || 0} Días Seguidos</div>
          <div class="stat-sub">¡Mantené la constancia!</div>
        </div>
      </div>
    `;

    if (prog && prog.dias && prog.dias.length) {
      let diasTabsHtml = '';
      let diasContentHtml = '';

      prog.dias.forEach((d, dIdx) => {
        const isFirst = dIdx === 0;
        diasTabsHtml += `
          <button type="button" class="btn-day-tab ${isFirst ? 'active' : ''} btn-student-dia-tab" onclick="switchStudentDia(${dIdx})" id="tab-student-dia-${dIdx}">
            <b class="day-tab-title">Día ${dIdx + 1}</b>
            <small class="day-tab-badge">${d.ejercicios?.length || 0} ejs</small>
          </button>
        `;

        const bloques = {
          calentamiento: (d.ejercicios || []).filter(e => e.bloque === 'calentamiento'),
          principal:     (d.ejercicios || []).filter(e => e.bloque === 'principal'),
          cardio:        (d.ejercicios || []).filter(e => e.bloque === 'cardio'),
          vuelta_calma:  (d.ejercicios || []).filter(e => e.bloque === 'vuelta_calma')
        };

        let blocksHtml = '';
        Object.keys(BLOQUE_INFO).forEach(bKey => {
          const bInfo = BLOQUE_INFO[bKey];
          const ejs = bloques[bKey] || [];
          if (!ejs.length) return;

          let ejRows = '';
          ejs.forEach((ej, ejIdx) => {
            const checkId = `chk-ej-${dIdx}-${bKey}-${ejIdx}`;
            ejRows += `
              <div class="student-ej-card" style="background:var(--bg-inp);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:10px 12px;margin-bottom:6px;display:flex;align-items:flex-start;gap:10px;width:100%;box-sizing:border-box;transition:var(--tr)">
                <input type="checkbox" id="${checkId}" style="width:20px;height:20px;margin-top:2px;cursor:pointer;accent-color:#10b981;flex-shrink:0" onchange="toggleEjComplete(this, '${checkId}-box')">
                <div id="${checkId}-box" style="flex:1;min-width:0">
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap">
                    <b style="font-size:14.5px;color:var(--t1);word-break:break-word">${ej.ejercicio_nombre}</b>
                    <span class="badge ${getGrupoBadgeClass(ej.grupo_muscular)}" style="font-size:10px;font-weight:800;text-transform:uppercase">${ej.grupo_muscular || 'Músculo'}</span>
                  </div>
                  ${ej.notas ? `<p style="color:var(--t2);font-size:12px;margin:3px 0">💡 ${ej.notas}</p>` : ''}
                  <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:5px">
                    <span class="badge b-purple" style="font-weight:700;font-size:10.5px">📊 ${ej.series} Series</span>
                    <span class="badge b-ok" style="font-weight:700;font-size:10.5px">🔁 ${ej.repeticiones} Reps</span>
                    <span class="badge b-warn" style="font-weight:700;font-size:10.5px">⏱️ ${ej.descanso_seg ? ej.descanso_seg + 's' : '60s'} Descanso</span>
                    ${ej.carga_sugerida ? `<span class="badge b-info" style="font-weight:700;font-size:10.5px">🏋️ ${ej.carga_sugerida}</span>` : ''}
                  </div>
                </div>
              </div>
            `;
          });

          const isCollapsed = Boolean(_studentBlocksCollapsed[bKey]);
          blocksHtml += `
            <div style="background:${bInfo.bg};border:1px solid ${bInfo.border};border-radius:14px;overflow:hidden;margin-bottom:12px;box-shadow:0 4px 15px rgba(0,0,0,0.15)">
              <div onclick="toggleStudentBlock('${bKey}')" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:${bInfo.headerBg};border-bottom:1px solid ${bInfo.border};cursor:pointer;user-select:none;flex-wrap:wrap;gap:6px">
                <div style="display:flex;align-items:center;gap:8px">
                  <span class="student-block-chev-${bKey}" style="font-size:13px;color:${bInfo.color};font-weight:900;transition:transform 0.25s;display:inline-block;transform:${isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)'}">▼</span>
                  <span style="font-size:18px">${bInfo.icon}</span>
                  <b style="font-size:14px;color:var(--t1);letter-spacing:0.2px">${bInfo.label}</b>
                  <span class="badge ${bInfo.badge}" style="font-size:10px">${ejs.length} Ejercicio${ejs.length === 1 ? '' : 's'}</span>
                </div>
                <span style="font-size:11px;color:${bInfo.color};font-weight:700">▼ Desplegar / Ocultar</span>
              </div>
              <div class="student-block-body-${bKey}" style="padding:10px 8px;display:${isCollapsed ? 'none' : 'flex'};flex-direction:column;gap:6px;transition:all 0.25s ease">
                ${ejRows}
              </div>
            </div>
          `;
        });

        if (!blocksHtml) {
          blocksHtml = `<div style="text-align:center;padding:30px;color:var(--t-mut)">Este día no tiene ejercicios asignados aún.</div>`;
        }

        diasContentHtml += `
          <div id="student-dia-pane-${dIdx}" class="student-dia-pane" style="display:${isFirst ? 'block' : 'none'}">
            <div style="background:rgba(59, 130, 246, 0.08);border:1px solid rgba(59, 130, 246, 0.2);border-radius:12px;padding:12px 14px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
              <div>
                <b style="color:var(--t1);font-size:14px">🎯 Enfoque de la Sesión:</b>
                <span style="color:#38bdf8;font-weight:800;margin-left:6px">${d.enfoque || d.nombre_dia}</span>
                <div style="font-size:11.5px;color:var(--t2);margin-top:2px">Tildá los ejercicios que completes y luego registrá tu check-in 💪</div>
              </div>
              <button type="button" class="btn btn-success" style="font-weight:800;box-shadow:0 4px 14px rgba(16,185,129,0.4)" onclick='openCheckinModal("${(d.nombre_dia || 'Día ' + (dIdx + 1)).replace(/"/g, '&quot;')}", ${d.id || 0}, ${prog.id || 0})'>
                ✅ Marcar Sesión Realizada
              </button>
            </div>
            ${blocksHtml}
          </div>
        `;
      });

      const fullProgHtml = `
        ${statsHeaderHtml}

        <div class="card student-routine-card" style="margin-bottom:20px">
          <div class="card-header student-routine-header" style="flex-wrap:wrap;gap:12px;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:14px">
            <div style="flex:1;min-width:240px">
              <div class="student-routine-badges" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;flex-wrap:wrap">
                <span class="badge b-ok" style="font-size:10.5px;padding:3px 9px;font-weight:800">Rutina Activa</span>
                <span class="badge b-purple" style="font-size:10.5px;padding:3px 9px;font-weight:800">${(prog.nivel || 'Intermedio').toUpperCase()}</span>
                <span class="badge b-info" style="font-size:10.5px;padding:3px 9px;font-weight:800">${prog.objetivo || 'Hipertrofia'}</span>
              </div>
              <h2 class="student-routine-title" style="font-size:20px;font-weight:800;color:var(--t1);margin:4px 0">${prog.titulo}</h2>
              <p class="student-routine-sub" style="color:var(--t2);font-size:12.5px;margin:2px 0 0 0">
                ${prog.coach_nombre ? `Asignada por Coach: <b>${prog.coach_nombre}</b> • ` : ''}Frecuencia: <b>${prog.dias.length} Días de entrenamiento</b>
              </p>
            </div>
            <div class="student-routine-actions" style="display:flex;gap:8px">
              <button class="btn btn-sm btn-secondary" onclick="window.print()">🖨️ Imprimir Rutina</button>
            </div>
          </div>

          <!-- Pestañas de Días en Carrusel de Cuadraditos -->
          <div class="days-carousel-container">
            ${diasTabsHtml}
          </div>

          <!-- Barra de Acciones de Bloques (Minimizar / Expandir) -->
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:12px;flex-wrap:wrap;gap:10px">
            <span style="font-size:12px;color:var(--t2);font-weight:700">Controles de visualización:</span>
            <div class="routine-toggle-actions">
              <button type="button" class="btn btn-secondary btn-routine-toggle" onclick="toggleAllStudentBlocks(true)" title="Minimizar todos los bloques">
                ⇲ Minimizar Todo
              </button>
              <button type="button" class="btn btn-secondary btn-routine-toggle" onclick="toggleAllStudentBlocks(false)" title="Expandir todos los bloques">
                ⇱ Expandir Todo
              </button>
            </div>
          </div>

          <!-- Contenido de Días -->
          <div id="student-dias-container">
            ${diasContentHtml}
          </div>
        </div>

        ${checkinsHistoryCardHtml}
      `;
      rutinaEl.innerHTML = fullProgHtml;
    } else if (data.rutina) {
      const legacyHtml = `
        ${statsHeaderHtml}
        <div class="card">
          <div class="card-header" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div>
              <span class="badge b-ok">Rutina Activa</span>
              <h2 style="font-size:20px;font-weight:800;margin-top:6px">${data.rutina.titulo}</h2>
              <p style="color:var(--t2);font-size:13px">Objetivo: <b>${data.rutina.objetivo}</b> | Días: <b>${data.rutina.dias_semana}</b> | Asignada: <b>${fmtDate(data.rutina.fecha_asignacion)}</b></p>
            </div>
            <button type="button" class="btn btn-success btn-sm" onclick='openCheckinModal("${(data.rutina.titulo || 'Entrenamiento').replace(/"/g, '&quot;')}")'>
              ✅ Marcar Sesión Realizada
            </button>
          </div>
          <div style="background:var(--bg-inp);padding:18px;border-radius:12px;white-space:pre-wrap;font-size:14px;line-height:1.6">${data.rutina.detalles}</div>
        </div>
        ${checkinsHistoryCardHtml}
      `;
      rutinaEl.innerHTML = legacyHtml;
    } else {
      const emptyRutinaHtml = `
        ${statsHeaderHtml}
        <div class="card" style="text-align:center;padding:40px;color:var(--t-mut)">
          <div style="font-size:36px;margin-bottom:8px">🏋️</div>
          <h3 style="color:var(--t1);font-size:18px;font-weight:800">Tu coach aún no cargó una rutina personalizada</h3>
          <p style="margin-top:6px;max-width:480px;margin-left:auto;margin-right:auto">¡Solicitásela a tu coach en tu próxima visita o podés registrar tus entrenamientos libres haciendo check-in!</p>
          <div style="margin-top:14px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <button type="button" class="btn btn-primary btn-sm" onclick='openCheckinModal("Entrenamiento Libre")'>
              ✅ Registrar Entrenamiento Libre
            </button>
            <a href="https://wa.me/${targetTel}?text=${encodeURIComponent(msgRutina)}" target="_blank" class="btn btn-secondary btn-sm">
              💬 Solicitar Rutina a ${targetLabel}
            </a>
          </div>
        </div>
        ${checkinsHistoryCardHtml}
      `;
      rutinaEl.innerHTML = emptyRutinaHtml;
    }
  }

  // Render Nutrición del Alumno
  const nutriEl = $('#alu-portal-nutricion') || $('#alumno-portal-nutri');
  if (nutriEl) {
    if (!isPlanPro) {
      const blockedNutriHtml = `
        <div class="card" style="text-align:center;padding:50px 20px;border:1px dashed rgba(255,255,255,0.15)">
          <div style="font-size:48px;margin-bottom:12px">🔒</div>
          <h3 style="color:var(--t1);font-size:20px;font-weight:800">Módulo de Nutrición no disponible</h3>
          <p style="color:var(--t2);max-width:500px;margin:8px auto 0;font-size:13.5px;line-height:1.6">
            El servicio de planes nutricionales personalizados no está habilitado actualmente en el plan de tu sede (<b>${a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'tu gimnasio'}</b>).
          </p>
          <div style="margin-top:16px">
            <span class="badge" style="background:#334155;color:#94a3b8;font-size:12px;padding:6px 14px;font-weight:700">
              🏢 Exclusivo para sedes con Plan PRO Activo
            </span>
          </div>
        </div>
      `;
      nutriEl.innerHTML = blockedNutriHtml;
    } else if (data.nutricion) {
      const nutriHtml = `
        <div class="card">
          <div class="card-header">
            <div>
              <span class="badge b-purple">Plan Nutricional</span>
              <h2 style="font-size:20px;font-weight:800;margin-top:6px">${data.nutricion.titulo}</h2>
              <p style="color:var(--t2);font-size:13px">Calorías Objetivo: <b>${data.nutricion.calorias_aprox} kcal / día</b> | Asignado: <b>${fmtDate(data.nutricion.fecha_asignacion)}</b></p>
            </div>
          </div>
          <div style="background:var(--bg-inp);padding:18px;border-radius:12px;white-space:pre-wrap;font-size:14px;line-height:1.6">${data.nutricion.detalles}</div>
        </div>`;
      nutriEl.innerHTML = nutriHtml;
    } else {
      const emptyNutriHtml = `
        <div class="card" style="text-align:center;padding:40px;color:var(--t-mut)">
          <div style="font-size:36px;margin-bottom:8px">🥗</div>
          <h3 style="color:var(--t1);font-size:18px;font-weight:800">No tenés un plan de comidas activo</h3>
          <p style="margin-top:6px;max-width:480px;margin-left:auto;margin-right:auto">Tu coach o nutricionista puede asignarte un plan nutricional a medida.</p>
          <a href="https://wa.me/${targetTel}?text=${encodeURIComponent(msgNutri)}" target="_blank" class="btn btn-secondary btn-sm" style="margin-top:12px;display:inline-flex">
            💬 Consultar Plan a ${targetLabel}
          </a>
        </div>`;
      nutriEl.innerHTML = emptyNutriHtml;
    }
  }

  // Render Mis Pagos & Tarjetas de Membresía
  if ($('#alu-pagos-estado-val')) {
    $('#alu-pagos-estado-val').textContent = isVencido ? '⛔ Vencido' : '✅ Al Día';
    $('#alu-pagos-estado-val').style.color = isVencido ? 'var(--err)' : 'var(--ok)';
  }
  if ($('#alu-pagos-estado-sub')) {
    $('#alu-pagos-estado-sub').textContent = isVencido ? `Saldo pendiente: $ ${fmtMoney(saldo)}` : 'Habilitado para ingresar';
  }
  if ($('#alu-pagos-dias-val')) {
    $('#alu-pagos-dias-val').textContent = data.dias_restantes !== null ? (data.dias_restantes >= 0 ? data.dias_restantes + ' Días' : 'Vencido') : '-';
    $('#alu-pagos-dias-val').style.color = isVencido ? 'var(--err)' : (data.dias_restantes !== null && data.dias_restantes <= 5 ? '#f59e0b' : '#60a5fa');
  }
  if ($('#alu-pagos-dias-sub')) {
    $('#alu-pagos-dias-sub').textContent = data.dias_restantes !== null && data.dias_restantes >= 0 ? 'Restantes de membresía' : 'Renovación requerida';
  }
  if ($('#alu-pagos-venc-val')) {
    $('#alu-pagos-venc-val').textContent = fmtDate(a.fecha_vencimiento);
  }

  let totalInvertidoAlu = 0;
  const pagosList = data.mis_pagos || [];
  pagosList.forEach(p => { totalInvertidoAlu += parseFloat(p.monto || 0); });

  if ($('#alu-pagos-total-val')) $('#alu-pagos-total-val').textContent = '$ ' + fmtMoney(totalInvertidoAlu);
  if ($('#alu-pagos-count-sub')) $('#alu-pagos-count-sub').textContent = `${pagosList.length} cuotas abonadas`;

  if ($('#tbl-mis-pagos tbody')) {
    const tb = $('#tbl-mis-pagos tbody');
    const mobCont = $('#mis-pagos-mobile-cards');
    tb.innerHTML = '';
    if (mobCont) mobCont.innerHTML = '';

    if (!pagosList.length) {
      tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron pagos registrados en tu historial.</td></tr>';
      if (mobCont) mobCont.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No hay pagos registrados.</div>';
    } else {
      pagosList.forEach(p => {
        const pObj = {
          id: p.id,
          alumno: a.nombre || CURRENT_USER.name,
          gym_nombre: a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'NITSOFT',
          plan: p.plan || a.plan || '3x',
          fecha_pago: p.fecha_pago,
          medio_pago: p.medio_pago,
          monto: p.monto,
          observaciones: p.observaciones,
          fecha_vencimiento: a.fecha_vencimiento
        };

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><b>${fmtDate(p.fecha_pago)}</b></td>
          <td><span class="badge b-info">Plan ${String(p.plan || '3x').toUpperCase()}</span></td>
          <td><span class="badge b-ok" style="text-transform:uppercase">${p.medio_pago || 'Efectivo'}</span></td>
          <td style="text-align:right;font-weight:800;color:var(--ok);font-size:14px">$ ${fmtMoney(p.monto)}</td>
          <td style="color:var(--t2);font-size:12.5px">${p.observaciones || '-'}</td>
          <td style="text-align:right">
            <button type="button" class="btn btn-xs btn-primary btn-recibo-alumno" style="font-weight:700">🧾 Recibo</button>
          </td>
        `;
        const btnRecibo = tr.querySelector('.btn-recibo-alumno');
        if (btnRecibo) btnRecibo.onclick = (e) => { e.preventDefault(); openReciboModal(pObj); };
        tb.appendChild(tr);

        if (mobCont) {
          const card = document.createElement('div');
          card.className = 'mobile-record-card';
          card.innerHTML = `
            <div class="mobile-card-header">
              <span style="font-weight:800;color:var(--t1)">${fmtDate(p.fecha_pago)}</span>
              <span class="badge b-ok" style="font-weight:800">$ ${fmtMoney(p.monto)}</span>
            </div>
            <div class="mobile-card-body">
              <div class="mobile-card-row"><span class="mobile-card-label">Plan</span><b style="color:#60a5fa">Plan ${String(p.plan || '3x').toUpperCase()}</b></div>
              <div class="mobile-card-row"><span class="mobile-card-label">Medio de Pago</span><span style="text-transform:uppercase">${p.medio_pago || 'Efectivo'}</span></div>
              ${p.observaciones ? `<div class="mobile-card-row"><span class="mobile-card-label">Obs</span><span>${p.observaciones}</span></div>` : ''}
            </div>
            <div class="mobile-card-actions" style="margin-top:8px">
              <button type="button" class="btn btn-xs btn-primary btn-mob-recibo-alumno" style="width:100%;font-weight:700">🧾 Ver Comprobante Digital</button>
            </div>
          `;
          const mobBtn = card.querySelector('.btn-mob-recibo-alumno');
          if (mobBtn) mobBtn.onclick = (e) => { e.preventDefault(); openReciboModal(pObj); };
          mobCont.appendChild(card);
        }
      });
    }
  }
}

async function loadAlumnoPortal() {
  const { ok, data } = await api('dashboard.kpis', {}, 'GET');
  if (ok && (data.role === 'alumno' || CURRENT_USER.role === 'alumno')) renderAlumnoPortal(data);
}

/* ===== SAAS GIMNASIOS & DUEÑOS (SUPERADMIN) ===== */
let _saasGymsCache = [];
let _saasGymsCurrentPage = 1;
let _saasGymsPageSize = 15;

async function loadSaasGimnasios() {
  const { ok, data } = await api('saas.gimnasios.list', {}, 'GET');
  if (!ok) return;
  _saasGymsCache = data || [];
  _saasGymsCurrentPage = 1;
  renderSaasGymsTable();
}

function renderSaasGymsTable() {
  const tb = $('#tbl-saas-gyms tbody');
  const mob = $('#saas-gyms-cards') || $('#saas-gyms-mobile-cards');
  if (tb) tb.innerHTML = '';
  if (mob) mob.innerHTML = '';

  const totalRecords = _saasGymsCache.length;
  const pageSize = Number(_saasGymsPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_saasGymsCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = _saasGymsCache.slice(startIdx, endIdx);

  if (!totalRecords) {
    if (tb) tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--t-mut);padding:28px">No hay sedes o gimnasios registrados.</td></tr>';
    if (mob) mob.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No hay sedes registradas.</div>';
  } else {
    pageItems.forEach(g => {
      const isSusp = g.suscripcion_estado === 'suspendido';
      const isProx = g.suscripcion_estado === 'proximo';
      const badgeCls = isSusp ? 'b-bad' : (isProx ? 'b-warn pulse' : (g.suscripcion_estado === 'vencido' ? 'b-bad' : 'b-ok'));
      const badgeTxt = isSusp ? '⛔ SUSPENDIDO' : (isProx ? '⚠️ PRONTO A VENCER' : (g.suscripcion_estado === 'vencido' ? '🔴 VENCIDO' : '🟢 AL DÍA (ACTIVO)'));

      const isPlanPro = g.plan_tipo === 'pro';
      const planBadge = isPlanPro
        ? `<span class="badge b-purple" style="font-size:11px;font-weight:900;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;border:none;box-shadow:0 2px 8px rgba(236,72,153,0.35);padding:3px 8px">👑 PLAN PRO</span>`
        : `<span class="badge b-info" style="font-size:11px;font-weight:700;padding:3px 8px">📦 STANDARD</span>`;

      const telClean = (g.telefono || '').replace(/\D/g, '');
      const waLink = telClean ? `<a href="https://wa.me/${telClean}" target="_blank" class="btn btn-xs btn-secondary" style="padding:3px 6px;font-size:11px;border-radius:6px" title="Abrir WhatsApp">💬 WhatsApp</a>` : '';

      const diasTxt = g.dias_para_vencer !== null ? (g.dias_para_vencer >= 0 ? `Quedan ${g.dias_para_vencer} días` : `Venció hace ${Math.abs(g.dias_para_vencer)} días`) : '';

      // 1. Fila Desktop / Tablet (Exactamente 6 Columnas alineadas)
      if (tb) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <div style="font-weight:800;font-size:14.5px;color:var(--t1)">${escapeHtml(g.nombre)}</div>
            <div style="font-size:11.5px;color:#60a5fa;margin-top:2px">Código: <b style="letter-spacing:0.5px">${escapeHtml(g.invite_code || '-')}</b></div>
            <div style="font-size:11.5px;color:var(--t-mut);margin-top:2px">📍 ${escapeHtml(g.direccion || 'Sin dirección')}</div>
          </td>
          <td>
            <div style="font-weight:700;color:var(--t1);font-size:13.5px">👤 ${escapeHtml(g.dueno_usuario || 'Sin asignar')}</div>
            <div style="font-size:11.5px;color:var(--t2);margin-top:2px">${escapeHtml(g.dueno_email || '-')}</div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:5px;flex-wrap:wrap">
              <span style="font-size:11.5px;color:var(--t-mut)">📞 ${escapeHtml(g.telefono || '-')}</span>
              ${waLink}
            </div>
          </td>
          <td>
            <div style="margin-bottom:6px">${planBadge}</div>
            <div style="font-size:15px;font-weight:900;color:#38bdf8">$ ${fmtMoney(g.suscripcion_monto)} <span style="font-size:11px;color:var(--t-mut);font-weight:600">/ mes</span></div>
          </td>
          <td>
            <div style="font-weight:800;color:var(--t1);font-size:13px">${fmtDate(g.suscripcion_vencimiento)}</div>
            <div style="font-size:11.5px;color:var(--t-mut);margin:2px 0 5px">${diasTxt}</div>
            <div><span class="badge ${badgeCls}">${badgeTxt}</span></div>
          </td>
          <td style="text-align:center">
            <div style="display:flex;flex-direction:column;gap:5px;align-items:center;justify-content:center">
              <span class="badge b-purple" style="font-weight:800;font-size:11.5px">👥 ${g.total_alumnos_gym || 0} socios</span>
              <span class="badge b-info" style="font-weight:800;font-size:11.5px">🏋️ ${g.total_profes_gym || 0} coaches</span>
            </div>
          </td>
          <td style="text-align:center">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;min-width:190px">
              ${isPlanPro ? `
                <button type="button" class="btn btn-xs btn-secondary" style="font-weight:700;font-size:11px;justify-content:center;padding:6px 4px" title="Cambiar a Plan Standard" onclick="togglePlanProGym(${g.id}, '${(g.nombre || '').replace(/'/g, "\\'")}', 'standard')">⭐ Standard</button>
              ` : `
                <button type="button" class="btn btn-xs btn-purple" style="font-weight:800;font-size:11px;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;border:none;box-shadow:0 2px 6px rgba(236,72,153,0.35);justify-content:center;padding:6px 4px" title="Pasar al Plan PRO (Habilita Nutrición y Comidas)" onclick="togglePlanProGym(${g.id}, '${(g.nombre || '').replace(/'/g, "\\'")}', 'pro')">👑 Pasar PRO</button>
              `}
              <button type="button" class="btn btn-xs btn-purple" style="background:rgba(139,92,246,0.25);border:1px solid rgba(139,92,246,0.5);color:#c084fc;font-weight:700;font-size:11px;justify-content:center;padding:6px 4px" title="Generar contraseña temporal para el dueño" onclick="generarClaveTemporal(${g.dueno_id || 0}, '${(g.dueno_usuario || g.nombre || '').replace(/'/g, "\\'")}', 0, 0, ${g.id})">🔑 Clave</button>
              <button type="button" class="btn btn-xs btn-success" style="font-weight:800;font-size:11px;justify-content:center;padding:6px 4px" onclick="openSaasPagoModal(${g.id})">💵 Cobrar</button>
              <div style="display:flex;gap:3px">
                <button type="button" class="btn btn-xs ${isSusp ? 'btn-warn' : 'btn-danger'}" style="font-weight:700;font-size:11px;flex:1;justify-content:center;padding:6px 2px" title="${isSusp ? 'Reactivar Gimnasio' : 'Suspender Gimnasio'}" onclick="toggleSuspensionGym(${g.id}, '${g.suscripcion_estado}')">${isSusp ? '✅ Activar' : '🚫 Susp.'}</button>
                <button type="button" class="btn btn-xs btn-secondary" style="font-size:11px;padding:6px 6px" title="Editar Datos" onclick="editGymById(${g.id})">✏️</button>
              </div>
            </div>
          </td>
        `;
        tb.appendChild(tr);
      }

      // 2. Card Limpia para Teléfonos Móviles (< 768px)
      if (mob) {
        const card = document.createElement('div');
        card.className = 'saas-sub-card-mobile';
        card.innerHTML = `
          <div class="saas-sub-card-header">
            <div>
              <div style="font-size:16px;font-weight:800;color:var(--t1)">${escapeHtml(g.nombre)}</div>
              <div style="font-size:12px;color:var(--t2);margin-top:2px">👤 Dueño: <b>${escapeHtml(g.dueno_usuario || 'Sin asignar')}</b></div>
            </div>
            <span class="badge ${badgeCls}" style="font-size:11px;font-weight:800;padding:4px 8px">${badgeTxt}</span>
          </div>

          <div class="saas-sub-card-body">
            <div class="saas-sub-row">
              <span class="saas-sub-label">📦 Plan Contratado</span>
              <span class="saas-sub-val">${planBadge}</span>
            </div>

            <div class="saas-sub-row">
              <span class="saas-sub-label">💵 Cuota SaaS</span>
              <span class="saas-sub-val" style="font-size:14px;font-weight:800;color:#60a5fa">$ ${fmtMoney(g.suscripcion_monto)} / mes</span>
            </div>

            <div class="saas-sub-row">
              <span class="saas-sub-label">📅 Vencimiento</span>
              <div class="saas-sub-val">
                <b style="color:var(--t1)">${fmtDate(g.suscripcion_vencimiento)}</b>
                ${diasTxt ? `<div style="font-size:11px;color:var(--t-mut);margin-top:1px">${diasTxt}</div>` : ''}
              </div>
            </div>

            <div class="saas-sub-row">
              <span class="saas-sub-label">👥 Total Socios</span>
              <span class="saas-sub-val"><span class="badge b-purple">${g.total_alumnos_gym || 0} socios</span></span>
            </div>
          </div>

          <div class="saas-sub-card-footer" style="display:flex;flex-direction:column;gap:8px">
            <button class="btn btn-success" onclick="openSaasPagoModal(${g.id})" style="font-weight:800">
              💵 Cobrar SaaS ($ ${fmtMoney(g.suscripcion_monto)})
            </button>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
              ${isPlanPro ? `
                <button class="btn btn-sm btn-secondary" onclick="togglePlanProGym(${g.id}, '${(g.nombre || '').replace(/'/g, "\\'")}', 'standard')">⭐ Standard</button>
              ` : `
                <button class="btn btn-sm btn-purple" style="background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;border:none" onclick="togglePlanProGym(${g.id}, '${(g.nombre || '').replace(/'/g, "\\'")}', 'pro')">👑 Pasar a PRO</button>
              `}
              <button class="btn btn-sm btn-purple" style="background:rgba(139,92,246,0.25);color:#c084fc" onclick="generarClaveTemporal(${g.dueno_id || 0}, '${(g.dueno_usuario || g.nombre || '').replace(/'/g, "\\'")}', 0, 0, ${g.id})">🔑 Clave Dueño</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
              <button class="btn btn-sm ${isSusp ? 'btn-warn' : 'btn-danger'}" onclick="toggleSuspensionGym(${g.id}, '${g.suscripcion_estado}')">${isSusp ? '✅ Reactivar Sede' : '🚫 Suspender Sede'}</button>
              <button class="btn btn-sm btn-secondary" onclick="editGymById(${g.id})">✏️ Editar Datos</button>
            </div>
          </div>
        `;
        mob.appendChild(card);
      }
    });
  }

  renderGenericPagination({
    containerId: 'saas-gyms-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'sedes',
    scrollTargetId: 'page-saas-gimnasios',
    onPageChange: (p) => {
      _saasGymsCurrentPage = p;
      renderSaasGymsTable();
    }
  });
}

function changeSaasGymsPageSize(sz) {
  _saasGymsPageSize = Number(sz) || 15;
  _saasGymsCurrentPage = 1;
  renderSaasGymsTable();
}

async function togglePlanProGym(gymId, gymNombre, nuevoPlan) {
  const isPro = nuevoPlan === 'pro';
  const r = await api('saas.gimnasios.toggle_plan_pro', { gimnasio_id: gymId, plan_tipo: nuevoPlan });
  if (r.ok) {
    showToast(isPro ? `👑 ¡${gymNombre || 'Gimnasio'} ascendido a PLAN PRO con éxito!` : `⭐ ${gymNombre || 'Gimnasio'} cambiado a Plan Standard`);
    await loadSaasGimnasios();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al cambiar plan', true);
  }
}

function openGymModal() {
  $('#saas-gym-id').value = '';
  $('#saas-gym-nombre').value = '';
  $('#saas-gym-code').value = '';
  $('#saas-gym-tel').value = '';
  $('#saas-gym-email').value = '';
  $('#saas-gym-dir').value = '';
  if ($('#saas-gym-plan-tipo')) $('#saas-gym-plan-tipo').value = 'standard';
  $('#saas-gym-monto').value = '45000.00';
  const in30 = new Date();
  in30.setDate(in30.getDate() + 30);
  $('#saas-gym-venc').value = in30.toISOString().split('T')[0];
  $('#saas-dueno-user').value = '';
  $('#saas-dueno-pass').value = '';
  if ($('#gym-modal-title')) $('#gym-modal-title').textContent = '➕ Habilitar / Crear Gimnasio & Dueño';
  openModal('modal-gym');
}

function editGymById(id) {
  const g = (_saasGymsCache || []).find(x => x.id == id);
  if (!g) return;
  $('#saas-gym-id').value = g.id;
  $('#saas-gym-nombre').value = g.nombre || '';
  $('#saas-gym-code').value = g.invite_code || '';
  $('#saas-gym-tel').value = g.telefono || '';
  $('#saas-gym-email').value = g.dueno_email || '';
  $('#saas-gym-dir').value = g.direccion || '';
  if ($('#saas-gym-plan-tipo')) $('#saas-gym-plan-tipo').value = g.plan_tipo || 'standard';
  $('#saas-gym-monto').value = g.suscripcion_monto || '45000.00';
  $('#saas-gym-venc').value = (g.suscripcion_vencimiento || '').split(' ')[0];
  $('#saas-dueno-user').value = g.dueno_usuario || '';
  $('#saas-dueno-pass').value = '';
  if ($('#gym-modal-title')) $('#gym-modal-title').textContent = `✏️ Editar Sede: ${g.nombre}`;
  openModal('modal-gym');
}

async function saveGym(e) {
  e.preventDefault();
  const id = $('#saas-gym-id').value;
  const data = {
    id: id,
    nombre: $('#saas-gym-nombre').value,
    invite_code: $('#saas-gym-code').value,
    telefono: $('#saas-gym-tel').value,
    email: $('#saas-gym-email').value,
    direccion: $('#saas-gym-dir').value,
    plan_tipo: $('#saas-gym-plan-tipo') ? $('#saas-gym-plan-tipo').value : 'standard',
    suscripcion_monto: $('#saas-gym-monto').value,
    suscripcion_vencimiento: $('#saas-gym-venc').value,
    dueno_usuario: $('#saas-dueno-user').value,
    dueno_password: $('#saas-dueno-pass').value
  };

  const r = await api('saas.gimnasios.save', data);
  if (r.ok) {
    showToast('Gimnasio y Dueño guardados exitosamente');
    closeModal('modal-gym');
    await loadSaasGimnasios();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al guardar gimnasio', true);
  }
}

async function toggleSuspensionGym(id, estadoActual) {
  const isSusp = estadoActual === 'suspendido';
  const r = await api('saas.gimnasios.toggle_suspension', { id, estado_actual: estadoActual });
  if (r.ok) {
    showToast(isSusp ? 'Gimnasio y dueño reactivados con éxito' : 'Gimnasio y dueño suspendidos');
    await loadSaasGimnasios();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al cambiar estado', true);
  }
}

let _saasPagosCache = [];
let _saasPagosCurrentPage = 1;
let _saasPagosPageSize = 15;

async function loadSaasPagos() {
  const { ok, data } = await api('saas.pagos.list', {}, 'GET');
  if (!ok) return;
  _saasPagosCache = data || [];
  _saasPagosCurrentPage = 1;
  renderSaasPagosTable();
}

function renderSaasPagosTable() {
  const tb = $('#tbl-saas-pagos tbody');
  const mob = $('#saas-pagos-cards') || $('#saas-pagos-mobile-cards');
  if (tb) tb.innerHTML = '';
  if (mob) mob.innerHTML = '';

  const totalRecords = _saasPagosCache.length;
  const pageSize = Number(_saasPagosPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_saasPagosCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = _saasPagosCache.slice(startIdx, endIdx);

  if (!totalRecords) {
    if (tb) tb.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--t-mut);padding:28px">No hay registros de cobros SaaS aún.</td></tr>';
    if (mob) mob.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No hay cobros registrados.</div>';
  } else {
    pageItems.forEach(p => {
      // 1. Fila Desktop / Tablet
      if (tb) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><b>${fmtDate(p.fecha_pago)}</b></td>
          <td><b>${escapeHtml(p.gimnasio_nombre)}</b></td>
          <td>${escapeHtml(p.dueno_nombre)}</td>
          <td><span class="badge b-info">${escapeHtml(p.periodo_mes)}</span></td>
          <td><span class="badge b-ok">${escapeHtml(p.medio_pago)}</span></td>
          <td style="text-align:right;font-weight:800;color:var(--ok)">$ ${fmtMoney(p.monto)}</td>
          <td style="color:var(--t-mut)">${escapeHtml(p.comprobante || '-')} ${p.observaciones ? `(${escapeHtml(p.observaciones)})` : ''}</td>
        `;
        tb.appendChild(tr);
      }

      // 2. Card Limpia para Teléfonos Móviles (< 768px)
      if (mob) {
        const card = document.createElement('div');
        card.className = 'saas-sub-card-mobile';
        card.innerHTML = `
          <div class="saas-sub-card-header">
            <div>
              <div style="font-size:15.5px;font-weight:800;color:var(--t1)">🏢 ${escapeHtml(p.gimnasio_nombre)}</div>
              <div style="font-size:12.5px;color:var(--t2);margin-top:2px">👤 Dueño: <b>${escapeHtml(p.dueno_nombre)}</b></div>
            </div>
            <span class="badge b-ok" style="font-size:13px;font-weight:800;padding:4px 10px">$ ${fmtMoney(p.monto)}</span>
          </div>
          <div class="saas-sub-card-body">
            <div class="saas-sub-row">
              <span class="saas-sub-label">📅 Fecha de Cobro</span>
              <span class="saas-sub-val">${fmtDate(p.fecha_pago)}</span>
            </div>
            <div class="saas-sub-row">
              <span class="saas-sub-label">🗓️ Período</span>
              <span class="saas-sub-val"><span class="badge b-info">${escapeHtml(p.periodo_mes)}</span></span>
            </div>
            <div class="saas-sub-row">
              <span class="saas-sub-label">💳 Medio de Pago</span>
              <span class="saas-sub-val"><span class="badge b-ok">${escapeHtml(p.medio_pago)}</span></span>
            </div>
            ${(p.comprobante || p.observaciones) ? `
            <div class="saas-sub-row">
              <span class="saas-sub-label">📝 Detalle</span>
              <span class="saas-sub-val" style="font-size:12px;color:var(--t2)">${escapeHtml(p.comprobante || '')} ${p.observaciones ? `(${escapeHtml(p.observaciones)})` : ''}</span>
            </div>` : ''}
          </div>
        `;
        mob.appendChild(card);
      }
    });
  }

  renderGenericPagination({
    containerId: 'saas-pagos-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'pagos SaaS',
    scrollTargetId: 'page-saas-pagos',
    onPageChange: (p) => {
      _saasPagosCurrentPage = p;
      renderSaasPagosTable();
    }
  });
}

function changeSaasPagosPageSize(sz) {
  _saasPagosPageSize = Number(sz) || 15;
  _saasPagosCurrentPage = 1;
  renderSaasPagosTable();
}

async function openSaasPagoModal(gymId = null) {
  if (!_saasGymsCache || !_saasGymsCache.length) {
    const { ok, data } = await api('saas.gimnasios.list', {}, 'GET');
    if (ok && data) _saasGymsCache = data;
  }
  const sel = $('#saas-pago-gym');
  if (sel) {
    sel.innerHTML = '<option value="">(Seleccionar Gimnasio)</option>';
    _saasGymsCache.forEach(g => {
      const opt = document.createElement('option');
      opt.value = g.id;
      opt.textContent = `${g.nombre} (Dueño: ${g.dueno_usuario || 'Sin asignar'}) - $ ${fmtMoney(g.suscripcion_monto || 45000)}`;
      sel.appendChild(opt);
    });
    if (gymId) sel.value = gymId;
  }
  openModal('modal-saas-pago');
}

async function saveSaasPago(e) {
  e.preventDefault();
  const data = {
    gimnasio_id: $('#saas-pago-gym').value,
    monto: $('#saas-pago-monto').value,
    fecha_pago: $('#saas-pago-fecha').value,
    medio_pago: $('#saas-pago-medio').value,
    comprobante: $('#saas-pago-comp').value
  };

  const r = await api('saas.pagos.save', data);
  if (r.ok) {
    showToast('Pago de suscripción registrado y servicio renovado');
    closeModal('modal-saas-pago');
    await loadSaasGimnasios();
    await loadSaasPagos();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al asentar pago', true);
  }
}



/* ===== RUTINAS (ARQUITECTURA RELACIONAL 4 NIVELES) ===== */
let _rutinasCache = [];
let _rutinasTab = 'plantillas'; // 'plantillas' | 'asignadas'
let _catalogoCache = [];
let _currentBuilderProg = null;
let _currentBuilderDiaIdx = 0;
let _currentGrupoFilter = 'todos';
let _editingEjercicioDetId = null;
let _builderBlocksCollapsed = {
  calentamiento: false,
  principal: false,
  cardio: false,
  vuelta_calma: false
};

function toggleAllBuilderBlocks(collapse = true) {
  Object.keys(BLOQUE_INFO).forEach(bKey => {
    _builderBlocksCollapsed[bKey] = Boolean(collapse);
  });
  renderBuilderDiaContent();
}

const BLOQUE_INFO = {
  calentamiento: { 
    label: 'Calentamiento & Movilidad', 
    icon: '🟡', 
    badge: 'b-warn', 
    desc: 'Activación cardiovascular, dinámica y prevención de lesiones',
    bg: 'linear-gradient(145deg, rgba(234, 179, 8, 0.12) 0%, rgba(161, 98, 7, 0.05) 100%)',
    headerBg: 'linear-gradient(135deg, rgba(234, 179, 8, 0.22), rgba(202, 138, 4, 0.12))',
    border: 'rgba(234, 179, 8, 0.35)',
    color: '#facc15',
    btnColor: '#ca8a04'
  },
  principal: { 
    label: 'Bloque Principal / Fuerza', 
    icon: '🔵', 
    badge: 'b-info', 
    desc: 'Ejercicios de sobrecarga progresiva e hipertrofia',
    bg: 'linear-gradient(145deg, rgba(59, 130, 246, 0.12) 0%, rgba(29, 78, 216, 0.05) 100%)',
    headerBg: 'linear-gradient(135deg, rgba(59, 130, 246, 0.22), rgba(37, 99, 235, 0.12))',
    border: 'rgba(59, 130, 246, 0.35)',
    color: '#60a5fa',
    btnColor: '#2563eb'
  },
  cardio: { 
    label: 'Cardio & HIIT', 
    icon: '🔴', 
    badge: 'b-bad', 
    desc: 'Trabajo aeróbico o intervalos de alta intensidad',
    bg: 'linear-gradient(145deg, rgba(239, 68, 68, 0.12) 0%, rgba(185, 28, 28, 0.05) 100%)',
    headerBg: 'linear-gradient(135deg, rgba(239, 68, 68, 0.22), rgba(220, 38, 38, 0.12))',
    border: 'rgba(239, 68, 68, 0.35)',
    color: '#f87171',
    btnColor: '#dc2626'
  },
  vuelta_calma: { 
    label: 'Vuelta a la Calma / Core', 
    icon: '🟢', 
    badge: 'b-ok', 
    desc: 'Abdominales, estiramiento y recuperación miofascial',
    bg: 'linear-gradient(145deg, rgba(16, 185, 129, 0.12) 0%, rgba(4, 120, 87, 0.05) 100%)',
    headerBg: 'linear-gradient(135deg, rgba(16, 185, 129, 0.22), rgba(5, 150, 105, 0.12))',
    border: 'rgba(16, 185, 129, 0.35)',
    color: '#34d399',
    btnColor: '#059669'
  }
};

let _rutinasCurrentPage = 1;
let _rutinasPageSize = 12;

function switchRutinasTab(tab) {
  _rutinasTab = tab;
  _rutinasCurrentPage = 1;
  $('#btn-tab-plantillas')?.classList.toggle('btn-primary', tab === 'plantillas');
  $('#btn-tab-plantillas')?.classList.toggle('btn-secondary', tab !== 'plantillas');
  $('#btn-tab-asignadas')?.classList.toggle('btn-primary', tab === 'asignadas');
  $('#btn-tab-asignadas')?.classList.toggle('btn-secondary', tab !== 'asignadas');
  renderRutinasGrid();
}

function switchStudentDia(idx) {
  $$('.btn-student-dia-tab').forEach((btn, i) => {
    btn.classList.toggle('btn-primary', i === idx);
    btn.classList.toggle('active', i === idx);
    btn.classList.toggle('btn-secondary', i !== idx);
  });
  $$('.student-dia-pane').forEach((p, i) => {
    p.style.display = i === idx ? 'block' : 'none';
  });
}

function toggleEjComplete(chk, boxId) {
  const box = $('#' + boxId);
  if (!box) return;
  if (chk.checked) {
    box.style.opacity = '0.45';
    box.style.textDecoration = 'line-through';
  } else {
    box.style.opacity = '1';
    box.style.textDecoration = 'none';
  }
}

let _studentBlocksCollapsed = {
  calentamiento: false,
  principal: false,
  cardio: false,
  vuelta_calma: false
};

function toggleStudentBlock(bKey) {
  const willBeCollapsed = !_studentBlocksCollapsed[bKey];
  _studentBlocksCollapsed[bKey] = willBeCollapsed;

  // Sincronizar dinámicamente en todos los paneles de días del alumno
  document.querySelectorAll(`.student-block-body-${bKey}`).forEach(el => {
    el.style.display = willBeCollapsed ? 'none' : 'flex';
  });
  document.querySelectorAll(`.student-block-chev-${bKey}`).forEach(chev => {
    chev.style.transform = willBeCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
  });
}

function toggleAllStudentBlocks(collapse = true) {
  Object.keys(BLOQUE_INFO).forEach(bKey => {
    _studentBlocksCollapsed[bKey] = Boolean(collapse);
    document.querySelectorAll(`.student-block-body-${bKey}`).forEach(el => {
      el.style.display = collapse ? 'none' : 'flex';
    });
    document.querySelectorAll(`.student-block-chev-${bKey}`).forEach(chev => {
      chev.style.transform = collapse ? 'rotate(-90deg)' : 'rotate(0deg)';
    });
  });
}

async function loadRutinas() {
  const { ok, data } = await api('rutinas.programas.list', {}, 'GET');
  if (!ok) return;
  _rutinasCache = data || [];
  _rutinasCurrentPage = 1;
  renderRutinasGrid();
  loadCatalogoCache();
}

async function loadCatalogoCache(force = false) {
  if (_catalogoCache && _catalogoCache.length && !force) {
    return _catalogoCache;
  }
  const { ok, data } = await api('catalogo_ejercicios.list', {}, 'GET');
  if (ok && Array.isArray(data)) {
    _catalogoCache = data;
  }
  return _catalogoCache;
}

function renderRutinasGrid() {
  const container = $('#rutinas-grid-container');
  if (!container) return;
  container.innerHTML = '';

  const allItems = _rutinasCache.filter(p => _rutinasTab === 'plantillas' ? Number(p.es_plantilla) === 1 : Number(p.es_plantilla) === 0);
  const totalRecords = allItems.length;
  const pageSize = Number(_rutinasPageSize || 12);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_rutinasCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const items = allItems.slice(startIdx, endIdx);

  if (!totalRecords) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:48px 20px;background:rgba(255,255,255,0.02);border:1px dashed var(--border);border-radius:16px">
        <div style="font-size:36px;margin-bottom:10px">${_rutinasTab === 'plantillas' ? '🏆' : '👤'}</div>
        <h4 style="font-size:16px;color:var(--t1);margin-bottom:6px">No hay ${_rutinasTab === 'plantillas' ? 'plantillas prearmadas registradas' : 'rutinas asignadas a alumnos aún'}</h4>
        <p style="color:var(--t2);font-size:13px;margin-bottom:16px">
          ${_rutinasTab === 'plantillas' ? 'Creá tu primer programa de entrenamiento estructurado (1 a 7 días).' : 'Asigná una plantilla a un socio para que pueda verla en su celular.'}
        </p>
        <button class="btn btn-primary btn-sm" onclick="${_rutinasTab === 'plantillas' ? 'openProgramaModal()' : 'openAsignarRutinaModal()'}">
          ${_rutinasTab === 'plantillas' ? '➕ Crear Nueva Plantilla' : '👥 Asignar Rutina a Socio'}
        </button>
      </div>
    `;
    renderGenericPagination({
      containerId: 'rutinas-pagination-bar',
      totalRecords: 0,
      currentPage: 1,
      pageSize,
      itemLabel: _rutinasTab === 'plantillas' ? 'plantillas' : 'rutinas',
      onPageChange: () => {}
    });
    return;
  }

  items.forEach(p => {
    const card = document.createElement('div');
    card.className = 'card stat-card rutina-prog-card';
    card.style.cssText = 'background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:var(--shadow);transition:transform 0.2s ease, border-color 0.2s ease;';

    const isPlantilla = Number(p.es_plantilla) === 1;
    const nivelBadgeClass = p.nivel === 'principiante' ? 'b-ok' : (p.nivel === 'avanzado' ? 'b-bad' : 'b-purple');
    const nivelLabel = p.nivel ? (p.nivel.charAt(0).toUpperCase() + p.nivel.slice(1)) : 'Intermedio';

    card.innerHTML = `
      <div>
        <!-- 1. Cabecera: Tipo de Programa / Socio + Acciones Rápidas (Editar / Eliminar) -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:8px">
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            ${isPlantilla 
              ? `<span class="badge b-purple" style="font-size:11px;font-weight:800;padding:4px 10px;display:inline-flex;align-items:center;gap:5px">
                  🏆 Plantilla Maestra
                 </span>`
              : `<span class="badge b-ok" style="font-size:11px;font-weight:800;padding:4px 10px;display:inline-flex;align-items:center;gap:5px">
                  👤 Socio: <b style="color:var(--t1)">${escapeHtml(p.alumno_nombre || 'Asignado')}</b>
                 </span>`
            }
            ${isPlantilla && Number(p.alumnos_asignados_count || 0) > 0 ? `
              <span class="badge b-ok" style="font-size:10.5px;font-weight:800;padding:4px 8px" title="${p.alumnos_asignados_count} alumnos tienen esta rutina activa actualmente">
                🔥 ${p.alumnos_asignados_count} activo${Number(p.alumnos_asignados_count) === 1 ? '' : 's'}
              </span>
            ` : ''}
          </div>
          <div style="display:flex;gap:5px;align-items:center">
            <button class="btn btn-secondary btn-xs btn-edit-prog" title="Editar detalles" style="padding:4px 8px;border-radius:6px;font-size:12px;background:rgba(255,255,255,0.05);border-color:var(--border)">
              ✏️
            </button>
            <button class="btn btn-danger btn-xs btn-del-prog" title="Eliminar programa" style="padding:4px 8px;border-radius:6px;font-size:12px;background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.3);color:#f87171">
              🗑️
            </button>
          </div>
        </div>

        <!-- 2. Título Principal -->
        <h3 style="font-size:17px;font-weight:800;color:var(--t1);margin-bottom:8px;line-height:1.3;letter-spacing:-0.3px">
          ${escapeHtml(p.titulo)}
        </h3>

        <!-- 3. Badges de Nivel y Objetivo -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
          <span class="badge ${nivelBadgeClass}" style="font-size:10.5px;font-weight:700">
            ⚡ ${nivelLabel}
          </span>
          <span class="badge b-info" style="font-size:10.5px;font-weight:700">
            🎯 ${escapeHtml(p.objetivo || 'Hipertrofia Muscular')}
          </span>
        </div>

        <!-- 4. Descripción -->
        <p style="color:var(--t2);font-size:12.5px;margin-bottom:16px;line-height:1.45;min-height:36px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
          ${escapeHtml(p.descripcion || 'Programa estructurado por sesiones y bloques de entrenamiento.')}
        </p>

        <!-- 5. Métricas Claras y Ordenadas -->
        <div style="display:grid;grid-template-columns:${isPlantilla ? '1fr 1fr 1.15fr' : ((!isPlantilla && p.coach_nombre) ? '1fr 1fr 1fr' : '1fr 1fr')};gap:8px;background:rgba(255,255,255,0.025);border:1px solid var(--border);border-radius:12px;padding:10px 12px;margin-bottom:18px">
          <div style="display:flex;flex-direction:column;gap:2px">
            <span style="font-size:10px;color:var(--t-mut);text-transform:uppercase;font-weight:800;letter-spacing:0.5px">Frecuencia</span>
            <span style="font-size:13px;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:4px">
              📅 ${p.dias_reales || p.dias_count} Días
            </span>
          </div>
          <div style="display:flex;flex-direction:column;gap:2px">
            <span style="font-size:10px;color:var(--t-mut);text-transform:uppercase;font-weight:800;letter-spacing:0.5px">Ejercicios</span>
            <span style="font-size:13px;font-weight:800;color:#38bdf8;display:flex;align-items:center;gap:4px">
              🏋️ ${p.total_ejercicios || 0} listos
            </span>
          </div>
          ${isPlantilla ? `
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:10px;color:var(--t-mut);text-transform:uppercase;font-weight:800;letter-spacing:0.5px">Asignada</span>
              <span style="font-size:13px;font-weight:800;color:${Number(p.alumnos_asignados_count || 0) > 0 ? '#34d399' : 'var(--t2)'};display:flex;align-items:center;gap:4px" title="${p.alumnos_asignados_count || 0} socios activos (${p.total_clonada || 0} veces clonada)">
                👥 ${p.alumnos_asignados_count || 0} socio${Number(p.alumnos_asignados_count) === 1 ? '' : 's'}
              </span>
            </div>
          ` : ((!isPlantilla && p.coach_nombre) ? `
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:10px;color:var(--t-mut);text-transform:uppercase;font-weight:800;letter-spacing:0.5px">Coach</span>
              <span style="font-size:12.5px;font-weight:700;color:#c084fc;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                🏋️ ${escapeHtml(p.coach_nombre)}
              </span>
            </div>
          ` : '')}
        </div>
      </div>

      <!-- 6. Botones de Acción Principales -->
      <div style="display:flex;gap:8px;border-top:1px solid var(--border);padding-top:14px">
        <button class="btn btn-sm btn-primary btn-build-prog" style="flex:1;font-weight:800;padding:9px 12px;display:flex;align-items:center;justify-content:center;gap:6px">
          🛠️ Diseñar Días & Ejercicios
        </button>
        ${isPlantilla ? `
          <button class="btn btn-sm btn-success btn-assign-prog" title="Asignar y clonar a un socio" style="font-weight:800;padding:9px 12px;display:flex;align-items:center;justify-content:center;gap:6px">
            👥 Asignar
          </button>
        ` : ''}
      </div>
    `;

    // Event listeners
    const btnEdit = card.querySelector('.btn-edit-prog');
    if (btnEdit) btnEdit.onclick = () => openProgramaModal(p);

    const btnDel = card.querySelector('.btn-del-prog');
    if (btnDel) btnDel.onclick = () => deletePrograma(p.id);

    const btnBuild = card.querySelector('.btn-build-prog');
    if (btnBuild) btnBuild.onclick = () => openRutinaBuilder(p.id);

    const btnAssign = card.querySelector('.btn-assign-prog');
    if (btnAssign) btnAssign.onclick = () => openAsignarRutinaModal(p.id);

    container.appendChild(card);
  });

  renderGenericPagination({
    containerId: 'rutinas-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: _rutinasTab === 'plantillas' ? 'plantillas' : 'rutinas',
    scrollTargetId: 'page-rutinas',
    onPageChange: (p) => {
      _rutinasCurrentPage = p;
      renderRutinasGrid();
    }
  });
}

function changeRutinasPageSize(sz) {
  _rutinasPageSize = Number(sz) || 12;
  _rutinasCurrentPage = 1;
  renderRutinasGrid();
}

function selectDiasCount(n) {
  $('#prog-dias-count').value = n;
  if ($('#prog-dias-badge')) $('#prog-dias-badge').textContent = `${n} Día${n > 1 ? 's' : ''}`;
  $$('.btn-dias-sel').forEach((btn, idx) => {
    const isThis = (idx + 1) === n;
    btn.classList.toggle('btn-primary', isThis);
    btn.classList.toggle('active', isThis);
    btn.classList.toggle('btn-secondary', !isThis);
  });
}

function openProgramaModal(p = null) {
  $('#prog-modal-title').textContent = p ? 'Editar Programa de Entrenamiento' : 'Crear Nuevo Programa de Entrenamiento';
  $('#prog-id').value = p?.id || '';
  $('#prog-es-plantilla').value = p ? (p.es_plantilla !== undefined ? p.es_plantilla : 1) : 1;
  $('#prog-alumno-id').value = p?.alumno_id || '';
  $('#prog-titulo').value = p?.titulo || '';
  $('#prog-obj').value = p?.objetivo || 'Hipertrofia Muscular';
  $('#prog-nivel').value = p?.nivel || 'intermedio';
  $('#prog-desc').value = p?.descripcion || '';
  
  const dias = p ? Number(p.dias_count || p.dias_reales || 3) : 3;
  selectDiasCount(dias);

  openModal('modal-rutina-programa');
}

async function saveProgramaHeader(e) {
  e.preventDefault();
  const data = {
    id: $('#prog-id').value,
    es_plantilla: $('#prog-es-plantilla').value,
    alumno_id: $('#prog-alumno-id').value,
    titulo: $('#prog-titulo').value,
    objetivo: $('#prog-obj').value,
    nivel: $('#prog-nivel').value,
    dias_count: $('#prog-dias-count').value,
    descripcion: $('#prog-desc').value
  };

  const r = await api('rutinas.programas.save', data);
  if (r.ok) {
    showToast('Programa guardado exitosamente');
    closeModal('modal-rutina-programa');
    loadRutinas();
    if (r.data?.id) {
      openRutinaBuilder(r.data.id);
    }
  } else {
    showToast(r.msg || 'Error al guardar programa', true);
  }
}

async function openRutinaBuilder(progId, targetDiaIdx = null) {
  // Pre-cargar catálogo de ejercicios en background si aún no está en memoria
  if (!_catalogoCache || !_catalogoCache.length) {
    loadCatalogoCache();
  }
  const isSameProgram = _currentBuilderProg && Number(_currentBuilderProg.id) === Number(progId);
  const { ok, data } = await api('rutinas.programas.get', { id: progId }, 'GET');
  if (!ok) {
    showToast('No se pudo cargar el programa', true);
    return;
  }

  _currentBuilderProg = data;
  const totalDias = data.dias?.length || 1;

  if (targetDiaIdx !== null) {
    _currentBuilderDiaIdx = Math.max(0, Math.min(Number(targetDiaIdx), totalDias - 1));
  } else if (isSameProgram) {
    _currentBuilderDiaIdx = Math.max(0, Math.min(_currentBuilderDiaIdx, totalDias - 1));
  } else {
    _currentBuilderDiaIdx = 0;
  }

  // Header data
  $('#builder-prog-titulo').textContent = data.titulo;
  $('#builder-prog-badge-nivel').textContent = (data.nivel || 'Intermedio').toUpperCase();
  $('#builder-prog-badge-obj').textContent = data.objetivo || 'Hipertrofia';
  
  const isPlantilla = Number(data.es_plantilla) === 1;
  $('#builder-prog-sub').textContent = isPlantilla 
    ? `📋 Plantilla Base • Frecuencia: ${data.dias?.length || 3} Días de Entrenamiento`
    : `👤 Rutina Asignada al Socio: ${data.alumno_nombre || 'Alumno'} • Frecuencia: ${data.dias?.length || 3} Días`;

  renderBuilderDayTabs();
  renderBuilderDiaContent();
  openModal('modal-rutina-builder');
}

function renderBuilderDayTabs() {
  const container = $('#builder-dias-tabs');
  if (!container || !_currentBuilderProg) return;
  container.innerHTML = '';

  const dias = _currentBuilderProg.dias || [];
  dias.forEach((d, idx) => {
    const isAct = idx === _currentBuilderDiaIdx;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `btn-day-tab ${isAct ? 'active' : ''}`;
    btn.innerHTML = `
      <b class="day-tab-title">Día ${idx + 1}</b>
      <small class="day-tab-badge">${d.ejercicios?.length || 0} ejs</small>
    `;
    btn.onclick = () => {
      _currentBuilderDiaIdx = idx;
      renderBuilderDayTabs();
      renderBuilderDiaContent();
    };
    container.appendChild(btn);
  });
}

let _selectedBulkEjIds = new Set();

function renderBuilderDiaContent() {
  const container = $('#builder-dia-content');
  if (!container || !_currentBuilderProg) return;
  container.innerHTML = '';

  const dia = (_currentBuilderProg.dias || [])[_currentBuilderDiaIdx];
  if (!dia) {
    container.innerHTML = `<div style="text-align:center;padding:30px;color:var(--t-mut)">No hay días configurados en este programa.</div>`;
    return;
  }

  // Update input enfoque
  if ($('#builder-dia-enfoque')) {
    $('#builder-dia-enfoque').value = dia.enfoque || '';
  }

  // Agrupar ejercicios por bloque
  const bloques = {
    calentamiento: (dia.ejercicios || []).filter(e => e.bloque === 'calentamiento'),
    principal:     (dia.ejercicios || []).filter(e => e.bloque === 'principal'),
    cardio:        (dia.ejercicios || []).filter(e => e.bloque === 'cardio'),
    vuelta_calma:  (dia.ejercicios || []).filter(e => e.bloque === 'vuelta_calma')
  };

  const wrap = document.createElement('div');
  wrap.style.cssText = 'display:flex;flex-direction:column;gap:18px';

  Object.keys(BLOQUE_INFO).forEach(bloqueKey => {
    const info = BLOQUE_INFO[bloqueKey];
    const ejs = bloques[bloqueKey] || [];
    const isCollapsed = Boolean(_builderBlocksCollapsed[bloqueKey]);

    const section = document.createElement('div');
    section.style.cssText = `background:${info.bg};border:1px solid ${info.border};border-radius:14px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.15);transition:var(--tr)`;

    // Header Bloque con función Desplegable (Accordion)
    const secHeader = document.createElement('div');
    secHeader.style.cssText = `display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:${info.headerBg};border-bottom:1px solid ${info.border};cursor:pointer;user-select:none;flex-wrap:wrap;gap:8px`;
    secHeader.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px">
        <span id="chev-builder-${bloqueKey}" style="font-size:13px;color:${info.color};font-weight:900;transition:transform 0.25s;display:inline-block;transform:${isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)'}">▼</span>
        <span style="font-size:18px">${info.icon}</span>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <b style="font-size:14.5px;color:var(--t1);letter-spacing:0.2px">${info.label}</b>
          <span class="badge ${info.badge}" style="font-size:10.5px">${ejs.length} Ejercicio${ejs.length === 1 ? '' : 's'}</span>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px" onclick="event.stopPropagation()">
        <button class="btn btn-xs" style="background:${info.btnColor};color:#fff;font-weight:700;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.25)" onclick="openSelectEjercicioModal('${bloqueKey}')">
          ➕ Añadir Ejercicios a ${info.label.split(' ')[0]}
        </button>
      </div>
    `;
    section.appendChild(secHeader);

    // Listado de ejercicios en el bloque con controles de edición inline directa
    const secBody = document.createElement('div');
    secBody.id = `builder-block-body-${bloqueKey}`;
    secBody.style.cssText = `padding:14px;display:${isCollapsed ? 'none' : 'flex'};flex-direction:column;gap:10px;transition:all 0.25s ease`;

    // Click en el header para colapsar/desplegar manteniendo el estado guardado
    secHeader.onclick = (e) => {
      if (e.target.closest('button')) return;
      const willBeCollapsed = secBody.style.display !== 'none';
      _builderBlocksCollapsed[bloqueKey] = willBeCollapsed;
      secBody.style.display = willBeCollapsed ? 'none' : 'flex';
      const chev = secHeader.querySelector(`#chev-builder-${bloqueKey}`);
      if (chev) chev.style.transform = willBeCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
    };

    if (!ejs.length) {
      secBody.innerHTML = `
        <div style="text-align:center;padding:18px;color:var(--t-mut);font-size:12.5px;font-style:italic">
          Sin ejercicios en este bloque. Hacé clic en "➕ Añadir Ejercicios" para seleccionarlos del catálogo.
        </div>
      `;
    } else {
      ejs.forEach((ej, idx) => {
        const itemCard = document.createElement('div');
        itemCard.style.cssText = 'background:var(--bg-inp);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:8px;transition:var(--tr)';

        const descansoVal = ej.descanso_seg !== undefined && ej.descanso_seg !== null ? Number(ej.descanso_seg) : 60;
        const seriesVal = ej.series ? Number(ej.series) : 4;

        itemCard.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="color:var(--t-mut);font-size:12px;font-weight:700">#${idx + 1}</span>
              <b style="font-size:14.5px;color:var(--t1)">${ej.ejercicio_nombre}</b>
              <span class="badge ${getGrupoBadgeClass(ej.grupo_muscular)}" style="font-size:10px;font-weight:800;text-transform:uppercase">${ej.grupo_muscular || 'Músculo'}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span id="save-status-${ej.id}" style="font-size:11.5px;font-weight:700;color:#10b981;opacity:0;transition:opacity 0.25s">✓ Guardado</span>
              <button class="btn btn-xs btn-danger" onclick="deleteEjercicioFromDia(${ej.id})" title="Quitar ejercicio">🗑️</button>
            </div>
          </div>

          <!-- Fila de Controles Inline (Series, Reps, Descanso, Carga) -->
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:2px">
            <div style="display:flex;align-items:center;gap:4px">
              <label style="font-size:11px;color:var(--t2);font-weight:700">Series:</label>
              <select class="inp" style="padding:4px 8px;font-size:12px;width:75px;font-weight:700;height:30px" onchange="updateEjInline(${ej.id}, 'series', this.value)">
                ${[1,2,3,4,5,6,7,8,10].map(s => `<option value="${s}" ${seriesVal === s ? 'selected' : ''}>${s} set${s>1?'s':''}</option>`).join('')}
              </select>
            </div>

            <div style="display:flex;align-items:center;gap:4px">
              <label style="font-size:11px;color:var(--t2);font-weight:700">Reps:</label>
              <input class="inp" style="padding:4px 8px;font-size:12px;width:105px;font-weight:700;height:30px" value="${ej.repeticiones || '10-12'}" placeholder="10-12" onchange="updateEjInline(${ej.id}, 'repeticiones', this.value)">
            </div>

            <div style="display:flex;align-items:center;gap:4px">
              <label style="font-size:11px;color:var(--t2);font-weight:700">Descanso:</label>
              <select class="inp" style="padding:4px 8px;font-size:12px;width:110px;font-weight:700;height:30px" onchange="updateEjInline(${ej.id}, 'descanso_seg', this.value)">
                <option value="0" ${descansoVal === 0 ? 'selected' : ''}>Sin descanso</option>
                <option value="30" ${descansoVal === 30 ? 'selected' : ''}>⏱️ 30 seg</option>
                <option value="45" ${descansoVal === 45 ? 'selected' : ''}>⏱️ 45 seg</option>
                <option value="60" ${descansoVal === 60 ? 'selected' : ''}>⏱️ 60 seg</option>
                <option value="90" ${descansoVal === 90 ? 'selected' : ''}>⏱️ 90 seg</option>
                <option value="120" ${descansoVal === 120 ? 'selected' : ''}>⏱️ 2 min</option>
                <option value="180" ${descansoVal === 180 ? 'selected' : ''}>⏱️ 3 min</option>
              </select>
            </div>

            <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:140px">
              <label style="font-size:11px;color:var(--t2);font-weight:700">Carga:</label>
              <input class="inp" style="padding:4px 8px;font-size:12px;font-weight:600;height:30px;flex:1" value="${ej.carga_sugerida || ''}" placeholder="Ej: 20 kg, RPE 8, Moderado" onchange="updateEjInline(${ej.id}, 'carga_sugerida', this.value)">
            </div>
          </div>

          <!-- Fila de Notas Técnicas Inline -->
          <div style="margin-top:2px">
            <input class="inp" style="padding:4px 10px;font-size:12px;color:var(--t2);background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.1);height:28px" value="${ej.notas || ''}" placeholder="📝 Click para agregar indicación técnica o variante (opcional)..." onchange="updateEjInline(${ej.id}, 'notas', this.value)">
          </div>
        `;
        secBody.appendChild(itemCard);
      });
    }

    section.appendChild(secBody);
    wrap.appendChild(section);
  });

  container.appendChild(wrap);
}

async function updateEjInline(ejId, field, val) {
  const statusSpan = $('#save-status-' + ejId);
  const data = { id: ejId, [field]: val };
  const r = await api('rutinas.ejercicios.update', data);
  if (r.ok) {
    if (statusSpan) {
      statusSpan.style.opacity = '1';
      setTimeout(() => { statusSpan.style.opacity = '0'; }, 1600);
    }
    // Sincronizar cache local en memoria sin romper el foco del input
    if (_currentBuilderProg) {
      const dia = (_currentBuilderProg.dias || [])[_currentBuilderDiaIdx];
      if (dia && dia.ejercicios) {
        const item = dia.ejercicios.find(e => Number(e.id) === Number(ejId));
        if (item) item[field] = val;
      }
    }
  } else {
    showToast(r.msg || 'Error al guardar', true);
  }
}

async function saveDiaEnfoque() {
  if (!_currentBuilderProg) return;
  const dia = (_currentBuilderProg.dias || [])[_currentBuilderDiaIdx];
  if (!dia) return;

  const enfoque = $('#builder-dia-enfoque')?.value || '';
  dia.enfoque = enfoque;
  await api('rutinas.dias.save', { id: dia.id, nombre_dia: dia.nombre_dia, enfoque: enfoque });
  showToast('Enfoque del día actualizado');
}

async function openSelectEjercicioModal(bloquePreselect = 'principal') {
  _selectedBulkEjIds.clear();

  const dia = (_currentBuilderProg?.dias || [])[_currentBuilderDiaIdx];
  const diaNombre = dia ? (dia.nombre_dia || `Día ${_currentBuilderDiaIdx + 1}`) : '';

  if ($('#ej-bulk-bloque-sel')) {
    $('#ej-bulk-bloque-sel').value = bloquePreselect;
  }
  onBulkBloqueChange(bloquePreselect);

  if ($('#modal-bulk-subtitle')) {
    $('#modal-bulk-subtitle').innerHTML = `Agregando ejercicios a <b>${diaNombre}</b>. Podés seleccionar múltiples ejercicios para este bloque y luego ajustar series y repeticiones directamente en el diseñador.`;
  }

  $('#ej-search-inp').value = '';
  _currentGrupoFilter = 'todos';
  setGrupoFilter('todos');

  // Asegurar siempre que el catálogo de ejercicios esté cargado
  const container = $('#ej-catalogo-bulk-list');
  if (!_catalogoCache || !_catalogoCache.length) {
    if (container) {
      container.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--t-mut)"><span class="spinner" style="display:inline-block;margin-bottom:8px"></span><br>Cargando catálogo de ejercicios...</div>`;
    }
    await loadCatalogoCache();
  }

  renderBulkCatalogoList();
  updateBulkSelectedBadge();
  openModal('modal-rutina-add-ejercicio');
}

function onBulkBloqueChange(bloque) {
  const info = BLOQUE_INFO[bloque] || BLOQUE_INFO['principal'];
  const dia = (_currentBuilderProg?.dias || [])[_currentBuilderDiaIdx];
  const diaNombre = dia ? (dia.nombre_dia || `Día ${_currentBuilderDiaIdx + 1}`) : '';
  if ($('#modal-bulk-title')) {
    $('#modal-bulk-title').innerHTML = `Añadir Ejercicios a ${info.icon} ${info.label} (${diaNombre})`;
  }
}

function getGrupoBadgeClass(grupo) {
  const g = (grupo || '').toLowerCase().trim();
  if (g.includes('pecho')) return 'badge-grupo-pecho';
  if (g.includes('espalda')) return 'badge-grupo-espalda';
  if (g.includes('pierna') || g.includes('cuadriceps') || g.includes('isquio') || g.includes('femoral') || g.includes('gluteo') || g.includes('gemelo') || g.includes('talon') || g.includes('soleo') || g.includes('aductor') || g.includes('abductor') || g.includes('sentadilla') || g.includes('estocada') || g.includes('prensa')) return 'badge-grupo-piernas';
  if (g.includes('hombro') || g.includes('deltoide') || g.includes('militar') || g.includes('trapecio')) return 'badge-grupo-hombros';
  if (g.includes('biceps') || g.includes('bíceps')) return 'badge-grupo-biceps';
  if (g.includes('triceps') || g.includes('tríceps')) return 'badge-grupo-triceps';
  if (g.includes('core') || g.includes('abdomen') || g.includes('abdominal') || g.includes('plank') || g.includes('plancha') || g.includes('lumb')) return 'badge-grupo-core';
  if (g.includes('cardio') || g.includes('hiit') || g.includes('aerobico') || g.includes('trote') || g.includes('bici')) return 'badge-grupo-cardio';
  if (g.includes('cuerpo_completo') || g.includes('movilidad') || g.includes('funcional')) return 'badge-grupo-cuerpo_completo';
  return 'b-info';
}

function setGrupoFilter(grupo) {
  _currentGrupoFilter = grupo;
  $$('.btn-grp-fil').forEach(btn => {
    const isAct = btn.textContent.toLowerCase().includes(grupo) || (grupo === 'todos' && btn.textContent === 'Todos');
    btn.classList.toggle('btn-primary', isAct);
    btn.classList.toggle('active', isAct);
    btn.classList.toggle('btn-secondary', !isAct);
  });
  renderBulkCatalogoList();
}

function renderBulkCatalogoList() {
  const container = $('#ej-catalogo-bulk-list');
  if (!container) return;
  container.innerHTML = '';

  const q = ($('#ej-search-inp')?.value || '').toLowerCase().trim();
  const grupo = _currentGrupoFilter;

  const filtered = _catalogoCache.filter(e => {
    const matchQ = !q || e.nombre.toLowerCase().includes(q) || (e.descripcion || '').toLowerCase().includes(q);
    const matchGrp = grupo === 'todos' || e.grupo_muscular === grupo;
    return matchQ && matchGrp;
  });

  if (!filtered.length) {
    container.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:24px;color:var(--t-mut);font-size:13px">No se encontraron ejercicios con ese filtro. Podés crearlo en el Catálogo Maestro.</div>`;
    return;
  }

  filtered.forEach(e => {
    const isSel = _selectedBulkEjIds.has(e.id);
    const isCustom = e.gimnasio_id !== null;

    const card = document.createElement('div');
    card.style.cssText = `padding:12px 14px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;background:${isSel ? 'rgba(59, 130, 246, 0.22)' : 'rgba(255, 255, 255, 0.03)'};border:1px solid ${isSel ? 'var(--pri)' : 'rgba(255,255,255,0.06)'};transition:var(--tr)`;

    card.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
        <input type="checkbox" style="width:20px;height:20px;cursor:pointer;accent-color:#3b82f6;flex-shrink:0" ${isSel ? 'checked' : ''} onclick="event.stopPropagation(); toggleBulkEjercicio(${e.id})">
        <div style="min-width:0;flex:1;overflow:hidden">
          <b style="font-size:14px;color:var(--t1);display:block;white-space:nowrap;text-overflow:ellipsis;overflow:hidden">${e.nombre}</b>
          <div style="display:flex;gap:6px;align-items:center;margin-top:4px;flex-wrap:wrap">
            <span class="badge ${getGrupoBadgeClass(e.grupo_muscular)}" style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.3px">${e.grupo_muscular}</span>
            <span class="badge ${isCustom ? 'b-purple' : 'b-ok'}" style="font-size:10px">${isCustom ? 'Personalizado' : 'Oficial'}</span>
          </div>
        </div>
      </div>
    `;

    card.onclick = () => toggleBulkEjercicio(e.id);
    container.appendChild(card);
  });
}

function toggleBulkEjercicio(ejId) {
  if (_selectedBulkEjIds.has(ejId)) {
    _selectedBulkEjIds.delete(ejId);
  } else {
    _selectedBulkEjIds.add(ejId);
  }
  renderBulkCatalogoList();
  updateBulkSelectedBadge();
}

function updateBulkSelectedBadge() {
  const count = _selectedBulkEjIds.size;
  const badge = $('#bulk-selected-badge');
  const btn = $('#btn-submit-bulk-ej');

  if (badge) badge.textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
  if (btn) {
    btn.disabled = count === 0;
    btn.textContent = count > 0 ? `➕ Cargar ${count} Ejercicio${count === 1 ? '' : 's'} al Bloque` : '➕ Cargar Ejercicios';
  }
}

function clearBulkSelection() {
  _selectedBulkEjIds.clear();
  renderBulkCatalogoList();
  updateBulkSelectedBadge();
}

async function submitBulkAddEjercicios() {
  if (!_selectedBulkEjIds.size) {
    showToast('Seleccioná al menos un ejercicio tildándolo en la lista', true);
    return;
  }

  const dia = (_currentBuilderProg.dias || [])[_currentBuilderDiaIdx];
  if (!dia) return;

  const bloque = $('#ej-bulk-bloque-sel')?.value || 'principal';
  const data = {
    dia_id: dia.id,
    bloque: bloque,
    ejercicios: Array.from(_selectedBulkEjIds)
  };

  const currentDiaIdx = _currentBuilderDiaIdx;
  const r = await api('rutinas.ejercicios.add_batch', data);
  if (r.ok) {
    showToast(r.msg || 'Ejercicios añadidos correctamente');
    closeModal('modal-rutina-add-ejercicio');
    // Recargar el programa en el diseñador manteniendo el día activo actual
    await openRutinaBuilder(_currentBuilderProg.id, currentDiaIdx);
    loadRutinas();
  } else {
    showToast(r.msg || 'Error al agregar ejercicios', true);
  }
}

async function deleteEjercicioFromDia(detId) {
  const currentDiaIdx = _currentBuilderDiaIdx;
  const r = await api('rutinas.ejercicios.delete', { id: detId });
  if (r.ok) {
    showToast('Ejercicio quitado');
    await openRutinaBuilder(_currentBuilderProg.id, currentDiaIdx);
    loadRutinas();
  } else {
    showToast(r.msg || 'Error al quitar ejercicio', true);
  }
}

function closeRutinaBuilder(notify = false) {
  closeModal('modal-rutina-builder');
  loadRutinas();
  if (notify) {
    showToast('¡Rutina guardada y actualizada correctamente!');
  }
}

async function deletePrograma(progId) {
  const ok = await systemConfirm({
    title: '¿Eliminar programa completo?',
    message: 'Se eliminarán todos los días y ejercicios configurados en este programa.',
    confirmText: 'Sí, Eliminar Programa',
    isDanger: true
  });
  if (!ok) return;

  const r = await api('rutinas.programas.delete', { id: progId });
  if (r.ok) {
    showToast('Programa eliminado correctamente');
    loadRutinas();
  }
}


function openCatalogoModal() {
  loadCatalogoCache().then(() => {
    renderCatalogoManagerList();
    openModal('modal-catalogo-ejercicios');
  });
}

function renderCatalogoManagerList() {
  const tb = $('#cat-manager-tbody');
  if (!tb) return;
  tb.innerHTML = '';

  const q = ($('#cat-search-inp')?.value || '').toLowerCase().trim();
  const grupo = $('#cat-grupo-sel')?.value || 'todos';

  const filtered = _catalogoCache.filter(e => {
    const matchQ = !q || e.nombre.toLowerCase().includes(q) || (e.descripcion || '').toLowerCase().includes(q);
    const matchGrp = grupo === 'todos' || e.grupo_muscular === grupo;
    return matchQ && matchGrp;
  });

  filtered.forEach(e => {
    const isCustom = e.gimnasio_id !== null;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b style="color:var(--t1)">${e.nombre}</b></td>
      <td><span class="badge ${getGrupoBadgeClass(e.grupo_muscular)}" style="text-transform:uppercase;font-size:10.5px;font-weight:800">${e.grupo_muscular}</span></td>
      <td style="color:var(--t2);font-size:12px">${e.descripcion || '-'}</td>
      <td style="text-align:right">
        <span class="badge ${isCustom ? 'b-purple' : 'b-ok'}" style="font-size:10px">
          ${isCustom ? 'Personalizado' : 'Oficial Gym Pro'}
        </span>
      </td>
    `;
    tb.appendChild(tr);
  });
}

function normalizeEjercicioText(str) {
  if (!str) return '';
  return str
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // Quitar tildes y diacríticos
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function onNewEjercicioNameInput(val) {
  const normQuery = normalizeEjercicioText(val);
  const autoBox = $('#new-ej-autocomplete');
  const alertBox = $('#new-ej-dup-alert');
  const saveBtn = $('#btn-save-new-ej');

  if (!normQuery || normQuery.length < 2) {
    if (autoBox) autoBox.style.display = 'none';
    if (alertBox) alertBox.style.display = 'none';
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.classList.remove('btn-secondary');
      saveBtn.classList.add('btn-primary');
      saveBtn.textContent = 'Guardar Ejercicio';
    }
    return;
  }

  const queryWords = normQuery.split(' ').filter(w => w.length > 1);
  if (!queryWords.length) queryWords.push(normQuery);

  // Buscar en cache del catálogo por coincidencia flexible de palabras clave
  const matches = _catalogoCache.filter(ej => {
    const normName = normalizeEjercicioText(ej.nombre);
    return queryWords.every(word => normName.includes(word));
  });

  // Verificar si hay coincidencia exacta (100% igual ignorando acentos y mayúsculas)
  const exactMatch = _catalogoCache.find(ej => normalizeEjercicioText(ej.nombre) === normQuery);

  if (exactMatch) {
    const isCustom = exactMatch.gimnasio_id !== null;
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
      alertBox.style.border = '1px solid #ef4444';
      alertBox.style.color = '#fca5a5';
      alertBox.innerHTML = `⚠️ <b>Ejercicio ya existente:</b> "${exactMatch.nombre}" ya está registrado en tu catálogo (<b style="text-transform:uppercase">${exactMatch.grupo_muscular}</b> • ${isCustom ? 'Personalizado' : 'Oficial Gym Pro'}). No se permiten nombres duplicados.`;
    }
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.classList.remove('btn-primary');
      saveBtn.classList.add('btn-secondary');
      saveBtn.textContent = '⛔ Nombre ya existente';
    }
  } else if (matches.length > 0) {
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.style.background = 'rgba(245, 158, 11, 0.12)';
      alertBox.style.border = '1px solid rgba(245, 158, 11, 0.4)';
      alertBox.style.color = '#fcd34d';
      alertBox.innerHTML = `💡 <b>Sugerencias encontradas:</b> Hay ${matches.length} ejercicio(s) similar(es) en el catálogo. Revisá el desplegable flotante para no duplicar.`;
    }
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.classList.remove('btn-secondary');
      saveBtn.classList.add('btn-primary');
      saveBtn.textContent = 'Guardar Ejercicio';
    }
  } else {
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.style.background = 'rgba(16, 185, 129, 0.12)';
      alertBox.style.border = '1px solid rgba(16, 185, 129, 0.4)';
      alertBox.style.color = '#6ee7b7';
      alertBox.innerHTML = `✅ <b>Nombre disponible:</b> Este ejercicio es nuevo y no coincide con ninguno existente.`;
    }
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.classList.remove('btn-secondary');
      saveBtn.classList.add('btn-primary');
      saveBtn.textContent = 'Guardar Ejercicio';
    }
  }

  // Renderizar desplegable flotante
  if (autoBox) {
    if (!matches.length) {
      autoBox.style.display = 'none';
      return;
    }

    autoBox.innerHTML = `
      <div style="font-size:11px;color:var(--t2);padding:4px 8px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:4px">
        🔍 Ejercicios similares en el catálogo (${matches.length}):
      </div>
    `;

    matches.slice(0, 8).forEach(ej => {
      const isCustom = ej.gimnasio_id !== null;
      const row = document.createElement('div');
      row.style.cssText = 'padding:6px 10px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;transition:var(--tr);margin-bottom:2px';
      row.onmouseover = () => row.style.background = 'rgba(59, 130, 246, 0.2)';
      row.onmouseout = () => row.style.background = 'transparent';

      row.innerHTML = `
        <div style="display:flex;align-items:center;gap:6px;overflow:hidden">
          <span style="font-size:13px;color:var(--t1);white-space:nowrap;text-overflow:ellipsis;overflow:hidden">${ej.nombre}</span>
          <span class="badge ${getGrupoBadgeClass(ej.grupo_muscular)}" style="font-size:9.5px;font-weight:800;text-transform:uppercase">${ej.grupo_muscular}</span>
        </div>
        <span class="badge ${isCustom ? 'b-purple' : 'b-ok'}" style="font-size:9.5px;white-space:nowrap">
          ${isCustom ? 'Personalizado' : 'Oficial'}
        </span>
      `;
      row.onclick = () => selectExistingFromAutocomplete(ej);
      autoBox.appendChild(row);
    });

    autoBox.style.display = 'block';
  }
}

function selectExistingFromAutocomplete(ej) {
  const autoBox = $('#new-ej-autocomplete');
  if (autoBox) autoBox.style.display = 'none';
  
  $('#new-ej-nombre').value = ej.nombre;
  $('#new-ej-grupo').value = ej.grupo_muscular;
  if (ej.descripcion) $('#new-ej-desc').value = ej.descripcion;

  onNewEjercicioNameInput(ej.nombre);
  showToast(`Seleccionaste "${ej.nombre}". Este ejercicio ya existe en el catálogo.`);
}

// Cerrar autocompletar al hacer clic fuera
document.addEventListener('click', (e) => {
  const autoBox = $('#new-ej-autocomplete');
  const inp = $('#new-ej-nombre');
  if (autoBox && inp && !autoBox.contains(e.target) && e.target !== inp) {
    autoBox.style.display = 'none';
  }
});

async function saveNuevoEjercicioCatalogo() {
  const nombre = $('#new-ej-nombre')?.value.trim();
  const grupo = $('#new-ej-grupo')?.value;
  const desc = $('#new-ej-desc')?.value.trim();

  if (!nombre || nombre.length < 3) {
    showToast('El nombre del ejercicio debe tener al menos 3 caracteres', true);
    return;
  }

  // Comprobar antes en el frontend para evitar peticiones innecesarias
  const norm = normalizeEjercicioText(nombre);
  const exact = _catalogoCache.find(e => normalizeEjercicioText(e.nombre) === norm);
  if (exact) {
    const isCust = exact.gimnasio_id !== null;
    showToast(`El ejercicio "${exact.nombre}" ya existe (${isCust ? 'Personalizado' : 'Oficial Gym Pro'} • ${exact.grupo_muscular.toUpperCase()})`, true);
    return;
  }

  const r = await api('catalogo_ejercicios.save', { nombre, grupo_muscular: grupo, descripcion: desc });
  if (r.ok) {
    showToast('¡Ejercicio añadido al catálogo maestro!');
    $('#new-ej-nombre').value = '';
    $('#new-ej-desc').value = '';
    $('#new-ej-dup-alert').style.display = 'none';
    $('#new-ej-autocomplete').style.display = 'none';
    await loadCatalogoCache();
    renderCatalogoManagerList();
  } else {
    showToast(r.msg || 'Error al guardar ejercicio', true);
  }
}

async function openAsignarRutinaModal(preselectPlantillaId = null, preselectAluId = null) {
  if (!_rutinasCache || !_rutinasCache.length) {
    const { ok, data } = await api('rutinas.programas.list', {}, 'GET');
    if (ok) _rutinasCache = data || [];
  }
  if (!_alumnosCache || !_alumnosCache.length) {
    await loadAlumnosOptions();
  }
  
  // Poblar select de plantillas
  const selPlan = $('#asig-plantilla');
  if (selPlan) {
    selPlan.innerHTML = '<option value="">(Seleccionar Plantilla Maestra)</option>';
    const plantillas = (_rutinasCache || []).filter(p => Number(p.es_plantilla) === 1);
    plantillas.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `🏆 ${p.titulo} (${p.dias_reales || p.dias_count} Días • ${p.objetivo})`;
      if (preselectPlantillaId && Number(p.id) === Number(preselectPlantillaId)) opt.selected = true;
      selPlan.appendChild(opt);
    });
  }

  // Poblar select de alumnos
  const selAlu = $('#asig-alumno');
  if (selAlu && _alumnosCache) {
    selAlu.innerHTML = '<option value="">(Seleccionar Alumno / Socio)</option>';
    _alumnosCache.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id;
      opt.textContent = `👤 ${a.nombre} (DNI: ${a.dni || '-'} • Plan: ${a.plan})`;
      if (preselectAluId && Number(a.id) === Number(preselectAluId)) opt.selected = true;
      selAlu.appendChild(opt);
    });
  }

  $('#asig-titulo').value = '';
  openModal('modal-rutina-asignar');
}

async function openRutinaAlumno(aluId, aluNombre) {
  aluId = Number(aluId);
  showToast('Cargando rutina del alumno...', false);
  
  // Precarga inmediata de catálogo de ejercicios en background
  if (!_catalogoCache || !_catalogoCache.length) {
    loadCatalogoCache();
  }

  const { ok, data } = await api('rutinas.programas.list', { alumno_id: aluId }, 'GET');
  if (ok && data && data.length) {
    const rutinasAlu = data.filter(p => Number(p.es_plantilla) === 0 && Number(p.alumno_id) === aluId);
    if (rutinasAlu.length > 0) {
      await openRutinaBuilder(rutinasAlu[0].id);
      return;
    }
  }
  
  await openAsignarRutinaModal(null, aluId);
}

function openAsignarRutinaDirecto() {
  if (!_currentBuilderProg) return;
  closeModal('modal-rutina-builder');
  openAsignarRutinaModal(_currentBuilderProg.id);
}

async function submitAsignarRutina(e) {
  e.preventDefault();
  const aluId = $('#asig-alumno')?.value;
  const plantillaId = $('#asig-plantilla')?.value;
  const titulo = $('#asig-titulo')?.value;

  if (!aluId || !plantillaId) {
    showToast('Selecciona el alumno y la plantilla', true);
    return;
  }

  const r = await api('rutinas.programas.assign', { alumno_id: aluId, plantilla_id: plantillaId, titulo });
  if (r.ok) {
    showToast(r.msg || '¡Rutina clonada y asignada al alumno!');
    closeModal('modal-rutina-asignar');
    _rutinasTab = 'asignadas';
    switchRutinasTab('asignadas');
    loadRutinas();
    if (r.data?.id) {
      openRutinaBuilder(r.data.id);
    }
  } else {
    showToast(r.msg || 'Error al asignar rutina', true);
  }
}

/* ===== NUTRICIÓN ===== */
let _nutriCache = [];
let _nutriCurrentPage = 1;
let _nutriPageSize = 15;

async function loadNutricion() {
  const { ok, data } = await api('nutricion.list', {}, 'GET');
  if (!ok) return;
  _nutriCache = data || [];
  _nutriCurrentPage = 1;
  renderNutricionTable();
}

function renderNutricionTable() {
  const tb = $('#tbl-nutri tbody') || $('#tbl-nutricion tbody');
  const mob = $('#nutri-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mob) mob.innerHTML = '';

  const totalRecords = _nutriCache.length;
  const pageSize = Number(_nutriPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_nutriCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = _nutriCache.slice(startIdx, endIdx);

  if (!totalRecords) {
    tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--t-mut);padding:24px">No hay planes nutricionales asignados aún. Hacé click en "+ Asignar Plan a Socio" para cargar uno.</td></tr>';
    if (mob) mob.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No hay planes nutricionales asignados.</div>';
  } else {
    pageItems.forEach(n => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b style="color:var(--t1)">${escapeHtml(n.alumno_nombre)}</b></td>
        <td><b>${escapeHtml(n.titulo)}</b></td>
        <td><span class="badge b-purple">${escapeHtml(n.calorias_aprox)} kcal</span></td>
        <td><span class="badge b-info">${escapeHtml(n.coach_nombre || 'General')}</span></td>
        <td>${fmtDate(n.fecha_asignacion)}</td>
        <td style="text-align:right;white-space:nowrap">
          <button class="btn btn-sm btn-secondary" onclick='openNutriModal(${JSON.stringify(n)})'>✏️ Editar</button>
          <button class="btn btn-sm btn-danger" onclick='deleteNutri(${n.id}, "${(n.titulo || '').replace(/'/g, "\\'")}")'>🗑️</button>
        </td>
      `;
      tb.appendChild(tr);
    });
  }

  renderGenericPagination({
    containerId: 'nutri-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'planes',
    scrollTargetId: 'page-nutricion',
    onPageChange: (p) => {
      _nutriCurrentPage = p;
      renderNutricionTable();
    }
  });
}

function changeNutriPageSize(sz) {
  _nutriPageSize = Number(sz) || 15;
  _nutriCurrentPage = 1;
  renderNutricionTable();
}

function openNutriModal(row = null) {
  if (typeof loadAlumnosOptions === 'function') loadAlumnosOptions();
  $('#nutri-id').value = row?.id || '';
  $('#nutri-alumno').value = row?.alumno_id || '';
  $('#nutri-titulo').value = row?.titulo || '';
  $('#nutri-cal').value = row?.calorias_aprox || 2200;
  $('#nutri-det').value = row?.detalles || '';
  if ($('#nutri-modal-title')) {
    $('#nutri-modal-title').textContent = row ? '✏️ Editar Plan Nutricional' : '🥗 Cargar / Asignar Plan Nutricional';
  }
  openModal('modal-nutri');
}

async function deleteNutri(id, titulo) {
  const ok = await systemConfirm({
    title: '¿Eliminar Plan Nutricional?',
    message: `¿Estás seguro de eliminar el plan nutricional <b>${titulo}</b>?<br><br><small style="color:var(--t2)">El alumno dejará de visualizar este plan de comidas en su app.</small>`,
    confirmText: 'Sí, Eliminar Plan',
    cancelText: 'Cancelar',
    icon: '🗑️',
    isDanger: true
  });
  if (!ok) return;

  const r = await api('nutricion.delete', { id });
  if (r.ok) {
    showToast('Plan nutricional eliminado');
    loadNutricion();
  } else {
    showToast(r.msg || 'Error al eliminar', true);
  }
}

async function saveNutri(e) {
  e.preventDefault();
  const data = {
    id: $('#nutri-id').value,
    alumno_id: $('#nutri-alumno').value,
    titulo: $('#nutri-titulo').value,
    calorias_aprox: $('#nutri-cal').value,
    detalles: $('#nutri-det').value
  };
  const r = await api('nutricion.save', data);
  if (r.ok) {
    showToast('Plan nutricional guardado');
    closeModal('modal-nutri');
    loadNutricion();
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== ALUMNOS ===== */
let _alumnosCache = [];
let _debounceAluTimer;
function debounceLoadAlumnos() {
  clearTimeout(_debounceAluTimer);
  _debounceAluTimer = setTimeout(loadAlumnos, 250);
}

let _aluCurrentPage = 1;
let _aluPageSize = 15;
let _coachAluCurrentPage = 1;
let _coachAluPageSize = 15;

async function loadAlumnos() {
  const isCoach = CURRENT_USER.role === 'coach';
  const q = (isCoach ? $('#coach-alu-q')?.value : $('#alu-q')?.value)?.trim() || '';
  const plan = (isCoach ? $('#coach-alu-plan')?.value : $('#alu-plan')?.value) || '';
  const estado = (isCoach ? $('#coach-alu-estado')?.value : $('#alu-estado')?.value) || '';
  const prof = $('#alu-prof')?.value || '';

  const { ok, data } = await api('alumnos.list', { q, plan, estado, profesor_id: prof }, 'GET');
  if (!ok) return;
  _alumnosCache = data || [];
  if (isCoach) _coachAluCurrentPage = 1;
  else _aluCurrentPage = 1;
  renderAlumnosPage();
}

function renderAlumnosPage() {
  const isCoach = CURRENT_USER.role === 'coach';
  const tb = isCoach ? $('#tbl-coach-alumnos tbody') : ($('#tbl-alu tbody') || $('#tbl-coach-alumnos tbody'));
  const mob = isCoach ? $('#coach-alumnos-cards') : $('#alumnos-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mob) mob.innerHTML = '';

  const data = _alumnosCache || [];
  const totalRecords = data.length;
  const pageSize = isCoach ? Number(_coachAluPageSize || 15) : Number(_aluPageSize || 15);
  const curPage = isCoach ? _coachAluCurrentPage : _aluCurrentPage;
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(curPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = data.slice(startIdx, endIdx);

  const totalCols = isCoach ? 10 : 11;
  if (!totalRecords) {
    tb.innerHTML = `<tr><td colspan="${totalCols}" style="text-align:center;color:var(--t-mut);padding:28px">${isCoach ? 'No tenés alumnos asignados que coincidan con la búsqueda.' : 'No se encontraron alumnos.'}</td></tr>`;
    if (mob) mob.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron alumnos.</div>';
  } else {
    pageItems.forEach(r => {
      const isProximo = r.alerta === 'proximo';
      const badgeCls = r.estado === 'vencido' ? 'b-bad' : (isProximo ? 'b-warn pulse' : (r.estado === 'pausado' ? 'b-warn' : 'b-ok'));
      const badgeTxt = r.estado === 'vencido' ? 'Vencido' : (isProximo ? 'Próximo' : (r.estado === 'pausado' ? 'Pausado' : 'Activo'));
      const saldoBadge = r.saldo_mes > 0 ? `<span class="badge b-warn">Debe $ ${fmtMoney(r.saldo_mes)}</span>` : `<span class="badge b-ok">Al Día</span>`;

      const telClean = (r.telefono || '').replace(/\D/g, '');
      const waLink = telClean ? `<a href="https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(r.nombre)}" target="_blank" style="color:var(--ok);text-decoration:none;margin-left:4px" title="WhatsApp">💬</a>` : '';

      const tr = document.createElement('tr');
      
      if (isCoach) {
        tr.innerHTML = `
          <td>
            <b style="font-size:14px;color:var(--t1)">${escapeHtml(r.nombre)}</b>
            ${r.dni ? `<br><small style="color:var(--t2);font-weight:600">DNI: ${escapeHtml(r.dni)}</small>` : ''}
          </td>
          <td style="white-space:nowrap">${escapeHtml(r.telefono || '-')} ${waLink}</td>
          <td><span class="badge b-info" style="font-size:11.5px;padding:4px 8px">${escapeHtml(r.plan)}</span></td>
          <td><span style="color:var(--t2);font-size:12.5px">${escapeHtml(r.actividades || 'Musculación')}</span></td>
          <td style="font-weight:700;white-space:nowrap;color:#60a5fa">$ ${fmtMoney(r.cuota_mes)}</td>
          <td style="color:var(--ok);font-weight:700;white-space:nowrap">$ ${fmtMoney(r.abonado_mes)}</td>
          <td style="white-space:nowrap">${saldoBadge}</td>
          <td style="white-space:nowrap;font-size:13px"><b>${fmtDate(r.fecha_vencimiento)}</b></td>
          <td style="white-space:nowrap"><span class="badge ${badgeCls}">${badgeTxt}</span></td>
          <td style="text-align:right;white-space:nowrap">
            <div style="display:inline-grid;grid-template-columns:1fr 1fr;gap:4px;min-width:175px">
              <button class="btn btn-xs btn-info" style="font-weight:700;padding:5px 8px;justify-content:center;box-shadow:0 2px 6px rgba(59,130,246,0.25)" title="Ver Ficha Integral 360°" onclick="openAlumnoFicha(${r.id})">👁️ Ficha</button>
              <button class="btn btn-xs btn-success" style="font-weight:700;padding:5px 8px;justify-content:center" title="Cobrar Cuota al Alumno" onclick="openPagoModal('alumno', ${r.id})">💵 Cobrar</button>
              <button class="btn btn-xs btn-primary" style="font-weight:700;padding:5px 8px;justify-content:center" title="Editar / Asignar Rutina" onclick="openRutinaAlumno(${r.id}, '${(r.nombre || '').replace(/'/g, "\\'")}')">📋 Rutina</button>
              <button class="btn btn-xs btn-danger" style="font-weight:700;padding:5px 8px;justify-content:center" title="Dar de baja de mi lista a cargo" onclick="desvincularAlumnoCoach(${r.id}, '${(r.nombre || '').replace(/'/g, "\\'")}')">🚫 Desvincular</button>
            </div>
          </td>`;
      } else {
        tr.innerHTML = `
          <td>
            <b style="font-size:14px;color:var(--t1)">${escapeHtml(r.nombre)}</b>
            ${r.dni ? `<br><small style="color:var(--t2);font-weight:600">DNI: ${escapeHtml(r.dni)}</small>` : ''}
          </td>
          <td style="white-space:nowrap">${escapeHtml(r.telefono || '-')} ${waLink}</td>
          <td><span class="badge b-info" style="font-size:11.5px;padding:4px 8px">${escapeHtml(r.plan)}</span></td>
          <td><span style="color:var(--t2);font-size:12.5px">${escapeHtml(r.actividades || 'Musculación')}</span></td>
          <td style="font-weight:700;white-space:nowrap;color:#60a5fa">$ ${fmtMoney(r.cuota_mes)}</td>
          <td style="color:var(--ok);font-weight:700;white-space:nowrap">$ ${fmtMoney(r.abonado_mes)}</td>
          <td style="white-space:nowrap">${saldoBadge}</td>
          <td style="white-space:nowrap;font-size:13px"><b>${fmtDate(r.fecha_vencimiento)}</b></td>
          <td style="white-space:nowrap"><span class="badge ${badgeCls}">${badgeTxt}</span></td>
          <td>${r.profesor ? `<span class="badge b-purple" style="font-size:11.5px">🏋️ ${escapeHtml(r.profesor)}</span>` : `<span style="color:var(--t-mut);font-size:12px">General</span>`}</td>
          <td style="text-align:right;white-space:nowrap">
            <div style="display:inline-flex;flex-direction:column;gap:4px;align-items:stretch;min-width:145px">
              <div style="display:flex;gap:4px">
                <button class="btn btn-xs btn-info" style="flex:1;font-weight:700" title="Ver Ficha 360°" onclick="openAlumnoFicha(${r.id})">👁️ Ficha</button>
                <button class="btn btn-xs btn-success" style="flex:1" title="Cobrar Cuota" onclick="openPagoModal('alumno', ${r.id})">💵 Cobrar</button>
                <button class="btn btn-xs btn-primary" style="flex:1" title="Rutina" onclick="openRutinaAlumno(${r.id}, '${(r.nombre || '').replace(/'/g, "\\'")}')">📋 Rutina</button>
              </div>
              <div style="display:flex;gap:4px">
                ${(r.estado === 'pausado') ? `
                  <button class="btn btn-xs btn-success" style="flex:1;font-weight:700" title="Reactivar y habilitar cuenta del alumno" onclick="toggleSuspensionAlumno(${r.id}, '${(r.nombre || '').replace(/'/g, "\\'")}', 'activo')">🔓 Reactivar</button>
                ` : `
                  <button class="btn btn-xs btn-warn" style="flex:1;font-weight:700" title="Suspender cuenta del alumno" onclick="toggleSuspensionAlumno(${r.id}, '${(r.nombre || '').replace(/'/g, "\\'")}', 'pausado')">⏸️ Suspender</button>
                `}
                <button class="btn btn-xs btn-secondary" style="flex:1" title="Editar Alumno" onclick='openAluModal(${JSON.stringify(r)})'>✏️ Editar</button>
              </div>
            </div>
          </td>`;
      }
      tb.appendChild(tr);
    });
  }

  // Renderizar Barra de Paginación
  const barId = isCoach ? 'coach-alumnos-pagination-bar' : 'alumnos-pagination-bar';
  const targetId = isCoach ? 'page-coach-alumnos' : 'page-alumnos';
  renderGenericPagination({
    containerId: barId,
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'alumnos',
    scrollTargetId: targetId,
    onPageChange: (p) => {
      if (isCoach) _coachAluCurrentPage = p;
      else _aluCurrentPage = p;
      renderAlumnosPage();
    }
  });
}

function changeAluPageSize(sz) {
  _aluPageSize = Number(sz) || 15;
  _aluCurrentPage = 1;
  renderAlumnosPage();
}

function changeCoachAluPageSize(sz) {
  _coachAluPageSize = Number(sz) || 15;
  _coachAluCurrentPage = 1;
  renderAlumnosPage();
}

/* ===== FICHA 360° INTEGRAL DEL ALUMNO ===== */
let _currentFichaData = null;

async function openAlumnoFicha(aluId) {
  aluId = Number(aluId);
  if (!aluId) return;

  showToast('Cargando ficha del alumno...', false);
  const { ok, data, msg } = await api('alumnos.ficha', { id: aluId }, 'GET');
  if (!ok || !data) {
    showToast(msg || 'Error al cargar ficha del alumno', true);
    return;
  }

  _currentFichaData = data;
  const a = data.alumno;

  // 1. Cabecera & Badges
  if ($('#ficha-alu-nombre')) $('#ficha-alu-nombre').textContent = a.nombre || 'Alumno';
  
  const gymNombre = a.gimnasio_nombre || CURRENT_USER.gimnasio_nombre || 'Gimnasio';
  if ($('#ficha-alu-sede-badge')) {
    $('#ficha-alu-sede-badge').innerHTML = `🏢 Sede: <b>${escapeHtml(gymNombre)}</b>`;
  }
  if ($('#ficha-alu-sede')) {
    $('#ficha-alu-sede').textContent = `🏢 Sede: ${gymNombre}`;
  }
  if ($('#ficha-alu-coach-tag')) {
    $('#ficha-alu-coach-tag').innerHTML = a.coach_nombre ? `🏋️ Coach: <b style="color:var(--t1)">${escapeHtml(a.coach_nombre)}</b>` : '<span style="color:var(--t-mut)">🏋️ Sin coach exclusivo</span>';
  }

  const isVencido = a.estado === 'vencido' || (a.saldo_mes > 0 && Number(a.dias_restantes) < 0);
  const badgeEst = $('#ficha-alu-badge-estado');
  if (badgeEst) {
    badgeEst.className = isVencido ? 'badge b-bad' : (a.estado === 'pausado' ? 'badge b-warn' : 'badge b-ok');
    badgeEst.textContent = isVencido ? '⛔ Cuota Vencida' : (a.estado === 'pausado' ? '⏸ Membresía Pausada' : '✅ Cuota al Día');
  }

  const badgePlan = $('#ficha-alu-badge-plan');
  if (badgePlan) {
    badgePlan.textContent = `🏷️ Plan ${String(a.plan || '3x').toUpperCase()}`;
  }

  if ($('#ficha-alu-sub')) {
    $('#ficha-alu-sub').textContent = `DNI: ${a.dni || '-'} • Teléfono: ${a.telefono || '-'}`;
  }

  // Botón de suspender / reactivar en la Ficha 360 (oculto para coach)
  const btnSusp = $('#ficha-btn-suspender');
  if (btnSusp) {
    if (CURRENT_USER.role === 'coach') {
      btnSusp.style.display = 'none';
    } else {
      btnSusp.style.display = 'inline-flex';
      if (a.estado === 'pausado') {
        btnSusp.className = 'btn btn-xs btn-success';
        btnSusp.innerHTML = '🔓 Reactivar Cuenta';
        btnSusp.title = 'Reactivar y habilitar cuenta del alumno';
      } else {
        btnSusp.className = 'btn btn-xs btn-warn';
        btnSusp.innerHTML = '⏸️ Suspender Cuenta';
        btnSusp.title = 'Suspender cuenta del alumno (conservando todos sus datos)';
      }
    }
  }

  // 2. Botón de WhatsApp
  const btnWa = $('#ficha-btn-wa');
  if (btnWa) {
    const cleanTel = (a.telefono || '').replace(/\D/g, '');
    if (cleanTel) {
      btnWa.style.display = 'inline-flex';
      btnWa.href = `https://wa.me/${cleanTel}?text=${encodeURIComponent('Hola ' + a.nombre + '! Te escribo desde el gimnasio.')}`;
    } else {
      btnWa.style.display = 'none';
    }
  }

  // 3. Tab 1: Resumen & Membresía
  if ($('#ficha-res-plan')) $('#ficha-res-plan').textContent = `Plan ${String(a.plan || '3x').toUpperCase()}`;
  if ($('#ficha-res-actividades')) $('#ficha-res-actividades').textContent = a.actividades || 'Musculación, Funcional';
  
  const saldo = parseFloat(a.saldo_mes || 0);
  const cuota = parseFloat(a.cuota_mes || 0);
  const abonado = parseFloat(a.abonado_mes || 0);
  
  if ($('#ficha-res-saldo')) {
    $('#ficha-res-saldo').textContent = saldo <= 0 ? '$ 0.00 (Al Día)' : `$ ${fmtMoney(saldo)} (Deuda)`;
    $('#ficha-res-saldo').style.color = saldo <= 0 ? 'var(--ok)' : 'var(--err)';
  }
  if ($('#ficha-res-cuota-detalle')) {
    $('#ficha-res-cuota-detalle').textContent = `Pactado: $ ${fmtMoney(cuota)} • Abonado: $ ${fmtMoney(abonado)}`;
  }

  if ($('#ficha-res-venc')) $('#ficha-res-venc').textContent = fmtDate(a.fecha_vencimiento);
  if ($('#ficha-res-dias-rest')) {
    const dias = Number(a.dias_restantes);
    if (dias > 0) {
      $('#ficha-res-dias-rest').textContent = `Faltan ${dias} día${dias === 1 ? '' : 's'} para el vencimiento`;
      $('#ficha-res-dias-rest').style.color = 'var(--t2)';
    } else if (dias === 0) {
      $('#ficha-res-dias-rest').textContent = '¡Vence hoy!';
      $('#ficha-res-dias-rest').style.color = 'var(--warn)';
    } else {
      $('#ficha-res-dias-rest').textContent = `Vencido hace ${Math.abs(dias)} día${Math.abs(dias) === 1 ? '' : 's'}`;
      $('#ficha-res-dias-rest').style.color = 'var(--err)';
    }
  }

  if ($('#ficha-res-email')) $('#ficha-res-email').textContent = a.email || '-';
  if ($('#ficha-res-tel')) $('#ficha-res-tel').textContent = a.telefono || '-';
  if ($('#ficha-res-alta')) $('#ficha-res-alta').textContent = fmtDate(a.fecha_inicio || a.created_at);
  if ($('#ficha-res-coach')) $('#ficha-res-coach').textContent = a.coach_nombre ? `🏋️ ${a.coach_nombre}` : 'Sin coach exclusivo asignado';

  // 4. Tab 2: Rutina & Ejercicios
  const rut = data.rutina;
  const diasRut = data.rutina_dias || [];
  if (rut) {
    if ($('#ficha-rutina-empty')) $('#ficha-rutina-empty').style.display = 'none';
    if ($('#ficha-rutina-content')) $('#ficha-rutina-content').style.display = 'block';
    if ($('#ficha-rut-titulo')) $('#ficha-rut-titulo').textContent = `📋 ${rut.titulo}`;
    if ($('#ficha-rut-meta')) $('#ficha-rut-meta').textContent = `Objetivo: ${rut.objetivo || 'General'} • Nivel: ${rut.nivel || 'Intermedio'} • ${diasRut.length} Día${diasRut.length === 1 ? '' : 's'} de entrenamiento`;

    const diasCont = $('#ficha-rut-dias-container');
    if (diasCont) {
      diasCont.innerHTML = '';
      if (!diasRut.length) {
        diasCont.innerHTML = '<div style="grid-column:1/-1;color:var(--t-mut);font-size:13px;padding:12px">El programa no tiene días configurados.</div>';
      } else {
        diasRut.forEach(d => {
          const card = document.createElement('div');
          card.className = 'card';
          card.style.padding = '14px';
          card.style.marginBottom = '0';
          card.style.background = 'rgba(255,255,255,0.02)';
          card.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <strong style="color:#60a5fa;font-size:13.5px">Día ${d.numero_dia}: ${d.nombre_dia || 'Sesión de Entrenamiento'}</strong>
              <span class="badge b-info" style="font-size:10.5px">${d.ejercicios_count || 0} ejercicio${d.ejercicios_count == 1 ? '' : 's'}</span>
            </div>
            <p style="font-size:12px;color:var(--t2);margin:0;line-height:1.4">${d.enfoque_muscular ? `💪 Enfoque: <b>${d.enfoque_muscular}</b>` : 'Entrenamiento general'}</p>
          `;
          diasCont.appendChild(card);
        });
      }
    }
  } else {
    if ($('#ficha-rutina-empty')) $('#ficha-rutina-empty').style.display = 'block';
    if ($('#ficha-rutina-content')) $('#ficha-rutina-content').style.display = 'none';
  }

  // 4.1 Historial de Check-ins y Rutinas Realizadas por el Alumno
  const tbCheckins = $('#tbl-ficha-checkins tbody');
  const countBadge = $('#ficha-checkins-count-badge');
  const checkins = data.rutinas_checkins || [];
  if (countBadge) countBadge.textContent = `${checkins.length} Entrenamiento${checkins.length === 1 ? '' : 's'} Realizado${checkins.length === 1 ? '' : 's'}`;
  
  if (tbCheckins) {
    tbCheckins.innerHTML = '';
    if (!checkins.length) {
      tbCheckins.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--t-mut);padding:18px">El alumno aún no registró check-ins de sus entrenamientos.</td></tr>';
    } else {
      checkins.forEach(c => {
        const tr = document.createElement('tr');
        const stars = '⭐'.repeat(c.nivel_esfuerzo || 3);
        const rutSafe = (c.rutina_nombre || 'Entrenamiento').replace(/"/g, '&quot;').replace(/\r?\n/g, ' ');
        const obsSafe = (c.observaciones || '').replace(/"/g, '&quot;').replace(/\r?\n/g, ' ');
        const feedbackBtn = `<button class="btn btn-xs btn-primary" style="font-weight:700" onclick="openCoachFeedbackModal(${c.id}, '${rutSafe.replace(/'/g, "\\'")}', '${obsSafe.replace(/'/g, "\\'")}')">💬 Responder</button>`;
        const feedbackTxt = c.coach_feedback 
          ? `<div style="background:rgba(139,92,246,0.12);border-left:3px solid #a855f7;padding:4px 8px;border-radius:4px;font-size:11.5px;color:#c084fc"><b>Coach:</b> ${c.coach_feedback}</div>` 
          : `<span style="color:var(--t-mut);font-size:11.5px">Sin devolución</span>`;

        tr.innerHTML = `
          <td><b>${fmtDate(c.fecha)}</b><br><small style="color:var(--t2)">${c.hora || ''}</small></td>
          <td><b style="color:var(--t1)">${c.rutina_nombre || 'Rutina'}</b></td>
          <td><span class="badge b-info">${c.duracion_min || 60} min</span><br><small title="Nivel de esfuerzo">${stars}</small></td>
          <td><span style="font-size:12px;color:var(--t2)">${c.observaciones || 'Sesión completada'}</span></td>
          <td>${feedbackTxt}</td>
          <td style="text-align:right">${feedbackBtn}</td>
        `;
        tbCheckins.appendChild(tr);
      });
    }
  }

  // 5. Tab 3: Asistencias & Constancia
  const asis = data.asistencias || { mes: 0, semana: 0, historial: [], ultima: null };
  if ($('#ficha-asis-mes')) $('#ficha-asis-mes').textContent = asis.mes || 0;
  if ($('#ficha-asis-mes-sub')) $('#ficha-asis-mes-sub').textContent = `${asis.mes || 0} ingresos este mes`;
  if ($('#ficha-asis-sem')) $('#ficha-asis-sem').textContent = asis.semana || 0;
  if ($('#ficha-asis-ultima')) $('#ficha-asis-ultima').textContent = asis.ultima ? fmtDate(asis.ultima) : 'Sin asistencias registradas';

  const badgeStatusAsis = $('#ficha-asis-status-badge');
  if (badgeStatusAsis) {
    if (asis.mes >= 12) {
      badgeStatusAsis.innerHTML = '<span class="badge b-ok" style="font-weight:800">🟢 Muy Constante (3+ x semana)</span>';
    } else if (asis.mes >= 4) {
      badgeStatusAsis.innerHTML = '<span class="badge b-info" style="font-weight:800">🟡 Asistencia Regular</span>';
    } else {
      badgeStatusAsis.innerHTML = '<span class="badge b-warn" style="font-weight:800">🔴 Baja Frecuencia</span>';
    }
  }

  const tbAsis = $('#tbl-ficha-asistencias tbody');
  if (tbAsis) {
    tbAsis.innerHTML = '';
    const hist = asis.historial || [];
    if (!hist.length) {
      tbAsis.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--t-mut);padding:18px">No hay asistencias registradas recientemente.</td></tr>';
    } else {
      hist.forEach(h => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><b>${fmtDate(h.fecha)}</b></td>
          <td><span class="badge b-info" style="font-size:11px">${h.hora || '-'}</span></td>
          <td><span style="color:var(--t2);font-size:12.5px">${h.observaciones || 'Ingreso registrado en sala'}</span></td>
        `;
        tbAsis.appendChild(tr);
      });
    }
  }

  // 6. Tab 4: Historial de Pagos
  const tbPag = $('#tbl-ficha-pagos tbody');
  if (tbPag) {
    tbPag.innerHTML = '';
    const pagos = data.pagos || [];
    if (!pagos.length) {
      tbPag.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--t-mut);padding:18px">No hay pagos registrados para este alumno.</td></tr>';
    } else {
      pagos.forEach(p => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><b>${fmtDate(p.fecha_pago)}</b></td>
          <td><span class="badge b-info" style="font-size:11px">${String(p.plan || 'Cuota').toUpperCase()}</span></td>
          <td><span style="text-transform:capitalize;color:var(--t2);font-size:12.5px">${p.medio_pago || 'Efectivo'}</span></td>
          <td><span style="color:var(--t2);font-size:12px">${p.observaciones || '-'}</span></td>
          <td style="text-align:right;font-weight:800;color:var(--ok)">$ ${fmtMoney(p.monto)}</td>
        `;
        tbPag.appendChild(tr);
      });
    }
  }

  // 7. Tab 5: Notas & Salud
  if ($('#ficha-notas-alu-id')) $('#ficha-notas-alu-id').value = a.id;
  if ($('#ficha-notas-alumno-txt')) $('#ficha-notas-alumno-txt').value = a.notas_alumno || '';
  if ($('#ficha-notas-coach-txt')) $('#ficha-notas-coach-txt').value = a.notas_coach || '';

  // Abrir pestaña Resumen por defecto
  switchFichaTab('tab-resumen');

  openModal('modal-alumno-ficha');
}

function switchFichaTab(tabKey, btnEl = null) {
  $$('.ficha-tab-pane').forEach(p => p.style.display = 'none');
  const targetPane = $('#ficha-' + tabKey);
  if (targetPane) targetPane.style.display = 'block';

  $$('.tab-ficha').forEach(b => b.classList.remove('active'));
  if (btnEl) {
    btnEl.classList.add('active');
  } else {
    const firstMatch = $(`.tab-ficha[onclick*="${tabKey}"]`);
    if (firstMatch) firstMatch.classList.add('active');
  }
}

function onFichaCobrarClick() {
  if (!_currentFichaData || !_currentFichaData.alumno) return;
  const aluId = _currentFichaData.alumno.id;
  closeModal('modal-alumno-ficha');
  openPagoModal('alumno', aluId);
}

function onFichaRutinaClick() {
  if (!_currentFichaData || !_currentFichaData.alumno) return;
  const aluId = _currentFichaData.alumno.id;
  const aluNombre = _currentFichaData.alumno.nombre;
  closeModal('modal-alumno-ficha');
  openRutinaAlumno(aluId, aluNombre);
}

function onFichaToggleSuspensionClick() {
  if (!_currentFichaData || !_currentFichaData.alumno) return;
  const a = _currentFichaData.alumno;
  const nuevoEstado = (a.estado === 'pausado') ? 'activo' : 'pausado';
  toggleSuspensionAlumno(a.id, a.nombre, nuevoEstado);
}

async function saveFichaNotas(e) {
  e.preventDefault();
  if (!_currentFichaData || !_currentFichaData.alumno) return;

  const aluId = $('#ficha-notas-alu-id')?.value || _currentFichaData.alumno.id;
  const notasAlu = $('#ficha-notas-alumno-txt')?.value || '';
  const notasCoach = $('#ficha-notas-coach-txt')?.value || '';

  const btnSave = $('#btn-save-ficha-notas');
  if (btnSave) {
    btnSave.disabled = true;
    btnSave.textContent = 'Guardando...';
  }

  const res = await api('alumnos.save_notes', {
    id: aluId,
    notas_alumno: notasAlu,
    notas_coach: notasCoach
  });

  if (btnSave) {
    btnSave.disabled = false;
    btnSave.textContent = '💾 Guardar Notas de Seguimiento';
  }

  if (res.ok) {
    showToast('¡Notas de seguimiento guardadas exitosamente!');
    _currentFichaData.alumno.notas_alumno = notasAlu;
    _currentFichaData.alumno.notas_coach = notasCoach;
  } else {
    showToast(res.msg || 'Error al guardar notas', true);
  }
}

function setFieldError(fieldId, errId, msg) {
  const inp = $('#' + fieldId);
  const errEl = $('#' + errId);
  if (inp) inp.classList.add('inp-error');
  if (errEl) {
    errEl.innerHTML = `⚠️ ${msg}`;
    errEl.style.display = 'block';
  }
}

function clearFieldError(fieldId, errId) {
  const inp = $('#' + fieldId);
  const errEl = $('#' + errId);
  if (inp) inp.classList.remove('inp-error');
  if (errEl) {
    errEl.innerHTML = '';
    errEl.style.display = 'none';
  }
}

function clearAluErrors() {
  clearFieldError('alu-nombre', 'err-alu-nombre');
  clearFieldError('alu-dni', 'err-alu-dni');
  clearFieldError('alu-telefono', 'err-alu-telefono');
  clearFieldError('alu-plan-inp', 'err-alu-plan');
  clearFieldError('alu-actividades', 'err-alu-actividades');
  clearFieldError('alu-inicio', 'err-alu-inicio');
  clearFieldError('alu-venc', 'err-alu-venc');
  clearFieldError('alu-estado-inp', 'err-alu-estado');
  if ($('#err-alu-prof')) clearFieldError('alu-prof-inp', 'err-alu-prof');
}

function setupAlumnoRealtimeValidation() {
  const nombreInp = $('#alu-nombre');
  if (nombreInp && !nombreInp.dataset.bound) {
    nombreInp.dataset.bound = 'true';
    nombreInp.addEventListener('input', () => {
      const val = nombreInp.value.trim();
      if (!val) {
        setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre y apellido son obligatorios.');
      } else if (/\d/.test(val)) {
        setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre no debe contener caracteres numéricos.');
      } else if (val.length < 3) {
        setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre debe tener al menos 3 caracteres.');
      } else {
        clearFieldError('alu-nombre', 'err-alu-nombre');
      }
    });
  }

  const dniInp = $('#alu-dni');
  if (dniInp && !dniInp.dataset.bound) {
    dniInp.dataset.bound = 'true';
    dniInp.addEventListener('input', () => {
      const val = dniInp.value.trim();
      if (!val) {
        setFieldError('alu-dni', 'err-alu-dni', 'El DNI es obligatorio para evitar registros duplicados.');
      } else if (/[a-zA-Z]/.test(val)) {
        setFieldError('alu-dni', 'err-alu-dni', 'El DNI solo puede contener números, sin letras ni puntos.');
      } else {
        const digits = val.replace(/\D/g, '');
        if (digits.length < 7 || digits.length > 9) {
          setFieldError('alu-dni', 'err-alu-dni', 'El DNI debe contener entre 7 y 9 dígitos numéricos.');
        } else {
          clearFieldError('alu-dni', 'err-alu-dni');
        }
      }
    });
  }

  const telInp = $('#alu-telefono');
  if (telInp && !telInp.dataset.bound) {
    telInp.dataset.bound = 'true';
    telInp.addEventListener('input', () => {
      const val = telInp.value.trim();
      if (val && /[a-zA-Z]/.test(val)) {
        setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede contener letras. Solo números (ej: 2657506957 o +54 9 2657...).');
      } else if (val) {
        const digits = val.replace(/\D/g, '');
        if (digits.length < 7) {
          setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono debe contener al menos 7 dígitos numéricos.');
        } else if (digits.length > 15) {
          setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede superar los 15 dígitos numéricos.');
        } else {
          clearFieldError('alu-telefono', 'err-alu-telefono');
        }
      } else {
        clearFieldError('alu-telefono', 'err-alu-telefono');
      }
    });
  }

  const planInp = $('#alu-plan-inp');
  const inicioInp = $('#alu-inicio');
  const vencInp = $('#alu-venc');

  if (planInp && !planInp.dataset.bound) {
    planInp.dataset.bound = 'true';
    planInp.addEventListener('change', () => {
      clearFieldError('alu-plan-inp', 'err-alu-plan');
      if (inicioInp && inicioInp.value && vencInp) {
        vencInp.value = calcVenc(inicioInp.value, planInp.value);
        clearFieldError('alu-venc', 'err-alu-venc');
      }
    });
  }

  if (inicioInp && !inicioInp.dataset.bound) {
    inicioInp.dataset.bound = 'true';
    inicioInp.addEventListener('change', () => {
      if (!inicioInp.value) {
        setFieldError('alu-inicio', 'err-alu-inicio', 'La fecha de inicio es obligatoria.');
      } else {
        clearFieldError('alu-inicio', 'err-alu-inicio');
        if (planInp && vencInp) {
          vencInp.value = calcVenc(inicioInp.value, planInp.value || '3x');
          clearFieldError('alu-venc', 'err-alu-venc');
        }
      }
    });
  }

  if (vencInp && !vencInp.dataset.bound) {
    vencInp.dataset.bound = 'true';
    vencInp.addEventListener('change', () => {
      if (!vencInp.value) {
        setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento es obligatoria.');
      } else if (inicioInp && inicioInp.value && vencInp.value < inicioInp.value) {
        setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.');
      } else {
        clearFieldError('alu-venc', 'err-alu-venc');
      }
    });
  }
}

function openAluModal(row = null) {
  if (CURRENT_USER.role !== 'admin_general' && CURRENT_USER.role !== 'dueno') {
    showToast('No tenés permisos para registrar o editar socios.', true);
    return;
  }
  clearAluErrors();
  setupAlumnoRealtimeValidation();
  $('#alu-modal-title').textContent = row ? 'Editar Alumno' : 'Registrar Nuevo Alumno';
  $('#alu-id').value = row?.id || '';
  $('#alu-nombre').value = row?.nombre || '';
  $('#alu-dni').value = row?.dni || '';
  $('#alu-telefono').value = row?.telefono || '';
  $('#alu-plan-inp').value = row?.plan || '3x';
  $('#alu-actividades').value = row?.actividades || 'Musculación, Funcional';
  $('#alu-inicio').value = row?.fecha_inicio || currentDate();
  $('#alu-venc').value = row?.fecha_vencimiento || calcVenc(row?.fecha_inicio || currentDate(), row?.plan || '3x');
  $('#alu-estado-inp').value = row?.estado || 'activo';
  if ($('#alu-prof-inp')) $('#alu-prof-inp').value = row?.profesor_id || '';
  openModal('modal-alu');
}

async function saveAlumno(e) {
  e.preventDefault();
  clearAluErrors();

  const nombreVal = ($('#alu-nombre').value || '').trim();
  const dniVal = ($('#alu-dni').value || '').trim();
  const telVal = ($('#alu-telefono').value || '').trim();
  const planVal = $('#alu-plan-inp').value;
  const actVal = ($('#alu-actividades').value || '').trim();
  const iniVal = $('#alu-inicio').value;
  const vencVal = $('#alu-venc').value;
  const estVal = $('#alu-estado-inp').value;
  const profVal = $('#alu-prof-inp')?.value || '';

  let hasError = false;
  let firstErrEl = null;

  // 1. Validar Nombre
  if (!nombreVal) {
    setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre y apellido son obligatorios.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-nombre');
  } else if (nombreVal.length < 3) {
    setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre debe tener al menos 3 caracteres.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-nombre');
  } else if (/\d/.test(nombreVal)) {
    setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre no debe contener caracteres numéricos.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-nombre');
  }

  // 2. Validar DNI
  if (!dniVal) {
    setFieldError('alu-dni', 'err-alu-dni', 'El DNI es obligatorio para evitar registros duplicados.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-dni');
  } else if (/[a-zA-Z]/.test(dniVal)) {
    setFieldError('alu-dni', 'err-alu-dni', 'El DNI solo puede contener números, sin letras ni puntos.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-dni');
  } else {
    const cleanDni = dniVal.replace(/\D/g, '');
    if (cleanDni.length < 7 || cleanDni.length > 9) {
      setFieldError('alu-dni', 'err-alu-dni', 'El DNI debe contener entre 7 y 9 dígitos numéricos.');
      hasError = true;
      if (!firstErrEl) firstErrEl = $('#alu-dni');
    }
  }

  // 3. Validar Teléfono
  if (telVal) {
    if (/[a-zA-Z]/.test(telVal)) {
      setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede contener letras. Solo números (ej: 2657506957 o +54 9 2657...).');
      hasError = true;
      if (!firstErrEl) firstErrEl = $('#alu-telefono');
    } else {
      const digits = telVal.replace(/\D/g, '');
      if (digits.length < 7) {
        setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono debe contener al menos 7 dígitos numéricos.');
        hasError = true;
        if (!firstErrEl) firstErrEl = $('#alu-telefono');
      } else if (digits.length > 15) {
        setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede superar los 15 dígitos numéricos.');
        hasError = true;
        if (!firstErrEl) firstErrEl = $('#alu-telefono');
      }
    }
  }

  // 4. Validar Plan
  if (!planVal) {
    setFieldError('alu-plan-inp', 'err-alu-plan', 'Seleccioná un plan válido.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-plan-inp');
  }

  // 5. Validar Fechas
  if (!iniVal) {
    setFieldError('alu-inicio', 'err-alu-inicio', 'La fecha de inicio es requerida.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-inicio');
  }
  if (!vencVal) {
    setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento es requerida.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-venc');
  } else if (iniVal && vencVal < iniVal) {
    setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-venc');
  }

  if (hasError) {
    if (firstErrEl) firstErrEl.focus();
    return;
  }

  const data = {
    id: $('#alu-id').value,
    nombre: nombreVal,
    dni: dniVal.replace(/\D/g, ''),
    telefono: telVal,
    plan: planVal,
    actividades: actVal,
    fecha_inicio: iniVal,
    fecha_vencimiento: vencVal,
    estado: estVal,
    profesor_id: profVal
  };

  const r = await api('alumnos.save', data);
  if (r.ok) {
    showToast('Alumno guardado exitosamente');
    closeModal('modal-alu');
    await loadAlumnos();
    loadAlumnosOptions();
    await loadDashboard();
  } else {
    const errorMsg = r.msg || 'Error al guardar alumno';
    showToast(errorMsg, true);
    if (errorMsg.includes('DNI')) {
      setFieldError('alu-dni', 'err-alu-dni', errorMsg);
      $('#alu-dni').focus();
    } else if (errorMsg.includes('nombre')) {
      setFieldError('alu-nombre', 'err-alu-nombre', errorMsg);
      $('#alu-nombre').focus();
    }
  }
}

async function toggleSuspensionAlumno(aluId, aluNombre, nuevoEstado) {
  const isSusp = (nuevoEstado === 'pausado');
  const r = await api('alumnos.toggle_suspension', { id: aluId, estado: nuevoEstado });
  if (r.ok) {
    showToast(isSusp ? `⏸️ Alumno '${aluNombre || 'Socio'}' suspendido con éxito` : `🔓 Alumno '${aluNombre || 'Socio'}' reactivado con éxito`);
    await loadAlumnos();
    if (_currentFichaData && Number(_currentFichaData.alumno?.id) === Number(aluId)) {
      openAlumnoFicha(aluId);
    }
    if (typeof loadDashboard === 'function') loadDashboard();
  } else {
    showToast(r.msg || 'Error al modificar estado', true);
  }
}

async function delAlumno(id, nombre) {
  const ok = await systemConfirm({
    title: '🗑️ ¿Eliminar Alumno?',
    message: `¿Estás seguro de que deseas eliminar permanentemente al socio <b>${nombre}</b> y todos sus registros asociados?`,
    confirmText: 'Sí, Eliminar Definitivamente',
    cancelText: 'Cancelar',
    icon: '🗑️',
    isDanger: true
  });
  if (!ok) return;

  const r = await api('alumnos.delete', { id });
  if (r.ok) { 
    showToast(`🗑️ Alumno '${nombre}' eliminado con éxito`); 
    await loadAlumnos(); 
    if (typeof loadDashboard === 'function') loadDashboard(); 
  } else {
    showToast(r.msg || 'Error al eliminar alumno', true);
  }
}

async function desvincularAlumnoCoach(id, nombre) {
  const ok = await systemConfirm({
    title: '¿Dar de baja de tu lista a cargo?',
    message: `¿Estás seguro de que deseas desvincular a <b>${nombre}</b> de tu lista de alumnos a cargo?<br><br><small style="color:var(--t2)">ℹ️ <b>Importante:</b> El alumno <b>permanecerá registrado en el gimnasio para el dueño</b> con todo su historial, pero ya no figurará en tus alumnos a cargo ni en tus comisiones.</small>`,
    confirmText: 'Sí, Desvincular de mi lista',
    cancelText: 'Cancelar',
    icon: '🚫',
    isDanger: true
  });
  if (!ok) return;

  const r = await api('coach.desvincular_alumno', { alumno_id: id });
  if (r.ok) {
    showToast('Alumno dado de baja de tu lista. Permanece registrado para el dueño.');
    await loadAlumnos();
    if (typeof loadDashboard === 'function') loadDashboard();
  } else {
    showToast(r.msg || 'Error al desvincular alumno', true);
  }
}

/* ===== PROFESORES ===== */
let _profesCache = [];
let _debounceProfTimer;
let _profCurrentPage = 1;
let _profPageSize = 15;

function debounceLoadProfesores() {
  clearTimeout(_debounceProfTimer);
  _debounceProfTimer = setTimeout(loadProfesores, 250);
}

async function loadProfesores() {
  const q = $('#prof-filter-q')?.value?.trim() || '';
  const res = await api('profesores.list', { q }, 'GET');
  if (res.ok) {
    _profesCache = res.data || [];
    _profCurrentPage = 1;
    renderProfesoresTable();
  }
}

function onProfTipoRemChange() {
  const tipo = $('#prof-tipo-rem')?.value || 'sueldo_fijo';
  if ($('#prof-grp-sueldo')) $('#prof-grp-sueldo').style.display = (tipo === 'sueldo_fijo') ? 'block' : 'none';
  if ($('#prof-grp-pct')) $('#prof-grp-pct').style.display = (tipo === 'porcentaje') ? 'block' : 'none';
  if ($('#prof-grp-mto-alu')) $('#prof-grp-mto-alu').style.display = (tipo === 'monto_alumno') ? 'block' : 'none';
  if ($('#prof-grp-canon')) $('#prof-grp-canon').style.display = (tipo === 'canon_alquiler') ? 'block' : 'none';
}

function renderProfesoresTable() {
  const q = ($('#prof-filter-q')?.value || '').toLowerCase().trim();
  const estadoFiltro = $('#prof-filter-estado')?.value || '';

  const tb = $('#tbl-prof tbody');
  const mob = $('#profesores-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mob) mob.innerHTML = '';

  let profList = _profesCache || [];

  if (estadoFiltro === 'al_dia') profList = profList.filter(p => p.saldo_mes <= 0);
  if (estadoFiltro === 'deuda') profList = profList.filter(p => p.saldo_mes > 0);
  if (q) profList = profList.filter(p => p.nombre.toLowerCase().includes(q) || (p.telefono && p.telefono.includes(q)));

  const totalRecords = profList.length;
  const pageSize = Number(_profPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_profCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = profList.slice(startIdx, endIdx);

  if (!totalRecords) {
    tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--t-mut);padding:28px">No se encontraron profesores o coaches registrados.</td></tr>';
    if (mob) mob.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron coaches.</div>';
  } else {
    pageItems.forEach(p => {
      const telClean = (p.telefono || '').replace(/\D/g, '');
      const waLink = telClean ? `<a href="https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(p.nombre)}" target="_blank" style="color:var(--ok);text-decoration:none;margin-left:4px" title="WhatsApp">📱</a>` : '';

      const tipo = p.tipo_remuneracion || 'sueldo_fijo';
      let badgeEsquema = '';
      if (tipo === 'porcentaje') {
        badgeEsquema = `<span class="badge b-purple" style="font-weight:800">📈 ${p.porcentaje_comision}% Comisión</span>`;
      } else if (tipo === 'monto_alumno') {
        badgeEsquema = `<span class="badge b-info" style="font-weight:800">👥 $ ${fmtMoney(p.monto_por_alumno)} / Alumno</span>`;
      } else if (tipo === 'canon_alquiler') {
        badgeEsquema = `<span class="badge b-purple" style="font-weight:800">🏢 Canon $ ${fmtMoney(p.canon_mensual)} (Paga al Dueño)</span>`;
      } else {
        badgeEsquema = `<span class="badge b-ok" style="font-weight:800">💼 Sueldo $ ${fmtMoney(p.cuota_mensual)}</span>`;
      }

      const recAlus = parseFloat(p.recaudado_alumnos_mes || 0);
      const gananciaCalc = parseFloat(p.ganancia_calculada_mes || 0);
      const abonadoMes = parseFloat(p.abonado_mes || 0);
      const saldoPendiente = parseFloat(p.saldo_mes ?? Math.max(0, gananciaCalc - abonadoMes));
      const hasZeroEarnings = (gananciaCalc <= 0 && tipo !== 'canon_alquiler');
      const isAlDia = (saldoPendiente <= 0 && gananciaCalc > 0);

      let badgeEstado = '';
      if (tipo === 'canon_alquiler') {
        const saldoCanon = parseFloat(p.canon_saldo_mes || 0);
        badgeEstado = saldoCanon <= 0 
          ? '<span class="badge b-ok">✓ Canon al Día</span>' 
          : `<span class="badge b-warn">⏱ Canon Deuda $ ${fmtMoney(saldoCanon)}</span>`;
      } else if (hasZeroEarnings) {
        badgeEstado = '<span class="badge" style="background:rgba(255,255,255,0.06);color:var(--t2);border:1px solid var(--border)">⚪ Sin honorarios ($ 0)</span>';
      } else if (isAlDia) {
        badgeEstado = '<span class="badge b-ok">✓ Liquidado al 100%</span>';
      } else {
        badgeEstado = `<span class="badge b-warn">⏱ Saldo $ ${fmtMoney(saldoPendiente)}</span>`;
      }

      const isCoachActivo = Number(p.activo ?? 1) === 1;
      const badgeActivo = isCoachActivo 
        ? '<span class="badge b-ok" style="font-size:9.5px;font-weight:700">✅ Activo</span>' 
        : '<span class="badge b-bad" style="font-size:9.5px;font-weight:800;background:rgba(239,68,68,0.2);color:#f87171;border:1px solid rgba(239,68,68,0.4)">⛔ Suspendido</span>';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <b style="font-size:14px;color:var(--t1)">${escapeHtml(p.nombre)}</b>
            ${badgeActivo}
          </div>
          <div style="font-size:12px;color:var(--t2);margin-top:2px">${escapeHtml(p.telefono || '-')} ${waLink}</div>
        </td>
        <td>${badgeEsquema}</td>
        <td style="white-space:nowrap">
          <button class="btn btn-xs btn-primary" style="font-weight:700;padding:4px 10px;font-size:11.5px;box-shadow:0 2px 8px rgba(59,130,246,0.25)" onclick='openAssignCoachAlumnosModal(${p.id}, "${p.nombre.replace(/"/g, '&quot;')}")' title="Asignar o desasignar socios a este coach">
            👥 ${p.total_alumnos || 0} socio${p.total_alumnos == 1 ? '' : 's'} (${p.alumnos_pagaron_mes || 0} pagaron)
          </button>
        </td>
        <td style="font-weight:700;color:var(--ok);white-space:nowrap">$ ${fmtMoney(recAlus)}</td>
        <td style="font-weight:800;color:#c084fc;white-space:nowrap">${tipo === 'canon_alquiler' ? '<span style="color:#38bdf8">Canon a pagar</span>' : '$ ' + fmtMoney(gananciaCalc)}</td>
        <td style="color:#10b981;font-weight:700;white-space:nowrap">${tipo === 'canon_alquiler' ? '$ ' + fmtMoney(p.canon_abonado_mes || 0) : '$ ' + fmtMoney(abonadoMes)}</td>
        <td style="white-space:nowrap">${badgeEstado}</td>
        <td style="text-align:right;white-space:nowrap">
          <div style="display:inline-flex;flex-direction:column;gap:4px;align-items:stretch;min-width:155px">
            <div style="display:flex;gap:4px">
              <button class="btn btn-xs ${isAlDia || hasZeroEarnings ? 'btn-secondary' : 'btn-primary'}" style="flex:1;font-weight:700" title="Liquidar Honorarios al Coach" onclick="openPagoModal('profesor', ${p.id})">💵 Liquidar</button>
              <button class="btn btn-xs btn-purple" style="flex:1;font-weight:700;background:rgba(139,92,246,0.25);border:1px solid #a855f7;color:#c084fc" title="Ver Movimientos y Canon" onclick="openCoachMovimientosModal(${p.id})">💳 Movimientos</button>
            </div>
            <div style="display:flex;gap:4px">
              ${isCoachActivo ? `
                <button class="btn btn-xs btn-warn" style="flex:1;font-weight:700" title="Suspender cuenta del Coach" onclick="toggleSuspensionProfesor(${p.id}, '${(p.nombre || '').replace(/'/g, "\\'")}', 0)">⏸️ Suspender</button>
              ` : `
                <button class="btn btn-xs btn-success" style="flex:1;font-weight:700" title="Reactivar y habilitar cuenta del Coach" onclick="toggleSuspensionProfesor(${p.id}, '${(p.nombre || '').replace(/'/g, "\\'")}', 1)">🔓 Reactivar</button>
              `}
              <button class="btn btn-xs btn-secondary" style="flex:1" title="Editar Coach" onclick='openProfModal(${JSON.stringify(p)})'>✏️ Editar</button>
            </div>
          </div>
        </td>
      `;
      tb.appendChild(tr);
    });
  }

  renderGenericPagination({
    containerId: 'profesores-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'coaches',
    scrollTargetId: 'page-profesores',
    onPageChange: (p) => {
      _profCurrentPage = p;
      renderProfesoresTable();
    }
  });
}

function changeProfPageSize(sz) {
  _profPageSize = Number(sz) || 15;
  _profCurrentPage = 1;
  renderProfesoresTable();
}

function openProfModal(row = null) {
  const isEdit = !!row?.id;
  $('#prof-modal-title').textContent = isEdit ? `Editar Esquema de Pago: ${row.nombre}` : 'Registrar Coach / Profesor';
  $('#prof-id').value = row?.id || '';
  $('#prof-nombre').value = row?.nombre || '';
  $('#prof-telefono').value = row?.telefono || '';

  // Bloquear nombre y teléfono en modo edición para el dueño
  const inpNom = $('#prof-nombre');
  const inpTel = $('#prof-telefono');
  if (inpNom) {
    inpNom.readOnly = isEdit;
    inpNom.required = !isEdit;
    inpNom.style.backgroundColor = isEdit ? 'rgba(255, 255, 255, 0.04)' : '';
    inpNom.style.cursor = isEdit ? 'not-allowed' : '';
    inpNom.title = isEdit ? 'El nombre y teléfono del coach no se pueden modificar' : '';
  }
  if (inpTel) {
    inpTel.readOnly = isEdit;
    inpTel.required = !isEdit;
    inpTel.style.backgroundColor = isEdit ? 'rgba(255, 255, 255, 0.04)' : '';
    inpTel.style.cursor = isEdit ? 'not-allowed' : '';
    inpTel.title = isEdit ? 'El teléfono del coach no se puede modificar' : '';
  }

  const hint = $('#prof-datos-hint');
  if (hint) hint.style.display = isEdit ? 'block' : 'none';

  const tipo = row?.tipo_remuneracion || 'sueldo_fijo';
  $('#prof-tipo-rem').value = tipo;
  $('#prof-cuota').value = row?.cuota_mensual || 25000;
  $('#prof-pct').value = row?.porcentaje_comision || 40;
  $('#prof-mto-alu').value = row?.monto_por_alumno || 5000;
  $('#prof-canon').value = row?.canon_mensual || 20000;
  $('#prof-dia-canon').value = row?.dia_pago_canon || 10;
  $('#prof-obs').value = row?.observaciones || '';

  onProfTipoRemChange();
  openModal('modal-prof');
}

async function saveProfesor(e) {
  e.preventDefault();
  const id = $('#prof-id').value;
  const data = {
    id: id,
    nombre: $('#prof-nombre').value,
    telefono: $('#prof-telefono').value,
    tipo_remuneracion: $('#prof-tipo-rem').value,
    cuota_mensual: $('#prof-cuota').value,
    porcentaje_comision: $('#prof-pct').value,
    monto_por_alumno: $('#prof-mto-alu').value,
    canon_mensual: $('#prof-canon').value,
    dia_pago_canon: $('#prof-dia-canon').value,
    observaciones: $('#prof-obs').value
  };

  const r = await api('profesores.save', data);
  if (r.ok) {
    showToast(r.msg || 'Coach guardado correctamente');
    closeModal('modal-prof');
    await loadProfesores();
    loadProfesOptions();
    if (typeof loadDashboard === 'function') loadDashboard();
  } else {
    showToast(r.msg || 'Error al guardar coach', true);
  }
}

async function toggleSuspensionProfesor(profId, profNombre, nuevoActivo) {
  const isSusp = (Number(nuevoActivo) === 0);
  const r = await api('profesores.toggle_suspension', { id: profId, activo: nuevoActivo });
  if (r.ok) {
    showToast(isSusp ? `⏸️ Coach '${profNombre || 'Profesor'}' suspendido con éxito` : `🔓 Coach '${profNombre || 'Profesor'}' reactivado con éxito`);
    await loadProfesores();
    loadProfesOptions();
    if (typeof loadDashboard === 'function') loadDashboard();
  } else {
    showToast(r.msg || 'Error al modificar estado', true);
  }
}

async function delProfesor(id, nombre) {
  const ok = await systemConfirm({
    title: '¿Eliminar Coach?',
    message: `¿Estás seguro de que deseas eliminar al coach <b>${nombre}</b>? Sus alumnos asignados permanecerán en el sistema.`,
    confirmText: 'Sí, Eliminar',
    cancelText: 'Cancelar',
    icon: '🏋️‍♂️',
    isDanger: true
  });
  if (!ok) return;

  const r = await api('profesores.delete', { id });
  if (r.ok) {
    showToast('Coach eliminado correctamente');
    await loadProfesores();
    loadProfesOptions();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al eliminar coach', true);
  }
}

/* ===== ASIGNACIÓN MASIVA DE ALUMNOS A COACH ===== */
let _assignCoachId = null;
let _selectedCoachAluIds = new Set();
let _allGymAlumnosCache = [];

async function openAssignCoachAlumnosModal(profId, profNombre) {
  _assignCoachId = profId;
  _selectedCoachAluIds.clear();

  $('#assign-coach-title').innerHTML = `👥 Asignar Alumnos a <b style="color:#a78bfa">${profNombre}</b>`;
  $('#assign-coach-search').value = '';

  // Cargar todos los alumnos del gimnasio
  const r = await api('alumnos.list', { q: '' }, 'GET');
  if (!r.ok) return;
  _allGymAlumnosCache = r.data || [];

  // Preseleccionar los que ya pertenecen a este profesor
  _allGymAlumnosCache.forEach(a => {
    if (Number(a.profesor_id) === Number(profId)) {
      _selectedCoachAluIds.add(Number(a.id));
    }
  });

  renderAssignCoachAlumnosList();
  openModal('modal-assign-coach-alumnos');
}

function renderAssignCoachAlumnosList() {
  const listCont = $('#assign-coach-alumnos-list');
  if (!listCont) return;
  listCont.innerHTML = '';

  const q = ($('#assign-coach-search')?.value || '').toLowerCase().trim();
  
  // Ocultar completamente a los alumnos que ya pertenecen a otro coach
  // Mostrando SOLO los que ya tiene este coach O los que no tienen coach asignado
  const filtered = _allGymAlumnosCache.filter(a => {
    const isThisCoach = Number(a.profesor_id) === Number(_assignCoachId);
    const isUnassigned = !a.profesor_id;
    if (!isThisCoach && !isUnassigned) return false;

    return !q || (a.nombre && a.nombre.toLowerCase().includes(q)) || (a.dni && String(a.dni).includes(q));
  });

  if (!filtered.length) {
    listCont.innerHTML = `
      <div style="text-align:center;padding:32px 20px;color:var(--t-mut);font-size:13px;background:rgba(255,255,255,0.02);border:1px dashed var(--border);border-radius:12px">
        <div style="font-size:24px;margin-bottom:6px">👥</div>
        No hay más socios disponibles sin coach en este gimnasio (o no coinciden con la búsqueda).
      </div>
    `;
    return;
  }

  filtered.forEach(a => {
    const isSel = _selectedCoachAluIds.has(Number(a.id));

    const card = document.createElement('div');
    card.style.cssText = `padding:10px 14px;border-radius:10px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;background:${isSel ? 'rgba(59, 130, 246, 0.18)' : 'rgba(255, 255, 255, 0.03)'};border:1px solid ${isSel ? 'var(--pri)' : 'rgba(255, 255, 255, 0.06)'};transition:var(--tr)`;

    card.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px">
        <input type="checkbox" style="width:18px;height:18px;cursor:pointer;accent-color:#3b82f6" ${isSel ? 'checked' : ''} onclick="event.stopPropagation(); toggleAssignCoachAlu(${a.id})">
        <div>
          <b style="font-size:14px;color:var(--t1)">${a.nombre}</b>
          <div style="display:flex;gap:6px;align-items:center;margin-top:2px">
            <span style="font-size:11.5px;color:var(--t2)">${a.dni ? 'DNI: ' + a.dni + ' • ' : ''}Plan: ${a.plan}</span>
            <span class="badge ${isSel ? 'b-ok' : 'b-info'}" style="font-size:10px">${isSel ? '✓ Asignado a este coach' : 'Disponible (Sin Coach)'}</span>
          </div>
        </div>
      </div>
      <span class="badge ${isSel ? 'b-ok' : 'b-gray'}" style="font-size:11px">
        ${isSel ? '✓ Asignado' : '+ Asignar'}
      </span>
    `;

    card.onclick = () => toggleAssignCoachAlu(a.id);
    listCont.appendChild(card);
  });

  updateAssignCoachBadge();
}

function toggleAssignCoachAlu(aluId) {
  aluId = Number(aluId);
  if (_selectedCoachAluIds.has(aluId)) {
    _selectedCoachAluIds.delete(aluId);
  } else {
    _selectedCoachAluIds.add(aluId);
  }
  renderAssignCoachAlumnosList();
}

function updateAssignCoachBadge() {
  const count = _selectedCoachAluIds.size;
  const badge = $('#assign-coach-count-badge');
  if (badge) {
    badge.textContent = `${count} socio${count === 1 ? '' : 's'} a cargo`;
  }
}

async function submitAssignCoachAlumnos() {
  if (!_assignCoachId) return;

  const data = {
    profesor_id: _assignCoachId,
    alumno_ids: Array.from(_selectedCoachAluIds)
  };

  const r = await api('profesores.assign_alumnos', data);
  if (r.ok) {
    showToast(r.msg || 'Alumnos asignados correctamente');
    closeModal('modal-assign-coach-alumnos');
    await loadProfesores();
    if (typeof loadAlumnos === 'function') loadAlumnos();
    loadAlumnosOptions();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al asignar alumnos', true);
  }
}


/* ===== CONTROLADOR GENÉRICO DE PAGINACIÓN ===== */
function renderGenericPagination({
  containerId,
  infoId = null,
  btnsId = null,
  totalRecords = 0,
  currentPage = 1,
  pageSize = 15,
  itemLabel = 'elementos',
  scrollTargetId = null,
  onPageChange
}) {
  const containerEl = document.getElementById(containerId);
  if (!containerEl) return;

  const infoEl = infoId ? document.getElementById(infoId) : containerEl.querySelector('.pagination-info');
  const btnsEl = btnsId ? document.getElementById(btnsId) : containerEl.querySelector('.pagination-controls');

  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(currentPage, totalPages));
  const startIdx = totalRecords > 0 ? (validPage - 1) * pageSize : 0;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);

  if (infoEl) {
    if (totalRecords === 0) {
      infoEl.textContent = `0 ${itemLabel} encontrados`;
    } else {
      infoEl.textContent = `Mostrando ${startIdx + 1} - ${endIdx} de ${totalRecords} ${itemLabel}`;
    }
  }

  if (!btnsEl) return;
  btnsEl.innerHTML = '';

  if (totalPages <= 1) {
    containerEl.style.display = totalRecords > 0 ? 'flex' : 'none';
    return;
  }
  containerEl.style.display = 'flex';

  const handlePageClick = (p) => {
    onPageChange(p);
    if (scrollTargetId) {
      const el = document.getElementById(scrollTargetId);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  // Botón Anterior
  const btnPrev = document.createElement('button');
  btnPrev.type = 'button';
  btnPrev.className = 'page-btn';
  btnPrev.innerHTML = '‹ Anterior';
  btnPrev.disabled = validPage === 1;
  btnPrev.onclick = () => handlePageClick(validPage - 1);
  btnsEl.appendChild(btnPrev);

  // Páginas numeradas
  let startPage = Math.max(1, validPage - 2);
  let endPage = Math.min(totalPages, startPage + 4);
  if (endPage - startPage < 4) {
    startPage = Math.max(1, endPage - 4);
  }

  if (startPage > 1) {
    const btnFirst = document.createElement('button');
    btnFirst.type = 'button';
    btnFirst.className = 'page-btn';
    btnFirst.textContent = '1';
    btnFirst.onclick = () => handlePageClick(1);
    btnsEl.appendChild(btnFirst);

    if (startPage > 2) {
      const dots = document.createElement('span');
      dots.style.cssText = 'color:var(--t-mut);padding:0 4px;font-size:12px';
      dots.textContent = '...';
      btnsEl.appendChild(dots);
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `page-btn ${i === validPage ? 'active' : ''}`;
    btn.textContent = i;
    btn.onclick = () => handlePageClick(i);
    btnsEl.appendChild(btn);
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      const dots = document.createElement('span');
      dots.style.cssText = 'color:var(--t-mut);padding:0 4px;font-size:12px';
      dots.textContent = '...';
      btnsEl.appendChild(dots);
    }

    const btnLast = document.createElement('button');
    btnLast.type = 'button';
    btnLast.className = 'page-btn';
    btnLast.textContent = totalPages;
    btnLast.onclick = () => handlePageClick(totalPages);
    btnsEl.appendChild(btnLast);
  }

  // Botón Siguiente
  const btnNext = document.createElement('button');
  btnNext.type = 'button';
  btnNext.className = 'page-btn';
  btnNext.innerHTML = 'Siguiente ›';
  btnNext.disabled = validPage === totalPages;
  btnNext.onclick = () => handlePageClick(validPage + 1);
  btnsEl.appendChild(btnNext);
}

/* ===== PAGOS Y CAJA ===== */
let _debouncePagosTimer;
function debounceLoadPagos() {
  clearTimeout(_debouncePagosTimer);
  _debouncePagosTimer = setTimeout(loadPagos, 250);
}

let _pagosCache = [];
let _pagosCurrentPage = 1;
let _pagosPageSize = 15;

function setPagoMesFilter(mes) {
  if ($('#pagos-filter-mes')) $('#pagos-filter-mes').value = mes;
  syncPagoMesQuickButtons(mes);
  loadPagos();
}

function onPagoMesSelectChange(mes) {
  syncPagoMesQuickButtons(mes);
  loadPagos();
}

function syncPagoMesQuickButtons(mes) {
  $$('.btn-pago-mes').forEach(btn => {
    const fnStr = btn.getAttribute('onclick') || '';
    const isAct = (mes === '' && fnStr.includes("('')")) || (mes !== '' && fnStr.includes(`('${mes}')`));
    btn.classList.toggle('btn-primary', isAct);
    btn.classList.toggle('active', isAct);
    btn.classList.toggle('btn-secondary', !isAct);
  });
}

function resetPagosFiltros() {
  if ($('#pagos-filter-q')) $('#pagos-filter-q').value = '';
  if ($('#pagos-filter-tipo')) $('#pagos-filter-tipo').value = '';
  if ($('#pagos-filter-medio')) $('#pagos-filter-medio').value = '';
  if ($('#pagos-filter-mes')) $('#pagos-filter-mes').value = '';
  syncPagoMesQuickButtons('');
  loadPagos();
}

async function loadPagos() {
  const q = $('#pagos-filter-q')?.value?.trim() || '';
  const tipo = $('#pagos-filter-tipo')?.value || '';
  const medio = $('#pagos-filter-medio')?.value || '';
  const mes = $('#pagos-filter-mes')?.value || '';

  const { ok, data } = await api('pagos.list', { q, tipo, medio, mes }, 'GET');
  if (!ok) return;

  _pagosCache = data || [];
  _pagosCurrentPage = 1;
  renderPagosPage();
}

function renderPagosPage() {
  const tb = $('#tbl-pagos tbody') || $('#tbl-coach-pagos tbody');
  const mobCont = $('#pagos-cards');
  if (!tb) return;

  const totalRecords = _pagosCache.length;
  const pageSize = Number(_pagosPageSize) || 15;
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  if (_pagosCurrentPage > totalPages) _pagosCurrentPage = totalPages;
  if (_pagosCurrentPage < 1) _pagosCurrentPage = 1;

  const startIdx = (_pagosCurrentPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = _pagosCache.slice(startIdx, endIdx);

  tb.innerHTML = '';
  if (mobCont) mobCont.innerHTML = '';

  // Calcular KPIs del total filtrado
  let totalIngresos = 0;
  let countIngresos = 0;
  let totalEgresos = 0;
  let countEgresos = 0;

  _pagosCache.forEach(p => {
    const monto = parseFloat(p.monto || 0);
    const isAlu = p.tipo === 'alumno';
    const isCanon = p.tipo === 'coach_a_dueno';
    if (isAlu || isCanon) {
      totalIngresos += monto;
      countIngresos++;
    } else {
      totalEgresos += monto;
      countEgresos++;
    }
  });

  const neto = totalIngresos - totalEgresos;

  if ($('#kpi-pago-ingresos')) $('#kpi-pago-ingresos').textContent = `$ ${fmtMoney(totalIngresos)}`;
  if ($('#kpi-pago-ingresos-sub')) $('#kpi-pago-ingresos-sub').textContent = `${countIngresos} cobro${countIngresos === 1 ? '' : 's'}`;
  if ($('#kpi-pago-egresos')) $('#kpi-pago-egresos').textContent = `$ ${fmtMoney(totalEgresos)}`;
  if ($('#kpi-pago-egresos-sub')) $('#kpi-pago-egresos-sub').textContent = `${countEgresos} liquidaci${countEgresos === 1 ? 'ón' : 'ones'}`;
  if ($('#kpi-pago-neto')) {
    $('#kpi-pago-neto').textContent = `$ ${fmtMoney(neto)}`;
    $('#kpi-pago-neto').style.color = neto >= 0 ? '#60a5fa' : '#f87171';
  }
  if ($('#kpi-pago-total-movs')) $('#kpi-pago-total-movs').textContent = totalRecords;

  if (!totalRecords) {
    tb.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--t-mut);padding:32px">No se encontraron movimientos con los filtros aplicados.</td></tr>';
    if (mobCont) {
      mobCont.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron movimientos.</div>';
    }
  } else {
    pageItems.forEach(p => {
      const isAlu = p.tipo === 'alumno';
      const isCanon = p.tipo === 'coach_a_dueno';
      const titular = isAlu ? (p.alumno || 'Alumno') : (p.profesor || 'Coach');

      let badgeTipo = '<span class="badge b-info" style="font-weight:800">👤 ALUMNO</span>';
      let montoColor = 'var(--ok)';
      let signo = '+';
      if (isCanon) {
        badgeTipo = '<span class="badge b-ok" style="font-weight:800">🏢 CANON COACH</span>';
      } else if (!isAlu) {
        badgeTipo = '<span class="badge b-purple" style="font-weight:800">💸 LIQ. COACH</span>';
        montoColor = '#c084fc';
        signo = '-';
      }

      let badgeMedio = 'b-ok';
      if (p.medio_pago === 'transferencia') badgeMedio = 'b-info';
      else if (p.medio_pago === 'debito' || p.medio_pago === 'credito') badgeMedio = 'b-warn';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b style="color:var(--t1)">${fmtDate(p.fecha_pago)}</b></td>
        <td>${badgeTipo}</td>
        <td><strong style="color:var(--t1)">${escapeHtml(titular)}</strong></td>
        <td><span class="badge ${isCanon ? 'b-ok' : (isAlu ? 'b-info' : 'b-purple')}">${escapeHtml(p.concepto || p.plan || (isAlu ? 'Cuota' : 'Pago'))}</span></td>
        <td><span class="badge ${badgeMedio}">${p.medio_pago ? p.medio_pago.toUpperCase() : 'EFECTIVO'}</span></td>
        <td style="text-align:right;font-weight:800;color:${montoColor};font-size:14px">${signo} $ ${fmtMoney(p.monto)}</td>
        <td style="text-align:right;color:var(--t-mut);font-size:12px">${escapeHtml(p.observaciones || '-')}</td>
      `;
      tb.appendChild(tr);

      // Mobile Card
      if (mobCont) {
        const mCard = document.createElement('div');
        mCard.className = 'saas-sub-card-mobile';
        mCard.style.cssText = 'background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px';
        mCard.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-size:12px;color:var(--t-mut)">📅 ${fmtDate(p.fecha_pago)}</span>
            ${badgeTipo}
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <b style="font-size:15px;color:var(--t1)">${escapeHtml(titular)}</b>
            <span style="font-size:16px;font-weight:800;color:${montoColor}">${signo} $ ${fmtMoney(p.monto)}</span>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <span class="badge ${isCanon ? 'b-ok' : (isAlu ? 'b-info' : 'b-purple')}" style="font-size:10px">${escapeHtml(p.concepto || p.plan || 'Pago')}</span>
            <span class="badge ${badgeMedio}" style="font-size:10px">${p.medio_pago ? p.medio_pago.toUpperCase() : 'EFECTIVO'}</span>
            ${p.observaciones ? `<span style="font-size:11px;color:var(--t-mut)">• ${escapeHtml(p.observaciones)}</span>` : ''}
          </div>
        `;
        mobCont.appendChild(mCard);
      }
    });
  }

  // Renderizar Barra de Paginación
  renderPagosPagination(totalRecords, totalPages, startIdx, endIdx);
}

function renderPagosPagination(totalRecords, totalPages, startIdx, endIdx) {
  const infoEl = $('#pagos-pagination-info');
  const btnsEl = $('#pagos-pagination-btns');
  const barEl = $('#pagos-pagination-bar');
  if (!barEl) return;

  if (totalRecords === 0) {
    if (infoEl) infoEl.textContent = '0 movimientos encontrados';
    if (btnsEl) btnsEl.innerHTML = '';
    return;
  }

  if (infoEl) {
    infoEl.textContent = `Mostrando ${startIdx + 1} - ${endIdx} de ${totalRecords} movimientos`;
  }

  if (!btnsEl) return;
  btnsEl.innerHTML = '';

  if (totalPages <= 1) {
    return;
  }

  // Botón Anterior
  const btnPrev = document.createElement('button');
  btnPrev.type = 'button';
  btnPrev.className = 'page-btn';
  btnPrev.innerHTML = '‹ Anterior';
  btnPrev.disabled = _pagosCurrentPage === 1;
  btnPrev.onclick = () => goToPagosPage(_pagosCurrentPage - 1);
  btnsEl.appendChild(btnPrev);

  // Páginas numeradas
  let startPage = Math.max(1, _pagosCurrentPage - 2);
  let endPage = Math.min(totalPages, startPage + 4);
  if (endPage - startPage < 4) {
    startPage = Math.max(1, endPage - 4);
  }

  if (startPage > 1) {
    const btnFirst = document.createElement('button');
    btnFirst.type = 'button';
    btnFirst.className = 'page-btn';
    btnFirst.textContent = '1';
    btnFirst.onclick = () => goToPagosPage(1);
    btnsEl.appendChild(btnFirst);

    if (startPage > 2) {
      const dots = document.createElement('span');
      dots.style.cssText = 'color:var(--t-mut);padding:0 4px;font-size:12px';
      dots.textContent = '...';
      btnsEl.appendChild(dots);
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `page-btn ${i === _pagosCurrentPage ? 'active' : ''}`;
    btn.textContent = i;
    btn.onclick = () => goToPagosPage(i);
    btnsEl.appendChild(btn);
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      const dots = document.createElement('span');
      dots.style.cssText = 'color:var(--t-mut);padding:0 4px;font-size:12px';
      dots.textContent = '...';
      btnsEl.appendChild(dots);
    }

    const btnLast = document.createElement('button');
    btnLast.type = 'button';
    btnLast.className = 'page-btn';
    btnLast.textContent = totalPages;
    btnLast.onclick = () => goToPagosPage(totalPages);
    btnsEl.appendChild(btnLast);
  }

  // Botón Siguiente
  const btnNext = document.createElement('button');
  btnNext.type = 'button';
  btnNext.className = 'page-btn';
  btnNext.innerHTML = 'Siguiente ›';
  btnNext.disabled = _pagosCurrentPage === totalPages;
  btnNext.onclick = () => goToPagosPage(_pagosCurrentPage + 1);
  btnsEl.appendChild(btnNext);
}

function goToPagosPage(page) {
  _pagosCurrentPage = page;
  renderPagosPage();
  const pagePagos = $('#page-pagos');
  if (pagePagos) {
    pagePagos.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function changePagosPageSize(size) {
  _pagosPageSize = Number(size) || 15;
  _pagosCurrentPage = 1;
  renderPagosPage();
}

/* ===== BUSCADORES DINÁMICOS Y DROPDOWNS EN TIEMPO REAL PARA MODAL DE PAGO ===== */

function filterPagoAlumnos(q, showAllOnEmpty = false) {
  const dropdown = $('#pago-alumno-dropdown');
  if (!dropdown) return;
  const list = _alumnosCache || [];
  const query = (q || '').trim().toLowerCase();

  if (!query && !showAllOnEmpty) {
    dropdown.style.display = 'none';
    return;
  }

  const filtered = !query ? list.slice(0, 20) : list.filter(a => {
    const nom = (a.nombre || '').toLowerCase();
    const dni = (a.dni || '').toLowerCase();
    const tel = (a.telefono || '').toLowerCase();
    const pl = (a.plan || '').toLowerCase();
    return nom.includes(query) || dni.includes(query) || tel.includes(query) || pl.includes(query);
  });

  if (!filtered.length) {
    dropdown.innerHTML = `<div style="padding:14px;text-align:center;color:var(--t2);font-size:13px">No se encontraron alumnos coincidentes con "${escapeHtml(q)}"</div>`;
    dropdown.style.display = 'block';
    return;
  }

  let html = '';
  filtered.forEach(a => {
    const cuota = parseFloat(a.cuota_mes || 0);
    const abonado = parseFloat(a.abonado_mes || 0);
    const saldo = Math.max(0, cuota - abonado);
    const diasRest = a.dias_restantes !== null ? Number(a.dias_restantes) : null;
    const isVencido = a.estado === 'vencido' || (diasRest !== null && diasRest <= 0);
    const isProximo = diasRest !== null && diasRest >= 0 && diasRest <= 5;
    
    let badgeEstado = '';
    if (isVencido) {
      badgeEstado = '<span class="badge b-bad" style="font-size:10px;padding:2px 6px">🔴 Vencido</span>';
    } else if (isProximo) {
      badgeEstado = `<span class="badge b-warn" style="font-size:10px;padding:2px 6px">⚠️ Vence en ${diasRest}d</span>`;
    } else if (saldo > 0) {
      badgeEstado = `<span class="badge b-warn" style="font-size:10px;padding:2px 6px">Debe $ ${fmtMoney(saldo)}</span>`;
    } else {
      badgeEstado = '<span class="badge b-ok" style="font-size:10px;padding:2px 6px">🟢 Al día</span>';
    }

    html += `
      <div class="search-dropdown-item" onclick="selectPagoAlumno(${a.id})" style="padding:11px 14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.06);transition:background 0.15s ease">
        <div>
          <div style="font-weight:700;color:var(--t1);font-size:13.5px">${escapeHtml(a.nombre)}</div>
          <div style="font-size:11.5px;color:var(--t2);margin-top:2px">
            ${a.dni ? 'DNI: ' + escapeHtml(a.dni) + ' • ' : ''}${a.telefono ? 'Tel: ' + escapeHtml(a.telefono) + ' • ' : ''}Plan ${(a.plan || '3x').toUpperCase()} ($ ${fmtMoney(cuota)})
          </div>
        </div>
        <div style="text-align:right">
          ${badgeEstado}
        </div>
      </div>
    `;
  });

  dropdown.innerHTML = html;
  dropdown.style.display = 'block';
}

function selectPagoAlumno(id) {
  const alu = (_alumnosCache || []).find(a => String(a.id) === String(id));
  if (!alu) return;

  const sel = $('#pago-alumno');
  if (sel) sel.value = String(alu.id);

  const inp = $('#pago-alumno-search');
  if (inp) inp.value = `${alu.nombre}${alu.dni ? ' (DNI: ' + alu.dni + ')' : ''}`;

  const btnClear = $('#btn-clear-alumno-search');
  if (btnClear) btnClear.style.display = 'block';

  const tag = $('#pago-alumno-selected-tag');
  if (tag) tag.style.display = 'inline';

  const dropdown = $('#pago-alumno-dropdown');
  if (dropdown) dropdown.style.display = 'none';

  onPagoAlumnoSelect();
}

function clearPagoAlumnoSelect() {
  const sel = $('#pago-alumno');
  if (sel) sel.value = '';

  const inp = $('#pago-alumno-search');
  if (inp) {
    inp.value = '';
    inp.focus();
  }

  const btnClear = $('#btn-clear-alumno-search');
  if (btnClear) btnClear.style.display = 'none';

  const tag = $('#pago-alumno-selected-tag');
  if (tag) tag.style.display = 'none';

  const dropdown = $('#pago-alumno-dropdown');
  if (dropdown) dropdown.style.display = 'none';

  onPagoAlumnoSelect();
}

function filterPagoProfes(q, showAllOnEmpty = false) {
  const dropdown = $('#pago-profesor-dropdown');
  if (!dropdown) return;
  const list = _profesCache || [];
  const query = (q || '').trim().toLowerCase();

  if (!query && !showAllOnEmpty) {
    dropdown.style.display = 'none';
    return;
  }

  const filtered = !query ? list : list.filter(p => {
    const nom = (p.nombre || '').toLowerCase();
    const tel = (p.telefono || '').toLowerCase();
    return nom.includes(query) || tel.includes(query);
  });

  if (!filtered.length) {
    dropdown.innerHTML = `<div style="padding:14px;text-align:center;color:var(--t2);font-size:13px">No se encontraron coaches coincidentes con "${escapeHtml(q)}"</div>`;
    dropdown.style.display = 'block';
    return;
  }

  let html = '';
  filtered.forEach(p => {
    const tipo = p.tipo_remuneracion || 'sueldo_fijo';
    let esquemaTxt = '';
    if (tipo === 'porcentaje') {
      esquemaTxt = `${p.porcentaje_comision}% Comisión`;
    } else if (tipo === 'monto_alumno') {
      esquemaTxt = `$ ${fmtMoney(p.monto_por_alumno)} / Alumno`;
    } else if (tipo === 'canon_alquiler') {
      esquemaTxt = `Canon $ ${fmtMoney(p.canon_mensual)}`;
    } else {
      esquemaTxt = `Sueldo $ ${fmtMoney(p.cuota_mensual)}`;
    }

    const gananciaCalc = parseFloat(p.ganancia_calculada_mes || 0);
    const abonadoMes = parseFloat(p.abonado_mes || 0);
    const saldoPendiente = parseFloat(p.saldo_mes ?? Math.max(0, gananciaCalc - abonadoMes));

    html += `
      <div class="search-dropdown-item" onclick="selectPagoProfesor(${p.id})" style="padding:11px 14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.06);transition:background 0.15s ease">
        <div>
          <div style="font-weight:700;color:var(--t1);font-size:13.5px">🏋️ ${escapeHtml(p.nombre)}</div>
          <div style="font-size:11.5px;color:var(--t2);margin-top:2px">
            ${p.telefono ? 'Tel: ' + escapeHtml(p.telefono) + ' • ' : ''}${esquemaTxt}
          </div>
        </div>
        <div style="text-align:right">
          ${saldoPendiente > 0 
            ? `<span class="badge b-warn" style="font-size:10px;padding:2px 6px">Pendiente $ ${fmtMoney(saldoPendiente)}</span>`
            : (gananciaCalc > 0 ? '<span class="badge b-ok" style="font-size:10px;padding:2px 6px">✓ Liquidado</span>' : '<span class="badge" style="font-size:10px;padding:2px 6px;background:rgba(255,255,255,0.06);color:var(--t2)">$ 0 Ganancia</span>')}
        </div>
      </div>
    `;
  });

  dropdown.innerHTML = html;
  dropdown.style.display = 'block';
}

function selectPagoProfesor(id) {
  const prof = (_profesCache || []).find(p => String(p.id) === String(id));
  if (!prof) return;

  const sel = $('#pago-profesor');
  if (sel) sel.value = String(prof.id);

  const inp = $('#pago-profesor-search');
  if (inp) inp.value = prof.nombre;

  const btnClear = $('#btn-clear-prof-search');
  if (btnClear) btnClear.style.display = 'block';

  const tag = $('#pago-profesor-selected-tag');
  if (tag) tag.style.display = 'inline';

  const dropdown = $('#pago-profesor-dropdown');
  if (dropdown) dropdown.style.display = 'none';

  onPagoProfesorSelect();
}

function clearPagoProfSelect() {
  const sel = $('#pago-profesor');
  if (sel) sel.value = '';

  const inp = $('#pago-profesor-search');
  if (inp) {
    inp.value = '';
    inp.focus();
  }

  const btnClear = $('#btn-clear-prof-search');
  if (btnClear) btnClear.style.display = 'none';

  const tag = $('#pago-profesor-selected-tag');
  if (tag) tag.style.display = 'none';

  const dropdown = $('#pago-profesor-dropdown');
  if (dropdown) dropdown.style.display = 'none';

  onPagoProfesorSelect();
}

// Cerrar dropdowns de búsqueda al clickear fuera
document.addEventListener('click', (e) => {
  const grpAlu = $('#group-pago-alumno');
  const grpProf = $('#group-pago-profesor');
  if (grpAlu && !grpAlu.contains(e.target)) {
    const d = $('#pago-alumno-dropdown');
    if (d) d.style.display = 'none';
  }
  if (grpProf && !grpProf.contains(e.target)) {
    const d = $('#pago-profesor-dropdown');
    if (d) d.style.display = 'none';
  }
});

async function openPagoModal(tipo = 'alumno', id = null) {
  if (!_alumnosCache || !_alumnosCache.length) {
    await loadAlumnosOptions();
  }
  if (!_profesCache || !_profesCache.length) {
    await loadProfesOptions();
  }

  if ($('#pago-fecha')) $('#pago-fecha').value = currentDate();
  if ($('#pago-tipo')) $('#pago-tipo').value = tipo;
  onPagoTipoChange();
  
  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');
  if (btnSubmit) btnSubmit.disabled = false;

  if (tipo === 'alumno') {
    if (id) {
      selectPagoAlumno(id);
    } else {
      clearPagoAlumnoSelect();
    }
  } else if (tipo === 'profesor') {
    if (id) {
      selectPagoProfesor(id);
    } else {
      clearPagoProfSelect();
    }
  }

  openModal('modal-pago');

  setTimeout(() => {
    if (tipo === 'alumno' && !id) {
      $('#pago-alumno-search')?.focus();
    } else if (tipo === 'profesor' && !id) {
      $('#pago-profesor-search')?.focus();
    }
  }, 150);
}

function onPagoTipoChange() {
  const tipo = $('#pago-tipo').value;
  const isAlu = tipo === 'alumno';
  if ($('#group-pago-alumno')) $('#group-pago-alumno').style.display = isAlu ? 'block' : 'none';
  if ($('#group-pago-profesor')) $('#group-pago-profesor').style.display = isAlu ? 'none' : 'block';
  
  if ($('#modal-pago-title')) {
    $('#modal-pago-title').textContent = isAlu ? '💵 Cobrar Cuota a Alumno / Socio' : '💵 Liquidar / Registrar Pago a Coach';
  }
  if ($('#lbl-pago-monto')) {
    $('#lbl-pago-monto').textContent = isAlu ? 'Monto a Cobrar ($) *' : 'Monto de Honorario a Liquidar ($) *';
  }
  if ($('#lbl-pago-summary-cuota')) {
    $('#lbl-pago-summary-cuota').textContent = isAlu ? 'Cuota Pactada:' : 'Honorario Acordado:';
  }
  if ($('#lbl-pago-summary-saldo')) {
    $('#lbl-pago-summary-saldo').textContent = isAlu ? 'Saldo Exacto a Cobrar:' : 'Saldo a Liquidar:';
  }

  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');
  if (btnSubmit) {
    btnSubmit.textContent = isAlu ? 'Confirmar Cobro de Cuota' : 'Confirmar Pago al Coach';
    btnSubmit.disabled = false;
  }
  
  if (isAlu) {
    onPagoAlumnoSelect();
    setTimeout(() => $('#pago-alumno-search')?.focus(), 50);
  } else {
    onPagoProfesorSelect();
    setTimeout(() => $('#pago-profesor-search')?.focus(), 50);
  }
}

function onPagoAlumnoSelect() {
  const id = $('#pago-alumno')?.value;
  const sBox = $('#pago-summary-box');
  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');

  if (!id) {
    if (sBox) sBox.style.display = 'none';
    $('#pago-monto').value = '';
    if ($('#pago-monto-hint')) $('#pago-monto-hint').textContent = 'Ingresá o confirmá el importe pactado a cobrar.';
    if (btnSubmit) btnSubmit.disabled = false;
    return;
  }

  const alu = (_alumnosCache || []).find(a => String(a.id) === String(id));
  if (alu) {
    const cuota = parseFloat(alu.cuota_mes || 0);
    const abonado = parseFloat(alu.abonado_mes || 0);
    const saldo = Math.max(0, cuota - abonado);
    const diasRest = alu.dias_restantes !== null ? Number(alu.dias_restantes) : null;
    const isVencido = alu.estado === 'vencido' || (diasRest !== null && diasRest <= 0);
    const isProximo = diasRest !== null && diasRest >= 0 && diasRest <= 5;
    const isRenewing = isVencido || isProximo || (saldo <= 0);

    const montoACobrar = (saldo > 0) ? saldo : cuota;

    if (sBox) {
      sBox.style.display = 'block';
      $('#pago-summary-plan').textContent = `👤 Socio: ${alu.nombre} • Plan ${(alu.plan || '3x').toUpperCase()}`;
      $('#pago-summary-cuota').textContent = `$ ${fmtMoney(cuota)}`;
      $('#pago-summary-abonado').textContent = `$ ${fmtMoney(abonado)}`;
      $('#pago-summary-saldo').textContent = `$ ${fmtMoney(saldo)}`;

      const badge = $('#pago-summary-badge');
      if (badge) {
        if (isVencido) {
          badge.className = 'badge b-bad';
          badge.textContent = '🔴 CUOTA VENCIDA / A RENOVAR';
        } else if (isProximo) {
          badge.className = 'badge b-warn pulse';
          badge.textContent = `⚠️ PRÓXIMO A VENCER (${diasRest} DÍAS)`;
        } else if (saldo > 0) {
          badge.className = 'badge b-warn';
          badge.textContent = `⚠️ SALDO PENDIENTE ($ ${fmtMoney(saldo)})`;
        } else {
          badge.className = 'badge b-ok';
          badge.textContent = '🟢 AL DÍA (RENOVACIÓN DE CUOTA)';
        }
      }
    }

    $('#pago-monto').value = montoACobrar.toFixed(2);
    $('#pago-monto').max = montoACobrar.toFixed(2);
    $('#pago-monto').min = '0.01';
    if ($('#pago-monto-hint')) {
      if (isVencido) {
        $('#pago-monto-hint').innerHTML = `<span style="color:#ef4444;font-weight:700">🔒 Cobro de $ ${fmtMoney(montoACobrar)} para renovar membresía por 30 días (máx: $ ${fmtMoney(montoACobrar)}).</span>`;
      } else if (isProximo) {
        $('#pago-monto-hint').innerHTML = `<span style="color:#f59e0b;font-weight:700">🔒 Renovación anticipada de $ ${fmtMoney(montoACobrar)} (máx: $ ${fmtMoney(montoACobrar)}).</span>`;
      } else {
        $('#pago-monto-hint').innerHTML = `<span style="color:#38bdf8;font-weight:700">🔒 Importe fijado en $ ${fmtMoney(montoACobrar)} (Plan ${(alu.plan || '3x').toUpperCase()}) • Podés cobrar hasta $ ${fmtMoney(montoACobrar)}.</span>`;
      }
    }
    if (btnSubmit) btnSubmit.disabled = false;
    validatePagoMontoInput();
  }
}

function onPagoProfesorSelect() {
  const id = $('#pago-profesor')?.value;
  const sBox = $('#pago-summary-box');
  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');

  if (!id) {
    if (sBox) sBox.style.display = 'none';
    $('#pago-monto').value = '';
    if ($('#pago-monto-hint')) $('#pago-monto-hint').textContent = 'Solo se permite liquidar el honorario mensual pactado exacto.';
    if (btnSubmit) btnSubmit.disabled = false;
    return;
  }

  const prof = (_profesCache || []).find(p => String(p.id) === String(id));
  if (prof) {
    const tipo = prof.tipo_remuneracion || 'sueldo_fijo';
    const cuota = parseFloat(prof.ganancia_calculada_mes ?? (tipo === 'sueldo_fijo' ? prof.cuota_mensual : 0));
    const pagado = parseFloat(prof.abonado_mes || 0);
    const saldo = parseFloat(prof.saldo_mes ?? Math.max(0, cuota - pagado));
    const hasZeroEarnings = (cuota <= 0);
    const isAlDia = (saldo <= 0 && cuota > 0);

    let planDescr = `🏋️ Coach: ${prof.nombre}`;
    if (tipo === 'porcentaje') {
      planDescr += ` (${prof.porcentaje_comision}% Comisión)`;
    } else if (tipo === 'monto_alumno') {
      planDescr += ` ($ ${fmtMoney(prof.monto_por_alumno)} / Alumno)`;
    } else {
      planDescr += ` (Sueldo Fijo)`;
    }

    if (sBox) {
      sBox.style.display = 'block';
      $('#pago-summary-plan').textContent = planDescr;
      $('#pago-summary-cuota').textContent = `$ ${fmtMoney(cuota)}`;
      $('#pago-summary-abonado').textContent = `$ ${fmtMoney(pagado)}`;
      $('#pago-summary-saldo').textContent = `$ ${fmtMoney(saldo)}`;

      const badge = $('#pago-summary-badge');
      if (badge) {
        if (hasZeroEarnings) {
          badge.className = 'badge b-bad';
          badge.textContent = '⚪ SIN HONORARIOS ACUMULADOS ($ 0)';
        } else if (isAlDia) {
          badge.className = 'badge b-ok';
          badge.textContent = '🟢 HONORARIOS LIQUIDADOS (AL DÍA)';
        } else {
          badge.className = 'badge b-warn';
          badge.textContent = `🟠 PAGO PENDIENTE ($ ${fmtMoney(saldo)})`;
        }
      }
    }

    if (hasZeroEarnings) {
      $('#pago-monto').value = '0.00';
      $('#pago-monto').max = '0.00';
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = '<span style="color:#ef4444;font-weight:700">🚫 Este coach aún tiene $ 0 de honorarios acumulados (sus alumnos asignados no registran pagos en este período).</span>';
      }
      if (btnSubmit) btnSubmit.disabled = true;
    } else if (isAlDia) {
      $('#pago-monto').value = '0.00';
      $('#pago-monto').max = '0.00';
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = '<span style="color:#10b981;font-weight:700">✅ Este coach ya tiene sus honorarios totalmente liquidados este mes.</span>';
      }
      if (btnSubmit) btnSubmit.disabled = true;
    } else {
      const montoFijado = saldo > 0 ? saldo : cuota;
      $('#pago-monto').value = montoFijado.toFixed(2);
      $('#pago-monto').max = saldo.toFixed(2);
      $('#pago-monto').min = '0.01';
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = `<span style="color:#a855f7;font-weight:700">💡 Podés liquidar el total de $ ${fmtMoney(saldo)} o ingresar un pago parcial menor (máx: $ ${fmtMoney(saldo)}).</span>`;
      }
      if (btnSubmit) btnSubmit.disabled = false;
    }
    validatePagoMontoInput();
  }
}

function validatePagoMontoInput() {
  const inp = $('#pago-monto');
  const errBox = $('#pago-monto-error');
  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');
  if (!inp) return true;

  const tipo = $('#pago-tipo')?.value || 'alumno';
  const val = parseFloat(inp.value);

  if (isNaN(val) || val <= 0) {
    if (errBox) {
      errBox.textContent = '🚫 El importe a registrar debe ser mayor a $ 0.';
      errBox.style.display = 'block';
    }
    inp.style.border = '1.5px solid #ef4444';
    inp.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.35)';
    if (btnSubmit) btnSubmit.disabled = true;
    return false;
  }

  if (tipo === 'alumno') {
    const aluId = $('#pago-alumno')?.value;
    const alu = (_alumnosCache || []).find(a => String(a.id) === String(aluId));
    if (alu) {
      const planKey = $('#pago-plan')?.value || alu.plan || '3x';
      const cuota = parseFloat(alu.cuota_mes || 0);
      const abonado = parseFloat(alu.abonado_mes || 0);
      const saldo = Math.max(0, cuota - abonado);
      const maxPermitido = (saldo > 0) ? saldo : cuota;

      if (maxPermitido > 0 && val > maxPermitido + 0.0001) {
        if (errBox) {
          errBox.innerHTML = `🚫 El monto ingresado ($ ${fmtMoney(val)}) <b>supera el valor máximo de la cuota</b> de $ ${fmtMoney(maxPermitido)} (Plan ${planKey.toUpperCase()}). Podés cobrar hasta $ ${fmtMoney(maxPermitido)}.`;
          errBox.style.display = 'block';
        }
        inp.style.border = '1.5px solid #ef4444';
        inp.style.boxShadow = '0 0 12px rgba(239, 68, 68, 0.45)';
        if (btnSubmit) btnSubmit.disabled = true;
        return false;
      }
    }
  }

  if (tipo === 'profesor') {
    const profId = $('#pago-profesor')?.value;
    const prof = (_profesCache || []).find(p => String(p.id) === String(profId));
    if (prof) {
      const tipoRem = prof.tipo_remuneracion || 'sueldo_fijo';
      const cuota = parseFloat(prof.ganancia_calculada_mes ?? (tipoRem === 'sueldo_fijo' ? prof.cuota_mensual : 0));
      const pagado = parseFloat(prof.abonado_mes || 0);
      const saldo = parseFloat(prof.saldo_mes ?? Math.max(0, cuota - pagado));

      if (cuota <= 0) {
        if (errBox) {
          errBox.textContent = '🚫 Este coach no registra honorarios acumulados ($ 0) este mes.';
          errBox.style.display = 'block';
        }
        inp.style.border = '1.5px solid #ef4444';
        inp.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.35)';
        if (btnSubmit) btnSubmit.disabled = true;
        return false;
      }

      if (val > saldo + 0.0001) {
        if (errBox) {
          errBox.innerHTML = `🚫 El monto ingresado ($ ${fmtMoney(val)}) <b>supera el saldo máximo a liquidar</b> de $ ${fmtMoney(saldo)}. Podés liquidar hasta $ ${fmtMoney(saldo)}.`;
          errBox.style.display = 'block';
        }
        inp.style.border = '1.5px solid #ef4444';
        inp.style.boxShadow = '0 0 12px rgba(239, 68, 68, 0.45)';
        if (btnSubmit) btnSubmit.disabled = true;
        return false;
      }
    }
  }

  // Válido
  if (errBox) errBox.style.display = 'none';
  inp.style.border = '1.5px solid rgba(255, 255, 255, 0.12)';
  inp.style.boxShadow = 'none';
  if (btnSubmit) btnSubmit.disabled = false;
  return true;
}

async function savePago(e) {
  e.preventDefault();
  if (!validatePagoMontoInput()) {
    const errBox = $('#pago-monto-error');
    const msg = errBox?.textContent || 'Por favor verificá el importe ingresado.';
    showToast(msg, true);
    $('#pago-monto')?.focus();
    return;
  }

  const tipo = $('#pago-tipo').value;
  const monto = parseFloat($('#pago-monto').value || 0);

  const data = {
    tipo,
    alumno_id: $('#pago-alumno').value,
    profesor_id: $('#pago-profesor')?.value || '',
    monto: $('#pago-monto').value,
    fecha_pago: $('#pago-fecha').value,
    medio_pago: $('#pago-medio').value,
    observaciones: $('#pago-obs').value
  };
  const r = await api('pagos.save', data);
  if (r.ok) {
    showToast('Pago registrado correctamente');
    closeModal('modal-pago');
    loadPagos();
    loadDashboard();
    if (CURRENT_USER.role !== 'alumno') loadAlumnos();
    loadAlumnosOptions();
    if (typeof loadProfesores === 'function') loadProfesores();
    if (typeof loadProfesOptions === 'function') loadProfesOptions();
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== GESTIÓN DE LIQUIDACIONES Y GANANCIAS DEL COACH ===== */
function switchCoachSubTab(tabKey) {
  $$('.coach-subpane').forEach(p => p.style.display = 'none');
  const target = $('#coach-subpane-' + tabKey);
  if (target) target.style.display = 'block';

  ['liq', 'alumnos', 'stats'].forEach(k => {
    const btn = $('#btn-coach-tab-' + k);
    if (btn) {
      if (k === tabKey) {
        btn.className = 'btn btn-sm btn-primary';
      } else {
        btn.className = 'btn btn-sm btn-secondary';
      }
    }
  });
}

function switchCoachMovTab(tabKey, btnEl = null) {
  $$('.coach-mov-pane').forEach(p => p.style.display = 'none');
  const target = $('#' + tabKey);
  if (target) target.style.display = 'block';

  $$('#modal-coach-movimientos .tab-ficha').forEach(b => b.classList.remove('active'));
  if (btnEl) {
    btnEl.classList.add('active');
  } else {
    const defaultBtn = $(`#modal-coach-movimientos .tab-ficha[onclick*="${tabKey}"]`);
    if (defaultBtn) defaultBtn.classList.add('active');
  }
}

let _coachLiqCache = [];
let _coachLiqCurrentPage = 1;
let _coachLiqPageSize = 15;

let _coachCobrosCache = [];
let _coachCobrosCurrentPage = 1;
let _coachCobrosPageSize = 15;

function renderCoachLiqTabla(pagos) {
  if (pagos !== undefined) {
    _coachLiqCache = pagos || [];
    _coachLiqCurrentPage = 1;
  }
  const tb = $('#tbl-coach-liq-pagos tbody');
  const mobCont = $('#coach-liq-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mobCont) mobCont.innerHTML = '';

  const totalRecords = _coachLiqCache.length;
  const pageSize = Number(_coachLiqPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_coachLiqCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = _coachLiqCache.slice(startIdx, endIdx);

  if (!totalRecords) {
    tb.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--t-mut);padding:24px">No hay liquidaciones registradas por la sede este mes.</td></tr>';
    if (mobCont) mobCont.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:18px">Sin liquidaciones recibidas.</div>';
  } else {
    pageItems.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b>${fmtDate(p.fecha_pago)}</b></td>
        <td><span class="badge b-ok" style="text-transform:uppercase;font-weight:700">${escapeHtml(p.medio_pago || 'Efectivo')}</span></td>
        <td><span style="font-size:12.5px;color:var(--t2)">${escapeHtml(p.observaciones || 'Liquidación de honorarios abonada por la sede')}</span></td>
        <td style="text-align:right;font-weight:800;color:var(--ok);font-size:15px">$ ${fmtMoney(p.monto)}</td>
      `;
      tb.appendChild(tr);

      if (mobCont) {
        const card = document.createElement('div');
        card.className = 'mobile-record-card';
        card.innerHTML = `
          <div class="mobile-card-header">
            <span style="font-weight:800;color:var(--t1)">${fmtDate(p.fecha_pago)}</span>
            <span class="badge b-ok" style="font-weight:800;font-size:13px">$ ${fmtMoney(p.monto)}</span>
          </div>
          <div class="mobile-card-body">
            <div class="mobile-card-row"><span class="mobile-card-label">Medio</span><span style="text-transform:uppercase">${escapeHtml(p.medio_pago || 'Efectivo')}</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Detalle</span><span>${escapeHtml(p.observaciones || 'Liquidación abonada')}</span></div>
          </div>
        `;
        mobCont.appendChild(card);
      }
    });
  }

  renderGenericPagination({
    containerId: 'coach-liq-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'liquidaciones',
    onPageChange: (p) => {
      _coachLiqCurrentPage = p;
      renderCoachLiqTabla();
    }
  });
}

function changeCoachLiqPageSize(sz) {
  _coachLiqPageSize = Number(sz) || 15;
  _coachLiqCurrentPage = 1;
  renderCoachLiqTabla();
}

function renderCoachDashLiqTabla(pagos) {
  const tb = $('#tbl-coach-dash-liq tbody');
  const mobCont = $('#coach-dash-liq-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mobCont) mobCont.innerHTML = '';

  if (!pagos || !pagos.length) {
    tb.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--t-mut);padding:18px">Sin liquidaciones registradas este mes.</td></tr>';
    if (mobCont) mobCont.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:12px">Sin liquidaciones recibidas.</div>';
    return;
  }

  pagos.slice(0, 5).forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${fmtDate(p.fecha_pago)}</b></td>
      <td><span class="badge b-ok" style="text-transform:uppercase;font-size:11px">${escapeHtml(p.medio_pago || 'Efectivo')}</span></td>
      <td><span style="font-size:12px;color:var(--t2)">${escapeHtml(p.observaciones || 'Liquidación recibida')}</span></td>
      <td style="text-align:right;font-weight:800;color:var(--ok);font-size:13.5px">$ ${fmtMoney(p.monto)}</td>
    `;
    tb.appendChild(tr);

    if (mobCont) {
      const card = document.createElement('div');
      card.className = 'mobile-record-card';
      card.innerHTML = `
        <div class="mobile-card-header">
          <span style="font-weight:800;color:var(--t1)">${fmtDate(p.fecha_pago)}</span>
          <span class="badge b-ok" style="font-weight:800">$ ${fmtMoney(p.monto)}</span>
        </div>
        <div class="mobile-card-body">
          <div class="mobile-card-row"><span class="mobile-card-label">Medio</span><span style="text-transform:uppercase">${escapeHtml(p.medio_pago || 'Efectivo')}</span></div>
          ${p.observaciones ? `<div class="mobile-card-row"><span class="mobile-card-label">Detalle</span><span>${escapeHtml(p.observaciones)}</span></div>` : ''}
        </div>
      `;
      mobCont.appendChild(card);
    }
  });
}

async function loadCoachIngresos() {
  const { ok, data } = await api('coach.pagos.list', {}, 'GET');
  if (!ok || !data) return;

  const prof = data.profesor || {};
  const tot = data.totales_mes || {};
  const liquidado = Number(tot.liquidado_mes || 0);
  const ganancia = Number(tot.ganancia_mes || 0);
  const recaudado = Number(tot.recaudado_alumnos || 0);
  const saldoPend = Number(tot.saldo_pendiente || 0);

  // Cards de la pantalla Mis Ganancias & Liquidaciones
  if ($('#coach-ingreso-liquidado')) $('#coach-ingreso-liquidado').textContent = '$ ' + fmtMoney(liquidado);
  if ($('#coach-cuota-mensual')) {
    $('#coach-cuota-mensual').textContent = liquidado > 0 ? `✅ $ ${fmtMoney(liquidado)} abonado por el dueño` : 'Sin liquidaciones percibidas';
  }

  if ($('#coach-ingreso-ganancia')) $('#coach-ingreso-ganancia').textContent = '$ ' + fmtMoney(ganancia);
  if ($('#coach-ingreso-ganancia-sub')) {
    $('#coach-ingreso-ganancia-sub').innerHTML = saldoPend > 0 
      ? `<span class="badge b-warn">Pendiente de cobro: $ ${fmtMoney(saldoPend)}</span>` 
      : `<span class="badge b-ok">✅ Al Día (Liquidado al 100%)</span>`;
  }

  if ($('#coach-rec-mes')) $('#coach-rec-mes').textContent = '$ ' + fmtMoney(recaudado);
  if ($('#coach-rec-mes-sub')) $('#coach-rec-mes-sub').textContent = `${tot.alumnos_pagaron || 0} socio${tot.alumnos_pagaron === 1 ? '' : 's'} abonaron este mes`;

  const tipo = prof.tipo_remuneracion || tot.tipo_remuneracion || 'sueldo_fijo';
  let esquemaTxt = 'Sueldo Fijo';
  let esquemaSub = `$ ${fmtMoney(prof.cuota_mensual || 0)} / mes`;
  if (tipo === 'porcentaje') {
    esquemaTxt = `${prof.porcentaje_comision || tot.porcentaje_comision || 0}% Comisión`;
    esquemaSub = 'Sobre cuotas de alumnos';
  } else if (tipo === 'monto_alumno') {
    esquemaTxt = `$ ${fmtMoney(prof.monto_por_alumno || tot.monto_por_alumno || 0)} / Alumno`;
    esquemaSub = 'Por socio que abone';
  }
  if ($('#coach-ingreso-esquema')) $('#coach-ingreso-esquema').textContent = esquemaTxt;
  if ($('#coach-ingreso-esquema-sub')) $('#coach-ingreso-esquema-sub').textContent = esquemaSub;

  // Estadísticas de días y asistencias
  const stats = data.stats_dias || {};
  if ($('#coach-dias-activos')) $('#coach-dias-activos').textContent = `${stats.dias_activos || 0} Días`;
  if ($('#coach-total-clases')) $('#coach-total-clases').textContent = `${stats.total_asistencias || 0} asistencias registradas`;
  if ($('#coach-stats-dias-val')) $('#coach-stats-dias-val').textContent = stats.dias_activos || 0;
  if ($('#coach-stats-alumnos-val')) $('#coach-stats-alumnos-val').textContent = stats.total_asistencias || 0;
  if ($('#coach-stats-prom-val')) {
    const dAct = Number(stats.dias_activos || 0);
    const tClas = Number(stats.total_asistencias || 0);
    $('#coach-stats-prom-val').textContent = dAct > 0 ? (tClas / dAct).toFixed(1) : '0.0';
  }

  // Renderizar las 2 tablas
  renderCoachLiqTabla(data.liquidaciones_recibidas || []);
  renderCoachCobrosTabla(data.cobros_alumnos || []);
}

function renderCoachCobrosTabla(pagos) {
  if (pagos !== undefined) {
    _coachCobrosCache = pagos || [];
    _coachCobrosCurrentPage = 1;
  }
  const tb = $('#tbl-coach-cobros-pagos tbody') || $('#tbl-coach-pagos tbody');
  const mobCont = $('#coach-cobros-cards') || $('#coach-alumnos-pagos-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mobCont) mobCont.innerHTML = '';

  const totalRecords = _coachCobrosCache.length;
  const pageSize = Number(_coachCobrosPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_coachCobrosCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = _coachCobrosCache.slice(startIdx, endIdx);

  if (!totalRecords) {
    tb.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--t-mut);padding:20px">No se registraron cuotas cobradas a tus alumnos este mes.</td></tr>';
    if (mobCont) mobCont.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:18px">Sin cuotas cobradas.</div>';
  } else {
    pageItems.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b>${fmtDate(p.fecha_pago)}</b></td>
        <td><b style="color:var(--t1)">${escapeHtml(p.alumno_nombre)}</b></td>
        <td><span class="badge b-info">Plan ${escapeHtml(String(p.alumno_plan || '3x').toUpperCase())}</span></td>
        <td><span class="badge b-ok" style="text-transform:uppercase">${escapeHtml(p.medio_pago || 'Efectivo')}</span></td>
        <td style="text-align:right;font-weight:800;color:var(--ok);font-size:14px">$ ${fmtMoney(p.monto)}</td>
      `;
      tb.appendChild(tr);

      if (mobCont) {
        const card = document.createElement('div');
        card.className = 'mobile-record-card';
        card.innerHTML = `
          <div class="mobile-card-header">
            <span style="font-weight:800;color:var(--t1)">${escapeHtml(p.alumno_nombre)}</span>
            <span class="badge b-ok" style="font-weight:800">$ ${fmtMoney(p.monto)}</span>
          </div>
          <div class="mobile-card-body">
            <div class="mobile-card-row"><span class="mobile-card-label">Fecha</span><span>${fmtDate(p.fecha_pago)}</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Plan</span><span class="badge b-info">Plan ${escapeHtml(String(p.alumno_plan || '3x').toUpperCase())}</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Medio</span><span style="text-transform:uppercase">${escapeHtml(p.medio_pago || 'Efectivo')}</span></div>
          </div>
        `;
        mobCont.appendChild(card);
      }
    });
  }

  renderGenericPagination({
    containerId: 'coach-cobros-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'cobros',
    onPageChange: (p) => {
      _coachCobrosCurrentPage = p;
      renderCoachCobrosTabla();
    }
  });
}

function changeCoachCobrosPageSize(sz) {
  _coachCobrosPageSize = Number(sz) || 15;
  _coachCobrosCurrentPage = 1;
  renderCoachCobrosTabla();
}

async function openCoachMovimientosModal(profId) {
  window._currentMovProfId = profId;
  const prof = (_profesCache || []).find(p => String(p.id) === String(profId));
  if ($('#mov-coach-nombre')) $('#mov-coach-nombre').textContent = prof ? `Movimientos de ${prof.nombre}` : 'Movimientos del Coach';
  
  let esquemaStr = 'Esquema Sueldo Fijo';
  if (prof) {
    if (prof.tipo_remuneracion === 'porcentaje') esquemaStr = `Esquema: ${prof.porcentaje_comision}% Comisión`;
    else if (prof.tipo_remuneracion === 'monto_alumno') esquemaStr = `Esquema: $ ${fmtMoney(prof.monto_por_alumno)} / Alumno`;
    else if (prof.tipo_remuneracion === 'canon_alquiler') esquemaStr = `Esquema: Canon de Instalaciones`;
  }
  if ($('#mov-coach-esquema')) $('#mov-coach-esquema').textContent = esquemaStr;

  const r = await api('coach.pagos.list', { profesor_id: profId }, 'GET');
  if (r.ok && r.data) {
    const d = r.data;

    // Llenar tabla liquidaciones
    const tbLiq = $('#tbl-mov-liq tbody');
    if (tbLiq) {
      tbLiq.innerHTML = '';
      if (!d.liquidaciones_recibidas || !d.liquidaciones_recibidas.length) {
        tbLiq.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--t-mut);padding:18px">Sin liquidaciones registradas.</td></tr>';
      } else {
        d.liquidaciones_recibidas.forEach(p => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td><b>${fmtDate(p.fecha_pago)}</b></td><td><span class="badge b-ok">${p.medio_pago}</span></td><td>${p.observaciones || 'Liquidación por sede'}</td><td style="text-align:right;font-weight:800;color:var(--ok)">$ ${fmtMoney(p.monto)}</td>`;
          tbLiq.appendChild(tr);
        });
      }
    }

    // Llenar tabla cobros
    const tbCobros = $('#tbl-mov-cobros tbody');
    if (tbCobros) {
      tbCobros.innerHTML = '';
      if (!d.cobros_alumnos || !d.cobros_alumnos.length) {
        tbCobros.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--t-mut);padding:18px">Sin cobros registrados a alumnos.</td></tr>';
      } else {
        d.cobros_alumnos.forEach(p => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td><b>${fmtDate(p.fecha_pago)}</b></td><td><b style="color:var(--t1)">${p.alumno_nombre}</b></td><td><span class="badge b-info">Plan ${String(p.alumno_plan || '3x').toUpperCase()}</span></td><td><span class="badge b-ok">${p.medio_pago}</span></td><td style="text-align:right;font-weight:800;color:var(--ok)">$ ${fmtMoney(p.monto)}</td>`;
          tbCobros.appendChild(tr);
        });
      }
    }

    // Stats de días
    if ($('#mov-stats-dias')) $('#mov-stats-dias').textContent = d.stats_dias?.dias_activos || 0;
    if ($('#mov-stats-asist')) $('#mov-stats-asist').textContent = d.stats_dias?.total_asistencias || 0;
  }

  switchCoachMovTab('tab-mov-liq');
  openModal('modal-coach-movimientos');
}

/* ===== CHECK-IN DE RUTINAS Y FEEDBACK DEL COACH ===== */
function openCheckinModal(rutinaNombre, diaId = null, progId = null, aluId = null) {
  if ($('#checkin-rutina-nombre')) $('#checkin-rutina-nombre').value = rutinaNombre || 'Entrenamiento del Día';
  if ($('#checkin-dia-id')) $('#checkin-dia-id').value = diaId || '';
  if ($('#checkin-prog-id')) $('#checkin-prog-id').value = progId || '';
  if ($('#checkin-alu-id')) $('#checkin-alu-id').value = aluId || CURRENT_USER.alumno_id || '';
  if ($('#checkin-obs')) $('#checkin-obs').value = '';
  openModal('modal-checkin-rutina');
}

async function submitRutinaCheckin(e) {
  e.preventDefault();
  const aluId = $('#checkin-alu-id')?.value;
  const diaId = $('#checkin-dia-id')?.value;
  const progId = $('#checkin-prog-id')?.value;
  const nombre = $('#checkin-rutina-nombre')?.value;
  const duracion = $('#checkin-duracion')?.value;
  const esfuerzo = $('#checkin-esfuerzo')?.value;
  const obs = $('#checkin-obs')?.value;

  const r = await api('alumnos.checkin_rutina', {
    alumno_id: aluId,
    dia_id: diaId,
    programa_id: progId,
    rutina_nombre: nombre,
    duracion_min: duracion,
    nivel_esfuerzo: esfuerzo,
    observaciones: obs
  });

  if (r.ok) {
    showToast('¡Sesión registrada con éxito! 🔥 Gran entrenamiento.');
    closeModal('modal-checkin-rutina');
    if (typeof loadAlumnoPortal === 'function') loadAlumnoPortal();
    if (typeof loadDashboard === 'function') loadDashboard();
  } else {
    showToast(r.msg || 'Error al registrar check-in', true);
  }
}

function openCoachFeedbackModal(checkinId, rutinaNombre, studentNotes) {
  if ($('#feedback-checkin-id')) $('#feedback-checkin-id').value = checkinId;
  if ($('#feedback-target-info')) $('#feedback-target-info').textContent = `🏋️ Entrenamiento: ${rutinaNombre}`;
  if ($('#feedback-student-notes')) $('#feedback-student-notes').textContent = studentNotes ? `Notas del socio: "${studentNotes}"` : 'El socio no dejó notas adicionales.';
  if ($('#feedback-text')) $('#feedback-text').value = '';
  openModal('modal-coach-feedback');
}

async function submitCoachFeedback(e) {
  e.preventDefault();
  const checkinId = $('#feedback-checkin-id')?.value;
  const feedback = $('#feedback-text')?.value;

  const r = await api('alumnos.dar_feedback_rutina', {
    checkin_id: checkinId,
    coach_feedback: feedback
  });

  if (r.ok) {
    showToast('Devolución técnica guardada con éxito.');
    closeModal('modal-coach-feedback');
    if (_currentFichaData && _currentFichaData.alumno) {
      openAlumnoFicha(_currentFichaData.alumno.id);
    }
  } else {
    showToast(r.msg || 'Error al guardar devolución', true);
  }
}

/* ===== RECIBO / COMPROBANTE DIGITAL DE PAGO ===== */
function openReciboModal(p) {
  if (!p) return;
  if (typeof p === 'number' || typeof p === 'string') {
    const found = (window._coachLiqCache || []).find(x => String(x.id) === String(p));
    if (found) p = found;
    else p = { id: p, tipo: 'profesor' };
  }
  const isProf = p.tipo === 'profesor';
  if ($('#recibo-nro')) $('#recibo-nro').textContent = `#REC-${String(p.id || 1).padStart(5, '0')}`;
  if ($('#recibo-gym-nombre')) $('#recibo-gym-nombre').textContent = p.gym_nombre || CURRENT_USER.gimnasio_nombre || 'Olympus Gym Pro';
  
  if ($('#recibo-lbl-titular')) $('#recibo-lbl-titular').textContent = isProf ? 'Coach / Beneficiario:' : 'Socio / Titular:';
  if ($('#recibo-alumno-nombre')) $('#recibo-alumno-nombre').textContent = p.profesor || p.alumno || CURRENT_USER.name || 'Titular';

  if ($('#recibo-lbl-plan')) $('#recibo-lbl-plan').textContent = isProf ? 'Concepto de Pago:' : 'Plan Contratado:';
  if ($('#recibo-plan')) {
    if (isProf) {
      $('#recibo-plan').textContent = 'Liquidación de Honorarios / Comisiones';
    } else {
      const planName = p.alumno_plan || p.plan;
      $('#recibo-plan').textContent = planName ? `Plan ${String(planName).toUpperCase()}` : 'Cuota Gimnasio';
    }
  }

  if ($('#recibo-fecha')) $('#recibo-fecha').textContent = fmtDate(p.fecha_pago || '2026-08-20');
  if ($('#recibo-medio')) $('#recibo-medio').textContent = (p.medio_pago || 'Efectivo').toUpperCase();
  if ($('#recibo-obs')) $('#recibo-obs').textContent = p.observaciones || (isProf ? 'Liquidación de honorarios abonada por la sede' : 'Cuota mensual regular');
  if ($('#recibo-monto')) $('#recibo-monto').textContent = `$ ${fmtMoney(p.monto || 0)}`;
  if ($('#recibo-venc')) $('#recibo-venc').textContent = p.fecha_vencimiento ? fmtDate(p.fecha_vencimiento) : '-';
  
  openModal('modal-recibo-alumno');
}
window.openReciboModal = openReciboModal;

function openCoachRecibo(pagoId) {
  let p = (window._coachLiqCache || []).find(x => String(x.id) === String(pagoId));
  if (!p) {
    p = { id: pagoId, tipo: 'profesor', monto: 10000, fecha_pago: '2026-08-20', medio_pago: 'efectivo', observaciones: 'Liquidación de honorarios abonada por la sede' };
  }
  openReciboModal(p);
}
window.openCoachRecibo = openCoachRecibo;

/* ===== USUARIOS & ROLES ===== */
let _usersCache = [];
let _usersCurrentPage = 1;
let _usersPageSize = 15;

async function loadUsuarios() {
  const { ok, data } = await api('usuarios.list', {}, 'GET');
  if (!ok) return;
  _usersCache = data || [];
  _usersCurrentPage = 1;
  renderUsuariosTable();
}

function renderUsuariosTable() {
  const tb = $('#tbl-usuarios tbody');
  const mob = $('#usuarios-cards');
  if (!tb) return;
  tb.innerHTML = '';
  if (mob) mob.innerHTML = '';

  const q = ($('#user-filter-q')?.value || '').toLowerCase().trim();
  const rolFiltro = $('#user-filter-rol')?.value || '';
  const estadoFiltro = $('#user-filter-estado')?.value || '';

  let list = _usersCache || [];
  if (rolFiltro) {
    if (rolFiltro === 'admin_general') {
      list = list.filter(u => u.rol === 'admin_general' || Number(u.is_superadmin) === 1);
    } else {
      list = list.filter(u => u.rol === rolFiltro);
    }
  }
  if (estadoFiltro !== '') {
    list = list.filter(u => String(u.activo) === String(estadoFiltro));
  }
  if (q) {
    list = list.filter(u => 
      (u.nombre_usuario && u.nombre_usuario.toLowerCase().includes(q)) ||
      (u.email && u.email.toLowerCase().includes(q)) ||
      (u.telefono && u.telefono.includes(q)) ||
      (u.persona_nombre && u.persona_nombre.toLowerCase().includes(q)) ||
      (u.gimnasio_nombre && u.gimnasio_nombre.toLowerCase().includes(q))
    );
  }

  const totalRecords = list.length;
  const pageSize = Number(_usersPageSize || 15);
  const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize));
  const validPage = Math.max(1, Math.min(_usersCurrentPage, totalPages));

  const startIdx = (validPage - 1) * pageSize;
  const endIdx = Math.min(startIdx + pageSize, totalRecords);
  const pageItems = list.slice(startIdx, endIdx);

  if (!totalRecords) {
    tb.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--t-mut);padding:28px">No se encontraron usuarios registrados.</td></tr>`;
    if (mob) mob.innerHTML = '<div style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron usuarios.</div>';
  } else {
    pageItems.forEach(u => {
      const isSuperAdmin = u.is_superadmin == 1 || u.rol === 'admin_general';
      const isSelf = Number(u.id) === Number(CURRENT_USER.id);
      const canManage = CURRENT_USER.role === 'admin_general' || (CURRENT_USER.role === 'dueno' && (u.rol === 'coach' || u.rol === 'alumno') && !isSelf);

      const rolBadge = isSuperAdmin ? 'b-purple' : (u.rol === 'dueno' ? 'b-info' : (u.rol === 'coach' ? 'b-ok' : 'b-warn'));
      const rolLabel = isSuperAdmin ? '👑 SuperAdmin' : (u.rol === 'dueno' ? '🏢 Dueño' : (u.rol === 'coach' ? '🏋️ Coach' : '👤 Socio'));
      const vinculo = u.rol === 'dueno' ? `🏢 ${u.gimnasio_nombre || 'Sede'}` : (u.rol === 'coach' ? `🏋️ ${u.profesor_nombre || 'Coach'}` : (u.rol === 'alumno' ? `👤 ${u.alumno_nombre || 'Socio'}` : 'Plataforma'));
      const isActivo = Number(u.activo) === 1;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <b style="font-size:14px;color:var(--t1)">${escapeHtml(u.nombre_usuario)}</b>
          ${isSelf ? `<span class="badge b-info" style="font-size:9.5px;margin-left:4px">Tu Sesión</span>` : ''}
        </td>
        <td>
          <span>${escapeHtml(u.email || '-')}</span>
          ${u.telefono ? `<br><small style="color:var(--t2)">📱 ${escapeHtml(u.telefono)}</small>` : ''}
        </td>
        <td><span class="badge ${rolBadge}">${rolLabel}</span></td>
        <td><span style="font-weight:600">${escapeHtml(vinculo)}</span></td>
        <td>${escapeHtml(u.gimnasio_nombre || '-')}</td>
        <td>
          <span class="badge ${isActivo ? 'b-ok' : 'b-bad'}" style="font-size:11.5px;padding:4px 9px">
            ${isActivo ? '✅ Habilitado' : '⛔ Bloqueado'}
          </span>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <div style="display:inline-flex;gap:5px;align-items:center;justify-content:flex-end">
            ${canManage ? `
              <button type="button" class="btn btn-xs btn-purple" 
                      style="font-weight:800;padding:5px 9px;background:rgba(139,92,246,0.2);border:1px solid rgba(139,92,246,0.4);color:#c084fc" 
                      title="Generar contraseña temporal de recuperación" 
                      onclick="generarClaveTemporal(${u.id}, '${(u.nombre_usuario || '').replace(/'/g, "\\'")}')">
                🔑 Clave
              </button>
              <button type="button" class="btn btn-xs ${isActivo ? 'btn-danger' : 'btn-success'}" 
                      style="font-weight:700;padding:5px 9px" 
                      title="${isActivo ? 'Bloquear acceso de cuenta al usuario' : 'Habilitar acceso de cuenta al usuario'}"
                      onclick="toggleUserStatus(${u.id}, '${(u.nombre_usuario || '').replace(/'/g, "\\'")}', ${isActivo ? 0 : 1})">
                ${isActivo ? '🔒 Bloquear' : '🔓 Habilitar'}
              </button>
              <button type="button" class="btn btn-xs btn-secondary" 
                      style="font-weight:700;padding:5px 9px" 
                      title="Blanquear contraseña" 
                      onclick="blanquearClaveUsuario(${u.id}, '${(u.nombre_usuario || '').replace(/'/g, "\\'")}')">
                🔑 Blanquear
              </button>
            ` : (isSelf ? `<span style="font-size:11.5px;color:var(--t-mut);font-style:italic">Tu Cuenta</span>` : `<span style="font-size:11.5px;color:var(--t-mut)">Protegido</span>`)}
            ${CURRENT_USER.role === 'admin_general' ? `
              <button type="button" class="btn btn-xs btn-secondary" title="Editar Usuario" onclick='openUserModal(${JSON.stringify(u)})'>✏️</button>
            ` : ''}
          </div>
        </td>
      `;
      tb.appendChild(tr);
    });
  }

  renderGenericPagination({
    containerId: 'usuarios-pagination-bar',
    totalRecords,
    currentPage: validPage,
    pageSize,
    itemLabel: 'usuarios',
    scrollTargetId: 'page-usuarios',
    onPageChange: (p) => {
      _usersCurrentPage = p;
      renderUsuariosTable();
    }
  });
}

function changeUsersPageSize(sz) {
  _usersPageSize = Number(sz) || 15;
  _usersCurrentPage = 1;
  renderUsuariosTable();
}

let _currentTempPassData = null;

async function generarClaveTemporal(userId, nombreUsuario, aluId = 0, profId = 0, gymId = 0) {
  const r = await api('usuarios.generar_temp_pass', {
    user_id: userId,
    alumno_id: aluId,
    profesor_id: profId,
    gimnasio_id: gymId
  });
  if (r.ok && r.data) {
    _currentTempPassData = r.data;
    if ($('#temp-pass-target-name')) $('#temp-pass-target-name').textContent = `Usuario: @${r.data.nombre_usuario} (${r.data.persona_nombre || ''})`;
    if ($('#temp-pass-code-display')) $('#temp-pass-code-display').textContent = r.data.temp_password;

    const btnWa = $('#btn-temp-pass-wa');
    if (btnWa) {
      if (r.data.whatsapp_link) {
        btnWa.style.display = 'inline-flex';
        btnWa.onclick = () => window.open(r.data.whatsapp_link, '_blank');
      } else {
        btnWa.style.display = 'none';
      }
    }

    openModal('modal-temp-pass');
    showToast('🔑 Contraseña temporal generada con éxito');
    if (typeof loadUsuarios === 'function') loadUsuarios();
  } else {
    showToast(r.msg || 'Error al generar contraseña temporal', true);
  }
}

function copyTempPassword() {
  if (!_currentTempPassData?.temp_password) return;
  navigator.clipboard.writeText(_currentTempPassData.temp_password).then(() => {
    showToast('📋 Contraseña temporal copiada al portapapeles');
  }).catch(() => {
    const inp = document.createElement('textarea');
    inp.value = _currentTempPassData.temp_password;
    document.body.appendChild(inp);
    inp.select();
    document.execCommand('copy');
    document.body.removeChild(inp);
    showToast('📋 Contraseña temporal copiada');
  });
}

function shareTempPassWhatsApp() {
  if (_currentTempPassData?.whatsapp_link) {
    window.open(_currentTempPassData.whatsapp_link, '_blank');
  } else if (_currentTempPassData?.whatsapp_msg) {
    const link = 'https://wa.me/?text=' + encodeURIComponent(_currentTempPassData.whatsapp_msg);
    window.open(link, '_blank');
  }
}

async function toggleUserStatus(userId, nombreUsuario, nuevoEstado) {
  const isBloquear = (Number(nuevoEstado) === 0);
  const r = await api('usuarios.toggle_status', { user_id: userId, activo: nuevoEstado });
  if (r.ok) {
    showToast(isBloquear ? `🔒 Cuenta de '${nombreUsuario}' bloqueada con éxito` : `🔓 Cuenta de '${nombreUsuario}' habilitada con éxito`);
    await loadUsuarios();
  } else {
    showToast(r.msg || 'Error al modificar estado', true);
  }
}

async function blanquearClaveUsuario(userId, nombreUsuario) {
  const r = await api('usuarios.blanquear', { user_id: userId });
  if (r.ok) {
    showToast(r.msg || `🔑 Contraseña de '${nombreUsuario}' blanqueada. Deberá crear una nueva clave al ingresar.`);
  } else {
    showToast(r.msg || 'Error al blanquear', true);
  }
}

function openUserModal(row = null) {
  const isEdit = !!row?.id;
  $('#user-modal-title').textContent = isEdit ? `Editar Usuario: @${row.nombre_usuario}` : 'Registrar Nuevo Usuario';
  $('#user-id').value = row?.id || '';
  $('#user-nombre').value = row?.nombre_usuario || '';
  $('#user-email').value = row?.email || '';
  if ($('#user-tel')) $('#user-tel').value = row?.telefono || '';
  $('#user-rol').value = row?.rol || 'alumno';
  $('#user-activo').value = row?.activo ?? 1;
  $('#user-password').value = '';
  $('#user-password').required = !isEdit;
  openModal('modal-usuario');
}

async function saveUsuario(e) {
  e.preventDefault();
  const data = {
    id: $('#user-id').value,
    nombre_usuario: $('#user-nombre').value,
    email: $('#user-email').value,
    telefono: $('#user-tel')?.value || '',
    rol: $('#user-rol').value,
    activo: $('#user-activo').value,
    password: $('#user-password').value
  };
  const r = await api('usuarios.save', data);
  if (r.ok) {
    showToast(r.msg || 'Usuario guardado exitosamente');
    closeModal('modal-usuario');
    await loadUsuarios();
  } else {
    showToast(r.msg || 'Error al guardar usuario', true);
  }
}

/* ===== CONFIGURACIÓN ===== */
async function loadConfig() {
  const { ok, data } = await api('config.get', {}, 'GET');
  if (!ok) return;
  const map = {}; data.forEach(x => { map[x.plan] = x.precio; });
  $('#cfg-3x').value = map['3x'] ?? 0;
  $('#cfg-full').value = map['full'] ?? 0;
  $('#cfg-clase').value = map['clase'] ?? 0;
}

async function saveConfig(e) {
  e.preventDefault();
  const data = { p3x: $('#cfg-3x').value, pfull: $('#cfg-full').value, pclase: $('#cfg-clase').value };
  const r = await api('config.save', data);
  if (r.ok) { showToast('Precios guardados'); loadAlumnos(); }
  else showToast(r.msg || 'Error', true);
  return false;
}

async function loadGymData() {
  const { ok, data } = await api('gym.get', {}, 'GET');
  if (!ok || !data) return;
  if ($('#cfg-gym-nombre')) $('#cfg-gym-nombre').value = data.nombre || 'Gimnasio';
  if ($('#cfg-gym-code')) $('#cfg-gym-code').value = data.invite_code || '';
  if ($('#cfg-gym-tel')) $('#cfg-gym-tel').value = data.telefono || '';
  if ($('#cfg-gym-dir')) $('#cfg-gym-dir').value = data.direccion || '';
  if (CURRENT_USER.role === 'dueno' && data.nombre && $('#user-role-text')) {
    $('#user-role-text').textContent = data.nombre;
  }
}

async function saveGymData(e) {
  e.preventDefault();
  const data = {
    nombre: $('#cfg-gym-nombre')?.value || '',
    invite_code: $('#cfg-gym-code')?.value || '',
    telefono: $('#cfg-gym-tel')?.value || '',
    direccion: $('#cfg-gym-dir')?.value || ''
  };
  const r = await api('gym.save', data);
  if (r.ok) {
    showToast('Datos de sede guardados');
    if (CURRENT_USER.role === 'dueno' && data.nombre && $('#user-role-text')) {
      $('#user-role-text').textContent = data.nombre;
    }
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== OPTIONS POPULATORS ===== */
async function loadAlumnosOptions() {
  const { ok, data } = await api('alumnos.list', { q: '' }, 'GET');
  if (!ok) return;
  _alumnosCache = data;
  ['#rutina-alumno', '#nutri-alumno', '#pago-alumno'].forEach(selStr => {
    const s = $(selStr);
    if (!s) return;
    s.innerHTML = '<option value="">(Seleccionar Alumno)</option>';
    data.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id;
      opt.textContent = `${a.nombre}${a.dni ? ' (DNI: ' + a.dni + ')' : ''} - ${a.plan} ($ ${fmtMoney(a.cuota_mes)})`;
      s.appendChild(opt);
    });
  });
}

async function loadProfesOptions() {
  const { ok, data } = await api('profesores.list', { q: '' }, 'GET');
  if (!ok) return;
  _profesCache = data;

  // 1. Selector de Coach en Formulario y Filtro de Alumnos: SOLO MOSTRAR EL NOMBRE (Sin precio ni sueldo privado)
  ['#alu-prof', '#alu-prof-inp'].forEach(selStr => {
    const s = $(selStr);
    if (!s) return;
    s.innerHTML = '<option value="">(Sin coach asignado / General)</option>';
    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.nombre}`;
      s.appendChild(opt);
    });
  });

  // 2. Selector de Pago a Coach (Liquidación de honorarios)
  const selPago = $('#pago-profesor');
  if (selPago) {
    selPago.innerHTML = '<option value="">(Seleccionar Coach)</option>';
    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.nombre}`;
      selPago.appendChild(opt);
    });
  }
}

/* ===== CANVAS CHARTS (SEMANAL BARRAS, MENSUAL LÍNEAS, ANUAL TORTA) ===== */

// 1. Gráfica de Dona para Dashboard (Cumplimiento)
function drawDonut(canvas, items, centerTitle = '', centerSub = '') {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = (canvas.clientWidth || 175);
  const h = canvas.height = (canvas.clientHeight || 175);
  ctx.clearRect(0, 0, w, h);

  const rawTot = items.reduce((a, b) => a + (Number(b.value) || 0), 0);
  const tot = rawTot || 1;
  const cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 8, ir = r * 0.68;
  let start = -Math.PI / 2;

  if (rawTot === 0) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, 2 * Math.PI); ctx.fillStyle = 'rgba(255, 255, 255, 0.06)'; ctx.fill();
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath(); ctx.arc(cx, cy, ir, 0, 2 * Math.PI); ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = '#64748b'; ctx.font = '700 13px system-ui'; ctx.textAlign = 'center';
    ctx.fillText('0', cx, cy + 4);
    return;
  }

  items.forEach(it => {
    const val = Number(it.value) || 0;
    if (val <= 0) return;
    const slice = (val / tot) * 2 * Math.PI;
    const end = start + slice;
    ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, r, start, end); ctx.closePath();
    ctx.fillStyle = it.color || '#3b82f6'; ctx.fill();
    start = end;
  });

  ctx.globalCompositeOperation = 'destination-out';
  ctx.beginPath(); ctx.arc(cx, cy, ir, 0, 2 * Math.PI); ctx.fill();
  ctx.globalCompositeOperation = 'source-over';

  const isDark = document.body.classList.contains('dark-mode');
  const mainTxt = centerTitle || rawTot.toLocaleString('es-AR');
  ctx.fillStyle = isDark ? '#f8fafc' : '#222222'; 
  ctx.font = '800 22px system-ui'; 
  ctx.textAlign = 'center';
  ctx.fillText(mainTxt, cx, cy + (centerSub ? -2 : 7));

  if (centerSub) {
    ctx.fillStyle = isDark ? '#94a3b8' : '#717171';
    ctx.font = '700 9.5px system-ui';
    ctx.fillText(centerSub.toUpperCase(), cx, cy + 14);
  }
}

// 2. Gráfica Semanal de BARRAS (Lun a Dom)
function drawWeeklyBarChart(canvas, data) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth || 400, h = canvas.height = canvas.clientHeight || 280;
  ctx.clearRect(0, 0, w, h);
  const pts = data?.serie || [];
  if (!pts.length) return;

  const isDark = document.body.classList.contains('dark-mode');
  const maxVal = Math.max(...pts.map(p => Number(p.monto) || 0), 100);
  const padBottom = 40, padTop = 35, padX = 30;
  const chartHeight = h - padBottom - padTop;
  const chartWidth = w - 2 * padX;
  const colStep = chartWidth / pts.length;
  const barWidth = Math.max(colStep * 0.52, 14);

  // Líneas guía horizontales
  ctx.strokeStyle = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const yLine = padTop + (chartHeight / 4) * i;
    ctx.beginPath(); ctx.moveTo(padX, yLine); ctx.lineTo(w - padX, yLine); ctx.stroke();
  }

  pts.forEach((p, i) => {
    const val = Number(p.monto) || 0;
    const bh = (val / maxVal) * chartHeight;
    const x = padX + i * colStep + (colStep - barWidth) / 2;
    const y = h - padBottom - bh;

    // Barra con Gradiente Azul/Violeta
    const grad = ctx.createLinearGradient(0, y, 0, h - padBottom);
    if (val > 0) {
      grad.addColorStop(0, '#3b82f6');
      grad.addColorStop(1, '#1e3a8a');
    } else {
      grad.addColorStop(0, isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.04)');
      grad.addColorStop(1, isDark ? 'rgba(255, 255, 255, 0.02)' : 'rgba(0, 0, 0, 0.01)');
    }

    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.roundRect(x, Math.min(y, h - padBottom - 4), barWidth, Math.max(bh, 4), [6, 6, 0, 0]);
    ctx.fill();

    // Monto arriba de la barra
    if (val > 0) {
      ctx.fillStyle = isDark ? '#60a5fa' : '#2563eb';
      ctx.font = '700 11px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(`$${(val >= 1000 ? (val/1000).toFixed(0) + 'k' : val)}`, x + barWidth / 2, y - 8);
    }

    // Día y Fecha abajo
    ctx.fillStyle = isDark ? '#f8fafc' : '#222222';
    ctx.font = '700 12px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText(p.label, x + barWidth / 2, h - padBottom + 16);

    ctx.fillStyle = isDark ? '#64748b' : '#717171';
    ctx.font = '10px system-ui';
    ctx.fillText(p.sublabel, x + barWidth / 2, h - padBottom + 28);
  });
}

// 3. Gráfica Mensual de LÍNEAS (Progreso y Evolución)
function drawMonthlyLineChart(canvas, data) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth || 400, h = canvas.height = canvas.clientHeight || 280;
  ctx.clearRect(0, 0, w, h);
  const pts = data?.serie || [];
  if (!pts.length) return;

  const isDark = document.body.classList.contains('dark-mode');
  const maxVal = Math.max(...pts.map(p => Number(p.monto) || 0), 100);
  const padBottom = 35, padTop = 35, padX = 40;
  const chartHeight = h - padBottom - padTop;
  const chartWidth = w - 2 * padX;
  const colStep = chartWidth / Math.max(1, pts.length - 1);

  // Líneas guía horizontales
  ctx.strokeStyle = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const yLine = padTop + (chartHeight / 4) * i;
    ctx.beginPath(); ctx.moveTo(padX, yLine); ctx.lineTo(w - padX, yLine); ctx.stroke();
  }

  // Coordenadas calculadas
  const coords = pts.map((p, i) => {
    const val = Number(p.monto) || 0;
    const x = padX + i * colStep;
    const y = h - padBottom - (val / maxVal) * chartHeight;
    return { x, y, val, label: p.label };
  });

  // Área sombreada bajo la curva
  const areaGrad = ctx.createLinearGradient(0, padTop, 0, h - padBottom);
  areaGrad.addColorStop(0, 'rgba(6, 182, 212, 0.35)');
  areaGrad.addColorStop(1, 'rgba(6, 182, 212, 0.0)');

  ctx.beginPath();
  ctx.moveTo(coords[0].x, h - padBottom);
  coords.forEach((c, i) => {
    if (i === 0) ctx.lineTo(c.x, c.y);
    else {
      const prev = coords[i - 1];
      const cx = (prev.x + c.x) / 2;
      ctx.bezierCurveTo(cx, prev.y, cx, c.y, c.x, c.y);
    }
  });
  ctx.lineTo(coords[coords.length - 1].x, h - padBottom);
  ctx.closePath();
  ctx.fillStyle = areaGrad;
  ctx.fill();

  // Línea continua de progreso
  ctx.beginPath();
  coords.forEach((c, i) => {
    if (i === 0) ctx.moveTo(c.x, c.y);
    else {
      const prev = coords[i - 1];
      const cx = (prev.x + c.x) / 2;
      ctx.bezierCurveTo(cx, prev.y, cx, c.y, c.x, c.y);
    }
  });
  ctx.strokeStyle = '#06b6d4';
  ctx.lineWidth = 3.5;
  ctx.lineCap = 'round';
  ctx.stroke();

  // Puntos interactivos y valores
  coords.forEach(c => {
    // Círculo exterior
    ctx.beginPath();
    ctx.arc(c.x, c.y, 6, 0, 2 * Math.PI);
    ctx.fillStyle = isDark ? '#090d16' : '#ffffff';
    ctx.fill();
    ctx.strokeStyle = '#06b6d4';
    ctx.lineWidth = 3;
    ctx.stroke();

    // Monto
    if (c.val > 0) {
      ctx.fillStyle = isDark ? '#38bdf8' : '#0284c7';
      ctx.font = '700 11px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(`$${(c.val >= 1000 ? (c.val/1000).toFixed(0) + 'k' : c.val)}`, c.x, c.y - 12);
    }

    // Etiqueta del mes
    ctx.fillStyle = isDark ? '#cbd5e1' : '#222222';
    ctx.font = '700 12px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText(c.label, c.x, h - padBottom + 20);
  });
}

// 4. Gráfica Anual de TORTA / DONUT (Distribución por Concepto)
function drawAnnualPieChart(canvas, items, legendId, totalAnual) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth || 300, h = canvas.height = canvas.clientHeight || 280;
  ctx.clearRect(0, 0, w, h);

  const isDark = document.body.classList.contains('dark-mode');
  const tot = totalAnual || items.reduce((a, b) => a + (Number(b.valor) || 0), 0) || 1;
  const cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 18, ir = r * 0.60;
  let start = -Math.PI / 2;

  items.forEach(it => {
    const val = Number(it.valor) || 0;
    const slice = (val / tot) * 2 * Math.PI;
    const end = start + slice;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, r, start, end);
    ctx.closePath();
    ctx.fillStyle = it.color || '#3b82f6';
    ctx.fill();
    start = end;
  });

  // Centro hueco (Donut)
  ctx.globalCompositeOperation = 'destination-out';
  ctx.beginPath();
  ctx.arc(cx, cy, ir, 0, 2 * Math.PI);
  ctx.fill();
  ctx.globalCompositeOperation = 'source-over';

  // Texto central
  ctx.fillStyle = isDark ? '#f8fafc' : '#222222';
  ctx.font = '800 18px system-ui';
  ctx.textAlign = 'center';
  ctx.fillText(`$${fmtMoney(tot)}`, cx, cy + 2);
  ctx.fillStyle = isDark ? '#94a3b8' : '#717171';
  ctx.font = '700 10px system-ui';
  ctx.fillText('TOTAL ANUAL', cx, cy + 18);

  // Renderizar Leyenda con barras de porcentaje
  const leg = document.getElementById(legendId);
  if (leg) {
    leg.innerHTML = '';
    items.forEach(it => {
      const val = Number(it.valor) || 0;
      const pct = tot > 0 ? ((val / tot) * 100).toFixed(1) : '0.0';
      const row = document.createElement('div');
      row.style.background = 'rgba(255, 255, 255, 0.03)';
      row.style.border = '1px solid var(--border)';
      row.style.borderRadius = '10px';
      row.style.padding = '10px 14px';
      row.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="display:inline-block;width:12px;height:12px;border-radius:4px;background:${it.color}"></span>
            <strong style="font-size:13px;color:var(--t1)">${it.label}</strong>
          </div>
          <div style="text-align:right">
            <span style="font-weight:800;color:${it.color};font-size:13px">$ ${fmtMoney(val)}</span>
            <small style="color:var(--t-mut);margin-left:6px;font-weight:700">(${pct}%)</small>
          </div>
        </div>
        <div style="width:100%;height:4px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden">
          <div style="width:${pct}%;height:100%;background:${it.color};border-radius:2px"></div>
        </div>
      `;
      leg.appendChild(row);
    });
  }
}

// Carga y Ejecución de Reportes
async function loadReportes() {
  const { ok, data } = await api('reportes.avanzado', {}, 'GET');
  if (!ok) return;

  // Actualizar KPI resumen
  if ($('#rep-total-semana')) $('#rep-total-semana').textContent = fmtMoney(data.semana?.total || 0);
  if ($('#rep-total-mes')) $('#rep-total-mes').textContent = fmtMoney(data.mensual?.total_ultimo || 0);
  if ($('#rep-total-anual')) $('#rep-total-anual').textContent = fmtMoney(data.anual?.total || 0);
  if ($('#rep-year-lbl')) $('#rep-year-lbl').textContent = data.anual?.year || '2026';

  requestAnimationFrame(() => {
    // 1. Semanal de Barras
    drawWeeklyBarChart($('#chart-semanal-barras'), data.semana);
    // 2. Mensual de Líneas
    drawMonthlyLineChart($('#chart-mensual-lineas'), data.mensual);
    // 3. Anual de Torta con Leyenda
    drawAnnualPieChart($('#chart-anual-torta'), data.anual?.distribucion || [], 'legend-anual-torta', data.anual?.total || 0);
  });
}

function currentDate() {
  const d = new Date();
  return `${d.getFullYear()}-${('0' + (d.getMonth() + 1)).slice(-2)}-${('0' + d.getDate()).slice(-2)}`;
}

function calcVenc(baseStr, plan) {
  const d = new Date(baseStr || Date.now());
  d.setDate(d.getDate() + 30);
  return `${d.getFullYear()}-${('0' + (d.getMonth() + 1)).slice(-2)}-${('0' + d.getDate()).slice(-2)}`;
}

/* ===== HISTORIAL NAVEGACIÓN Y BOTÓN ATRÁS DEL NAVEGADOR ===== */
window.addEventListener('popstate', (e) => {
  // 1. Si hay un modal o simulador abierto, cerrarlo y evitar saltar de pantalla
  const openModalEl = $$('.modal-backdrop, .simulator-overlay').find(m => m.style.display === 'flex');
  if (openModalEl) {
    openModalEl.style.display = 'none';
    const anyStillOpen = $$('.modal-backdrop, .simulator-overlay').some(m => m.style.display === 'flex');
    if (!anyStillOpen) document.body.style.overflow = '';
    return;
  }

  // 2. Si el sidebar móvil está abierto, cerrarlo
  const sb = $('.sidebar');
  if (sb && sb.classList.contains('open')) {
    closeMobileSidebar();
    return;
  }

  // 3. Si está en simulación móvil en pantalla, desactivarlo
  if (typeof _liveDeviceActive !== 'undefined' && _liveDeviceActive) {
    setLiveDeviceMode('fullscreen');
    return;
  }

  // 4. Si el estado del evento contiene una página o modal específico
  if (e.state) {
    if (e.state.type === 'modal' && e.state.modalId) {
      handleRouteHash(e.state.modalId);
      return;
    }
    if (e.state.page) {
      setPage(e.state.page, false);
      return;
    }
  }

  // 5. Si hay hash en la URL
  if (window.location.hash) {
    handleRouteHash();
    return;
  }

  // 6. Volver a la página previa del stack o dashboard
  if (_navHistory.length > 0) {
    const prev = _navHistory.pop();
    setPage(prev, false);
  } else {
    setPage('dashboard', false);
  }
});

/* ===== ROUTER DINÁMICO POR HASH (PÁGINAS Y MODALES) ===== */
function handleRouteHash(rawHash = null) {
  const hash = (rawHash !== null ? rawHash : (window.location.hash ? window.location.hash.substring(1) : '')).trim();
  if (!hash) return;

  // 1. Modales del sistema
  if (hash === 'modal-pago' || hash === 'pago' || hash === 'nuevo-pago') {
    openPagoModal();
    return;
  }
  if (hash === 'modal-invite' || hash === 'invite') {
    openInviteModal();
    return;
  }
  if (hash === 'modal-gym' || hash === 'nuevo-gym') {
    if (typeof openGymModal === 'function') openGymModal();
    return;
  }
  if (hash === 'modal-saas-pago') {
    if (typeof openSaasPagoModal === 'function') openSaasPagoModal();
    return;
  }
  if (hash === 'modal-catalogo-ejercicios' || hash === 'catalogo') {
    if (typeof openCatalogoModal === 'function') openCatalogoModal();
    return;
  }
  if (hash === 'modal-asignar-rutina') {
    if (typeof openAsignarRutinaModal === 'function') openAsignarRutinaModal();
    return;
  }
  if (hash === 'modal-rutina-programa') {
    if (typeof openProgramaModal === 'function') openProgramaModal();
    return;
  }

  // Cualquier otro modal con ID existente
  if (hash.startsWith('modal-')) {
    const modalEl = $('#' + hash);
    if (modalEl && modalEl.classList.contains('modal-backdrop')) {
      openModal(hash);
      return;
    }
  }

  // 2. Páginas principales
  if ($('#page-' + hash)) {
    setPage(hash, false);
    return;
  }
}

window.addEventListener('hashchange', () => {
  handleRouteHash();
});

/* ===== INIT ===== */
window.addEventListener('DOMContentLoaded', () => {
  const d = new Date();
  if ($('#current-date-txt')) {
    $('#current-date-txt').textContent = d.toLocaleDateString('es-AR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  loadGymData();
  if (['admin_general', 'dueno', 'coach'].includes(CURRENT_USER.role)) {
    loadCatalogoCache();
  }

  // Comprobar si hay hash inicial para restaurar la vista correcta (ej: #alumnos o #coach-ingresos)
  let initialPage = 'dashboard';
  const initialHash = window.location.hash ? window.location.hash.substring(1).trim() : '';

  if (initialHash) {
    if ($('#page-' + initialHash)) {
      initialPage = initialHash;
    } else if (initialHash === 'coach-ingresos') {
      if (CURRENT_USER.role === 'coach' && $('#page-coach-ingresos')) {
        initialPage = 'coach-ingresos';
      }
    }
  }

  setPage(initialPage, false);
  try {
    if (!initialHash || initialHash === initialPage) {
      history.replaceState({ type: 'page', page: initialPage }, '', '#' + initialPage);
    }
  } catch(e) {}

  // Si el hash inicial corresponde a un modal, abrirlo automáticamente
  if (initialHash && (initialHash.startsWith('modal-') || ['nuevo-pago'].includes(initialHash))) {
    setTimeout(() => {
      handleRouteHash(initialHash);
    }, 50);
  }

  if (CURRENT_USER.role !== 'alumno') {
    loadProfesOptions();
    loadAlumnosOptions();
  }
  let _resizeTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(_resizeTimer);
    _resizeTimer = setTimeout(() => {
      if (window.innerWidth > 1024) {
        closeMobileSidebar();
      }
      const pageRep = $('#page-reportes');
      if (pageRep && pageRep.style.display !== 'none') {
        loadReportes();
      }
    }, 200);
  });

  // Inicializar Tema (Modo Noche / Modo Día)
  initAppTheme();

  // Cerrar modal al hacer clic fuera del recuadro
  document.addEventListener('click', (e) => {
    if (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) {
      closeModal(e.target.id);
    }
  });
});

/* ===== THEME SWITCHER (MODO AIRBNB CLARO / NOCTURNO) ===== */

function initAppTheme() {
  const savedTheme = localStorage.getItem('gym_theme') || 'light';
  setAppTheme(savedTheme, false);
}

function setAppTheme(theme, save = true) {
  const isDark = theme === 'dark';
  if (isDark) {
    document.body.classList.add('dark-mode');
  } else {
    document.body.classList.remove('dark-mode');
  }

  if (save) {
    localStorage.setItem('gym_theme', theme);
  }

  const btnDark = $('#btn-theme-dark');
  const btnLight = $('#btn-theme-light');
  if (btnDark && btnLight) {
    btnDark.classList.toggle('active', isDark);
    btnLight.classList.toggle('active', !isDark);
  }

  // Si está en el dashboard o reportes, redibujar gráficos con la nueva paleta
  const currentPage = document.querySelector('.page.active')?.id;
  if (currentPage === 'page-dashboard' && typeof loadDashboard === 'function') {
    loadDashboard();
  } else if (currentPage === 'page-reportes' && typeof loadReportes === 'function') {
    loadReportes();
  }
}