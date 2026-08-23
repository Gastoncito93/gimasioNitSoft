<?php
require_once __DIR__ . '/../proteger.php';
require_once __DIR__ . '/helpers.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$currentGymId = getEffectiveGymId();
maintainAutoStates($pdo);

$action = $_GET['ajax'] ?? $_GET['action'] ?? $_POST['action'] ?? input('action') ?? '';
if (!$action) {
    jsonOut(false, [], 'No se especificó ninguna acción');
}

// Router por dominio
if (str_starts_with($action, 'saas.') || str_starts_with($action, 'invitaciones.')) {
    require __DIR__ . '/saas.php';
} elseif (str_starts_with($action, 'asistencias.')) {
    require __DIR__ . '/asistencias.php';
} elseif (str_starts_with($action, 'rutinas.') || str_starts_with($action, 'catalogo_ejercicios.')) {
    require __DIR__ . '/rutinas.php';
} elseif (str_starts_with($action, 'nutricion.')) {
    require __DIR__ . '/nutricion.php';
} elseif (str_starts_with($action, 'dashboard.')) {
    require __DIR__ . '/dashboard.php';
} elseif (str_starts_with($action, 'alumnos.')) {
    // Alumnos and checkin/rutina routing
    if (in_array($action, ['alumnos.checkin_rutina', 'alumnos.dar_feedback_rutina', 'alumnos.historial_rutinas'])) {
        require __DIR__ . '/rutinas.php';
    } else {
        require __DIR__ . '/alumnos.php';
    }
} elseif (str_starts_with($action, 'profesores.') || str_starts_with($action, 'coach.')) {
    require __DIR__ . '/coaches.php';
} elseif (str_starts_with($action, 'pagos.')) {
    require __DIR__ . '/pagos.php';
} elseif (str_starts_with($action, 'config.') || str_starts_with($action, 'gym.')) {
    require __DIR__ . '/config.php';
} elseif (str_starts_with($action, 'usuarios.')) {
    require __DIR__ . '/usuarios.php';
} elseif (str_starts_with($action, 'reportes.')) {
    require __DIR__ . '/reportes.php';
}

jsonOut(false, [], "Acción desconocida: {$action}");