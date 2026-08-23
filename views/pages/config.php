<section id="page-config" style="display:none">
  <div class="card" style="max-width:800px;margin:0 auto">
    <div class="card-header">
      <div class="card-title">⚙️ Configuración y Personalización de la Sede</div>
    </div>
    <form onsubmit="return saveGymData(event)" style="padding:20px">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nombre de la Sede / Gimnasio *</label>
          <input id="cfg-nombre" class="inp" required>
        </div>
        <div class="form-group">
          <label class="form-label">Teléfono de Contacto</label>
          <input id="cfg-telefono" class="inp">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Oficial</label>
          <input id="cfg-email" type="email" class="inp">
        </div>
        <div class="form-group">
          <label class="form-label">Dirección / Ubicación</label>
          <input id="cfg-direccion" class="inp">
        </div>
      </div>
      <div style="text-align:right;margin-top:16px">
        <button type="submit" class="btn btn-primary" style="font-weight:800">💾 Guardar Configuración</button>
      </div>
    </form>
  </div>
</section>