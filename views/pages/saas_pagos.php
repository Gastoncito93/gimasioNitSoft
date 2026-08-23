<section id="page-saas-pagos" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <div>
        <div class="card-title">💳 Historial de Pagos y Facturación SaaS</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Registro de abonos mensuales cobrados a los dueños de gimnasios</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" onclick="loadSaasPagos()">🔄 Actualizar</button>
        <button class="btn btn-success btn-sm" onclick="openSaasPagoModal()">💵 Registrar Pago de Abono</button>
      </div>
    </div>
    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-saas-pagos">
        <thead>
          <tr>
            <th>Fecha Pago</th>
            <th>Gimnasio / Sede</th>
            <th>Dueño</th>
            <th>Período</th>
            <th>Medio de Pago</th>
            <th style="text-align:right">Monto</th>
            <th>Comprobante / Obs</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="saas-pagos-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Pagos SaaS -->
    <div id="saas-pagos-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 pagos</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="saas-pagos-page-size" class="inp page-size-sel" onchange="changeSaasPagosPageSize(this.value)">
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