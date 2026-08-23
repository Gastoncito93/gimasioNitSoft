<?php
/**************************************************************
 * CONFIG & SEGURIDAD - GYM PRO SaaS PLATFORM
 * Arquitectura Segura con 4 Roles
 * Admin General, Dueño, Coach, Alumno
 *
 * LOCAL:
 * XAMPP + MySQL local
 *
 * PRODUCCIÓN:
 * DonWeb + config.production.php privado
 **************************************************************/

/* ============================================================
 * DETECCIÓN DE ENTORNO
 * ============================================================ */

/*
 * Este archivo NO va a estar en GitHub.
 * Más adelante lo crearemos directamente en DonWeb.
 */
$productionConfig = __DIR__ . '/config.production.php';

/*
 * Si el archivo existe, significa que estamos en producción.
 */
$isProduction = file_exists($productionConfig);

/*
 * En DonWeb cargará las credenciales reales.
 * En tu PC este archivo no existe, por lo tanto seguirá usando XAMPP.
 */
if ($isProduction) {
    require_once $productionConfig;
}


/* ============================================================
 * SESIONES
 * ============================================================ */

if (session_status() === PHP_SESSION_NONE) {

    if (!headers_sent()) {

        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Lax');

        /*
         * En producción, cuando tengamos HTTPS,
         * la cookie de sesión solo viajará por conexión segura.
         */
        if ($isProduction) {
            ini_set('session.cookie_secure', '1');
        }

        session_start();

    } elseif (php_sapi_name() === 'cli') {

        @session_start();
    }
}


/* ============================================================
 * CONFIGURACIÓN DE BASE DE DATOS
 * ============================================================ */

/*
 * Estos valores son únicamente para tu entorno LOCAL con XAMPP.
 *
 * En DonWeb, config.production.php definirá primero:
 *
 * DB_HOST
 * DB_NAME
 * DB_USER
 * DB_PASS
 *
 * Por eso usamos !defined().
 */

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'gimnasio');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}


/* ============================================================
 * ZONA HORARIA
 * ============================================================ */

date_default_timezone_set('America/Argentina/San_Luis');


/* ============================================================
 * CONSTANTES DE SEGURIDAD Y NEGOCIO
 * ============================================================ */

if (!defined('BCRYPT_COST')) {
    define('BCRYPT_COST', 12);
}

if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

if (!defined('RATE_LIMIT_WINDOW_SEC')) {
    define('RATE_LIMIT_WINDOW_SEC', 60);
}

if (!defined('ALERTA_DIAS_ALUMNO')) {
    define('ALERTA_DIAS_ALUMNO', 5);
}


/* ============================================================
 * ROLES DEL SISTEMA (RBAC)
 * ============================================================ */

if (!defined('ROLE_SUPERADMIN')) {
    define('ROLE_SUPERADMIN', 'admin_general');
}

if (!defined('ROLE_ADMIN_GENERAL')) {
    define('ROLE_ADMIN_GENERAL', 'admin_general');
}

if (!defined('ROLE_DUENO')) {
    define('ROLE_DUENO', 'dueno');
}

if (!defined('ROLE_COACH')) {
    define('ROLE_COACH', 'coach');
}

if (!defined('ROLE_ALUMNO')) {
    define('ROLE_ALUMNO', 'alumno');
}

if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin_general');
}


/* ============================================================
 * CONEXIÓN PDO
 * ============================================================ */

try {

    $pdo = new PDO(
        'mysql:host=' . DB_HOST .
        ';dbname=' . DB_NAME .
        ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

} catch (Throwable $e) {

    http_response_code(500);

    /*
     * En producción NO mostramos información técnica
     * de MySQL al usuario.
     */
    if ($isProduction) {

        $detalleError = 'No se pudo establecer conexión con la base de datos.';
        $ayudaError = 'Intentá nuevamente en unos minutos.';

    } else {

        /*
         * En local sí mostramos el error real para poder
         * solucionar problemas con XAMPP.
         */
        $detalleError = $e->getMessage();
        $ayudaError = 'Verificá que Apache y MySQL estén iniciados en XAMPP.';
    }

    ?>
    <!DOCTYPE html>
    <html lang="es">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Error de Conexión - NITSOFT Gym</title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                font-family:
                    -apple-system,
                    BlinkMacSystemFont,
                    'Segoe UI',
                    Roboto,
                    sans-serif;

                background: #090d16;
                color: #f8fafc;

                display: flex;
                align-items: center;
                justify-content: center;

                min-height: 100vh;

                margin: 0;
                padding: 20px;
            }

            .error-card {
                background: #131b2e;
                border: 1px solid #ef4444;
                border-radius: 16px;

                max-width: 520px;
                width: 100%;

                padding: 30px;

                box-shadow:
                    0 20px 40px
                    rgba(0, 0, 0, 0.6);
            }

            h2 {
                color: #ef4444;
                margin-top: 0;
            }

            p {
                color: #cbd5e1;
                line-height: 1.6;
            }

        </style>

    </head>

    <body>

        <div class="error-card">

            <h2>⚠️ Error de conexión</h2>

            <p>
                <?= htmlspecialchars(
                    $detalleError,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <?= htmlspecialchars(
                    $ayudaError,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

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
     * Genera hash BCrypt con Salt criptográfico automático
     * y factor de costo 12.
     */
    function hashPassword(string $password): string
    {
        return password_hash(
            $password,
            PASSWORD_BCRYPT,
            [
                'cost' => BCRYPT_COST
            ]
        );
    }
}


if (!function_exists('validatePasswordStrength')) {

    /**
     * Valida la fortaleza de la contraseña:
     *
     * - Entre 8 y 20 caracteres
     * - Al menos 1 mayúscula
     * - Al menos 1 minúscula
     * - Al menos 1 número
     * - Al menos 1 símbolo especial
     */
    function validatePasswordStrength(string $password): array
    {

        $errores = [];

        $len = strlen($password);

        if ($len < 8 || $len > 20) {

            $errores[] =
                'La contraseña debe tener entre 8 y 20 caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {

            $errores[] =
                'La contraseña debe contener al menos una letra mayúscula (A-Z).';
        }

        if (!preg_match('/[a-z]/', $password)) {

            $errores[] =
                'La contraseña debe contener al menos una letra minúscula (a-z).';
        }

        if (!preg_match('/[0-9]/', $password)) {

            $errores[] =
                'La contraseña debe contener al menos un número (0-9).';
        }

        if (!preg_match('/[^a-zA-Z0-9\s]/', $password)) {

            $errores[] =
                'La contraseña debe contener al menos un símbolo especial (ej: ! @ # $ % * - _ . ? & +).';
        }

        return [
            'ok'      => empty($errores),
            'errores' => $errores,
            'mensaje' => implode(' ', $errores)
        ];
    }
}


if (!function_exists('verifyPassword')) {

    /**
     * Verifica contraseña plana contra hash BCrypt.
     */
    function verifyPassword(
        string $password,
        ?string $hash
    ): bool {

        if (empty($hash)) {
            return false;
        }

        return password_verify(
            $password,
            $hash
        );
    }
}


if (!function_exists('getClientIp')) {

    /**
     * Obtiene la IP real del cliente.
     */
    function getClientIp(): string
    {

        return
            $_SERVER['HTTP_CLIENT_IP']
            ??
            $_SERVER['HTTP_X_FORWARDED_FOR']
            ??
            $_SERVER['REMOTE_ADDR']
            ??
            '127.0.0.1';
    }
}


if (!function_exists('checkRateLimit')) {

    /**
     * Verifica si la IP/Usuario superó
     * el límite de intentos.
     *
     * 5 intentos cada 60 segundos.
     */
    function checkRateLimit(
        string $username,
        PDO $pdo
    ): array {

        $ip = getClientIp();

        $window =
            (int) RATE_LIMIT_WINDOW_SEC;


        /*
         * Eliminar intentos viejos.
         */
        $pdo->query(
            "DELETE FROM login_attempts
             WHERE attempt_time <
             (NOW() - INTERVAL 1 HOUR)"
        );


        /*
         * Contar intentos dentro de la ventana.
         */
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                MAX(attempt_time) AS ultimo

             FROM login_attempts

             WHERE
                (
                    ip_address = :ip
                    OR username = :u
                )

                AND attempt_time >=
                (
                    NOW()
                    - INTERVAL {$window} SECOND
                )"
        );


        $stmt->execute([
            ':ip' => $ip,
            ':u'  => $username
        ]);


        $row = $stmt->fetch();


        $intentos =
            (int) ($row['total'] ?? 0);


        /*
         * Bloqueo temporal.
         */
        if (
            $intentos
            >=
            MAX_LOGIN_ATTEMPTS
        ) {

            $ultimoTs =
                strtotime(
                    $row['ultimo']
                );


            $segundosRestantes =
                max(
                    1,
                    (
                        $ultimoTs
                        +
                        $window
                    )
                    -
                    time()
                );


            return [

                'bloqueado' => true,

                'intentos' =>
                    $intentos,

                'segundos' =>
                    $segundosRestantes,

                'mensaje' =>
                    "Demasiados intentos fallidos. " .
                    "Por seguridad tu acceso ha sido bloqueado " .
                    "temporalmente por {$segundosRestantes} segundos."
            ];
        }


        return [

            'bloqueado' => false,

            'intentos' =>
                $intentos,

            'restantes' =>
                MAX_LOGIN_ATTEMPTS
                -
                $intentos
        ];
    }
}


if (!function_exists('recordFailedAttempt')) {

    /**
     * Registra un intento fallido.
     */
    function recordFailedAttempt(
        string $username,
        PDO $pdo
    ): void {

        $ip =
            getClientIp();


        $stmt =
            $pdo->prepare(
                "INSERT INTO login_attempts
                (
                    ip_address,
                    username,
                    attempt_time
                )

                VALUES
                (
                    :ip,
                    :u,
                    NOW()
                )"
            );


        $stmt->execute([
            ':ip' => $ip,
            ':u'  => $username
        ]);
    }
}


if (!function_exists('clearLoginAttempts')) {

    /**
     * Limpia intentos fallidos después
     * de un login exitoso.
     */
    function clearLoginAttempts(
        string $username,
        PDO $pdo
    ): void {

        $ip =
            getClientIp();


        $stmt =
            $pdo->prepare(
                "DELETE FROM login_attempts

                 WHERE
                    ip_address = :ip
                    OR username = :u"
            );


        $stmt->execute([
            ':ip' => $ip,
            ':u'  => $username
        ]);
    }
}