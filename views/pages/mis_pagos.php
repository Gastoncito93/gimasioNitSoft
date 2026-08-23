<section id="page-mis-pagos" style="display:none">
  <div class="title-page">Mis Pagos & Historial de Cuotas</div>

  <!-- TARJETAS DE ESTADÍSTICAS DEL ALUMNO (PAGOS) -->
  <div class="grid g4" style="margin-bottom:20px">
    <div class="stat-card" style="border-left:4px solid var(--ok)">
      <div class="stat-label">💳 Estado de Membresía</div>
      <div class="stat-value" id="alu-pagos-estado-val" style="font-size:20px">-</div>
      <div class="stat-sub" id="alu-pagos-estado-sub">-</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #60a5fa">
      <div class="stat-label">🗓️ Días Restantes</div>
      <div class="stat-value" id="alu-pagos-dias-val" style="font-size:20px">-</div>
      <div class="stat-sub" id="alu-pagos-dias-sub">-</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #f59e0b">
      <div class="stat-label">📅 Próximo Vencimiento</div>
      <div class="stat-value" id="alu-pagos-venc-val" style="font-size:20px;color:#f59e0b">-</div>
      <div class="stat-sub">Fecha límite de renovación</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #a855f7">
      <div class="stat-label">💰 Total Histórico Abonado</div>
      <div class="stat-value" id="alu-pagos-total-val" style="font-size:20px;color:#a855f7">$ 0</div>
      <div class="stat-sub" id="alu-pagos-count-sub">0 cuotas</div>
    </div>
  </div>

  <!-- TABLA DE PAGOS Y RECIBOS -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div class="card-title">🧾 Mis Pagos Registrados</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Comprobantes y recibos oficiales emitidos por el gimnasio</div>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="loadAlumnoPortal()">🔄 Actualizar Historial</button>
    </div>

    <div class="tbl-wrap desk-table-container">
      <table class="tbl" id="tbl-mis-pagos">
        <thead>
          <tr>
            <th>Fecha de Pago</th>
            <th>Plan / Concepto</th>
            <th>Medio de Pago</th>
            <th style="text-align:right">Monto Abonado</th>
            <th>Observaciones</th>
            <th style="text-align:right">Comprobante</th>
          </tr>
        </thead>
        <tbody>
          <!-- Renderizado dinámico vía JS -->
        </tbody>
      </table>
    </div>

    <!-- CARDS MÓVILES PARA CELULARES -->
    <div id="mis-pagos-mobile-cards" class="mobile-cards-container"></div>
  </div>
</section>