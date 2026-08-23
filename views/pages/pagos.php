<section id="page-pagos" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="card-title">💵 Caja & Historial de Movimientos</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Ingresos por cuotas de socios, liquidaciones a coaches y pagos de canon</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-secondary btn-sm" onclick="loadPagos()">🔄 Actualizar</button>
        <button type="button" class="btn btn-success btn-sm" onclick="openPagoModal('alumno')" style="font-weight:800">➕ Cobro a Alumno</button>
        <button type="button" class="btn btn-purple btn-sm" onclick="openPagoModal('profesor')" style="font-weight:800">💸 Liquidar a Coach</button>
      </div>
    </div>

    <!-- Mini KPIs de Caja en tiempo real -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;padding:0 18px 16px">
      <div style="background:rgba(16, 185, 129, 0.08);border:1px solid rgba(16, 185, 129, 0.25);border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;gap:3px">
        <span style="font-size:11px;color:#34d399;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">Total Ingresos</span>
        <b id="kpi-pago-ingresos" style="font-size:20px;color:#34d399;font-weight:800">$ 0</b>
        <span id="kpi-pago-ingresos-sub" style="font-size:11px;color:var(--t2)">0 cobros</span>
      </div>
      <div style="background:rgba(139, 92, 246, 0.08);border:1px solid rgba(139, 92, 246, 0.25);border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;gap:3px">
        <span style="font-size:11px;color:#c084fc;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">Liquidado a Coaches</span>
        <b id="kpi-pago-egresos" style="font-size:20px;color:#c084fc;font-weight:800">$ 0</b>
        <span id="kpi-pago-egresos-sub" style="font-size:11px;color:var(--t2)">0 liquidaciones</span>
      </div>
      <div style="background:rgba(59, 130, 246, 0.08);border:1px solid rgba(59, 130, 246, 0.25);border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;gap:3px">
        <span style="font-size:11px;color:#60a5fa;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">Balance Neto</span>
        <b id="kpi-pago-neto" style="font-size:20px;color:#60a5fa;font-weight:800">$ 0</b>
        <span id="kpi-pago-neto-sub" style="font-size:11px;color:var(--t2)">Caja del período</span>
      </div>
      <div style="background:rgba(255, 255, 255, 0.03);border:1px solid var(--border);border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;gap:3px">
        <span style="font-size:11px;color:var(--t-mut);font-weight:800;text-transform:uppercase;letter-spacing:0.5px">Movimientos Registrados</span>
        <b id="kpi-pago-total-movs" style="font-size:20px;color:var(--t1);font-weight:800">0</b>
        <span style="font-size:11px;color:var(--t2)">Filtrados actualmente</span>
      </div>
    </div>

    <!-- Barra de Filtros SaaS Ordenada y Alineada -->
    <div style="padding:0 18px 16px">
      <!-- Pestañas rápidas por Período / Mes -->
      <div id="pagos-quick-meses" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
        <button type="button" class="btn btn-xs btn-primary btn-pago-mes active" onclick="setPagoMesFilter('')">📅 Todos los Meses</button>
        <button type="button" class="btn btn-xs btn-secondary btn-pago-mes" onclick="setPagoMesFilter('2026-08')">⭐ Agosto 2026 (Este Mes)</button>
        <button type="button" class="btn btn-xs btn-secondary btn-pago-mes" onclick="setPagoMesFilter('2026-07')">📅 Julio 2026</button>
        <button type="button" class="btn btn-xs btn-secondary btn-pago-mes" onclick="setPagoMesFilter('2025-12')">📅 Diciembre 2025</button>
        <button type="button" class="btn btn-xs btn-secondary btn-pago-mes" onclick="setPagoMesFilter('2025-11')">📅 Noviembre 2025</button>
        <button type="button" class="btn btn-xs btn-secondary btn-pago-mes" onclick="setPagoMesFilter('2026')">📊 Todo el 2026</button>
      </div>

      <div class="filter-bar" style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1.2fr auto;gap:10px;align-items:center;padding:12px 16px;background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:var(--r)">
        <div>
          <input id="pagos-filter-q" class="inp" placeholder="🔍 Buscar por nombre, concepto o comprobante..." oninput="debounceLoadPagos()">
        </div>
        <div>
          <select id="pagos-filter-tipo" class="inp" onchange="loadPagos()">
            <option value="">🏷️ Todos los Tipos</option>
            <option value="alumno">👤 Cobros a Alumnos</option>
            <option value="profesor">💸 Liquidaciones a Coaches</option>
          </select>
        </div>
        <div>
          <select id="pagos-filter-medio" class="inp" onchange="loadPagos()">
            <option value="">💳 Todos los Medios</option>
            <option value="efectivo">💵 Efectivo</option>
            <option value="transferencia">📲 Transferencia / QR</option>
            <option value="debito">💳 Débito</option>
            <option value="credito">💳 Crédito</option>
          </select>
        </div>
        <div>
          <select id="pagos-filter-mes" class="inp" onchange="onPagoMesSelectChange(this.value)" style="height:38px;font-weight:700">
            <option value="">📅 Todos los Meses</option>
            <option value="2026-08">📅 Agosto 2026 (Actual)</option>
            <option value="2026-07">📅 Julio 2026</option>
            <option value="2026-06">📅 Junio 2026</option>
            <option value="2026-05">📅 Mayo 2026</option>
            <option value="2026-04">📅 Abril 2026</option>
            <option value="2026-03">📅 Marzo 2026</option>
            <option value="2026-02">📅 Febrero 2026</option>
            <option value="2026-01">📅 Enero 2026</option>
            <option value="2025-12">📅 Diciembre 2025</option>
            <option value="2025-11">📅 Noviembre 2025</option>
            <option value="2025-10">📅 Octubre 2025</option>
            <option value="2026">📊 Todo el Año 2026</option>
            <option value="2025">📊 Todo el Año 2025</option>
          </select>
        </div>
        <div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="resetPagosFiltros()" title="Limpiar todos los filtros" style="height:38px;padding:0 12px;white-space:nowrap">
            🧹 Limpiar
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla de Pagos Desktop -->
    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-pagos">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo Movimiento</th>
            <th>Titular / Beneficiario</th>
            <th>Concepto / Plan</th>
            <th>Medio de Pago</th>
            <th style="text-align:right">Monto</th>
            <th style="text-align:right">Comprobante / Detalle</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <!-- Mobile Cards Container -->
    <div id="pagos-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Inteligente -->
    <div id="pagos-pagination-bar" class="pagination-wrap">
      <div id="pagos-pagination-info" class="pagination-info">Mostrando 0 de 0 movimientos</div>
      <div id="pagos-pagination-btns" class="pagination-controls">
        <!-- Render dinámico de botones de página -->
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="pagos-page-size" class="inp page-size-sel" onchange="changePagosPageSize(this.value)">
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