<!-- MOBILE SIDEBAR BACKDROP OVERLAY -->
<div id="sidebar-backdrop" class="sidebar-backdrop" onclick="closeMobileSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="brand-logo-container">
    <div class="brand-banner-box">
      <!-- Banner Tema Claro -->
      <img src="assets/img/banner-modo-claro.png?v=<?= filemtime(__DIR__ . '/../../assets/img/banner-modo-claro.png') ?>" alt="NitSoft" class="brand-banner-img brand-banner-light" id="app-brand-banner-light">
      <!-- Banner Tema Oscuro -->
      <img src="assets/img/banner-modo-oscuro.png?v=<?= filemtime(__DIR__ . '/../../assets/img/banner-modo-oscuro.png') ?>" alt="NitSoft" class="brand-banner-img brand-banner-dark" id="app-brand-banner-dark">
    </div>
  </div>

  <div class="user-badge">
    <div class="user-avatar" style="text-transform:uppercase">
      <?= substr($userDisplayName, 0, 1) ?>
    </div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($userDisplayName) ?></div>
      <div class="user-role-tag" id="user-role-text" style="color:var(--pri)">
        <?= $userRole === 'admin_general' ? '👑 SuperAdmin' : ($userRole === 'dueno' ? '🏢 Dueño Sede' : ($userRole === 'coach' ? '🏋️ Entrenador' : '👤 Alumno')) ?>
      </div>
    </div>
  </div>

  <nav class="nav">
    <a href="#" data-page="dashboard" class="active"><span class="nav-icon">📊</span><span style="flex:1">Dashboard</span></a>

    <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
      <a href="#" data-page="saas-gimnasios"><span class="nav-icon">🏢</span><span style="flex:1">Gimnasios & Sedes</span></a>
      <a href="#" data-page="saas-pagos"><span class="nav-icon">💳</span><span style="flex:1">Abonos & Pagos SaaS</span></a>
    <?php endif; ?>

    <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?>
      <a href="#" data-page="alumnos"><span class="nav-icon">👥</span><span style="flex:1">Alumnos / Socios</span></a>
      <a href="#" data-page="profesores"><span class="nav-icon">🏋️</span><span style="flex:1">Coaches & Profesores</span></a>
      <a href="#" data-page="rutinas"><span class="nav-icon">📋</span><span style="flex:1">Programas & Rutinas</span></a>
      <a href="#" data-page="nutricion"><span class="nav-icon">🥗</span><span style="flex:1">Plan Nutricional</span><?php if ($isPlanPro): ?><span class="badge b-purple" style="font-size:9px;font-weight:800;padding:2px 6px">PRO ACTIVO</span><?php else: ?><span class="badge" style="font-size:9.5px;font-weight:800;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:var(--t1);border:none;padding:2px 7px;border-radius:6px;box-shadow:0 2px 6px rgba(236,72,153,0.35);letter-spacing:0.3px">PRO</span><?php endif; ?></a>
      <a href="#" data-page="pagos"><span class="nav-icon">💵</span><span style="flex:1">Caja & Cobranzas</span></a>
      <a href="#" data-page="reportes"><span class="nav-icon">📈</span><span style="flex:1">Reportes Financieros</span></a>
    <?php endif; ?>

    <?php if (hasRole(ROLE_COACH)): ?>
      <a href="#" data-page="coach-alumnos"><span class="nav-icon">👥</span><span style="flex:1">Mis Alumnos a Cargo</span></a>
      <a href="#" data-page="rutinas"><span class="nav-icon">📋</span><span style="flex:1">Rutinas & Ejercicios</span></a>
      <a href="#" data-page="nutricion"><span class="nav-icon">🥗</span><span style="flex:1">Plan Nutricional</span><?php if ($isPlanPro): ?><span class="badge b-purple" style="font-size:9px;font-weight:800;padding:2px 6px">PRO ACTIVO</span><?php else: ?><span class="badge" style="font-size:9.5px;font-weight:800;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:var(--t1);border:none;padding:2px 7px;border-radius:6px;box-shadow:0 2px 6px rgba(236,72,153,0.35);letter-spacing:0.3px">PRO</span><?php endif; ?></a>
      <a href="#" data-page="coach-ingresos"><span class="nav-icon">💰</span><span style="flex:1">Mis Ganancias & Cobros</span></a>
    <?php endif; ?>

    <?php if (hasRole(ROLE_ALUMNO)): ?>
      <a href="#" data-page="mi-membresia"><span class="nav-icon">🪪</span><span style="flex:1">Mi Membresía Digital</span></a>
      <a href="#" data-page="mi-rutina"><span class="nav-icon">🏋️</span><span style="flex:1">Mi Plan de Entrenamiento</span></a>
      <?php if ($isPlanPro): ?>
        <a href="#" data-page="mi-nutricion"><span class="nav-icon">🥗</span><span style="flex:1">Plan Nutricional</span><span class="badge b-purple" style="font-size:9px;font-weight:800;padding:2px 6px">PRO ACTIVO</span></a>
      <?php else: ?>
        <a href="#" data-page="mi-nutricion" style="opacity:0.65"><span class="nav-icon">🥗</span><span style="flex:1">Plan Nutricional</span><span class="badge" style="font-size:9px;font-weight:800;background:#475569;color:#94a3b8;border:none;padding:2px 6px;border-radius:4px">BLOQUEADO</span></a>
      <?php endif; ?>
      <a href="#" data-page="mis-pagos"><span class="nav-icon">🧾</span><span style="flex:1">Mis Pagos & Cuotas</span></a>
    <?php endif; ?>

    <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?>
      <a href="#" data-page="config"><span class="nav-icon">⚙️</span><span style="flex:1">Configuración Sede</span></a>
    <?php endif; ?>

    <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
      <a href="#" data-page="usuarios"><span class="nav-icon">🔑</span><span style="flex:1">Usuarios & Claves</span></a>
    <?php endif; ?>
  </nav>

  <!-- SECCIÓN DE CONFIGURACIONES & PREFERENCIAS -->
  <div class="sidebar-footer" style="padding:14px;border-top:1px solid var(--border);margin-top:auto">
    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:var(--t-mut);margin-bottom:8px;display:flex;align-items:center;gap:6px">
      <span>⚙️ Configuración & Modo</span>
    </div>

    <!-- TEMA AIRBNB / MODO DÍA & NOCHE -->
    <div class="theme-switch-group" style="display:flex;background:#f7f7f7;border:1px solid var(--border);border-radius:10px;padding:3px;margin-bottom:10px;gap:2px">
      <button type="button" id="btn-theme-light" onclick="setAppTheme('light')" 
              class="theme-btn active" 
              style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:7px 8px;border-radius:8px;border:none;font-size:11.5px;font-weight:700;cursor:pointer;transition:all 0.2s ease"
              title="Tema Claro Airbnb">
        <span>☀️</span> <span>Claro</span>
      </button>
      <button type="button" id="btn-theme-dark" onclick="setAppTheme('dark')" 
              class="theme-btn" 
              style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:7px 8px;border-radius:8px;border:none;font-size:11.5px;font-weight:700;cursor:pointer;transition:all 0.2s ease"
              title="Tema Nocturno Airbnb">
        <span>🌙</span> <span>Noche</span>
      </button>
    </div>

    <!-- CERRAR SESIÓN -->
    <a href="logout.php" class="btn-sidebar-logout" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#f87171;text-decoration:none;font-size:13px;font-weight:700;transition:all 0.2s ease">
      <span style="font-size:16px">🚪</span>
      <span style="flex:1">Cerrar Sesión</span>
      <span style="font-size:11px;opacity:0.7">→</span>
    </a>
  </div>
</aside>