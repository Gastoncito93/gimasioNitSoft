<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Cambio Obligatorio de Contraseña - GYM PRO';

if (empty($_SESSION['pending_user_id'])) {
    header('Location: login.php');
    exit;
}

$errores        = [];
$nombrePendiente = $_SESSION['pending_user_name'] ?? '';
$isTempReason   = ($_GET['reason'] ?? '') === 'temp' || !empty($_SESSION['force_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password === '' || $password2 === '') {
        $errores[] = 'Debés completar ambos campos.';
    } elseif ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    } else {
        $passValidation = validatePasswordStrength($password);
        if (!$passValidation['ok']) {
            foreach ($passValidation['errores'] as $err) {
                $errores[] = $err;
            }
        }

        if (!$errores) {
            $userId = (int)$_SESSION['pending_user_id'];

            $st = $pdo->prepare('SELECT * FROM users WHERE id = :id AND activo = 1 LIMIT 1');
            $st->execute([':id' => $userId]);
            $usuario = $st->fetch();

            if (!$usuario) {
                $errores[] = 'El usuario ya no existe o fue desactivado.';
            } else {
                // BCrypt Hash con Salt Criptográfico
                $hash = hashPassword($password);

                $up = $pdo->prepare('UPDATE users SET password_hash = :h, debe_cambiar_password = 0 WHERE id = :id');
                $up->execute([':h' => $hash, ':id' => $userId]);

                // Traer datos complementarios (persona_nombre, gimnasio)
                $stFull = $pdo->prepare('
                    SELECT u.*, a.nombre AS alumno_nombre, p.nombre AS profesor_nombre
                    FROM users u
                    LEFT JOIN alumnos a ON a.id = u.alumno_id
                    LEFT JOIN profesores p ON p.id = u.profesor_id
                    WHERE u.id = ? LIMIT 1
                ');
                $stFull->execute([$userId]);
                $userRow = $stFull->fetch() ?: $usuario;

                // Iniciar sesión
                session_regenerate_id(true);
                $_SESSION['user_id']       = (int)$userRow['id'];
                $_SESSION['user_name']     = $userRow['alumno_nombre'] ?: ($userRow['profesor_nombre'] ?: $userRow['nombre_usuario']);
                $_SESSION['login_handle']  = $userRow['nombre_usuario'];
                $_SESSION['user_email']    = $userRow['email'];
                $_SESSION['user_role']     = $userRow['rol'] ?? ROLE_DUENO;
                $_SESSION['is_superadmin'] = ((int)($userRow['is_superadmin'] ?? 0) === 1) || ($userRow['rol'] === ROLE_ADMIN_GENERAL);
                $_SESSION['gimnasio_id']   = (int)($userRow['gimnasio_id'] ?? 1);
                $_SESSION['profesor_id']   = $userRow['profesor_id'];
                $_SESSION['alumno_id']     = $userRow['alumno_id'];

                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['force_change_password']);

                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  :root{
    --bg-main: #090d16;
    --bg-card: #131b2e;
    --bg-inp: #1b2640;
    --border: #263554;
    --pri: #3b82f6;
    --sec: #8b5cf6;
    --ok: #10b981;
    --warn: #f59e0b;
    --err: #ef4444;
    --t1: #f8fafc;
    --t2: #94a3b8;
    --r: 14px;
  }
  *{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;}
  body{
    background: radial-gradient(circle at 50% 0%, #1e1b4b 0%, var(--bg-main) 70%);
    color: var(--t1);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .auth-shell{ width: 100%; max-width: 460px; }
  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 34px 28px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .badge-temp{
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(139, 92, 246, 0.16);
    border: 1px solid rgba(139, 92, 246, 0.4);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    color: #c084fc;
    margin-bottom: 14px;
  }
  .auth-title{ font-size: 22px; font-weight: 900; margin-bottom: 8px; color: #fff; letter-spacing: -0.3px; }
  .auth-sub{ color: var(--t2); font-size: 13px; margin-bottom: 20px; line-height: 1.5; }
  .auth-form-group{ margin-bottom: 16px; }
  .auth-label{ display: block; font-size: 12.5px; font-weight: 700; color: var(--t2); margin-bottom: 6px; }
  .inp{
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-inp);
    border: 1px solid var(--border);
    border-radius: var(--r);
    color: #fff;
    font-size: 14px;
    outline: none;
    transition: all 0.2s;
  }
  .inp:focus{ border-color: var(--pri); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); }
  .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 13px 18px;
    border-radius: var(--r);
    border: none;
    cursor: pointer;
    font-weight: 800;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s;
  }
  .btn-primary{ background: linear-gradient(135deg, var(--pri), var(--sec)); color: #fff; margin-top: 10px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35); }
  .btn-primary:hover{ transform: translateY(-1px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5); }
  .alert{ border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.4; }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
  
  .req-list{
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 18px;
    font-size: 11.5px;
    color: var(--t2);
  }
  .req-item{ display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
  .req-item:last-child{ margin-bottom: 0; }

  @media(max-width: 480px) {
    body { padding: 14px 10px; }
    .auth-card { padding: 22px 16px; border-radius: 16px; }
    .auth-title { font-size: 19px; }
  }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    
    <div class="badge-temp">
      <span>🔑</span>
      <span>Primer Ingreso con Clave Temporal</span>
    </div>

    <div class="auth-title">Definir Nueva Contraseña</div>
    <div class="auth-sub">
      Hola <strong style="color:#60a5fa">@<?= htmlspecialchars($nombrePendiente) ?></strong>. Por motivos de seguridad, debés cambiar la contraseña temporal y definir tu <b>clave definitiva personal</b> para ingresar.
    </div>

    <?php if ($errores): ?>
      <div class="alert alert-error">
        <?php foreach ($errores as $e): ?>
          <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="req-list">
      <div style="font-weight:700;color:#fff;margin-bottom:6px">Requisitos de la nueva contraseña:</div>
      <div class="req-item" id="req-len"><span>🔹</span> Entre 8 y 20 caracteres</div>
      <div class="req-item" id="req-up"><span>🔹</span> Al menos una letra mayúscula (A-Z)</div>
      <div class="req-item" id="req-low"><span>🔹</span> Al menos una letra minúscula (a-z)</div>
      <div class="req-item" id="req-num"><span>🔹</span> Al menos un número (0-9)</div>
      <div class="req-item" id="req-sym"><span>🔹</span> Al menos un símbolo especial (! @ # $ % * - _ . ? & +)</div>
    </div>

    <form method="post" autocomplete="off" onsubmit="return validateForm(event)">
      <div class="auth-form-group">
        <label class="auth-label">Nueva Contraseña Definitiva *</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="pass1" class="inp" type="password" name="password" required placeholder="Ingresá tu nueva clave (Mínimo 8 caracteres)" autofocus oninput="checkStrength(this.value)" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('pass1', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>
      <div class="auth-form-group">
        <label class="auth-label">Confirmar Nueva Contraseña *</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="pass2" class="inp" type="password" name="password2" required placeholder="Repetí tu nueva clave" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('pass2', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">🔐 Guardar Clave y Acceder al Sistema</button>
    </form>
  </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.innerHTML = '🙈';
    btn.setAttribute('title', 'Ocultar contraseña');
  } else {
    inp.type = 'password';
    btn.innerHTML = '👁️';
    btn.setAttribute('title', 'Mostrar contraseña');
  }
}

function checkStrength(val) {
  const lenOk = val.length >= 8 && val.length <= 20;
  const upOk  = /[A-Z]/.test(val);
  const lowOk = /[a-z]/.test(val);
  const numOk = /[0-9]/.test(val);
  const symOk = /[^a-zA-Z0-9\s]/.test(val);

  document.getElementById('req-len').innerHTML = (lenOk ? '✅' : '🔹') + ' Entre 8 y 20 caracteres';
  document.getElementById('req-len').style.color = lenOk ? '#10b981' : 'var(--t2)';

  document.getElementById('req-up').innerHTML = (upOk ? '✅' : '🔹') + ' Al menos una letra mayúscula (A-Z)';
  document.getElementById('req-up').style.color = upOk ? '#10b981' : 'var(--t2)';

  document.getElementById('req-low').innerHTML = (lowOk ? '✅' : '🔹') + ' Al menos una letra minúscula (a-z)';
  document.getElementById('req-low').style.color = lowOk ? '#10b981' : 'var(--t2)';

  document.getElementById('req-num').innerHTML = (numOk ? '✅' : '🔹') + ' Al menos un número (0-9)';
  document.getElementById('req-num').style.color = numOk ? '#10b981' : 'var(--t2)';

  document.getElementById('req-sym').innerHTML = (symOk ? '✅' : '🔹') + ' Al menos un símbolo especial (! @ # $ % * - _ . ? & +)';
  document.getElementById('req-sym').style.color = symOk ? '#10b981' : 'var(--t2)';
}

function validateForm(e) {
  const p1 = document.getElementById('pass1').value;
  const p2 = document.getElementById('pass2').value;
  if (p1 !== p2) {
    alert('Las contraseñas no coinciden.');
    if (e) e.preventDefault();
    return false;
  }
  if (p1.length < 8 || p1.length > 20) {
    alert('La contraseña debe tener entre 8 y 20 caracteres.');
    if (e) e.preventDefault();
    return false;
  }
  if (!/[A-Z]/.test(p1)) {
    alert('La contraseña debe contener al menos una letra mayúscula (A-Z).');
    if (e) e.preventDefault();
    return false;
  }
  if (!/[a-z]/.test(p1)) {
    alert('La contraseña debe contener al menos una letra minúscula (a-z).');
    if (e) e.preventDefault();
    return false;
  }
  if (!/[0-9]/.test(p1)) {
    alert('La contraseña debe contener al menos un número (0-9).');
    if (e) e.preventDefault();
    return false;
  }
  if (!/[^a-zA-Z0-9\s]/.test(p1)) {
    alert('La contraseña debe contener al menos un símbolo especial (ej: ! @ # $ % * - _ . ? & +).');
    if (e) e.preventDefault();
    return false;
  }
  return true;
}
</script>
</body>
</html>
