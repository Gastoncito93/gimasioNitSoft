<?php
/**************************************************************
 * MIDDLEWARE DE AUTENTICACIÓN Y CONTROL DE ACCESO (RBAC - 4 ROLES)
 * Arquitectura Multi-Tenant (Nitsof Pattern: 3 Capas)
 * 1. SuperAdmin (is_superadmin = true, Plataforma)
 * 2. Inquilinos / Gimnasios (Tenants)
 * 3. Miembros con Aislamiento de Datos por gimnasio_id
 **************************************************************/
require_once __DIR__ . '/config.php';

// 1. Verificar sesión activa
if (empty($_SESSION['user_id'])) {
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'No autorizado. Iniciá sesión para continuar.']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// 2. Sincronizar datos del usuario desde la Base de Datos
$userId = (int)$_SESSION['user_id'];
$stUser = $pdo->prepare("
    SELECT u.id, u.nombre_usuario, u.email, u.telefono, u.rol, u.is_superadmin, u.gimnasio_id, u.profesor_id, u.alumno_id, u.activo,
           g.nombre AS gimnasio_nombre, g.suscripcion_estado, g.suscripcion_vencimiento, g.suscripcion_monto,
           a.nombre AS alumno_nombre, p.nombre AS profesor_nombre
    FROM users u
    LEFT JOIN gimnasios g ON g.id = u.gimnasio_id
    LEFT JOIN alumnos a ON a.id = u.alumno_id
    LEFT JOIN profesores p ON p.id = u.profesor_id
    WHERE u.id = ? LIMIT 1
");
$stUser->execute([$userId]);
$userRow = $stUser->fetch();

if (!$userRow || (int)$userRow['activo'] !== 1) {
    session_unset();
    session_destroy();
    header('Location: login.php?msg=cuenta_inactiva');
    exit;
}

// 3. Control de Suspensión para Dueños (SaaS Subscription Check)
if ($userRow['rol'] === ROLE_DUENO && $userRow['suscripcion_estado'] === 'suspendido') {
    if (isset($_GET['ajax'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'suspendido' => true,
            'msg' => 'Tu suscripción al sistema está suspendida por pago pendiente. Contactá al Administrador General.'
        ]);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Suscripción Suspendida - GYM PRO SaaS</title>
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; background: #090d16; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .box { background: #131b2e; border: 2px solid #ef4444; border-radius: 18px; max-width: 520px; padding: 36px; text-align: center; box-shadow: 0 25px 60px rgba(0,0,0,0.8); }
            .badge-sus { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 12px; display: inline-block; margin-bottom: 16px; }
            h2 { color: #f87171; font-size: 24px; margin-bottom: 12px; }
            p { color: #cbd5e1; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
            .contact-box { background: #1b2640; border: 1px solid #243452; border-radius: 12px; padding: 16px; margin-bottom: 24px; text-align: left; font-size: 13px; }
            .btn { display: inline-block; background: #3b82f6; color: #fff; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="box">
            <span class="badge-sus">⚠️ SERVICIO SUSPENDIDO</span>
            <h2>Membresía de Gimnasio Bloqueada</h2>
            <p>La suscripción de tu sede <strong><?= htmlspecialchars($userRow['gimnasio_nombre'] ?? 'Tu Gimnasio') ?></strong> a la plataforma se encuentra suspendida temporalmente por cuota vencida impaga.</p>
            <div class="contact-box">
                <div style="font-weight:700;margin-bottom:6px;color:#60a5fa">🛡️ Administrador General de la Plataforma:</div>
                <div>💬 <b>WhatsApp Soporte:</b> <a href="https://wa.me/5492664000000?text=Hola,%20solicito%20renovacion%20de%20suscripcion%20para%20<?= urlencode($userRow['gimnasio_nombre'] ?? '') ?>" target="_blank" style="color:#34d399;text-decoration:none">+54 9 266 4000000</a></div>
                <div>📧 <b>Email:</b> superadmin@gymplatform.com</div>
                <div style="margin-top:8px;color:#94a3b8;font-size:12px">🔒 Todos los datos de tus socios, cobros y clases se encuentran 100% resguardados y se reactivarán al regularizar el pago.</div>
            </div>
            <a href="logout.php" class="btn" style="background:#ef4444">🚪 Cerrar Sesión</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 4. Claims del Usuario y Contexto Multi-Tenant
$isSuperAdmin = ((int)($userRow['is_superadmin'] ?? 0) === 1 || $userRow['rol'] === ROLE_ADMIN_GENERAL);
$_SESSION['user_name']      = $userRow['alumno_nombre'] ?: ($userRow['profesor_nombre'] ?: $userRow['nombre_usuario']);
$_SESSION['login_handle']   = $userRow['nombre_usuario'];
$_SESSION['user_email']     = $userRow['email'];
$_SESSION['user_role']      = $userRow['rol'] ?: ROLE_DUENO;
$_SESSION['is_superadmin']  = $isSuperAdmin;
$_SESSION['gimnasio_id']    = (int)($userRow['gimnasio_id'] ?? 1);
$_SESSION['profesor_id']    = $userRow['profesor_id'];
$_SESSION['alumno_id']      = $userRow['alumno_id'];
$_SESSION['gimnasio_nombre'] = $userRow['gimnasio_nombre'] ?? 'NITSOFT';

$userName        = $_SESSION['user_name'];
$userEmail       = $_SESSION['user_email'];
$userRole        = $_SESSION['user_role'];
$gimnasioId      = (int)($_SESSION['gimnasio_id'] ?? 1);
$gimnasioNombre  = $_SESSION['gimnasio_nombre'] ?? 'NITSOFT';
$profesorId      = !empty($_SESSION['profesor_id']) ? (int)$_SESSION['profesor_id'] : null;
$alumnoId        = !empty($_SESSION['alumno_id']) ? (int)$_SESSION['alumno_id'] : null;

if (!function_exists('cleanDisplayName')) {
    function cleanDisplayName(?string $name): string {
        if (!$name) return 'Usuario';
        $clean = preg_replace('/^(dueno|coach|alumno)[_\-\s]+/i', '', $name);
        $clean = str_replace(['_', '-'], ' ', $clean);
        return ucwords(trim($clean));
    }
}
$userDisplayName = cleanDisplayName($userName);

// Modo Auditoría para SuperAdmin (Permite cambiar el gimnasio activo para auditar)
if ($isSuperAdmin && isset($_GET['switch_gym'])) {
    $auditGymId = (int)$_GET['switch_gym'];
    $_SESSION['audit_gym_id'] = $auditGymId > 0 ? $auditGymId : null;
}
$auditGymId = ($isSuperAdmin && !empty($_SESSION['audit_gym_id'])) ? (int)$_SESSION['audit_gym_id'] : null;

/**
 * Retorna el gimnasio efectivo sobre el que se debe aislar la consulta.
 * Si es un usuario normal (Dueño, Coach, Alumno) retorna SIEMPRE su gimnasio_id fijo.
 * Si es SuperAdmin y tiene un gimnasio en auditoría retorna ese ID, o null si está en vista global.
 */
function getEffectiveGymId(): ?int {
    global $isSuperAdmin, $auditGymId, $gimnasioId;
    if ($isSuperAdmin) {
        return $auditGymId ?: null;
    }
    return $gimnasioId;
}

/**
 * Retorna los datos del usuario en sesión
 */
function getCurrentUser(): array {
    global $userId, $userName, $userEmail, $userRole, $isSuperAdmin, $gimnasioId, $profesorId, $alumnoId, $auditGymId;
    return [
        'id'             => $userId,
        'name'           => $userName,
        'email'          => $userEmail,
        'role'           => $userRole,
        'is_superadmin'  => $isSuperAdmin,
        'gimnasio_id'    => $gimnasioId,
        'audit_gym_id'   => $auditGymId,
        'profesor_id'    => $profesorId,
        'alumno_id'      => $alumnoId
    ];
}

/**
 * Comprueba si el usuario actual posee uno de los roles permitidos
 */
function hasRole($roles): bool {
    global $userRole;
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($userRole, $roles, true);
}

/**
 * Exige que el usuario posea uno de los roles permitidos; de lo contrario aborta con 403 Forbidden
 */
function requireRole($roles, bool $isAjax = false): void {
    if (!hasRole($roles)) {
        http_response_code(403);
        if ($isAjax || isset($_GET['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Acceso denegado. Permisos insuficientes para tu rol.']);
            exit;
        }
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>403 - Acceso Denegado</title>
            <style>
                body { font-family: system-ui, sans-serif; background: #090d16; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .box { background: #131b2e; border: 1px solid #ef4444; border-radius: 14px; padding: 30px; text-align: center; max-width: 440px; }
                h2 { color: #ef4444; margin-bottom: 10px; }
                a { color: #3b82f6; text-decoration: none; font-weight: 700; }
            </style>
        </head>
        <body>
            <div class="box">
                <h2>⛔ 403 - Acceso Denegado</h2>
                <p>No tenés los permisos necesarios para acceder a este módulo.</p>
                <br>
                <a href="index.php">← Volver a mi panel</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
