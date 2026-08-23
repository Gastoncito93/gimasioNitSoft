<section id="page-usuarios" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="card-title">🔑 Gestión de Usuarios, Roles y Seguridad</div>
        <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Administración de cuentas, roles RBAC, claves de acceso y estados de cuenta</div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-secondary btn-sm" onclick="loadUsuarios()">🔄 Actualizar</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openUserModal()" style="font-weight:800">➕ Nuevo Usuario</button>
      </div>
    </div>

    <!-- Barra de Filtros SaaS -->
    <div class="filter-bar" style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:12px;padding:14px 16px;background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px solid var(--border);border-radius:var(--r);margin-bottom:18px">
      <div>
        <input id="user-filter-q" class="inp" placeholder="🔍 Buscar por nombre de usuario, email o teléfono..." oninput="renderUsuariosTable()">
      </div>
      <div>
        <select id="user-filter-rol" class="inp" onchange="renderUsuariosTable()">
          <option value="">⚡ Todos los Roles</option>
          <option value="admin_general">👑 SuperAdmin</option>
          <option value="dueno">🏢 Dueño de Gimnasio</option>
          <option value="coach">🏋️ Coach / Entrenador</option>
          <option value="alumno">👤 Alumno / Socio</option>
        </select>
      </div>
      <div>
        <select id="user-filter-estado" class="inp" onchange="renderUsuariosTable()">
          <option value="">⚡ Todos los Estados</option>
          <option value="1">🟢 Habilitados (Activos)</option>
          <option value="0">🔴 Bloqueados (Inactivos)</option>
        </select>
      </div>
    </div>

    <div class="tbl-wrap desk-table-container" style="border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0">
      <table class="tbl" id="tbl-usuarios">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Email / Contacto</th>
            <th>Rol</th>
            <th>Perfil Vinculado</th>
            <th>Sede / Gimnasio</th>
            <th>Estado</th>
            <th style="text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="usuarios-cards" class="mobile-cards-container" style="display:none;padding:12px"></div>

    <!-- Barra de Paginación Usuarios -->
    <div id="usuarios-pagination-bar" class="pagination-wrap">
      <div class="pagination-info">Mostrando 0 de 0 usuarios</div>
      <div class="pagination-controls"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--t2)">Mostrar:</span>
        <select id="usuarios-page-size" class="inp page-size-sel" onchange="changeUsersPageSize(this.value)">
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