<?php
require_once __DIR__ . '/proteger.php';

$pageTitle      = 'Blanquear Contraseña de Usuario - GYM Pro';
$errores        = [];
$exito          = '';
$usuarioBuscado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioBuscado = trim($_POST['usuario'] ?? '');

    if ($usuarioBuscado === '') {
        $errores[] = 'Ingresá un nombre de usuario o email.';
    } else {
        $st = $pdo->prepare(
            'SELECT id, nombre_usuario, email
             FROM users
             WHERE email = :u OR nombre_usuario = :u
             LIMIT 1'
        );
        $st->execute([':u' => $usuarioBuscado]);
        $usuario = $st->fetch();

        if (!$usuario) {
            $errores[] = 'No se encontró ningún usuario con ese nombre o correo.';
        } else {
            $up = $pdo->prepare(
                'UPDATE users
                 SET password_hash = NULL
                 WHERE id = :id'
            );
            $up->execute([':id' => $usuario['id']]);

            $exito = 'Contraseña blanqueada correctamente para: ' .
                     htmlspecialchars($usuario['nombre_usuario']) .
                     ' (' . htmlspecialchars($usuario['email']) . '). En su próximo inicio de sesión se le pedirá definir una nueva contraseña.';
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
  .auth-shell{width: 100%; max-width: 460px;}
  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px 28px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .auth-title{font-size: 24px; font-weight: 800; margin-bottom: 8px;}
  .auth-sub{color: var(--t2); font-size: 14px; margin-bottom: 24px; line-height: 1.5;}
  .auth-form-group{margin-bottom: 16px;}
  .auth-label{display: block; font-size: 13px; font-weight: 600; color: var(--t2); margin-bottom: 6px;}
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
  .inp:focus{
    border-color: var(--pri);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
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
    font-size: 15px;
    text-decoration: none;
  }
  .btn-primary{
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    margin-top: 8px;
  }
  .btn-secondary{
    background: var(--bg-inp);
    color: var(--t2);
    border: 1px solid var(--border);
    margin-top: 10px;
  }
  .alert{border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 18px;}
  .alert-error{background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5;}
  .alert-success{background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.4); color: #86efac;}
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-title">Blanquear Contraseña</div>
    <div class="auth-sub">
      Herramienta administrativa para resetear la contraseña de un usuario.
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
        <label class="auth-label">Usuario o Email</label>
        <input class="inp" type="text" name="usuario" required placeholder="Nombre de usuario o correo"
               value="<?= htmlspecialchars($usuarioBuscado) ?>">
      </div>
      <button class="btn btn-primary" type="submit">Blanquear Clave de Acceso</button>
      <a href="index.php" class="btn btn-secondary">← Volver al Panel</a>
    </form>
  </div>
</div>
</body>
</html>
