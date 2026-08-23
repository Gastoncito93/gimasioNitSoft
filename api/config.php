<?php
// Módulo API: config

    if ($action === 'config.get') {
        $rows = $pdo->query("SELECT plan, precio FROM plan_precios")->fetchAll();
        jsonOut(true, $rows);
    }

    if ($action === 'config.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $p3x    = (float)input('p3x', 0);
        $pfull  = (float)input('pfull', 0);
        $pclase = (float)input('pclase', 0);
        $pdo->prepare("REPLACE INTO plan_precios (plan, precio) VALUES ('3x', ?), ('full', ?), ('clase', ?)")
            ->execute([$p3x, $pfull, $pclase]);
        jsonOut(true, [], 'Precios actualizados');
    }

    if ($action === 'gym.get') {
        $targetId = $currentGymId ?: 1;
        $row = $pdo->query("SELECT * FROM gimnasios WHERE id=$targetId LIMIT 1")->fetch() ?: [];
        jsonOut(true, $row);
    }

    if ($action === 'gym.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $targetId = $currentGymId ?: 1;
        $nombre = trim(input('nombre', 'Gimnasio'));
        $tel    = trim(input('telefono', ''));
        $dir    = trim(input('direccion', ''));
        $code   = trim(input('invite_code', ''));
        $pdo->prepare("UPDATE gimnasios SET nombre=?, telefono=?, direccion=?, invite_code=? WHERE id=?")
            ->execute([$nombre, $tel, $dir, $code ?: null, $targetId]);
        jsonOut(true, [], 'Datos de sede guardados');
    }

    /* --- Gestión de Usuarios y Roles --- */

