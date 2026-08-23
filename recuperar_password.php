<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Recuperar Contraseña - GYM PRO SaaS';
$errores   = [];
$exito     = '';
$paso      = 1; // Paso 1: Identificar usuario | Paso 2: Exito

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    $dni_raw       = trim($_POST['dni'] ?? '');
    $dniClean      = preg_replace('/\D/', '', $dni_raw);
    $newPassword   = $_POST['password'] ?? '';
    $newPassword2  = $_POST['password2'] ?? '';

    if ($identificador === '' || $dni_raw === '' || $newPassword === '' || $newPassword2 === '') {
        $errores[] = 'Todos los campos son obligatorios.';
    }

    if ($dni_raw !== '' && (strlen($dniClean) < 7 || strlen($dniClean) > 9)) {
        $errores[] = 'El DNI debe contener entre 7 y 9 dígitos numéricos.';
    }

    if ($newPassword !== $newPassword2) {
        $errores[] = 'Las nuevas contraseñas no coinciden.';
    } else {
        $passValidation = validatePasswordStrength($newPassword);
        if (!$passValidation['ok']) {
            foreach ($passValidation['errores'] as $err) {
                $errores[] = $err;
            }
        }
    }

    if (!$errores) {
        // Buscar usuario en base de datos
        $st = $pdo->prepare("
            SELECT u.id, u.nombre_usuario, u.email, u.rol, u.gimnasio_id, u.alumno_id, u.profesor_id, u.activo,
                   a.dni AS alumno_dni, a.nombre AS alumno_nombre,
                   p.nombre AS profesor_nombre,
                   g.nombre AS gimnasio_nombre, g.telefono AS gimnasio_telefono
            FROM users u
            LEFT JOIN alumnos a ON a.id = u.alumno_id
            LEFT JOIN profesores p ON p.id = u.profesor_id
            LEFT JOIN gimnasios g ON g.id = u.gimnasio_id
            WHERE (u.email = ? OR u.nombre_usuario = ?)
            LIMIT 1
        ");
        $st->execute([$identificador, $identificador]);
        $usuario = $st->fetch();

        if (!$usuario) {
            $errores[] = 'No se encontró ninguna cuenta con ese usuario o correo electrónico.';
        } elseif ((int)$usuario['activo'] !== 1) {
            $errores[] = 'Tu cuenta se encuentra desactivada. Contactá a la administración del gimnasio.';
        } else {
            // Validar identidad del titular por DNI
            $dniValido = true;

            if ($usuario['alumno_id'] && !empty($usuario['alumno_dni'])) {
                // Alumno con DNI cargado en su ficha
                $dniValido = ($dniClean === preg_replace('/\D/', '', $usuario['alumno_dni']));
            }

            if (!$dniValido) {
                $errores[] = 'El DNI ingresado no coincide con el DNI registrado en la ficha de alumno.';
            } else {
                // Actualizar contraseña con BCrypt Hash
                $hash = hashPassword($newPassword);
                $up = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $up->execute([$hash, $usuario['id']]);

                // Limpiar intentos de fuerza bruta en login si estaban bloqueados
                clearLoginAttempts($usuario['nombre_usuario'], $pdo);
                clearLoginAttempts($usuario['email'], $pdo);

                $nombreTitular = $usuario['alumno_nombre'] ?: ($usuario['profesor_nombre'] ?: $usuario['nombre_usuario']);
                $exito = "🎉 ¡Contraseña restablecida exitosamente para <b>" . htmlspecialchars($nombreTitular) . "</b> (Usuario: <b>" . htmlspecialchars($usuario['nombre_usuario']) . "</b>)! Ya podés iniciar sesión con tu nueva clave.";
                $paso = 2;
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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg-main: #090d16;
    --bg-card: #131b2e;
    --bg-inp: #1b2640;
    --border: #263554;
    --pri: #3b82f6;
    --sec: #8b5cf6;
    --ok: #10b981;
    --t1: #f8fafc;
    --t2: #94a3b8;
    --t-mut: #64748b;
    --err: #ef4444;
    --r: 12px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
  body {
    background: radial-gradient(circle at 50% 0%, #1e1b4b 0%, var(--bg-main) 70%);
    color: var(--t1);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .auth-shell { width: 100%; max-width: 480px; }
  .auth-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px 28px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .auth-title { font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
  .auth-sub { color: var(--t2); font-size: 13.5px; margin-bottom: 20px; line-height: 1.4; }
  .auth-form-group { margin-bottom: 15px; }
  .auth-label { display: block; font-size: 13px; font-weight: 600; color: var(--t2); margin-bottom: 6px; }
  .inp {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-inp);
    border: 1px solid var(--border);
    border-radius: var(--r);
    color: #fff;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
  }
  .inp:focus {
    border-color: var(--pri);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
    background: #202d4b;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 13px 18px;
    border-radius: var(--r);
    border: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.2s ease;
    text-decoration: none;
  }
  .btn-primary {
    background: linear-gradient(135deg, var(--pri), var(--sec));
    color: #fff;
    margin-top: 6px;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
  }
  .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
  .alert { border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.4; }
  .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
  .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #86efac; }
  .auth-footer { margin-top: 20px; font-size: 13px; color: var(--t-mut); text-align: center; }
  .auth-footer a { color: var(--pri); text-decoration: none; font-weight: 600; }
  .auth-footer a:hover { text-decoration: underline; }
  
  .help-box {
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed var(--border);
    border-radius: var(--r);
    padding: 14px;
    margin-top: 20px;
    font-size: 12.5px;
    color: var(--t2);
    line-height: 1.4;
  }
  .help-box strong { color: #fff; }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="font-size:24px">🔑</div>
      <div>
        <div class="auth-title">Recuperar Contraseña</div>
        <div class="auth-sub" style="margin-bottom:0">Validá tu identidad con tu DNI para definir una nueva clave.</div>
      </div>
    </div>

    <?php if ($errores): ?>
      <div class="alert alert-error">
        <?php foreach ($errores as $e): ?>
          <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($exito): ?>
      <div class="alert alert-success">
        <div style="line-height:1.5"><?= $exito ?></div>
        <div style="margin-top:14px">
          <a href="login.php" class="btn btn-primary" style="background:#10b981;font-size:14px;padding:11px 16px">👉 Iniciar Sesión con mi Nueva Clave</a>
        </div>
      </div>
    <?php else: ?>

    <form method="post" action="recuperar_password.php">
      <div class="auth-form-group">
        <label class="auth-label">Usuario o Correo Electrónico *</label>
        <input class="inp" type="text" name="identificador" required placeholder="Ej: flor7108 o tu@email.com"
               value="<?= htmlspecialchars($_POST['identificador'] ?? '') ?>" autofocus>
      </div>

      <div class="auth-form-group">
        <label class="auth-label">DNI / Documento Registrado *</label>
        <input class="inp" type="text" name="dni" required placeholder="Ej: 38456789 (Sin puntos)"
               value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>"
               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
        <div style="font-size:11px;color:var(--t2);margin-top:4px">
          🛡️ Validación de seguridad por titularidad de carnet.
        </div>
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Nueva Contraseña (8-20 car., Mayús, Minús, Núm, Símbolo) *</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="rec-pass1" class="inp" type="password" name="password" required placeholder="Mínimo 8 caracteres" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('rec-pass1', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Confirmar Nueva Contraseña *</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="rec-pass2" class="inp" type="password" name="password2" required placeholder="Repetir nueva contraseña" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('rec-pass2', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>

      <button class="btn btn-primary" type="submit">Restablecer Contraseña</button>
    </form>

    <div class="help-box">
      <strong>💬 ¿No recordás tu DNI o usuario?</strong><br>
      Pedile al dueño o profesor de tu gimnasio que blanquee o actualice tu clave directamente desde su panel de control.
    </div>

    <?php endif; ?>

    <div class="auth-footer">
      ¿Te acordaste de tu clave? <a href="logout.php">Volver a Iniciar Sesión</a>
    </div>
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
</script>
</body>
</html>
