<?php
require_once __DIR__ . '/config.php';

// Si ya está logueado, redirigir directo al panel
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Iniciar Sesión - GYM Pro SaaS';
$errores   = [];
$bloqueo   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. Verificación de Rate Limiting (Máx 5 intentos / 60 seg)
    $rateStatus = checkRateLimit($login, $pdo);
    if ($rateStatus['bloqueado']) {
        $errores[] = $rateStatus['mensaje'];
        $bloqueo   = $rateStatus['segundos'];
    } elseif ($login === '' || $password === '') {
        $errores[] = 'Completá tu usuario o email y la contraseña.';
    } else {
        // 2. Consulta de usuario protegida contra SQL Injection
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre_usuario, u.email, u.password_hash, u.activo, u.rol, u.is_superadmin, u.gimnasio_id, u.profesor_id, u.alumno_id,
                    a.nombre AS alumno_nombre, p.nombre AS profesor_nombre
             FROM users u
             LEFT JOIN alumnos a ON a.id = u.alumno_id
             LEFT JOIN profesores p ON p.id = u.profesor_id
             WHERE u.email = ? OR u.nombre_usuario = ?
             LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            recordFailedAttempt($login, $pdo);
            $errores[] = 'Usuario o contraseña incorrectos.';
        } elseif ((int)$usuario['activo'] !== 1) {
            $errores[] = 'Tu cuenta se encuentra desactivada. Contactá al Administrador General.';
        } else {
            // Contraseña blanqueada
            if (empty($usuario['password_hash'])) {
                $_SESSION['pending_user_id']   = $usuario['id'];
                $_SESSION['pending_user_name'] = $usuario['nombre_usuario'];
                header('Location: set_password.php');
                exit;
            }

            // 3. Verificación de Contraseña con BCrypt & Salt
            if (verifyPassword($password, $usuario['password_hash'])) {
                // Éxito: Limpiar intentos de fuerza bruta
                clearLoginAttempts($login, $pdo);

                // Regenerar ID de sesión contra ataques de fijación de sesión
                session_regenerate_id(true);

                // Cargar Claims del usuario en sesión
                $_SESSION['user_id']       = (int)$usuario['id'];
                $_SESSION['user_name']     = $usuario['alumno_nombre'] ?: ($usuario['profesor_nombre'] ?: $usuario['nombre_usuario']);
                $_SESSION['login_handle']  = $usuario['nombre_usuario'];
                $_SESSION['user_email']    = $usuario['email'];
                $_SESSION['user_role']     = $usuario['rol'] ?? ROLE_DUENO;
                $_SESSION['is_superadmin'] = ((int)($usuario['is_superadmin'] ?? 0) === 1) || ($usuario['rol'] === ROLE_ADMIN_GENERAL);
                $_SESSION['gimnasio_id']   = (int)($usuario['gimnasio_id'] ?? 1);
                $_SESSION['profesor_id']   = $usuario['profesor_id'];
                $_SESSION['alumno_id']     = $usuario['alumno_id'];

                header('Location: index.php');
                exit;
            } else {
                recordFailedAttempt($login, $pdo);
                $errores[] = 'Usuario o contraseña incorrectos.';
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
    --t-mut: #64748b;
    --r: 12px;
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
  .auth-shell{ width: 100%; max-width: 520px; }
  .auth-logo{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 22px;
  }
  .auth-logo-icon{
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--pri), var(--sec));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 800;
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
  }
  .auth-logo-text{ display: flex; flex-direction: column; }
  .auth-logo-text span:first-child{ font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
  .auth-logo-text span:last-child{ font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--pri); }

  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px 26px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .auth-title{ font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
  .auth-sub{ color: var(--t2); font-size: 13px; margin-bottom: 20px; }

  .auth-form-group{ margin-bottom: 16px; }
  .auth-label{ display: block; font-size: 13px; font-weight: 600; color: var(--t2); margin-bottom: 6px; }
  .inp{
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
  .inp:focus{
    border-color: var(--pri);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
    background: #202d4b;
  }
  .btn{
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
  .btn-primary{
    background: linear-gradient(135deg, var(--pri), var(--sec));
    color: #fff;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
    margin-top: 6px;
  }
  .btn-primary:hover{ filter: brightness(1.1); transform: translateY(-2px); }

  .alert{
    border-radius: var(--r);
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 16px;
    line-height: 1.4;
  }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }

  /* Roles Selector / Demo Pills */
  .roles-box{
    margin-top: 22px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed var(--border);
    border-radius: 14px;
  }
  .roles-header{
    font-size: 12px;
    font-weight: 700;
    color: var(--t2);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .roles-grid{
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  .role-btn{
    background: var(--bg-inp);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .role-btn:hover{ border-color: var(--pri); background: #202d4b; transform: translateY(-1px); }
  .role-btn strong{ display: block; font-size: 12px; color: #fff; margin-bottom: 2px; }
  .role-btn span{ font-size: 11px; color: var(--pri); font-weight: 600; }
  .role-btn small{ display: block; font-size: 10px; color: var(--t-mut); margin-top: 2px; }

  .auth-footer{ margin-top: 18px; font-size: 13px; color: var(--t-mut); text-align: center; }
  .auth-footer a{ color: var(--pri); text-decoration: none; font-weight: 600; }
  .auth-footer a:hover{ text-decoration: underline; }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-logo">
    <div class="auth-logo-icon">🏋️</div>
    <div class="auth-logo-text">
      <span>GYM PRO SaaS</span>
      <span>Sistema Multi-Rol (4 Roles)</span>
    </div>
  </div>
  
  <div class="auth-card">
    <div class="auth-title">Iniciar Sesión</div>
    <div class="auth-sub">Ingresá con tu cuenta según tu rol asignado.</div>

    <?php if ($errores): ?>
      <div class="alert alert-error">
        <?php foreach ($errores as $e): ?>
          <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php" id="login-form">
      <div class="auth-form-group">
        <label class="auth-label">Usuario o Correo</label>
        <input id="inp-login" class="inp" type="text" name="login" required placeholder="superadmin o tu@email.com"
               value="<?= htmlspecialchars($_POST['login'] ?? 'superadmin') ?>" autofocus>
      </div>

      <div class="auth-form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <label class="auth-label" style="margin-bottom:0">Contraseña</label>
          <a href="recuperar_password.php" style="font-size:12px;color:var(--pri);text-decoration:none;font-weight:600">¿Olvidaste tu contraseña?</a>
        </div>
        <input id="inp-pass" class="inp" type="password" name="password" required placeholder="••••••••" value="admin123">
      </div>

      <button class="btn btn-primary" type="submit">Autenticar e Ingresar</button>
    </form>

    <!-- ACCESOS RÁPIDOS POR ROL PARA PRUEBAS -->
    <div class="roles-box">
      <div class="roles-header">
        <span>🛡️</span>
        <span>Acceso rápido a los 4 roles del sistema:</span>
      </div>
      <div class="roles-grid">
        <div class="role-btn" onclick="fillCreds('superadmin', 'admin123')">
          <strong>👑 Admin General</strong>
          <span>superadmin</span>
          <small>Control SaaS, Dueños y Pagos</small>
        </div>
        <div class="role-btn" onclick="fillCreds('dueno_carlos', 'dueno123')">
          <strong>🏢 Dueño de Gym</strong>
          <span>Carlos</span>
          <small>Olympus Gym Pro (Activo)</small>
        </div>
        <div class="role-btn" onclick="fillCreds('coach_gaston', 'coach123')">
          <strong>🏋️ Coach</strong>
          <span>coach_gaston</span>
          <small>Rutinas, Dietas e Ingresos</small>
        </div>
        <div class="role-btn" onclick="fillCreds('alumno_florencia', 'alumno123')">
          <strong>👤 Alumno</strong>
          <span>alumno_florencia</span>
          <small>Carnet, Rutina y Asistencias</small>
        </div>
      </div>

      <div style="margin-top:10px;text-align:center">
        <button type="button" class="role-btn" style="width:100%;border-color:#ef4444;background:rgba(239,68,68,0.08)" onclick="fillCreds('dueno_ramiro', 'dueno123')">
          <strong style="color:#f87171">🚫 Probar Dueño Suspendido (Spartan Gym)</strong>
          <span style="color:#fca5a5">dueno_ramiro / dueno123</span>
        </button>
      </div>
    </div>

    <div class="auth-footer">
      Sistema de Gimnasio con RBAC, BCrypt y Rate Limiting
    </div>
  </div>
</div>

<script>
function fillCreds(u, p) {
  document.getElementById('inp-login').value = u;
  document.getElementById('inp-pass').value = p;
}
</script>
</body>
</html>
