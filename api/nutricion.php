<?php
// Módulo API: nutricion

    if ($action === 'nutricion.list') {
        $aluId = (int)input('alumno_id', 0);
        $sql = "SELECT pn.*, al.nombre AS alumno_nombre, p.nombre AS coach_nombre
                FROM planes_nutricionales pn
                LEFT JOIN alumnos al ON al.id = pn.alumno_id
                LEFT JOIN profesores p ON p.id = pn.coach_id
                WHERE 1=1";
        $p = [];

        if ($currentGymId) {
            $sql .= " AND pn.gimnasio_id = ?";
            $p[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND pn.alumno_id = ?";
            $p[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $sql .= " AND (pn.coach_id = ? OR al.profesor_id = ?)";
            $p[] = $profesorId ?: 0;
            $p[] = $profesorId ?: 0;
        }

        if ($aluId > 0) {
            $sql .= " AND pn.alumno_id = ?";
            $p[] = $aluId;
        }

        $sql .= " ORDER BY pn.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'nutricion.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id      = (int)input('id', 0);
        $aluId   = (int)input('alumno_id', 0);
        $coachId = hasRole(ROLE_COACH) ? $profesorId : ((int)input('coach_id', 0) ?: null);
        $titulo  = trim(input('titulo', ''));
        $cal     = (int)input('calorias_aprox', 2200);
        $det     = trim(input('detalles', ''));
        $estado  = input('estado', 'activo');
        $gymDest = $currentGymId ?: 1;

        if (!$aluId || $titulo === '' || $det === '') {
            jsonOut(false, [], 'Alumno, título y menú nutricional obligatorios');
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE planes_nutricionales SET alumno_id=?, coach_id=?, titulo=?, calorias_aprox=?, detalles=?, estado=? WHERE id=?")
                ->execute([$aluId, $coachId, $titulo, $cal, $det, $estado, $id]);
        } else {
            $pdo->prepare("INSERT INTO planes_nutricionales (gimnasio_id, alumno_id, coach_id, titulo, calorias_aprox, detalles, fecha_asignacion, estado) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$gymDest, $aluId, $coachId, $titulo, $cal, $det, hoy(), $estado]);
        }
        jsonOut(true, [], 'Plan nutricional guardado exitosamente');
    }

    if ($action === 'nutricion.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id = (int)input('id', 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM planes_nutricionales WHERE id = ?")->execute([$id]);
        }
        jsonOut(true, [], 'Plan nutricional eliminado correctamente');
    }

    /* -------------------------------------------------------------
     * DASHBOARD KPIs MULTI-TENANT (SUPERADMIN, DUEÑO, COACH, ALUMNO)
     * ------------------------------------------------------------- */

