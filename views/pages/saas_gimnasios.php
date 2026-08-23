<section id="page-saas-gimnasios" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <div>
        <div class="card-title">🏢 Gimnasios & Sedes Suscritas (SaaS Total)</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Gestión multi-tenant de clientes y cobros de abono mensual</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" onclick="loadSaasGimnasios()">🔄 Actualizar</button>
        <button class="btn btn-primary btn-sm" onclick="openGymModal()">➕ Nuevo Gimnasio & Dueño</button>
      </div>
    </div>
    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-saas-gyms">
        <thead>
          <tr>
            <th>Gimnasio / Sede</th>
            <th>Dueño & Contacto</th>
            <th>Plan & Cuota SaaS</th>
            <th>Vencimiento & Estado</th>
            <th style="text-align:center">Socios & Coaches</th>
            <th style="text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="saas-gyms-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Sedes SaaS -->
    <div id="saas-gyms-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 sedes</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="saas-gyms-page-size" class="inp page-size-sel" onchange="changeSaasGymsPageSize(this.value)">
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