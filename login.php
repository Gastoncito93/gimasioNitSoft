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
            'SELECT u.id, u.nombre_usuario, u.email, u.password_hash, u.activo, u.debe_cambiar_password, u.rol, u.is_superadmin, u.gimnasio_id, u.profesor_id, u.alumno_id,
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
            $errores[] = '⛔ Tu cuenta de acceso se encuentra suspendida o bloqueada. Por favor comunicate con la administración de tu gimnasio.';
        } else {
            // Contraseña blanqueada
            if (empty($usuario['password_hash'])) {
                $_SESSION['pending_user_id']   = $usuario['id'];
                $_SESSION['pending_user_name'] = $usuario['nombre_usuario'];
                $_SESSION['force_change_password'] = 1;
                header('Location: set_password.php?reason=blank');
                exit;
            }

            // 3. Verificación de Contraseña con BCrypt & Salt
            if (verifyPassword($password, $usuario['password_hash'])) {
                // Éxito: Limpiar intentos de fuerza bruta
                clearLoginAttempts($login, $pdo);

                // Si el usuario ingresó con una contraseña temporal generada por el admin:
                if ((int)($usuario['debe_cambiar_password'] ?? 0) === 1) {
                    $_SESSION['pending_user_id']   = $usuario['id'];
                    $_SESSION['pending_user_name'] = $usuario['nombre_usuario'];
                    $_SESSION['force_change_password'] = 1;
                    header('Location: set_password.php?reason=temp');
                    exit;
                }

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
  .auth-shell{ width: 100%; max-width: 480px; margin-top: -35px; }
  .auth-logo{
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
  }
  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 28px 24px;
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
    gap: 8px;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 700;
    border-radius: var(--r);
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    text-decoration: none;
  }
  .btn-primary{
    background: linear-gradient(135deg, var(--pri), var(--sec));
    color: #fff;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
  }
  .btn-primary:hover{
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
  }

  .alert{
    border-radius: var(--r);
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 16px;
    line-height: 1.4;
  }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }

  .auth-footer{ margin-top: 18px; font-size: 12.5px; color: var(--t-mut); text-align: center; }
  .auth-footer a{ color: var(--pri); text-decoration: none; font-weight: 600; }
  .auth-footer a:hover{ text-decoration: underline; }

  @media(max-width: 480px) {
    body { padding: 14px 10px; }
    .auth-shell { margin-top: -15px; }
    .auth-card { padding: 22px 16px; border-radius: 16px; }
    .auth-title { font-size: 19px; }
  }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-logo">
    <img src="assets/img/MarcaPrincipal.png?v=<?= filemtime(__DIR__ . '/assets/img/MarcaPrincipal.png') ?>" alt="NitSoft Logo" style="width:410px;max-width:92vw;height:auto;max-height:360px;object-fit:contain;display:block;filter:drop-shadow(0 12px 30px rgba(0,0,0,0.35))">
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
        <input id="inp-login" class="inp" type="text" name="login" required placeholder="Tu usuario o email"
               value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" autocomplete="username" autofocus>
      </div>

      <div class="auth-form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <label class="auth-label" style="margin-bottom:0">Contraseña</label>
          <a href="recuperar_password.php" style="font-size:12px;color:var(--pri);text-decoration:none;font-weight:600">¿Olvidaste tu contraseña?</a>
        </div>
        <div style="position:relative;display:flex;align-items:center">
          <input id="inp-pass" class="inp" type="password" name="password" required placeholder="••••••••" value="" autocomplete="current-password" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('inp-pass', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>

      <button class="btn btn-primary" type="submit">Autenticar e Ingresar</button>
    </form>

    <div class="auth-footer">
      Sistema de Gestión
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
