<div class="topbar">
  <div class="mobile-only" style="display:flex;align-items:center;gap:10px;width:100%;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:8px">
      <button id="btn-mobile-menu" class="btn btn-secondary btn-sm" onclick="toggleMobileSidebar()" aria-label="Abrir Menú" style="font-size:16px;padding:7px 12px;font-weight:800;gap:6px">
        ☰ <span>Menú</span>
      </button>
    </div>
    <span class="badge b-purple" style="font-size:11px;font-weight:800">
      🏢 <?= htmlspecialchars($gimnasioNombre) ?>
    </span>
  </div>

  <div class="topbar-title">
    <span>📍 Sistema de Gimnasio</span>
    <span style="color:var(--t-mut)">•</span>
    <span style="color:var(--pri);font-weight:700">
      <?= $userRole === 'admin_general' ? '👑 Admin General' : ($userRole === 'dueno' ? '🏢 Dueño de Sede' : ($userRole === 'coach' ? '🏋️ Coach / Entrenador' : '👤 Portal Alumno')) ?>
    </span>
    <span style="color:var(--t-mut)">•</span>
    <span class="badge b-purple" style="font-size:11.5px;font-weight:800;padding:3px 10px;display:inline-flex;align-items:center;gap:6px">
      🏢 <?= htmlspecialchars($gimnasioNombre) ?>
    </span>

    <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
      <span style="color:var(--t-mut)">•</span>
      <select id="sel-audit-gym" class="inp" style="padding:3px 8px;font-size:11px;height:auto;background:#1e293b;border-color:#475569" onchange="switchAuditGym(this.value)">
        <option value="">(Auditoría Global)</option>
      </select>
    <?php endif; ?>
    
    <span style="color:var(--t-mut)">•</span>
    <span id="current-date-txt" style="color:var(--t-mut)"></span>
  </div>
  <div class="topbar-actions">
    <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH])): ?>
      <button class="btn btn-secondary btn-sm" onclick="openInviteModal()">🔗 Enlace Invitación</button>
      <button class="btn btn-primary btn-sm" onclick="openPagoModal()">+ Pago</button>
    <?php elseif (hasRole(ROLE_ALUMNO)): ?>
      <button class="btn btn-primary btn-sm" onclick="setPage('mi-membresia')">🪪 Mi Carnet</button>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-secondary btn-sm" style="color:#ef4444;border-color:rgba(239,68,68,0.3)">Cerrar Sesión</a>
  </div>
</div>