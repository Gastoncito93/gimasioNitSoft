<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Crear Cuenta - GYM PRO SaaS';
$errores   = [];
$exito     = '';

// 1. Detección de Token de Invitación (Multi-Tenant Direct Invite)
$inviteToken = trim($_GET['invite'] ?? $_POST['invite_token'] ?? '');
$inviteGym   = null;
$assignedRole = ROLE_ALUMNO;

if ($inviteToken !== '') {
    // Buscar en tabla invitaciones
    $stInv = $pdo->prepare("
        SELECT inv.*, g.nombre AS gimnasio_nombre
        FROM invitaciones inv
        JOIN gimnasios g ON g.id = inv.gimnasio_id
        WHERE inv.token = ? AND (inv.expira_en IS NULL OR inv.expira_en > NOW()) AND inv.usos_restantes > 0
        LIMIT 1
    ");
    $stInv->execute([$inviteToken]);
    $invRow = $stInv->fetch();

    if ($invRow) {
        $inviteGym    = $invRow;
        $assignedRole = $invRow['rol'];
    } else {
        // Buscar por invite_code directo del gimnasio
        $stGym = $pdo->prepare("SELECT id AS gimnasio_id, nombre AS gimnasio_nombre FROM gimnasios WHERE invite_code = ? LIMIT 1");
        $stGym->execute([$inviteToken]);
        $gymRow = $stGym->fetch();
        if ($gymRow) {
            $inviteGym = $gymRow;
            $assignedRole = ROLE_ALUMNO;
        } else {
            $errores[] = 'El enlace o código de invitación no es válido o ha expirado.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $nombre_usuario  = strtolower(trim($_POST['nombre_usuario'] ?? ''));
    $dni_raw         = trim($_POST['dni'] ?? '');
    $dniClean        = preg_replace('/\D/', '', $dni_raw);
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $telefono        = trim($_POST['telefono'] ?? '');
    $password        = $_POST['password'] ?? '';
    $password2       = $_POST['password2'] ?? '';
    $gimnasio_id     = $inviteGym ? (int)$inviteGym['gimnasio_id'] : 1;

    if ($nombre_completo === '' || $nombre_usuario === '' || $dni_raw === '' || $email === '' || $password === '' || $password2 === '') {
        $errores[] = 'Todos los campos marcados con * son obligatorios.';
    }

    if (strlen($nombre_completo) < 3) {
        $errores[] = 'El nombre y apellido debe tener al menos 3 caracteres.';
    }

    if (!preg_match('/^[a-z0-9_.\-]{3,30}$/i', $nombre_usuario)) {
        $errores[] = 'El nombre de usuario para ingresar solo puede contener letras, números, puntos o guiones (entre 3 y 30 caracteres, sin espacios).';
    }

    if ($dni_raw !== '' && (strlen($dniClean) < 7 || strlen($dniClean) > 9)) {
        $errores[] = 'El DNI debe contener entre 7 y 9 dígitos numéricos.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }

    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (strlen($password) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if (!$errores) {
        $st = $pdo->prepare('SELECT id, email, nombre_usuario FROM users WHERE email = ? OR nombre_usuario = ? LIMIT 1');
        $st->execute([$email, $nombre_usuario]);
        $existingUser = $st->fetch();

        if ($existingUser) {
            if ($existingUser['nombre_usuario'] === $nombre_usuario) {
                $errores[] = "El nombre de usuario '{$nombre_usuario}' ya está en uso. Por favor elegí otro.";
            } else {
                $errores[] = "El correo electrónico '{$email}' ya se encuentra registrado. Podés iniciar sesión con tus credenciales.";
            }
        } else {
            $alumnoId = null;
            $profesorId = null;
            $vinculado = false;
            $nombreFicha = $nombre_completo;

            if ($assignedRole === ROLE_ALUMNO) {
                // 1. Buscar si ya existe una ficha de alumno con este DNI en la misma sede
                $stAlu = $pdo->prepare("SELECT id, nombre, email, telefono, dni FROM alumnos WHERE gimnasio_id = ? AND dni = ? LIMIT 1");
                $stAlu->execute([$gimnasio_id, $dniClean]);
                $existingAlu = $stAlu->fetch();

                if ($existingAlu) {
                    // Verificar que no tenga ya un usuario web asignado
                    $stUserCheck = $pdo->prepare("SELECT id, nombre_usuario FROM users WHERE alumno_id = ? LIMIT 1");
                    $stUserCheck->execute([$existingAlu['id']]);
                    if ($stUserCheck->fetch()) {
                        $errores[] = 'Ya existe una cuenta de usuario creada y vinculada con el DNI ' . htmlspecialchars($dniClean) . '. Si olvidaste tu contraseña, podés iniciar sesión o solicitar restablecerla.';
                    } else {
                        // VINCULAR ALUMNO EXISTENTE
                        $alumnoId = (int)$existingAlu['id'];
                        $nombreFicha = $existingAlu['nombre'];
                        $vinculado = true;

                        // Actualizar email y teléfono si la ficha no los tenía
                        $updAlu = $pdo->prepare("
                            UPDATE alumnos 
                            SET email = COALESCE(NULLIF(email, ''), ?), 
                                telefono = COALESCE(NULLIF(telefono, ''), ?) 
                            WHERE id = ?
                        ");
                        $updAlu->execute([$email, $telefono, $alumnoId]);
                    }
                } else {
                    // 2. Si no se encontró por DNI, verificar por nombre si tenía ficha sin DNI
                    $stAluNom = $pdo->prepare("SELECT id, nombre, email, telefono, dni FROM alumnos WHERE gimnasio_id = ? AND LOWER(TRIM(nombre)) = LOWER(?) LIMIT 1");
                    $stAluNom->execute([$gimnasio_id, $nombre_completo]);
                    $existingByName = $stAluNom->fetch();

                    if ($existingByName) {
                        $stUserCheck = $pdo->prepare("SELECT id FROM users WHERE alumno_id = ? LIMIT 1");
                        $stUserCheck->execute([$existingByName['id']]);
                        if (!$stUserCheck->fetch()) {
                            $alumnoId = (int)$existingByName['id'];
                            $nombreFicha = $existingByName['nombre'];
                            $vinculado = true;

                            $updAlu = $pdo->prepare("
                                UPDATE alumnos 
                                SET dni = ?,
                                    email = COALESCE(NULLIF(email, ''), ?), 
                                    telefono = COALESCE(NULLIF(telefono, ''), ?) 
                                WHERE id = ?
                            ");
                            $updAlu->execute([$dniClean, $email, $telefono, $alumnoId]);
                        }
                    }

                    // 3. Si no existe ficha previa, crear nuevo alumno con su nombre completo y DNI
                    if (!$alumnoId) {
                        $insAlu = $pdo->prepare("
                            INSERT INTO alumnos (gimnasio_id, nombre, dni, telefono, email, plan, actividades, fecha_inicio, fecha_vencimiento, estado) 
                            VALUES (?, ?, ?, ?, ?, '3x', 'Musculación', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'activo')
                        ");
                        $insAlu->execute([$gimnasio_id, $nombre_completo, $dniClean, $telefono, $email]);
                        $alumnoId = (int)$pdo->lastInsertId();
                    }
                }
            } elseif ($assignedRole === ROLE_COACH) {
                // Para coaches
                $stProf = $pdo->prepare("SELECT id, nombre FROM profesores WHERE gimnasio_id = ? AND LOWER(TRIM(nombre)) = LOWER(?) LIMIT 1");
                $stProf->execute([$gimnasio_id, $nombre_completo]);
                $existingProf = $stProf->fetch();

                if ($existingProf) {
                    $profesorId = (int)$existingProf['id'];
                    $vinculado = true;
                } else {
                    $insProf = $pdo->prepare("INSERT INTO profesores (gimnasio_id, nombre, telefono, cuota_mensual) VALUES (?, ?, ?, 0.00)");
                    $insProf->execute([$gimnasio_id, $nombre_completo, $telefono]);
                    $profesorId = (int)$pdo->lastInsertId();
                }
            }

            if (!$errores) {
                // BCrypt Hash con Salt Criptográfico
                $hash = hashPassword($password);

                // Insertar usuario con su gimnasio_id y perfil vinculado
                $ins = $pdo->prepare(
                    'INSERT INTO users (nombre_usuario, email, telefono, password_hash, rol, gimnasio_id, profesor_id, alumno_id, activo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $ins->execute([
                    $nombre_usuario,
                    $email,
                    $telefono,
                    $hash,
                    $assignedRole,
                    $gimnasio_id,
                    $profesorId,
                    $alumnoId
                ]);

                // Descontar uso de invitación si aplica
                if ($inviteGym && isset($inviteGym['id'])) {
                    $pdo->prepare("UPDATE invitaciones SET usos_restantes = GREATEST(0, usos_restantes - 1) WHERE id = ?")->execute([$inviteGym['id']]);
                }

                if ($vinculado) {
                    $exito = '🎉 ¡Excelente! Tu cuenta de acceso (<b>' . htmlspecialchars($nombre_usuario) . '</b>) se vinculó correctamente a tu ficha de socio existente (<b>' . htmlspecialchars($nombreFicha) . '</b>) en ' . htmlspecialchars($inviteGym['gimnasio_nombre'] ?? 'tu gimnasio') . '. Ya podés iniciar sesión.';
                } else {
                    $exito = '🎉 ¡Cuenta creada con éxito para <b>' . htmlspecialchars($nombre_completo) . '</b> (Usuario: <b>' . htmlspecialchars($nombre_usuario) . '</b>) en ' . htmlspecialchars($inviteGym['gimnasio_nombre'] ?? 'Gimnasio') . '! Ya podés iniciar sesión.';
                }
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
    --t1: #f8fafc;
    --t2: #94a3b8;
    --t-mut: #64748b;
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
  .auth-shell{ width: 100%; max-width: 480px; }
  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px 26px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .auth-title{ font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
  .auth-sub{ color: var(--t2); font-size: 13px; margin-bottom: 20px; }
  
  .tenant-pill{
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.35);
    padding: 10px 14px;
    border-radius: var(--r);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .tenant-pill strong{ color: #60a5fa; font-size: 13px; }
  
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
    padding: 13px 18px;
    border-radius: var(--r);
    border: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
  }
  .btn-primary{ background: linear-gradient(135deg, var(--pri), var(--sec)); color: #fff; margin-top: 8px; }
  .auth-footer{ margin-top: 18px; font-size: 13px; color: var(--t-mut); text-align: center; }
  .auth-footer a{ color: var(--pri); text-decoration: none; font-weight: 600; }
  .alert{ border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
  .alert-success{ background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #86efac; }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-title">Registrarse</div>
    <div class="auth-sub">Creá tu cuenta de acceso directo al gimnasio.</div>

    <?php if ($inviteGym): ?>
      <div class="tenant-pill">
        <span style="font-size:18px">🏢</span>
        <div>
          <div style="font-size:11px;color:var(--t-mut);text-transform:uppercase;font-weight:700">Sede Vinculada:</div>
          <strong><?= htmlspecialchars($inviteGym['gimnasio_nombre']) ?></strong>
          <span style="font-size:11px;color:#cbd5e1"> (Rol: <?= strtoupper($assignedRole) ?>)</span>
        </div>
      </div>
    <?php endif; ?>

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
        <div style="margin-top:12px">
          <a href="login.php" class="btn btn-primary" style="background:#10b981;font-size:13.5px;padding:10px 14px;font-weight:700">👉 Ir a Iniciar Sesión</a>
        </div>
      </div>
    <?php else: ?>

    <form method="post">
      <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken) ?>">

      <div class="auth-form-group">
        <label class="auth-label">Nombre y Apellido Real *</label>
        <input class="inp" type="text" name="nombre_completo" required placeholder="Ej: Marcos Pérez"
               value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>" autofocus>
        <div style="font-size:11px;color:var(--t2);margin-top:4px">
          🏷️ Tu nombre real para la ficha del gimnasio y carnet de socio.
        </div>
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Nombre de Usuario (Para Iniciar Sesión) *</label>
        <input class="inp" type="text" name="nombre_usuario" required placeholder="Ej: marcosperez o marcos_p"
               value="<?= htmlspecialchars($_POST['nombre_usuario'] ?? '') ?>"
               oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_.\-]/g, '')">
        <div style="font-size:11px;color:var(--t2);margin-top:4px">
          👤 Tu cuenta de acceso personal (sin espacios).
        </div>
      </div>

      <div class="auth-form-group">
        <label class="auth-label">DNI / Documento *</label>
        <input class="inp" type="text" name="dni" required placeholder="Ej: 38456789 (Sin puntos)"
               value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>"
               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
        <div style="font-size:11px;color:var(--t2);margin-top:4px;line-height:1.4">
          💡 Si tu gimnasio ya cargó tu ficha previamente, colocá tu <b>mismo DNI</b> para vincular automáticamente tus pagos, carnet digital y rutinas.
        </div>
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Correo Electrónico *</label>
        <input class="inp" type="email" name="email" required placeholder="tu@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Teléfono / WhatsApp</label>
        <input class="inp" type="text" name="telefono" placeholder="+54 266 ..."
               value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Contraseña *</label>
        <input class="inp" type="password" name="password" required placeholder="Mínimo 6 caracteres">
      </div>

      <div class="auth-form-group">
        <label class="auth-label">Confirmar Contraseña *</label>
        <input class="inp" type="password" name="password2" required placeholder="Repetir contraseña">
      </div>

      <button class="btn btn-primary" type="submit">Crear Mi Cuenta</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      ¿Ya tenés cuenta? <a href="login.php">Iniciar Sesión</a>
    </div>
  </div>
</div>
</body>
</html>
