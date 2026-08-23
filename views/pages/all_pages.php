<?php
/**
 * Router de Vistas y Páginas - GYM PRO SaaS
 */
require __DIR__ . '/dashboard.php';

if (hasRole(ROLE_ADMIN_GENERAL)) {
    require __DIR__ . '/saas_gimnasios.php';
    require __DIR__ . '/saas_pagos.php';
}

if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])) {
    require __DIR__ . '/alumnos.php';
    require __DIR__ . '/profesores.php';
    require __DIR__ . '/pagos.php';
    require __DIR__ . '/reportes.php';
    require __DIR__ . '/config.php';
    if (hasRole(ROLE_ADMIN_GENERAL)) {
        require __DIR__ . '/usuarios.php';
    }
}

if (hasRole(ROLE_COACH)) {
    require __DIR__ . '/coach_alumnos.php';
    require __DIR__ . '/coach_ingresos.php';
}

if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH])) {
    require __DIR__ . '/rutinas.php';
    require __DIR__ . '/nutricion.php';
}

if (hasRole(ROLE_ALUMNO)) {
    require __DIR__ . '/mi_membresia.php';
    require __DIR__ . '/mi_rutina.php';
    require __DIR__ . '/mi_nutricion.php';
    require __DIR__ . '/mis_pagos.php';
}