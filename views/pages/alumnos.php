<section id="page-alumnos" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="card-title">👥 Gestión de Alumnos / Socios</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Socios activos, cuotas, membresías y seguimiento deportivo</div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-secondary btn-sm" onclick="loadAlumnos()">🔄 Actualizar</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAluModal()" style="font-weight:800">➕ Nuevo Alumno</button>
      </div>
    </div>

    <!-- Barra de Filtros SaaS Rediseñada -->
    <div class="filter-bar" style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:12px;padding:14px 16px;background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:var(--r);margin-bottom:18px">
      <div>
        <input id="alu-q" class="inp" placeholder="🔍 Buscar por nombre, DNI, teléfono..." oninput="debounceLoadAlumnos()">
      </div>
      <div>
        <select id="alu-estado" class="inp" onchange="loadAlumnos()">
          <option value="">⚡ Todos los Estados</option>
          <option value="activo">🟢 Al Día (Activos)</option>
          <option value="vencido">🔴 Cuota Vencida</option>
          <option value="pausado">⏸️ Suspendidos / Pausados</option>
        </select>
      </div>
      <div>
        <select id="alu-plan" class="inp" onchange="loadAlumnos()">
          <option value="">📦 Todos los Planes</option>
          <option value="3x">🏋️ Plan 3x por Semana</option>
          <option value="full">⭐ Plan Full / Pase Libre</option>
          <option value="clase">🎟️ Pase por Clase</option>
        </select>
      </div>
      <div>
        <select id="alu-prof" class="inp" onchange="loadAlumnos()">
          <option value="">👤 Todos los Coaches</option>
        </select>
      </div>
    </div>

    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-alu">
        <thead>
          <tr>
            <th>Alumno / Socio</th>
            <th>Contacto</th>
            <th>Plan</th>
            <th>Actividades</th>
            <th>Cuota</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Vencimiento</th>
            <th>Estado</th>
            <th>Coach</th>
            <th style="text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="alumnos-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Alumnos -->
    <div id="alumnos-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 alumnos</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="alumnos-page-size" class="inp page-size-sel" onchange="changeAluPageSize(this.value)">
          <option value="10">10 por pág.</option>
          <option value="15" selected>15 por pág.</option>
          <option value="25">25 por pág.</option>
          <option value="50">50 por pág.</option>
          <option value="100">100 por pág.</option>
        </select>
      </div>
    </div>
  </div>
</section>