<?php
// Módulo API: asistencias

    if ($action === 'asistencias.list') {
        $aluId = (int)input('alumno_id', 0);
        $sql = "SELECT asis.*, al.nombre AS alumno_nombre, al.plan, al.estado AS alumno_estado, pr.nombre AS coach_nombre
                FROM asistencias asis
                LEFT JOIN alumnos al ON al.id = asis.alumno_id
                LEFT JOIN profesores pr ON pr.id = asis.coach_id
                WHERE 1=1";
        $p = [];

        if ($currentGymId) {
            $sql .= " AND asis.gimnasio_id = ?";
            $p[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND asis.alumno_id = ?";
            $p[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $sql .= " AND (asis.coach_id = ? OR al.profesor_id = ?)";
            $p[] = $profesorId ?: 0;
            $p[] = $profesorId ?: 0;
        }

        if ($aluId > 0) {
            $sql .= " AND asis.alumno_id = ?";
            $p[] = $aluId;
        }

        $sql .= " ORDER BY asis.fecha DESC, asis.hora DESC LIMIT 100";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'asistencias.checkin') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $aluId = (int)input('alumno_id', 0);
        $obs   = trim(input('observaciones', 'Ingreso al gimnasio'));
        if (!$aluId) jsonOut(false, [], 'Seleccioná un alumno para registrar asistencia');

        $stAlu = $pdo->prepare("SELECT gimnasio_id, profesor_id, estado, fecha_vencimiento FROM alumnos WHERE id=?");
        $stAlu->execute([$aluId]);
        $aluData = $stAlu->fetch();
        if (!$aluData) jsonOut(false, [], 'Alumno no encontrado');

        $gymDest = $aluData['gimnasio_id'] ?: ($currentGymId ?: 1);
        $hora = (new DateTime())->format('H:i:s');
        $fecha = hoy();

        $pdo->prepare("INSERT INTO asistencias (alumno_id, gimnasio_id, coach_id, fecha, hora, observaciones) VALUES (?,?,?,?,?,?)")
            ->execute([$aluId, $gymDest, $aluData['profesor_id'] ?: $profesorId, $fecha, $hora, $obs]);

        jsonOut(true, [
            'alumno_estado' => $aluData['estado'],
            'vencimiento'   => $aluData['fecha_vencimiento']
        ], '¡Ingreso registrado correctamente!');
    }



