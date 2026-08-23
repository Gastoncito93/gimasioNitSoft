<?php
require_once __DIR__ . '/proteger.php';

$pageTitle = 'Cambiar Contraseña - GYM Pro';
$errores   = [];
$exito     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['password_actual'] ?? '';
    $nueva  = $_POST['password_nueva'] ?? '';
    $nueva2 = $_POST['password_nueva2'] ?? '';

    if ($actual === '' || $nueva === '' || $nueva2 === '') {
        $errores[] = 'Todos los campos son obligatorios.';
    } elseif ($nueva !== $nueva2) {
        $errores[] = 'Las nuevas contraseñas no coinciden.';
    } else {
        $passValidation = validatePasswordStrength($nueva);
        if (!$passValidation['ok']) {
            foreach ($passValidation['errores'] as $err) {
                $errores[] = $err;
            }
        }
    }

    if (!$errores) {
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $st->execute([':id' => $userId]);
        $usuario = $st->fetch();

        if (!$usuario || empty($usuario['password_hash'])) {
            $errores[] = 'No se pudo verificar tu contraseña actual.';
        } elseif (!verifyPassword($actual, $usuario['password_hash'])) {
            $errores[] = 'La contraseña actual ingresada es incorrecta.';
        } else {
            // Generar nuevo hash BCrypt con Salt
            $hash = hashPassword($nueva);
            $up = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $up->execute([':h' => $hash, ':id' => $userId]);
            $exito = '¡Contraseña actualizada exitosamente!';
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
  .btn-secondary{ background: var(--bg-inp); color: var(--t2); border: 1px solid var(--border); margin-top: 8px; }
  .alert{ border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
  .alert-success{ background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #86efac; }

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
    <div class="auth-title">Cambiar Contraseña</div>
    <div class="auth-sub">
      Usuario: <strong style="color:var(--pri)"><?= htmlspecialchars($userName) ?></strong> (Rol: <b><?= htmlspecialchars(strtoupper($userRole)) ?></b>)
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
        <?= htmlspecialchars($exito) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="auth-form-group">
        <label class="auth-label">Contraseña Actual</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="cp-actual" class="inp" type="password" name="password_actual" required placeholder="Tu clave actual" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('cp-actual', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>
      <div class="auth-form-group">
        <label class="auth-label">Nueva Contraseña (8-20 car., Mayús, Minús, Núm, Símbolo)</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="cp-nueva" class="inp" type="password" name="password_nueva" required placeholder="Mínimo 8 caracteres" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('cp-nueva', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>
      <div class="auth-form-group">
        <label class="auth-label">Repetir Nueva Contraseña</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="cp-conf" class="inp" type="password" name="password_nueva2" required placeholder="Confirmar nueva clave" style="padding-right:44px">
          <button type="button" onclick="togglePasswordVisibility('cp-conf', this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t2);cursor:pointer;font-size:16px;padding:4px;display:flex;align-items:center;justify-content:center;z-index:2">
            👁️
          </button>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Actualizar con BCrypt</button>
      <a href="index.php" class="btn btn-secondary">← Volver al Panel</a>
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
</script>
</body>
</html>
