<div id="modal-invite" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <h3 id="inv-modal-title" style="font-size:18px;font-weight:800">🔗 Enlaces de Registro Directo</h3>
        <span id="inv-coach-badge" class="badge b-purple" style="display:none;margin-top:4px;font-size:11px">🏋️‍♂️ Coach: Auto-Asignación</span>
      </div>
      <button class="btn-close" onclick="closeModal('modal-invite')">&times;</button>
    </div>
    <div class="modal-body">
      <p id="inv-modal-subtitle" style="color:var(--t2);font-size:13px;margin-bottom:16px">
        Compartí estos enlaces con tus socios o profesores para que se registren directamente en tu gimnasio sin necesidad de seleccionar la sede manualmente:
      </p>

      <div class="form-group">
        <label class="form-label" id="inv-lbl-alumno">👤 Enlace para Registro de Alumnos / Socios</label>
        <div style="display:flex;gap:8px">
          <input id="inv-link-alumno" class="inp" readonly>
          <button class="btn btn-primary" onclick="copyLink('inv-link-alumno')">📋 Copiar</button>
        </div>
      </div>

      <div class="form-group" id="inv-grp-coach" style="margin-top:14px">
        <label class="form-label">🏋️ Enlace para Registro de Coaches / Profes</label>
        <div style="display:flex;gap:8px">
          <input id="inv-link-coach" class="inp" readonly>
          <button class="btn btn-primary" onclick="copyLink('inv-link-coach')">📋 Copiar</button>
        </div>
      </div>

      <div style="margin-top:16px;text-align:center">
        <button class="btn btn-success" style="width:100%" onclick="shareWhatsAppInvite()">💬 Compartir por WhatsApp</button>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-invite')">Cerrar</button>
    </div>
  </div>
</div>

<!-- ==================== MODAL: CREAR / EDITAR GIMNASIO & DUEÑO (SAAS) ==================== -->
<div id="modal-gym" class="modal-backdrop">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-header">
      <h3 id="gym-modal-title" style="font-size:18px;font-weight:800">➕ Habilitar / Crear Gimnasio & Dueño</h3>
      <button class="btn-close" onclick="closeModal('modal-gym')">&times;</button>
    </div>
    <form onsubmit="return saveGym(event)">
      <div class="modal-body">
        <input type="hidden" id="saas-gym-id">

        <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:10px;padding:12px 14px;margin-bottom:14px">
          <div style="font-size:12px;font-weight:800;color:#60a5fa;text-transform:uppercase">1. Datos de la Sede / Gimnasio</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nombre de la Sede / Gimnasio *</label>
            <input id="saas-gym-nombre" class="inp" required placeholder="Ej: Gimnasio Proinco">
          </div>
          <div class="form-group">
            <label class="form-label">Código de Invitación / Sede</label>
            <input id="saas-gym-code" class="inp" placeholder="Ej: PROINCO">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Teléfono / WhatsApp</label>
            <input id="saas-gym-tel" class="inp" placeholder="Ej: 5492657506957">
          </div>
          <div class="form-group">
            <label class="form-label">Email Oficial</label>
            <input id="saas-gym-email" type="email" class="inp" placeholder="contacto@gimnasio.com">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Dirección Física</label>
          <input id="saas-gym-dir" class="inp" placeholder="Ej: Av. Principal 1234">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Plan del SaaS *</label>
            <select id="saas-gym-plan-tipo" class="inp">
              <option value="standard">Standard (Gestión, Rutinas y Cobranzas)</option>
              <option value="pro">Plan PRO (Incluye Nutrición y Dietas)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Cuota / Abono SaaS Mensual ($) *</label>
            <input id="saas-gym-monto" type="number" step="0.01" class="inp" required value="45000.00">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Fecha de Vencimiento de Suscripción *</label>
          <input id="saas-gym-venc" type="date" class="inp" required>
        </div>

        <div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:10px;padding:12px 14px;margin:16px 0 14px">
          <div style="font-size:12px;font-weight:800;color:#c084fc;text-transform:uppercase">2. Cuenta de Acceso del Dueño</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Usuario del Dueño *</label>
            <input id="saas-dueno-user" class="inp" required placeholder="Ej: dueno_proinco">
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña (Dejar en blanco para mantener actual si edita)</label>
            <input id="saas-dueno-pass" type="password" class="inp" placeholder="Clave de acceso">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-gym')">Cancelar</button>
        <button type="submit" class="btn btn-primary" style="font-weight:800">💾 Guardar Gimnasio & Dueño</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================== MODAL: REGISTRAR PAGO DE ABONO SAAS ==================== -->
<div id="modal-saas-pago" class="modal-backdrop">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header">
      <h3 style="font-size:18px;font-weight:800">💵 Registrar Cobro / Abono SaaS</h3>
      <button class="btn-close" onclick="closeModal('modal-saas-pago')">&times;</button>
    </div>
    <form onsubmit="return saveSaasPago(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Sede / Gimnasio *</label>
          <select id="saas-pago-gym" class="inp" required>
            <option value="">(Seleccionar Gimnasio)</option>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Monto Abonado ($) *</label>
            <input id="saas-pago-monto" type="number" step="0.01" class="inp" required value="45000.00">
          </div>
          <div class="form-group">
            <label class="form-label">Fecha de Pago *</label>
            <input id="saas-pago-fecha" type="date" class="inp" required value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Medio de Pago *</label>
            <select id="saas-pago-medio" class="inp">
              <option value="transferencia">Transferencia Bancaria</option>
              <option value="efectivo">Efectivo</option>
              <option value="debito">Tarjeta de Débito</option>
              <option value="credito">Tarjeta de Crédito</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">N° de Comprobante / Referencia</label>
            <input id="saas-pago-comp" class="inp" placeholder="Ej: TRF-83921">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-saas-pago')">Cancelar</button>
        <button type="submit" class="btn btn-success" style="font-weight:800">✅ Registrar Pago & Renovar Mes</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================== NUEVOS MODALES DE RUTINAS (4 NIVELES) ==================== -->

<!-- 1. MODAL: CREAR / EDITAR PROGRAMA O PLANTILLA -->
<div id="modal-rutina-programa" class="modal-backdrop">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header">
      <h3 id="prog-modal-title" style="font-size:18px;font-weight:800">Crear Nuevo Programa de Entrenamiento</h3>
      <button class="btn-close" onclick="closeModal('modal-rutina-programa')">&times;</button>
    </div>
    <form onsubmit="return saveProgramaHeader(event)">
      <div class="modal-body">
        <input type="hidden" id="prog-id">
        <input type="hidden" id="prog-es-plantilla" value="1">
        <input type="hidden" id="prog-alumno-id">

        <div class="form-group">
          <label class="form-label">Título del Programa / Plantilla *</label>
          <input id="prog-titulo" class="inp" required placeholder="Ej: Hipertrofia y Fuerza - 4 Días">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Objetivo Principal</label>
            <select id="prog-obj" class="inp">
              <option value="Hipertrofia Muscular">Hipertrofia Muscular</option>
              <option value="Fuerza Máxima">Fuerza Máxima</option>
              <option value="Definición y Pérdida de Grasa">Definición y Pérdida de Grasa</option>
              <option value="Acondicionamiento / Principiante">Acondicionamiento / Principiante</option>
              <option value="Resistencia / Funcional">Resistencia / Funcional</option>
              <option value="Salud y Movilidad">Salud y Movilidad</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nivel de Experiencia</label>
            <select id="prog-nivel" class="inp">
              <option value="principiante">Principiante (Iniciación)</option>
              <option value="intermedio" selected>Intermedio</option>
              <option value="avanzado">Avanzado (Alto Rendimiento)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
            <span>Frecuencia / Días de Sesión *</span>
            <span id="prog-dias-badge" class="badge b-info">3 Días</span>
          </label>
          <div style="display:grid;grid-template-columns:repeat(7, 1fr);gap:6px;margin-top:6px">
            <button type="button" class="btn btn-sm btn-secondary btn-dias-sel" onclick="selectDiasCount(1)">1D</button>
            <button type="button" class="btn btn-sm btn-secondary btn-dias-sel" onclick="selectDiasCount(2)">2D</button>
            <button type="button" class="btn btn-sm btn-primary btn-dias-sel active" onclick="selectDiasCount(3)">3D</button>
            <button type="button" class="btn btn-sm btn-secondary btn-dias-sel" onclick="selectDiasCount(4)">4D</button>
            <button type="button" class="btn btn-sm btn-secondary btn-dias-sel" onclick="selectDiasCount(5)">5D</button>
            <button type="button" class="btn btn-sm btn-secondary btn-dias-sel" onclick="selectDiasCount(6)">6D</button>
            <button type="button" class="btn btn-sm btn-secondary btn-dias-sel" onclick="selectDiasCount(7)">7D</button>
          </div>
          <input type="hidden" id="prog-dias-count" value="3">
          <small style="color:var(--t-mut);display:block;margin-top:6px">La app creará automáticamente las pestañas (Día 1, Día 2, etc.) sin atarse a días fijos.</small>
        </div>

        <div class="form-group">
          <label class="form-label">Descripción / Pautas Generales</label>
          <textarea id="prog-desc" class="inp" rows="3" placeholder="Recomendaciones de calentamiento, tiempo de descanso general, cadencia o notas de progresión..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-rutina-programa')">Cancelar</button>
        <button class="btn btn-primary">Guardar y Abrir Diseñador de Días ➔</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. MODAL: DISEÑADOR / CONSTRUCTOR DE PROGRAMA (BUILDER VISUAL) -->
<div id="modal-rutina-builder" class="modal-backdrop">
  <div class="modal-box" style="max-width:960px;width:95%;max-height:92vh;display:flex;flex-direction:column">
    <div class="modal-header" style="border-bottom:1px solid var(--border);padding-bottom:14px">
      <div>
        <div style="display:flex;align-items:center;gap:10px">
          <h3 id="builder-prog-titulo" style="font-size:20px;font-weight:800;color:var(--t1)">Nombre del Programa</h3>
          <span id="builder-prog-badge-nivel" class="badge b-purple">Intermedio</span>
          <span id="builder-prog-badge-obj" class="badge b-info">Hipertrofia</span>
        </div>
        <div id="builder-prog-sub" style="font-size:12.5px;color:var(--t2);margin-top:4px">Plantilla de Entrenamiento • Frecuencia: 3 Días</div>
      </div>
      <button class="btn-close" onclick="closeRutinaBuilder()">&times;</button>
    </div>

    <!-- Pestañas de Días de Sesión (Día 1, Día 2...) en Carrusel de Cuadraditos -->
    <div style="background:var(--bg-card-hover, rgba(0,0,0,0.02));border-bottom:1px solid var(--border);padding:12px 18px;display:flex;flex-direction:column;gap:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div id="builder-dias-tabs" class="days-carousel-container" style="margin-bottom:0;padding-bottom:2px;flex:1">
          <!-- Renderizado dinámico de pestañas cuadradas de días -->
        </div>
        <div class="routine-toggle-actions" style="display:flex;gap:8px;flex-wrap:wrap">
          <button type="button" class="btn btn-secondary btn-routine-toggle" onclick="toggleAllBuilderBlocks(true)" title="Minimizar todos los bloques">
            ⇲ Minimizar Todo
          </button>
          <button type="button" class="btn btn-secondary btn-routine-toggle" onclick="toggleAllBuilderBlocks(false)" title="Expandir todos los bloques">
            ⇱ Expandir Todo
          </button>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
        <div style="font-size:12px;color:var(--t2)">Enfoque del día activo:</div>
        <div style="display:flex;gap:8px;align-items:center">
          <input id="builder-dia-enfoque" class="inp" style="padding:7px 12px;font-size:13px;width:240px" placeholder="Enfoque: ej. Pecho y Tríceps" onchange="saveDiaEnfoque()">
          <button class="btn btn-sm btn-primary" onclick="openSelectEjercicioModal()" style="font-weight:700">➕ Añadir Ejercicio</button>
        </div>
      </div>
    </div>

    <!-- Cuerpo del Diseñador: Bloques del Día Activo -->
    <div id="builder-dia-content" class="modal-body" style="flex:1;overflow-y:auto;padding:20px">
      <!-- Renderizado dinámico de los 4 bloques: Calentamiento, Principal, Cardio, Vuelta a la Calma -->
    </div>

    <div class="modal-footer" style="border-top:1px solid var(--border);justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div style="display:flex;align-items:center;gap:8px;color:var(--t2);font-size:12.5px">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981"></span>
        <span style="color:#10b981;font-weight:700">✓ Guardado automático en tiempo real</span>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <button type="button" class="btn btn-secondary" onclick="closeRutinaBuilder()">Cerrar</button>
        <button type="button" class="btn btn-primary" style="font-weight:800" onclick="closeRutinaBuilder(true)">
          💾 Guardar & Finalizar
        </button>
        <button type="button" class="btn btn-success" style="font-weight:800" onclick="openAsignarRutinaDirecto()">👥 Asignar esta Rutina</button>
      </div>
    </div>
  </div>
</div>

<!-- 3. MODAL: SELECCIÓN MÚLTIPLE DE EJERCICIOS DEL CATÁLOGO -->
<div id="modal-rutina-add-ejercicio" class="modal-backdrop" style="z-index:10050">
  <div class="modal-box" style="max-width:1120px;width:96%;height:90vh;max-height:92vh;display:flex;flex-direction:column;padding:0;overflow:hidden">
    <div class="modal-header" style="border-bottom:1px solid var(--border);padding:18px 24px">
      <div>
        <h3 id="modal-bulk-title" style="font-size:20px;font-weight:800;color:var(--t1)">Seleccionar Ejercicios del Catálogo</h3>
        <p id="modal-bulk-subtitle" style="color:var(--t2);font-size:13px;margin-bottom:0">Podés seleccionar múltiples ejercicios para este bloque. Luego ajustarás series y repeticiones directamente en el diseñador.</p>
      </div>
      <button class="btn-close" onclick="closeModal('modal-rutina-add-ejercicio')">&times;</button>
    </div>
    
    <div class="modal-body" style="flex:1;display:flex;flex-direction:column;overflow:hidden;padding:18px 24px;gap:14px">
      <!-- Buscador y Bloque Destino -->
      <div class="form-row" style="margin-bottom:0;display:grid;grid-template-columns:1fr auto;gap:14px">
        <div class="form-group" style="margin-bottom:0">
          <input id="ej-search-inp" class="inp" placeholder="🔍 Buscar por nombre o técnica..." oninput="renderBulkCatalogoList()" autocomplete="off" style="height:42px;font-size:14px">
        </div>
        <div class="form-group" style="margin-bottom:0;min-width:280px">
          <select id="ej-bulk-bloque-sel" class="inp" onchange="onBulkBloqueChange(this.value)" style="height:42px;font-size:13.5px;font-weight:700">
            <option value="calentamiento">🟡 Bloque Calentamiento & Movilidad</option>
            <option value="principal" selected>🔵 Bloque Principal / Fuerza</option>
            <option value="cardio">🔴 Bloque Cardio & HIIT</option>
            <option value="vuelta_calma">🟢 Bloque Vuelta a la Calma / Core</option>
          </select>
        </div>
      </div>

      <!-- Filtros por Grupo Muscular -->
      <div id="ej-grupos-filter" style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-xs btn-primary btn-grp-fil active" onclick="setGrupoFilter('todos')">Todos</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('pecho')">Pecho</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('espalda')">Espalda</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('piernas')">Piernas</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('hombros')">Hombros</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('biceps')">Bíceps</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('triceps')">Tríceps</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('core')">Core/Abdomen</button>
        <button type="button" class="btn btn-xs btn-secondary btn-grp-fil" onclick="setGrupoFilter('cardio')">Cardio/HIIT</button>
      </div>

      <!-- Lista de Ejercicios en Grid con Checkboxes -->
      <div id="ej-catalogo-bulk-list" style="flex:1;display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:10px;overflow-y:auto;padding-right:6px;align-content:start">
        <!-- Renderizado dinámico -->
      </div>
    </div>

    <!-- Barra Inferior de Acción y Contador -->
    <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;background:var(--bg-card-hover, rgba(0,0,0,0.02))">
      <div style="display:flex;align-items:center;gap:10px">
        <span id="bulk-selected-badge" class="badge b-info" style="font-size:12.5px;padding:6px 14px;font-weight:700">0 seleccionados</span>
        <button type="button" class="btn btn-xs btn-secondary" onclick="clearBulkSelection()">Deseleccionar todos</button>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-rutina-add-ejercicio')">Cancelar</button>
        <button id="btn-submit-bulk-ej" type="button" class="btn btn-success" style="font-weight:800;padding:10px 20px" onclick="submitBulkAddEjercicios()">
          ➕ Cargar Ejercicios Seleccionados
        </button>
      </div>
    </div>
  </div>
</div>

<!-- 4. MODAL: CATÁLOGO MAESTRO DE EJERCICIOS (+66 EJERCICIOS) -->
<div id="modal-catalogo-ejercicios" class="modal-backdrop">
  <div class="modal-box" style="max-width:850px">
    <div class="modal-header">
      <div>
        <h3 style="font-size:18px;font-weight:800">📖 Catálogo Maestro de Ejercicios</h3>
        <p style="color:var(--t2);font-size:12px;margin-bottom:0">Base de datos de ejercicios precargados y personalizados por grupo muscular.</p>
      </div>
      <button class="btn-close" onclick="closeModal('modal-catalogo-ejercicios')">&times;</button>
    </div>
    <div class="modal-body" style="max-height:75vh;overflow-y:auto">
      
      <!-- Panel para crear nuevo ejercicio personalizado con Autocompletado y Detección de Duplicados -->
      <div style="background:rgba(59, 130, 246, 0.08);border:1px dashed rgba(59, 130, 246, 0.35);border-radius:14px;padding:16px;margin-bottom:20px;position:relative">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <b style="font-size:13.5px;color:var(--t1);display:flex;align-items:center;gap:6px">
            <span>➕ Crear Nuevo Ejercicio Personalizado</span>
            <span style="font-size:11px;font-weight:normal;color:var(--t2)">(con detección inteligente de duplicados)</span>
          </b>
        </div>

        <div class="form-row" style="margin-bottom:0">
          <div class="form-group" style="margin-bottom:6px;position:relative">
            <label class="form-label" style="font-size:12px">Nombre del Ejercicio *</label>
            <input id="new-ej-nombre" class="inp" placeholder="Escribí el nombre (ej. Aperturas con Mancuernas...)" autocomplete="off" oninput="onNewEjercicioNameInput(this.value)">
            
            <!-- Desplegable Flotante de Sugerencias y Coincidencias en Tiempo Real -->
            <div id="new-ej-autocomplete" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:99999;background:#151c2e;border:1px solid #3b82f6;border-radius:10px;box-shadow:0 14px 35px rgba(0,0,0,0.8);margin-top:4px;max-height:220px;overflow-y:auto;padding:6px">
              <!-- Render dinámico de coincidencias -->
            </div>
          </div>

          <div class="form-group" style="margin-bottom:6px">
            <label class="form-label" style="font-size:12px">Grupo Muscular *</label>
            <select id="new-ej-grupo" class="inp">
              <option value="pecho">Pecho</option>
              <option value="espalda">Espalda</option>
              <option value="piernas">Piernas</option>
              <option value="hombros">Hombros</option>
              <option value="biceps">Bíceps</option>
              <option value="triceps">Tríceps</option>
              <option value="core">Core / Abdomen</option>
              <option value="cardio">Cardio / HIIT</option>
              <option value="cuerpo_completo">Cuerpo Completo</option>
            </select>
          </div>
        </div>

        <!-- Alerta en tiempo real de duplicado o similitud -->
        <div id="new-ej-dup-alert" style="display:none;margin-top:8px;margin-bottom:8px;font-size:12px;padding:8px 12px;border-radius:8px"></div>

        <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
          <input id="new-ej-desc" class="inp" placeholder="Descripción técnica, agarre o variante (opcional)" style="flex:1">
          <button id="btn-save-new-ej" type="button" class="btn btn-primary" style="white-space:nowrap;padding:9px 16px;font-weight:700" onclick="saveNuevoEjercicioCatalogo()">
            Guardar Ejercicio
          </button>
        </div>
      </div>

      <!-- Buscador y Filtros -->
      <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap">
        <input id="cat-search-inp" class="inp" style="flex:1" placeholder="🔍 Buscar en catálogo..." oninput="renderCatalogoManagerList()">
        <select id="cat-grupo-sel" class="inp" style="width:180px" onchange="renderCatalogoManagerList()">
          <option value="todos">Todos los grupos</option>
          <option value="pecho">Pecho</option>
          <option value="espalda">Espalda</option>
          <option value="piernas">Piernas</option>
          <option value="hombros">Hombros</option>
          <option value="biceps">Bíceps</option>
          <option value="triceps">Tríceps</option>
          <option value="core">Core / Abdomen</option>
          <option value="cardio">Cardio / HIIT</option>
          <option value="cuerpo_completo">Cuerpo Completo</option>
        </select>
      </div>

      <!-- Tabla de Ejercicios del Catálogo -->
      <div class="tbl-wrap" style="max-height:360px;overflow-y:auto">
        <table class="tbl">
          <thead><tr><th>Ejercicio</th><th>Grupo Muscular</th><th>Descripción</th><th style="text-align:right">Tipo</th></tr></thead>
          <tbody id="cat-manager-tbody"></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-catalogo-ejercicios')">Cerrar</button>
    </div>
  </div>
</div>

<!-- 5. MODAL: ASIGNAR PROGRAMA A SOCIO (CLONACIÓN PERSONALIZADA) -->
<div id="modal-rutina-asignar" class="modal-backdrop">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h3 style="font-size:18px;font-weight:800">👥 Asignar Rutina a Socio</h3>
      <button class="btn-close" onclick="closeModal('modal-rutina-asignar')">&times;</button>
    </div>
    <form onsubmit="return submitAsignarRutina(event)">
      <div class="modal-body">
        <p style="color:var(--t2);font-size:13px;margin-bottom:16px">
          Al asignar una plantilla maestra, la app creará una <b>copia editable e individual</b> para el socio, permitiéndote adaptar series, repeticiones o cambiar ejercicios sin modificar la plantilla general.
        </p>

        <div class="form-group">
          <label class="form-label">Socio / Alumno *</label>
          <select id="asig-alumno" class="inp" required><option value="">(Seleccionar Alumno)</option></select>
        </div>

        <div class="form-group">
          <label class="form-label">Plantilla Base a Clonar *</label>
          <select id="asig-plantilla" class="inp" required><option value="">(Seleccionar Plantilla)</option></select>
        </div>

        <div class="form-group">
          <label class="form-label">Título Personalizado (Opcional)</label>
          <input id="asig-titulo" class="inp" placeholder="Ej: Rutina Personalizada de Florencia">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-rutina-asignar')">Cancelar</button>
        <button class="btn btn-success">🚀 Asignar y Clonar Rutina</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: PLAN NUTRICIONAL -->
<div id="modal-nutri" class="modal-backdrop">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-header">
      <h3 id="nutri-modal-title" style="font-size:18px;font-weight:800">Cargar Plan Nutricional / Comida</h3>
      <button class="btn-close" onclick="closeModal('modal-nutri')">&times;</button>
    </div>
    <form onsubmit="return saveNutri(event)">
      <div class="modal-body">
        <input type="hidden" id="nutri-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Alumno *</label>
            <select id="nutri-alumno" class="inp" required><option value="">(Seleccionar Alumno)</option></select>
          </div>
          <div class="form-group">
            <label class="form-label">Título del Plan *</label>
            <input id="nutri-titulo" class="inp" required placeholder="Ej: Plan de Definición y Rendimiento">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Calorías Aprox. Diarias</label>
          <input id="nutri-cal" type="number" class="inp" value="2200">
        </div>
        <div class="form-group">
          <label class="form-label">Detalle de Comidas (Desayuno, Almuerzo, Merienda, Cena) *</label>
          <textarea id="nutri-det" class="inp" rows="6" required placeholder="🍳 Desayuno: ...&#10;🥗 Almuerzo: ...&#10;🍎 Merienda: ...&#10;🍗 Cena: ..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nutri')">Cancelar</button>
        <button class="btn btn-primary">Guardar Plan Nutricional</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: FICHA 360° INTEGRAL DEL ALUMNO (COACH / DUEÑO / ADMIN) -->
<div id="modal-alumno-ficha" class="modal-backdrop">
  <div class="modal-box" style="max-width:1080px;width:96%;height:88vh;max-height:92vh;display:flex;flex-direction:column;padding:0;overflow:hidden">
    <!-- Header -->
    <div class="modal-header" style="padding:18px 24px;border-bottom:1px solid var(--border);background:rgba(15,23,42,0.85);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;flex:1;min-width:0">
        <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg, #3b82f6, #1d4ed8);display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 4px 14px rgba(59,130,246,0.35);flex-shrink:0">
          👤
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 id="ficha-alu-nombre" style="font-size:20px;font-weight:800;margin:0;color:var(--t1);letter-spacing:-0.3px">Nombre del Alumno</h3>
            <span id="ficha-alu-sede-badge" class="badge b-purple" style="font-size:12px;font-weight:800;padding:4px 12px;letter-spacing:0.3px;display:inline-flex;align-items:center;gap:6px;border-radius:8px;box-shadow:0 2px 8px rgba(168,85,247,0.25)">🏢 Sede: Gimnasio</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap">
            <span id="ficha-alu-badge-estado" class="badge b-ok" style="font-weight:800;font-size:11.5px">✅ Cuota al Día</span>
            <span id="ficha-alu-badge-plan" class="badge b-info" style="font-weight:800;font-size:11.5px">🏷️ Plan Full</span>
            <span style="color:var(--t-mut);font-size:12px">•</span>
            <p id="ficha-alu-sub" style="font-size:12.5px;color:var(--t2);margin:0;font-weight:600">DNI: - • Tel: -</p>
          </div>
        </div>
      </div>
      <button class="btn-close" onclick="closeModal('modal-alumno-ficha')">&times;</button>
    </div>

    <!-- Quick Action Bar -->
    <div style="padding:10px 24px;background:var(--bg-card-hover, rgba(0,0,0,0.02));border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:11.5px;font-weight:800;color:var(--t2);text-transform:uppercase;letter-spacing:0.5px">Acciones Rápidas:</span>
        <a id="ficha-btn-wa" href="#" target="_blank" class="btn btn-xs btn-success" style="font-weight:700;text-decoration:none">💬 WhatsApp</a>
        <button id="ficha-btn-cobrar" class="btn btn-xs btn-primary" style="font-weight:700" onclick="onFichaCobrarClick()">💵 Cobrar Cuota</button>
        <button id="ficha-btn-rutina" class="btn btn-xs btn-secondary" style="font-weight:700" onclick="onFichaRutinaClick()">📋 Rutina</button>
        <button id="ficha-btn-suspender" class="btn btn-xs btn-warn" style="font-weight:700" onclick="onFichaToggleSuspensionClick()">⏸️ Suspender</button>
      </div>
      <div id="ficha-alu-coach-tag" style="font-size:12px;color:var(--t-mut);font-weight:600">🏋️ Coach Asignado</div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display:flex;gap:4px;padding:0 24px;border-bottom:1px solid var(--border);background:rgba(0,0,0,0.25);overflow-x:auto">
      <button class="tab-ficha active" onclick="switchFichaTab('tab-resumen', this)">📊 1. Resumen & Membresía</button>
      <button class="tab-ficha" onclick="switchFichaTab('tab-rutina', this)">🏋️‍♂️ 2. Rutina & Ejercicios</button>
      <button class="tab-ficha" onclick="switchFichaTab('tab-asistencias', this)">📅 3. Asistencias & Constancia</button>
      <button class="tab-ficha" onclick="switchFichaTab('tab-pagos', this)">💳 4. Historial Pagos</button>
      <button class="tab-ficha" onclick="switchFichaTab('tab-notas', this)">📝 5. Notas & Salud</button>
    </div>

    <!-- Tab Contents (Scrollable) -->
    <div class="modal-body" style="padding:22px 24px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:18px">
      
      <!-- TAB 1: RESUMEN -->
      <div id="ficha-tab-resumen" class="ficha-tab-pane">
        <div class="grid g3" style="gap:12px;margin-bottom:14px">
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Plan & Actividades</div>
            <div class="stat-value" id="ficha-res-plan" style="font-size:16px;color:#60a5fa">Plan 3x</div>
            <div class="stat-sub" id="ficha-res-actividades">Musculación</div>
          </div>
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Cuota & Saldo este Mes</div>
            <div class="stat-value" id="ficha-res-saldo" style="font-size:16px;color:var(--ok)">$ 0.00</div>
            <div class="stat-sub" id="ficha-res-cuota-detalle">Pactado: $ 0</div>
          </div>
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Vencimiento de Cuota</div>
            <div class="stat-value" id="ficha-res-venc" style="font-size:16px;color:#f59e0b">-</div>
            <div class="stat-sub" id="ficha-res-dias-rest">0 días restantes</div>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;padding:16px;background:var(--bg-card-hover, rgba(0,0,0,0.02))">
          <div style="font-weight:800;font-size:13.5px;color:var(--t1);margin-bottom:10px">📌 Información de Membresía & Contacto</div>
          <div class="grid g2" style="gap:10px;font-size:13px">
            <div><span style="color:var(--t-mut)">Email:</span> <b id="ficha-res-email" style="color:var(--t1)">-</b></div>
            <div><span style="color:var(--t-mut)">Teléfono / Celular:</span> <b id="ficha-res-tel" style="color:var(--t1)">-</b></div>
            <div><span style="color:var(--t-mut)">Fecha de Ingreso:</span> <b id="ficha-res-alta" style="color:var(--t1)">-</b></div>
            <div><span style="color:var(--t-mut)">Coach Asignado:</span> <b id="ficha-res-coach" style="color:#c084fc">-</b></div>
          </div>
        </div>
      </div>

      <!-- TAB 2: RUTINA -->
      <div id="ficha-tab-rutina" class="ficha-tab-pane" style="display:none">
        <div id="ficha-rutina-empty" style="display:none;text-align:center;padding:32px;background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px dashed var(--border);border-radius:12px">
          <div style="font-size:36px;margin-bottom:8px">📋</div>
          <h4 style="font-weight:700;color:var(--t1);margin-bottom:4px">Sin Rutina Activa Asignada</h4>
          <p style="font-size:13px;color:var(--t2);margin-bottom:14px">Este alumno no cuenta con un programa activo asignado actualmente.</p>
          <button class="btn btn-primary btn-sm" onclick="onFichaRutinaClick()">+ Crear / Asignar Rutina</button>
        </div>
        <div id="ficha-rutina-content">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
            <div>
              <h4 id="ficha-rut-titulo" style="font-size:16px;font-weight:800;color:var(--t1);margin:0">Rutina</h4>
              <p id="ficha-rut-meta" style="font-size:12.5px;color:var(--t2);margin:2px 0 0 0">Objetivo: - • Nivel: -</p>
            </div>
            <button class="btn btn-sm btn-secondary" onclick="onFichaRutinaClick()">✏️ Editar Programa</button>
          </div>
          <div id="ficha-rut-dias-container" class="grid g2" style="gap:12px"></div>

          <!-- Historial de Check-ins y Rutinas Realizadas por el Alumno -->
          <div style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
              <div>
                <h5 style="font-size:14.5px;font-weight:800;color:var(--t1);margin:0">🏋️‍♂️ Historial de Rutinas Realizadas (Check-ins del Socio)</h5>
                <span style="font-size:12px;color:var(--t2)">Sesiones completadas, esfuerzo percibido y feedback técnico</span>
              </div>
              <span id="ficha-checkins-count-badge" class="badge b-ok">0 Entrenamientos</span>
            </div>
            <div class="tbl-wrap" style="max-height:300px;overflow-y:auto">
              <table class="tbl" id="tbl-ficha-checkins">
                <thead><tr><th>Fecha / Hora</th><th>Rutina Entrenada</th><th>Duración / RPE</th><th>Notas del Alumno</th><th>Devolución Coach</th><th style="text-align:right">Feedback</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: ASISTENCIAS -->
      <div id="ficha-tab-asistencias" class="ficha-tab-pane" style="display:none">
        <div class="grid g3" style="gap:12px;margin-bottom:14px">
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Asistencias este Mes</div>
            <div class="stat-value" id="ficha-asis-mes" style="font-size:22px;color:var(--ok)">0</div>
            <div class="stat-sub" id="ficha-asis-mes-sub">Entrenamientos registrados</div>
          </div>
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Asistencias esta Semana</div>
            <div class="stat-value" id="ficha-asis-sem" style="font-size:22px;color:#60a5fa">0</div>
            <div class="stat-sub">Semana actual</div>
          </div>
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Último Ingreso</div>
            <div class="stat-value" id="ficha-asis-ultima" style="font-size:15px;color:#f59e0b">-</div>
            <div class="stat-sub" id="ficha-asis-status-badge"><span class="badge b-ok">Regular</span></div>
          </div>
        </div>

        <div class="card" style="margin-bottom:0">
          <div class="card-header" style="padding:12px 16px"><div class="card-title" style="font-size:13.5px">Últimos Registros de Asistencia</div></div>
          <div class="tbl-wrap">
            <table class="tbl" id="tbl-ficha-asistencias">
              <thead><tr><th>Fecha</th><th>Hora</th><th>Observación</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB 4: PAGOS -->
      <div id="ficha-tab-pagos" class="ficha-tab-pane" style="display:none">
        <div class="card" style="margin-bottom:0">
          <div class="card-header" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center">
            <div class="card-title" style="font-size:13.5px">Historial de Cobros y Cuotas Abonadas</div>
            <button class="btn btn-xs btn-primary" onclick="onFichaCobrarClick()">+ Registrar Cobro</button>
          </div>
          <div class="tbl-wrap">
            <table class="tbl" id="tbl-ficha-pagos">
              <thead><tr><th>Fecha</th><th>Plan</th><th>Medio de Pago</th><th>Observaciones</th><th style="text-align:right">Monto</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB 5: NOTAS & SALUD -->
      <div id="ficha-tab-notas" class="ficha-tab-pane" style="display:none">
        <form onsubmit="return saveFichaNotas(event)">
          <input type="hidden" id="ficha-notas-alu-id">
          
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label" style="display:flex;align-items:center;justify-content:space-between">
              <span>🗣️ Notas Generales / Objetivos del Alumno</span>
              <small style="color:var(--t-mut);font-weight:normal">Objetivos, preferencias y notas de membresía</small>
            </label>
            <textarea id="ficha-notas-alumno-txt" class="inp" rows="3" placeholder="Ej: Quiere hipertrofiar tren superior, entrena 4 días a la semana por la tarde..."></textarea>
          </div>

          <div class="form-group" style="margin-bottom:18px">
            <label class="form-label" style="display:flex;align-items:center;justify-content:space-between">
              <span style="color:#c084fc;font-weight:800">🔒 Notas Privadas del Coach (Solo Entrenadores & Admin)</span>
              <span class="badge b-purple" style="font-size:10.5px">Privado</span>
            </label>
            <textarea id="ficha-notas-coach-txt" class="inp" rows="4" style="border-color:rgba(168,85,247,0.3)" placeholder="Ej: Lesión en menisco izquierdo en recuperación. Evitar sentadilla profunda con más de 60kg. PR de Press Banca: 85kg..."></textarea>
            <div style="font-size:11.5px;color:var(--t-mut);margin-top:4px">Estas notas son estrictamente confidenciales y solo pueden ser leídas por los coaches y el dueño.</div>
          </div>

          <div style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary" id="btn-save-ficha-notas">💾 Guardar Notas de Seguimiento</button>
          </div>
        </form>
      </div>

    </div>
    
    <!-- Footer -->
    <div class="modal-footer" style="padding:14px 24px;border-top:1px solid var(--border);background:rgba(15,23,42,0.85);display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:12px;color:var(--t-mut)">GYM PRO • Ficha Integral 360°</span>
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-alumno-ficha')">Cerrar Ficha</button>
    </div>
  </div>
</div>

<!-- ==================== MODAL: REGISTRAR / EDITAR ALUMNO / SOCIO ==================== -->
<div id="modal-alu" class="modal-backdrop">
  <div class="modal-box" style="max-width:640px">
    <div class="modal-header">
      <h3 id="alu-modal-title" style="font-size:18px;font-weight:800">Registrar Nuevo Alumno</h3>
      <button class="btn-close" onclick="closeModal('modal-alu')">&times;</button>
    </div>
    <form onsubmit="return saveAlumno(event)">
      <div class="modal-body">
        <input type="hidden" id="alu-id">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nombre y Apellido *</label>
            <input id="alu-nombre" class="inp" placeholder="Ej: Juan Pérez" required>
            <div id="err-alu-nombre" class="field-error"></div>
          </div>
          <div class="form-group">
            <label class="form-label">DNI / Documento *</label>
            <input id="alu-dni" class="inp" placeholder="Ej: 38456123" required>
            <div id="err-alu-dni" class="field-error"></div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Teléfono / WhatsApp</label>
            <input id="alu-telefono" class="inp" placeholder="Ej: 2657506957">
            <div id="err-alu-telefono" class="field-error"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Plan / Membresía *</label>
            <select id="alu-plan-inp" class="inp" required>
              <option value="3x">Plan 3x por Semana</option>
              <option value="full">Plan Full / Pase Libre</option>
              <option value="clase">Pase por Clase</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Actividades & Disciplinas</label>
          <input id="alu-actividades" class="inp" placeholder="Ej: Musculación, Funcional, Crossfit" value="Musculación, Funcional">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Fecha de Inicio *</label>
            <input id="alu-inicio" type="date" class="inp" required>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha de Vencimiento *</label>
            <input id="alu-venc" type="date" class="inp" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Estado de la Membresía *</label>
            <select id="alu-estado-inp" class="inp" required>
              <option value="activo">🟢 Activo / Al Día</option>
              <option value="vencido">🔴 Vencido</option>
              <option value="pausado">⏸️ Pausado / Suspendido</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Coach / Profesor Asignado</label>
            <select id="alu-prof-inp" class="inp">
              <option value="">(Sin coach asignado / General)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-alu')">Cancelar</button>
        <button type="submit" class="btn btn-primary" style="font-weight:800">💾 Guardar Alumno</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: PROFESOR -->
<div id="modal-prof" class="modal-backdrop">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h3 id="prof-modal-title" style="font-size:18px;font-weight:800">Registrar Coach / Profesor</h3>
      <button class="btn-close" onclick="closeModal('modal-prof')">&times;</button>
    </div>
    <form onsubmit="return saveProfesor(event)">
      <div class="modal-body">
        <input type="hidden" id="prof-id">
        <div class="form-row">
          <div class="form-group"><label class="form-label" id="prof-lbl-nombre">Nombre del Coach *</label><input id="prof-nombre" class="inp" placeholder="Gastón Sosa"></div>
          <div class="form-group"><label class="form-label" id="prof-lbl-telefono">Teléfono / WhatsApp</label><input id="prof-telefono" class="inp" placeholder="+54 266 ..."></div>
        </div>
        <div id="prof-datos-hint" style="display:none;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--t2);margin-bottom:14px">
          🔒 <b>Modo Dueño:</b> Los datos de contacto del coach (nombre y teléfono) están protegidos. Como dueño podés configurar libremente su esquema de pago, honorarios y condiciones.
        </div>
        
        <!-- ESQUEMA DE REMUNERACIÓN / PAGO DEL COACH -->
        <div class="form-group">
          <label class="form-label">Esquema de Remuneración / Acuerdo *</label>
          <select id="prof-tipo-rem" class="inp" onchange="onProfTipoRemChange()" style="font-weight:700">
            <option value="sueldo_fijo">💼 1. Sueldo Fijo Mensual (El Dueño le paga al Coach)</option>
            <option value="porcentaje">📈 2. Porcentaje de Comisión por Cuota de Alumno (%)</option>
            <option value="monto_alumno">👥 3. Monto Fijo por cada Alumno que Paga ($)</option>
            <option value="canon_alquiler">🏢 4. Canon de Instalaciones (El Coach le paga al Dueño por alquiler de sala)</option>
          </select>
        </div>

        <!-- 1. SUELDO FIJO -->
        <div id="prof-grp-sueldo" class="form-group">
          <label class="form-label" id="prof-lbl-sueldo">Sueldo / Honorario Fijo Mensual ($) *</label>
          <input id="prof-cuota" type="number" step="0.01" class="inp" value="0.00" placeholder="Ej: 180000">
          <div style="font-size:11.5px;color:var(--t-mut);margin-top:4px">El coach percibirá un sueldo fijo mensual independiente de la recaudación de sus alumnos.</div>
        </div>

        <!-- 2. PORCENTAJE DE COMISIÓN -->
        <div id="prof-grp-pct" class="form-group" style="display:none">
          <label class="form-label">Porcentaje de Comisión (%) *</label>
          <div style="display:flex;align-items:center;gap:8px">
            <input id="prof-pct" type="number" step="0.1" min="0" max="100" class="inp" value="40.0" placeholder="Ej: 40">
            <span style="font-size:18px;font-weight:900;color:var(--pri)">%</span>
          </div>
          <div style="font-size:11.5px;color:var(--t-mut);margin-top:4px">El coach ganará este % sobre el total de cuotas recaudadas de sus alumnos a cargo en el mes.</div>
        </div>

        <!-- 3. MONTO FIJO POR ALUMNO -->
        <div id="prof-grp-mto-alu" class="form-group" style="display:none">
          <label class="form-label">Monto Fijo por cada Alumno que Pagó en el Mes ($) *</label>
          <input id="prof-mto-alu" type="number" step="0.01" class="inp" value="0.00" placeholder="Ej: 6000">
          <div style="font-size:11.5px;color:var(--t-mut);margin-top:4px">El coach cobrará este importe fijo multiplicado por cada socio que abonó su cuota en el mes.</div>
        </div>

        <!-- 4. CANON DE ALQUILER / USO DE INSTALACIONES (COACH PAGA A DUEÑO) -->
        <div id="prof-grp-canon" class="form-group" style="display:none">
          <div class="form-row">
            <div class="form-group" style="flex:2">
              <label class="form-label">Canon Mensual que el Coach Paga al Dueño ($) *</label>
              <input id="prof-canon" type="number" step="0.01" class="inp" value="25000.00" placeholder="Ej: 30000" style="color:#38bdf8;font-weight:800">
            </div>
            <div class="form-group" style="flex:1">
              <label class="form-label">Día Límite de Pago</label>
              <input id="prof-dia-canon" type="number" min="1" max="31" class="inp" value="10" placeholder="Día 10">
            </div>
          </div>
          <div style="font-size:11.5px;color:#38bdf8;margin-top:4px">🏢 <b>Esquema de Alquiler de Espacio:</b> El coach le pagará este canon a la sede por usar las instalaciones para sus entrenamientos.</div>
        </div>

        <div class="form-group"><label class="form-label">Observaciones</label><textarea id="prof-obs" class="inp" rows="2" placeholder="Especialidades, horarios, notas internas del acuerdo"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-prof')">Cancelar</button>
        <button class="btn btn-primary">Guardar Coach</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: ASIGNAR ALUMNOS A COACH -->
<div id="modal-assign-coach-alumnos" class="modal-backdrop">
  <div class="modal-box" style="max-width:680px;width:95%;max-height:88vh;display:flex;flex-direction:column">
    <div class="modal-header" style="border-bottom:1px solid var(--border);padding-bottom:14px">
      <div>
        <h3 id="assign-coach-title" style="font-size:18px;font-weight:800;color:var(--t1)">Asignar Alumnos al Coach</h3>
        <p id="assign-coach-subtitle" style="color:var(--t2);font-size:12.5px;margin-bottom:0">Seleccioná socios disponibles (sin coach asignado) para ponerlos a cargo de este entrenador.</p>
      </div>
      <button class="btn-close" onclick="closeModal('modal-assign-coach-alumnos')">&times;</button>
    </div>
    <div class="modal-body" style="flex:1;overflow-y:auto;padding:16px 20px">
      <input id="assign-coach-search" class="inp" placeholder="🔍 Buscar socio por nombre o DNI..." oninput="renderAssignCoachAlumnosList()" style="margin-bottom:14px" autocomplete="off">
      <div id="assign-coach-alumnos-list" style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;padding-right:4px">
        <!-- Renderizado dinámico -->
      </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 20px;display:flex;justify-content:space-between;align-items:center;background:var(--bg-card-hover, rgba(0,0,0,0.02))">
      <span id="assign-coach-count-badge" class="badge b-info" style="font-size:12px;padding:5px 12px;font-weight:700">0 socios seleccionados</span>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-assign-coach-alumnos')">Cancelar</button>
        <button type="button" class="btn btn-success" style="font-weight:800" onclick="submitAssignCoachAlumnos()">💾 Guardar Asignación</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: PAGO -->
<div id="modal-pago" class="modal-backdrop">
  <div class="modal-box" style="max-width:680px;width:95%">
    <div class="modal-header" style="padding:18px 24px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:24px">💳</span>
        <div>
          <h3 id="modal-pago-title" style="font-size:18px;font-weight:800;margin:0;color:var(--t1)">Registrar Cobro / Liquidación</h3>
          <div style="font-size:12px;color:var(--t2);margin-top:2px">Caja y cobranzas con control de cuotas y honorarios</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeModal('modal-pago')">&times;</button>
    </div>
    <form id="form-pago" onsubmit="return savePago(event)" novalidate>
      <div class="modal-body" style="padding:20px 24px">
        <div class="form-row">
          <div class="form-group" style="flex:1">
            <label class="form-label">Tipo de Transacción *</label>
            <select id="pago-tipo" class="inp" onchange="onPagoTipoChange()" style="font-weight:700">
              <option value="alumno">👤 Cobro a Alumno (Ingreso de Cuota)</option>
              <option value="profesor">🏋️ Liquidar Honorarios a Coach / Profe</option>
            </select>
          </div>
        </div>

        <!-- BUSCADOR DINÁMICO DE ALUMNO -->
        <div class="form-group" id="group-pago-alumno" style="margin-bottom:16px;position:relative">
          <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
            <span>Alumno / Socio *</span>
            <span id="pago-alumno-selected-tag" style="display:none;font-size:11.5px;color:#10b981;font-weight:700">✓ Socio Seleccionado</span>
          </label>
          
          <div style="position:relative;display:flex;align-items:center">
            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--t-mut);pointer-events:none">🔍</span>
            <input id="pago-alumno-search" type="text" class="inp" placeholder="Escribí para buscar por nombre, DNI o teléfono..." 
                   autocomplete="off" style="padding-left:36px;padding-right:36px;font-weight:600"
                   oninput="filterPagoAlumnos(this.value)" onfocus="filterPagoAlumnos(this.value, true)">
            <button type="button" id="btn-clear-alumno-search" onclick="clearPagoAlumnoSelect()" 
                    style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px">✕</button>
          </div>
          
          <!-- Dropdown con resultados en tiempo real -->
          <div id="pago-alumno-dropdown" class="search-dropdown-menu" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:var(--bg-inp);border:1px solid #3b82f6;border-radius:10px;z-index:9999;box-shadow:0 15px 35px rgba(0,0,0,0.7);margin-top:4px">
          </div>
          
          <select id="pago-alumno" style="display:none" onchange="onPagoAlumnoSelect()"><option value="">(Seleccionar Alumno)</option></select>
        </div>

        <!-- BUSCADOR DINÁMICO DE PROFESOR -->
        <div class="form-group" id="group-pago-profesor" style="display:none;margin-bottom:16px;position:relative">
          <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
            <span>Coach / Profesor a Liquidar *</span>
            <span id="pago-profesor-selected-tag" style="display:none;font-size:11.5px;color:#a855f7;font-weight:700">✓ Coach Seleccionado</span>
          </label>
          
          <div style="position:relative;display:flex;align-items:center">
            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--t-mut);pointer-events:none">🔍</span>
            <input id="pago-profesor-search" type="text" class="inp" placeholder="Escribí para buscar coach por nombre o teléfono..." 
                   autocomplete="off" style="padding-left:36px;padding-right:36px;font-weight:600"
                   oninput="filterPagoProfes(this.value)" onfocus="filterPagoProfes(this.value, true)">
            <button type="button" id="btn-clear-prof-search" onclick="clearPagoProfSelect()" 
                    style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px">✕</button>
          </div>
          
          <!-- Dropdown con resultados en tiempo real -->
          <div id="pago-profesor-dropdown" class="search-dropdown-menu" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:var(--bg-inp);border:1px solid #a855f7;border-radius:10px;z-index:9999;box-shadow:0 15px 35px rgba(0,0,0,0.7);margin-top:4px">
          </div>
          
          <select id="pago-profesor" style="display:none" onchange="onPagoProfesorSelect()"><option value="">(Seleccionar Coach)</option></select>
        </div>

        <!-- RESUMEN DE CUOTA PACTADA Y SALDO -->
        <div id="pago-summary-box" style="display:none;background:rgba(59, 130, 246, 0.08);border:1px solid rgba(59, 130, 246, 0.25);border-radius:12px;padding:14px 16px;margin-bottom:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px">
            <span id="pago-summary-plan" style="font-weight:700;color:var(--t1)">Plan / Titular: -</span>
            <span id="pago-summary-badge" class="badge b-info">-</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1.2fr;gap:10px;margin-top:10px;font-size:12.5px;border-top:1px dashed rgba(255,255,255,0.1);padding-top:10px">
            <div><span id="lbl-pago-summary-cuota" style="color:var(--t2)">Cuota Pactada:</span><br><b id="pago-summary-cuota" style="color:var(--t1);font-size:13.5px">$ 0</b></div>
            <div><span id="lbl-pago-summary-abonado" style="color:var(--t2)">Abonado este Mes:</span><br><b id="pago-summary-abonado" style="color:var(--ok);font-size:13.5px">$ 0</b></div>
            <div><span id="lbl-pago-summary-saldo" style="color:var(--t2)">Saldo a Liquidar/Cobrar:</span><br><b id="pago-summary-saldo" style="color:#38bdf8;font-size:14px">$ 0</b></div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1.2">
            <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
              <span id="lbl-pago-monto">Monto Exacto ($) *</span>
              <span id="pago-lock-badge" class="badge b-info" style="font-size:10px;padding:2px 6px">🔒 PACTADO</span>
            </label>
            <input id="pago-monto" type="number" step="0.01" class="inp" required placeholder="0.00" 
                   style="font-weight:800;color:var(--t1);background:var(--bg-inp);font-size:16px;transition:border 0.2s, box-shadow 0.2s"
                   oninput="validatePagoMontoInput()">
            <div id="pago-monto-error" style="display:none;font-size:12px;color:#f87171;font-weight:700;margin-top:6px;background:rgba(239,68,68,0.12);padding:7px 12px;border-radius:8px;border:1px solid rgba(239,68,68,0.35);line-height:1.4"></div>
            <div id="pago-monto-hint" style="font-size:11px;color:var(--t2);margin-top:4px">
              Solo se permite registrar el importe pactado exacto.
            </div>
          </div>
          <div class="form-group" style="flex:1"><label class="form-label">Fecha de Pago *</label><input id="pago-fecha" type="date" class="inp" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1">
            <label class="form-label">Medio de Pago *</label>
            <select id="pago-medio" class="inp" style="font-weight:600"><option value="efectivo">💵 Efectivo</option><option value="transferencia">🏦 Transferencia Bancaria</option><option value="tarjeta">💳 Tarjeta Débito / Crédito</option><option value="otro">📄 Otro / MercadoPago</option></select>
          </div>
          <div class="form-group" style="flex:1"><label class="form-label">Comprobante / Detalle</label><input id="pago-obs" class="inp" placeholder="Opcional (ej: Transf #98234)"></div>
        </div>
      </div>
      <div class="modal-footer" style="padding:16px 24px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-pago')">Cancelar</button>
        <button id="btn-pago-submit" class="btn btn-success" style="font-weight:800;padding:10px 22px">Confirmar Operación</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: GIMNASIO & DUEÑO (SUPERADMIN) -->

<!-- MODAL: USUARIO & ROL -->
<div id="modal-usuario" class="modal-backdrop">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h3 id="user-modal-title" style="font-size:18px;font-weight:800;color:var(--t1);margin:0">Gestionar Usuario & Rol</h3>
      <button type="button" class="btn-close" onclick="closeModal('modal-usuario')">&times;</button>
    </div>
    <form onsubmit="return saveUsuario(event)">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="user-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nombre de Usuario *</label>
            <input id="user-nombre" class="inp" placeholder="ej: nombre_usuario" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input id="user-email" type="email" class="inp" placeholder="usuario@email.com" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Teléfono / WhatsApp</label>
            <input id="user-tel" class="inp" placeholder="ej: +54 266 123456">
          </div>
          <div class="form-group">
            <label class="form-label">Rol del Sistema *</label>
            <select id="user-rol" class="inp">
              <option value="admin_general">👑 SuperAdmin (Acceso Total)</option>
              <option value="dueno">🏢 Dueño de Gimnasio</option>
              <option value="coach">🏋️ Coach / Entrenador</option>
              <option value="alumno">👤 Alumno / Socio</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Estado de la Cuenta *</label>
            <select id="user-activo" class="inp">
              <option value="1">🟢 Habilitado (Activo)</option>
              <option value="0">🔴 Bloqueado (Suspendido)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña (8-20 car., Mayús, Minús, Núm, Símb.)</label>
            <div style="position:relative;display:flex;align-items:center">
              <input id="user-password" type="password" class="inp" placeholder="Dejar en blanco si no se cambia (Mín. 8 car.)" style="padding-right:44px">
              <button type="button" onclick="togglePasswordVisibility('user-password', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
                👁️
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-usuario')">Cancelar</button>
        <button type="submit" class="btn btn-primary" style="font-weight:800">💾 Guardar Usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: CONTRASEÑA TEMPORAL GENERADA -->
<div id="modal-temp-pass" class="modal-backdrop">
  <div class="modal-box" style="max-width:480px;border:1px solid rgba(139,92,246,0.4);box-shadow:0 25px 60px rgba(0,0,0,0.8);border-radius:20px;background:var(--bg-inp);overflow:hidden">
    <div style="background:linear-gradient(135deg, rgba(139,92,246,0.2), rgba(59,130,246,0.1));padding:22px 24px 18px 24px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(139,92,246,0.25);border:1px solid rgba(139,92,246,0.5);display:flex;align-items:center;justify-content:center;font-size:22px">
          🔑
        </div>
        <div>
          <h3 style="font-size:17px;font-weight:800;color:var(--t1);margin:0">Contraseña Temporal Generada</h3>
          <div id="temp-pass-target-name" style="font-size:12.5px;color:var(--t2);margin-top:2px">Usuario: @...</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeModal('modal-temp-pass')">&times;</button>
    </div>

    <div style="padding:22px 24px">
      <div style="background:var(--bg-card-hover, rgba(0,0,0,0.02));border:1px dashed rgba(139,92,246,0.35);border-radius:14px;padding:18px 16px;text-align:center;margin-bottom:18px">
        <div style="font-size:11px;font-weight:800;color:#c084fc;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px">Clave Provisoria de Acceso</div>
        <div id="temp-pass-code-display" style="font-size:26px;font-weight:900;letter-spacing:2px;color:#38bdf8;font-family:monospace;padding:6px 0;user-select:all">
          Temp123!ABC
        </div>
        <div style="font-size:11.5px;color:var(--t2);margin-top:6px;line-height:1.4">
          ⚠️ <b>Cambio Obligatorio:</b> Al ingresar con esta clave, el sistema le exigirá inmediatamente cambiarla por una nueva contraseña personal.
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-bottom:8px">
        <button class="btn btn-secondary" style="flex:1;font-weight:700;font-size:13px;padding:11px 14px" onclick="copyTempPassword()">
          📋 Copiar Clave
        </button>
        <button id="btn-temp-pass-wa" class="btn btn-success" style="flex:1.2;font-weight:700;font-size:13px;padding:11px 14px" onclick="shareTempPassWhatsApp()">
          💬 Enviar WhatsApp
        </button>
      </div>
    </div>

    <div style="padding:12px 24px 18px 24px;background:rgba(0,0,0,0.2);text-align:right">
      <button class="btn btn-secondary btn-sm" onclick="closeModal('modal-temp-pass')">Entendido / Cerrar</button>
    </div>
  </div>
</div>

<!-- MODAL: CONFIRMACIÓN DEL SISTEMA -->
<div id="modal-confirm" class="modal-backdrop">
  <div class="modal-box" style="max-width:440px;border:1px solid #334155;box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.8);border-radius:16px;background:var(--bg-inp);overflow:hidden">
    <div style="padding:28px 24px 20px 24px;text-align:center">
      <div id="confirm-modal-icon" style="width:54px;height:54px;border-radius:50%;background:rgba(239, 68, 68, 0.15);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px auto;border:1px solid rgba(239, 68, 68, 0.3)">
        🗑️
      </div>
      <h3 id="confirm-modal-title" style="font-size:18px;font-weight:800;color:var(--t1);margin-bottom:8px">¿Eliminar Alumno?</h3>
      <div id="confirm-modal-msg" style="font-size:13.5px;color:var(--t2);line-height:1.5;margin:0">Esta acción no se puede deshacer.</div>
    </div>
    <div class="modal-footer" style="padding:16px 24px;display:flex;gap:12px;justify-content:center;background:rgba(15, 23, 42, 0.6);border-top:1px solid #1e293b">
      <button type="button" class="btn btn-secondary" id="confirm-modal-cancel" style="flex:1;padding:10px 16px;font-size:13.5px;border-radius:10px">Cancelar</button>
      <button type="button" class="btn btn-danger" id="confirm-modal-btn" style="flex:1;padding:10px 16px;font-size:13.5px;font-weight:700;border-radius:10px">Sí, Eliminar</button>
    </div>
  </div>
</div>

<!-- ==================== MODAL: HISTORIAL DE MOVIMIENTOS Y LIQUIDACIONES DEL COACH ==================== -->
<div id="modal-coach-movimientos" class="modal-backdrop">
  <div class="modal-box" style="max-width:840px;width:100%;max-height:86vh;display:flex;flex-direction:column;border-radius:20px;background:var(--bg-inp);border:1px solid rgba(139,92,246,0.35);box-shadow:0 25px 60px rgba(0,0,0,0.85);margin:auto;overflow:hidden">
    <div style="background:linear-gradient(135deg, #1e1b4b, #0f172a);padding:18px 24px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(139,92,246,0.3);border:1px solid rgba(139,92,246,0.5);display:flex;align-items:center;justify-content:center;font-size:22px">
          💳
        </div>
        <div>
          <h3 id="mov-coach-nombre" style="font-size:18px;font-weight:800;color:var(--t1);margin:0">Movimientos & Pagos del Coach</h3>
          <div id="mov-coach-esquema" style="font-size:12px;color:#60a5fa;margin-top:2px">Esquema: ...</div>
        </div>
      </div>
      <button type="button" class="btn-close" onclick="closeModal('modal-coach-movimientos')">&times;</button>
    </div>

    <!-- Sub-tabs del Historial -->
    <div style="display:flex;gap:4px;padding:0 24px;background:rgba(0,0,0,0.25);border-bottom:1px solid var(--border);overflow-x:auto;flex-shrink:0">
      <button type="button" class="tab-ficha active" onclick="switchCoachMovTab('tab-mov-liq', this)">💵 1. Liquidaciones Recibidas</button>
      <button type="button" class="tab-ficha" onclick="switchCoachMovTab('tab-mov-cobros', this)">👥 2. Cobros a Alumnos</button>
      <button type="button" class="tab-ficha" onclick="switchCoachMovTab('tab-mov-stats', this)">📅 3. Estadísticas de Días</button>
    </div>

    <div class="modal-body" style="flex:1 1 auto;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:14px">
      <!-- TAB 1: LIQUIDACIONES -->
      <div id="tab-mov-liq" class="coach-mov-pane">
        <div class="card-header" style="padding:0 0 10px 0;display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:700;font-size:13.5px;color:var(--t1)">Liquidaciones y Honorarios Abonados por la Sede</span>
        </div>
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-mov-liq">
            <thead><tr><th>Fecha</th><th>Medio</th><th>Observaciones</th><th style="text-align:right">Monto Liquidado</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: COBROS A ALUMNOS -->
      <div id="tab-mov-cobros" class="coach-mov-pane" style="display:none">
        <div class="card-header" style="padding:0 0 10px 0">
          <span style="font-weight:700;font-size:13.5px;color:var(--t1)">Cuotas Recaudadas de Socios a Cargo</span>
        </div>
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-mov-cobros">
            <thead><tr><th>Fecha</th><th>Alumno</th><th>Plan</th><th>Medio</th><th style="text-align:right">Monto</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: ESTADÍSTICAS DE DÍAS -->
      <div id="tab-mov-stats" class="coach-mov-pane" style="display:none">
        <div class="grid g2" style="gap:12px;margin-bottom:14px">
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Días Activos este Mes</div>
            <div class="stat-value" id="mov-stats-dias" style="font-size:22px;color:var(--ok)">0</div>
            <div class="stat-sub">Jornadas con asistencias</div>
          </div>
          <div class="stat-card" style="padding:14px">
            <div class="stat-label">Total Clases / Asistencias</div>
            <div class="stat-value" id="mov-stats-asist" style="font-size:22px;color:#60a5fa">0</div>
            <div class="stat-sub">Socios atendidos en el mes</div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal-footer" style="padding:14px 24px;background:rgba(0,0,0,0.25);border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:flex-end;flex-shrink:0">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-coach-movimientos')">Cerrar</button>
    </div>
  </div>
</div>

<!-- ==================== MODAL: REGISTRAR ENTRENAMIENTO / CHECK-IN DE RUTINA ==================== -->
<div id="modal-checkin-rutina" class="modal-backdrop">
  <div class="modal-box" style="max-width:500px;border:1px solid rgba(16,185,129,0.4);border-radius:20px;background:var(--bg-inp);overflow:hidden">
    <div style="background:linear-gradient(135deg, rgba(16,185,129,0.25), rgba(59,130,246,0.2));padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.3);border:1px solid rgba(16,185,129,0.5);display:flex;align-items:center;justify-content:center;font-size:22px">
          🏋️‍♂️
        </div>
        <div>
          <h3 style="font-size:18px;font-weight:800;color:var(--t1);margin:0">Completar Entrenamiento de Hoy</h3>
          <div style="font-size:12px;color:var(--t2);margin-top:2px">Registrá tu sesión para que tu coach evalúe tu progreso</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeModal('modal-checkin-rutina')">&times;</button>
    </div>
    <form onsubmit="return submitRutinaCheckin(event)">
      <div class="modal-body" style="padding:22px 24px;display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="checkin-prog-id">
        <input type="hidden" id="checkin-dia-id">
        <input type="hidden" id="checkin-alu-id">

        <div class="form-group">
          <label class="form-label">Rutina / Día Entrenado *</label>
          <input id="checkin-rutina-nombre" class="inp" required style="font-weight:700;color:#38bdf8" placeholder="Ej: Día 1 - Pecho y Tríceps">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">⏱️ Duración de Sesión (min)</label>
            <input id="checkin-duracion" type="number" min="10" max="240" class="inp" value="55" required>
          </div>
          <div class="form-group">
            <label class="form-label">🔥 Nivel de Esfuerzo (RPE)</label>
            <select id="checkin-esfuerzo" class="inp" style="font-weight:700">
              <option value="5">⭐⭐⭐⭐⭐ 5/5 - Esfuerzo Máximo / Intenso</option>
              <option value="4" selected>⭐⭐⭐⭐ 4/5 - Esfuerzo Alto / Fuerte</option>
              <option value="3">⭐⭐⭐ 3/5 - Esfuerzo Moderado / Óptimo</option>
              <option value="2">⭐⭐ 2/5 - Esfuerzo Ligero / Recuperación</option>
              <option value="1">⭐ 1/5 - Muy Suave</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">📝 Notas Personales, Cargas & Sensaciones</label>
          <textarea id="checkin-obs" class="inp" rows="3" placeholder="Ej: Pude subir 2.5kg en press plano. Sentí buena congestión en tríceps."></textarea>
        </div>
      </div>
      <div class="modal-footer" style="padding:14px 24px;background:rgba(0,0,0,0.25);border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-checkin-rutina')">Cancelar</button>
        <button type="submit" class="btn btn-success" style="font-weight:800;box-shadow:0 4px 14px rgba(16,185,129,0.4)">
          ✅ Guardar Check-in de Entrenamiento
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==================== MODAL: DEVOLUCIÓN TÉCNICA / FEEDBACK DEL COACH ==================== -->
<div id="modal-coach-feedback" class="modal-backdrop">
  <div class="modal-box" style="max-width:500px;border:1px solid rgba(139,92,246,0.4);border-radius:20px;background:var(--bg-inp);overflow:hidden">
    <div style="background:linear-gradient(135deg, rgba(139,92,246,0.25), rgba(59,130,246,0.2));padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(139,92,246,0.3);border:1px solid rgba(139,92,246,0.5);display:flex;align-items:center;justify-content:center;font-size:22px">
          💬
        </div>
        <div>
          <h3 style="font-size:18px;font-weight:800;color:var(--t1);margin:0">Devolución Técnica al Alumno</h3>
          <div style="font-size:12px;color:var(--t2);margin-top:2px">Dejale feedback y consejos sobre su sesión de entrenamiento</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeModal('modal-coach-feedback')">&times;</button>
    </div>
    <form onsubmit="return submitCoachFeedback(event)">
      <div class="modal-body" style="padding:22px 24px;display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="feedback-checkin-id">
        <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:10px;padding:12px">
          <div id="feedback-target-info" style="font-size:13px;color:var(--t1);font-weight:700">Entrenamiento: ...</div>
          <div id="feedback-student-notes" style="font-size:12px;color:var(--t2);margin-top:4px">Notas del alumno: ...</div>
        </div>

        <div class="form-group">
          <label class="form-label">Mensaje / Feedback del Coach *</label>
          <textarea id="feedback-text" class="inp" rows="4" required placeholder="Ej: ¡Excelente sesión Florencia! Mantené ese ritmo. La próxima semana aumentamos 2.5kg en la última serie."></textarea>
        </div>
      </div>
      <div class="modal-footer" style="padding:14px 24px;background:rgba(0,0,0,0.25);border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-coach-feedback')">Cancelar</button>
        <button type="submit" class="btn btn-primary" style="font-weight:800">💾 Guardar Feedback</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================== MODAL: COMPROBANTE / RECIBO DIGITAL DE PAGO ==================== -->
<div id="modal-recibo-alumno" class="modal-backdrop" onclick="if(event.target===this)closeModal('modal-recibo-alumno')">
  <div class="modal-box recibo-ticket-box">
    <button type="button" class="btn-close no-print" style="position:absolute;top:14px;right:16px;z-index:10;color:#64748b;font-size:24px;cursor:pointer;background:transparent;border:none" onclick="closeModal('modal-recibo-alumno')">&times;</button>
    <div class="recibo-header">
      <div style="font-size:36px;margin-bottom:6px">🧾</div>
      <h3 style="font-size:18px;font-weight:900;margin:0">Comprobante de Pago Oficial</h3>
      <div id="recibo-gym-nombre" style="font-size:13.5px;font-weight:800;margin-top:3px">Olympus Gym Pro</div>
    </div>
    <div class="modal-body recibo-body">
      <div class="recibo-row">
        <span>Nro. de Recibo:</span>
        <b id="recibo-nro">#REC-00123</b>
      </div>
      <div class="recibo-row">
        <span id="recibo-lbl-titular">Socio / Titular:</span>
        <b id="recibo-alumno-nombre">Florencia Carreño</b>
      </div>
      <div class="recibo-row">
        <span id="recibo-lbl-plan">Plan / Concepto:</span>
        <b id="recibo-plan">Plan 3x por Semana</b>
      </div>
      <div class="recibo-row">
        <span>Fecha de Pago:</span>
        <b id="recibo-fecha">20/08/2026</b>
      </div>
      <div class="recibo-row">
        <span>Medio de Pago:</span>
        <b id="recibo-medio">Transferencia</b>
      </div>
      <div class="recibo-row">
        <span>Observaciones / Detalle:</span>
        <b id="recibo-obs">-</b>
      </div>
      <div class="recibo-total-box">
        <span class="recibo-total-lbl">IMPORTE ABONADO:</span>
        <span id="recibo-monto" class="recibo-total-val">$ 22.000,00</span>
      </div>
      <div class="recibo-footer-info">
        Comprobante no válido como factura fiscal • Emitido por NitSoft
      </div>
    </div>
    <div class="modal-footer no-print" style="padding:14px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;gap:10px;justify-content:space-between">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-recibo-alumno')">Cerrar</button>
      <button type="button" class="btn btn-primary" onclick="window.print()" style="font-weight:800">🖨️ Imprimir Recibo</button>
    </div>
  </div>
</div>

<!-- ==================== MODAL: SELECTOR DE SIMULACIÓN MULTI-ROL (SUPERADMIN) ==================== -->
<div id="modal-simulation-switcher" class="modal-backdrop">
  <div class="modal-box" style="max-width:540px;border:1px solid #3b82f6;box-shadow:0 25px 70px rgba(0, 0, 0, 0.85);border-radius:20px;background:var(--bg-inp);overflow:hidden">
    <div style="background:linear-gradient(135deg, rgba(59,130,246,0.25), rgba(139,92,246,0.2));padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,0.3);border:1px solid rgba(59,130,246,0.5);display:flex;align-items:center;justify-content:center;font-size:22px">
          🎭
        </div>
        <div>
          <h3 style="font-size:18px;font-weight:800;color:var(--t1);margin:0">Simular Perspectiva de Usuario</h3>
          <div style="font-size:12.5px;color:var(--t2);margin-top:2px">Experimentá el sistema exactamente como lo ve cada rol</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeModal('modal-simulation-switcher')">&times;</button>
    </div>

    <div class="modal-body" style="padding:22px 24px;display:flex;flex-direction:column;gap:16px">
      <p style="font-size:13px;color:var(--t2);margin:0;line-height:1.5">
        Elegí un rol y una sede para navegar y probar todas las vistas con sus datos reales (carnet digital, rutinas, ingresos, caja, etc.).
      </p>

      <div>
        <label class="form-label" style="font-weight:800;color:var(--t1)">1. Seleccionar Rol a Experimentar:</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
          <button type="button" class="btn btn-secondary sim-role-btn" data-sim-role="admin_general" onclick="selectSimRole('admin_general')" style="justify-content:flex-start;padding:12px;text-align:left;border-radius:12px">
            <div>
              <div style="font-size:13.5px;font-weight:800;color:var(--t1)">👑 SuperAdmin SaaS</div>
              <div style="font-size:11px;color:var(--t-mut);margin-top:2px">Control global de sedes</div>
            </div>
          </button>
          <button type="button" class="btn btn-secondary sim-role-btn" data-sim-role="dueno" onclick="selectSimRole('dueno')" style="justify-content:flex-start;padding:12px;text-align:left;border-radius:12px">
            <div>
              <div style="font-size:13.5px;font-weight:800;color:var(--t1)">🏢 Dueño de Gimnasio</div>
              <div style="font-size:11px;color:var(--t-mut);margin-top:2px">Caja, socios, coaches</div>
            </div>
          </button>
          <button type="button" class="btn btn-secondary sim-role-btn" data-sim-role="coach" onclick="selectSimRole('coach')" style="justify-content:flex-start;padding:12px;text-align:left;border-radius:12px">
            <div>
              <div style="font-size:13.5px;font-weight:800;color:var(--t1)">🏋️ Coach / Profe</div>
              <div style="font-size:11px;color:var(--t-mut);margin-top:2px">Alumnos, cobros, rutinas</div>
            </div>
          </button>
          <button type="button" class="btn btn-secondary sim-role-btn" data-sim-role="alumno" onclick="selectSimRole('alumno')" style="justify-content:flex-start;padding:12px;text-align:left;border-radius:12px">
            <div>
              <div style="font-size:13.5px;font-weight:800;color:var(--t1)">🎓 Alumno / Socio</div>
              <div style="font-size:11px;color:var(--t-mut);margin-top:2px">Carnet, QR, cuota, plan</div>
            </div>
          </button>
        </div>
      </div>

      <div id="sim-gym-group" style="display:none">
        <label class="form-label" style="font-weight:800;color:var(--t1)">2. Seleccionar Sede / Gimnasio:</label>
        <select id="sim-select-gym" class="inp" onchange="onSimGymChange(this.value)" style="background:#1e293b;font-weight:700">
          <!-- Dinámico -->
        </select>
      </div>

      <div id="sim-coach-group" style="display:none">
        <label class="form-label" style="font-weight:800;color:var(--t1)">3. Seleccionar Coach / Entrenador:</label>
        <select id="sim-select-coach" class="inp" style="background:#1e293b;font-weight:700">
          <!-- Dinámico -->
        </select>
      </div>

      <div id="sim-alumno-group" style="display:none">
        <label class="form-label" style="font-weight:800;color:var(--t1)">3. Seleccionar Alumno / Socio:</label>
        <select id="sim-select-alumno" class="inp" style="background:#1e293b;font-weight:700">
          <!-- Dinámico -->
        </select>
      </div>
    </div>

    <div class="modal-footer" style="padding:14px 24px;background:rgba(0,0,0,0.25);border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-simulation-switcher')">Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="applyRoleSimulation()" style="font-weight:800;padding:10px 20px;box-shadow:0 4px 14px rgba(59,130,246,0.4)">
        🚀 Activar Perspectiva
      </button>
    </div>
  </div>
</div>

<!-- ==================== MODAL: SIMULADOR DE DISPOSITIVOS RESPONSIVE ==================== -->
<div id="modal-device-simulator" class="simulator-overlay">
  <!-- Toolbar Superior del Simulador -->
  <div class="simulator-toolbar">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:20px">📱</span>
        <strong style="font-size:13.5px;color:var(--t1)">Simulador de Marco</strong>
      </div>
      
      <select id="sim-device-select" class="inp" style="width:auto;min-width:200px;padding:6px 10px;font-size:12px;font-weight:700;background:#1e293b;border-color:#334155" onchange="setSimulatorDevice(this.value)">
        <option value="iphone-15">📱 iPhone 14/15 Pro (393 × 852)</option>
        <option value="iphone-se">📱 iPhone SE Compacto (375 × 667)</option>
        <option value="galaxy-s23">📱 Samsung Galaxy S23 (412 × 915)</option>
        <option value="iphone-max">📱 iPhone 15 Pro Max (430 × 932)</option>
        <option value="ipad-mini">📟 iPad Mini / Tablet 8" (768 × 1024)</option>
        <option value="ipad-pro">📟 iPad Pro 11" (834 × 1194)</option>
        <option value="laptop">💻 Laptop Compacta 13" (1024 × 680)</option>
      </select>

      <button class="btn btn-secondary btn-sm" onclick="toggleSimulatorOrientation()" title="Rotar pantalla vertical / horizontal" style="font-weight:700;font-size:12px;padding:6px 10px">
        🔄 <span id="sim-orientation-lbl">Horizontal</span>
      </button>

      <div style="display:flex;align-items:center;gap:4px;background:#1e293b;padding:2px 6px;border-radius:8px;border:1px solid #334155">
        <span style="font-size:11px;color:var(--t2);font-weight:700;margin-right:2px">Zoom:</span>
        <button class="btn btn-xs btn-secondary" onclick="setSimulatorZoom(0.70)">70%</button>
        <button class="btn btn-xs btn-secondary" onclick="setSimulatorZoom(0.85)">85%</button>
        <button class="btn btn-xs btn-primary" onclick="setSimulatorZoom(1.0)">100%</button>
      </div>

      <div style="display:flex;align-items:center;gap:4px;background:#1e293b;padding:2px 6px;border-radius:8px;border:1px solid #334155">
        <span style="font-size:11px;color:#c084fc;font-weight:800;margin-right:2px">🎭 Rol:</span>
        <select id="sim-frame-role-select" class="inp" style="width:auto;padding:3px 8px;font-size:11.5px;font-weight:700;background:var(--bg-inp);border-color:#334155;color:var(--t1)" onchange="switchSimFrameRole(this.value)">
          <option value="admin_general">👑 SuperAdmin</option>
          <option value="dueno">🏢 Dueño</option>
          <option value="coach">🏋️ Coach</option>
          <option value="alumno">🎓 Alumno</option>
        </select>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:8px">
      <span id="sim-res-badge" class="badge b-info" style="font-size:11px;font-weight:800">393 × 852 px</span>
      <button class="btn btn-secondary btn-sm" onclick="reloadSimulatorIframe()" title="Recargar vista">🔄</button>
      <button class="btn btn-danger btn-sm" onclick="closeDeviceSimulator()" style="font-weight:800;padding:6px 14px">✕ Salir</button>
    </div>
  </div>

  <!-- Contenedor del Chasis / Marco de Dispositivo -->
  <div style="display:flex;justify-content:center;align-items:flex-start;width:100%;flex:1;overflow:auto;padding-bottom:30px">
    <div id="sim-wrapper" style="transform-origin: top center; transition: transform 0.25s ease">
      <div id="device-frame" class="device-chassis" style="width:393px;height:852px">
        <div id="device-notch" class="device-notch"></div>
        <iframe id="sim-iframe" class="device-iframe" src="about:blank"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- BARRA FLOTANTE DE MODO MÓVIL DIRECTO EN PANTALLA -->
<div id="live-device-bar" class="live-device-bar" style="position:fixed;bottom:14px;left:50%;transform:translateX(-50%);z-index:999999;background:rgba(15,23,42,0.96);border:1.5px solid rgba(139,92,246,0.6);box-shadow:0 20px 60px rgba(0,0,0,0.9),0 0 30px rgba(139,92,246,0.3);border-radius:18px;padding:8px 16px;display:none;align-items:center;gap:12px;backdrop-filter:blur(16px);flex-wrap:wrap;max-width:96vw;">
  <div style="display:flex;align-items:center;gap:8px">
    <span style="font-size:16px">📱</span>
    <span style="font-size:12.5px;font-weight:800;color:var(--t1)">Simulador Móvil:</span>
    <span id="live-device-badge" class="badge b-purple" style="font-size:11px;font-weight:800">iPhone 15 Pro (393px)</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
    <button type="button" class="btn btn-xs btn-secondary btn-live-sub" data-device-mode="fullscreen" onclick="setLiveDeviceMode('fullscreen')" style="font-weight:700">🖥️ Escritorio</button>
    <button type="button" class="btn btn-xs btn-secondary btn-live-sub" data-device-mode="iphone" onclick="setLiveDeviceMode('iphone')" style="font-weight:700">📱 iPhone 15 (393px)</button>
    <button type="button" class="btn btn-xs btn-secondary btn-live-sub" data-device-mode="se" onclick="setLiveDeviceMode('se')" style="font-weight:700">📱 Compacto SE (360px)</button>
    <button type="button" class="btn btn-xs btn-secondary btn-live-sub" data-device-mode="tablet" onclick="setLiveDeviceMode('tablet')" style="font-weight:700">📟 Tablet (768px)</button>
    <button type="button" class="btn btn-xs btn-secondary" onclick="openDeviceSimulator()" style="font-weight:800;border-color:#34d399;color:#34d399">📟 Marco</button>
    <button type="button" class="btn btn-xs btn-primary" onclick="openSimulationModal()" style="font-weight:800">🎭 Rol</button>
    <button type="button" class="btn btn-xs btn-danger" onclick="exitSimulationMode()" style="font-weight:900;padding:6px 14px;background:#ef4444;border-color:#dc2626;box-shadow:0 0 14px rgba(239,68,68,0.5)">✕ Salir de Simulación (Esc)</button>
  </div>
</div>

<!-- TOAST -->
<div id="toast"></div>

<!-- ==================== FRONTEND CONTROLLER (JS) ==================== -->
