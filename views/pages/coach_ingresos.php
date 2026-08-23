<section id="page-coach-ingresos" style="display:none">
  <!-- 4 TARJETAS KPI SUPERIORES PARA EL COACH -->
  <div class="grid g4" style="margin-bottom:20px;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px">
    <div class="stat-card" style="border-left:4px solid var(--ok)">
      <div class="stat-label">💵 Liquidado por la Sede</div>
      <div class="stat-value" style="color:var(--ok)">$ <span id="coach-ing-kpi-liquidado">0.00</span></div>
      <div class="stat-sub"><span class="badge b-ok">Abonado este mes</span></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #a855f7">
      <div class="stat-label">💰 Comisión / Honorarios</div>
      <div class="stat-value" style="color:#c084fc">$ <span id="coach-ing-kpi-ganancia">0.00</span></div>
      <div class="stat-sub"><span style="color:var(--t2)">Esquema activo</span></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #60a5fa">
      <div class="stat-label">👥 Recaudado de Mis Alumnos</div>
      <div class="stat-value" style="color:#60a5fa">$ <span id="coach-ing-kpi-recaudado">0.00</span></div>
      <div class="stat-sub"><span id="coach-ing-kpi-asist" class="badge b-info">0 abonaron</span></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f59e0b">
      <div class="stat-label">🏋️‍♂️ Mis Alumnos a Cargo</div>
      <div class="stat-value" id="coach-ing-kpi-alumnos">0</div>
      <div class="stat-sub"><span class="badge b-ok">Asignados</span></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <div>
        <div class="card-title">💰 Mis Ganancias, Liquidaciones & Cobros</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Detalle completo de tus honorarios, liquidaciones recibidas y cuotas abonadas</div>
      </div>
      <button class="btn btn-secondary btn-sm" onclick="loadCoachIngresos()">🔄 Actualizar</button>
    </div>

    <!-- SUB-PESTAÑAS DEL COACH -->
    <div style="display:flex;gap:8px;border-bottom:1px solid var(--border);padding:0 18px 12px;margin-bottom:16px;flex-wrap:wrap">
      <button class="btn btn-sm btn-primary coach-subtab-btn" onclick="switchCoachSubTab('liq', this)">💵 1. Liquidaciones Recibidas</button>
      <button class="btn btn-sm btn-secondary coach-subtab-btn" onclick="switchCoachSubTab('cobros', this)">👥 2. Cobros a Mis Alumnos</button>
      <button class="btn btn-sm btn-secondary coach-subtab-btn" onclick="switchCoachSubTab('stats', this)">📅 3. Estadísticas de Días</button>
    </div>

    <div style="padding:0 18px 18px">
      <!-- 1. TAB: LIQUIDACIONES RECIBIDAS DEL DUEÑO -->
      <div id="coach-subpane-liq" class="coach-subpane">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
          <h4 style="font-size:15px;font-weight:800;color:var(--t1);margin:0">Liquidaciones y Honorarios Percibidos de la Sede</h4>
        </div>
        <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
          <table class="tbl" id="tbl-coach-liq-pagos">
            <thead><tr><th>Fecha</th><th>Medio de Pago</th><th>Concepto / Observaciones</th><th style="text-align:right">Monto Liquidado</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="coach-liq-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>
        <div id="coach-liq-pagination-bar" class="pagination-wrap">
          <div class="pagination-info">Mostrando 0 de 0 liquidaciones</div>
          <div class="pagination-controls"></div>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
            <select id="coach-liq-page-size" class="inp page-size-sel" onchange="changeCoachLiqPageSize(this.value)">
              <option value="10">10 por pág.</option>
              <option value="15" selected>15 por pág.</option>
              <option value="25">25 por pág.</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 2. TAB: COBROS A MIS ALUMNOS -->
      <div id="coach-subpane-cobros" class="coach-subpane" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
          <h4 style="font-size:15px;font-weight:800;color:var(--t1);margin:0">Cuotas Recaudadas de Mis Socios a Cargo</h4>
          <button class="btn btn-xs btn-primary" onclick="openPagoModal('alumno')" style="font-weight:700">+ Cobrar Cuota</button>
        </div>
        <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
          <table class="tbl" id="tbl-coach-cobros-pagos">
            <thead><tr><th>Fecha</th><th>Alumno</th><th>Plan</th><th>Medio</th><th style="text-align:right">Monto</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="coach-cobros-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>
        <div id="coach-cobros-pagination-bar" class="pagination-wrap">
          <div class="pagination-info">Mostrando 0 de 0 cobros</div>
          <div class="pagination-controls"></div>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
            <select id="coach-cobros-page-size" class="inp page-size-sel" onchange="changeCoachCobrosPageSize(this.value)">
              <option value="10">10 por pág.</option>
              <option value="15" selected>15 por pág.</option>
              <option value="25">25 por pág.</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 3. TAB: ESTADÍSTICAS -->
      <div id="coach-subpane-stats" class="coach-subpane" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-top:10px">
          <div style="background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:14px;padding:18px;text-align:center">
            <div style="font-size:32px;margin-bottom:8px">🏋️‍♂️</div>
            <div style="font-size:24px;font-weight:900;color:#38bdf8" id="coach-stat-dias-activos">0</div>
            <div style="font-size:13px;color:var(--t2);margin-top:4px">Días Activos este Mes</div>
          </div>
          <div style="background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:14px;padding:18px;text-align:center">
            <div style="font-size:32px;margin-bottom:8px">👥</div>
            <div style="font-size:24px;font-weight:900;color:#34d399" id="coach-stat-asistencias">0</div>
            <div style="font-size:13px;color:var(--t2);margin-top:4px">Check-ins de Mis Alumnos</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>