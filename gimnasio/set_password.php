<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Definir Nueva Contraseña - GYM Pro';

if (empty($_SESSION['pending_user_id'])) {
    header('Location: login.php');
    exit;
}

$errores        = [];
$nombrePendiente = $_SESSION['pending_user_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password === '' || $password2 === '') {
        $errores[] = 'Debés completar ambos campos.';
    } elseif ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $userId = (int)$_SESSION['pending_user_id'];

        $st = $pdo->prepare('SELECT * FROM users WHERE id = :id AND activo = 1 LIMIT 1');
        $st->execute([':id' => $userId]);
        $usuario = $st->fetch();

        if (!$usuario) {
            $errores[] = 'El usuario ya no existe o fue desactivado.';
        } else {
            // BCrypt Hash con Salt
            $hash = hashPassword($password);

            $up = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $up->execute([':h' => $hash, ':id' => $userId]);

            // Iniciar sesión
            session_regenerate_id(true);
            $_SESSION['user_id']     = (int)$usuario['id'];
            $_SESSION['user_name']   = $usuario['nombre_usuario'];
            $_SESSION['user_email']  = $usuario['email'];
            $_SESSION['user_role']   = $usuario['rol'] ?? ROLE_ADMIN;
            $_SESSION['profesor_id'] = $usuario['profesor_id'];
            $_SESSION['alumno_id']   = $usuario['alumno_id'];

            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name']);

            header('Location: index.php');
            exit;
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
    --t1: #f8fafc;
    --t2: #94a3b8;
    --err: #ef4444;
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
  .auth-shell{ width: 100%; max-width: 440px; }
  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px 26px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .auth-title{ font-size: 22px; font-weight: 800; margin-bottom: 6px; }
  .auth-sub{ color: var(--t2); font-size: 13px; margin-bottom: 20px; }
  .auth-form-group{ margin-bottom: 14px; }
  .auth-label{ display: block; font-size: 13px; font-weight: 600; color: var(--t2); margin-bottom: 6px; }
  .inp{
    width: 100%;
    padding: 11px 14px;
    background: var(--bg-inp);
    border: 1px solid var(--border);
    border-radius: var(--r);
    color: #fff;
    font-size: 14px;
    outline: none;
  }
  .inp:focus{ border-color: var(--pri); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); }
  .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 12px 18px;
    border-radius: var(--r);
    border: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
  }
  .btn-primary{ background: linear-gradient(135deg, var(--pri), var(--sec)); color: #fff; margin-top: 8px; }
  .alert{ border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-title">Definir Contraseña</div>
    <div class="auth-sub">
      Usuario: <strong style="color:var(--pri)"><?= htmlspecialchars($nombrePendiente) ?></strong>
    </div>

    <?php if ($errores): ?>
      <div class="alert alert-error">
        <?php foreach ($errores as $e): ?>
          <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="auth-form-group">
        <label class="auth-label">Nueva Contraseña</label>
        <input class="inp" type="password" name="password" required placeholder="Mínimo 6 caracteres">
      </div>
      <div class="auth-form-group">
        <label class="auth-label">Repetir Nueva Contraseña</label>
        <input class="inp" type="password" name="password2" required placeholder="Confirmar clave">
      </div>
      <button class="btn btn-primary" type="submit">Guardar y Acceder</button>
    </form>
  </div>
</div>
</body>
</html>
