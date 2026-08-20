<?php
/**************************************************************
 * CONFIG & SEGURIDAD - GYM PRO SaaS PLATFORM
 * Arquitectura Segura con 4 Roles (Admin General, Dueño, Coach, Alumno)
 * BCrypt Cost 12, Rate Limiting Anti-Brute Force
 **************************************************************/

if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de cookies de sesión
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'gimnasio');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

date_default_timezone_set('America/Argentina/San_Luis');

// Constantes de Seguridad y Negocio
if (!defined('BCRYPT_COST'))          define('BCRYPT_COST', 12);
if (!defined('MAX_LOGIN_ATTEMPTS'))   define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('RATE_LIMIT_WINDOW_SEC')) define('RATE_LIMIT_WINDOW_SEC', 60);
if (!defined('ALERTA_DIAS_ALUMNO'))   define('ALERTA_DIAS_ALUMNO', 5);

// Los 4 Roles del Sistema (RBAC)
if (!defined('ROLE_SUPERADMIN'))    define('ROLE_SUPERADMIN', 'admin_general');
if (!defined('ROLE_ADMIN_GENERAL')) define('ROLE_ADMIN_GENERAL', 'admin_general');
if (!defined('ROLE_DUENO'))         define('ROLE_DUENO', 'dueno');
if (!defined('ROLE_COACH'))         define('ROLE_COACH', 'coach');
if (!defined('ROLE_ALUMNO'))        define('ROLE_ALUMNO', 'alumno');
if (!defined('ROLE_ADMIN'))         define('ROLE_ADMIN', 'admin_general'); // compatibilidad

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Consultas preparadas nativas contra SQL Injection
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Conexión - Gimnasio</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #090d16; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .error-card { background: #131b2e; border: 1px solid #ef4444; border-radius: 16px; max-width: 520px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
            h2 { color: #ef4444; margin-top: 0; }
            p { color: #cbd5e1; line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <h2>⚠️ Error de Conexión a MySQL</h2>
            <p>No se pudo establecer comunicación con el servidor MySQL en XAMPP.</p>
            <p><strong>Detalle:</strong> <?= htmlspecialchars($e->getMessage()) ?></p>
            <p>Verificá que el servicio <strong>MySQL</strong> esté iniciado en el panel de control de <strong>XAMPP</strong>.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* ============================================================
 * FUNCIONES DE SEGURIDAD, HASHING Y RATE LIMITING
 * ============================================================ */

if (!function_exists('hashPassword')) {
    /**
     * Genera hash BCrypt con Salt criptográfico automático y factor de costo 12
     */
    function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
}

if (!function_exists('verifyPassword')) {
    /**
     * Verifica contraseña plana contra hash BCrypt
     */
    function verifyPassword(string $password, ?string $hash): bool {
        if (empty($hash)) return false;
        return password_verify($password, $hash);
    }
}

if (!function_exists('getClientIp')) {
    /**
     * Obtiene la IP real del cliente
     */
    function getClientIp(): string {
        return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

if (!function_exists('checkRateLimit')) {
    /**
     * Verifica si la IP/Usuario superó el límite de intentos (Anti Fuerza Bruta)
     * Política de ventana fija: 5 intentos cada 60 segundos
     */
    function checkRateLimit(string $username, PDO $pdo): array {
        $ip = getClientIp();
        $window = (int)RATE_LIMIT_WINDOW_SEC;

        // Limpieza de intentos antiguos (> 1 hora)
        $pdo->query("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL 1 HOUR)");

        // Contar intentos fallidos en la ventana de tiempo
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total, MAX(attempt_time) AS ultimo
             FROM login_attempts
             WHERE (ip_address = :ip OR username = :u)
               AND attempt_time >= (NOW() - INTERVAL {$window} SECOND)"
        );
        $stmt->execute([':ip' => $ip, ':u' => $username]);
        $row = $stmt->fetch();
        $intentos = (int)($row['total'] ?? 0);

        if ($intentos >= MAX_LOGIN_ATTEMPTS) {
            $ultimoTs = strtotime($row['ultimo']);
            $segundosRestantes = max(1, ($ultimoTs + $window) - time());
            return [
                'bloqueado' => true,
                'intentos'  => $intentos,
                'segundos'  => $segundosRestantes,
                'mensaje'   => "Demasiados intentos fallidos. Por seguridad tu acceso ha sido bloqueado temporalmente por {$segundosRestantes} segundos."
            ];
        }

        return [
            'bloqueado' => false,
            'intentos'  => $intentos,
            'restantes' => MAX_LOGIN_ATTEMPTS - $intentos
        ];
    }
}

if (!function_exists('recordFailedAttempt')) {
    /**
     * Registra un intento fallido de inicio de sesión
     */
    function recordFailedAttempt(string $username, PDO $pdo): void {
        $ip = getClientIp();
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (:ip, :u, NOW())");
        $stmt->execute([':ip' => $ip, ':u' => $username]);
    }
}

if (!function_exists('clearLoginAttempts')) {
    /**
     * Limpia los intentos fallidos tras un inicio de sesión exitoso
     */
    function clearLoginAttempts(string $username, PDO $pdo): void {
        $ip = getClientIp();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip OR username = :u");
        $stmt->execute([':ip' => $ip, ':u' => $username]);
    }
}
