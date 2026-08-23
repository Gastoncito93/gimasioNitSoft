<section id="page-reportes" style="display:none">
  <div class="card" style="margin-bottom:20px">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="card-title">📈 Reportes Financieros & Estadísticas Avanzadas</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Métricas de facturación semanal, mensual y distribución anual en tiempo real</div>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="loadReportes()">🔄 Actualizar</button>
    </div>

    <!-- 3 KPIs Resumen Financiero Superior -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;padding:0 18px 18px">
      <div style="background:rgba(59, 130, 246, 0.08);border:1px solid rgba(59, 130, 246, 0.25);border-radius:14px;padding:16px 20px;display:flex;flex-direction:column;gap:4px">
        <span style="font-size:11px;color:#60a5fa;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">📅 Esta Semana</span>
        <b style="font-size:24px;color:var(--t1);font-weight:800">$ <span id="rep-total-semana">0</span></b>
        <span style="font-size:12px;color:var(--t2)">Ingresos lunes a domingo</span>
      </div>
      <div style="background:rgba(16, 185, 129, 0.08);border:1px solid rgba(16, 185, 129, 0.25);border-radius:14px;padding:16px 20px;display:flex;flex-direction:column;gap:4px">
        <span style="font-size:11px;color:#34d399;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">📆 Este Mes</span>
        <b style="font-size:24px;color:#34d399;font-weight:800">$ <span id="rep-total-mes">0</span></b>
        <span style="font-size:12px;color:var(--t2)">Total cobrado a alumnos</span>
      </div>
      <div style="background:rgba(139, 92, 246, 0.08);border:1px solid rgba(139, 92, 246, 0.25);border-radius:14px;padding:16px 20px;display:flex;flex-direction:column;gap:4px">
        <span style="font-size:11px;color:#c084fc;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">🏆 Total Anual (<span id="rep-year-lbl">2026</span>)</span>
        <b style="font-size:24px;color:#c084fc;font-weight:800">$ <span id="rep-total-anual">0</span></b>
        <span style="font-size:12px;color:var(--t2)">Facturación acumulada anual</span>
      </div>
    </div>
  </div>

  <!-- Fila 1: Semanal + Mensual -->
  <div class="grid g2" style="margin-bottom:20px">
    <div class="card">
      <div class="card-header">
        <div class="card-title">📅 Ingresos de Esta Semana (Día x Día)</div>
      </div>
      <div style="height:270px;padding:10px 14px">
        <canvas id="chart-semanal-barras" style="width:100%;height:100%"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">📆 Facturación Mensual (Últimos 6 Meses)</div>
      </div>
      <div style="height:270px;padding:10px 14px">
        <canvas id="chart-mensual-lineas" style="width:100%;height:100%"></canvas>
      </div>
    </div>
  </div>

  <!-- Fila 2: Torta Anual con Leyenda y Barras de Porcentaje -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🥧 Distribución de Ingresos y Gastos por Concepto</div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:24px;align-items:center;padding:18px 24px">
      <div style="max-width:320px;margin:0 auto;height:270px;width:100%">
        <canvas id="chart-anual-torta" style="width:100%;height:100%"></canvas>
      </div>
      <div id="legend-anual-torta" style="display:flex;flex-direction:column;gap:10px">
        <!-- Render dinámico vía JS -->
      </div>
    </div>
  </div>
</section>