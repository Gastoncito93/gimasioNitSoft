<section id="page-nutricion" style="display:none">
  <div class="title-page">Plan Nutricional</div>
  <?php if ($isPlanPro): ?>
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <div>
        <div class="card-title">🥗 Gestión de Planes Nutricionales</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Asignación de dietas y pautas alimentarias personalizadas</div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="openNutriModal()">➕ Asignar Plan a Socio</button>
    </div>
    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-nutri">
        <thead><tr><th>Alumno</th><th>Objetivo</th><th>Calorías / Día</th><th>Coach</th><th>Fecha Asignación</th><th style="text-align:right">Acciones</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="nutri-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Nutrición -->
    <div id="nutri-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 planes</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="nutri-page-size" class="inp page-size-sel" onchange="changeNutriPageSize(this.value)">
          <option value="10">10 por pág.</option>
          <option value="15" selected>15 por pág.</option>
          <option value="25">25 por pág.</option>
          <option value="50">50 por pág.</option>
        </select>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:48px 24px">
    <div style="font-size:48px;margin-bottom:12px">🥗</div>
    <h3 style="font-size:20px;font-weight:800;color:var(--t1);margin-bottom:8px">Módulo de Nutrición Exclusivo de PLAN PRO</h3>
    <p style="color:var(--t2);max-width:500px;margin:0 auto 20px;font-size:13.5px">
      Habilitá el módulo de dietas, macros y recomendaciones nutricionales para tus alumnos activando el Plan PRO con la administración.
    </p>
    <a href="https://wa.me/5492657506957?text=Hola!%20Quiero%20activar%20el%20Plan%20PRO%20de%20Nutrición" target="_blank" class="btn btn-primary" style="display:inline-flex;font-weight:800">
      ⚡ Solicitar Activación de Plan PRO
    </a>
  </div>
  <?php endif; ?>
</section>