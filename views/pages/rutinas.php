<section id="page-rutinas" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="card-title">📋 Programas de Entrenamiento & Biblioteca de Ejercicios</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Estructura rutinas por días, bloques, series y catálogo de ejercicios</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-secondary btn-sm" onclick="openCatalogoModal()">📖 Catálogo de Ejercicios</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openProgramaModal()" style="font-weight:800">➕ Crear Plantilla / Programa</button>
      </div>
    </div>

    <!-- Pestañas Rutinas -->
    <div style="display:flex;gap:8px;border-bottom:1px solid var(--border);padding:0 18px 12px;margin-bottom:16px">
      <button type="button" id="btn-tab-plantillas" class="btn btn-sm btn-primary tab-rutina-btn" onclick="switchRutinasTab('plantillas')">🏆 Plantillas Maestras</button>
      <button type="button" id="btn-tab-asignadas" class="btn btn-sm btn-secondary tab-rutina-btn" onclick="switchRutinasTab('asignadas')">👤 Rutinas Asignadas a Socios</button>
    </div>

    <div style="padding:0 18px 18px">
      <div id="rutinas-grid-container" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:18px">
        <!-- Render dinámico vía JS -->
      </div>
    </div>

    <!-- Barra de Paginación Rutinas -->
    <div id="rutinas-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 programas</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="rutinas-page-size" class="inp page-size-sel" onchange="changeRutinasPageSize(this.value)">
          <option value="6">6 por pág.</option>
          <option value="9">9 por pág.</option>
          <option value="12" selected>12 por pág.</option>
          <option value="24">24 por pág.</option>
        </select>
      </div>
    </div>
  </div>
</section>