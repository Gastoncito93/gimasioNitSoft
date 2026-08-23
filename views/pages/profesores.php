<section id="page-profesores" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="card-title">🏋️ Coaches & Profesores del Gimnasio</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Liquidación de honorarios, esquemas de comisión, canon y asignación de socios</div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-secondary btn-sm" onclick="loadProfesores()">🔄 Actualizar</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openProfModal()" style="font-weight:800">➕ Nuevo Coach</button>
      </div>
    </div>

    <!-- Barra de Filtros SaaS Rediseñada -->
    <div class="filter-bar" style="display:grid;grid-template-columns:2fr 1fr;gap:12px;padding:14px 16px;background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:var(--r);margin-bottom:18px">
      <div>
        <input id="prof-filter-q" class="inp" placeholder="🔍 Buscar por nombre o teléfono de coach..." oninput="renderProfesoresTable()">
      </div>
      <div>
        <select id="prof-filter-estado" class="inp" onchange="renderProfesoresTable()">
          <option value="">⚡ Todos los Estados</option>
          <option value="al_dia">🟢 Al Día / Liquidado</option>
          <option value="deuda">⏱️ Saldo Pendiente</option>
        </select>
      </div>
    </div>

    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-prof">
        <thead>
          <tr>
            <th>Coach / Profesor</th>
            <th>Esquema de Ganancia</th>
            <th>Alumnos Asignados</th>
            <th>Recaudado Socios</th>
            <th>Ganancia Coach</th>
            <th>Liquidado Mes</th>
            <th>Estado Saldo</th>
            <th style="text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="profesores-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Profesores -->
    <div id="profesores-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 coaches</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="profesores-page-size" class="inp page-size-sel" onchange="changeProfPageSize(this.value)">
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