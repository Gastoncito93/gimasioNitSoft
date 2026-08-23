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
    // A. Buscar coincidencia exacta en tabla invitaciones
    $stInv = $pdo->prepare("
        SELECT inv.*, g.nombre AS gimnasio_nombre, g.invite_code
        FROM invitaciones inv
        JOIN gimnasios g ON g.id = inv.gimnasio_id
        WHERE inv.token = ? AND (inv.expira_en IS NULL OR inv.expira_en > NOW()) AND inv.usos_restantes > 0
        LIMIT 1
    ");
    $stInv->execute([$inviteToken]);
    $invRow = $stInv->fetch();

    if ($invRow) {
        $inviteGym    = $invRow;
        $assignedRole = $invRow['rol'] ?: ROLE_ALUMNO;
    } else {
        // B. Comprobar si el token contiene sufijo de rol (_COACH, _PROFESOR, _ALUMNO, _SOCIO)
        $isCoachSuffix = false;
        $baseCode = $inviteToken;

        if (preg_match('/^(.*?)[_-](coach|profesor|profe)$/i', $inviteToken, $m)) {
            $baseCode = $m[1];
            $isCoachSuffix = true;
        } elseif (preg_match('/^(.*?)[_-](alumno|socio)$/i', $inviteToken, $m)) {
            $baseCode = $m[1];
            $isCoachSuffix = false;
        }

        // Buscar en tabla invitaciones con el código base
        $stInv2 = $pdo->prepare("
            SELECT inv.*, g.nombre AS gimnasio_nombre, g.invite_code
            FROM invitaciones inv
            JOIN gimnasios g ON g.id = inv.gimnasio_id
            WHERE inv.token = ? AND (inv.expira_en IS NULL OR inv.expira_en > NOW()) AND inv.usos_restantes > 0
            LIMIT 1
        ");
        $stInv2->execute([$baseCode]);
        $invRow2 = $stInv2->fetch();

        if ($invRow2) {
            $inviteGym    = $invRow2;
            $assignedRole = $isCoachSuffix ? ROLE_COACH : ($invRow2['rol'] ?: ROLE_ALUMNO);
        } else {
            // C. Buscar por invite_code directo en tabla gimnasios
            $stGym = $pdo->prepare("SELECT id AS gimnasio_id, nombre AS gimnasio_nombre, invite_code FROM gimnasios WHERE invite_code = ? OR invite_code = ? LIMIT 1");
            $stGym->execute([$inviteToken, $baseCode]);
            $gymRow = $stGym->fetch();

            if ($gymRow) {
                $inviteGym    = $gymRow;
                $assignedRole = $isCoachSuffix ? ROLE_COACH : ROLE_ALUMNO;
            } else {
                $errores[] = 'El enlace o código de invitación no es válido o ha expirado.';
            }
        }
    }
} else {
    $errores[] = 'Para registrarte, necesitás un enlace o código de invitación provisto por el gimnasio.';
}

$isCoach = ($assignedRole === ROLE_COACH);
$pageTitle = $isCoach ? 'Registro de Coach / Profesor - GYM PRO' : 'Crear Cuenta de Socio - GYM PRO';

// 2. Detección de Coach Referidor / Asignador (Enlace provisto por el Coach)
$coachParamId = (int)($_GET['coach'] ?? $_POST['coach_id'] ?? 0);
$referralCoach = null;
if (!$isCoach && $coachParamId > 0 && $inviteGym) {
    $stCoachRef = $pdo->prepare("SELECT id, nombre, telefono FROM profesores WHERE id = ? AND gimnasio_id = ? LIMIT 1");
    $stCoachRef->execute([$coachParamId, $inviteGym['gimnasio_id']]);
    $referralCoach = $stCoachRef->fetch() ?: null;
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

    // Validación de campos requeridos
    if ($nombre_completo === '' || $nombre_usuario === '' || $email === '' || $password === '' || $password2 === '') {
        $errores[] = 'Todos los campos marcados con * son obligatorios.';
    }

    // Para alumnos, el DNI es obligatorio para evitar duplicados en la base de datos de socios
    if (!$isCoach && $dni_raw === '') {
        $errores[] = 'El DNI es obligatorio para los socios para vincular tu carnet y cuotas.';
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

    // Validación estandarizada de contraseña
    $passValidation = validatePasswordStrength($password);
    if (!$passValidation['ok']) {
        foreach ($passValidation['errores'] as $err) {
            $errores[] = $err;
        }
    }
    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
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
                        $coachAsignar = $referralCoach ? (int)$referralCoach['id'] : null;
                        $insAlu = $pdo->prepare("
                            INSERT INTO alumnos (gimnasio_id, nombre, dni, telefono, email, plan, actividades, fecha_inicio, fecha_vencimiento, estado, profesor_id) 
                            VALUES (?, ?, ?, ?, ?, '3x', 'Musculación', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'activo', ?)
                        ");
                        $insAlu->execute([$gimnasio_id, $nombre_completo, $dniClean, $telefono, $email, $coachAsignar]);
                        $alumnoId = (int)$pdo->lastInsertId();
                    } elseif ($referralCoach) {
                        // Si ya existía ficha previa sin coach asignado, asignarle este coach referidor
                        $pdo->prepare("UPDATE alumnos SET profesor_id = COALESCE(profesor_id, ?) WHERE id = ?")
                            ->execute([(int)$referralCoach['id'], $alumnoId]);
                    }
                }
            } elseif ($assignedRole === ROLE_COACH) {
                // Registro de Entrenador / Coach
                $stProf = $pdo->prepare("SELECT id, nombre FROM profesores WHERE gimnasio_id = ? AND LOWER(TRIM(nombre)) = LOWER(?) LIMIT 1");
                $stProf->execute([$gimnasio_id, $nombre_completo]);
                $existingProf = $stProf->fetch();

                if ($existingProf) {
                    $profesorId = (int)$existingProf['id'];
                    $vinculado = true;
                    $nombreFicha = $existingProf['nombre'];
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

                if ($isCoach) {
                    $exito = '🎉 <b>¡Cuenta de Coach creada con éxito!</b> Tu usuario es <b>' . htmlspecialchars($nombre_usuario) . '</b> en <b>' . htmlspecialchars($inviteGym['gimnasio_nombre'] ?? 'el gimnasio') . '</b>. Ya podés iniciar sesión para acceder al panel de entrenadores.';
                } elseif ($vinculado) {
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
  .auth-shell{ width: 100%; max-width: 500px; }
  .auth-card{
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px 28px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  }
  .auth-title{ font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
  .auth-sub{ color: var(--t2); font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
  
  .tenant-pill{
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.3);
    padding: 12px 14px;
    border-radius: var(--r);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .tenant-pill.coach-pill{
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.4);
  }
  .tenant-pill strong{ color: #60a5fa; font-size: 13.5px; }
  .tenant-pill.coach-pill strong{ color: #a78bfa; }
  
  .auth-form-group{ margin-bottom: 16px; position: relative; }
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
    transition: all 0.2s ease;
  }
  .inp:focus{ border-color: var(--pri); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); }
  .inp.inp-err{ border-color: var(--err); }
  .inp.inp-ok{ border-color: var(--ok); }

  .field-error-msg{
    display: none;
    color: #fca5a5;
    font-size: 11.5px;
    font-weight: 600;
    margin-top: 5px;
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
    text-decoration: none;
  }
  .btn-primary{ background: linear-gradient(135deg, var(--pri), var(--sec)); color: #fff; margin-top: 10px; }
  .btn-primary:hover{ opacity: 0.95; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35); }
  
  .auth-footer{ margin-top: 18px; font-size: 13px; color: var(--t-mut); text-align: center; }
  .auth-footer a{ color: var(--pri); text-decoration: none; font-weight: 600; }
  .alert{ border-radius: var(--r); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
  .alert-error{ background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
  .alert-success{ background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #86efac; }
  
  .badge-role{
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .badge-coach{ background: #8b5cf6; color: #fff; }
  .badge-alumno{ background: #3b82f6; color: #fff; }

  .btn-toggle-pass{
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    color: var(--t2);
    cursor: pointer;
    padding: 6px 9px;
    border-radius: 8px;
    font-size: 11.5px;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s ease;
  }
  .btn-toggle-pass:hover{
    background: rgba(255,255,255,0.1);
    color: #fff;
  }

  .pass-checklist{
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 11.5px;
  }
  .rule-item{
    color: var(--t-mut);
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
  }
  .rule-item.valid{
    color: #10b981;
    font-weight: 600;
  }
  .rule-item.invalid{
    color: #f87171;
  }
  .rule-icon{
    font-size: 12px;
    width: 14px;
    display: inline-block;
    text-align: center;
  }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-title">
      <?= $isCoach ? '🏋️‍♂️ Registro de Coach / Profesor' : '👤 Registro de Socio / Alumno' ?>
    </div>
    <div class="auth-sub">
      <?= $isCoach 
        ? 'Creá tu cuenta de acceso de Profesor para gestionar tus alumnos, asistencias y rutinas de entrenamiento.' 
        : 'Creá tu cuenta de acceso directo al gimnasio para ver tus rutinas, cuotas y asistencias.' ?>
    </div>

    <?php if ($inviteGym): ?>
      <div class="tenant-pill <?= $isCoach ? 'coach-pill' : '' ?>">
        <span style="font-size:20px"><?= $isCoach ? '🏋️‍♂️' : '🏢' ?></span>
        <div style="flex:1">
          <div style="font-size:11px;color:var(--t-mut);text-transform:uppercase;font-weight:700">Sede Vinculada:</div>
          <strong><?= htmlspecialchars($inviteGym['gimnasio_nombre']) ?></strong>
        </div>
        <div>
          <span class="badge-role <?= $isCoach ? 'badge-coach' : 'badge-alumno' ?>">
            <?= $isCoach ? 'Coach / Profesor' : 'Socio / Alumno' ?>
          </span>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$isCoach && $referralCoach): ?>
      <div style="background:rgba(139, 92, 246, 0.12);border:1px solid rgba(139, 92, 246, 0.35);border-radius:12px;padding:12px 16px;margin-top:12px;margin-bottom:18px;display:flex;align-items:center;gap:12px">
        <span style="font-size:24px">🏋️‍♂️</span>
        <div style="flex:1">
          <div style="font-size:11px;color:#c084fc;font-weight:700;text-transform:uppercase;letter-spacing:0.5px">Tu Entrenador Asignado:</div>
          <div style="font-size:14.5px;font-weight:800;color:#fff"><?= htmlspecialchars($referralCoach['nombre']) ?></div>
        </div>
        <span class="badge b-purple" style="font-size:11.5px;padding:4px 8px">Asignación Directa</span>
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
      <div class="alert alert-success" style="padding:24px 20px;border-radius:16px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.35);text-align:center">
        <div style="font-size:36px;margin-bottom:10px">🎉</div>
        <div style="line-height:1.6;font-size:14.5px;color:#f8fafc"><?= $exito ?></div>
        <div style="margin-top:20px">
          <a href="logout.php" class="btn btn-primary" style="background:#10b981;font-size:14px;padding:12px 24px;font-weight:800;display:inline-flex;align-items:center;gap:8px;text-decoration:none;border-radius:12px;box-shadow:0 4px 14px rgba(16,185,129,0.35)">🔐 Iniciar Sesión</a>
        </div>
      </div>
    <?php else: ?>

    <form method="post" autocomplete="off" onsubmit="return validateRegisterForm(event)">
      <!-- Dummy inputs ocultos para neutralizar autofill agresivo de navegadores -->
      <input type="text" name="prevent_autofill_user" style="position:absolute;top:-9999px;left:-9999px;opacity:0" tabindex="-1" autocomplete="off">
      <input type="password" name="prevent_autofill_pass" style="position:absolute;top:-9999px;left:-9999px;opacity:0" tabindex="-1" autocomplete="off">

      <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken) ?>">
      <input type="hidden" name="coach_id" value="<?= htmlspecialchars((string)($coachParamId ?: '')) ?>">

      <!-- 1. Nombre y Apellido Real -->
      <div class="auth-form-group">
        <label class="auth-label">Nombre y Apellido Real *</label>
        <input id="inp-nombre-real" class="inp" type="text" name="nombre_completo" required 
               placeholder="<?= $isCoach ? 'Ej: Noah Sosa' : 'Ej: Noah Sosa' ?>"
               value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>" autofocus autocomplete="off" 
               oninput="onNombreRealInput(this.value)" onblur="validateFieldNombre(this)">
        <div style="font-size:11px;color:var(--t2);margin-top:4px">
          <?= $isCoach ? '🏋️‍♂️ Tu nombre visible para los socios y en el panel del gimnasio.' : '👤 Tu nombre real para la ficha del gimnasio y carnet de socio.' ?>
        </div>
        <div id="err-nombre-real" class="field-error-msg"></div>
      </div>

      <!-- 2. Nombre de Usuario -->
      <div class="auth-form-group">
        <label class="auth-label">Nombre de Usuario (Para Iniciar Sesión) *</label>
        <input id="inp-username" class="inp" type="text" name="nombre_usuario" required 
               placeholder="<?= $isCoach ? 'Ej: noahsosa o profe_noah' : 'Ej: noahsosa o noah_sosa' ?>"
               value="<?= htmlspecialchars($_POST['nombre_usuario'] ?? '') ?>" autocomplete="off"
               oninput="onUsernameManualInput(this)" onblur="validateFieldUsername(this)">
        <div style="font-size:11px;color:var(--t2);margin-top:4px">
          🔑 Tu cuenta de acceso personal (se autocompleta con tu nombre, podés cambiarlo).
        </div>
        <div id="err-username" class="field-error-msg"></div>
      </div>

      <!-- 3. DNI / Documento -->
      <div class="auth-form-group">
        <label class="auth-label">DNI / Documento <?= $isCoach ? '(Opcional)' : '*' ?></label>
        <input id="inp-dni" class="inp" type="text" name="dni" <?= $isCoach ? '' : 'required' ?> placeholder="Ej: 38456789 (Sin puntos)"
               value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>" autocomplete="off"
               oninput="this.value = this.value.replace(/[^0-9]/g, ''); validateFieldDni(this)" onblur="validateFieldDni(this)">
        <div style="font-size:11px;color:var(--t2);margin-top:4px;line-height:1.4">
          <?= $isCoach 
            ? '📄 Documento de identidad para tu ficha de profesor (opcional).' 
            : '📄 Si tu gimnasio ya cargó tu ficha previamente, colocá tu <b>mismo DNI</b> para vincular automáticamente tus pagos, carnet digital y rutinas.' ?>
        </div>
        <div id="err-dni" class="field-error-msg"></div>
      </div>

      <!-- 4. Correo Electrónico -->
      <div class="auth-form-group">
        <label class="auth-label">Correo Electrónico *</label>
        <input id="inp-email" class="inp" type="email" name="email" required placeholder="tu@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="off"
               onblur="validateFieldEmail(this)">
        <div id="err-email" class="field-error-msg"></div>
      </div>

      <!-- 5. Teléfono -->
      <div class="auth-form-group">
        <label class="auth-label">Teléfono / WhatsApp</label>
        <input id="inp-telefono" class="inp" type="text" name="telefono" placeholder="+54 9 266 ..."
               value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" autocomplete="off">
      </div>

      <!-- 6. Contraseña con Restricciones y Botón Mostrar -->
      <div class="auth-form-group">
        <label class="auth-label">Contraseña *</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="inp-password" class="inp" type="password" name="password" required 
                 placeholder="Mínimo 8 caracteres" autocomplete="new-password"
                 style="padding-right:96px" oninput="onPasswordInput()" onblur="onPasswordBlur()">
          <button type="button" class="btn-toggle-pass" onclick="togglePasswordVisibility('inp-password', this)">
            <span class="toggle-icon">👁️</span>
            <span class="toggle-text">Ver</span>
          </button>
        </div>
        
        <!-- Checklist dinámico de restricciones -->
        <div id="pass-rules-box" class="pass-checklist">
          <div id="rule-len" class="rule-item">
            <span class="rule-icon">○</span> Entre 8 y 20 caracteres
          </div>
          <div id="rule-upper" class="rule-item">
            <span class="rule-icon">○</span> Al menos una letra mayúscula (A-Z)
          </div>
          <div id="rule-lower" class="rule-item">
            <span class="rule-icon">○</span> Al menos una letra minúscula (a-z)
          </div>
          <div id="rule-number" class="rule-item">
            <span class="rule-icon">○</span> Al menos un número (0-9)
          </div>
          <div id="rule-symbol" class="rule-item">
            <span class="rule-icon">○</span> Al menos un símbolo especial (! @ # $ % * - _ . ? & +)
          </div>
        </div>
        <div id="err-password" class="field-error-msg"></div>
      </div>

      <!-- 7. Confirmar Contraseña con Botón Mostrar -->
      <div class="auth-form-group">
        <label class="auth-label">Confirmar Contraseña *</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="inp-password2" class="inp" type="password" name="password2" required 
                 placeholder="Repetir contraseña exacta" autocomplete="new-password"
                 style="padding-right:96px" oninput="onPasswordConfirmInput()" onblur="onPasswordConfirmBlur()">
          <button type="button" class="btn-toggle-pass" onclick="togglePasswordVisibility('inp-password2', this)">
            <span class="toggle-icon">👁️</span>
            <span class="toggle-text">Ver</span>
          </button>
        </div>
        <div id="match-feedback" style="display:none;margin-top:6px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:6px"></div>
        <div id="err-password2" class="field-error-msg"></div>
      </div>

      <button id="btn-submit-register" class="btn btn-primary" type="submit">
        <?= $isCoach ? '🚀 Crear Mi Cuenta de Coach' : '🚀 Crear Mi Cuenta de Socio' ?>
      </button>
    </form>

    <div class="auth-footer">
      ¿Ya tenés cuenta? <a href="logout.php">Iniciar Sesión</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const IS_COACH = <?= $isCoach ? 'true' : 'false' ?>;
let _usernameManualEdited = false;

// Si al cargar la página ya había un valor en POST, marcarlo
if (document.getElementById('inp-username')?.value?.trim() !== '') {
  _usernameManualEdited = true;
}

// 1. Generación Inteligente de Nombre de Usuario
function onNombreRealInput(val) {
  const userInp = document.getElementById('inp-username');
  if (!userInp || _usernameManualEdited) return;

  let clean = val
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // Quitar tildes
    .replace(/[^a-z0-9\s]/g, '')     // Solo letras y números
    .trim();

  if (!clean) {
    userInp.value = '';
    return;
  }

  let parts = clean.split(/\s+/).filter(p => p.length > 0);
  let suggested = parts.join('');

  userInp.value = suggested;
  validateFieldUsername(userInp);
}

function onUsernameManualInput(el) {
  el.value = el.value.toLowerCase().replace(/[^a-z0-9_.\-]/g, '');
  _usernameManualEdited = true;
  validateFieldUsername(el);
}

// 2. Mostrar / Ocultar Contraseña
function togglePasswordVisibility(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  
  const iconSpan = btn.querySelector('.toggle-icon');
  const textSpan = btn.querySelector('.toggle-text');

  if (inp.type === 'password') {
    inp.type = 'text';
    if (iconSpan) iconSpan.textContent = '🙈';
    if (textSpan) textSpan.textContent = 'Ocultar';
    btn.style.background = 'rgba(59, 130, 246, 0.2)';
    btn.style.color = '#fff';
  } else {
    inp.type = 'password';
    if (iconSpan) iconSpan.textContent = '👁️';
    if (textSpan) textSpan.textContent = 'Ver';
    btn.style.background = 'rgba(255, 255, 255, 0.04)';
    btn.style.color = 'var(--t2)';
  }
}

// 3. Validación de Reglas de Contraseña en Tiempo Real
function checkPasswordRules(pass) {
  const len = pass.length;
  return {
    len: len >= 8 && len <= 20,
    upper: /[A-Z]/.test(pass),
    lower: /[a-z]/.test(pass),
    number: /[0-9]/.test(pass),
    symbol: /[^a-zA-Z0-9\s]/.test(pass)
  };
}

function updateRuleUI(ruleId, isValid, text) {
  const el = document.getElementById(ruleId);
  if (!el) return;
  
  el.classList.toggle('valid', isValid);
  el.classList.toggle('invalid', !isValid && el.dataset.touched === 'true');
  
  const icon = el.querySelector('.rule-icon');
  if (icon) {
    icon.textContent = isValid ? '✓' : '○';
  }
}

function onPasswordInput() {
  const pass = document.getElementById('inp-password')?.value || '';
  const rules = checkPasswordRules(pass);

  // Marcar como tocado
  document.querySelectorAll('.rule-item').forEach(el => el.dataset.touched = 'true');

  updateRuleUI('rule-len', rules.len);
  updateRuleUI('rule-upper', rules.upper);
  updateRuleUI('rule-lower', rules.lower);
  updateRuleUI('rule-number', rules.number);
  updateRuleUI('rule-symbol', rules.symbol);

  const allValid = rules.len && rules.upper && rules.lower && rules.number && rules.symbol;
  const inp = document.getElementById('inp-password');
  const err = document.getElementById('err-password');

  if (inp) {
    inp.classList.toggle('inp-ok', allValid);
    inp.classList.toggle('inp-err', pass.length > 0 && !allValid);
  }

  if (err) {
    if (pass.length > 0 && !allValid) {
      err.style.display = 'block';
      err.textContent = 'Cumplí con todos los requisitos de la lista verde para continuar.';
    } else {
      err.style.display = 'none';
    }
  }

  onPasswordConfirmInput();
}

function onPasswordBlur() {
  const pass = document.getElementById('inp-password')?.value || '';
  if (!pass) {
    const err = document.getElementById('err-password');
    if (err) {
      err.style.display = 'block';
      err.textContent = 'La contraseña es obligatoria.';
    }
  }
}

// 4. Validación de Confirmación de Contraseña
function onPasswordConfirmInput() {
  const p1 = document.getElementById('inp-password')?.value || '';
  const p2 = document.getElementById('inp-password2')?.value || '';
  const matchBox = document.getElementById('match-feedback');
  const errBox = document.getElementById('err-password2');
  const inp2 = document.getElementById('inp-password2');

  if (!p2) {
    if (matchBox) matchBox.style.display = 'none';
    if (errBox) errBox.style.display = 'none';
    if (inp2) { inp2.classList.remove('inp-ok'); inp2.classList.remove('inp-err'); }
    return;
  }

  const isMatch = (p1 === p2 && p1.length > 0);
  if (inp2) {
    inp2.classList.toggle('inp-ok', isMatch);
    inp2.classList.toggle('inp-err', !isMatch);
  }

  if (matchBox) {
    matchBox.style.display = 'flex';
    if (isMatch) {
      matchBox.style.color = '#10b981';
      matchBox.innerHTML = '<span>✓</span> Las contraseñas coinciden perfectamente.';
      if (errBox) errBox.style.display = 'none';
    } else {
      matchBox.style.color = '#f87171';
      matchBox.innerHTML = '<span>✗</span> Las contraseñas no coinciden.';
    }
  }
}

function onPasswordConfirmBlur() {
  const p1 = document.getElementById('inp-password')?.value || '';
  const p2 = document.getElementById('inp-password2')?.value || '';
  const errBox = document.getElementById('err-password2');

  if (!p2) {
    if (errBox) {
      errBox.style.display = 'block';
      errBox.textContent = 'Debes confirmar tu contraseña.';
    }
  } else if (p1 !== p2) {
    if (errBox) {
      errBox.style.display = 'block';
      errBox.textContent = 'Las contraseñas no coinciden.';
    }
  }
}

// 5. Validaciones individuales de campos
function validateFieldNombre(el) {
  const err = document.getElementById('err-nombre-real');
  const val = (el.value || '').trim();
  if (!val || val.length < 3) {
    el.classList.add('inp-err'); el.classList.remove('inp-ok');
    if (err) { err.style.display = 'block'; err.textContent = 'Ingresá tu nombre y apellido completo (mínimo 3 letras).'; }
    return false;
  }
  el.classList.remove('inp-err'); el.classList.add('inp-ok');
  if (err) err.style.display = 'none';
  return true;
}

function validateFieldUsername(el) {
  const err = document.getElementById('err-username');
  const val = (el.value || '').trim();
  const validUser = /^[a-z0-9_.\-]{3,30}$/i.test(val);
  if (!val || !validUser) {
    el.classList.add('inp-err'); el.classList.remove('inp-ok');
    if (err) { err.style.display = 'block'; err.textContent = 'El usuario debe tener entre 3 y 30 caracteres (solo letras, números, . o _).'; }
    return false;
  }
  el.classList.remove('inp-err'); el.classList.add('inp-ok');
  if (err) err.style.display = 'none';
  return true;
}

function validateFieldDni(el) {
  const err = document.getElementById('err-dni');
  const val = (el.value || '').trim();
  if (!IS_COACH && !val) {
    el.classList.add('inp-err'); el.classList.remove('inp-ok');
    if (err) { err.style.display = 'block'; err.textContent = 'El DNI es obligatorio para los socios.'; }
    return false;
  }
  if (val && (val.length < 7 || val.length > 9)) {
    el.classList.add('inp-err'); el.classList.remove('inp-ok');
    if (err) { err.style.display = 'block'; err.textContent = 'El DNI debe tener entre 7 y 9 dígitos numéricos.'; }
    return false;
  }
  el.classList.remove('inp-err');
  if (val) el.classList.add('inp-ok');
  if (err) err.style.display = 'none';
  return true;
}

function validateFieldEmail(el) {
  const err = document.getElementById('err-email');
  const val = (el.value || '').trim();
  const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
  if (!val || !validEmail) {
    el.classList.add('inp-err'); el.classList.remove('inp-ok');
    if (err) { err.style.display = 'block'; err.textContent = 'Ingresá un correo electrónico válido.'; }
    return false;
  }
  el.classList.remove('inp-err'); el.classList.add('inp-ok');
  if (err) err.style.display = 'none';
  return true;
}

// 6. Validación completa al enviar el formulario
function validateRegisterForm(e) {
  const fNom = validateFieldNombre(document.getElementById('inp-nombre-real'));
  const fUser = validateFieldUsername(document.getElementById('inp-username'));
  const fDni = validateFieldDni(document.getElementById('inp-dni'));
  const fEmail = validateFieldEmail(document.getElementById('inp-email'));

  const p1 = document.getElementById('inp-password')?.value || '';
  const p2 = document.getElementById('inp-password2')?.value || '';
  const rules = checkPasswordRules(p1);
  const passValid = rules.len && rules.upper && rules.lower && rules.number && rules.symbol;
  const matchValid = (p1 === p2 && p1.length > 0);

  if (!passValid) {
    onPasswordInput();
    document.getElementById('inp-password')?.focus();
    e.preventDefault();
    return false;
  }

  if (!matchValid) {
    onPasswordConfirmBlur();
    document.getElementById('inp-password2')?.focus();
    e.preventDefault();
    return false;
  }

  if (!fNom || !fUser || !fDni || !fEmail) {
    e.preventDefault();
    return false;
  }

  return true;
}
</script>
</body>
</html>