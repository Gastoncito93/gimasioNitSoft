<!-- ==================== VIEW: DASHBOARD (4 ROLES) ==================== -->
<section id="page-dashboard">
  <div class="title-page">Dashboard de Control</div>

  <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?>
    <!-- 1. DASHBOARD DEL ADMIN GENERAL (SAAS) Y DUEÑO -->
    <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
    <!-- SUPERADMIN OVERVIEW PANEL (SAAS GLOBAL) -->
    <div style="background:linear-gradient(135deg, rgba(30,27,75,0.7), rgba(15,23,42,0.8));border:1px solid rgba(139,92,246,0.35);border-radius:16px;padding:18px 24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
          <span class="badge b-purple" style="font-size:11px;font-weight:800">👑 PLATAFORMA SAAS GYM PRO</span>
          <span class="badge b-ok" style="font-size:11px;font-weight:800">GLOBAL MULTI-TENANT</span>
        </div>
        <h2 style="font-size:22px;font-weight:900;color:var(--t1);margin:0">Panel de Control SuperAdmin</h2>
        <p style="color:var(--t2);font-size:13px;margin:4px 0 0 0">Administrá las sedes, gimnasios suscritos, facturación SaaS y accesos directos.</p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-primary btn-sm" onclick="openGymModal()">➕ Crear Gimnasio & Dueño</button>
        <button class="btn btn-success btn-sm" onclick="openSaasPagoModal()">💵 Registrar Pago SaaS</button>
        <button class="btn btn-secondary btn-sm" onclick="setPage('saas-gimnasios')">⚙️ Configurar Sedes</button>
      </div>
    </div>

    <!-- GRID DE ESCRITORIOS DE GIMNASIOS -->
    <div id="superadmin-gyms-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(290px, 1fr));gap:16px;align-items:stretch;margin-bottom:20px">
      <!-- Renderizado dinámico vía JS -->
    </div>
    <?php endif; ?>

    <div class="grid g4">
      <div class="stat-card">
        <div class="stat-label" id="lbl-kpi-1">Total Alumnos</div>
        <div class="stat-value" id="kpi-alumnos">-</div>
        <div class="stat-sub" id="sub-kpi-1"><span id="kpi-activos" class="badge b-ok">- al día</span> <span id="kpi-vencidos" class="badge b-bad">- con deuda</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" id="lbl-kpi-2">Coaches & Profes</div>
        <div class="stat-value" id="kpi-profes">-</div>
        <div class="stat-sub" id="sub-kpi-2"><span id="kpi-profes-sub" class="badge b-purple">Equipo Activo</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" id="lbl-kpi-3">Recaudación de Hoy</div>
        <div class="stat-value" style="color:var(--ok)">$ <span id="rec-hoy">-</span></div>
        <div class="stat-sub" id="sub-kpi-3">Semana: $ <b id="rec-semana">-</b></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" id="lbl-kpi-4">Ingresos del Mes</div>
        <div class="stat-value" style="color:#60a5fa">$ <span id="kpi-mes">-</span></div>
        <div class="stat-sub" id="sub-kpi-4">Mes corriente</div>
      </div>
    </div>

    <div class="grid g2" style="margin-top:16px">
      <div class="card" style="display:flex;flex-direction:column;gap:14px">
        <div class="card-header" style="justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:10px">
          <div>
            <div class="card-title" id="dash-chart-title">🎯 Estado de Cobranzas & Equipo</div>
            <div style="font-size:12px;color:var(--t2);margin-top:2px" id="dash-chart-subtitle">Resumen visual de socios y coaches</div>
          </div>
        </div>

        <div id="dash-charts-container" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:stretch">
          <div style="background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:6px">
              <span style="font-weight:800;font-size:13px;color:var(--t1)">👥 Socios / Alumnos</span>
              <span id="dash-alu-tot-badge" class="badge b-info">0 Alumnos</span>
            </div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:14px">
              <canvas id="chart-alumnos" class="chart" style="width:120px;height:120px;max-width:120px;max-height:120px"></canvas>
              <div id="dash-alu-summary" style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--t1)">
                <div style="display:flex;align-items:center;gap:6px">
                  <span style="width:9px;height:9px;border-radius:50%;background:#10b981;display:inline-block"></span>
                  <span>Pagaron: <b id="dash-alu-pagaron" style="color:#10b981;font-size:13px">0</b></span>
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                  <span style="width:9px;height:9px;border-radius:50%;background:#ef4444;display:inline-block"></span>
                  <span>Deben: <b id="dash-alu-deben" style="color:#ef4444;font-size:13px">0</b></span>
                </div>
              </div>
            </div>
            <button class="btn btn-xs btn-secondary" onclick="setPage('alumnos')" style="width:100%;justify-content:center;font-weight:700;font-size:11.5px">Ver Alumnos →</button>
          </div>

          <div style="background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:6px">
              <span style="font-weight:800;font-size:13px;color:var(--t1)">🏋️‍♂️ Coaches / Profes</span>
              <span id="dash-prof-tot-badge" class="badge b-purple">0 Profes</span>
            </div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:14px">
              <canvas id="chart-profes" class="chart" style="width:120px;height:120px;max-width:120px;max-height:120px"></canvas>
              <div id="dash-prof-summary" style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--t1)">
                <div style="display:flex;align-items:center;gap:6px">
                  <span style="width:9px;height:9px;border-radius:50%;background:#a855f7;display:inline-block"></span>
                  <span>Liquidados: <b id="dash-prof-liquidados" style="color:#a855f7;font-size:13px">0</b></span>
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                  <span style="width:9px;height:9px;border-radius:50%;background:#f59e0b;display:inline-block"></span>
                  <span>Pendientes: <b id="dash-prof-pendientes" style="color:#f59e0b;font-size:13px">0</b></span>
                </div>
              </div>
            </div>
            <button class="btn btn-xs btn-secondary" onclick="setPage('profesores')" style="width:100%;justify-content:center;font-weight:700;font-size:11.5px">Ver Coaches →</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">⚠️ Próximos a Vencer (5 Días)</div></div>
        <div class="tbl-wrap desk-table-container" style="max-height:240px; overflow-y:auto;">
          <table class="tbl" id="tbl-prox">
            <thead><tr><th>Alumno</th><th>Plan</th><th>Vence</th><th>Acción</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="dash-prox-cards" class="mobile-cards-container"></div>
      </div>
    </div>

  <?php elseif (hasRole(ROLE_COACH)): ?>
    <!-- 2. DASHBOARD DEL COACH -->
    <div style="background:linear-gradient(135deg, rgba(59,130,246,0.12), rgba(139,92,246,0.15));border:1px solid rgba(59,130,246,0.3);border-radius:16px;padding:18px 24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
          <span class="badge b-ok" style="font-size:11px;font-weight:800">🏋️ COACH ACTIVO</span>
          <span class="badge b-purple" style="font-size:11px;font-weight:800">🏢 SEDE: <?= htmlspecialchars($gimnasioNombre) ?></span>
        </div>
        <h2 style="font-size:22px;font-weight:900;color:var(--t1);margin:0">¡Hola, <?= htmlspecialchars($userDisplayName) ?>! 👋</h2>
        <p style="color:var(--t2);font-size:13px;margin:4px 0 0 0">Pertenecés al equipo de entrenadores de <b style="color:#60a5fa"><?= htmlspecialchars($gimnasioNombre) ?></b>.</p>
      </div>
      <div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.05);border:1px solid var(--border);padding:10px 16px;border-radius:12px">
        <span style="font-size:24px">🏢</span>
        <div>
          <div style="font-size:10px;color:var(--t-mut);text-transform:uppercase;font-weight:700">Tu Gimnasio</div>
          <div style="font-size:15px;font-weight:900;color:#60a5fa"><?= htmlspecialchars($gimnasioNombre) ?></div>
        </div>
      </div>
    </div>

    <div class="grid g4" style="margin-bottom:20px;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px">
      <div class="stat-card" style="border-left:4px solid var(--ok)">
        <div class="stat-label">💵 Liquidado por la Sede</div>
        <div class="stat-value" style="color:var(--ok)">$ <span id="coach-kpi-liquidado">0.00</span></div>
        <div class="stat-sub"><span id="coach-kpi-liquidado-sub" class="badge b-ok">Abonado este mes</span></div>
      </div>
      <div class="stat-card" style="border-left:4px solid #a855f7">
        <div class="stat-label">💰 Comisión / Honorarios</div>
        <div class="stat-value" style="color:#c084fc">$ <span id="coach-kpi-ganancia">0.00</span></div>
        <div class="stat-sub"><span id="coach-kpi-ganancia-sub" style="color:var(--t2)">Esquema activo</span></div>
      </div>
      <div class="stat-card" style="border-left:4px solid #60a5fa">
        <div class="stat-label">👥 Recaudado de Mis Alumnos</div>
        <div class="stat-value" style="color:#60a5fa">$ <span id="coach-kpi-recaudado">0.00</span></div>
        <div class="stat-sub"><span id="coach-kpi-asist" class="badge b-info">0 abonaron</span></div>
      </div>
      <div class="stat-card" style="border-left:4px solid #f59e0b">
        <div class="stat-label">🏋️‍♂️ Mis Alumnos a Cargo</div>
        <div class="stat-value" id="coach-kpi-alumnos">0</div>
        <div class="stat-sub"><span id="coach-kpi-activos" class="badge b-ok">- activos</span> <span id="coach-kpi-vencidos" class="badge b-bad">- vencidos</span></div>
      </div>
    </div>

    <div class="grid g2">
      <div class="card">
        <div class="card-header">
          <div class="card-title">⚡ Acciones Rápidas del Coach</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <button class="btn btn-success" style="font-weight:700;justify-content:center;padding:12px 10px" onclick="openPagoModal('alumno')">💵 Cobrar Cuota</button>
          <button class="btn btn-primary" style="font-weight:700;justify-content:center;padding:12px 10px" onclick="openAsignarRutinaModal()">📋 Cargar Rutina</button>
          <button class="btn btn-warn" onclick="setPage('nutricion')" style="font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;padding:12px 10px"><span>🥗 Plan Nutricional</span><span class="badge" style="background:#000;color:var(--t1);font-size:9px"><?= $isPlanPro ? 'PRO ACTIVO' : 'PRO' ?></span></button>
          <button class="btn btn-info" onclick="setPage('coach-alumnos')" style="font-weight:700;justify-content:center;padding:12px 10px">👥 Mis Alumnos</button>
        </div>
      </div>

      <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <div class="card-title">💵 Liquidaciones Recibidas del Dueño</div>
          <button class="btn btn-xs btn-primary" onclick="setPage('coach-ingresos')">Ver Todo →</button>
        </div>
        <div class="tbl-wrap desk-table-container" style="max-height:260px; overflow-y:auto;">
          <table class="tbl" id="tbl-coach-dash-liq">
            <thead><tr><th>Fecha</th><th>Medio</th><th>Detalle</th><th style="text-align:right">Monto</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="coach-dash-liq-cards" class="mobile-cards-container"></div>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><div class="card-title">⚠️ Vencimientos Próximos de Mis Alumnos</div></div>
      <div class="tbl-wrap desk-table-container" style="max-height:240px; overflow-y:auto;">
        <table class="tbl" id="coach-tbl-prox">
          <thead><tr><th>Alumno</th><th>Plan</th><th>Vence</th><th>WhatsApp</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div id="coach-dash-prox-cards" class="mobile-cards-container"></div>
    </div>

  <?php elseif (hasRole(ROLE_ALUMNO)): ?>
    <!-- 3. DASHBOARD DEL ALUMNO -->
    <div id="alu-portal-dashboard"></div>
  <?php endif; ?>
</section>